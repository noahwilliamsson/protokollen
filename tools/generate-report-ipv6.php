#!/usr/bin/env php
<?php
/**
 * Determine IPv6 support on services
 */
require_once('../php/ServiceGroup.class.php');
require_once('../php/TestWwwPreferences.class.php');
require_once('../php/TestDnsAddresses.class.php');
require_once('../php/TestSslprobe.class.php');

$p = new ServiceGroup();
$idList = $p->listEntityIds();

if($argc > 1) {
		$m = $p->getMySQLHandle();
		$q = 'SELECT entity_id FROM entity_tags e, tags t WHERE e.tag_id=t.id AND t.tag=?';
		$st = $m->prepare($q) or die("$m->error, SQL: $q\n");
		$st->bind_param('s', $argv[1]);
		$st->execute() or die("$m->error, SQL: $q\n");
		$r = $st->get_result();
		$idList = array();
		while($row = $r->fetch_object())
			$idList[] = $row->entity_id;
		$r->close();
		$st->close();
}

$m = $p->getMySQLHandle();
$today = strftime('%F');
foreach($idList as $entityId) {
	$report = reportEntityIpv6($entityId);

	$q = 'SELECT id FROM reports WHERE entity_id=? AND created=?';
	$st = $m->prepare($q);
	$st->bind_param('is', $entityId, $today);
	$st->execute();
	$r = $st->get_result();
	$row = $r->fetch_object();
	$r->close();
	$st->close();
	if($row !== NULL) {
		$id = $row->id;
	}
	else {
		$q = 'INSERT INTO reports SET entity_id=?, created=?';
		$st = $m->prepare($q);
		$st->bind_param('is', $entityId, $today);
		$st->execute();
		$id = $st->insert_id;
		$st->close();
	}

	$q = 'UPDATE reports SET dnssec=?,
			ns_total=?, ns_ipv4=?, ns_ipv6=?,
			mx_total=?, mx_ipv4=?, mx_ipv6=?, mx_starttls=?,
			web_total=?, web_ipv4=?, web_ipv6=?,
			https=? WHERE id=?';
	$st = $m->prepare($q);
	$st->bind_param('iiiiiiiiiiisi', $report->dnssec,
					$report->ns->total, $report->ns->ipv4, $report->ns->ipv6,
					$report->mx->total, $report->mx->ipv4, $report->mx->ipv6,
					$report->mx->starttls, $report->web->total,
					$report->web->ipv4, $report->web->ipv6, $report->https,
					$id);
	$st->execute();
	$st->close();

	$e = $p->getEntityById($entityId);
	echo "$e->domain: ". json_encode($report) ."\n";
}

function reportEntityIpv6($entityId) {
	$report = (object)array(
		'dnssec' => 0,
		'ns' => (object)array(
			'total' => 0,
			'ipv4' => 0,
			'ipv6' => 0,
		),
		'mx' => (object)array(
			'total' => 0,
			'ipv4' => 0,
			'ipv6' => 0,
			'starttls' => 0,
		),
		'web' => (object)array(
			'total' => 0,
			'ipv4' => 0,
			'ipv6' => 0,
		),
		'https' => 'no',
	);

	$p = new ServiceGroup();
	$e = $p->getEntityById($entityId);
	$testDns = new TestDnsAddresses();
	foreach($p->listServices($e->id, ProtokollenBase::SERVICE_TYPE_DNS) as $svc) {
		$grp = $p->getServiceGroup($svc->id);
		if($grp === NULL)
			break;
		$item = $testDns->getItem($svc->id, $grp->id);
		if(!$item)
			continue;

		foreach($item->json->records as $hostname => $obj) {
			$report->ns->total++;
			if(count($obj->a))
				$report->ns->ipv4++;
			if(count($obj->aaaa))
				$report->ns->ipv6++;
		}
	}

	foreach($p->listServices($e->id, ProtokollenBase::SERVICE_TYPE_SMTP) as $svc) {
		$grp = $p->getServiceGroup($svc->id);
		if($grp === NULL)
			break;

		$item = $testDns->getItem($svc->id, $grp->id);
		if(!$item)
			continue;

		foreach($item->json->records as $hostname => $obj) {
			$report->mx->total++;
			if(count($obj->a))
				$report->mx->ipv4++;
			if(count($obj->aaaa))
				$report->mx->ipv6++;
		}

		$test = new TestSslprobe();
		foreach($grp->json as $svcHost) {
			$numIps = 0;
			$numIpsWithStarttls = 0;
			$item = $test->getItem($svc->id, $grp->id, $svcHost->hostname);
			if($item === NULL)
				continue;

			foreach($item->json as $probe) {
				$numIps++;
				foreach($probe->protocols as $proto) {
					/* Ignore SSLv2 in STARTTLS */
					if($proto->version < 768)
						continue;
					if(!$proto->supported)
						continue;
					$numIpsWithStarttls++;
					break;
				}
			}

			if($numIps > 0 && $numIps === $numIpsWithStarttls)
				$report->mx->starttls++;
		}
	}

	$webUrls = array();
	foreach($p->listServices($e->id) as $svc) {
		if($svc->service_type !== ProtokollenBase::SERVICE_TYPE_HTTP
			&& $svc->service_type !==  ProtokollenBase::SERVICE_TYPE_HTTPS)
			continue;

		$grp = $p->getServiceGroup($svc->id);
		if($grp === NULL)
			continue;

		/* Attempt to find www. host */
		$hasWww = FALSE;
		foreach($grp->json as $svcHost) {
			if(!preg_match('@^www.@', $svcHost->hostname))
				continue;
			$hasWww = TRUE;
			break;
		}

		if(!$hasWww) {
			continue;
		}

		/* Make sure WWW service is valid */
		$test = new TestWwwPreferences();
		$item = $test->getItem($svc->id, $grp->id);
		if(!$item)
			continue;
		$obj = $item->json;
		if(!isset($obj->preferred))
			continue;
		$webUrls[] = $obj->preferred->url;
	}

	$hostnames = array();
	$schemes = array();
	foreach($webUrls as $url) {
		$hostnames[] = parse_url($url, PHP_URL_HOST);
		$schemes[] = parse_url($url, PHP_URL_SCHEME);
	}

	foreach(array_unique($hostnames) as $hostname) {
		$report->web->total++;
		$rrset = dns_get_record($hostname, DNS_A);
		if(count($rrset))
			$report->web->ipv4++;
		$rrset = dns_get_record($hostname, DNS_AAAA);
		if(count($rrset))
			$report->web->ipv6++;
	}

	$schemes = array_unique($schemes);
	if(count($schemes) === 2)
		$report->https = 'partial';
	else if(count($schemes) && $schemes[0] === 'https')
		$report->https = 'yes';

	return $report;
}

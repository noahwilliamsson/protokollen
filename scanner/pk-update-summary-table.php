#!/usr/bin/env php
<?php
/**
 * Update summary table (reports) from scan data
 */
require_once('../php/ServiceGroup.class.php');
require_once('../php/TestWwwPreferences.class.php');
require_once('../php/TestDnsAddresses.class.php');
require_once('../php/TestDnssecStatus.class.php');
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

	$q = 'UPDATE reports SET
			ns_total=?, ns_ipv4=?, ns_ipv6=?, ns_dnssec=?,
			mx_total=?, mx_ipv4=?, mx_ipv6=?, mx_dnssec=?,
			mx_starttls=?,
			mx_ip_total=?, mx_ip_country_se=?, mx_ip_country_other=?,
			mx_ip_country_unknown=?, mx_ip_starttls=?, mx_ip_starttls_pfs=?,
			mx_ip_starttls_sslv2=?, mx_ip_starttls_sslv3=?,
			mx_ip_starttls_tlsv1=?, mx_ip_starttls_tlsv1_1=?,
			mx_ip_starttls_tlsv1_2=?,
			web_total=?, web_ipv4=?, web_ipv6=?, web_dnssec=?,
			https=? WHERE id=?';
	$st = $m->prepare($q) or die("ERROR: $m->error, SQL: $q\n");
	$st->bind_param('iiiiiiiiiiiiiiiiiiiiiiiisi',
					$report->ns->total, $report->ns->ipv4,
					$report->ns->ipv6, $report->ns->dnssec,
					$report->mx->total, $report->mx->ipv4,
					$report->mx->ipv6, $report->mx->dnssec,
					$report->mx->starttls,
					$report->mx->ip->total, $report->mx->ip->country_se,
					$report->mx->ip->country_other,
					$report->mx->ip->country_unknown,
					$report->mx->ip->starttls, $report->mx->ip->starttls_pfs,
					$report->mx->ip->starttls_sslv2,
					$report->mx->ip->starttls_sslv3,
					$report->mx->ip->starttls_tlsv1,
					$report->mx->ip->starttls_tlsv1_1,
					$report->mx->ip->starttls_tlsv1_2,
					$report->web->total, $report->web->ipv4,
					$report->web->ipv6, $report->web->dnssec,
					$report->https, $id);
	$st->execute();
	$st->close();

	$e = $p->getEntityById($entityId);
	echo "$e->domain: ". json_encode($report) ."\n";
}

function reportEntityIpv6($entityId) {
	$report = (object)array(
		'ns' => (object)array(
			'total' => 0,
			'ipv4' => 0,
			'ipv6' => 0,
			'dnssec' => 0,
		),
		'mx' => (object)array(
			'total' => 0,
			'ipv4' => 0,
			'ipv6' => 0,
			'dnssec' => 0,
			'starttls' => 0,
			'ip' => (object)array(
				'total' => 0,
				'country_se' => 0,
				'country_other' => 0,
				'country_unknown' => 0,
				'starttls' => 0,
				'starttls_sslv2' => 0,
				'starttls_sslv3' => 0,
				'starttls_tlsv1' => 0,
				'starttls_tlsv1_1' => 0,
				'starttls_tlsv1_2' => 0,
				'starttls_pfs' => 0,
			)
		),
		'web' => (object)array(
			'total' => 0,
			'ipv4' => 0,
			'ipv6' => 0,
			'dnssec' => 0,
		),
		'https' => 'no',
	);

	$p = new ServiceGroup();
	$e = $p->getEntityById($entityId);
	$testDns = new TestDnsAddresses();
	$testDnssec = new TestDnssecStatus();
	$testSslprobe = new TestSslprobe();
	$testWww = new TestWwwPreferences();

	foreach($p->listServices($e->id, ProtokollenBase::SERVICE_TYPE_DNS) as $svc) {
		$grp = $p->getServiceGroup($svc->id);
		if($grp === NULL)
			break;

		$item = $testDns->getItem($svc->id, $grp->id);
		if($item) foreach($item->data->records as $hostname => $obj) {
			$report->ns->total++;
			if(count($obj->a))
				$report->ns->ipv4++;
			if(count($obj->aaaa))
				$report->ns->ipv6++;
		}

		$item = $testDnssec->getItem($svc->id, $grp->id);
		if($item) foreach($item->data as $hostname => $obj) {
			if($obj->secure)
				$report->ns->dnssec++;
		}
	}

	foreach($p->listServices($e->id, ProtokollenBase::SERVICE_TYPE_SMTP) as $svc) {
		$grp = $p->getServiceGroup($svc->id);
		if($grp === NULL)
			break;

		$item = $testDns->getItem($svc->id, $grp->id);
		if($item) foreach($item->data->records as $hostname => $obj) {
			$report->mx->total++;
			if(count($obj->a))
				$report->mx->ipv4++;
			if(count($obj->aaaa))
				$report->mx->ipv6++;
		}

		$item = $testDnssec->getItem($svc->id, $grp->id);
		if($item) foreach($item->data as $hostname => $obj) {
			if($obj->secure)
				$report->mx->dnssec++;
		}

		foreach($grp->data as $svcHost) {
			$numIps = 0;
			$numIpsWithStarttls = 0;

			$item = $testSslprobe->getItem($svc->id, $grp->id, $svcHost->hostname);
			if($item === NULL)
				continue;

			$numConnections = 0;
			foreach($item->data as $probe) {
				$numIps++;
				$report->mx->ip->total++;
				if(strstr($probe->ip, ':')) {
					/* No support for IPv6 addresses yet */
					$report->mx->ip->country_unknown++;
				}
				else {
					$ccTLD = geoip_country_code_by_name($probe->ip);
					if(!strcmp($ccTLD, 'SE'))
						$report->mx->ip->country_se++;
					else
						$report->mx->ip->country_other++;
				}

				$haveStarttls = FALSE;
				$havePfs = FALSE;
				foreach($probe->protocols as $proto) {
					/* Note that SMTP works */
					if($proto->establishedConnections > 0)
						$numConnections++;

					/* Ignore probes that produced an error */
					if($proto->lastError !== NULL)
						continue;

					if(!$proto->supported)
						continue;

					switch($proto->version) {
					case 2: $report->mx->ip->starttls_sslv2++; break;
					case 768: $report->mx->ip->starttls_sslv3++; break;
					case 769: $report->mx->ip->starttls_tlsv1++; break;
					case 770: $report->mx->ip->starttls_tlsv1_1++; break;
					case 771: $report->mx->ip->starttls_tlsv1_2++; break;
					default: break;
					}

					foreach($proto->cipherSuites as $cs) {
						if(strstr($cs->name, 'DHE') || strstr($cs->name, 'EDH')) {
							$havePfs = TRUE;
							break;
						}
					}

					$haveStarttls = TRUE;;
				}

				if($haveStarttls) {
					$report->mx->ip->starttls++;
					$numIpsWithStarttls++;
				}

				if($havePfs)
					$report->mx->ip->starttls_pfs++;
			}

			/* Note that all IP addresses for this hostname support STARTTLS */
			if($numIps > 0 && $numIps === $numIpsWithStarttls)
				$report->mx->starttls++;
		}
	}

	$webUrls = array();
	$webDnssec = array();
	foreach($p->listServices($e->id) as $svc) {
		if($svc->service_type !== ProtokollenBase::SERVICE_TYPE_HTTP
			&& $svc->service_type !==  ProtokollenBase::SERVICE_TYPE_HTTPS)
			continue;

		$grp = $p->getServiceGroup($svc->id);
		if($grp === NULL)
			continue;

		/* Check DNSSEC status on web service */
		$item = $testDnssec->getItem($svc->id, $grp->id);
		if($item) foreach($item->data as $hostname => $obj) {
			$webDnssec[$hostname] = $obj->secure;
		}

		/* Attempt to find www. host */
		$hasWww = FALSE;
		foreach($grp->data as $svcHost) {
			if(!preg_match('@^www.@', $svcHost->hostname))
				continue;
			$hasWww = TRUE;
			break;
		}

		if(!$hasWww)
			continue;

		/* Make sure WWW service is valid */
		$item = $testWww->getItem($svc->id, $grp->id);
		if(!$item)
			continue;
		$obj = $item->data;
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
		/**
		 * Because the above loop consider the final URLs and not
		 * the actual service hosts, this won't work in the case
		 * when the service group {exampl.com, www.example.com}
		 * have a final URL of http://some-other-zone.com
		 */
		$report->web->total++;
		if(isset($webDnssec[$hostname])) {
			if($webDnssec[$hostname])
				$report->web->dnssec++;
		}

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

#!/usr/bin/env php
<?php
/**
 * Protokollen - create services and add hostnames from entity definitions
 */

require_once('../php/ProtokollenBase.class.php');
require_once('../php/ServiceGroup.class.php');
require_once('../php/ServiceSet.class.php');


$p = new ProtokollenBase();
$sg = new ServiceGroup();
$ss = new ServiceSet();

foreach($p->listEntityDomains() as $domain) {
	/* Load entity */
	$e = $p->getEntityByDomain($domain);

	/* Add HTTP service */
	echo "Creating HTTP service: $e->domain\n";
	$svcIdHttp = $p->addService($e->id, ProtokollenBase::SERVICE_TYPE_HTTP,
				$e->domain, 'Webbsajt '. $e->domain .' (HTTP)');

	/* Compile HTTP service set */
	$hostnames = array();
	$arr = array($e->domain, 'www.'. $e->domain);
	foreach($arr as $hostname) {
		foreach(dns_get_record($hostname, DNS_A) as $rr)
			$hostnames[] = $hostname;
		foreach(dns_get_record($hostname, DNS_AAAA) as $rr)
			$hostnames[] = $hostname;
	}

	$hostnames = array_unique($hostnames);
	if(!empty($hostnames)) {

		/* Add HTTP service set */
		$ss->addServiceSet($svcIdHttp, 'http', $hostnames);
		echo "- HTTP: ". implode(', ', $hostnames) ."\n";

		/* HTTPS may not be supported yet, but we'll keep an eye out */
		echo "Creating HTTPS service: $e->domain\n";
		$svcIdHttps = $p->addService($e->id, ProtokollenBase::SERVICE_TYPE_HTTPS,
					$e->domain, 'Webbsajt '. $e->domain
					.' (HTTPS)');
		$ss->addServiceSet($svcIdHttps, 'https', $hostnames);
		echo "- HTTPS: ". implode(', ', $hostnames) ."\n";
	}


	$groupHttp = array();
	foreach($hostnames as $hostname) {
		$obj = new stdClass();
		$obj->hostname = $hostname;
		$obj->port = 80;
		$obj->prio = 0;
		$obj->protocol = 'http';
		$groupHttp[] = $obj;
	}

	if(!empty($groupHttp))
		$sg->addServiceGroup($svcIdHttp, $groupHttp);


	$groupHttps = array();
	foreach($hostnames as $hostname) {
		$obj = new stdClass();
		$obj->hostname = $hostname;
		$obj->port = 443;
		$obj->prio = 0;
		$obj->protocol = 'https';
		$groupHttps[] = $obj;
	}

	if(!empty($groupHttps))
		$sg->addServiceGroup($svcIdHttps, $groupHttps);


	/* DNS: Create service and service set (old)  */
	$rrset = array();
	foreach(dns_get_record($e->domain, DNS_SOA) as $unused)
		$rrset = dns_get_record($e->domain, DNS_NS);

	if(!empty($rrset)) {
		$svcIdDns = $p->addService($e->id, ProtokollenBase::SERVICE_TYPE_DNS,
				$e->domain, 'DNS-zon '. $e->domain);
		echo "Creating DNS service: $e->domain\n";

		$hostnames = array();
		foreach($rrset as $rr)
			$hostnames[] = $rr['target'];
		if(!empty($hostnames)) {
			$ss->addServiceSet($svcIdDns, 'dns', $hostnames);
			echo "- DNS: ". implode(', ', $hostnames) ."\n";
		}
	}

	/* DNS: Create service and service group */
	$hostnames = array();
	$groupDns = array();
	foreach($rrset as $rr) {
		$obj = new stdClass();
		$obj->hostname = $rr['target'];
		$obj->port = 53;
		$obj->prio = 0;
		$obj->protocol = 'dns';
		$groupDns[] = $obj;
		$hostnames[] = $rr['target'];
	}

	if(!empty($groupDns)) {
		$svcIdDns = $p->addService($e->id, ProtokollenBase::SERVICE_TYPE_DNS,
				$e->domain, 'DNS-zon '. $e->domain);
		echo "Creating DNS service: $e->domain\n";

		$sg->addServiceGroup($svcIdDns, $groupDns);
		echo "- DNS: ". implode(', ', $hostnames) ."\n";
	}


	if($e->domain_email === NULL)
		continue;

	/* SMTP: Create service and service set (old) */
	$rrset = dns_get_record($e->domain, DNS_MX);

	$hostnames = array();
	foreach($rrset as $rr)
		$hostnames[] = $rr['target'];
	if(!empty($hostnames)) {
		echo "Creating SMTP service: $e->domain_email\n";
		$svcIdSmtp = $p->addService($e->id, ProtokollenBase::SERVICE_TYPE_SMTP, $e->domain_email,
				'E-postdomän '. $e->org);
		$ss->addServiceSet($svcIdSmtp, 'smtp', $hostnames);
		echo "- SMTP: ". implode(', ', $hostnames) ."\n";
	}

	/* SMTP: Create service and service group */
	$groupSmtp = array();
	$hostnames = array();
	foreach($rrset as $rr) {
		$obj = new stdClass();
		$obj->hostname = $rr['target'];
		$obj->port = 25;
		$obj->prio = $rr['pri'];
		$obj->protocol = 'smtp';
		$groupSmtp[] = $obj;
		$hostnames[] = $rr['target'];
	}

	if(!empty($groupSmtp)) {
		echo "Creating SMTP service: $e->domain_email\n";
		$svcIdSmtp = $p->addService($e->id, ProtokollenBase::SERVICE_TYPE_SMTP, $e->domain_email,
				'E-postdomän '. $e->org);
		$sg->addServiceGroup($svcIdSmtp, $groupSmtp);
		echo "- SMTP: ". implode(', ', $hostnames) ."\n";
	}
}

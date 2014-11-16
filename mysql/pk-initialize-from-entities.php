#!/usr/bin/env php
<?php
/**
 * Protokollen - create services and add service groups
 */

require_once('../php/ServiceGroup.class.php');


$p = new ProtokollenBase();
$sg = new ServiceGroup();

foreach($p->listEntityDomains() as $domain) {
	/* Load entity */
	$e = $p->getEntityByDomain($domain);

	/* Compile list of web hostnames */
	$hostnames = array();
	$arr = array($e->domain, 'www.'. $e->domain);
	foreach($arr as $hostname) {
		foreach(dns_get_record($hostname, DNS_A) as $rr)
			$hostnames[] = $hostname;
		foreach(dns_get_record($hostname, DNS_AAAA) as $rr)
			$hostnames[] = $hostname;
	}

	$hostnames = array_unique($hostnames);


	/* HTTP: Create service and service group */
	$groupHttp = array();
	foreach($hostnames as $hostname) {
		$obj = new stdClass();
		$obj->hostname = $hostname;
		$obj->port = 80;
		$obj->prio = 0;
		$obj->protocol = 'http';
		$groupHttp[] = $obj;
	}

	if(!empty($groupHttp)) {
		/* Add HTTP service */
		echo "Creating HTTP service: $e->domain\n";
		$svcIdHttp = $p->addService($e->id, ProtokollenBase::SERVICE_TYPE_HTTP,
					$e->domain, 'Webbsajt '. $e->domain .' (HTTP)');

		echo "- HTTP: ". implode(', ', $hostnames) ."\n";
		$sg->addServiceGroup($svcIdHttp, $groupHttp);
	}


	/* HTTPS: Create service and service group */
	$groupHttps = array();
	foreach($hostnames as $hostname) {
		$obj = new stdClass();
		$obj->hostname = $hostname;
		$obj->port = 443;
		$obj->prio = 0;
		$obj->protocol = 'https';
		$groupHttps[] = $obj;
	}

	if(!empty($groupHttps)) {
		/* HTTPS may not be supported yet, but we'll keep an eye out */
		echo "Creating HTTPS service: $e->domain\n";
		$svcIdHttps = $p->addService($e->id, ProtokollenBase::SERVICE_TYPE_HTTPS,
					$e->domain, 'Webbsajt '. $e->domain
					.' (HTTPS)');

		echo "- HTTPS: ". implode(', ', $hostnames) ."\n";
		$sg->addServiceGroup($svcIdHttps, $groupHttps);
	}


	/* DNS: Create service and service group */
	$rrset = array();
	foreach(dns_get_record($e->domain, DNS_SOA) as $unused)
		$rrset = dns_get_record($e->domain, DNS_NS);

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
		echo "Creating DNS service: $e->domain\n";
		$svcIdDns = $p->addService($e->id, ProtokollenBase::SERVICE_TYPE_DNS,
				$e->domain, 'DNS-zon '. $e->domain);

		echo "- DNS: ". implode(', ', $hostnames) ."\n";
		$sg->addServiceGroup($svcIdDns, $groupDns);
	}


	if($e->domain_email === NULL)
		continue;


	/* SMTP: Create service and service group */
	$groupSmtp = array();
	$hostnames = array();
	$rrset = dns_get_record($e->domain, DNS_MX);
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

		echo "- SMTP: ". implode(', ', $hostnames) ."\n";
		$sg->addServiceGroup($svcIdSmtp, $groupSmtp);
	}
}

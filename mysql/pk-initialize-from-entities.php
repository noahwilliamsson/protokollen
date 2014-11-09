#!/usr/bin/env php
<?php
/**
 * Protokollen - create services and add hostnames from entity definitions
 */

require_once('../php/Protokollen.class.php');


$p = new Protokollen();

foreach($p->listEntityDomains() as $domain) {
	/* Load entity */
	$e = $p->getEntityByDomain($domain);

	/* Add HTTP service */
	echo "Creating HTTP service: $e->domain\n";
	$svcId = $p->addService($e->id, Protokollen::SERVICE_TYPE_HTTP,
				$e->domain, 'Webbsajt '. $e->org .' (HTTP)');

	/* Compile HTTP service set */
	$hostnames = array();
	$arr = array($e->domain, 'www.'. $e->domain);
	foreach($arr as $hostname) {
		foreach(dns_get_record($hostname, DNS_A) as $rr)
			$hostnames[] = $hostname;
		foreach(dns_get_record($hostname, DNS_AAAA) as $rr)
			$hostnames[] = $hostname;
	}

	if(!empty($hostnames)) {
		$hostnames = array_unique($hostnames);

		/* Add HTTP service set */
		$p->addServiceSet($svcId, 'HTTP', $hostnames);
		echo "- HTTP: ". implode(', ', $hostnames) ."\n";


		/* HTTPS may not be supported yet, but we'll keep an eye out */
		echo "Creating HTTPS service: $e->domain\n";
		$svcId = $p->addService($e->id, Protokollen::SERVICE_TYPE_HTTPS,
					$e->domain, 'Webbsajt '. $e->org
					.' (HTTPS)');
		$p->addServiceSet($svcId, 'HTTPS', $hostnames);
		echo "- HTTPS: ". implode(', ', $hostnames) ."\n";
	}


	/* DNS */
	foreach(dns_get_record($e->domain, DNS_SOA) as $rr) {
		$svcId = $p->addService($e->id, Protokollen::SERVICE_TYPE_DNS,
				$e->domain, 'DNS-zon '. $e->domain);

		$hostnames = array();
		foreach(dns_get_record($e->domain, DNS_NS) as $rr)
			$hostnames[] = $rr['target'];
		if(!empty($hostnames))
			$p->addServiceSet($svcId, 'DNS', $hostnames);
	}


	/* SMTP */
	if($e->domain_email === NULL)
		continue;

	$hostnames = array();
	foreach(dns_get_record($e->domain_email, DNS_MX) as $rr)
		$hostnames[] = $rr['target'];
	if(!empty($hostnames)) {
		$svcId = $p->addService($e->id, 'SMTP', $e->domain_email,
				'E-postdomän '. $e->org);
		$p->addServiceSet($svcId, 'SMTP', $hostnames);
	}
}

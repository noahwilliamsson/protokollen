#!/usr/bin/env php
<?php
/**
 * Protokollen - update service sets from DNS and dump data as TSV
 * For use with the updating script
 */

require_once('../php/Protokollen.class.php');


if($argc < 2)
	die("Usage: ${argv[0]} <service type>\n");

$serviceType = $argv[1];


$p = new Protokollen();
$entities = $p->listEntityIds();

/* Randomize order */
shuffle($entities);
foreach($entities as $entityId) {
	$httpServices = $p->listServices($entityId, $serviceType);
	foreach($httpServices as $svc) {
		/* Update service set */
		switch($svc->service_type) {
		case Protokollen::SERVICE_TYPE_SMTP:
			$hostnames = array();
			$rr = dns_get_record($svc->service_name, DNS_MX);
			foreach($rr as $r)
				$hostnames[] = $r['target'];
			if(empty($hostnames))
				break;
			$p->addServiceSet($svc->id, $svc->service_type,
						$hostnames);
			break;
		case Protokollen::SERVICE_TYPE_DNS:
			$hostnames = array();
			$rr = dns_get_record($svc->service_name, DNS_NS);
			foreach($rr as $r)
				$hostnames[] = $r['target'];
			if(empty($hostnames))
				break;
			$p->addServiceSet($svc->id, $svc->service_type,
						$hostnames);
			break;
		default:
			break;
		}

		if(($ss = $p->getServiceSet($svc->id)) === NULL) {
			/* Empty set */
			continue;
		}

		$args = array($entityId, $svc->id, $svc->service_type, $ss->id);

		$jsonRow = $p->getJsonByHash($svc->id, $ss->json_sha256);
		$svcHosts = json_decode($jsonRow->json);
		foreach($svcHosts as $svcHost) {
			$args[] = $svcHost->protocol;
			$args[] = $svcHost->hostname;
			$args[] = $svcHost->port;
		}

		echo implode("\t", $args) ."\n";
	}
}
#!/usr/bin/env php
<?php
/**
 * Protokollen - add service to entity
 */

require_once('../php/Protokollen.class.php');


if($argc < 4)
	die("Usage: ${argv[0]} <base domain> <service type> <service name> [hostname]\n");

$domain = trim($argv[1], '.');
$serviceType = trim($argv[2], '.');
$serviceName = trim($argv[3], '.');
$hostname = $serviceName;
if($argc == 5)
	$hostname = trim($argv[4], '.');

$p = new Protokollen();
$e = $p->getEntityByDomain($domain);

$svc = $p->getServiceByName($e->entity_id, $serviceType, $serviceName);
if($svc === NULL) {
	$svc = $p->addService($e->entity_id, $serviceType, $serviceName);
	echo "Added new service '$serviceName' with type $serviceType and ID: $svc->service_id\n";
}
$hostnames = $p->listServiceHostnames($svc->service_id);
$found = FALSE;
foreach($hostnames as $h) {
	if(!strcasecmp($h->hostname, $hostname)) {
		echo "Hostname '$hostname' is already known for this service\n";
		$found = true;
		break;
	}
}

if(!$found) {
	$id = $p->addServiceHostname($svc->service_id, $hostname);
	echo "Hostname '$hostname' added as ID $id as child under domain '$domain' (entity id: $e->entity_id)\n";
}

#!/usr/bin/env php
<?php
/**
 * Protokollen - add service to entity
 */

require_once('../php/ServiceGroup.class.php');
mb_internal_encoding('utf-8');

if($argc < 4)
	die("Usage: ${argv[0]} <base domain> <service type> <service name[:port]> [hostname[:port]]\n");

$domain = trim($argv[1], '.');
$serviceType = strtoupper($argv[2]);
$serviceName = trim($argv[3], '.');

$hosts = array();
$hostnames = array();
for($i = 3; $i < $argc; $i++) {
	$obj = new stdClass();
	$obj->hostname = trim(mb_convert_case($argv[$i], MB_CASE_LOWER), '.');
	$proto = strtolower($serviceType);
	if($proto === 'webmail')
		$proto = 'https';
	switch($proto) {
	case 'http': $obj->port = 80; break;
	case 'smtp': $obj->port = 25; break;
	case 'https': $obj->port = 443; break;
	case 'dns': $obj->port = 53; break;
	case 'ircs': $obj->port = 6697; break;
	default: die("Unsupported protocol '$proto'\n"); break;
	}
	$obj->prio = 0;
	$obj->protocol = $proto;

	$arr = explode(':', $obj->hostname);
	if(count($arr) === 2) {
		$obj->hostname = $arr[0];
		$obj->port = intval($arr[1]);
		if($obj->port <= 0)
			die("Invalid port: ${arr[1]}\n");
	}
	$hostnames[] = $obj->hostname .':'. $obj->port;
	$hosts[] = $obj;
}

$p = new ServiceGroup();
$e = $p->getEntityByDomain($domain);
if(!$e)
	die("Unknown base domain: $domain\n");

$svc = $p->getServiceByName($e->id, $serviceType, $serviceName);
if($svc === NULL) {
	$svcId = $p->addService($e->id, $serviceType, $serviceName);
	$svc = $p->getServiceById($svcId);
	echo "- Created new '$serviceType' service '$serviceName' with ID: $svc->id\n";
}

$grp = $p->getServiceGroup($svc->id);
if($grp !== NULL) {
	die("Service group already exists with ID $grp->id\n");
}

$grpId = $p->addServiceGroup($svc->id, $hosts);
$grp = $p->getServiceGroup($svc->id, $grpId);
echo "- Added service group [". implode(', ', $hostnames) ."] with ID: $grp->id\n";

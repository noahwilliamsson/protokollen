#!/usr/bin/env php
<?php
/**
 * Protokollen - list services as Tab-Separated Values
 * For use with the updating script
 */
require_once('../php/Protokollen.class.php');

$p = new Protokollen();
$entities = $p->listEntityIds();
foreach($entities as $entityId) {
	$httpServices = $p->listServicesByType($entityId, Protokollen::SERVICE_TYPE_HTTP);
	foreach($httpServices as $svc) {
		$hostnames = $p->listServiceHostnames($svc->service_id);
		$withoutWww = array();
		foreach($hostnames as $h)
			$withoutWww[] = str_replace('www.', '', $h->hostname);
		$hostnames = array_unique($withoutWww);
		echo sprintf("%d\t%d\t%s\t%s\n", $entityId, $svc->service_id, $svc->service_type, implode(', ', $hostnames));
	}

	$httpServices = $p->listServicesByType($entityId, Protokollen::SERVICE_TYPE_WEBMAIL);
	foreach($httpServices as $svc) {
		$hostnames = $p->listServiceHostnames($svc->service_id);
		$withoutWww = array();
		foreach($hostnames as $h)
			$withoutWww[] = str_replace('www.', '', $h->hostname);
		$hostnames = array_unique($withoutWww);
		echo sprintf("%d\t%d\t%s\t%s\n", $entityId, $svc->service_id, $svc->service_type, implode(', ', $hostnames));
	}
}

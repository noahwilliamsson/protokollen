#!/usr/bin/env php
<?php
/**
 * Protokollen - list services as Tab-Separated Values
 * For use with the updating script
 */
require_once('../php/Protokollen.class.php');

$p = new Protokollen();
$entities = $p->listEntityIds();

/* Randomize order */
shuffle($entities);
foreach($entities as $entityId) {
	$httpServices = $p->listServices($entityId, Protokollen::SERVICE_TYPE_HTTP);
	foreach($httpServices as $svc) {
		$hostnames = $p->listServiceHostnames($svc->id);
		$withoutWww = array();
		foreach($hostnames as $h)
			$withoutWww[] = str_replace('www.', '', $h->hostname);
		$hostnames = array_unique($withoutWww);
		echo sprintf("%d\t%d\t%s\t%s\n", $entityId, $svc->id, $svc->service_type, implode(', ', $hostnames));
	}

	$httpServices = $p->listServices($entityId, Protokollen::SERVICE_TYPE_WEBMAIL);
	foreach($httpServices as $svc) {
		$hostnames = $p->listServiceHostnames($svc->id);
		$withoutWww = array();
		foreach($hostnames as $h)
			$withoutWww[] = str_replace('www.', '', $h->hostname);
		$hostnames = array_unique($withoutWww);
		echo sprintf("%d\t%d\t%s\t%s\n", $entityId, $svc->id, $svc->service_type, implode(', ', $hostnames));
	}
}

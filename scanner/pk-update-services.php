#!/usr/bin/env php
<?php
/**
 * Protokollen - update service sets from DNS and dump data as TSV
 * For use with the updating script
 */

require_once('../php/ProtokollenBase.class.php');
require_once('../php/ServiceGroup.class.php');

mb_internal_encoding('utf-8');


if($argc < 2)
	die("Usage: ${argv[0]} <service type>\n");

$serviceType = $argv[1];


$p = new ProtokollenBase();
$sg = new ServiceGroup();
$entities = $p->listEntityIds();

$header = array('Entity ID', 'Service ID', 'Service Type', 'Service group ID', 'Protocol #1', 'Hostname #1', 'Port #1', '...');
echo implode("\t", $header) ."\n";

/* Randomize order */
shuffle($entities);
foreach($entities as $entityId) {
	$services = $p->listServices($entityId, $serviceType);
	foreach($services as $svc) {
		/* Update service set */
		switch($svc->service_type) {
		case ProtokollenBase::SERVICE_TYPE_SMTP:
			$hostnames = array();
			$group = array();
			foreach(dns_get_record($svc->service_name, DNS_MX) as $rr) {
				$obj = new stdClass();
				$obj->hostname = $rr['target'];
				$obj->port = 25;
				$obj->prio = $rr['pri'];
				$obj->protocol = 'smtp';
				$group[] = $obj;

				$hostnames[] = $rr['target'];
			}

			if(!empty($group))
				$sg->addServiceGroup($svc->id, $group);
			break;
		case ProtokollenBase::SERVICE_TYPE_DNS:
			$hostnames = array();
			$group = array();
			foreach(dns_get_record($svc->service_name, DNS_NS) as $rr) {
				$obj = new stdClass();
				$obj->hostname = $rr['target'];
				$obj->port = 53;
				$obj->prio = 0;
				$obj->protocol = 'dns';
				$group[] = $obj;

				$hostnames[] = $rr['target'];
			}

			if(!empty($group))
				$sg->addServiceGroup($svc->id, $group);
			break;
		default:
			break;
		}

		if(($grp = $sg->getServiceGroup($svc->id)) === NULL) {
			/* Empty set */
			continue;
		}

		$args = array($entityId, $svc->id, $svc->service_type, $grp->id);
		foreach($grp->data as $svcHost) {
			$args[] = strtolower($svcHost->protocol);
			$args[] = mb_convert_case($svcHost->hostname, MB_CASE_LOWER);
			$args[] = $svcHost->port;
		}

		echo implode("\t", $args) ."\n";
	}
}

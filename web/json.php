<?php
/**
 * Output entity data as JSON
 */

require_once('../php/ServiceGroup.class.php');
require_once('../php/TestWwwPreferences.class.php');
require_once('../php/TestSslprobe.class.php');
require_once('../php/TestDnsAddresses.class.php');
require_once('../php/TestDnssecStatus.class.php');

if(!isset($_GET['id']))
	die("Parameter id does not contain an entity ID\n");

$id = intval($_GET['id']);
$withRevisions = FALSE;
if(isset($_GET['revisions']) && !empty($_GET['revisions']))
	$withRevisions = TRUE;

$filename = 'protokollen-entity-'. $id .'-'. strftime('%Y%m%d_%H%m') .'.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename='. $filename);

echo json_encode(entityToObject($id, $withRevisions), JSON_PRETTY_PRINT);

/**
 * Produce JSON tree of entity
 * @param $entityId Entity ID
 * @param $withRevisions Whether or not to include revisions (boolean)
 * @return JSON
 */
function entityToObject($entityId, $withRevisions = FALSE) {
	$p = new ServiceGroup();
	$e = $p->getEntityById($entityId);

	/**
	 * Create properties in sorted order because
	 * PHP can't produce JSON with sorted keys
	 */
	$entity = new stdClass();
	if($e === NULL) {
		return $entity;
	}

	$entity->created = $e->created;
	$entity->id = intval($e->id);
	$entity->name = $e->org;
	$entity->organizationGroup = $e->org_group;
	$entity->organizationShort = $e->org_short;
	$entity->tags = $p->getEntityTags($e->id);
	$entity->updated = $e->updated;
	$entity->zones = array();

	$zone = new stdClass();
	$zone->created = $e->created;
	$zone->name = $e->domain;
	$zone->services = array();
	$zone->tags = $p->getEntityTags($e->id);
	$zone->updated = $e->updated;

	foreach($p->listServices($e->id) as $svc) {
		$service = getServiceObject($svc->id, $withRevisions);
		$zone->services[] = $service;
	}

	$entity->zones[] = $zone;

	if($e->domain_email !== NULL && $e->domain_email !== $e->domain) {
		$zone = new stdClass();
		$zone->created = $e->created;
		$zone->name = $e->domain_email;
		$zone->services = array();
		$zone->tags = $p->getEntityTags($e->id);
		$zone->updated = $e->updated;
		$entity->zones[] = $zone;
	}

	return $entity;
}

function getServiceObject($svcId, $withRevisions = FALSE) {
	$p = new ServiceGroup();
	$svc = $p->getServiceById($svcId);

	$service = new stdClass();
	$service->created = $svc->created;
	$service->description = $svc->service_desc;
	$service->groups = array();
	$service->id = intval($svc->id);
	$service->name = $svc->service_name;
	$service->tests = array();
	$service->type = $svc->service_type;
	$service->updated = $svc->updated;

	foreach($p->listServiceGroups($svc->id) as $grp) {
		if(!$withRevisions && $grp->entry_type !== 'current')
			continue;

		$group = new stdClass();
		$group->created = $grp->created;
		$group->data = $grp->json;
		$group->entryType = $grp->entry_type;
		$group->id = intval($grp->id);
		$group->until = $grp->until;
		$group->updated = $grp->updated;
		$service->groups[] = $group;
	}

	$testDnssec = new TestDnssecStatus();
	$testDnsAddrs = new TestDnsAddresses();
	$testProbes = new TestSslprobe();
	$testWww = new TestWwwPreferences();

	foreach($testDnsAddrs->listItems($svc->id) as $row) {
		if(!$withRevisions && $row->entry_type !== 'current')
			continue;
		$test = new stdClass();
		$test->created = $row->created;
		$test->data = $row->json;
		$test->entryType = $row->entry_type;
		$test->id = intval($row->id);
		$test->serviceGroupId = intval($row->svc_group_id);
		$test->serviceId = intval($row->service_id);
		$test->type = 'DNS Addresses';
		$test->until = $row->until;
		$test->updated = $row->updated;
		$service->tests[] = $test;
	}

	foreach($testDnssec->listItems($svc->id) as $row) {
		if(!$withRevisions && $row->entry_type !== 'current')
			continue;
		$test = new stdClass();
		$test->created = $row->created;
		$test->data = $row->json;
		$test->entryType = $row->entry_type;
		$test->id = intval($row->id);
		$test->serviceGroupId = intval($row->svc_group_id);
		$test->serviceId = intval($row->service_id);
		$test->type = 'DNSSEC status';
		$test->until = $row->until;
		$test->updated = $row->updated;
		$service->tests[] = $test;
	}

	foreach($testWww->listItems($svc->id) as $row) {
		if(!$withRevisions && $row->entry_type !== 'current')
			continue;
		$test = new stdClass();
		$test->created = $row->created;
		$test->data = $row->json;
		$test->entryType = $row->entry_type;
		$test->id = intval($row->id);
		$test->serviceGroupId = intval($row->svc_group_id);
		$test->serviceId = intval($row->service_id);
		$test->type = 'Web preferences';
		$test->until = $row->until;
		$test->updated = $row->updated;
		$service->tests[] = $test;
	}

	foreach($service->groups as $grp) {
		foreach($testProbes->listItems($svc->id, $grp->id) as $row) {
			if(!$withRevisions && $row->entry_type !== 'current')
				continue;
			$test = new stdClass();
			$test->created = $row->created;
			$test->data = $row->json;
			$test->entryType = $row->entry_type;
			$test->id = intval($row->id);
			$test->hostname = intval($row->hostname);
			$test->serviceGroupId = intval($row->svc_group_id);
			$test->serviceId = intval($row->service_id);
			$test->type = 'Sslprobe';
			$test->until = $row->until;
			$test->updated = $row->updated;
			$service->tests[] = $test;
		}
	}

	return $service;
}

<?php
require_once('ProtokollenBase.class.php');

class ServiceGroup extends ProtokollenBase  {
	/**
	 * @param $instance Instance of Protokollen()
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * Get service group
	 * @param $svcId Service ID
	 * @returns Service group object or NULL, throws on errors
	 */
	function getServiceGroup($svcId) {
		$m = $this->getMySQLHandle();

		/* Lookup current mapping between service and service group */
		$q = 'SELECT sg.* FROM svc_group_map sgm
				LEFT JOIN svc_groups sg
					ON sgm.svc_group_id=sg.id
				WHERE sgm.service_id=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('i', $svcId);
		$st->execute();
		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		if($row !== NULL)
			$row->json = json_decode($row->json);

		return $row;
	}

	/**
	 * Get service group by primary key
	 * @param $svcGrpId Service group ID
	 * @returns Service group object or NULL, throws on errors
	 */
	function getServiceGroupById($svcGrpId) {
		$m = $this->getMySQLHandle();

		/* Lookup current mapping between service and service group */
		$q = 'SELECT * FROM svc_groups WHERE id=?';
		$st = $m->prepare($q);
		$st->bind_param('i', $svcGrpId);
		$st->execute();
		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		if($row !== NULL)
			$row->json = json_decode($row->json);

		return $row;
	}

	/**
	 * List service groups
	 * @param $svcId Service ID
	 * @returns Array of service groups, throws on errors
	 */
	function listServiceGroups($svcId) {
		$m = $this->getMySQLHandle();

		$q = 'SELECT sg.*, sgm.entry_type, sgm.until FROM svc_group_map sgm
				LEFT JOIN svc_groups sg
					ON sgm.svc_group_id=sg.id
				WHERE sgm.service_id=?
				ORDER BY sgm.entry_type, sgm.created DESC';
		$st = $m->prepare($q);
		$st->bind_param('i', $svcId);
		$st->execute();
		$r = $st->get_result();
		$arr = array();
		while($row = $r->fetch_object()) {
			$row->json = json_decode($row->json);
			$arr[] = $row;
		}
		$r->close();
		$st->close();

		return $arr;
	}

	/**
	 * Add service group
	 * @param $svcId Service ID
	 * @param $serviceHosts Array of service group descriptions
	 * @returns ID of service group, throws on error
	 */
	function addServiceGroup($svcId, $serviceHosts) {
		$m = $this->getMySQLHandle();

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		/* Create sorted set */
		$arr = array();
		foreach($serviceHosts as $svcHost) {
			$obj = new stdClass();
			$obj->hostname = mb_convert_case($svcHost->hostname, MB_CASE_LOWER);
			$obj->port = intval($svcHost->port);
			$obj->prio = intval($svcHost->prio);
			$obj->protocol = strtolower($svcHost->protocol);
			$key = sprintf('%s:%05d:%s:%05d',
							$obj->protocol, $obj->prio,
							$obj->hostname, $obj->port);
			$arr[$key] = $obj;
		}

		ksort($arr);
		$serviceHosts = array_values($arr);
		$json = json_encode($serviceHosts);
		$jsonId = $this->addJson($svc->id, $json);
		$jsonHash = hash('sha256', $json);

		$q = 'SELECT id FROM svc_groups WHERE hash=?';
		$st = $m->prepare($q);
		$st->bind_param('s', $jsonHash);
		$st->execute();
		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		if($row !== NULL) {
			$svcGrpId = $row->id;
		}
		else {
			/* Add new service group */
			$q = 'INSERT INTO svc_groups SET json=?, hash=?, created=NOW()';
			$st = $m->prepare($q);
			$st->bind_param('ss', $json, $jsonHash);
			$st->execute();
			$svcGrpId = $st->insert_id;
			$st->close();
		}


		/* Lookup current mapping between service and service group */
		$q = 'SELECT id, svc_group_id, entry_type FROM svc_group_map WHERE service_id=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('i', $svc->id);
		$st->execute();
		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		$mapId = 0;
		if($row !== NULL)
			$mapId = $row->id;

		if($row !== NULL && $row->svc_group_id == $svcGrpId)
			return $svcGrpId;

		/**
		 * Set new current mapping
		 * XXX: This is racy...
		 */
		$q = 'INSERT INTO svc_group_map SET service_id=?, svc_group_id=?, entry_type="current", created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('ii', $svc->id, $svcGrpId);
		$st->execute();
		$st->close();

		if($mapId != 0) {
			/* Change previous mapping to a revision */
			$q = 'UPDATE svc_group_map SET until=NOW(), entry_type="revision" WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('i', $mapId);
			$st->execute();
			$st->close();
		}

		$newHosts = array();
		foreach($serviceHosts as $svcHost)
			$newHosts[] = $svcHost->hostname .':'. $svcHost->port;
		$log = $svc->service_type .' service group created:'
			.' ['. implode(', ', $newHosts) .']';
		if($row !== NULL) {
			$oldHosts = array();
			$prev = $this->getServiceGroupById($row->svc_group_id);
			foreach($prev->json as $svcHost)
				$oldHots[] = $svcHost->hostname .':'. $svcHost->port;

			$arr = array();
			$add = array_diff($newHosts, $oldHosts);
			$del = array_diff($oldHosts, $newHosts);
			if(count($add))
				$arr[] = 'added ('. implode('; ', $add) .')';
			if(count($del))
				$arr[] = 'removed ('. implode('; ', $del) .')';
			if(count($arr) === 0)
				return $svcGrpId;
			$log = $svc->service_type .' service group changed:'
					.' '. implode(', ', $arr);
		}

		$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);
		return $svcGrpId;
	}
}

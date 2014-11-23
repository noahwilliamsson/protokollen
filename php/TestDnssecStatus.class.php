<?php
/**
 * Handle JSON generated by the check_dnssec_status.py tool
 */

require_once(dirname(__FILE__) .'/ServiceGroup.class.php');

class TestDnssecStatus extends ServiceGroup {

	function __construct() {
		parent::__construct();
	}

	function listItems($svcId, $svcGrpId) {
		$m = $this->getMySQLHandle();

		$st = $m->prepare('SELECT * FROM test_dnssec_statuses
					WHERE service_id=? AND svc_group_id=?
					ORDER BY entry_type, created DESC');
		$st->bind_param('ii', $svcId, $svcGrpId);
		if(!$st->execute()) {
			$err = "DNSSEC status lookup ($svcId, $svcGrpId) failed:"
				." $m->error";
			throw new Exception($err);
		}

		$arr = array();
		$r = $st->get_result();
		while($row = $r->fetch_object()) {
			$row->json = NULL;
			$json = $this->getJsonByHash($svcId, $row->json_sha256);
			if($json !== NULL)
				$row->json = json_decode($json->json);
			$arr[] = $row;
		}

		$r->close();
		$st->close();

		return $arr;
	}

	/**
	 * Lookup current DNS addresses for service group
	 * @param $svcId Service ID
	 * @param $svcGrpId Service group ID
	 * @returns Object or NULL, throws on errors
	 */
	function getItem($svcId, $svcGrpId) {
		$m = $this->getMySQLHandle();

		$q = 'SELECT *
			FROM test_dnssec_statuses
			WHERE service_id=? AND svc_group_id=?
			AND entry_type="current"';
		if(($st = $m->prepare($q)) === FALSE) {
			$err = "DNSSEC status lookup ($svc->id, $svcGrpId) failed:"
				." $m->error";
			throw new Exception($err);
		}

		$st->bind_param('ii', $svcId, $svcGrpId);
		if($st->execute() === FALSE) {
			$err = "DNSSEC status lookup ($svc->id, $svcGrpId) failed:"
				." $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		if($row) {
			$row->json = NULL;
			$json = $this->getJsonByHash($svcId, $row->json_sha256);
			if($json !== NULL)
				$row->json = json_decode($json->json);
		}

		return $row;
	}

	/**
	 * Imports data from check_dnssec_status.py tool
	 * @param $svcId Service ID
	 * @param $svcGrpId Service group ID
	 * @param $json JSON output from check_dnssec_status.py
	 * @returns ID of row in test_dns_addresse, throws on errors
	 */
	function importJson($svcId, $svcGrpId, $json) {
		$m = $this->getMySQLHandle();

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$result = json_decode($json);
		if(!is_object($result))
			throw new Exception(__METHOD__ .": Invalid JSON"
						." ($svc->id, $svcGrpId)");

		$numHosts = 0;
		$numDnskey = 0;
		$numDs = 0;
		$numSecure = 0;
		foreach($result as $hostname => $obj) {
			$numHosts++;
			if($obj->dnskey) $numDnskey++;
			if($obj->ds) $numDs++;
			if($obj->secure) $numSecure++;
		}

		$q = 'SELECT * FROM test_dnssec_statuses
			WHERE service_id=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('i', $svc->id);
		if(!$st->execute()) {
			$err = "DNSSEC status lookup ($svc->id) failed:"
				." $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		$json = json_encode($result);
		$hash = hash('sha256', $json);
		if($row !== NULL && $row->json_sha256 === $hash)
			return $row->id;

		$jsonId = $this->addJson($svc->id, $json);
		$q = 'INSERT INTO test_dnssec_statuses
			SET service_id=?, svc_group_id=?, entry_type="current",
			json_id=?, json_sha256=?, num_hosts=?, num_dnskey=?,
			num_ds=?, num_secure=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iiisiiii', $svc->id, $svcGrpId, $jsonId,
				$hash, $numHosts, $numDnskey, $numDs, $numSecure);
		if(!$st->execute()) {
			$err = "DNSSEC status add ($svc->id, $svcGrpId) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		if($row !== NULL) {
			$q = 'UPDATE test_dnssec_statuses SET
				entry_type="revision", until=NOW() WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('i', $row->id);
			if(!$st->execute()) {
				$err = "DNSSEC status revision update"
					." ($svc->id) failed: $m->error";
				throw new Exception($err);
			}

			$st->close();
		}

		/* Log changes */
		if($row === NULL) {
			$log = "DNSSEC status created: Hosts/DNSKEY/DS/Secure"
				." $numHosts/$numDnskey/$numDs/$numSecure";
			$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);
			return $id;
		}

		$changes = array();
		if($row->num_hosts != $numHosts)
			$changes[] = "Hosts [$row->num_hosts -> $numHosts]";
		if($row->num_dnskey != $numDnskey)
			$changes[] = "DNSKEY [$row->num_dnskey -> $numDnskey]";
		if($row->num_ds != $numDs)
			$changes[] = "DS [$row->num_ds  -> $numDs]";
		if($row->num_secure != $numSecure)
			$changes[] = "Secure [$row->num_secure -> $numSecure]";

		if(!empty($changes)) {
			$log = 'DNSSEC status changed: '. implode(', ', $changes);
			$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);
		}

		return $id;
	}
}
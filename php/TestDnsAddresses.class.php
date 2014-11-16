<?php
/**
 * Protokollen PHP class
 *
 */

require_once(dirname(__FILE__) .'/ServiceGroup.class.php');

class TestDnsAddresses extends ServiceGroup {

	function __construct() {
		parent::__construct();
	}


	function listDnsAddresses($svcId) {
		$m = $this->getMySQLHandle();

		$st = $m->prepare('SELECT * FROM test_dns_addresses
					WHERE service_id=?
					ORDER BY entry_type, created DESC');
		$st->bind_param('i', $svcId);
		if(!$st->execute()) {
			$err = "DNS address lookup ($svcId) failed: $m->error";
			throw new Exception($err);
		}

		$arr = array();
		$r = $st->get_result();
		while($row = $r->fetch_object())
			$arr[] = $row;
		$r->close();
		$st->close();

		return $arr;
	}

	function addDnsAddressesJson($svcId, $svcGrpId, $json) {
		$m = $this->getMySQLHandle();

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$result = json_decode($json);
		if(!is_object($result))
			throw new Exception(__METHOD__ .": Invalid JSON"
						." ($svc->id, $svcGrpId)");

		$numHosts = $result->hosts;
		$numA = $result->a;
		$numAAAA = $result->aaaa;
		$numCNAME = $result->cname;

		$q = 'SELECT * FROM test_dns_addresses
			WHERE service_id=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('i', $svc->id);
		if(!$st->execute()) {
			$err = "DNS address lookup ($svc->id) failed: $m->error";
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
		$q = 'INSERT INTO test_dns_addresses
			SET service_id=?, svc_group_id=?, entry_type="current",
			json_id=?, json_sha256=?, num_hosts=?, num_a=?,
			num_aaaa=?, num_cname=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iiisiiii', $svc->id, $svcGrpId, $jsonId,
				$hash, $numHosts, $numA, $numAAAA, $numCNAME);
		if(!$st->execute()) {
			$err = "DNS address pref add ($svc->id, $svcGrpId) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		if($row !== NULL) {
			$q = 'UPDATE test_dns_addresses SET entry_type="revision",
				until=NOW() WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('i', $row->id);
			if(!$st->execute()) {
				$err = "DNS address revision update ($svc->id) failed:"
					." $m->error";
				throw new Exception($err);
			}

			$st->close();
		}

		/* Log changes */
		if($row === NULL) {
			$log = "DNS addresses created: A/AAAA/CNAME $numA/$numAAAA/$numCNAME";
			$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);
			return $id;
		}

		$changes = array();
		if($row->num_a != $numA)
			$changes[] = "A [$row->num_a -> $numA]";
		if($row->num_aaaa != $numAAAA)
			$changes[] = "AAAA [$row->num_aaaa  -> $numAAAA]";
		if($row->num_cname != $numCNAME)
			$changes[] = "CNAME [$row->num_cname -> $numCNAME]";

		if(!empty($changes)) {
			$log = 'DNS addresses changed: '. implode(', ', $changes);
			$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);
		}

		return $id;
	}
}

<?php
/**
 * Protokollen PHP class
 *
 */

require_once(dirname(__FILE__) .'/ServiceGroup.class.php');

class TestWwwPreferences extends ServiceGroup {

	function __construct() {
		parent::__construct();
	}


	function listWwwPreferences($svcId) {
		$m = $this->getMySQLHandle();

		$st = $m->prepare('SELECT * FROM test_www_prefs
					WHERE service_id=?
					ORDER BY entry_type, created DESC');
		$st->bind_param('i', $svcId);
		if(!$st->execute()) {
			$err = "WWW prefs lookup ($svcId) failed: $m->error";
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

	function addWwwPreferencesJson($svcId, $svcGrpId, $json) {
		$m = $this->getMySQLHandle();

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$result = json_decode($json);
		if(!is_object($result))
			throw new Exception(__METHOD__ .": Invalid JSON"
						." ($svc->id, $svcGrpId)");

		$url = null;
		$title = null;
		if($result->preferred != null) {
			if(!empty($result->preferred->url))
				$url = $result->preferred->url;
			if(!empty($result->preferred->title))
				$title = $result->preferred->title;
		}

		$q = 'SELECT * FROM test_www_prefs
			WHERE service_id=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('i', $svc->id);
		if(!$st->execute()) {
			$err = "WWW pref lookup ($svc->id) failed: $m->error";
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
		$q = 'INSERT INTO test_www_prefs
			SET service_id=?, svc_group_id=?, entry_type="current",
			json_id=?, json_sha256=?, url=?, title=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iiisss', $svc->id, $svcGrpId, $jsonId,
				$hash, $url, $title);
		if(!$st->execute()) {
			$err = "WWW pref add ($svc->id, $svcGrpId) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		if($row !== NULL) {
			$q = 'UPDATE test_www_prefs SET entry_type="revision",
				until=NOW() WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('i', $row->id);
			if(!$st->execute()) {
				$err = "Web pref revision update ($svc->id) failed:"
					." $m->error";
				throw new Exception($err);
			}

			$st->close();
		}

		/* Log changes */
		if($row === NULL) {
			$log = "Web preferences created: preferred URL [$url]";
			$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);
			return $id;
		}

		$changes = array();
		if($row->url !== $url)
			$changes[] = "preferred URL [$row->url -> $url]";
		if($row->title !== $title)
			$changes[] = "title [$row->title -> $title]";

		if(!empty($changes)) {
			$log = 'Web preferences changed: '. implode(', ', $changes);
			$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);
		}

		return $id;
	}
}

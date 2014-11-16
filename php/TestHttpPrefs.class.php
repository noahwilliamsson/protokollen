<?php
/**
 * Protokollen PHP class
 *
 */

require_once(dirname(__FILE__) .'/ProtokollenBase.class.php');

class TestHttpPrefs extends ProtokollenBase {

function __construct($instance) {
		parent::__construct();
	}


	function getHttpPreferences($svcId) {
		$m = $this->getMySQLHandle();

		$st = $m->prepare('SELECT * FROM service_http_preferences
					WHERE service_id=?
					ORDER BY entry_type, created DESC');
		$st->bind_param('i', $svcId);
		if(!$st->execute()) {
			$err = "HTTP prefs lookup ($svcId) failed: $m->error";
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

	function addHttpPreferenceJson($svcId, $json) {
		$m = $this->getMySQLHandle();

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$result = json_decode($json);
		if(!is_object($result))
			throw new Exception(__METHOD__ .": Invalid JSON for"
						." for service $svc->id");

		$pref = null;
		$title = null;
		$http_preferred = null;
		$https_preferred = null;
		$https_error = null;

		if($result->preferred != null) {
			if(!empty($result->preferred->url))
				$pref = $result->preferred->url;
			if(!empty($result->preferred->title))
				$title = $result->preferred->title;
		}

		$arr = array();
		if(isset($result->http)) foreach($result->http as $res) {
			if(substr($res->error, 0, 22) === 'Could not resolve host')
				continue;
			if($res->error)
				continue;
			$arr[] = $res->location;
		}
		$arr = array_unique($arr);
		sort($arr);
		if(!empty($arr))
			$http_preferred = $arr[0];

		$arr = array();
		$arr_err = array();
		if(isset($result->https)) foreach($result->https as $res) {
			if(substr($res->error, 0, 22) === 'Could not resolve host')
				continue;
			if($res->error)
				$arr_err[] = $res->error;
			if($res->error)
				continue;
			$arr[] = $res->location;
		}
		$arr = array_unique($arr);
		$arr_err = array_unique($arr_err);
		sort($arr);
		if(!empty($arr))
			$https_preferred = $arr[0];
		else if(!empty($arr_err))
			$https_error = $arr_err[0];

		$q = 'SELECT * FROM service_http_preferences
			WHERE service_id=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('i', $svc->id);
		if(!$st->execute()) {
			$err = "HTTP pref lookup ($svc->id) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();


		$e = $this->getEntityById($svc->entity_id);

		$json = json_encode($result);
		$hash = hash('sha256', $json);
		if($row !== NULL && $row->json_sha256 === $hash) {
			$q = 'UPDATE service_http_preferences
				SET service_id=?, domain=?, title=?,
				preferred_url=?, http_preferred_url=?,
				https_preferred_url=?, https_error=?,
				created=NOW()
				WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('issssssi', $svc->id,
				$e->domain, $title, $pref,
				$http_preferred, $https_preferred, $https_error,
				$row->id);
			if(!$st->execute()) {
				$err = "HTTP pref update ($svc->id) failed:"
					." $m->error";
				throw new Exception($err);
			}

			$st->close();
			return $row->id;
		}

		$jsonId = $this->addJson($svc->id, $json);
		$q = 'INSERT INTO service_http_preferences
			SET service_id=?, entry_type="current", domain=?,
			title=?, preferred_url=?, http_preferred_url=?,
			https_preferred_url=?, https_error=?,
			json_id=?, json_sha256=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('issssssis', $svc->id, $e->domain, $title,
				$pref, $http_preferred, $https_preferred,
				$https_error, $jsonId, $hash);
		if(!$st->execute()) {
			$err = "HTTP pref add ($svc->id) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		$q = 'UPDATE service_http_preferences SET entry_type="revision",
			updated=updated WHERE service_id=? AND id!=?';
		$st = $m->prepare($q);
		$st->bind_param('ii', $svc->id, $id);
		if(!$st->execute()) {
			$err = "HTTP ref revision update ($svc->id) failed:"
					." $m->error";
			throw new Exception($err);
		}

		$st->close();


		/* Log changes */
		if($row === NULL) {
			$log = 'HTTP preferences created, preferred URL is: '. $pref;
			$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);
			return $id;
		}

		$changes = array();
		if($row->preferred_url !== $pref)
			$changes[] = "preferred URL ($row->preferred_url -> $pref)";
		if($row->http_preferred_url !== $http_preferred)
			$changes[] = "preferred HTTP URL ($row->http_preferred_url -> $http_preferred)";
		if($row->https_preferred_url !== $https_preferred)
			$changes[] = "preferred HTTPS URL ($row->https_preferred_url -> $https_preferred)";
		if($row->https_error !== $https_error)
			$changes[] = "HTTPS error ($row->https_error -> $https_error)";

		if(!empty($changes)) {
			$log = 'HTTP preferences changed: '. implode(', ', $changes);
			$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);
		}

		return $id;
	}
}

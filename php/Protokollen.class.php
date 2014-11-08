<?php
/**
 * Protokollen PHP class
 *
 */

require_once(dirname(__FILE__) .'/MySQL.config.php');

class Protokollen {

	const SERVICE_TYPE_HTTP		= 'HTTP';
	const SERVICE_TYPE_WEBMAIL	= 'Webmail';
	const SERVICE_TYPE_SMTP		= 'SMTP';
	const SERVICE_TYPE_DNS		= 'DNS';

	private $m;

	function __construct() {
		$this->m = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
		$this->m->set_charset(DB_CHARSET);
	}

	function getMySQLHandle() {
		return $this->m;
	}

	function listEntityIds() {
		$m = $this->m;
		$st = $m->prepare('SELECT id FROM entities WHERE id > 1');
		if(!$st->execute()) {
			$err = "List entities failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$arr = array();
		while($row = $r->fetch_object())
			$arr[] = $row->id;
		$r->close();
		$st->close();

		return $arr;
	}

	function listEntityDomains() {
		$m = $this->m;
		$st = $m->prepare('SELECT domain FROM entities WHERE id > 1');
		if(!$st->execute()) {
			$err = "List entity domains failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$arr = array();
		while($row = $r->fetch_object())
			$arr[] = $row->domain;
		$r->close();
		$st->close();

		return $arr;
	}

	function getEntityById($entityId) {
		$m = $this->m;
		$st = $m->prepare('SELECT * FROM entities WHERE id=?');
		$st->bind_param('s', $entityId);
		if(!$st->execute()) {
			$err = "Entity lookup ($entityId) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	function getEntityByDomain($domain) {
		$m = $this->m;
		$st = $m->prepare('SELECT * FROM entities WHERE domain=?');
		$st->bind_param('s', $domain);
		if(!$st->execute()) {
			$err = "Entity lookup ($domain) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	function getServiceById($svcId) {
		$m = $this->m;
		$st = $m->prepare('SELECT * FROM services WHERE id=?');
		$st->bind_param('i', $svcId);
		if(!$st->execute()) {
			$err = "Service ($svcId) lookup failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	function listServices($entityId, $svcType = NULL) {
		$m = $this->m;
		if($svcType) {
			$st = $m->prepare('SELECT * FROM services
					WHERE entity_id=? AND service_type=?');
			$st->bind_param('is', $entityId, $svcType);
		}
		else {
			$st = $m->prepare('SELECT * FROM services
					WHERE entity_id=? ORDER BY service_type');
			$st->bind_param('i', $entityId);
		}

		if(!$st->execute()) {
			$err = "List services ($entityId, $svcType) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$arr = array();
		while($row = $r->fetch_object())
			$arr[] = $row;
		$r->close();
		$st->close();

		return $arr;
	}

	function getServiceByName($entityId, $svcType, $svcName) {
		$m = $this->m;
		$st = $m->prepare('SELECT * FROM services
					WHERE entity_id=? AND service_type=?
					AND service_name=?');
		$st->bind_param('iss', $entityId, $svcType, $svcName);
		if(!$st->execute()) {
			$err = "Service lookup ($entityId, $svcType, $svcName)"
					." failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	function addService($entityId, $svcType, $svcName, $svcDesc = NULL) {
		$m = $this->m;
		$e = $this->getEntityById($entityId);
		$st = $m->prepare('INSERT INTO services
					SET entity_id=?, entity_domain=?,
					service_type=?, service_name=?,
					service_desc=?, created=NOW()');
		$st->bind_param('issss', $entityId, $e->domain,
				$svcType, $svcName, $svcDesc);
		$id = NULL;
		if(!$st->execute()) {
			$err = "Add service ($entityId, $svcType, $svcName) failed:"
					." $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();
		return $this->getServiceById($id);
	}

	function addServiceHostname($svcId, $hostname) {
		$m = $this->m;

		$svc = $this->getServiceById($svcId);
		if($svc === NULL)
			throw new Exception(__METHOD__ .": Unknown service ($svcId)");

		$st = $m->prepare('INSERT INTO service_hostnames
					SET service_id=?, entity_id=?,
					service_type=?, hostname=?, created=NOW()');
		$st->bind_param('iiss', $svc->id, $svc->entity_id,
				$svc->service_type, $hostname);
		$id = NULL;
		if(!$st->execute()) {
			$err = "Add service hostname ($svcId, $hostname) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	function listServiceHostnames($svcId) {
		$m = $this->m;
		$st = $m->prepare('SELECT * FROM service_hostnames WHERE service_id=?');
		$st->bind_param('i', $svcId);
		if(!$st->execute()) {
			$err = "List service ($svcId) hostnames failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$arr = array();
		while($row = $r->fetch_object())
			$arr[] = $row;
		$r->close();
		$st->close();

		return $arr;
	}

	function getJsonById($jsonId) {
		$m = $this->m;

		$st = $m->prepare('SELECT * FROM json WHERE id=?');
		$st->bind_param('i', $jsonId);
		if(!$st->execute()) {
			$err = "JSON lookup ($jsonId) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	function getJsonByHash($sha256) {
		$m = $this->m;

		$st = $m->prepare('SELECT * FROM json WHERE json_sha256=?');
		$st->bind_param('s', $sha256);
		if(!$st->execute()) {
			$err = "JSON lookup ($sha256) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	function addJson($svcId, $json) {
		$m = $this->m;
		$hash = hash('sha256', $json);

		$st = $m->prepare('SELECT id FROM json
					WHERE service_id=? AND json_sha256=?');
		$st->bind_param('is', $svcId, $hash);
		if(!$st->execute()) {
			$err = "JSON lookup ($svcId, $hash) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		if($row !== NULL) {
			$id = $row->id;
		}
		else {
			$st = $m->prepare('INSERT INTO json
						SET service_id=?, json_sha256=?,
						json=?, created=NOW()');
			$st->bind_param('iss', $svcId, $hash, $json);
			if(!$st->execute()) {
				$err = "JSON add ($svcId, $hash) failed: $m->error";
				throw new Exception($err);
			}

			$id = $st->insert_id;
			$st->close();
		}

		return $id;
	}

	function getHttpPreferences($svcId) {
		$m = $this->m;

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
		$m = $this->m;

		$svc = $this->getServiceById($svcId);
		if($svc === NULL)
			throw new Exception(__METHOD__ .": Unknown service"
						." $svcId");

		$result = json_decode($json);
		if(!is_object($result))
			throw new Exception(__METHOD__ .": Invalid JSON for"
						." for service $svcId");

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
		foreach($result->http as $res) {
			if($res->error || $res->status != 200)
				continue;
			$arr[] = $res->location;
		}
		$arr = array_unique($arr);
		sort($arr);
		if(!empty($arr))
			$http_preferred = $arr[0];

		$arr = array();
		$arr_err = array();
		foreach($result->https as $res) {
			if($res->error)
				$arr_err[] = $res->error;
			if($res->error || $res->status != 200)
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

		$st = $m->prepare('SELECT * FROM service_http_preferences
					WHERE service_id=? AND entry_type=?');
		$entry_type = 'current';
		$st->bind_param('is', $svc->id, $entry_type);
		if(!$st->execute()) {
			$err = "HTTP pref lookup ($svc->id) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();


		$json = json_encode($result);
		$hash = hash('sha256', $json);
		if($row !== NULL && $row->json_sha256 === $hash) {
			/* Just update timestamp if nothing changed */
			$st = $m->prepare('UPDATE service_http_preferences
						SET updated=NOW() WHERE id=?');
			$st->bind_param('i', $row->id);
			if(!$st->execute()) {
				$err = "HTTP pref update ($svc->id) failed: $m->error";
				throw new Exception($err);
			}

			return $row->id;
		}

		$jsonId = $this->addJson($svc->id, $json);
		$st = $m->prepare('INSERT INTO service_http_preferences
					SET service_id=?, entry_type=?, domain=?, title=?,
					preferred_url=?, http_preferred_url=?,
					https_preferred_url=?, https_error=?,
					json_id=?, json_sha256=?, created=NOW()');
		$entry_type = 'current';
		$st->bind_param('isssssssis', $svc->id, $entry_type,
				$result->domain, $title, $pref,
				$http_preferred, $https_preferred, $https_error,
				$jsonId, $hash);
		if(!$st->execute()) {
			$err = "HTTP pref add ($svc->id) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		$st = $m->prepare('UPDATE service_http_preferences
					SET entry_type=?, updated=updated
					WHERE service_id=? AND id!=?');
		$entry_type = 'revision';
		$st->bind_param('sii', $entry_type, $svc->id, $id);
		if(!$st->execute()) {
			$err = "HTTP ref revision update ($svc->id) failed:"
					." $m->error";
			throw new Exception($err);
		}

		$st->close();


		/* Log changes */
		if($row === NULL) {
			$log = 'HTTP preferences created, preferred URL is: '. $pref;
			$this->logEntry($svc->id, $domain, $log);
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
			$this->logEntry($svc->id, $domain, $log);
		}

		return $id;
	}

	function getTlsStatuses($svcId, $hostnameId) {
		$m = $this->m;

		$st = $m->prepare('SELECT * FROM service_tls_statuses
					WHERE service_id=? AND hostname_id=?
					ORDER BY entry_type, created DESC');
		$st->bind_param('ii', $svcId, $hostnameId);
		if(!$st->execute()) {
			$err = "TLS status lookup ($svcId, $hostnameId)"
				." failed: $m->error";
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

	function addTlsStatusJson($svcId, $json) {
		$m = $this->m;

		$probes = json_decode($json);
		if(!is_array($probes))
			throw new Exception(__METHOD__ .": Invalid JSON for"
						." for service $svcId");
		if(empty($probes))
			return NULL;

		$svc = $this->getServiceById($svcId);
		if($svc === NULL)
			throw new Exception(__METHOD__ .": Unknown service"
						." $svcId");

		$numIps = 0;
		$sslv2 = 0;
		$sslv3 = 0;
		$tlsv1 = 0;
		$tlsv1_1 = 0;
		$tlsv1_2 = 0;
		$hostname = null;
		foreach($probes as $hostProbe) {
			$numIps++;
			$hostname = $hostProbe->host;
			foreach($hostProbe->protocols as $proto) {
				if(!$proto->supported)
					continue;

				switch($proto->name) {
				case 'SSL 2.0': $sslv2++; break;
				case 'SSL 3.0': $sslv3++; break;
				case 'TLS 1.0': $tlsv1++; break;
				case 'TLS 1.1': $tlsv1_1++; break;
				case 'TLS 1.2': $tlsv1_2++; break;
				default:
					break;
				}
			}
		}

		foreach($this->listServiceHostnames($svc->id) as $h) {
			if(!strcasecmp($h->hostname, $hostname)) {
				$hostname = $h;
				break;
			}
		}

		if(!is_object($hostname)) {
			/* Not found */
			echo "ERROR: Hostname '$hostname' not found in"
				." service with ID '$svcId'\n";
			return null;
		}


		/* Lookup current entry */
		$st = $m->prepare('SELECT * FROM service_tls_statuses
					WHERE service_id=? AND hostname_id=?
					AND entry_type=?');
		$entry_type = 'current';
		$st->bind_param('iis', $svc->id,
				$hostname->id, $entry_type);
		if(!$st->execute()) {
			$err = "TLS status lookup ($svc->id,"
				." $hostname->id) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();


		$json = json_encode($probes);
		$hash = hash('sha256', $json);
		if($row !== NULL && $row->json_sha256 === $hash) {
			/* Just update timestamp if nothing changed */
			$st = $m->prepare('UPDATE service_tls_statuses
						SET updated=NOW() WHERE id=?');
			$st->bind_param('i', $row->id);
			if(!$st->execute()) {
				$err = "TLS status update ($svc->id,"
					." $hostname->id) failed: $m->error";
				throw new Exception($err);
			}

			return $row->id;
		}


		/* Add new row */
		$jsonId = $this->addJson($svc->id, $json);
		$st = $m->prepare('INSERT INTO service_tls_statuses
					SET service_id=?, hostname_id=?, entry_type=?,
					hostname=?, num_ips=?,
					sslv2=?, sslv3=?, tlsv1=?, tlsv1_1=?, tlsv1_2=?,
					json_id=?, json_sha256=?, created=NOW()');
		$entry_type = 'current';
		$st->bind_param('iissiiiiiiis', $svc->id,
				$hostname->id, $entry_type,
				$hostname->hostname, $numIps,
				$sslv2, $sslv3, $tlsv1, $tlsv1_1, $tlsv1_2,
				$jsonId, $hash);
		if(!$st->execute()) {
			$err = "TLS status add ($svc->id, $hostname->id)"
				." failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();


		$st = $m->prepare('UPDATE service_tls_statuses
					SET entry_type=?, updated=updated
					WHERE service_id=? AND hostname_id=? AND id!=?');
		$entry_type = 'revision';
		$st->bind_param('siii', $entry_type,
				$svc->id, $hostname->id, $id);
		if(!$st->execute()) {
			$err = "TLS status revision update ($svc->id,"
				." $hostname->id) failed: $m->error";
			throw new Exception($err);
		}

		$st->close();


		/* Log changes */
		if($row === NULL) {
			$log = 'TLS status created';
			$this->logEntry($svc->id, $hostname->hostname, $log);
			return $id;
		}

		$changes = array();
		if($row->sslv2 != $sslv2)
			$changes[] = "SSLv2 ($row->sslv2 -> $sslv2)";
		if($row->sslv3 != $sslv3)
			$changes[] = "SSLv3 ($row->sslv3 -> $sslv3)";
		if($row->tlsv1 != $tlsv1)
			$changes[] = "TLSv1 ($row->tlsv1 -> $tlsv1)";
		if($row->tlsv1_1 != $tlsv1_1)
			$changes[] = "TLSv1 ($row->tlsv1_1 -> $tlsv1_1)";
		if($row->tlsv1_2 != $tlsv1_2)
			$changes[] = "TLSv1 ($row->tlsv1_1 -> $tlsv1_2)";

		if($row->num_ips != $numIps) {
			/* overwrite */
			$changes = array();
			$changes[] = "number of IPs ($row->num_ips -> $numIps)";
		}

		if(!empty($changes)) {
			$log = 'TLS status changed: '. implode(', ', $changes);
			$this->logEntry($svc->id, $hostname->hostname, $log);
		}

		return $id;
	}

	function logEntry($svcId, $hostname, $msg) {
		$m = $this->m;
		$svc = $this->getServiceById($svcId);
		$st = $m->prepare('INSERT INTO logs
					SET service_id=?, hostname=?,
					service=?, `log`=?, created=NOW()');
		$st->bind_param('isss', $svcId, $hostname,
				$svc->service_name, $msg);
		$id = NULL;
		if(!$st->execute()) {
			$err = "Log entry add ($svcId, $hostname) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();
		return $this;
	}
}

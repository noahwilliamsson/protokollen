<?php
/**
 * Protokollen PHP class
 *
 */

require_once(dirname(__FILE__) .'/MySQL.config.php');

class Protokollen {

	const SERVICE_TYPE_HTTP		= 'HTTP';
	const SERVICE_TYPE_HTTPS	= 'HTTPS';
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

	/**
	 * Add new service to entity
	 * @param $entityID Entity ID
	 * @param $svcType Service type (of Protokollen::SERVER_TYPE_*)
	 * @param $svcName Service name (should be a domain)
	 * @param $svcDesc Service description
	 * @return ID of row in services table, throws on error
	 */
	function addService($entityId, $svcType, $svcName, $svcDesc = NULL) {
		$e = $this->getEntityById($entityId);

		$svc = $this->getServiceByName($entityId, $svcType, $svcName);
		if($svc !== NULL)
			return $svc->id;

		$q = 'INSERT INTO services SET entity_id=?, entity_domain=?,
					service_type=?, service_name=?,
					service_desc=?, created=NOW()';
		$st = $this->m->prepare($q);
		$st->bind_param('issss', $entityId, $e->domain, $svcType,
				$svcName, $svcDesc);
		if(!$st->execute()) {
			$err = "Add service ($entityId, $svcType, $svcName)"
				." failed: $this->m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	function getServiceHostname($svcId, $hostname) {
		$m = $this->m;

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$q = 'SELECT * FROM service_hostnames WHERE service_id=?
			AND entry_type="current" AND hostname=?
			ORDER BY created DESC';
		$st = $m->prepare($q);
		$st->bind_param('is', $svc->id, $hostname);
		if(!$st->execute()) {
			$err = "Lookup current service hostname"
				." ($svcId, $hostname) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	/**
	 * Add hostname to service
	 */
	function addServiceHostname($svcId, $hostname) {
		$m = $this->m;

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$svcHostname = $this->getServiceHostname($svcId, $hostname);
		if($svcHostname !== NULL)
			return $svcHostname->id;

		$q = 'INSERT INTO service_hostnames SET service_id=?,
			entity_id=?, entry_type="current", service_type=?,
			hostname=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iiss', $svc->id, $svc->entity_id,
				$svc->service_type, $hostname);
		$id = NULL;
		if(!$st->execute()) {
			$err = "Add service hostname ($svcId, $hostname)"
				." failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		$log = sprintf('Created service hostname: %s', $hostname);
		$this->logEntry($svc->id, $svc->service_name, $log);

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

	/**
	 * Lookup JSON by service ID and SHA-256 hash
	 * @param $svcId Service ID
	 * @param $sha256 SHA-256 hash of JSON
	 * @return Row (object) from JSON table, throws on error
	 */
	function getJsonByHash($svcId, $sha256) {
		$m = $this->m;

		$q = 'SELECT * FROM json WHERE service_id=? AND json_sha256=?';
		$st = $m->prepare($q);
		$st->bind_param('is', $svcId, $sha256);
		if(!$st->execute()) {
			$err = "JSON lookup ($svcId, $sha256) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	/**
	 * Add JSON to JSON store
	 * @param $svcId Service ID (must exist)
	 * @param $json JSON text
	 * @return ID of row in JSON table, throws on error
	 */
	private function addJson($svcId, $json) {
		$m = $this->m;

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$hash = hash('sha256', $json);
		$row = $this->getJsonByHash($svc->id, $hash);
		if($row !== NULL)
			return $row->id;

		$q = 'INSERT INTO json SET service_id=?, json_sha256=?,
			service=?, json=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('isss', $svc->id, $hash,
			$svc->service_name, $json);
		if(!$st->execute()) {
			$err = "JSON add ($svc->id, $hash) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

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

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

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
		foreach($result->https as $res) {
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


		$json = json_encode($result);
		$hash = hash('sha256', $json);
		if($row !== NULL && $row->json_sha256 === $hash) {
			$q = 'UPDATE service_http_preferences
				SET service_id=?, entry_type=?, domain=?,
				title=?, preferred_url=?, http_preferred_url=?,
				https_preferred_url=?, https_error=?,
				created=NOW()
				WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('isssssssi', $svc->id, $entry_type,
				$result->domain, $title, $pref,
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
		$st->bind_param('issssssis', $svc->id, $result->domain, $title,
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

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

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
		$q = 'SELECT * FROM service_tls_statuses WHERE service_id=?
			AND hostname_id=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('ii', $svc->id, $hostname->id);
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
		$q = 'INSERT INTO service_tls_statuses SET service_id=?,
			hostname_id=?, entry_type="current", hostname=?,
			num_ips=?, sslv2=?, sslv3=?, tlsv1=?, tlsv1_1=?,
			tlsv1_2=?, json_id=?, json_sha256=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iisiiiiiiis', $svc->id, $hostname->id,
				$hostname->hostname, $numIps, $sslv2, $sslv3,
				$tlsv1, $tlsv1_1, $tlsv1_2, $jsonId, $hash);
		if(!$st->execute()) {
			$err = "TLS status add ($svc->id, $hostname->id)"
				." failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();


		$q = 'UPDATE service_tls_statuses
			SET entry_type="revision", updated=updated
			WHERE service_id=? AND hostname_id=? AND id!=?';
		$st = $m->prepare($q);
		$st->bind_param('iii', $svc->id, $hostname->id, $id);
		if(!$st->execute()) {
			$err = "TLS status revision update ($svc->id,"
				." $hostname->id) failed: $m->error";
			throw new Exception($err);
		}

		$st->close();


		/* Log changes */
		if($row === NULL) {
			$log = 'TLS status created';
			$this->logEntry($svc->id, $hostname->hostname, $log, $jsonId);
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
			$changes[] = "TLSv1.1 ($row->tlsv1_1 -> $tlsv1_1)";
		if($row->tlsv1_2 != $tlsv1_2)
			$changes[] = "TLSv1.2 ($row->tlsv1_1 -> $tlsv1_2)";

		if($row->num_ips != $numIps) {
			/* overwrite */
			$changes = array();
			$changes[] = "number of IPs ($row->num_ips -> $numIps)";
		}

		if(!empty($changes)) {
			$log = 'TLS status changed: '. implode(', ', $changes);
			$this->logEntry($svc->id, $hostname->hostname, $log, $jsonId);
		}

		return $id;
	}

	function logEntry($svcId, $hostname, $msg, $jsonId = NULL) {
		$m = $this->m;
		$svc = $this->getServiceById($svcId);
		if($jsonId) {
			$st = $m->prepare('INSERT INTO logs
					SET service_id=?, json_id=?, hostname=?,
					service=?, `log`=?, created=NOW()');
			$st->bind_param('iisss', $svcId, $jsonId, $hostname,
					$svc->service_name, $msg);
		}
		else {
			$st = $m->prepare('INSERT INTO logs
					SET service_id=?, hostname=?,
					service=?, `log`=?, created=NOW()');
			$st->bind_param('isss', $svcId, $hostname,
					$svc->service_name, $msg);
		}

		$id = NULL;
		if(!$st->execute()) {
			$err = "Log entry add ($svcId, $hostname) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();
		return $this;
	}

	/**
	 * Get service set associated with service
	 * @param $svcId Service ID
	 * @return Row (object) for service_sets entry
	 */
	function getServiceSet($svcId) {
		$m = $this->m;

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$q = 'SELECT * FROM service_sets
			WHERE service_id=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('i', $svc->id);
		if(!$st->execute()) {
			$err = "Service set lookup failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	/**
	 * Add a new set of hostnames to a service
	 * @param $svcId Service ID
	 * @param $protocol Internet protocol (DNS, HTTP, HTTPS, SMTP, ..)
	 * @param $hostnames Array of hostname[:port] strings
	 * @return ID of service_sets entry
	 */
	function addServiceSet($svcId, $protocol, $hostnames) {
		$m = $this->m;

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$ss = $this->getServiceSet($svc->id);

		$protocol = strtoupper($protocol);

		/* Set default port */
		$port = NULL;
		switch($protocol) {
		case 'HTTP': $port = 80; break;
		case 'HTTPS': $port = 443; break;
		case 'DNS': $port = 53; break;
		case 'SMTP': $port = 25; break;
		default:
			throw new Exception(__METHOD__ .": Unsupported"
						." protocol: $protocol");
		}

		/* Create sorted set */
		$arr = array();
		foreach($hostnames as $h) {
			$tmp = explode(':', $h);
			if(count($tmp) === 1) $tmp[] = $port;
			list($fqdn, $hostport) = $tmp;

			$obj = new stdClass();
			$obj->hostname = mb_convert_case($fqdn, MB_CASE_LOWER);
			$obj->port = intval($hostport);
			$obj->protocol = $protocol;

			$key = sprintf('%s:%s:%05d', $obj->protocol,
					$obj->hostname, $obj->port);

			$arr[$key] = $obj;
		}

		ksort($arr);
		$json = json_encode(array_values($arr));
		$hash = hash('sha256', $json);
		if($ss && $ss->json_sha256 === $hash) {
			/* No changes */
			return $ss->id;
		}

		$jsonId = $this->addJson($svcId, $json);
		$q = 'INSERT INTO service_sets SET service_id=?, entity_id=?,
			entry_type="current", json_id=?, json_sha256=?,
			service_type=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iiiss', $svc->id, $svc->entity_id,
				$jsonId, $hash, $svc->service_type);
		if(!$st->execute()) {
			$err = "Service set add ($svcId) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		$newSs = array();
		foreach(array_values($arr) as $s)
			$newSs[] = $s->hostname .':'. $s->port;

		$log = 'Service set created: ['. implode(', ', $newSs) .']';
		if($ss) {
			$q = 'UPDATE service_sets SET entry_type="revision"
				WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('i', $ss->id);
			if(!$st->execute()) {
				$err = "Service set revision update ($svcId)"
					." failed: $m->error";
				throw new Exception($err);
			}

			$st->close();

			$oldSs = array();
			foreach($ss as $s)
				$oldSs[] = $s->hostname .':'. $s->port;

			$log = 'Service set changed:'
				.' ['. implode(', ', $oldSs) .'] ->'
				.' ['. implode(', ', $newSs) .']';
		}

		$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);

		return $id;
	}

	/**
	 * Get current service host associated with service and hostname
	 * @param $svcId Service ID
	 * @param $hostname Hostname
	 * @return Row (object) from service_vhosts table, throws on error
	 */
	function getServiceVhost($svcId, $hostname) {
		$m = $this->m;

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$q = 'SELECT * FROM service_vhosts WHERE service_id=?
			AND hostname=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('is', $svc->id, $hostname);
		if(!$st->execute()) {
			$err = "Service vhost lookup failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	/**
	 * Add service virtual host
	 * @param $svcId Service ID
	 * @param $hostname Hostname
	 * @param $ip IP-address
	 * @return Service vhost ID, throws on error
	 */
	function addServiceVhost($svcId, $hostname, $ip) {
		$m = $this->m;

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$nodeId = $this->addNode($ip);
		$vhost = $this->getServiceVhost($svcId, $hostname);
		if($vhost && $vhost->nodeId == $nodeId)
			return $vhost->id;

		$q = 'INSERT INTO service_vhosts SET service_id=?, entity_id=?,
			node_id=?, entry_type="current", hostname=?,
			service_type=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iiiss', $svc->id, $svc->entity_id, $nodeId,
				$hostname, $svc->service_type);

		if(!$st->execute()) {
			$err = "Service vhost add ($svcId, $hostname, $ip)"
				." failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		$log = sprintf('Created virtual host: %s [%s]', $hostname, $ip);
		if($vhost) {
			$q = 'UPDATE service_vhosts SET entry_type="revision"
				WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('i', $vhost->id);
			if(!$st->execute()) {
				$err = "Service vhost revision update ($svcId)"
					." failed: $m->error";
				throw new Exception($err);
			}

			$st->close();
			$log = sprintf('Virtual host changed: %s [%s -> %s]',
					$hostname, $vhost->ip, $ip);
		}

		$this->logEntry($svc->id, $svc->service_name, $log);

		return $id;
	}

	/**
	 * Lookup node by IP-address
	 * @param $ip IP-address
	 * @return Row (object) from nodes table, throws on error
	 */
	function getNodeByIp($ip) {
		$m = $this->m;

		$st = $m->prepare('SELECT * FROM nodes WHERE ip=?');
		$st->bind_param('s', $ip);
		if(!$st->execute()) {
			$err = "Node lookup ($ip) failed: $m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	/**
	 * Add node
	 * @param $ip IP-address
	 */
	function addNode($ip) {
		$node = $this->getNodeByIp($ip);
		if($node !== NULL)
			return $node->id;

		$m = $this->m;
		$q = 'INSERT INTO nodes SET ip=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('s', $ip);
		if(!$st->execute()) {
			$err = "Node add ($ip) failed: $m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	function getCertByHash($sha256Hash) {
		$q = 'SELECT * FROM certs WHERE pem_sha256=?';
		$st = $this->m->prepare($q);
		$st->bind_param('s', $sha256Hash);
		if(!$st->execute()) {
			$err = "Cert lookup ($sha256Hash)"
				." failed: $this->m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	/**
	 * Add X.509 certificate in PEM format to store
	 * @param $pem X.509 cert PEM-encoded
	 * @return ID from certs table, throws on error
	 */
	function addCert($pem) {
		$hash = hash('sha256', $pem);
		$node = $this->getCertByHash($hash);
		if($node !== NULL)
			return $node->id;

		$q = 'INSERT INTO certs
			SET pem_sha256=?, x509=?, created=NOW()';
		$st = $this->m->prepare($q);
		$st->bind_param('ss', $hash, $pem);
		if(!$st->execute()) {
			$err = "Cert add ($hash) failed: $this->m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	function addCertVhostMapping($certId, $vhostId) {
		$q = 'SELECT id FROM service_vhost_certs
			WHERE cert_id=? AND vhost_id=?';
		$st = $this->m->prepare($q);
		$st->bind_param('ii', $certId, $vhostId);
		if(!$st->execute()) {
			$err = "Cert vhost lookup ($certId, $vhostId)"
				." failed: $this->m->error";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();
		if($row !== NULL)
			return $row->id;

		$q = 'INSERT INTO service_vhost_certs
			SET cert_id=?, vhost_id=?, created=NOW()';
		$st = $this->m->prepare($q);
		$st->bind_param('ii', $certId, $vhostId);
		if(!$st->execute()) {
			$err = "Cert vhost add ($certId, $vhostId)"
				." failed: $this->m->error";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}
}

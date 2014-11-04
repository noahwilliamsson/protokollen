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

	function listEntityIds() {
		$m = $this->m;
		$st = $m->prepare('SELECT entity_id FROM entities WHERE entity_id > 1');
		if(!$st->execute()) {
			$err = "ERR: Failed to list entities: $m->error\n";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$arr = array();
		while($row = $r->fetch_object())
			$arr[] = $row->entity_id;
		$r->close();
		$st->close();

		return $arr;
	}

	function listEntityDomains() {
		$m = $this->m;
		$st = $m->prepare('SELECT domain FROM entities WHERE entity_id > 1');
		if(!$st->execute()) {
			$err = "ERR: Failed to list entities: $m->error\n";
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
		$st = $m->prepare('SELECT * FROM entities WHERE entity_id=?');
		$st->bind_param('s', $entityId);
		if(!$st->execute()) {
			$err = "ERR: Failed to lookup entity by ID $entityId: $m->error\n";
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
			$err = "ERR: Failed to lookup entity by domain $domain: $m->error\n";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	function getServiceById($serviceId) {
		$m = $this->m;
		$st = $m->prepare('SELECT * FROM entity_services WHERE service_id=?');
		$st->bind_param('i', $serviceId);
		if(!$st->execute()) {
			$err = "ERR: Failed to fetch service with ID $serviceId: $m->error\n";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	function listServicesByType($entityId, $serviceType) {
		$m = $this->m;
		$st = $m->prepare('SELECT * FROM entity_services WHERE entity_id=? AND service_type=?');
		$st->bind_param('is', $entityId, $serviceType);
		if(!$st->execute()) {
			$err = "ERR: Failed to list services with entity_id=$entityId AND service_name=$serviceName: $m->error\n";
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

	function getServiceByName($entityId, $serviceType, $serviceName) {
		$m = $this->m;
		$st = $m->prepare('SELECT * FROM entity_services WHERE entity_id=? AND service_type=? AND service_name=?');
		$st->bind_param('iss', $entityId, $serviceType, $serviceName);
		if(!$st->execute()) {
			$err = "ERR: Failed to fetch service with entity_id=$entityId AND service_name=$serviceName: $m->error\n";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	function addService($entityId, $serviceType, $serviceName, $serviceDesc = NULL) {
		$m = $this->m;
		$e = $this->getEntityById($entityId);
		$st = $m->prepare('INSERT INTO entity_services
							SET entity_id=?, entity_domain=?,
							service_type=?, service_name=?,
							service_desc=?, created=NOW()');
		$st->bind_param('issss', $entityId, $e->domain,
						$serviceType, $serviceName, $serviceDesc);
		$id = NULL;
		if(!$st->execute()) {
			$err = "ERR: Failed to add service: $m->error\n";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();
		return $this->getServiceById($id);
	}

	function addServiceHostname($serviceId, $hostname) {
		$m = $this->m;

		$svc = $this->getServiceById($serviceId);
		if($svc === NULL)
			throw new Exception("ERR: Unknown service $serviceId");

		$st = $m->prepare('INSERT INTO service_hostnames SET service_id=?, entity_id=?, service_type=?, hostname=?, created=NOW()');
		$st->bind_param('iiss', $svc->service_id, $svc->entity_id, $svc->service_type, $hostname);
		$id = NULL;
		if(!$st->execute()) {
			$err = "ERR: Service $serviceId: Failed to add service hostname '$hostname': $m->error\n";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	function listServiceHostnames($serviceId) {
		$m = $this->m;
		$st = $m->prepare('SELECT * FROM service_hostnames WHERE service_id=?');
		$st->bind_param('i', $serviceId);
		if(!$st->execute()) {
			$err = "ERR: Failed to fetch service hostname for service with ID $serviceId: $m->error\n";
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

	function addJson($serviceId, $json) {
		$m = $this->m;
		$hash = hash('sha256', $json);

		$st = $m->prepare('SELECT id FROM json WHERE service_id=? AND json_sha256=?');
		$st->bind_param('is', $serviceId, $hash);
		if(!$st->execute()) {
			$err = "ERR: Failed to lookup JSON for service_id=$serviceId AND json_sha256=$hash: $m->error\n";
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
			$st = $m->prepare('INSERT INTO json SET service_id=?, json_sha256=?, json=?, created=NOW()');
			$st->bind_param('iss', $serviceId, $hash, $json);
			if(!$st->execute()) {
				$err = "ERR: Failed to add JSON for service_id=$serviceId AND json_sha256=$hash: $m->error\n";
				throw new Exception($err);
			}

			$id = $st->insert_id;
			$st->close();
		}

		return $id;
	}

	function addHttpPreferenceJson($filename) {
		$m = $this->m;

		$data = file_get_contents($filename);
		$result = json_decode($data);
		$domain = $result->domain;

		$e = $this->getEntityByDomain($domain);
		if($e === NULL) {
			$arr = explode('.', $domain, 2);
			$temp = $arr[1];
			$e = $this->getEntityByDomain($temp);
			if($e === NULL)
				throw new Exception("Unable to identify entity for domain '$domain' (tried: $temp)");
		}

		$svc = $this->getServiceByName($e->entity_id, Protokollen::SERVICE_TYPE_HTTP, $domain);
		if($svc === NULL)
			throw new Exception("Unable to identify service for domain '$domain' and entity $e->entity_id");

	
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

		$json = json_encode($result);
		$hash = hash('sha256', $json);
		$st = $m->prepare('SELECT id FROM service_http_preferences
							WHERE service_id=?
							AND json_sha256=?
							AND entry_type=?');
		$entry_type = 'current';
		$st->bind_param('iss', $svc->service_id, $hash, $entry_type);
		if(!$st->execute()) {
			$err = "ERR: Failed to lookup HTTP pref for service ID $svc->service_id: $m->error\n";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		if($row !== NULL) {
			/* Update timestamp */
			$st = $m->prepare('UPDATE service_http_preferences
								SET title=?, preferred_url=?,
								http_preferred_url=?,
								https_preferred_url=?,
								https_error=?, updated=NOW()
								WHERE id=?');
			$st->bind_param('sssssi', $title, $pref,
							$http_preferred, $https_preferred, $https_error, $row->id);
			if(!$st->execute()) {
				$err = "ERR: Failed to update HTTP pref for service ID $svc->service_id: $m->error\n";
				throw new Exception($err);
			}

			return $row->id;
		}

		$jsonId = $this->addJson($svc->service_id, $json);
		$st = $m->prepare('INSERT INTO service_http_preferences
							SET service_id=?, entry_type=?, domain=?, title=?,
							preferred_url=?, http_preferred_url=?,
							https_preferred_url=?, https_error=?,
							json_id=?, json_sha256=?, created=NOW()');
		$entry_type = 'current';
		$st->bind_param('isssssssis', $svc->service_id, $entry_type,
						$result->domain, $title, $pref,
						$http_preferred, $https_preferred, $https_error,
						$jsonId, $hash);
		if(!$st->execute()) {
			$err = "ERR: Failed to insert new HTTP pref for service ID $svc->service_id: $m->error\n";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();


		$st = $m->prepare('UPDATE service_http_preferences
							SET entry_type=?, updated=updated
							WHERE service_id=? AND id!=?');
		$entry_type = 'revision';
		$st->bind_param('sii', $entry_type, $svc->service_id, $id);
		if(!$st->execute()) {
			$err = "ERR: Failed to mark previous HTTP prefs"
					." as 'revision' for service ID $svc->service_id:"
					." $m->error\n";
			throw new Exception($err);
		}

		$st->close();

		return $id;
	}

	function addTlsStatusJson($serviceId, $filename) {
		$m = $this->m;

		$data = file_get_contents($filename);
		$probes = json_decode($data);
		if(empty($probes))
			return NULL;

		$svc = $this->getServiceById($serviceId);
		if($svc === NULL)
			throw new Exception("Unable to identify service for"
								." service_id '$serviceId'");
		$e = $this->getEntityById($svc->service_id);

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

		foreach($this->listServiceHostnames($svc->service_id) as $h) {
			if(!strcasecmp($h->hostname, $hostname)) {
				$hostname = $h;
				break;
			}
		}

		if(!is_object($hostname)) {
			echo "ERROR: Hostname '$hostname' not found in"
				." service with ID '$serviceId'\n";
			return null;
		}

		
		/* Lookup current entry */
		$st = $m->prepare('SELECT * FROM service_tls_statuses
							WHERE service_id=? AND hostname_id=?
							AND entry_type=?');
		$entry_type = 'current';
		$st->bind_param('iis', $svc->service_id,
								$hostname->hostname_id, $entry_type);
		if(!$st->execute()) {
			$err = "ERR: Failed to lookup TLS status for"
					." service ID $svc->service_id and"
					." hostname ID $hostname->hostname_id: $m->error\n";
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();


		$json = json_encode($probes);
		$hash = hash('sha256', $json);
		if($row !== NULL && $row->json_sha256 === $hash) {
			/* Update timestamp if nothing changed */
			$st = $m->prepare('UPDATE service_tls_statuses
								SET updated=NOW() WHERE id=?');
			$st->bind_param('i', $row->id);
			if(!$st->execute()) {
				$err = "ERR: Failed to update TLS status for"
						." service ID $svc->service_id and"
						." hostname ID $hostname->hostname_id: $m->error\n";
				throw new Exception($err);
			}

			return $row->id;
		}


		/* Add new row */
		$jsonId = $this->addJson($svc->service_id, $json);
		$st = $m->prepare('INSERT INTO service_tls_statuses
							SET service_id=?, hostname_id=?, entry_type=?,
							hostname=?, num_ips=?,
							sslv2=?, sslv3=?, tlsv1=?, tlsv1_1=?, tlsv1_2=?,
							json_id=?, json_sha256=?, created=NOW()');
		$entry_type = 'current';
		$st->bind_param('iissiiiiiiis', $svc->service_id,
						$hostname->hostname_id, $entry_type,
						$hostname->hostname, $numIps,
						$sslv2, $sslv3, $tlsv1, $tlsv1_1, $tlsv1_2,
						$jsonId, $hash);
		if(!$st->execute()) {
			$err = "ERR: Failed to insert new TLS status for"
					." service ID $svc->service_id and"
					." hostname ID $hostname->hostname_id: $m->error\n";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();


		$st = $m->prepare('UPDATE service_tls_statuses
							SET entry_type=?, updated=updated
							WHERE service_id=? AND hostname_id=? AND id!=?');
		$entry_type = 'revision';
		$st->bind_param('siii', $entry_type,
						$svc->service_id, $hostname->hostname_id, $id);
		if(!$st->execute()) {
			$err = "ERR: Failed to mark previous TLS statuses as 'revision'"
					." for service ID $svc->service_id and"
					." hostname ID $hostname->hostname_id: $m->error\n";
			throw new Exception($err);
		}

		$st->close();


		$changes = array();
		if($row->num_ips != $numIps) {
			$changes[] = "number of IPs ($row->num_ips -> $numIps)";
		}
		else {
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
		}
	
		if(!empty($changes)) {
			$log = 'TLS status changed: '. implode(', ', $changes);
			$this->logEntry($svc->service_id, $hostname->hostname, $log);
		}

		return $id;
	}

	function logEntry($serviceId, $hostname, $msg) {
		$m = $this->m;
		$svc = $this->getServiceById($serviceId);
		$st = $m->prepare('INSERT INTO logs
							SET service_id=?, hostname=?, 
							service=?, `log`=?, created=NOW()');
		$st->bind_param('isss', $serviceId, $hostname,
						$svc->service_name, $msg);
		$id = NULL;
		if(!$st->execute()) {
			$err = "ERR: Failed to add log entry: $m->error\n";
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();
		return $this;
	}
}

<?php
/**
 * Protokollen PHP class
 *
 */

require_once(dirname(__FILE__) .'/ProtokollenBase.class.php');

class ServiceSet extends ProtokollenBase {

	/**
	 * Constructor
	 * @param $instance Instance of Protokollen()
	 * @param $svcId Service ID
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * Get current service set associated with service
	 * @param $svcId Service ID
	 * @return Row (object) for service_sets entry
	 */
	function getServiceSet($svcId) {
		$m = $this->getMySQLHandle();

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
	 * Get service set by primary key
	 * @param $svcSetId Service set ID
	 * @return Row (object) for service_sets entry
	 */
	function getServiceSetById($svcSetId) {
		$m = $this->getMySQLHandle();

		$q = 'SELECT * FROM service_sets WHERE id=?';
		$st = $m->prepare($q);
		$st->bind_param('i', $svcSetId);
		if(!$st->execute()) {
			$err = "Service set lookup ($svcSetId)"
				." failed: ". $m->error;
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
	 * @param $protocol Internet protocol, lowercase (dns, http, https, ..)
	 * @param $hostnames Array of hostname[:port] strings
	 * @return ID of service_sets entry
	 */
	function addServiceSet($svcId, $protocol, $hostnames) {
		$m = $this->getMySQLHandle();

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$protocol = strtolower($protocol);

		/* Set default port */
		$port = NULL;
		switch($protocol) {
		case 'http': $port = 80; break;
		case 'https': $port = 443; break;
		case 'dns': $port = 53; break;
		case 'smtp': $port = 25; break;
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
		$currentSvcSet = $this->getServiceSet($svc->id);
		if($currentSvcSet && $currentSvcSet->json_sha256 === $hash) {
			/* No changes */
			return $currentSvcSet->id;
		}


		$jsonId = $this->addJson($svc->id, $json);
		$q = 'INSERT INTO service_sets SET service_id=?, entity_id=?,
			entry_type="current", json_id=?, json_sha256=?,
			service_type=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iiiss', $svc->id, $svc->entity_id,
				$jsonId, $hash, $svc->service_type);
		if(!$st->execute()) {
			$err = "Service set add ($svc->id) failed:"
				." ". $m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		$newHosts = array();
		foreach(array_values($arr) as $s)
			$newHosts[] = $s->hostname .':'. $s->port;

		$log = $svc->service_type .' service set created:'
				.' ['. implode(', ', $newHosts) .']';
		if($currentSvcSet !== NULL) {
			$q = 'UPDATE service_sets SET entry_type="revision"
				WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('i', $currentSvcSet->id);
			if(!$st->execute()) {
				$err = "Service set revision update ($svc->id)"
					." failed: ". $m->error;
				throw new Exception($err);
			}

			$st->close();

			$oldHosts = array();
			$jsonRow = $this->getJsonByHash($svc->id, $currentSvcSet->json_sha256);
			$oldSvcHosts = json_decode($jsonRow->json);
			foreach($oldSvcHosts as $s)
				$oldHosts[] = $s->hostname .':'. $s->port;

			$arr = array();
			$add = array_diff($newHosts, $oldHosts);
			$del = array_diff($oldHosts, $newHosts);
			if(count($add))
				$arr[] = 'added ('. implode('; ', $add) .')';
			if(count($del))
				$arr[] = 'removed ('. implode('; ', $del) .')';
			if(count($arr) === 0)
				return $id;

			$log = $svc->service_type .' service set changed:'
					.' '. implode(', ', $arr);
		}

		$this->logEntry($svc->id, $svc->service_name, $log, $jsonId);

		return $id;
	}

	/**
	 * Get current service host associated with service and hostname
	 * @param $nodeId Node ID for the hostname's IP-address
	 * @return Row (object) from service_vhosts table, throws on error
	 */
	function getServiceVhost($svcSetId, $hostname, $nodeId) {
		$m = $this->getMySQLHandle();

		if(($ss = $this->getServiceSetById($svcSetId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcSetId)";
			throw new Exception($err);
		}

		$q = 'SELECT * FROM service_vhosts WHERE service_set_id=?
			AND node_id=? AND hostname=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('iis', $ss->id, $nodeId, $hostname);
		if(!$st->execute()) {
			$err = "Service vhost lookup failed: ". $m->error;
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	/**
	 * List current service hosts associated with service
	 * @param $svcSetId Service set ID
	 * @param $hostname Hostname
	 * @return Row (object) from service_vhosts table, throws on error
	 */
	function listServiceVhosts($svcSetId, $hostname) {
		$m = $this->getMySQLHandle();

		if(($ss = $this->getServiceSetById($svcSetId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcSetId)";
			throw new Exception($err);
		}

		$q = 'SELECT * FROM service_vhosts WHERE service_set_id=?
			AND hostname=? AND entry_type="current"';
		$st = $m->prepare($q);
		$st->bind_param('is', $ss->id, $hostname);
		if(!$st->execute()) {
			$err = "Service vhost lookup failed: ". $m->error;
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

	/**
	 * Add service virtual host
	 * @param $svcSetId Service set ID
	 * @param $hostname Hostname
	 * @param $ip IP-address
	 * @return Service vhost ID, throws on error
	 */
	function addServiceVhost($svcSetId, $hostname, $ip) {
		$m = $this->getMySQLHandle();

		if(($ss = $this->getServiceSetById($svcSetId)) === NULL) {
			$err = __METHOD__ .": No service set defined for"
				." service ($svcSetId, $hostname, $ip)";
			throw new Exception($err);
		}

		$svc = $this->getServiceById($ss->service_id);

		$jsonRow = $this->getJsonByHash($svc->id, $ss->json_sha256);
		$hostExistInServiceSet = FALSE;
		foreach(json_decode($jsonRow->json) as $svcHost) {
			if(!strcasecmp($svcHost->hostname, $hostname)) {
				$hostExistInServiceSet = TRUE;
				break;
			}
		}

		if(!$hostExistInServiceSet) {
			$err = __METHOD__ .": Host not defined in service set"
				." with ID $ss->id ($hostname, $ip)";
			throw new Exception($err);
		}

		$nodeId = $this->addNode($ip);
		$vhost = $this->getServiceVhost($ss->id, $hostname, $nodeId);
		if($vhost && $vhost->node_id == $nodeId)
			return $vhost->id;

		$q = 'INSERT INTO service_vhosts SET service_set_id=?,
			service_id=?, node_id=?, entry_type="current",
			hostname=?, ip=?, service_type=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iiisss', $ss->id, $svc->id, $nodeId,
				$hostname, $ip, $svc->service_type);
		if(!$st->execute()) {
			$err = "Service vhost add ($hostname, $ip)"
				." failed: ". $m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		if($vhost !== NULL) {
			$q = 'UPDATE service_vhosts SET entry_type="revision"
				WHERE id=?';
			$st = $m->prepare($q);
			$st->bind_param('i', $vhost->id);
			if(!$st->execute()) {
				$err = "Service vhost revision update ($svcSetId)"
					." failed: ". $m->error;
				throw new Exception($err);
			}

			$st->close();
			$oldNode = $this->getNodeById($vhost->node_id);
			$log = sprintf('Virtual host changed: %s [%s -> %s]',
					$hostname, $oldNode->ip, $ip);
			$this->logEntry($svc->id, $svc->service_name, $log);
		}

		return $id;
	}

	/**
	 * Lookup node by primary key
	 * @param $nodeId Node ID
	 * @return Row (object) from nodes table, throws on error
	 */
	function getNodeById($nodeId) {
		$m = $this->getMySQLHandle();

		$st = $m->prepare('SELECT * FROM nodes WHERE id=?');
		$st->bind_param('i', $nodeId);
		if(!$st->execute()) {
			$err = "Node lookup ($nodeId) failed:"
				." ". $m->error;
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	/**
	 * Lookup node by IP-address
	 * @param $ip IP-address
	 * @return Row (object) from nodes table, throws on error
	 */
	function getNodeByIp($ip) {
		$m = $this->getMySQLHandle();

		$st = $m->prepare('SELECT * FROM nodes WHERE ip=?');
		$st->bind_param('s', $ip);
		if(!$st->execute()) {
			$err = "Node lookup ($ip) failed: ". $m->error;
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
		$m = $this->getMySQLHandle();

		$node = $this->getNodeByIp($ip);
		if($node !== NULL)
			return $node->id;

		$q = 'INSERT INTO nodes SET ip=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('s', $ip);
		if(!$st->execute()) {
			$err = "Node add ($ip) failed: ". $m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	function getCertByHash($sha256Hash) {
		$m = $this->getMySQLHandle();

		$q = 'SELECT * FROM certs WHERE pem_sha256=?';
		$st = $m->prepare($q);
		$st->bind_param('s', $sha256Hash);
		if(!$st->execute()) {
			$err = "Cert lookup ($sha256Hash)"
				." failed: ". $m->error;
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
		$m = $this->getMySQLHandle();

		$hash = hash('sha256', $pem);
		$node = $this->getCertByHash($hash);
		if($node !== NULL)
			return $node->id;

		$q = 'INSERT INTO certs
			SET pem_sha256=?, x509=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('ss', $hash, $pem);
		if(!$st->execute()) {
			$err = "Cert add ($hash) failed: ". $m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	function addCertVhostMapping($certId, $vhostId) {
		$m = $this->getMySQLHandle();

		$q = 'SELECT id FROM service_vhost_certs
			WHERE cert_id=? AND vhost_id=?';
		$st = $m->prepare($q);
		$st->bind_param('ii', $certId, $vhostId);
		if(!$st->execute()) {
			$err = "Cert vhost lookup ($certId, $vhostId)"
				." failed: ". $m->error;
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
		$st = $m->prepare($q);
		$st->bind_param('ii', $certId, $vhostId);
		if(!$st->execute()) {
			$err = "Cert vhost add ($certId, $vhostId)"
				." failed: ". $m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}
}

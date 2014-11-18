<?php
/**
 * Protokollen PHP class
 *
 */

require_once(dirname(__FILE__) .'/MySQL.config.php');

class ProtokollenBase {

	const SERVICE_TYPE_HTTP		= 'HTTP';
	const SERVICE_TYPE_HTTPS	= 'HTTPS';
	const SERVICE_TYPE_WEBMAIL	= 'Webmail';
	const SERVICE_TYPE_SMTP		= 'SMTP';
	const SERVICE_TYPE_DNS		= 'DNS';

	private $m;
	private static $dbInstance = null;

	function __construct() {
		$this->m = ProtokollenBase::getMySQLInstance();
	}

	protected static function getMySQLInstance() {
		if(!ProtokollenBase::$dbInstance) {
			$m = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
			$m->set_charset(DB_CHARSET);
			ProtokollenBase::$dbInstance = $m;
		}

		return ProtokollenBase::$dbInstance;
	}

	function getMySQLHandle() {
		return $this->m;
	}

	/**
	 * List entity IDs
	 * @returns Array of IDs, throws on error
	 */
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

	/**
	 * List entity domains
	 * @returns Array of domains, throws on error
	 */
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

	/**
	 * Get entity object by ID
	 * @param $entityId Entity ID
	 * @returns Object or NULL, throws on error
	 */
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

	/**
	 * Get entity object by domain
	 * @param $domain Domain
	 * @returns Object or NULL, throws on error
	 */
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

	/**
	 * Add entity
	 * @param $domain Domain
	 * @param $emailDomain E-mail domain
	 * @param $url URL
	 * @param $org Organization
	 * @param $orgShort Organization short
	 * @param $orgGroup Organization group
	 * @returns ID of entity, throws on error
	 */
	function addEntity($domain, $emailDomain, $url, $org, $orgShort = NULL, $orgGroup = NULL) {
		$e = $this->getEntityByDomain($domain);
		if($e !== NULL) {
			return $e->id;
		}

		$q = 'INSERT INTO entities
			SET domain=?, domain_email=?, url=?,
			org=?, org_short=?, org_group=?, created=NOW()';
		if(($st = $this->m->prepare($q)) === FALSE) {
			$err = "Add entity ($domain, $emailDomain)"
				." failed: ". $this->m->error;
			throw new Exception($err);
		}
		$st->bind_param('ssssss', $domain, $emailDomain, 
				$url, $org, $orgShort, $orgGroup);
		if(!$st->execute()) {
			$err = "Add entity ($domain, $emailDomain)"
				." failed: ". $this->m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	/**
	 * Add entity tag
	 * @param $entityId Entity ID
	 * @param $tag Tag name
	 * @returns ID of entity tag mapping row, throws on error
	 */
	function addEntityTag($entityId, $tag) {
		$q = 'SELECT id FROM tags WHERE tag=?';
		$st = $this->m->prepare($q);
		$st->bind_param('s', $tag);
		$st->execute();
		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		if($row === NULL) {
			$q = 'INSERT INTO tags SET tag=?, created=NOW()';
			$st = $this->m->prepare($q);
			$st->bind_param('s', $tag);
			$st->execute();
			$tagId = $st->insert_id;
			$st->close();
		}
		else {
			$tagId = $row->id;
		}

		$q = 'SELECT * FROM entity_tags WHERE entity_id=? AND tag_id=?';
		$st = $this->m->prepare($q);
		$st->bind_param('ii', $entityId, $tagId);
		$st->execute();
		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();
		if($row !== NULL)
			return $row->id;

		$q = 'INSERT INTO entity_tags
			SET entity_id=?, tag_id=?, created=NOW()';
		$st = $this->m->prepare($q);
		$st->bind_param('ii', $entityId, $tagId);
		$st->execute();
		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	/**
	 * Add entity source
	 * @param $entityId Entity ID
	 * @param $source Source name
	 * @param $sourceId Source id
	 * @param $sourceUrl Source URL
	 * @returns ID of entity source, throws on error
	 */
	function addEntitySource($entityId, $source, $sourceId, $sourceUrl = NULL) {

		$q = 'SELECT * FROM entity_sources
			WHERE entity_id=? AND source=? AND source_id=?';
		$st = $this->m->prepare($q);
		$st->bind_param('iss', $entityId, $source, $sourceId);
		$st->execute();
		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();
		if($row !== NULL)
			return $row->id;

		$q = 'INSERT INTO entity_sources SET entity_id=?, source=?,
			source_id=?, source_url=?, created=NOW()';
		$st = $this->m->prepare($q);
		$st->bind_param('isss', $entityId, $source, $sourceId, $sourceUrl);
		$st->execute();
		$id = $st->insert_id;
		$st->close();

		return $id;
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
			$q = 'SELECT * FROM services WHERE entity_id=? AND service_type=?
					ORDER BY LENGTH(service_name), service_name, service_type';
			$st = $m->prepare($q);
			$st->bind_param('is', $entityId, $svcType);
		}
		else {
			$q = 'SELECT * FROM services WHERE entity_id=?
					ORDER BY LENGTH(service_name), service_name, service_type';
			$st = $m->prepare($q);
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
		if($svc !== NULL) {
			if($svcDesc === NULL)
				return $svc->id;

			$q = 'UPDATE services SET service_desc=? WHERE id=?';
			$st = $this->m->prepare($q);
			$st->bind_param('si', $svcDesc, $svc->id);
			if(!$st->execute()) {
				$err = "Update service ($entityId, $svcType,"
					." $svcName) failed: ". $this->m->error;
				throw new Exception($err);
			}
			$st->close();

			return $svc->id;
		}

		$q = 'INSERT INTO services SET entity_id=?, entity_domain=?,
					service_type=?, service_name=?,
					service_desc=?, created=NOW()';
		$st = $this->m->prepare($q);
		$st->bind_param('issss', $entityId, $e->domain, $svcType,
				$svcName, $svcDesc);
		if(!$st->execute()) {
			$err = "Add service ($entityId, $svcType, $svcName)"
				." failed: ". $this->m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
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
	protected function addJson($svcId, $json) {
		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		$hash = hash('sha256', $json);
		$q = 'SELECT id FROM json WHERE service_id=? AND json_sha256=?';
		$st = $this->m->prepare($q);
		$st->bind_param('is', $svcId, $hash);
		if(!$st->execute()) {
			$err = "JSON lookup ($svcId, $sha256)"
				." failed: ". $this->m->error;
			error_log($err);
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		if($row !== NULL)
			return $row->id;

		$q = 'INSERT INTO json SET service_id=?, json_sha256=?,
			service=?, json=?, created=NOW()';
		$st = $this->m->prepare($q);
		$st->bind_param('isss', $svc->id, $hash,
			$svc->service_name, $json);
		if(!$st->execute()) {
			$err = "JSON add ($svc->id, $hash)"
				." failed: ". $this->m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	function logEntry($svcId, $hostname, $msg, $jsonId = NULL) {
		$m = $this->m;
		$svc = $this->getServiceById($svcId);
		$q = 'INSERT INTO logs SET service_id=?, json_id=?, hostname=?,
			service=?, `log`=?, created=NOW()';
		$st = $m->prepare($q);
		$service = "$svc->service_name ($svc->service_type)";
		$st->bind_param('iisss', $svcId, $jsonId, $hostname,
				$service, $msg);
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

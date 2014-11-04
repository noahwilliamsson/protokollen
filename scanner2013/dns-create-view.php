<?php

class DnsMap {

	private $zoneId = NULL;
	private static $m = NULL;

	public $domain;
	public $version;
	public $created;
	public $updated;

	public function __construct($zoneId) {

		$this->zoneId = $zoneId;
		$this->reload($this->zoneId);
	}

	/**
	 * Reload zone details from databases
	 */
	private function reload($zoneId) {
		$m = self::getDbInstance();

		$q = 'SELECT * FROM dns_zones WHERE id="'. $m->escape_string($zoneId) .'"';
		if(($r = @$m->query($q)) === FALSE)
			return NULL;

		$row = $r->fetch_object();
		$r->close();
		if($row === NULL)
			throw new Exception('Unknown zone ID');

		$this->domain = $row->domain;
		$this->version = $row->version;
		$this->created = $row->created;
		$this->updated = $row->updated;
	}

	/**
	 * Return instance of self
	 *
	 * $domain Domain (zone)
	 *
	 * @return New instance or NULL if not found
	 */
	public static function lookupZone($domain) {

		$id = self::getZoneId($domain);
		if($id === NULL)
			return NULL;

		return new self($id);
	}

	/**
	 * Get zone ID for domain
	 *
	 * $domain Domain (zone)
	 * 
	 * @return Zone ID or NULL if not found
	 */
	private static function getZoneId($domain) {

		$m = self::getDbInstance();

		$q = 'SELECT * FROM dns_zones WHERE domain="'. $m->escape_string($domain) .'"';
		if(($r = @$m->query($q)) === FALSE)
			return NULL;

		$row = $r->fetch_object();
		$r->close();
		if($row === NULL)
			return NULL;

		return intval($row->id);
	}

	/**
	 * Add zone to database
	 *
	 * $domain Domain (zone)
	 *
	 * @return Zone ID or NULL if database error
	 */
	public static function addZone($domain) {
		$id = self::getZoneId($domain);
		if($id !== NULL)
			return $id;

		$m = self::getDbInstance();
		$q = 'INSERT INTO dns_zones SET created=NOW(), domain="'. $m->escape_string($domain) .'"';
		if(@$m->query($q) === FALSE)
			return NULL;

		return $m->insert_id;
	}

	public function getViewIdFromSerial($serial) {
		$m = self::getDbInstance();

		$q = 'SELECT id FROM dns_views WHERE zone_id="'. $m->escape_string($this->zoneId) .'" AND serial="'. $m->escape_string($serial) .'"';
		if(($r = @$m->query($q)) === FALSE)
			return NULL;

		$row = $r->fetch_object();
		$r->close();
		if($row === NULL)
			return NULL;

		return $row->id;
	}

	private function updateVersion() {
		$m = self::getDbInstance();

		$q = 'UPDATE dns_zones SET version=version+1 WHERE id="'. $m->escape_string($this->zoneId) .'"';
		if(($r = @$m->query($q)) === FALSE) {
			echo "DEBUG: $m->error, SQL: $q\n";
			return NULL;
		}

		$this->reload($this->zoneId);

		return $this;
	}

	public function updateTimestamp() {
		$m = self::getDbInstance();

		$q = 'UPDATE dns_zones SET updated=NOW() WHERE id="'. $m->escape_string($this->zoneId) .'"';
		if(($r = @$m->query($q)) === FALSE) {
			echo "DEBUG: $m->error, SQL: $q\n";
			return NULL;
		}
		
		$this->reload($this->zoneId);
		
		$q = 'UPDATE dns_views SET updated=NOW() WHERE zone_id="'. $m->escape_string($this->zoneId) .'" AND version="'. $m->escape_string($this->version) .'"';
		if(($r = @$m->query($q)) === FALSE) {
			echo "DEBUG: $m->error, SQL: $q\n";
			return NULL;
		}
		
		return $this;
	}

	public function addView($serial, $nameserver, $rrData) {

		$m = self::getDbInstance();

		if($this->updateVersion() === NULL) {
			echo "DEBUG: updateVersion() failed\n";
			return NULL;
		}

		$fields = array();
		$fields[] = 'created=NOW()';
		$fields[] = 'zone_id="'. $m->escape_string($this->zoneId) .'"';
		$fields[] = 'version="'. $m->escape_string($this->version) .'"';
		$fields[] = 'serial="'. $m->escape_string($serial) .'"';
		$fields[] = 'nameserver="'. $m->escape_string($nameserver) .'"';
		$q = 'INSERT INTO dns_views SET '. implode(',', $fields);
		if(@$m->query($q) === FALSE) {
			echo "DEBUG: $m->error, SQL: $q\n";
			return NULL;
		}

		$viewId = $m->insert_id;

		foreach($rrData as $rr) {
			list($hostname, $rrType, $rdCount, $data) = $rr;
			$fields = array();
			$fields[] = 'view_id="'. $m->escape_string($viewId) .'"';
			$fields[] = 'zone_id="'. $m->escape_string($this->zoneId) .'"';
			$fields[] = 'version="'. $m->escape_string($this->version) .'"';
			$fields[] = 'hostname="'. $m->escape_string($hostname) .'"';
			$fields[] = 'rr_type="'. $m->escape_string($rrType) .'"';
			$fields[] = 'rd_count="'. $m->escape_string($rdCount) .'"';
			$fields[] = 'rr_data="'. $m->escape_string($data) .'"';
			$fields[] = 'created=NOW()';

			$q = 'INSERT INTO dns_data SET '. implode(',', $fields);
			if(@$m->query($q) === FALSE) {
				echo "DEBUG: $m->error, SQL: $q\n";
				return NULL;
			}
		}

		return $this;
	}

	/**
	 * Update zone
	 *
	 * @return This or NULL on error
	 */
	public function refresh() {

		$nsList = self::dig('NS', $this->domain);
		if(empty($nsList)) return NULL;

		/* Find nameserver with highest serial */
		$serials = array();
		$maxSerial = 0;
		$nameserver = NULL;
		foreach($nsList as $ns) {
			$arr = self::dig('SOA', $this->domain, $ns);
			if(empty($arr)) continue;

			$arr = explode(' ', $arr[0]);
			if(count($arr) < 3) continue;
			$serial = $arr[2];
			echo "$this->domain: Serial $serial at nameserver $ns\n";

			if(!isset($serials[$serial])) $serials[$serial] = array();
			$serials[$serial][] = $ns;
			sort($serials[$serial]);

			if($serial > $maxSerial) {
				$maxSerial = $serial;
				$nameserver = $ns;
			}
		}

		if($nameserver === NULL) {
			echo "$this->domain: No nameservers found\n";
			return NULL;
		}


		echo "$this->domain: Checking serial $maxSerial\n";
		foreach($serials[$maxSerial] as $nameserver) {

			echo "$this->domain: Querying nameserver $nameserver with serial $maxSerial\n";
			$viewId = $this->getViewIdFromSerial($maxSerial);
			if($viewId !== NULL) {
				echo "$this->domain: Known serial $maxSerial, only updating timestamp\n";
				/* Update last time of check */
				$this->updateTimestamp();
				break;
			}

			$rrData = array();


			$hostname = $this->domain;

			$arr = self::dig('SOA', $hostname, $nameserver);
			if($arr === NULL) continue;
			$rrData[] = array($hostname, 'SOA', 7, $arr[0]);

			$arr = self::dig('NS', $hostname, $nameserver);
			if($arr === NULL) continue;
			sort($arr);
			foreach($arr as $rr) $rrData[] = array($hostname, 'NS', 1, $rr);
	
			$arr = self::dig('MX', $hostname, $nameserver);
			if($arr === NULL) continue;
			sort($arr);
			foreach($arr as $rr) $rrData[] = array($hostname, 'MX', 2, $rr);

			$arr = self::dig('A', $hostname, $nameserver);
			if($arr === NULL) continue;
			sort($arr);
			foreach($arr as $rr) $rrData[] = array($hostname, 'A', 1, $rr);
	
			$arr = self::dig('AAAA', $hostname, $nameserver);
			if($arr === NULL) continue;
			sort($arr);
			foreach($arr as $rr) $rrData[] = array($hostname, 'AAAA', 1, $rr);

			$arr = self::dig('CNAME', $hostname, $nameserver);
			if($arr === NULL) continue;
			sort($arr);
			foreach($arr as $rr) $rrData[] = array($hostname, 'CNAME', 1, $rr);

	
			$hostname = 'www.'. $this->domain;
	
			$arr = self::dig('A', $hostname, $nameserver);
			if($arr === NULL) continue;
			sort($arr);
			foreach($arr as $rr) $rrData[] = array($hostname, 'A', 1, $rr);

			$arr = self::dig('AAAA', $hostname, $nameserver);
			if($arr === NULL) continue;
			sort($arr);
			foreach($arr as $rr) $rrData[] = array($hostname, 'AAAA', 1, $rr);

			$arr = self::dig('CNAME', $hostname, $nameserver);
			if($arr === NULL) continue;
			sort($arr);
			foreach($arr as $rr) $rrData[] = array($hostname, 'CNAME', 1, $rr);

			if($this->addView($maxSerial, $nameserver, $rrData) === NULL) {
				echo "$this->domain: Failed to add new view of serial $maxSerial\n";
				continue;
			}

			echo "$this->domain: Added snapshot of zone with serial $maxSerial from nameserver $nameserver\n";
			break;
		}

		return $this;
	}



	public static function dig($type, $hostname, $ns = NULL) {

		$args = array();
		$args[] = 'dig';
		$args[] = '+short';
		$args[] = '+time=2';
		$args[] = '-t';
		$args[] = escapeshellarg($type);
		$args[] = escapeshellarg($hostname);
		if($ns !== NULL)
			$args[] = escapeshellarg('@'. $ns);

		//echo "EXEC: ". implode(' ', $args) ."\n";
		exec(implode(' ', $args), $output, $exitcode);
		if($exitcode !== 0)
			return NULL;

		$arr = array();
		foreach($output as $line) if(substr($line, 0, 2) != ';;') $arr[] = $line;
		$output = $arr;

		if(strcasecmp($type, 'a') == 0 || strcasecmp($type, 'aaaa') == 0) {
			/* dig +short -t a may return a CNAME */
			$arr = array();
			foreach($output as $line)
				if(@inet_pton($line) !== FALSE)
					$arr[] = $line;
			$output = $arr;
		}

		return $output;
	}

	/**
	 * Get shared database handler
	 */
	public static function getDbInstance() {
		if(self::$m === NULL) {
			$m = new mysqli('localhost','debian-sys-maint','GNfLPgFFIQqfXXUb','pc');
			$m->set_charset('utf8');
			self::$m = $m;
		}

		return self::$m;
	}
}


if($argc != 2)
	die("Usage: ${argv[0]} <domain>\n");

$domains = array();
if($argv[1] == '.') {	
	$m = DnsMap::getDbInstance();
	$q = 'SELECT domain FROM dns_zones ORDER BY updated';
	if(($r = @$m->query($q)) !== FALSE) {
		while($row = $r->fetch_object())
			$domains[] = $row->domain;
		$r->close();
	}
}
else 
	$domains[] = $argv[1];

foreach($domains as $domain) {
	$zoneId = DnsMap::addZone($domain);
	$zone = new DnsMap($zoneId);
	$zone->refresh();
}

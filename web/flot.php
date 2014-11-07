<?php
/**
 * Quick hack to make two data series for flot graphs
 */

require_once('../php/Protokollen.class.php');


function makeFlots($entityIds) {
	$p = new Protokollen();
	$m = $p->getMySQLHandle();

	$jsonIds = array();
	$protocols = array('none' => 0);

	if(empty($entityIds)) {
		/* Needs to contain something or the query will fail */
		$entityIds[] = 0;
	}

	$q = 'SELECT id FROM entities WHERE id IN('. implode(',', $entityIds) .')';
	$r = $m->query($q);
	while($row = $r->fetch_object()) {
		/**
		 * List all HTTP sites belonging to an entity
		 * Each site may consist of multiple hostnames (example.com, www.example.com)
		 */
		$sites = $p->listServices($row->id, Protokollen::SERVICE_TYPE_HTTP);
		foreach($sites as $site) {
			$q = 'SELECT json_id, sslv2, sslv3, tlsv1, tlsv1_1, tlsv1_2
					FROM service_tls_statuses
					WHERE service_id="'. $m->escape_string($site->id) .'" AND entry_type="current"';

			$rt = $m->query($q) or die($m->error);
			while($rtRow = $rt->fetch_object()) {

				$jsonIds[] = $rtRow->json_id;

				$numTlsProtocolsSupported = false;
				foreach($rtRow as $key => $value) {
					if($key === 'json_id') continue;
					if(!isset($protocols[$key]))
						$protocols[$key] = 0;

					if($value == 0)
						continue;

					$numTlsProtocolsSupported = true;
					$protocols[$key] += 1;
				}

				if(!$numTlsProtocolsSupported)
					$protocols['none']++;
			}

			$rt->close();
		}
	}
	$r->close();

	/* Convert to flot data series */
	$flot = array();
	foreach($protocols as $key => $value) {
		$obj = new stdClass();
		$obj->label = $key;
		$obj->data = intval($value);
		$flot[] = $obj;
	}


	$uniqueIps = array();
	$protocols = array('none' => 0);

	if(empty($jsonIds)) {
		/* Needs to contain something or the query will fail */
		$jsonIds[] = 0;
	}

	$q = 'SELECT id, json FROM json WHERE id IN('. implode(',', $jsonIds) .')';
	$r = $m->query($q);
	while($row = $r->fetch_object()) {
		$arr = json_decode($row->json);
		if($arr === NULL) {
			/* Broken JSON */
			continue;
		}

		foreach($arr as $ip) {
			$uniqueIps[] = $ip->ip;
			$numSupportedProtos = 0;
			foreach($ip->protocols as $proto) {
				/* Ignore unsupported protocols */
				if(!$proto->supported)
					continue;

				if(isset($proto->extensions) && $proto->extensions->sniNameUnknown) {
					/* Skip virtual hosts where the target hostname is not known */
					continue;
				}

				$numSupportedProtos++;
				if(!isset($protocols[$proto->name]))
					$protocols[$proto->name] = 0;
				$protocols[$proto->name]++;
			}

			if(!$numSupportedProtos)
				$protocols['none']++;
		}
	}

	$r->close();
	$uniqueIps = array_unique($uniqueIps);

	$flot2 = array();
	foreach($protocols as $key => $value) {
		$obj = new stdClass();
		$obj->label = $key;
		$obj->data = intval($value);
		$flot2[] = $obj;
	}

	$arr = array($flot, $flot2, $uniqueIps);
	return $arr;
}

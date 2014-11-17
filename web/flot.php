<?php
/**
 * Quick hack to make two data series for flot graphs
 */

require_once('../php/ServiceGroup.class.php');
require_once('../php/TestSslprobe.class.php');


function makeFlots($entityIds) {
	$p = new ServiceGroup();
	$testSslprobe = new TestSslprobe();

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
		$sites = $p->listServices($row->id, ProtokollenBase::SERVICE_TYPE_HTTPS);
		foreach($sites as $svc) {
			$grp = $p->getServiceGroup($svc->id);
			if(!$grp)
				continue;

			foreach($grp->json as $svcHost) {
				$probe = $testSslprobe->getItem($svc->id, $grp->id, $svcHost->hostname);
				if(!$probe)
					continue;

				$jsonIds[] = $probe->json_id;
				$numTlsProtocolsSupported = false;
				foreach($probe as $key => $value) {
					if(!in_array($key, array('sslv2', 'sslv3', 'tlsv1', 'tlsv1_1', 'tlsv1_2')))
						continue;
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


	$flot3 = array();
	ksort($protocols, SORT_FLAG_CASE|SORT_NATURAL);
	$idx = 0;
	foreach($protocols as $key => $value) {
		$key = strtoupper($key);
		$key = str_replace(' ', 'v', $key);
		switch($key) {
		case 'NONE': $color = 'black'; break;
		case 'SSLv2.0':
		case 'SSLv3.0': $color = 'red'; break;
		case 'TLSv1.0':
		case 'TLSv1.1': $color = '#FFD700'; break;
		case 'TLSv1.2': $color = 'green'; break;
		}

		$obj = new stdClass();
		$obj->data[] = array($idx, $value);
		$obj->color = $color;
		$flot3[$key] = $obj;
		$idx++;
	}



	$arr = array($flot, $flot2, $uniqueIps, $flot3);
	return $arr;
}

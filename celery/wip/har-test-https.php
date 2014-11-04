<?php
/**
 * Test support of HTTPS on URLs from HAR (netsniff.js) 
 *
 */

// Abort test on domain if more than N resources fail to load over https
$maxBroken = 5;

$c = curl_init();
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/534.34 (KHTML, like Gecko) PhantomJS/1.9.2 Safari/534.34 (siteintegrity)');
curl_setopt($c, CURLOPT_TIMEOUT, 2);
curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);


function getHttpResources($filename) {
	$resources = array();

	$data = file_get_contents($filename);
	$obj = json_decode($data);
	foreach($obj->log->entries as $e) {
		$r = $e->request;

		if(substr($r->url, 0, 5) !== 'http:') 
			continue;

		$resources[] = $r->url;
	}

	return $resources;
}

function mapResourcesByDomain($resources) {
	$domainMap = array();
	foreach($resources as $url) {
		$arr = parse_url($url);

		$domain = $arr['host'];
		if(isset($arr['port'])) $domain .= ':'. $arr['port'];

		if(!isset($domainMap[$domain])) {
			$o = new stdClass();
			$o->domain = $domain;
			$o->resources = array();
			$o->numStable = 0;
			$o->numBroken = 0;
			$o->numUntested = 0;
			$o->verdict = 'Unknown';
			$o->n = 0;
			$o->failedResources = array();
			$domainMap[$domain] = $o;
		}

		$domainMap[$domain]->resources[] = $url;
		$domainMap[$domain]->n++;
	}

	return $domainMap;
}

function testResource(&$obj, $resource) {
	global $c;
	global $maxBroken;

	if($obj->numBroken > $maxBroken) {
		echo "- Domain $obj->domain has failed $obj->numBroken tests, skipping URL: $resource\n";
		$obj->numUntested++;
		return FALSE;
	}

	$urls = array($resource, 'https:'. substr($resource, 5));
	$arr = array();
	foreach($urls as $url) {
		$o = new stdClass();
		$o->url = $url;
		$o->error = '';
		$o->status = 0;

		curl_setopt($c, CURLOPT_URL, $url);
		$o->data = curl_exec($c);
		$o->status = curl_getinfo($c, CURLINFO_HTTP_CODE);
		$o->lastUrl = curl_getinfo($c, CURLINFO_EFFECTIVE_URL);
		$o->size = curl_getinfo($c, CURLINFO_SIZE_DOWNLOAD);
		if($o->data === FALSE) {
			$o->data = '';
			$o->error = curl_error($c) .' ('. curl_errno($c) .')';
		}

		$arr[] = $o;
	}

	list($a, $b) = $arr;
	$ok = TRUE;
	foreach(array('error', 'status') as $key) {
		if($a->$key !== $b->$key) {
			$msg = "Differ on $key: '{$a->$key}' vs '{$b->$key}'";
			$obj->failedResources[$resource] = $msg;
			$obj->numBroken++;
			return FALSE;
		}
	}

	$factor = 1.0;
	if($b->size > 0) $factor = abs(1.0 - ($a->size / $b->size));
	if($a->size != $b->size && $factor > 0.03) {
		$key = 'size';
		$msg = "Differ on size: '{$a->$key}' vs '{$b->$key}' (factor: $factor)";
		$obj->failedResources[$resource] = $msg;
		$obj->numBroken++;
		return FALSE;
	}

	$key = 'lastUrl';
	if($a->$key === $b->$key) {
		$msg = "Differ on $key: '{$a->$key}' vs '{$b->$key}' (expected https://)\n";
		$obj->failedResources[$resource] = $msg;
		$obj->numBroken++;
		return FALSE;
	}

	$obj->numStable++;
	return TRUE;
}

$filename = 'php://stdin';
if($argc > 1) $filename = $argv[1];

$resources = getHttpResources($filename);
$resources = array_unique($resources);
sort($resources);
$domainMap = mapResourcesByDomain($resources);

echo "[i] ". count($domainMap) ." different domains in HAR\n";
$n = count($domainMap);
$i = 0;
foreach($domainMap as $domain => &$obj) {

	$i++;
	echo sprintf("[%3d/%-3d] Testing domain %s: Testing %d resources\n", $i, $n, $obj->domain, $obj->n);
	foreach($obj->resources as $url) {
		$ret = testResource($obj, $url);
		if($ret === TRUE)
			continue;

		if($obj->numUntested) {
			echo "$domain: Skipped resource $url (too many errors)\n";
			continue;
		}

		$arr = array_slice(array_values($obj->failedResources), count($obj->failedResources) - 1);
		$err = $arr[0];
		echo "$domain: Resource $url failed: $err\n";
	}
}

echo "=========================\n\n";

echo "[i] ". count($domainMap) ." different domains seen\n";
foreach($domainMap as $domain => &$obj) {
	$verdict = 'OK';
	if(!$obj->numBroken)
		$verdict = 'OK';
	else if($obj->numUntested)
		$verdict = 'BROKEN (resources skipped)';
	else if($obj->numBroken && !$obj->numStable)
		$verdict = 'BROKEN';
	else if($obj->numStable > $obj->numBroken)
		$verdict = "Most OK";
	else
		$verdict = "Some OK";

	$obj->prefix = '+';
	if($verdict !== 'OK') $obj->prefix = '-';
	$obj->verdict = "[$obj->prefix] $domain: $obj->n resources total, $obj->numStable OK, $obj->numBroken broken, $obj->numUntested not tested: $verdict";
}

foreach($domainMap as $domain => &$obj) {
	if($obj->prefix !== '+')
		continue;
	echo "$obj->verdict\n";
}

foreach($domainMap as $domain => &$obj) {
	if($obj->prefix === '+')
		continue;

	echo "$obj->verdict\n";
	if($obj->numBroken) {
		$errorList = array();
		foreach($obj->failedResources as $url => $msg) $errorList[] = $msg;
		$errorList = array_unique($errorList);
		$numUniqueErrors = count($errorList);

		if($numUniqueErrors === 1 && count($obj->failedResources) > 1) {
			echo "- Common error: ". $errorList[0] ."\n";
		}
		else {
			foreach($obj->failedResources as $url => $msg) {
				if(strlen($url) > 72)
					$url = substr($url, 0, 72) .'…';
				echo "\t$url\t($msg)\n";
			}
		}
	}
}

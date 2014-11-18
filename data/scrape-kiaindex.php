#!/usr/bin/env php
<?php
/**
 * Script to scrape data off KIAindex.se
 * References:
 * http://www.kiaindex.se
 */

require_once('../php/ProtokollenBase.class.php');

define('SOURCE_URL', 'http://www.kiaindex.se/?page=1&number_per_page=-1&filter=1');


$c = curl_init();
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_TIMEOUT, 10);
curl_setopt($c, CURLOPT_USERAGENT, 'protokollen/1.0 (scraper)');

$sites = scrapeKiaList(fetch(SOURCE_URL));
echo json_encode($sites, JSON_PRETTY_PRINT) ."\n";


function fetch($url) {
	global $c;

	curl_setopt($c, CURLOPT_URL, $url);
	if(($data = curl_exec($c)) === FALSE) {
		error_log("ERROR: ". curl_error($c) .", URL: $url\n");
		return NULL;
	}
	else if(($code = curl_getinfo($c, CURLINFO_HTTP_CODE)) !== 200) {
		error_log("ERROR: Unexpected HTTP code $code for URL: $url\n");
		return NULL;
	}

	return $data;
}

function scrapeKiaObject($data) {
	$d = new DOMDocument();
	@$d->loadHTML($data);
	$x = new DOMXpath($d);

	$nodes = $x->query('//div[@id="site_info"]');
	if(!$nodes->length)
		return NULL;

	$obj = new stdClass();
	$obj->categories = array();
	$obj->url = NULL;

	$site = $nodes->item(0);
	foreach($x->query('.//p[contains(., "Kategorier")]/a') as $node)
		$obj->categories[] = $node->nodeValue;
	foreach($x->query('.//p[contains(., "Adress")]/a') as $node)
		$obj->url = $node->getAttribute('href');

	return $obj;
}

function scrapeKiaList($data) {
	$d = new DOMDocument();
	@$d->loadHTML($data);
	$x = new DOMXpath($d);

	foreach($x->query('//div[@id="list"]/div[not(contains(@class, "child-of-node")) and @id]') as $row) {
		$id = $row->getAttribute('id');
		$nodes = $x->query('div[contains(@class, "name") and not(contains(@class, "network"))]/a', $row);
		if(!$nodes->length)
			continue;

		$anchor = $nodes->item(0);
		$obj = new stdClass();
		$obj->title = $anchor->nodeValue;
		$obj->domain = NULL;
		$obj->source = $anchor->getAttribute('href');
		$obj->objectId = NULL;
		if(preg_match('@object_id=(\d+)@', $obj->source, $matches))
			$obj->objectId = intval($matches[1]);
		$obj->position = 0;
		foreach($x->query('div[contains(@class, "two")]', $row) as $node)
			$obj->position = intval($node->nodeValue);
		$obj->unique = 0;
		foreach($x->query('div[contains(@class, "four")]', $row) as $node)
			$obj->unique = intval(str_replace(' ', '', $node->nodeValue));
		$obj->visits = 0;
		foreach($x->query('div[contains(@class, "six")]', $row) as $node)
			$obj->visits = intval(str_replace(' ', '', $node->nodeValue));
		$obj->pageviews = 0;
		foreach($x->query('div[contains(@class, "eight")]', $row) as $node)
			$obj->pageviews = intval(str_replace(' ', '', $node->nodeValue));

		$url = 'http://www.kiaindex.se/?object_id='. $obj->objectId;
		$details = scrapeKiaObject(fetch($url));
		if($details !== NULL) {
			$obj->categories = $details->categories;
			if($details->url !== NULL)
				$obj->domain = parse_url($details->url, PHP_URL_HOST);
		}
		foreach(dns_get_record($obj->title, DNS_ANY) as $rr) {
			if(isset($rr['host'])) {
				$obj->domain = strtolower($rr['host']);
				break;
			}
		}

		if($obj->domain !== NULL) {
			/* KIA does this on new sites .. */
			$obj->domain = preg_replace('@\.new$@', '', $obj->domain);
			$obj->domain = str_replace(' ', '', $obj->domain);
		}

		if(!$obj->domain)
			continue;

		$arr[] = $obj;
	}

	return $arr;
}

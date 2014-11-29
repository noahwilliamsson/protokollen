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

$items = scrapeKiaList(fetch(SOURCE_URL));
echo json_encode($items, JSON_PRETTY_PRINT) ."\n";


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
		$obj->categories = array();
		$obj->email = NULL;
		$obj->name = $anchor->nodeValue;
		$obj->organization = NULL;
		$obj->private = new stdClass();
		$obj->source = $anchor->getAttribute('href');
		$obj->sourceId = NULL;
		$obj->url = NULL;
		$obj->zone = NULL;
		if(preg_match('@object_id=(\d+)@', $obj->source, $matches))
			$obj->sourceId = intval($matches[1]);
		$obj->private->position = 0;
		foreach($x->query('div[contains(@class, "two")]', $row) as $node)
			$obj->private->position = intval($node->nodeValue);
		$obj->private->unique = 0;
		foreach($x->query('div[contains(@class, "four")]', $row) as $node)
			$obj->private->unique = intval(str_replace(' ', '', $node->nodeValue));
		$obj->private->visits = 0;
		foreach($x->query('div[contains(@class, "six")]', $row) as $node)
			$obj->private->visits = intval(str_replace(' ', '', $node->nodeValue));
		$obj->private->pageviews = 0;
		foreach($x->query('div[contains(@class, "eight")]', $row) as $node)
			$obj->private->pageviews = intval(str_replace(' ', '', $node->nodeValue));

		$url = 'http://www.kiaindex.se/?object_id='. $obj->sourceId;
		$details = scrapeKiaObject(fetch($url));
		if($details !== NULL) {
			$obj->categories = array_merge(array('KIAindex.se'), $details->categories);
			sort($obj->categories);
			if($details->url !== NULL) {
				$domain = parse_url($details->url, PHP_URL_HOST);
				$obj->zone = preg_replace('@^www\.@i', '', $domain);
			}
		}

		foreach(dns_get_record($obj->name, DNS_ANY) as $rr) {
			if(isset($rr['host'])) {
				$domain = strtolower($rr['host']);
				$obj->zone = preg_replace('@^www\.@i', '', $domain);
				break;
			}
		}

		if($obj->zone !== NULL) {
			/* KIA does this on new sites .. */
			$obj->zone = preg_replace('@\.new$@', '', $obj->zone);
			$obj->zone = str_replace(' ', '', $obj->zone);
		}

		if(!$obj->zone)
			continue;

		$arr[] = $obj;
	}

	return $arr;
}

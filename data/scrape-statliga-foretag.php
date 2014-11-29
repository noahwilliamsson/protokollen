#!/usr/bin/env php
<?php
/**
 * Scrape government owned companties
 * References:
 * http://www.regeringen.se/sb/d/17677
 */

define('SOURCE_URL', 'http://www.regeringen.se/sb/d/17677');


$c = curl_init();
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($c, CURLOPT_USERAGENT, 'protokollen/1.0 (scraper)');

curl_setopt($c, CURLOPT_URL, SOURCE_URL);
if(($data = curl_exec($c)) === FALSE)
	die("ERROR: ". curl_error($c) .", URL: $url\n");
if(($code = curl_getinfo($c, CURLINFO_HTTP_CODE)) !== 200)
	die("ERROR: Unexpected HTTP code $code, URL: $url\n");

$dom = new DOMDocument();
@$dom->loadHTML($data);
$x = new DOMXpath($dom);

$result = array();
$prevCategory = $category = NULL;
foreach($x->query('//*[@class="noticeItem"]/div') as $itemNode) {

	$class = $itemNode->getAttribute('class');
	$nodes = $x->query('.//h3', $itemNode);
	if(strstr($class, 'white')) {
		foreach($nodes as $node)
			$category = $node->nodeValue;
		continue;
	}
	else if(!strstr($class, 'gray'))
		continue;

	$obj = new stdClass();
	$obj->categories = array('Statligt ägda företag', $category);
	$obj->email = NULL;
	$obj->name = NULL;
	$obj->organization = $category;
	$obj->private = new stdClass();
	$obj->source = SOURCE_URL;
	$obj->sourceId = NULL;
	$obj->url = NULL;
	$obj->zone = NULL;
	sort($obj->categories);
	foreach($x->query('.//h3', $itemNode) as $node)
		$obj->name = trim($node->nodeValue);
	foreach($x->query('.//li/a', $itemNode) as $node) {
		$class = $node->parentNode->getAttribute('class');
		if(!empty($class) && strstr($class, 'pdf')) {
			$obj->private->pdf = $node->getAttribute('href');
			if(substr($obj->private->pdf, 0, 1) === '/') {
				$base = parse_url(SOURCE_URL, PHP_URL_SCHEME) .'://';
				$base .= parse_url(SOURCE_URL, PHP_URL_HOST);
				$obj->private->pdf = $base . $obj->private->pdf;
			}
			continue;
		}
		else if(empty($obj->url)) {
			$obj->url = $node->getAttribute('href');
			$domain = parse_url($obj->url, PHP_URL_HOST);
			$obj->zone = preg_replace('@^www\.@i', '', $domain);
		}
	}

	if(empty($obj->zone))
		continue;

	$result[] = $obj;
}

echo json_encode($result, JSON_PRETTY_PRINT);

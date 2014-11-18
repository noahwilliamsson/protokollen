#!/usr/bin/env php
<?php
/**
 * Scrape Landsting addresses
 * References:
 * http://www.skl.se/kommuner_och_landsting/fakta-om-landsting-och-regioner/adresser_och_lansbokstaver_landsting
 */

define('SOURCE_URL', 'http://www.skl.se/kommuner_och_landsting/fakta-om-landsting-och-regioner/adresser_och_lansbokstaver_landsting');


$c = curl_init();
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);

curl_setopt($c, CURLOPT_URL, SOURCE_URL);
if(($data = curl_exec($c)) === FALSE)
	die("ERROR: ". curl_error($c) .", URL: $url\n");
if(($code = curl_getinfo($c, CURLINFO_HTTP_CODE)) !== 200)
	die("ERROR: Unexpected HTTP code $code, URL: $url\n");

if(!preg_match('@<!-- Page content starts here -->(.*)<!-- Page content stops here -->@s', $data, $matches))
	die("ERROR: Failed to extract data using regexp, URL: $url\n");

$html = $matches[1];
$dom = new DOMDocument();
@$dom->loadHTML($data);
$x = new DOMXpath($dom);

$result = array();
foreach($x->query('//*[@class="pagecontent sv-layout"]//*[@class="sv-text-portlet-content"]/p') as $pNode) {

	$obj = new stdClass();
	$obj->source = SOURCE_URL;
	foreach($pNode->childNodes as $node) {
		if($node->nodeName === 'a') {
			$href = $node->getAttribute('href');
			$text = $node->nodeValue;

			if(preg_match('/@/', $href)) {
				$obj->email = $text;
				list(,$obj->maildomain) = explode('@', $href);
				continue;
			}

			$obj->name = $text;
			$obj->url = $href;
			$domain = parse_url($href, PHP_URL_HOST);
			$domain = str_replace('www.', '', $domain);
			$obj->domain = $domain;
			continue;
		}
		else if($node->nodeType !== XML_TEXT_NODE) {
			continue;
		}

		$text = trim($node->nodeValue);
		if(preg_match('@Länsbokstav:\s+(\w+)@', $text, $matches))
			$obj->countyCodes = $matches[1];
		else if(preg_match('@^(\d+\s+\d+)\s+(.*)@', $text, $matches)) {
			$obj->zipcode = $matches[1];
			$obj->city = $matches[2];
		}
		else if(preg_match('@^[0-9 -]+$@', $text))
			$obj->phone = $text;
		else if(!isset($arr['address']))
			$obj->address = $text;
		else
			$obj->other = $text;
	}

	if(count(array_keys((array)$obj)) < 7)
		continue;
	$result[] = $obj;
}

echo json_encode($result, JSON_PRETTY_PRINT);

#!/usr/bin/env php
<?php
/**
 * Import JSON from data/scrape-*.php tools
 */

require_once('../php/ProtokollenBase.class.php');

mb_internal_encoding('utf-8');


if($argc != 2)
	die("Usage: ${argv[0]} <scrape-output.json>\n");

$p = new ProtokollenBase();
$filename = $argv[1];
$data = file_get_contents($filename);
$items = json_decode($data);
foreach($items as $item) {
	$zone = mb_convert_case($item->zone, MB_CASE_LOWER);
	$rrset = dns_get_record(idn_to_ascii($zone), DNS_SOA);
	if(empty($rrset)) {
		echo "Skipping site $item->zone with name '$item->name' because of missing SOA in DNS\n";
		continue;
	}

	/* Attempt to determine email domain */
	$emailDomain = $zone;
	if(isset($item->email) && !empty($item->email)) {
		list(, $emailDomain) = explode('@', $item->email);
	}

	$rrset = dns_get_record(idn_to_ascii($emailDomain), DNS_MX);
	if(empty($rrset)) {
		echo "No MX pointers for email domain '$emailDomain'\n";
		$emailDomain = NULL;
	}

	/* Attempt to determine URL */
	$url = NULL;
	if(isset($item->url) && !empty($item->url)) {
		$url = $item->url;
	}
	else {
		$hosts = array('www.'. $zone, $zone);
		foreach($hosts as $host) {
			$rrset = dns_get_record(idn_to_ascii($host), DNS_ANY);
			if(empty($rrset))
				continue;

			$url = 'http://'. mb_convert_case($host, MB_CASE_LOWER);
			break;
		}
	}

	/* Add entity */
	$e = $p->getEntityByDomain($zone);
	if($e === NULL) {
		echo "Creating entity for $zone\n";
		$id = $p->addEntity($zone, $emailDomain, $url, $item->name, $item->organization);
		$e = $p->getEntityById($id);
	}

	/* Add entity tags */
	foreach($item->categories as $category)
		$p->addEntityTag($e->id, $category);

	/* Add source reference */
	$sourceName = NULL;
	if(isset($item->source) && !empty($item->source))
		$sourceName = str_replace('www.', '', parse_url($item->source, PHP_URL_HOST));
	$sourceId = NULL;
	if(isset($item->sourceId) && !empty($item->sourceId))
		$sourceId = $item->sourceId;
	echo "* Adding source for entity $e->id: name=$sourceName, id=$sourceId, url=$item->source\n";
	$p->addEntitySource($e->id, $sourceName, $sourceId, $item->source);
}

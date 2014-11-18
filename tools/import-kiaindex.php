#!/usr/bin/env php
<?php
/**
 * Import JSON from data/scrape-kiaindex.php tool
 */

require_once('../php/ProtokollenBase.class.php');

mb_internal_encoding('utf-8');


if($argc != 2)
	die("Usage: ${argv[0]} <kia.json>\n");

$p = new ProtokollenBase();
$filename = $argv[1];
$data = file_get_contents($filename);
$kiaSites = json_decode($data);
foreach($kiaSites as $site) {
	$apex = str_replace('www.', '', $site->domain);
	$apex = mb_convert_case($apex, MB_CASE_LOWER);
	$rrset = dns_get_record($apex, DNS_SOA);
	if(empty($rrset)) {
		echo "Skipping site $site->domain with title '$site->title' because of missing SOA in DNS\n";
		continue;
	}

	$emailDomain = mb_convert_case($site->domain, MB_CASE_LOWER);
	$rrset = dns_get_record($apex, DNS_MX);
	if(empty($rrset))
		$emailDomain = NULL;

	$url = NULL;
	$hosts = array('www.'. $apex, $site->domain, $apex);
	foreach($hosts as $host) {
		$rrset = dns_get_record($host, DNS_ANY);
		if(empty($rrset))
			continue;

		$url = 'http://'. mb_convert_case($host, MB_CASE_LOWER);
		break;
	}

	$e = $p->getEntityByDomain($apex);
	if($e === NULL) {
		echo "No entity for $apex\n";
		$id = $p->addEntity($apex, $emailDomain, $url, $site->title);
		$e = $p->getEntityById($id);
	}

	foreach($site->categories as $category)
		$p->addEntityTag($e->id, $category);

	$p->addEntitySource($e->id, 'KIAindex.se', $site->objectId, $site->source);
}

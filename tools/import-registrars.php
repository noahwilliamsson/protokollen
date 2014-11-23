#!/usr/bin/env php
<?php
/**
 * Import data from http://www.dnssecandipv6.se/rdns/
 */

require_once('../php/ProtokollenBase.class.php');

mb_internal_encoding('utf-8');

if($argc != 2)
	die("Usage: ${argv[0]} <www.dnssecandipv6.se-rdns.html>\n");

$p = new ProtokollenBase();
$filename = $argv[1];
$data = file_get_contents($filename);
if(preg_match_all('@>([a-z0-9.-]*)</a>@', $data, $matches)) foreach($matches[1] as $apex) {

	$rrset = dns_get_record($apex, DNS_SOA);
	if(empty($rrset)) {
		echo "Skipping site $apex because of missing SOA in DNS\n";
		continue;
	}

	$emailDomain = mb_convert_case($apex, MB_CASE_LOWER);
	$rrset = dns_get_record($apex, DNS_MX);
	if(empty($rrset))
		$emailDomain = NULL;

	$url = NULL;
	$hosts = array('www.'. $apex, $apex);
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
		$id = $p->addEntity($apex, $emailDomain, $url, $apex);
		$e = $p->getEntityById($id);
	}

	$p->addEntityTag($e->id, 'Domänregistrarer (.SE)');
	$p->addEntitySource($e->id, 'http://www.dnssecandipv6.se/rdns/', NULL, 'http://www.dnssecandipv6.se/rdns/');
}

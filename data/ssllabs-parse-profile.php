<?php
/**
 * Script to scrape data off Qualy SSL Labs client SSL profile site
 * Original research by Ivan Ristić (https://github.com/ivanr)
 * References:
 * https://www.ssllabs.com/ssltest/clients.html
 */

require_once('../php/ProtokollenBase.class.php');

define('SOURCE_URL', 'https://www.ssllabs.com/ssltest/clients.html');


$c = curl_init();
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_TIMEOUT, 10);
curl_setopt($c, CURLOPT_USERAGENT, 'protokollen/1.0 (scraper)');

curl_setopt($c, CURLOPT_URL, SOURCE_URL);
if(($data = curl_exec($c)) === FALSE)
	die("ERROR: ". curl_error($c) .", URL: $url\n");
$clients = scrapeClients($data);


$arr = array();
foreach($clients as $client) {
	$url = dirname(SOURCE_URL) .'/'. basename($client->source);

	curl_setopt($c, CURLOPT_URL, $url);
	if(($data = curl_exec($c)) === FALSE)
		die("ERROR: ". curl_error($c) .", URL: $url\n");

	$profile = scrapeProfile($data);
	foreach($profile as $key => $value)
		$client->$key = $value;

	$client->source = $url;
	$arr[$client->name] = $client;
}

/* Dump to stdout */
echo json_encode($arr, JSON_PRETTY_PRINT) ."\n";

/* Import data to browser_profiles table */
$p = new ProtokollenBase();
$m = $p->getMySQLHandle();
$st = $m->prepare('INSERT INTO browser_profiles SET browser=?, json=?, created=NOW()');
foreach($arr as $name => $obj) {
	$json = json_encode($obj, JSON_PRETTY_PRINT);
	$st->reset();
	$st->bind_param('ss', $name, $json);
	$st->execute();
}
$st->close();


function scrapeClients($data) {
	$d = new DOMDocument();
	@$d->loadHTML($data);
	$x = new DOMXpath($d);

	$h = array();
	foreach($x->query('//table[@id="multiTable"]/thead/tr/th') as $node)
		$h[] = $node->nodeValue;

	$clients = array();
	foreach($x->query('//table[@id="multiTable"]/tr') as $row) {

		$nodes = $x->query('td', $row);
		for($i = 0; $i < $nodes->length; $i++) {
			$node = $nodes->item($i);
			$key = $h[$i];
			$value = $node->nodeValue;

			if($node->firstChild->nodeName === 'a') {
				$arr['name'] = $value;
				$arr['source'] = $node->firstChild->getAttribute('href');
				continue;
			}

			if(!strcmp($value, 'Yes'))
				$value = true;
			else if(!strcmp($value, 'No'))
				$value = false;
			$arr[$key] = $value;
		}

		$clients[] = (object)$arr;
	}

	return $clients;
}

function scrapeProfile($data) {
	$d = new DOMDocument();
	@$d->loadHTML($data);
	$x = new DOMXpath($d);

	$profile = new stdClass();
	foreach($x->query('//h1') as $h1) {
		foreach($h1->childNodes as $node) {
			if($node->nodeType !== XML_TEXT_NODE) continue;
			$profile->name = trim($node->nodeValue);
		}
	}

	$profile->protocols = array();
	foreach($x->query('//td[text()="Protocols*"]/../../../tbody/tr') as $row) {
		$nodes = $x->query('td', $row);
		
		$node = $nodes->item(0);
		while($node && $node->nodeType !== XML_TEXT_NODE)
			$node = $node->firstChild;
		$key = $node->nodeValue;

		$node = $nodes->item(1);
		while($node && $node->nodeType !== XML_TEXT_NODE)
			$node = $node->firstChild;
		$value = $node->nodeValue;

		$profile->protocols[$key] = !strcmp($value, 'Yes');
	}

	$profile->ciphers = array();
	foreach($x->query('//td[text()="Cipher Suites (in order of preference)"]/../../../tr') as $row) {
		$nodes = $x->query('td', $row);
		if($nodes->length != 2)
			continue;

		$node = $nodes->item(0);
		while($node && $node->nodeType !== XML_TEXT_NODE)
			$node = $node->firstChild;
		$name = trim($node->nodeValue, ' (');

		$node = $nodes->item(1);
		while($node && $node->nodeType !== XML_TEXT_NODE)
			$node = $node->firstChild;
		$bits = $node->nodeValue;

		$nodes = $x->query('.//code', $nodes->item(0));
		$code = base_convert($nodes->item(0)->nodeValue, 16, 10);

		$profile->ciphers[$name] = intval($code);
	}

	$profile->features = array();
	foreach($x->query('//td[text()="Protocol Details"]/../../../tbody/tr') as $row) {
		$nodes = $x->query('td', $row);
		
		$node = $nodes->item(0);
		while($node && $node->nodeType !== XML_TEXT_NODE)
			$node = $node->firstChild;
		$key = $node->nodeValue;

		$node = $nodes->item(1);
		while($node && $node->nodeType !== XML_TEXT_NODE)
			$node = $node->firstChild;
		$value = trim($node->nodeValue);

		if(!strcmp($value, 'Yes'))
			$value = true;
		else if(!strcmp($value, 'No'))
			$value = false;
		else if(!strcmp($value, '-'))
			$value = null;
		else {
			$arr = array();
			foreach(explode(',', $value) as $item) {
				$arr[] = trim($item);
			}
			$value = $arr;
		}
		$profile->features[$key] = $value;
	}

	return (object)$profile;
}

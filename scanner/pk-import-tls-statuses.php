#!/usr/bin/env php
<?php
/**
 * Protokollen - import JSON from sslprobe
 */

require_once('../php/Protokollen.class.php');


if($argc != 3)
	die("Usage: ${argv[0]} <service ID> <domain.tld.json>\n");

$svcId = $argv[1];
$filename = $argv[2];

$json = file_get_contents($filename);
if($json === FALSE)
	die("ERROR: File not found: $filename\n");

if(empty($json)) {
	/* Ignore empty files from sslprobe for non-existant hosts */
	die();
}

$p = new Protokollen();
$svc = $p->getServiceById($svcId);
$id = $p->addTlsStatusJson($svc->id, $json);

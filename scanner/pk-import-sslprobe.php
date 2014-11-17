#!/usr/bin/env php
<?php
/**
 * Protokollen - import JSON from sslprobe
 */

require_once('../php/TestSslprobe.class.php');


if($argc != 5)
	die("Usage: ${argv[0]} <service ID> <service group ID> <hostname> <sslprobe.json>\n");

$svcId = $argv[1];
$svcGrpId = $argv[2];
$hostname = $argv[3];
$filename = $argv[4];

$json = file_get_contents($filename);
if($json === FALSE)
	die("ERROR: File not found: $filename\n");

if(empty($json)) {
	/* Ignore empty files from sslprobe for non-existant hosts */
	die();
}

$test = new TestSslprobe();
$id = $test->importJson($svcId, $svcGrpId, $hostname, $json);

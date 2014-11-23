#!/usr/bin/env php
<?php
/**
 * Protokollen - import JSON from check_dnssec_status.py
 */

require_once('../php/TestDnssecStatus.class.php');


if($argc != 4)
	die("Usage: ${argv[0]} <service ID> <service group ID> <service-group.json>\n");

$svcId = $argv[1];
$svcGrpId = $argv[2];
$filename = $argv[3];

$json = file_get_contents($filename);
if($json === FALSE)
	die("ERROR: File not found: $filename\n");

$test = new TestDnssecStatus();
$id = $test->importJson($svcId, $svcGrpId, $json);

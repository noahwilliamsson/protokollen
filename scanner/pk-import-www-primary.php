#!/usr/bin/env php
<?php
/**
 * Protokollen - import JSON from check_http_primary.py
 */

require_once('../php/TestWwwPreferences.class.php');


if($argc != 4)
	die("Usage: ${argv[0]} <service ID> <service group ID> <domain.tld.json>\n");

$svcId = $argv[1];
$svcGrpId = $argv[2];
$filename = $argv[3];

$json = file_get_contents($filename);
if($json === FALSE)
	die("ERROR: File not found: $filename\n");

$test = new TestWwwPreferences();
$id = $test->addWwwPreferencesJson($svcId, $svcGrpId, $json);

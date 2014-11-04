#!/usr/bin/env php
<?php
/**
 * Protokollen - import JSON from check_http_primary.py
 */

require_once('../php/Protokollen.class.php');


if($argc != 2)
	die("Usage: ${argv[0]} <domain.tld.json>\n");

$filename = $argv[1];

$p = new Protokollen();
$id = $p->addHttpPreferenceJson($filename);

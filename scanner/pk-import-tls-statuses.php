#!/usr/bin/env php
<?php
/**
 * Protokollen - import JSON from sslprobe
 */

require_once('../php/Protokollen.class.php');


if($argc != 3)
	die("Usage: ${argv[0]} <service ID> <domain.tld.json>\n");

$serviceId = $argv[1];
$filename = $argv[2];

$p = new Protokollen();
$svc = $p->getServiceById($serviceId);
$id = $p->addTlsStatusJson($svc->id, $filename);

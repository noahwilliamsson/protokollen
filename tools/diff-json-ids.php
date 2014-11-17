#!/usr/bin/env php
<?php
/**
 * For debugging logging issues where JSON data changes
 */
require_once('../php/ServiceGroup.class.php');
$p = new ServiceGroup();
$m = $p->getMySQLHandle();

if($argc < 3)
	die("Usage: ${argv[0]} <json ID 1> <json ID 2>\n");

$id1 = intval($argv[1]);
$id2 = intval($argv[2]);

$q = 'SELECT json FROM json WHERE id='. $id1;
$r = $m->query($q);
$row = $r->fetch_object();
$r->close();
file_put_contents('/tmp/json.1', json_encode(json_decode($row->json), JSON_PRETTY_PRINT));

$q = 'SELECT json FROM json WHERE id='. $id2;
$r = $m->query($q);
$row = $r->fetch_object();
$r->close();
file_put_contents('/tmp/json.2', json_encode(json_decode($row->json), JSON_PRETTY_PRINT));

system("diff -ruN /tmp/json.1 /tmp/json.2");

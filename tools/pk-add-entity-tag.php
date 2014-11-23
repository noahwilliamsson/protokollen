#!/usr/bin/env php
<?php
/**
 * Protokollen - add tag to entity
 */

require_once('../php/ProtokollenBase.class.php');
mb_internal_encoding('utf-8');

if($argc < 3)
	die("Usage: ${argv[0]} <tag name> <zone> [<zone>, ..]\n");

$tag = trim($argv[1]);

$p = new ProtokollenBase();
if($p->getTag($tag) === NULL)
	die("Unknown tag: $tag\n");

for($i = 2; $i < $argc; $i++) {
	$domain = trim($argv[$i], '.');
	$e = $p->getEntityByDomain($domain);
	if(!$e) {
		echo "ERROR: Unknown base domain: $domain\n";
		continue;
	}

	$id = $p->addEntityTag($e->id, $tag);
	echo "Entity tag added (ID: $id) to entity $domain (ID: $e->id)\n";
}

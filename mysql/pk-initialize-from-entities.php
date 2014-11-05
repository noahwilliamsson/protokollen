#!/usr/bin/env php
<?php
/**
 * Protokollen - create services and add hostnames from entity definitions
 */

require_once('../php/Protokollen.class.php');


$p = new Protokollen();

$m = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$m->set_charset(DB_CHARSET) or die("$m->error\n");

$q = 'SELECT domain FROM entities WHERE domain IS NOT NULL ORDER BY id';
$r = $m->query($q) or die("$m->error, SQL: $q\n");
while($row = $r->fetch_object()) {
	/* Load entity */
	$e = $p->getEntityByDomain($row->domain);

	/* Add HTTP service */
	$s = $p->addService($e->id, Protokollen::SERVICE_TYPE_HTTP, $e->domain, 'Webbsajt '. $e->org);
	$p->addServiceHostname($s->id, $e->domain);
	$p->addServiceHostname($s->id, 'www.'. $e->domain);

	/* Add SMTP service */
	if($e->domain_email === NULL)
		continue;

	$s = $p->addService($e->id, Protokollen::SERVICE_TYPE_SMTP, $e->domain_email, 'E-postdomän '. $e->org);
	/* Not yet implemented - should this be MX pointers instead? */
	$p->addServiceHostname($s->id, $e->domain_email);
}

$r->close();


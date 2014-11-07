#!/usr/bin/env php
<?php
/**
 * Protokollen - create services and add hostnames from entity definitions
 */

require_once('../php/Protokollen.class.php');


$p = new Protokollen();

foreach($p->listEntityDomains() as $domain) {
	/* Load entity */
	$e = $p->getEntityByDomain($domain);

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

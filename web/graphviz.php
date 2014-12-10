<?php
/**
 * Generate SVG graph over services for entity domain
 *
 * Usage:
 * $ php graphviz.php sydsvenskan.se | dot -Tsvg -o graph.svg
 * $ php graphviz.php sydsvenskan.se | dot -Gdpi=72 -Gsize=12,15 -Tpng -o graph.png
 *
 * Requirements:
 * $ sudo apt-get install graphviz
 *
 * References:
 * http://www.graphviz.org/doc/info/shapes.html#html
 * http://www.graphviz.org/doc/info/colors.html#svg
 */

require_once('../php/ServiceGroup.class.php');
require_once('../php/TestWwwPreferences.class.php');
require_once('../php/TestSslprobe.class.php');
require_once('../php/TestDnsAddresses.class.php');
require_once('../php/TestDnssecStatus.class.php');


if(PHP_SAPI === 'cli') {
	if($argc != 1)
		die("Usage: ${argv[0]} <domain>\n");

	die(svgForDomain($argv[1]));
}

if(function_exists('headers_sent') && !headers_sent() && isset($_GET['d'])) {
	header('Content-Type: image/svg+xml');
	die(svgForDomain($_GET['d']));
}

/**
 * Generate SVG for entity
 */
function svgForDomain($domain) {

	$p = new ServiceGroup();
	$e = $p->getEntityByDomain($domain);


	ob_start();

	echo "digraph svggraph {\n";
	echo "  charset=utf8;\n";
	/* Go left-right instead of top-down */
	echo "  rankdir=LR;\n";

	/**
	 * Rounded record type: Mrecord (as opposed to: record)
	 * Also investigate HTML tables:
	 * http://www.graphviz.org/doc/info/shapes.html#html
	 */
	echo "  graph [ nodesep=0.1 ranksep=0.1 ]; \n";
	echo "  node [ shape=Mrecord fontsize=8 fontname=\"helvetica\" ];\n";


	echo "ent_$e->id [ label=\" $e->org\" ];\n";
	$testWww = new TestWwwPreferences();
	$testSslprobe = new TestSslprobe();
	$testDnsAddrs = new TestDnsAddresses();
	$testDnssec = new TestDnssecStatus();
	foreach($p->listServices($e->id) as $svc) {

		/* Load service group */
		$grp = $p->getServiceGroup($svc->id);
		if(!$grp)
			continue;

		switch($svc->service_type) {
		case 'DNS': $header = 'Namnservrar'; break;
		case 'SMTP': $header = 'Mejlservrar'; break;
		default: $header = 'Servergrupp'; break;
		}

		$label = array();
		if(!empty($svc->service_desc))
			$label[] = sprintf('<tr><td bgcolor="lightgoldenrodyellow" port="p0">%s<br /><br/><b>%s</b></td></tr>', $svc->service_desc, $header);
		else
			$label[] = sprintf('<tr><td bgcolor="lightgoldenrodyellow" port="p0">%s (%s)<br /><br/><b>%s</b></td></tr>', $svc->service_name, $svc->service_type, $header);


		$hostIndex = 10;
		foreach($grp->data as $svcHost) {
			$hostIndex++;
			$label[] = sprintf('<tr><td align="right" port="f%d">%s:%d</td></tr>', $hostIndex, $svcHost->hostname, $svcHost->port);
		}

		$serviceBox = '';
		// $serviceBox .= sprintf('svc_%d [ label=<<table>%s</table>>  color=gray style=filled fillcolor=lightgoldenrodyellow ] ', $svc->id, implode("\n", $label));
		$serviceBox .= sprintf('svc_%d [ label=<<table cellborder="1" cellspacing="0" border="0">%s</table>> shape=none ] ', $svc->id, implode("\n", $label));
		$serviceBox .= sprintf('ent_%d -> svc_%d:p0'."\n", $e->id, $svc->id);

		/* Load DNSSEC status for hostnames in service group */
		$dnssec = $testDnssec->getItem($svc->id, $grp->id);

		/* Load web preferences for service group */
		$prefs = $testWww->getItem($svc->id, $grp->id);
		if(!empty($prefs)) {
			$rows = array();
			$rows[] = sprintf('<tr><td colspan="2"><b>%s</b><br/>%s</td></tr>', 'Webbplats', !empty($prefs->url)? $prefs->url: strtolower(str_replace('Webmail', 'https', $svc->service_type) .'://'. $svc->service_name));

			$scheme = NULL;
			if($prefs->url !== NULL)
				$scheme = parse_url($prefs->url, PHP_URL_SCHEME);
			if(!empty($prefs->errors)) {
				$str = $prefs->errors;
				if(mb_strlen($str) > 45) $str = mb_substr($prefs->errors, 0, 45) .'…';
				$rows[] = sprintf('<tr><td bgcolor="pink">%s</td><td>%s</td></tr>',
								'Error', $str);

				$serviceBox = str_replace('lightgoldenrodyellow', 'pink', $serviceBox);
			}

			echo $serviceBox;

			/* Render HTTP prefs box */
			echo sprintf('http_prefs_%d [ label=<<table cellborder="1" border="0" >%s</table>> shape="none"] ', $svc->id, implode('', $rows));
			/* Link HTTP service to HTTP prefs box */
			echo sprintf("svc_%d -> http_prefs_%d\n", $svc->id, $svc->id);
		}
		else {
			echo $serviceBox;
		}


		/* Render service group box */
		$label = array(sprintf('<p0>%s-servers %s', $svc->service_type, $svc->service_name));
		foreach($grp->data as $svcHost) {
			$svcHostId = preg_replace('@[^A-Za-z0-9]@', '_', $svcHost->hostname);
			$label[] = sprintf('<%s> %s', $svcHostId, $svcHost->hostname);
		}


		/* Load DNS addresses for hostnames in service group */
		$addrs = $testDnsAddrs->getItem($svc->id, $grp->id);
		if(!$addrs)
			continue;

		$hostIpMap = array();
		foreach($addrs->data->records as $hostname => $obj) {
			$arr = array();
			foreach($obj->a as $ip) $arr[] = $ip;
			foreach($obj->aaaa as $ip) $arr[] = $ip;
			$hostIpMap[$hostname] = $arr;
		}


		$seenNodesAll = array();
		$tlsBoxes = array();
		$tlsIds = array();
		$hostIndex = 10;
		foreach($grp->data as $svcHost) {
			$hostIndex++;

			$label = array(sprintf('<p0>%s-server %s', $svc->service_type, $svcHost->hostname));

			/* Add DNSSEC note in vhost box */
			if($dnssec !== NULL) foreach($dnssec->data as $hostname => $obj) {
				$dnssecHostId = preg_replace('@[^A-Za-z0-9]@', '_', 'dnssec_'. $svcHost->hostname);
				if($svcHost->hostname === $hostname) {
					if($obj->secure)
						$label[] = sprintf('<%s> DNSSEC: OK (%s)', $dnssecHostId, $hostname);
					else
						$label[] = sprintf('<%s> DNSSEC: %s', $dnssecHostId, $obj->error);
				}
			}


			$seenNodesSvc = array();
			$nodeIdx = 1;
			foreach($hostIpMap[$svcHost->hostname] as $ip) {
					$label[] = sprintf('<p%d>%s', $nodeIdx, $ip);
					$nodeId = 'ip'. str_replace('.', '_', str_replace(':', '_', $ip));
					$seenNodesSvc[$nodeIdx++] = $nodeId;

					if(!isset($seenNodesAll[$ip])) {
						/* Render node (IP-address) */
						echo sprintf("%s [ shape=house label=\"%s\" ]\n", $nodeId, $ip);
						$seenNodesAll[$ip] = $nodeId;
					}
			}

			/* Render vhost box */
			$vhostId = preg_replace('@[^A-Za-z0-9]@', '_', $svcHost->hostname);
			$vhostBoxId = sprintf('svc_grp_%d_vhosts_%s', $grp->id, $vhostId);
			echo sprintf('%s [ label="%s" fillcolor=aliceblue style=filled ] ',
							$vhostBoxId,
							implode('|', $label));
			echo "\n";

			for($i = 1; $i < $nodeIdx; $i++) {
					/* Link vhost to node */
					echo sprintf("%s:p%d -> %s\n", $vhostBoxId, $i, $seenNodesSvc[$i]);
			}

			/* Link service set host to vhost box */
			echo sprintf("svc_%d:f%d -> %s\n", $svc->id, $hostIndex, $vhostBoxId);
			echo "\n";


			/* Load sslprobe for service host */
			$sslprobe = $testSslprobe->getItem($svc->id, $grp->id, $svcHost->hostname);
			if(!$sslprobe)
				continue;

			foreach($hostIpMap[$svcHost->hostname] as $ip) {
				foreach($sslprobe->data as $probe) {
					$opts = parseProbe($probe);
					$tlsId = 'tls_'. substr(md5(json_encode($opts)), 0, 8);

					$vhostId = preg_replace('@[^A-Za-z0-9]@', '_', $svcHost->hostname);
					$vhostBoxId = sprintf('svc_grp_%d_vhosts_%s', $grp->id, $vhostId);

					if(!isset($seenNodesAll[$probe->ip])) {
						/* Happens because of inconsistencies in database */
						continue;
					}

					$nodeId = $seenNodesAll[$probe->ip];
					foreach($seenNodesSvc as $key => $value) {
						if($value !== $nodeId)
							continue;
						/* Link vhost port to TLS box (even though TLS box is not yet defined) */
						echo " $vhostBoxId:p${key} -> $tlsId \n";
						break;
					}

					if(isset($tlsBoxes[$tlsId]))
						continue;

					$trows = array();
					foreach($opts as $obj) {
						if(!is_object($obj))
							continue;

						$trows[] = '<tr><td bgcolor="'. $obj->color .'">'. $obj->title .'</td><td>'. $obj->body .'</td></tr>';
					}

					$box = $tlsId .' [ label=<<table cellborder="1" border="0" >'. implode('', $trows) .'</table>> shape="none"]' ."\n";
					$tlsBoxes[$tlsId] = $box;
				}
			}
		}

		/* Render TLS box */
		foreach($tlsBoxes as $tlsId => $box)
			echo $box;
	}

	echo "}\n";

	$dot = ob_get_contents();
	ob_end_clean();


	/* Dump .dot file to temporary file */
	$dotFilename = tempnam(sys_get_temp_dir(), 'graphviz');
	file_put_contents($dotFilename, $dot);

	/* Generate SVG */
	ob_start();
	$args = array('dot', '-Gcharset=utf8', '-Tsvg');
	$args[] = escapeshellarg($dotFilename);
	$command = implode(' ', $args);
	passthru($command);
	$svg = ob_get_contents();
	ob_end_clean();

	unlink($dotFilename);

	return $svg;
}

/**
 * Helper routine for parsing an sslprobe(1) probe.
 * Used by the graphviz code.
 */
function parseProbe($probe) {
	$opts = array();
	$certData = array();
	$numSupportedProtocols = 0;
	foreach($probe->protocols as $proto) {
		$obj = new stdClass();
		$obj->color = '';
		$obj->title = $proto->name;
		$obj->body = '';

		if(!$proto->supported) {
				if(in_array($proto->name, array('SSL 2.0', 'SSL 3.0')))
						continue;
		}
		else {
			$numSupportedProtocols++;
		}

		switch($proto->name) {
		case 'SSL 2.0':
			$obj->color = 'green';
			$obj->body = 'NOT SUPPORTED';
			if($proto->supported) {
				$obj->color = 'red';
				$obj->body = 'DANGEROUS';
			}
			break;
		case 'SSL 3.0':
			$obj->color = 'green';
			$obj->body = 'NOT SUPPORTED';
			if($proto->supported) {
				$obj->color = 'red';
				$obj->body = 'OBSOLETE';
			}
			break;
		case 'TLS 1.0':
		case 'TLS 1.1':
			$obj->color = 'yellow';
			$obj->body = 'SUPPORTED';
			if(!$proto->supported) {
				$obj->color = 'red';
				$obj->body = 'MISSING';
			}
			break;
		case 'TLS 1.2':
			$obj->color = 'green';
			$obj->body = 'PERFECT';
			if(!$proto->supported) {
				$obj->color = 'red';
				$obj->body = 'MISSING';
			}
			break;
		}

		//if($proto->name !== 'TLS 1.0') continue;
		$certIdx = 0;
		$leafCertIdx = 0;
		foreach($proto->certificates as $pem) {
			$certIdx++;
			$x509 = openssl_x509_parse($pem, true);
			if(!isset($x509['extensions'])) {
				error_log(__FUNCTION__  .": $probe->host ($probe->ip) cert index $certIdx: No extensions in cert");
				continue;
			}

			$ext = $x509['extensions'];
			if(!isset($ext['basicConstraints'])) {
				error_log(__FUNCTION__ .": $probe->host ($probe->ip): cert index $certIdx: No constraints in cert");
				continue;
			}

			$c = new stdClass();
			$c->altName = NULL;
			$c->ca = NULL;
			$c->cn = NULL;
			$c->sigAlg = NULL;
			if($ext['basicConstraints'] === 'CA:FALSE') {
				$leafCertIdx = $certIdx;
				$c->ca = FALSE;
			}
			else if($ext['basicConstraints'] === 'CA:TRUE') {
				$c->ca = TRUE;
			}
			if(isset($x509['subject']) && isset($x509['subject']['CN']))
				$c->cn = $x509['subject']['CN'];
			if(isset($ext['subjectAltName']))
				$c->altName = $ext['subjectAltName'];

			$c->validFrom = strftime('%F %T', $x509['validFrom_time_t']);
			$c->validTo = strftime('%F %T', $x509['validTo_time_t']);
			openssl_x509_export($pem, $text, false);
			if(preg_match('@Signature Algorithm:\s+(.*)@', $text, $matches))
				$c->sigAlg = $matches[1];
			$certData[] = json_encode($c);
		}

		$obj->invalidChain = ($leafCertIdx === 1)? FALSE: TRUE;
		$opts[$proto->name] = $obj;
	}

	if($numSupportedProtocols === 0) {
		$obj = new stdClass();
		$obj->color = 'red';
		$obj->title = 'SSL/TLS';
		$obj->body = 'NOT SUPPORTED';
		$opts = array($obj);
	}

	$certData = array_unique($certData);
	$opts['certs'] = $certData;
	return $opts;
}

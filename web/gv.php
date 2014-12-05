<?php
/**
 * SVG generator
 *
 * Usage:
 * $ php gv.php sydsvenskan.se|tee /dev/stderr| dot -Gdpi=72 -Gsize=12,15 -Tsvg -o temp.txt.svg
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
require_once(dirname(__FILE__) .'/tlsbox.php');


if(function_exists('headers_sent') && !headers_sent()) {
	if(isset($_GET['d'])) {
		header('Content-Type: image/svg+xml');
		$svg = svgForDomain($_GET['d']);
		die($svg);
	}
}

/**
 * Generate SVG for entity
 */
function svgForDomain($domain) {

	$p = new ServiceGroup();
	$e = $p->getEntityByDomain($domain);

	ob_start();

	echo "digraph g {\n";
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
		$label = array('<f0>'. $svc->service_type, '<f1>'. $svc->service_name);
		if(!empty($svc->service_desc))
			$label[] = $svc->service_desc;

		$serviceBox = '';
		$serviceBox .= sprintf('svc_%d [ label="%s" color=gray style=filled fillcolor=lightgoldenrodyellow ] ', $svc->id, implode('|', $label));
		$serviceBox .= sprintf("ent_%d -> svc_%d:f1\n", $e->id, $svc->id);

		/* Load service group */
		$grp = $p->getServiceGroup($svc->id);
		if(!$grp)
			continue;

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
		foreach($grp->data as $svcHost) {

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
						$seenNodes[$ip] = $nodeId;
					}
			}

			/* Render vhost box */
			$vhostId = preg_replace('@[^A-Za-z0-9]@', '_', $svcHost->hostname);
			$vhostBoxId = sprintf('svc_set_%d_vhosts_%s', $grp->id, $vhostId);
			echo sprintf('%s [ label="%s" fillcolor=aliceblue style=filled ] ',
							$vhostBoxId,
							implode('|', $label));
			echo "\n";

			for($i = 1; $i < $nodeIdx; $i++) {
					/* Link vhost to node */
					echo sprintf("%s -> %s\n", $vhostBoxId, $seenNodesSvc[$i]);
			}

			/* Link service set host to vhost box */
			echo sprintf("svc_%d:f1 -> %s\n", $svc->id, $vhostBoxId);
			echo "\n";
		}


		$tlsBoxes = array();
		$tlsIds = array();
		foreach($grp->data as $svcHost) {

			/* Load sslprobe for service host */
			$sslprobe = $testSslprobe->getItem($svc->id, $grp->id, $svcHost->hostname);
			if(!$sslprobe)
				continue;

			foreach($hostIpMap[$svcHost->hostname] as $ip) {
				foreach($sslprobe->data as $probe) {
					$opts = parseProbe($probe);
					$tlsId = 'tls_'. substr(md5(json_encode($opts)), 0, 8);

					$vhostId = preg_replace('@[^A-Za-z0-9]@', '_', $svcHost->hostname);
					$vhostBoxId = sprintf('svc_set_%d_vhosts_%s', $grp->id, $vhostId);
					/* Link vhost to TLS box (even though TLS box is not yet defined) */
					echo " $vhostBoxId -> $tlsId \n";

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
	$filename = tempnam(sys_get_temp_dir(), 'graphviz');
	file_put_contents($filename, $dot);

	/* Generate SVG */
	$args = array('dot', '-Gcharset=utf8', '-Gdpi=72', '-Gsize=30,39', '-Tsvg');
	$args[] = escapeshellarg($filename);
	$command = implode(' ', $args);

	ob_start();
	passthru($command);
	$svg = ob_get_contents();
	ob_end_clean();

	unlink($filename);

	return $svg;
}

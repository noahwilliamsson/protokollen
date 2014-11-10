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

require_once('../php/Protokollen.class.php');


if(isset($argc)) {
	$domain = 'aftonbladet.se';
	if($argc > 1)
		$domain = $argv[1];

	$svg = svgForDomain($domain);
	die($svg);
}


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

	$p = new Protokollen();
	$e = $p->getEntityByDomain($domain);

	ob_start();

echo "digraph g {\n";
echo "charset=utf8;\n";
/* Go left-right instead of top-down */
echo "  rankdir=LR;\n";

/**
 * Rounded record type: Mrecord (as opposed to: record)
 * Also investigate HTML tables:
 * http://www.graphviz.org/doc/info/shapes.html#html
 */
echo " graph [ nodesep=0.1 ranksep=0.1 ]; \n";
echo "  node [ shape=Mrecord fontsize=8 fontname=\"helvetica\" ];\n";


echo "ent_$e->id [ label=\"$e->org\" ];\n";
foreach($p->listServices($e->id /*, Protokollen::SERVICE_TYPE_HTTP*/) as $svc) {
		$label = array('<f0>'. $svc->service_type, '<f1>'. $svc->service_name);
		if(!empty($svc->service_desc))
			$label[] = $svc->service_desc;

		$serviceBox = '';
		$serviceBox .= sprintf('svc_%d [ label="%s" color=gray style=filled fillcolor=lightgoldenrodyellow ] ', $svc->id, implode('|', $label));
		$serviceBox .= sprintf("ent_%d -> svc_%d:f1\n", $e->id, $svc->id);



		$prefs = $p->getHttpPreferences($svc->id);
		if(!empty($prefs)) {
			$prefs = $prefs[0];

			$rows = array();
			$rows[] = sprintf('<tr><td colspan="2"><b>%s</b>%s</td></tr>', 'Webbplats', !empty($prefs->preferred_url)? '<br/>'. $prefs->preferred_url: '');
			if(!empty($prefs->http_preferred_url))
				$rows[] = sprintf('<tr><td>%s</td><td href="%s">%s</td></tr>',
								'HTTP', $prefs->http_preferred_url,
								$prefs->http_preferred_url);
			if(!empty($prefs->https_preferred_url))
				$rows[] = sprintf('<tr><td bgcolor="lightgreen">%s</td><td href="%s">%s</td></tr>',
								'HTTPS', $prefs->https_preferred_url,
								$prefs->https_preferred_url);
			if( !empty($prefs->https_error)) {
				$str = $prefs->https_error;
				if(mb_strlen($str) > 45) $str = mb_substr($prefs->https_error, 0, 45) .'…';
				$rows[] = sprintf('<tr><td bgcolor="pink">%s</td><td>%s</td></tr>',
								'HTTPS err', $str);

				$serviceBox = str_replace('lightgoldenrodyellow', 'pink', $serviceBox);
			}

			echo $serviceBox;

			/* Render HTTP prefs box */
			echo sprintf('http_prefs_%d [ label=<<table cellborder="1" border="0" >%s</table>> shape="none"] ', $svc->id, implode('', $rows));
			/* Link HTTP service to HTTP prefs box */
			echo sprintf("svc_%d -> http_prefs_%d\n", $svc->id, $svc->id);
		}
		else
			echo $serviceBox;


		/* Render service set box */
		$label = array(sprintf('<p0>%s-servers %s', $svc->service_type, $svc->service_name));
		if(($ss = $p->getServiceSet($svc->id)) === NULL)
				continue;

		$data = $p->getJsonByHash($svc->id, $ss->json_sha256);
		foreach(json_decode($data->json) as $svcHost) {
			$label[] = sprintf('<%s> %s', preg_replace('@[^A-Za-z0-9]@', 'A', $svcHost->hostname), $svcHost->hostname);
		}

if(0) {
		echo sprintf('svc_set_%d [ label="%s" color=gray ] ', $ss->id, implode('|', $label));

		/* Link service to service set */
		echo sprintf("svc_%d:f0 -> svc_set_%d:p0\n", $svc->id, $ss->id);
}

		$seenNodesAll = array();
		foreach(json_decode($data->json) as $svcHost) {
			$label = array(sprintf('<p0>%s-server %s', $svc->service_type, $svcHost->hostname));
			$vhosts = $p->listServiceVhosts($ss->id, $svcHost->hostname);

			$seenNodesSvc = array();
			$nodeIdx = 1;
			if(empty($vhosts)) {
					foreach(dns_get_record($svcHost->hostname, DNS_ANY) as $rr) {
						$ip = NULL;
						if(isset($rr['ip'])) $ip = $rr['ip'];
						else if(isset($rr['ipv6'])) $ip = $rr['ipv6'];
						if(empty($ip)) continue;
						$obj = new stdClass();
						$obj->node_id = $p->addNode($ip);
						$obj->ip = $ip;
						$vhosts[] = $obj;
						break;
					}

			}
			foreach($vhosts as $vhost) {
					$node = $p->getNodeById($vhost->node_id);

					$label[] = sprintf('<p%d>%s', $nodeIdx, $node->ip);
					$nodeId = 'ip'. str_replace('.', '_', str_replace(':', '_', $node->ip));
					$seenNodesSvc[$nodeIdx++] = $nodeId;
					if(!isset($seenNodesAll[$node->ip])) {
						echo sprintf("%s [ shape=house label=\"%s\" ]\n", $nodeId, $node->ip);
						$seenNodes[$node->ip] = $nodeId;
					}

			}

			// if(empty($vhosts)) continue;


			/* Render vhost box */
			$vhostId = preg_replace('@[^A-Za-z0-9]@', '_', $svcHost->hostname);
			$vhostBoxId = sprintf('svc_set_%d_vhosts_%s', $ss->id, $vhostId);
			echo sprintf('%s [ label="%s" fillcolor=aliceblue style=filled ] ',
						$vhostBoxId,
						implode('|', $label));

			echo "\n";
			for($i = 1; $i < $nodeIdx; $i++) {
					echo sprintf("\n%s -> %s\n", $vhostBoxId, $seenNodesSvc[$i]);
			}

			/* Link service set host to vhost box */
			/*
			echo sprintf("svc_set_%d:%s -> svc_set_%d_vhosts_%s\n",
						$ss->id, preg_replace('@[^A-Za-z0-9]@', 'A', $svcHost->hostname),
						$ss->id, preg_replace('@[^A-Za-z0-9]@', 'A', $svcHost->hostname));
			*/
			echo sprintf("svc_%d:f1 -> %s\n",
						$svc->id, $vhostBoxId);
			echo "\n";
		}
}

echo "}\n";


	$dot = ob_get_contents();
	ob_end_clean();

	/* Dump .dot file to temporary file */
	$filename = tempnam(sys_get_temp_dir(), 'graphviz');
	file_put_contents($filename, $dot);

	/* Generate SVG */
	$args = array('dot', '-Gcharset=utf8', '-Gdpi=72', '-Gsize=12,15', '-Tsvg');
	$args[] = escapeshellarg($filename);
	$command = implode(' ', $args);

	ob_start();
	passthru($command);
	$svg = ob_get_contents();
	ob_end_clean();

	unlink($filename);

	return $svg;
}

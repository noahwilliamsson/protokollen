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
echo "  node [ shape=Mrecord fontsize=10 fontname=\"helvetica\" ];\n";


echo "ent_$e->id [ label=\"$e->org\" ];\n";
foreach($p->listServices($e->id /*, Protokollen::SERVICE_TYPE_HTTP*/) as $svc) {
		$label = array('<f0>'. $svc->service_type, '<f1>'. $svc->service_name);
		if(!empty($svc->service_desc))
			$label[] = $svc->service_desc;
		echo sprintf('http_%d [ label="%s" ] ', $svc->id, implode('|', $label));
		echo "ent_$e->id -> http_$svc->id:f1\n";

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
				$rows[] = sprintf('<tr><td bgcolor="pink">%s</td><td>%s</td></tr>',
								'HTTPS err', $prefs->https_error);
			}



			/* Render HTTP prefs box */
			echo sprintf('http_prefs_%d [ label=<<table cellborder="1" border="0" >%s</table>> shape="none"] ', $svc->id, implode('', $rows));
			/* Link HTTP service to HTTP prefs box */
			echo sprintf("http_%d -> http_prefs_%d\n", $svc->id, $svc->id);
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

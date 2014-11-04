<?php


for($i = 1; $i < $argc; $i++) {
	$d = $argv[$i];
	$d = trim($d, '.');

	$labels = explode('.', $d);
	$n = count($labels);

	$domains = array();
	for($j = 0; $j <= $n - 2; $j++) {
		$domain = implode('.', array_slice($labels, $j));
		$arr = dig('SOA', $domain);
		if(empty($arr)) continue;
                if(count($arr) != 1) continue;
		$arr = explode(' ', $arr[0]);
		if(count($arr) != 7) continue;

		$domains[] = $domain;
	}

	foreach($domains as $domain) echo "$domain\n";
}

	function dig($type, $hostname, $ns = NULL) {

		$args = array();
		$args[] = 'dig';
		$args[] = '+short';
		$args[] = '+time=2';
		$args[] = '-t';
		$args[] = escapeshellarg($type);
		$args[] = escapeshellarg($hostname);
		if($ns !== NULL)
			$args[] = escapeshellarg('@'. $ns);

		//echo "EXEC: ". implode(' ', $args) ."\n";
		exec(implode(' ', $args), $output, $exitcode);
		if($exitcode !== 0)
			return NULL;

		$arr = array();
		foreach($output as $line) if(substr($line, 0, 2) != ';;') $arr[] = $line;
		$output = $arr;

		if(strcasecmp($type, 'a') == 0 || strcasecmp($type, 'aaaa') == 0) {
			/* dig +short -t a may return a CNAME */
			$arr = array();
			foreach($output as $line)
				if(@inet_pton($line) !== FALSE)
					$arr[] = $line;
			$output = $arr;
		}

		return $output;
	}


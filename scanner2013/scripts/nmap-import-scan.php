<?php

$filename = 'php://stdin';
if($argc > 1)
	$filename = $argv[1];
$fd = fopen($filename, 'r');
if($fd === FALSE) die("ERROR: Failed to open $filename\n");

$m = new mysqli('localhost','debian-sys-maint','GNfLPgFFIQqfXXUb','pc');
$m->set_charset('utf8');


while($line = fgets($fd)) {
// Host: 192.71.240.23 (www.aftonbladet-cdn.se)    Ports: 25/filtered/tcp//smtp///, 80/open/tcp//http///, 443/open/tcp//https///
	$line = trim($line);
	if(!preg_match('@Host: ([0-9a-fA-F:.]+)@', $line, $matches)) continue;
	$ip = $matches[1];
	if(!preg_match('@Ports: (.*)@', $line, $matches)) continue;
	$ports = explode(', ', $matches[1]);

	$fields = array();
	$fields[] = 'ip="'. $m->escape_string($ip) .'"';
	foreach(array('25', '80', '443') as $port) {
		$port_field = 'port_'. $port;
		$port_status = 0;
		foreach($ports as $status) {
			$arr = explode('/', $status);
			if($arr[0] !== $port) continue;
			if($arr[1] === 'open') $port_status = 1;
			break;
		}

		$fields[] = $port_field .'='. $port_status;
	}

	$q = 'INSERT INTO tcp_scans SET created=NOW(),'. implode(',', $fields) .' ON DUPLICATE KEY UPDATE '. implode(',', $fields);
	$m->query($q) or die("$m->error, SQL: $q\n");
}

fclose($fd);
$m->close();

<?php
$filename = 'php://stdin';
if($argc > 1) $filename = $argv[1];
$data = file_get_contents($filename);
$obj = json_decode($data);
foreach($obj->log->entries as $e) {
	$r = $e->request;
	echo "$r->method $r->url\n";
}
?>

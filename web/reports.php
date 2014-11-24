<?php
/**
 * Dump data in reports table as CSV
 */

require_once('../php/ProtokollenBase.class.php');

$date = strftime('%F');
if(isset($_GET['d']))
	$date = strftime('%F', strtotime($_GET['d']));

$p = new ProtokollenBase();
$m = $p->getMySQLHandle();
$q = 'SELECT e.org, e.org_short, e.org_group, e.domain, e.domain_email, e.url, r.*
FROM entities e LEFT JOIN reports r ON e.id=r.entity_id
WHERE e.id > 1 AND r.created="'. $m->escape_string($date) .'"';

$filename = 'protokollen-'. strftime('%Y%m%d_%H%m') .'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename='. $filename);

$r = $m->query($q);
$didHeader = FALSE;
$fd = fopen('php://output', 'w');
while($row = $r->fetch_object()) {
	if(!$didHeader) {
		$arr = array();
		foreach($row as $k => $v)
			$arr[] = $k;
		fputcsv($fd, $arr);
	}
	$arr = array();
	foreach($row as $k => $v)
		$arr[] = $v;
	fputcsv($fd, $arr);
}
$r->close();
fclose($fd);

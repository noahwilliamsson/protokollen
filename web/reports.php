<?php
/**
 * Dump data in reports table as CSV
 * Script args:
 * - charset (UTF-8 or ISO-8859-1)
 * - date (YYYY-mm-dd)
 * - delim (CSV delimiter: ",", ";" or "\t")
 */

require_once('../php/ProtokollenBase.class.php');

$date = strftime('%F');
if(isset($_GET['date']))
	$date = strftime('%F', strtotime($_GET['date']));

$charset = 'UTF-8';
$dbCharset = 'utf8';
if(isset($_GET['charset'])) switch(strtoupper($_GET['charset'])) {
case 'ISO-8859-1':
case 'ISO-8859-15':
case 'Windows-1252':
case 'CP1252':
	$charset = strtoupper($_GET['charset']);
	$dbCharset = 'latin1';
	break;
case 'UTF-8':
	$charset = strtoupper($_GET['charset']);
	$dbCharset = 'utf8';
	break;
default:
	break;
}

$delimiter = ',';
if(isset($_GET['delim'])) switch($_GET['delim']) {
case ',':
case ';':
case "\t":
	$delimiter = $_GET['delim'];
	break;
default:
	break;
}

$p = new ProtokollenBase();
$m = $p->getMySQLHandle();
$m->set_charset($dbCharset);
$q = 'SELECT e.org, e.org_short, e.org_group, e.domain, e.domain_email, e.url, r.*
FROM entities e LEFT JOIN reports r ON e.id=r.entity_id
WHERE e.id > 1 AND r.created="'. $m->escape_string($date) .'"';

$filename = 'protokollen-'. strftime('%Y%m%d_%H%m') .'.csv';
header('Content-Type: text/csv; charset='. $charset);
header('Content-Disposition: attachment; filename='. $filename);

$r = $m->query($q);
$didHeader = FALSE;
$fd = fopen('php://output', 'w');
while($row = $r->fetch_object()) {
	if(!$didHeader) {
		$arr = array();
		foreach($row as $k => $v)
			$arr[] = $k;
		fputcsv($fd, $arr, $delimiter);
		$didHeader = TRUE;
	}
	$arr = array();
	foreach($row as $k => $v)
		$arr[] = $v;
	fputcsv($fd, $arr, $delimiter);
}
$r->close();
fclose($fd);

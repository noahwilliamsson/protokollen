<?php
/**
 * Output entity data as JSON
 */

require_once('json.inc.php');

if(!isset($_GET['id']))
	die("Parameter id does not contain an entity ID\n");

$id = intval($_GET['id']);
$withRevisions = FALSE;
if(isset($_GET['revisions']) && !empty($_GET['revisions']))
	$withRevisions = TRUE;

$filename = 'protokollen-entity-'. $id .'-'. strftime('%Y%m%d_%H%m') .'.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename='. $filename);

echo json_encode(entityToObject($id, $withRevisions), JSON_PRETTY_PRINT);

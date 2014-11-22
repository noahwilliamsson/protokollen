<?php
/**
 * VERP receiver
 *
 * Called via ~/.procmailrc:
 * :0 fw
 * * ^To.*
 * | /usr/bin/php /home/bouncetest/bouncetest-recv.php
 *
 */

define('DB_FILENAME', '/home/bouncetest/bouncetest.db');

date_default_timezone_set('Europe/Stockholm');

$data = file_get_contents('php://stdin');
$filename = dirname(__FILE__) .'/'. strftime('%Y%m%d_%H%M%S.txt');
file_put_contents($filename, 'Date: '. strftime('%F %T') ."\n");
file_put_contents($filename, $data, FILE_APPEND);
chmod($filename, 0644);

$verp = NULL;
if(preg_match('@X-Original-To:\s+(.*)@', $data, $matches)
	|| preg_match('@To:\s+(.*)@', $data, $matches)) {
	$verp = $matches[1];
}

$db = new SQLite3(DB_FILENAME);
$db->busyTimeout(30*1000);

$st = $db->prepare('SELECT id FROM verp WHERE return_path=? ORDER BY date_sent DESC');
$st->bindParam(1, $verp, SQLITE3_TEXT);
$r = $st->execute();
$row = $r->fetchArray();
$st->close();
if($row !== FALSE) {
	$st = $db->prepare('UPDATE verp SET date_received=?, bounce=? WHERE id=?');
	$date = strftime('%F %T');
	$st->bindParam(1, $date, SQLITE3_TEXT);
	$st->bindParam(2, $data,  SQLITE3_TEXT);
	$st->bindParam(3, $row['id'], SQLITE3_INTEGER);
	$st->execute();
	$st->close();
}
$db->close();

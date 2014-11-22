#!/usr/bin/env php
<?php
/**
 * This script sends an email to a (hopefully) nonexistant mailbox
 * at a target domain in an attempt to determine support for
 * STARTTLS for outgoing emails.
 *
 * The idea here is that a lot of sites use a filtering SMTP relay
 * in front of their real mailserver.
 *
 * An incoming message will be relayed by the public MX to the
 * internal MX.   Upon receiving the incoming mail, the internal MX
 * determines that the mailbox does not exist at the target site
 * and generates a bounce message.  The bounce message will contain
 * Received: headers exposing internal mail paths and, hopefully,
 * other information that could help tell if the message was delivered
 * over TLS all the way or not.
 *
 * Obviously this won't work in several common scenarios such as when:
 * - the public MX immediately can reject mail for unknown mailboxes
 * - the receiver have a "catch-all" mailbox
 *
 */

require_once('../php/ProtokollenBase.class.php');

define('DB_FILENAME', '/home/bouncetest/bouncetest.db');
define('SENDER_DOMAIN', 'bouncetest.example.com');
define('SENDER_USER', 'bouncetest');
define('SENDER_NAME', 'Protokollen Studstest');

mb_internal_encoding('UTF-8');
if($argc < 2)
	die("Usage: ${argv[0]} <domain> [[username] [name]]\n");

$domain = $argv[1];
$rcpt_user = 'nonexistant';
$rcpt_name = 'Mr Non Existant';
if($argc > 2)
	$rcpt_user = $argv[2];
if($argc > 3)
	$rcpt_name = $argv[3];
$recipient = $rcpt_user .'@'. $domain;

$p = new ProtokollenBase();
$e = $p->getEntityByDomain($domain);
if($e === NULL)
	die("ERROR: Unknown domain: $domain\n");
else if($e->domain_email === NULL)
	die("ERROR: Domain $domain has no email domain defined\n");
$domain = $e->domain_email;

$from = sprintf('%s <%s@%s>', SENDER_NAME, SENDER_USER, SENDER_DOMAIN);
$to = sprintf('%s <%s@%s>', $rcpt_name, $rcpt_user, $domain);
$sub = sprintf('id=%d=%s=%s', $e->id, $rcpt_user, $domain);
$verp = sprintf('%s+%s@%s', SENDER_USER, $sub, SENDER_DOMAIN);

$date = strftime('%a, %d %b %Y %H:%M:%S %z');
$messageId = sprintf('<%s.%s@%s>', strftime('%Y%m%d.%H%M%S'), $sub, SENDER_DOMAIN);

$headers = array();
$headers[] = 'From: '. $from;
$headers[] = 'Date: '. $date;
$headers[] = 'Reply-To: <'. $verp .'>';
$headers[] = 'Message-Id: '. $messageId;
$headers[] = 'X-Mailer: Protokollen/1.0';
$headers[] = 'Content-Language: sv-SE';
$headers[] = 'Content-Type: text/plain; charset=utf-8';
$headers[] = 'MIME-Version: 1.0';

$subject = 'En fråga.. vem läser den här mejlen?';
$body = 'Hej,

Jag har skickat dig ett testmejl för att försöka avgöra om din
mejlserver använder sig av STARTTLS när den skickar mejl till
andra personer på internet.

Det var inte egentligen inte meningen att någon skulle se det
här mejlet eftersom det skickades till en icke-existerande 
mejladress.

Tanken var att mejlet skulle studsa tillbaka till avsändaren
med automatik men det har uppenbarligen inte fungerat.

Vill du hjälpa till att slutföra testet ändå?

Klick då på "Svara" i din mejlklient och skicka iväg mejlet.
Du behöver inte skriva något i meddelandefältet eftersom det
ändå är en dator som med automatik kommer processa svaret.

Tack på förhand.';

$db = new SQLite3(DB_FILENAME);
$db->busyTimeout(30*1000);
$db->query('CREATE TABLE IF NOT EXISTS verp
			(id integer primary key asc autoincrement,
			entity_id unsigned integer,
			domain text,
			mail_from text,
			mail_to text,
			return_path text,
			message_id text,
			date_sent text,
			date_received text,
			bounce text)');
$st = $db->prepare('INSERT INTO verp
					(entity_id, domain, mail_from, mail_to,
					return_path, message_id, date_sent)
					VALUES(?,?,?,?,?,?,?)');
$st->bindParam(1, $e->id, SQLITE3_INTEGER);
$st->bindParam(2, $domain, SQLITE3_TEXT);
$st->bindParam(3, $from, SQLITE3_TEXT);
$st->bindParam(4, $to, SQLITE3_TEXT);
$st->bindParam(5, $verp, SQLITE3_TEXT);
$st->bindParam(6, $messageId, SQLITE3_TEXT);
$st->bindParam(7, $date, SQLITE3_TEXT);
$st->execute();
$st->close();
$db->close();

mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'Q'), $body, implode("\r\n", $headers), '-f'. $verp);

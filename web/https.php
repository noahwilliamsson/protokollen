<?php
require_once('../php/ProtokollenBase.class.php');
require_once('../php/ServiceGroup.class.php');
require_once('../php/TestWwwPreferences.class.php');
require_once('../php/TestSslprobe.class.php');
$p = new ServiceGroup();
$m = $p->getMySQLHandle();
$wwwPrefsTest = new TestWwwPreferences();
$sslprobeTest = new TestSslprobe();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Protokollen - HTTPS-stöd på huvudwebbsajten</title>

	<!-- Bootstrap -->
	<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css" rel="stylesheet" />

	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
		<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
	<![endif]-->
	<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
	<!-- Include all compiled plugins (below), or include individual files as needed -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
</head>
<body>
	<?php include('nav.php') ?>

	<div class="container">

	<h2>HTTPS-stöd på huvudwebbsajten (<?php echo strftime('%F') ?>)</h2>

	<p><a href="https://en.wikipedia.org/wiki/HTTP_Secure">HTTPS</a> skyddar datatrafiken mellan webbläsare och webbserver.</p>
	<p>För att få <em>HTTPS OK</em> krävs att alla certifikatet är giltigt, att HTTPS är påtvingat på sajten, att TLSv1.0 stöds och att det äldre SSLv2 protokollet inte är aktiverat. Siffrorna nedan avser hur många IP-adresser det handlar om. Enbart huvuddomänen (t.ex. <em>www.example.com</em>) har testats här.</p>
	<p>Klicka på <span class="glyphicon glyphicon-plus"></span> för att fälla ut kategorin (det tar lite tid så håll ut..). Tabellen kan laddas ner i <a href="/reports.php?date=<?php echo strftime('%F', strtotime('3 hours ago')) ?>&amp;charset=UTF-8">CSV-format</a> (UTF-8 eller <a href="/reports.php?date=<?php echo strftime('%F', strtotime('3 hours ago')) ?>&amp;charset=ISO-8859-1">ISO-8859-1</a> för Excel på Mac).</p>

	<table class="table table-condensed table-striped" id="https">
		<thead>
			<tr>
				<th>Organisation</th>
				<th>HTTPS OK</th>
				<th title="Om HTTPS enbart används (påtvingat) på huvuddsajten, om båda protokollen stöds (tillgängligt) eller om inget protokoll stöds (saknas)">Stöd</th>
				<th>Land (SE)</th>
				<th>Land (övr)</th>
				<th title="IP-adressen kunde inte landsbestämmas (vanligt för IPv6-adresser)">Land (?)</th>
				<th title="Perfect forward secrecy - sessionsunika nycklar">PFS</th>
				<th title="SSLv2 är obsolete sedan 15+ år tillbaka">SSLv2</th>
				<th>SSLv3</th>
				<th>TLSv1</th>
				<th>TLSv1.1</th>
				<th>TLSv1.2</th>
			 </tr>
		</thead>
		<tbody>
<?php
$tags = array();
$q = 'SELECT t.id, t.tag, COUNT(*) n FROM entity_tags et
LEFT JOIN tags t ON et.tag_id=t.id
GROUP BY et.tag_id
ORDER BY n DESC, tag';
$r = $m->query($q);
while($row = $r->fetch_object())
	$tags[$row->tag] = $row->id;
$r->close();

foreach($tags as $tag => $tagId):
	$entityIds = array();
	$q = 'SELECT et.entity_id
			FROM entity_tags et
			LEFT JOIN entities e ON et.entity_id=e.id
			LEFT JOIN reports r ON r.entity_id=e.id AND r.created=CURDATE()
			WHERE tag_id="'. $m->escape_string($tagId) .'"
			ORDER BY IF(https="yes" AND https_ip_tlsv1=https_ip_total AND https_ip_total>0 AND https_ip_sslv2=0, 1, 0) DESC, IF(https="partial", 1, 0) DESC, org';
	$r = $m->query($q) or die($m->error);
	while($row = $r->fetch_object())
		$entityIds[] = $row->entity_id;
	$r->close();

	$q = '	SELECT
				SUM(IF(https="yes" AND https_ip_tlsv1=https_ip_total AND https_ip_total>0 AND https_ip_sslv2=0, 1, 0)) AS https_ok,
				"" AS dummy,
				SUM(IF(https_ip_country_se>0,1,0)) https_ip_country_se,
				SUM(IF(https_ip_country_other>0,1,0)) https_ip_country_other,
				SUM(IF(https_ip_country_unknown>0,1,0)) https_ip_country_unknown,
				SUM(IF(https_ip_tls_forward_secrecy>0,1,0)) https_ip_tls_forward_secrecy,
				SUM(IF(https_ip_sslv2>0,1,0)) https_ip_sslv2,
				SUM(IF(https_ip_sslv3>0,1,0)) https_ip_sslv3,
				SUM(IF(https_ip_tlsv1>0,1,0)) https_ip_tlsv1,
				SUM(IF(https_ip_tlsv1_1>0,1,0)) https_ip_tlsv1_1,
				SUM(IF(https_ip_tlsv1_2>0,1,0)) https_ip_tlsv1_2
			FROM reports
			WHERE entity_id IN('. implode(',', $entityIds) .')
			AND created=CURDATE()
			';
	$r = $m->query($q)
	or die("$m->error, SQL: $q");
	$header = $r->fetch_object();
	$r->close();
?>
	<tr id="starttls-tag<?php echo $tagId ?>">
		<td>
			<span class="glyphicon glyphicon-plus"></span><a href="#starttls-tag<?php echo $tagId ?>" onclick="return false" data-toggle="collapse" data-target="tr.tag<?php echo $tagId ?>">
			<?php echo htmlspecialchars($tag, ENT_NOQUOTES) ?></a>
			(<?php echo count($entityIds) ?>st)
		</td>
		<?php foreach($header as $key => $value): ?>
		<?php if($key === 'https_ok'): ?>
		<td><?php echo htmlspecialchars(sprintf('%.1f%% (%dst)', 100.0*$value/count($entityIds), $value), ENT_NOQUOTES) ?></td>
		<?php else: ?>
		<td><?php echo htmlspecialchars($value, ENT_NOQUOTES) ?></td>
		<?php endif; ?>
		<?php endforeach; ?>
	</tr>

	<?php
	$entityNum = 0;
	foreach($entityIds as $entityId):
		$entityNum++;
		$e = $p->getEntityById($entityId);
		$q = 'SELECT
				IF(https="yes" AND https_ip_tlsv1=https_ip_total AND https_ip_total>0 AND https_ip_sslv2=0, 1, 0) AS https_ok,
				https,
				https_ip_total, https_ip_country_se, https_ip_country_other,
				https_ip_country_unknown, https_ip_tls_forward_secrecy,
				https_ip_sslv2, https_ip_sslv3,
				https_ip_tlsv1, https_ip_tlsv1_1,
				https_ip_tlsv1_2
				FROM reports
				WHERE entity_id='. $entityId .' AND created=CURDATE()
				';
		$r = $m->query($q) or die("$m->error, SQL: $q");
		$row = $r->fetch_object();
		if(!$row) continue;
		$r->close();
		$title = $e->org;
		if(mb_strlen($title) > 30)
			$title = mb_substr($title, 0, 30) .'…';
	?>
			<?php if($entityNum % 20 === 0): ?>
			<tr class="collapse out tag<?php echo $tagId ?>">
				<th>Organisation</th>
				<th>HTTPS OK</th>
				<th title="Om HTTPS enbart används (påtvingat) på huvuddsajten, om båda protokollen stöds (tillgängligt) eller om inget protokoll stöds (saknas)">Stöd</th>
				<th>Land (SE)</th>
				<th>Land (övr)</th>
				<th>Land (?)</th>
				<th>HTTPS</th>
				<th>PFS</th>
				<th>SSLv2</th>
				<th>SSLv3</th>
				<th>TLSv1</th>
				<th>TLSv1.1</th>
				<th>TLSv1.2</th>
			</tr>
			<?php endif; ?>
			<tr class="collapse out tag<?php echo $tagId ?>">
				<td><a href="/view.php?domain=<?php echo urlencode($e->domain); ?>"><?php echo htmlspecialchars($title, ENT_NOQUOTES) ?></a></td>
				<?php
				foreach($row as $key => $value):
					$class = '';

					if($key === 'https_ip_total') continue;

					if($key === 'https_ok') {
						if($value > 0) {
							$class = 'success';
							$value = '<i class="glyphicon glyphicon-ok"></i> HTTPS';
						}
						else {
							$class = 'warning';
							$value = '<i class="glyphicon glyphicon-remove"></i> HTTPS';
						}
					}

					if(in_array($key, array('https_ip_country_se', 'https_ip_starttls', 'https_ip_tls_forward_secrecy', 'https_ip_sslv3', 'https_ip_tlsv1', 'https_ip_tlsv1_1', 'https_ip_tlsv1_2', 'https_ip_tls_forward_secrecy'))) {
						if($value === $row->https_ip_total)
							$class = 'success';
						else {
							$class = 'warning';
						}

						if(!$value) {
							if($key === 'https_ip_starttls')
								$class = 'danger';
						}
						else if($value === $row->https_ip_total)
							$value = 'alla';
						else
							$value = sprintf('%d av %d', $value, $row->https_ip_total);
					}

					if(in_array($key, array('https'))) {
						switch($value) {
						case 'yes': $class = 'success'; $value = 'Påtvingat'; break;
						case 'partial': $class = 'warning'; $value = 'Tillgängligt'; break;
						case 'no': $class = 'danger'; $value = 'Saknas'; break;
						}
					}

					if(in_array($key, array('https_ip_country_other', 'https_ip_sslv2'))) {
						if($value > 0) {
							$class = 'warning';
							if($key === 'https_ip_sslv2')
								$class = 'danger';
						}
						else
							$class = 'success';

						if(!$value)
							$value = 'inga';
						else if($value === $row->https_ip_total)
							$value = 'alla';
						else
							$value = sprintf('%d av %d', $value, $row->https_ip_total);
					}
				?>
				<td class="<?php echo $class ?>"><?php echo $value ?></td>
				<?php endforeach; ?>
			</tr>
	<?php endforeach; /* entity IDs with tag */?>
<?php endforeach; /* entity IDs with tag */?>
		</tbody>
	</table>

		<hr/>
		<?php include('footer.php'); ?>
	</div> <!-- /.container -->
</body>
</html>

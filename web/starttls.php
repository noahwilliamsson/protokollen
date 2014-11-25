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
	<title>Protokollen - STARTTLS-stöd på mejlservrar</title>

	<!-- Bootstrap -->
	<link href="css/bootstrap.min.css" rel="stylesheet" />

	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
		<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
	<![endif]-->
	<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
	<!-- Include all compiled plugins (below), or include individual files as needed -->
	<script src="js/bootstrap.min.js"></script>
</head>
<body>
	<?php include('nav.php') ?>

	<div class="container">

	<h2>STARTTLS-stöd på mejlservrar (<?php echo strftime('%F') ?>)</h2>

	<p><a href="https://en.wikipedia.org/wiki/STARTTLS">STARTTLS</a> ger möjlighet till krypterad leverans av mejl, förutsatt att både sändande och mottagande mejlserver stöder det. Det förhindrar passiv massövervakning av elektronisk post.</p>
	<p>För att få <em>STARTTLS OK</em> krävs att alla mejlservrar har stöd för STARTTLS. Siffrorna nedan avser hur många IP-adresser det handlar om. En domän kan ha flera mejlservrar och varje mejlserver kan ha flera IP-adresser.</p>
	<p>Klicka på <span class="glyphicon glyphicon-plus"></span> för att fälla ut kategorin (det tar lite tid så håll ut..). Tabellen kan laddas ner i <a href="/reports.php">CSV-format</a> (UTF-8).</p>

	<table class="table table-condensed table-striped" id="starttls">
		<thead>
			<tr>
				<th>Organisation</th>
				<th>STARTTLS OK</th>
				<th>Land (SE)</th>
				<th>Land (övr)</th>
				<th title="IP-adressen kunde inte landsbestämmas (vanligt för IPv6-adresser)">Land (?)</th>
				<th>STARTTLS</th>
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
	$q = 'SELECT entity_id
			FROM entity_tags et
			LEFT JOIN entities e ON et.entity_id=e.id
			WHERE tag_id="'. $m->escape_string($tagId) .'"
			ORDER BY org';
	$r = $m->query($q) or die($m->error);
	while($row = $r->fetch_object())
		$entityIds[] = $row->entity_id;
	$r->close();

	$q = '	SELECT
				SUM(IF(mx_ip_starttls=mx_ip_total, 1, 0)) AS starttls_ok,
				SUM(IF(mx_ip_country_se>0,1,0)) mx_ip_country_se,
				SUM(IF(mx_ip_country_other>0,1,0)) mx_ip_country_other,
				SUM(IF(mx_ip_country_unknown>0,1,0)) mx_ip_country_unknown,
				SUM(IF(mx_ip_starttls>0,1,0)) mx_ip_starttls,
				SUM(IF(mx_ip_starttls_pfs>0,1,0)) mx_ip_starttls_pfs,
				SUM(IF(mx_ip_starttls_sslv2>0,1,0)) mx_ip_starttls_sslv2,
				SUM(IF(mx_ip_starttls_sslv3>0,1,0)) mx_ip_starttls_sslv3,
				SUM(IF(mx_ip_starttls_tlsv1>0,1,0)) mx_ip_starttls_tlsv1,
				SUM(IF(mx_ip_starttls_tlsv1_1>0,1,0)) mx_ip_starttls_tlsv1_1,
				SUM(IF(mx_ip_starttls_tlsv1_2>0,1,0)) mx_ip_starttls_tlsv1_2
			FROM reports
			WHERE entity_id IN('. implode(',', $entityIds) .')
			AND created=CURDATE()
			';
	$r = $m->query($q);
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
		<td><?php echo htmlspecialchars($value, ENT_NOQUOTES) ?></td>
		<?php endforeach; ?>
	</tr>

	<?php
	$entityNum = 0;
	foreach($entityIds as $entityId):
		$entityNum++;
		$e = $p->getEntityById($entityId);
		$q = 'SELECT
				IF(mx_ip_starttls=mx_ip_total, 1, 0) AS starttls_ok,
				mx_ip_total, mx_ip_country_se, mx_ip_country_other,
				mx_ip_country_unknown, mx_ip_starttls, mx_ip_starttls_pfs,
				mx_ip_starttls_sslv2, mx_ip_starttls_sslv3,
				mx_ip_starttls_tlsv1, mx_ip_starttls_tlsv1_1,
				mx_ip_starttls_tlsv1_2
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
				<th>STARTTLS OK</th>
				<th>Land (SE)</th>
				<th>Land (övr)</th>
				<th>Land (?)</th>
				<th>STARTTLS</th>
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

					if($key === 'mx_ip_total') continue;

					if($key === 'starttls_ok') {
						if($value > 0) {
							$class = 'success';
							$value = '<i class="glyphicon glyphicon-ok"></i> STARTTLS';
						}
						else {
							$class = 'warning';
							$value = '<i class="glyphicon glyphicon-remove"></i> STARTTLS';
						}
					}

					if(in_array($key, array('mx_ip_country_se', 'mx_ip_starttls', 'mx_ip_starttls_pfs', 'mx_ip_starttls_sslv3', 'mx_ip_starttls_tlsv1', 'mx_ip_starttls_tlsv1_1', 'mx_ip_starttls_tlsv1_2', 'mx_ip_starttls_pfs'))) {
						if($value === $row->mx_ip_total)
							$class = 'success';
						else {
							$class = 'warning';
						}

						if(!$value) {
							// $value = 'inga';
							if($key === 'mx_ip_starttls')
								$class = 'danger';
						}
						else if($value === $row->mx_ip_total)
							$value = 'alla';
						else
							$value = sprintf('%d av %d', $value, $row->mx_ip_total);
					}

					if(in_array($key, array('mx_ip_country_other', 'mx_ip_starttls_sslv2'))) {
						if($value > 0) {
							$class = 'warning';
							if($key === 'mx_ip_starttls_sslv2')
								$class = 'danger';
						}
						else
							$class = 'success';

						if(!$value)
							$value = 'inga';
						else if($value === $row->mx_ip_total)
							$value = 'alla';
						else
							$value = sprintf('%d av %d', $value, $row->mx_ip_total);
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

		<footer>
			<p>&copy; Cykla och vält Feb, 2014</p>
		</footer>
	</div> <!-- /container -->
</body>
</html>

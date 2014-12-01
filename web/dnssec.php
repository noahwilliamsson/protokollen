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
	<title>Protokollen - DNSSEC-stöd på internettjänster</title>

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

	<h2>DNSSEC-stöd på internettjänster (<?php echo strftime('%F') ?>)</h2>

	<p><a href="https://en.wikipedia.org/wiki/Domain_Name_System_Security_Extensions">DNSSEC</a> är viktigt för trovärdigheten eftersom protokollet ger verifierbara svar på DNS-frågor. Det förhindrar att en elak tredje part kan manipulera svar på DNS-uppslag.</p>
	<p>För att få <em>DNSSEC OK</em> krävs att alla namnservrar (NS) är säkrade med DNSSEC, att alla mejlservrarna (MX) är säkrade med DNSSEC (om man hanterar mejl på domänen) och att de domäner som används för webben är säkrade med DNSSEC.</p>
	<p>Klicka på <span class="glyphicon glyphicon-plus"></span> för att fälla ut kategorin (det tar lite tid så håll ut..). Det lyser grönt när adresser för tjänsten har stöd för DNSSEC. Siffran inom parantes anger hur många värddatorer som har stöd för DNSSEC. Tabellen kan laddas ner i <a href="/reports.php">CSV-format</a> (UTF-8).</p>

	<table class="table table-condensed table-striped" id="dnssec">
		<thead>
			<tr>
				<th>Organisation</th>
				<th>DNSSEC OK</th>
				<th>NS DNSSEC</th>
				<th>MX DNSSEC</th>
				<th>Webb DNSSEC</th>
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
			ORDER BY IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total, 1, 0) DESC, org';
	$r = $m->query($q) or die($m->error);
	while($row = $r->fetch_object())
		$entityIds[] = $row->entity_id;
	$r->close();

	$q = '	SELECT
				SUM(IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total, 1, 0)) dnssec_ok,
				SUM(IF(ns_dnssec = ns_total,1,0)) any_ns_dnssec,
				SUM(IF(mx_dnssec = mx_total,1,0)) any_mx_dnssec,
				SUM(IF(web_dnssec = web_total,1,0)) any_web_dnssec
			FROM reports
			WHERE entity_id IN('. implode(',', $entityIds) .')
			AND created=CURDATE()
			';
	$r = $m->query($q);
	$header = $r->fetch_object();
	$r->close();
?>
	<tr id="dnssec-tag<?php echo $tagId ?>">
		<td>
			<span class="glyphicon glyphicon-plus"></span><a href="#dnssec-tag<?php echo $tagId ?>" onclick="return false" data-toggle="collapse" data-target="tr.tag<?php echo $tagId ?>">
			<?php echo htmlspecialchars($tag, ENT_NOQUOTES) ?></a>
			(<?php echo count($entityIds) ?>st)
		</td>
		<?php foreach($header as $key => $value): ?>
		<?php if($key === 'dnssec_ok'): ?>
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
				IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total, 1, 0) dnssec_ok,
				ns_dnssec AS NS, mx_dnssec AS MX, web_dnssec AS Webb,
				ns_total AS NS_total, mx_total AS MX_total, web_total AS Webb_total
				FROM reports
				WHERE entity_id='. $entityId .' AND created=CURDATE()
				';
		$r = $m->query($q);
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
				<th>DNSSEC OK</th>
				<th>NS DNSSEC</th>
				<th>MX DNSSEC</th>
				<th>Webb DNSSEC</th>
			 </tr>
			<?php endif; ?>
			<tr class="collapse out tag<?php echo $tagId ?>">
				<td><a href="/view.php?domain=<?php echo urlencode($e->domain); ?>"><?php echo htmlspecialchars($title, ENT_NOQUOTES) ?></a></td>
				<?php
				foreach($row as $key => $value):
					$class = 'warning';
					/**
					 * Skip _total columns as they're only included to
					 * determine if a zero value in other columns are OK.
					 * The common scenario is when a zone has zero MX rercords.
					 */
					if(strstr($key, '_total'))
						continue;

					$totalKey = "${key}_total";
					if($value > 0 || ($key !== 'dnssec_ok' && $value === $row->$totalKey)) {
						$class = 'success';
						$value = "$key ($value)";
						if($key === 'dnssec_ok')
							$value = '<i class="glyphicon glyphicon-ok"></i> DNSSEC';
					}
					else {
						$value = "$key ($value)";
						if($key === 'dnssec_ok')
							$value = '<i class="glyphicon glyphicon-remove"></i> DNSSEC';
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

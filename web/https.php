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
	<title>Protokollen - HTTPS-stöd på internettjänster</title>

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

	<h2>HTTPS-stöd på internettjänster (<?php echo strftime('%F') ?>)</h2>

	<p><strong>TODO</strong> Allt</p>
	<p>Klicka på <span class="glyphicon glyphicon-plus"></span> för att fälla ut kategorin (det tar lite tid så håll ut..). Det lyser grönt när adresser för tjänsten har stöd för HTTPS. Siffran inom parantes anger hur många värddatorer som har stöd för HTTPS. Tabellen kan laddas ner i <a href="/reports.php">CSV-format</a> (UTF-8).</p>

	<table class="table table-condensed table-striped" id="https">
		<thead>
			<tr>
				<th>Organisation</th>
				<th>HTTPS OK</th>
				<th>Webb HTTPS</th>
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
			ORDER BY https DESC, org';
	$r = $m->query($q) or die($m->error);
	while($row = $r->fetch_object())
		$entityIds[] = $row->entity_id;
	$r->close();

	$q = '	SELECT
				SUM(IF(https="yes", 1, 0)) https_ok
			FROM reports
			WHERE entity_id IN('. implode(',', $entityIds) .')
			AND created=CURDATE()
			';
	$r = $m->query($q);
	$header = $r->fetch_object();
	$r->close();
?>
	<tr id="https-tag<?php echo $tagId ?>">
		<td>
			<span class="glyphicon glyphicon-plus"></span><a href="#https-tag<?php echo $tagId ?>" onclick="return false" data-toggle="collapse" data-target="tr.tag<?php echo $tagId ?>">
			<?php echo htmlspecialchars($tag, ENT_NOQUOTES) ?></a>
			(<?php echo count($entityIds) ?>st)
		</td>
		<?php foreach($header as $key => $value): ?>
		<td colspan="2"><?php echo htmlspecialchars(sprintf('%.1f%% (%dst)', 100.0*$value/count($entityIds), $value), ENT_NOQUOTES) ?></td>
		<?php endforeach; ?>
	</tr>

	<?php
	$entityNum = 0;
	foreach($entityIds as $entityId):
		$entityNum++;
		$e = $p->getEntityById($entityId);
		$q = 'SELECT
				IF(https="yes", 1, 0) https_ok, https
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
				<th>HTTPS OK</th>
				<th>Webb HTTPS</th>
			 </tr>
			<?php endif; ?>
			<tr class="collapse out tag<?php echo $tagId ?>">
				<td><a href="/view.php?domain=<?php echo urlencode($e->domain); ?>"><?php echo htmlspecialchars($title, ENT_NOQUOTES) ?></a></td>
				<?php
				foreach($row as $key => $value):
					$class = 'warning';
					switch($value) {
					case 'yes':
					case '1':
						$class = 'success';
						if($key === 'https_ok')
							$value = '<i class="glyphicon glyphicon-ok"></i> HTTPS';
						break;
					case '0':
						if($key === 'https_ok')
							$value = '<i class="glyphicon glyphicon-remove"></i> HTTPS';
						break;
					default:
						break;
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

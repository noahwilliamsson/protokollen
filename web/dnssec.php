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
	<title>Protokollen - nåbarhet via DNSSEC</title>

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

	<!-- Main jumbotron for a primary marketing message or call to action -->
	<div class="jumbotron">
		<div class="container">
			<p>Visa stödet för SSL/TLS på en sajt genom att skriva in domänen i rutan nedan.</p>

			<form class="form-inline" role="form" method="get" action="/view.php">
				<div class="form-group">
					<label for="domain">Domän</label>
					<input type="text" class="form-control" id="domain" name="domain" placeholder="example.se">
				</div>
				<button type="submit" class="btn btn-primary">Visa</button>
			</form>
		</div>
	</div>

	<div class="container">

	<h2>DNSSEC-stöd på internettjänster (<?php echo strftime('%F') ?>)</h2>
	<!--
	<table class="table table-condensed table-hover table-striped" id="dnssec">
	-->
	<table class="table table-condensed" id="dnssec">
		<thead>
			<tr>
				<th>Organisation</th>
				<th>DNSSEC OK</th>
				<th>NS DNSSEC</th>
				<th>MX DNSSEC</th>
				<th>Web DNSSEC</th>
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
		<td><?php echo htmlspecialchars($value, ENT_NOQUOTES) ?></td>
		<?php endforeach; ?>
	</tr>

	<?php
	foreach($entityIds as $entityId):
		$e = $p->getEntityById($entityId);
		$q = 'SELECT
				IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total, 1, 0) dnssec_ok,
				ns_dnssec AS NS, mx_dnssec AS MX, web_dnssec AS Web,
				ns_total AS NS_total, mx_total AS MX_total, web_total AS Web_total
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

		<footer>
			<p>&copy; Cykla och vält Feb, 2014</p>
		</footer>
	</div> <!-- /container -->
</body>
</html>

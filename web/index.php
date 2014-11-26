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
	<title>Protokollen - undersök internetjänsters säkerhet</title>

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
	<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.2/jquery.flot.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/flot/0.8.2/jquery.flot.pie.min.js"></script>
	<script src="flot/jquery.flot.valuelabels.js"></script>
</head>
<body>
	<?php include('nav.php'); ?>

	<!-- Main jumbotron for a primary marketing message or call to action -->
	<div class="jumbotron">
		<div class="container">
			<p>Visa stödet för moderna internetprotokoll på en sajt genom att skriva in domänen i rutan nedan.</p>

			<form class="form-inline" role="form" method="get" action="/view.php">
				<div class="form-group">
					<label for="domain">Domän</label>
					<input type="text" class="form-control" id="domain" name="domain" placeholder="example.se" />
				</div>
				<button type="submit" class="btn btn-primary">Visa</button>
			</form>
		</div>
	</div>

	<div class="container">
		<p><strong>TODO</strong> Mindre detaljer här. Det faktiska SSL/TLS-protokollen är ointressanta jämfört med huruvida https stöds alls och om det finns stöd för forward secrecy. Protokolldetaljerna borde nog bo på en egen sida.</p>
		<p><strong>TODO</strong> Hämta data från en summary tabell istället så det inte går så långsamt.</p>
		<p><strong>TODO</strong> Någon slags var-har-jag-hamnat-och-vad-betyder-allt sida hade varit bra.</p>
		<script>
		function labelFormatter(label, series) {
			return "<div style='font-size:8pt; text-align:center; padding:2px; color:white;'>" + label.toUpperCase().replace('V','v').replace('_','.') + "<br/>" + Math.round(series.percent) + "% (n=" + series.data[0][1] +")</div>";
		}

		var pieOpts = {
		series: {
		pie: {
			show: true,
			radius: 3/4,
			label: {
			show: true,
			radius: 3/4,
			formatter: labelFormatter,
			background: {
				opacity: 0.5,
				color: '#000'
			}
			}
		}
		},
		legend: {
		show: false
		}
		}

		var barOpts = {
			series: {
				bars: {
					show: true,
					barWidth: 0.6,
					align: "center"
				},
				valueLabels: {
					show: true
				}
			},
			xaxis: {
				tickLength: 0
			}
		};
		</script>

<?php

require_once('flot.php');

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
	$q = 'SELECT entity_id FROM entity_tags WHERE tag_id="'. $m->escape_string($tagId) .'"';
	$r = $m->query($q);
	while($row = $r->fetch_object())
		$entityIds[] = $row->entity_id;
	$r->close();

	$flots = makeFlots($entityIds);
	list($flot, $flot2, $uniqueIps, $flot3) = $flots;

	$hash = substr(md5($tag), 0, 8);
?>

	<h2><?php echo htmlspecialchars($tag, ENT_NOQUOTES) ?></h2>
	<p>Antal unika IP-adresser: <?php echo count($uniqueIps) ?></p>
	<div id="tls-status-3-<?php echo $hash ?>" style="width:350px;height:250px;float:left"></div>
	<table class="table table-condensed table-hover table-striped" style="float:left; width:400px">
		<thead>
			<tr>
				<th>Org.</th>
				<th>Domän</th>
				<th>SSLv2</th>
				<th>SSLv3</th>
				<th>TLSv1</th>
				<th>TLSv1.1</th>
				<th>TLSv1.2</th>
			</tr>
		</thead>
		<tbody>
		<?php
		for($i = 0, $j = 0; $i < count($entityIds) && $j < 10; $i++):
			$e = $p->getEntityById($entityIds[$i]);
			$services = $p->listServices($e->id, ProtokollenBase::SERVICE_TYPE_HTTPS);
			foreach($services as $svc):

				$grp = $p->getServiceGroup($svc->id);
				/* Fetch WWW prefs test and make sure https is supported */
				$prefs = $wwwPrefsTest->getItem($svc->id, $grp->id);
				if(!$prefs || !$prefs->url || $prefs->errors !== NULL)
					continue;

				$sslprobe = NULL;
				$wwwHostname = parse_url($prefs->url, PHP_URL_HOST);
				foreach($grp->data as $svcHost) {
					if($svcHost->hostname !== $wwwHostname)
						continue;

					$sslprobe = $sslprobeTest->getItem($svc->id, $grp->id, $svcHost->hostname);
					break;
				}

				if($sslprobe === NULL)
					continue;

				$j++;
				$title = mb_substr($prefs->url, 0, 40);
				if(mb_strlen($prefs->url) > 40) $title .= '…';
				if(empty($title)) $title = $e->domain;

				$protocols = array('sslv2' => 0, 'sslv3' => 0, 'tlsv1' => 0, 'tlsv1_1' => 0, 'tlsv1_2' => 0);
				foreach(array_keys($protocols) as $key)
					$protocols[$key] = $sslprobe->$key;

		?>
			<tr>
				<td><?php echo htmlspecialchars($e->org, ENT_NOQUOTES) ?></td>
				<td><a href="/view.php?domain=<?php echo urlencode($e->domain); ?>"><?php echo htmlspecialchars($title, ENT_NOQUOTES) ?></a></td>
				<?php
				foreach($protocols as $key => $num):
					$msg = 'OK';
					$color = 'green';
					switch($key) {
					case 'sslv2': $msg = 'ERR'; $color = 'red'; break;
					case 'sslv3': $msg = 'ERR'; $color = 'red'; break;
					case 'tlsv1': break;
					case 'tlsv1_1': break;
					case 'tlsv1_2': break;
					}
				?>
				<td><span style="background: <?php echo $color ?>; margin: 3px; font-weight: bold; color:white"><?php if($num > 0) echo $msg; ?></span></td>
				<?php endforeach; ?>
			</tr>
			<?php endforeach; /* services */?>
		<?php endfor; /* iterate 10 */ ?>
		<?php if($j == 10): ?><tr><td colspan="2">...</td></tr><?php endif; ?>
		</tbody>
	</table>
	<hr style="clear:both"/>

	<script>
	<?php
	$ticks = array();
	$idx = 0;
	foreach($flot3 as $key => $unused)
		$ticks[] = array($idx++, $key);
	?>
	barOpts.xaxis.ticks = <?php echo json_encode($ticks) ?>;

	/*
	jQuery.plot(jQuery('#tls-status-1-<?php echo $hash ?>'), <?php echo json_encode($flot) ?>, pieOpts);
	jQuery.plot(jQuery('#tls-status-2-<?php echo $hash ?>'), <?php echo json_encode($flot2) ?>, pieOpts);
	*/
	jQuery.plot(jQuery('#tls-status-3-<?php echo $hash ?>'), <?php echo json_encode(array_values($flot3)) ?>, barOpts);
	</script>
<?php
endforeach;
?>

		<hr/>
		<?php include('footer.php'); ?>
	</div> <!-- /.container -->
</body>
</html>

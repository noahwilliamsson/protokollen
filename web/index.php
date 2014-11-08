<?php
require_once('../php/Protokollen.class.php');
$p = new Protokollen();
$m = $p->getMySQLHandle();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Protokollen - undersök internetjänsters säkerhet</title>

	<!-- Bootstrap -->
	<link href="css/bootstrap.min.css" rel="stylesheet">

	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
		<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
	<![endif]-->
	<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
	<!-- Include all compiled plugins (below), or include individual files as needed -->
	<script src="js/bootstrap.min.js"></script>
	<script src="flot/jquery.flot.js"></script>
	<script src="flot/jquery.flot.pie.js"></script>
<style type="text/css">
/* Move down content because we have a fixed navbar that is 50px tall */
body {
padding-top: 50px;
padding-bottom: 20px;
}
</style>
</head>
<body>
	<div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
		<div class="container">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="/">Protokollen</a>
			</div>
			<div class="collapse navbar-collapse">
				<ul class="nav navbar-nav">
					<li class="active"><a href="/">Hem</a></li>
					<li><a href="#medier">Medier</a></li>
					<li><a href="#myndigheter">Myndigheter</a></li>
					<li><a href="https://github.com/noahwilliamsson/protokollen">Om tjänsten</a></li>
				</ul>
			</div><!--/.nav-collapse -->
		</div>
	</div>

	<!-- Main jumbotron for a primary marketing message or call to action -->
	<div class="jumbotron">
		<div class="container">
			<!--
			<h1>Visa sajt</h1>
			-->
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
	<script>
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
	function labelFormatter(label, series) {
		return "<div style='font-size:8pt; text-align:center; padding:2px; color:white;'>" + label.toUpperCase().replace('V','v').replace('_','.') + "<br/>" + Math.round(series.percent) + "% (n=" + series.data[0][1] +")</div>";
	}
	</script>
<?php


require_once('flot.php');

$categories = array();
$q = 'SELECT DISTINCT cat FROM entities WHERE id > 1';
$r = $m->query($q);
while($row = $r->fetch_object())
	$categories[] = $row->cat;
$r->close();

foreach($categories as $cat):
	$entityIds = array();
	$q = 'SELECT id FROM entities WHERE cat="'. $m->escape_string($cat) .'"';
	$r = $m->query($q);
	while($row = $r->fetch_object())
		$entityIds[] = $row->id;
	$r->close();
	
	$flots = makeFlots($entityIds);
	list($flot, $flot2, $uniqueIps) = $flots;

	$hash = substr(md5($cat), 0, 8);
?>

	<h2><?php echo htmlspecialchars($cat, ENT_NOQUOTES) ?></h2>
	<p>Antal unika IP-adresser: <?php echo count($uniqueIps) ?></p>
	<div id="tls-status-1-<?php echo $hash ?>" style="width:250px;height:250px;float:left"></div>
	<div id="tls-status-2-<?php echo $hash ?>" style="width:250px;height:250px;float:left"></div>
	<table class="table table-condensed" style="float:left; width:400px">
		<thead>
			<tr>
				<th>Org.</th>
				<th>Domän</th>
			</tr>
		</thead>
		<tbody>
		<?php
		for($i = 0, $j = 0; $i < count($entityIds) && $j < 10; $i++):
			$e = $p->getEntityById($entityIds[$i]);
			$services = $p->listServices($e->id, Protokollen::SERVICE_TYPE_HTTP);
			foreach($services as $svc):
				$m = $p->getMySQLHandle();
				$q = 'SELECT https_preferred_url AS url FROM service_http_preferences WHERE service_id="'. $m->escape_string($svc->id) .'" AND entry_type="current" AND https_error IS NULL';
				$r = $m->query($q);
				while(($row = $r->fetch_object()) && $j < 10):
					$j++;
					$title = mb_substr($row->url, 0, 40);
					if(mb_strlen($row->url) > 40) $title .= '…';
		?>
			<tr>
				<td><?php echo htmlspecialchars($e->org, ENT_NOQUOTES) ?></td>
				<td><a href="/view.php?domain=<?php echo urlencode($e->domain); ?>"><?php echo htmlspecialchars($title, ENT_NOQUOTES) ?></a></td>
			</tr>
				<?php endwhile; ?>
			<?php endforeach; ?>
		<?php endfor; ?>
		<?php if($j == 10): ?><tr><td colspan="2">...</td></tr><?php endif; ?>
		</tbody>
	</table>
	<hr style="clear:both"/>
	<script>

	jQuery.plot(jQuery('#tls-status-1-<?php echo $hash ?>'), <?php echo json_encode($flot) ?>, pieOpts);
	jQuery.plot(jQuery('#tls-status-2-<?php echo $hash ?>'), <?php echo json_encode($flot2) ?>, pieOpts);
	</script>
<?php
endforeach;
?>

		<hr/>

		<footer>
			<p>&copy; Cykla och vält Feb, 2014</p>
		</footer>
	</div> <!-- /container -->
</body>
</html>

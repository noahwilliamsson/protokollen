<?php
require_once('../php/ServiceGroup.class.php');
require_once('../php/TestWwwPreferences.class.php');
require_once('../php/TestSslprobe.class.php');
require_once('../php/TestDnsAddresses.class.php');
require_once('../php/TestDnssecStatus.class.php');
require_once('json.inc.php');

if(!isset($_GET['domain'])) {
	header('Location: /');
	die;
}

$domain = $_GET['domain'];

$p = new ServiceGroup();
$ent = $p->getEntityByDomain($domain);

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Protokollen - undersök internetjänsters säkerhet</title>

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
        <script src="flot/jquery.flot.js"></script>
        <script src="flot/jquery.flot.pie.js"></script>
</head>
<body>
	<?php include('nav.php') ?>

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

<?php if($ent === NULL): ?>
	<div class="container">
		<h2>Domänen hittades inte</h2>
		<p>Den här tjänsten övervarkar ett tusendal domäner som bedömts vara intressanta. Domänen du sökte efter fanns inte med i den listan. I nuläget finns ingen möjlighet att själv lägga till eller testa valfria domäner.</p>
	</div>
<?php else: ?>

	<div class="container">
		<?php
		$summaryDnssec = array();
		$summaryIpv6 = array();
		$tags = $p->getEntityTags($ent->id);
		foreach($p->listServices($ent->id) as $svc) {
			$service = getServiceObject($svc->id);

			$key = "$service->name ($service->type)";
			$value = 'danger';
			if(isset($service->tests['se.protokollen.tests.dns.addresses']))
			foreach($service->tests['se.protokollen.tests.dns.addresses'] as $test) {
				$addrs = $test->data;
				switch($service->type) {
				case 'DNS':
				case 'SMTP':
					if(!empty($addrs->aaaa))
						$value = 'success';
					break;
				default:
					if($addrs->aaaa === $addrs->hosts)
						$value = 'success';
					break;
				}
			}
			$summaryIpv6[$key] = $value;

			$numTotal = 0; $num = 0;
			$key = "$service->name ($service->type)";
			if(isset($service->tests['se.protokollen.tests.dnssec.status']))
			foreach($service->tests['se.protokollen.tests.dnssec.status'] as $test) {
				foreach($test->data as $hostname => $dnssec) {
					$numTotal++;
					if($dnssec->secure)
						$num++;
				}
			}
			$value = 'danger';
			if($numTotal > 0 && $numTotal === $num)
				$value = 'success';
			$summaryDnssec[$key] = $value;
		}
		?>
		<h2>Översikt</h2>
		<div class="row">

			<div class="col-sm-6 col-md-4">
				<div class="thumbnail">
					<div class="caption">
						<h3>Domänsäkerhet</h3>
						<p>Tjänster med stöd för DNSSEC</p>
						<ul class="list-group">
							<?php foreach($summaryDnssec as $service => $class): ?>
							<li class="list-group-item list-group-item-<?php echo $class ?>"><?php echo htmlspecialchars($service, ENT_NOQUOTES) ?></li>
							<?php endforeach; ?>
						</ul>
						<p><strong>Placeholder</strong> Din sajt? Klicka här för att läsa mer om hur du <a href="#">kommer igång med DNSSEC</a>.</p>
					</div>
				</div>
			</div>

			<div class="col-sm-6 col-md-4">
				<div class="thumbnail">
					<div class="caption">
						<h3>Mejlsäkerhet</h3>
						<p>Mejlservrar med stöd för kryptering</p>
						<ul class="list-group">
							<li class="list-group-item list-group-item-info">Placeholder</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="col-sm-6 col-md-4">
				<div class="thumbnail">
					<div class="caption">
						<h3>Nåbarhet</h3>
						<p>Tjänster med tillräckligt stöd för IPv6</p>
						<ul class="list-group">
							<?php foreach($summaryIpv6 as $service => $class): ?>
							<li class="list-group-item list-group-item-<?php echo $class ?>"><?php echo htmlspecialchars($service, ENT_NOQUOTES) ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>

			<div class="col-sm-6 col-md-4">
				<div class="thumbnail">
					<div class="caption">
						<h3>Webbsäkerhet</h3>
						<p>Tjänster med stöd för HTTPS</p>
						<ul class="list-group">
							<li class="list-group-item list-group-item-danger">Placeholder</li>
						</ul>
						<p><strong>Placeholder</strong> Din sajt? Klicka här för att läsa mer om hur du skaffar ett certifikat och <a href="#">kommer igång med https</a>.</p>
					</div>
				</div>
			</div>

			<div class="col-sm-6 col-md-4">
				<div class="thumbnail">
					<div class="caption">
						<h3>Kategorier</h3>
						<ul class="list-group">
							<?php foreach($tags as $tag): ?>
							<li class="list-group-item list-group-item-info"><?php echo htmlspecialchars($tag, ENT_NOQUOTES) ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>

		</div> <!-- /.row -->

	</div> <!-- /.container -->

	<div class="container">
		<h2>Rådata</h2>
		<p>Det aktuella rådatat finns tillgängligt i <a href="download.php?id=<?php echo urlencode($ent->id) ?>">JSON-format</a>.  Här är samma data <a href="download.php?id=<?php echo urlencode($ent->id) ?>&amp;revisions=1">inklusive historiskt data</a>. <strong>OBS!</strong> Datat väger ofta flera megabyte.</p>

		<h2>Graf över internettjänster</h2>
		<p><strong>TODO</strong> Lägg varje tjänsttyp (DNS, mejl, webb, ..) i egna flikar(?) för att reducera innehållet i grafen. Eller kanske kryssrutor för att välja vad som ska visas i grafen.<br />
		<strong>TODO</strong> Just nu är grafen en platt bild (PNG) men SVG är ett möjligt alternativ som bl.a. erbjuder klickbara länkar på bildytan. SVG är f.n. avstängt eftersom graphviz/dot ibland producerar trasig markup när det förekommer Unicode text.
		</p>
		<!--
		This works but gives no clickable links..
		-->
		<img src="gv.php?d=<?php echo rawurlencode($domain) ?>" alt="" />

		<?php
		/**
		 * Using an <object> tag gives clickable links but unfortunately
		 * dot(1) messes up SVG output if the input text contained
		 * non-ASCII characters..
		 */
		if(0):
			require_once('gv.php');
			$svg = svgForDomain($domain);
		?>
		<object type="image/svg+xml" data="data:image/svg+xml;base64,<?php echo base64_encode($svg) ?>"></object>
		<?php
		endif;
		?>
	</div>

	<div class="container">
		<h2>Lista över tjänster</h2>
		<p><strong>TODO</strong> Det här borde grupperas under flikar och presenteras på ett snyggare sätt. Just nu mest data för felsökning.</p>
		<?php
		foreach($p->listServices($ent->id) as $svc):
			/*
			if($svc->service_type === ProtokollenBase::SERVICE_TYPE_DNS) continue;
			if($svc->service_type === ProtokollenBase::SERVICE_TYPE_SMTP) continue;
			*/

			$grp = $p->getServiceGroup($svc->id);
			$testWww = new TestWwwPreferences();
			$testDnssec = new TestDnssecStatus();
			$testDnsAddrs = new TestDnsAddresses();
			$testProbes = new TestSslprobe();
			$prefs = $testWww->getItem($svc->id, $grp->id);
			$dnssec = $testDnssec->getItem($svc->id, $grp->id);
			$addrs = $testDnsAddrs->getItem($svc->id, $grp->id);
		?>
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">

				<?php
				$title = $svc->service_name;
				if(!empty($svc->service_desc))
						$title .= ' ('. $svc->service_desc .')';
				echo htmlspecialchars($title, ENT_NOQUOTES);
				if($prefs !== NULL):
					foreach($prefs as $key => $value):
					switch($key) {
					case 'url':
						if(empty($value))
							break;
						$scheme = parse_url($value, PHP_URL_SCHEME);
						echo '<span class="label label-success">'. htmlspecialchars($scheme, ENT_NOQUOTES) .'</span>';
						break;
					case 'errors':
						if(!empty($value))
							echo '<span class="label label-danger">HTTP error</span>';
						break;
					}
					echo ' ';
					endforeach;
				endif;
				?>
				</h3>
			</div>

			<div class="panel-body">
				<?php if($prefs !== NULL): ?>
					<h4>Webbtest</h4>

					<?php if($prefs->title): ?>
					<p>
					<strong>Föredragen webbplats:</strong>
					<a href="<?php echo htmlspecialchars($prefs->url); ?>"><?php echo htmlspecialchars($prefs->title, ENT_NOQUOTES); ?></a> (<?php echo htmlspecialchars(parse_url($prefs->url, PHP_URL_HOST), ENT_NOQUOTES) ?>)
					</p>
					<?php endif; ?>

					<!-- List group -->
					<ul class="list-group">
						<?php if($prefs->errors): ?>
						<li class="list-group-item list-group-item-danger"><span class="label label-warning">error</span> <?php echo htmlspecialchars($prefs->errors, ENT_NOQUOTES); ?></li>
						<?php endif; ?>
					</ul>
					<?php
					$str = json_encode($prefs->data, JSON_PRETTY_PRINT);
					?>
					<pre><?php echo htmlspecialchars($str, ENT_NOQUOTES) ?></pre>

				<?php endif; // prefs ?>

				<?php
				if($dnssec !== NULL):
					$str = json_encode($dnssec->data, JSON_PRETTY_PRINT);
				?>
				<h4>DNSSEC</h4>
				<pre><?php echo htmlspecialchars($str, ENT_NOQUOTES) ?></pre>
				<?php endif; ?>

				<?php
				if($addrs !== NULL):
					$str = json_encode($addrs->data, JSON_PRETTY_PRINT);
				?>
				<h4>Service group addresses</h4>
				<pre><?php echo htmlspecialchars($str, ENT_NOQUOTES) ?></pre>
				<?php endif; ?>

				<?php
				if($grp !== NULL):
					$str = json_encode($grp->data, JSON_PRETTY_PRINT);
				?>
				<h4>Service group</h4>
				<pre><?php echo htmlspecialchars($str, ENT_NOQUOTES) ?></pre>

					<?php
					foreach($grp->data as $svcHost):
						$probe = $testProbes->getItem($svc->id, $grp->id, $svcHost->hostname);
						if($probe === NULL)
							continue;
						$str = json_encode($probe->data, JSON_PRETTY_PRINT);
					?>
					<h4>Sslprobe <?php echo htmlspecialchars($svcHost->protocol .':'. $svcHost->hostname .':'. $svcHost->port, ENT_NOQUOTES) ?></h4>
					<pre><?php echo htmlspecialchars($str, ENT_NOQUOTES) ?></pre>
					<?php endforeach; ?>
				<?php endif; ?>


			</div> <!-- /.panel-body -->
		</div> <!-- /.panel -->

		<?php endforeach; // services ?>
	</div>
<?php endif; // $ent !== NULL ?>

	<div class="container">
		<hr/>
		<?php include('footer.php'); ?>
	</div> <!-- /.container -->
</body>
</html>

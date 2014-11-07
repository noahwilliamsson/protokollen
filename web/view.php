<?php
require_once('../php/Protokollen.class.php');
$p = new Protokollen();

$domain = $_GET['domain'];
$ent = $p->getEntityByDomain($domain);
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
					<li><a href="#about">Om tjänsten</a></li>
				</ul>
			</div><!--/.nav-collapse -->
		</div>
	</div>

	<!-- Main jumbotron for a primary marketing message or call to action -->
	<div class="jumbotron">
		<div class="container">
			<h1>Visa sajt</h1>
			<p>Visa stödet för SSL/TLS på en sajt genom att skriva in domänen i rutan nedan.</p>

			<form class="form-inline" role="form" method="post" action="/view.php">
				<div class="form-group">
					<label for="domain">Domän</label>
					<input type="text" class="form-control" id="domain" name="domain" placeholder="example.se">
				</div>
				<button type="submit" class="btn btn-primary">Visa</button>
			</form>
		</div>
	</div>
	<div class="container">
		<?php
		foreach($p->listServices($ent->id) as $svc):
			if($svc->service_type === Protokollen::SERVICE_TYPE_DNS) continue;
			if($svc->service_type === Protokollen::SERVICE_TYPE_SMTP) continue;
			$m = $p->getMySQLHandle();
			$q = 'SELECT * FROM service_http_preferences WHERE service_id="'. $m->escape_string($svc->id) .'"';
			$r = $m->query($q);
			$prefs = $r->fetch_object();
			$r->close();
		?>
		<div class="panel panel-default">
		  <div class="panel-heading">
			<h3 class="panel-title">
			<?php
			echo htmlspecialchars(trim("$svc->service_name ($svc->service_desc)", '() '), ENT_NOQUOTES);
			if($prefs !== NULL):
				foreach($prefs as $key => $value):
				switch($key) {
				case 'http_preferred_url':
					if(!empty($value))
						echo '<span class="label label-success">http</span>';
					break;
				case 'https_preferred_url':
					if(!empty($value))
						echo '<span class="label label-success">https</span>';
					break;
				case 'https_error':
					if(!empty($value))
						echo '<span class="label label-danger">HTTPs error</span>';
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
					<?php if($prefs->title): ?>
					<a href="<?php echo htmlspecialchars($prefs->preferred_url); ?>"><?php echo htmlspecialchars($prefs->title, ENT_NOQUOTES); ?></a>
					<?php endif; ?>
					<!-- List group -->
				  <ul class="list-group">
					<?php if($prefs->https_error): ?>
					<li class="list-group-item list-group-item-danger"><span class="label label-danger">HTTPs error</span> <?php echo htmlspecialchars($prefs->https_error, ENT_NOQUOTES); ?></li>
					<?php endif; ?>
					<?php if($prefs->https_preferred_url): ?>
					<li class="list-group-item list-group-item-success"><span class="label label-success">https</span> <?php echo htmlspecialchars($prefs->https_preferred_url, ENT_NOQUOTES); ?></li>
					<?php endif; ?>
					<?php if($prefs->http_preferred_url): ?>
					<li class="list-group-item list-group-item-success"><span class="label label-success">http</span> <?php echo htmlspecialchars($prefs->http_preferred_url, ENT_NOQUOTES); ?></li>
					<?php endif; ?>
				  </ul>
			<?php endif; // prefs ?>
		  </div>
		</div>

		<?php endforeach; // service ?>
	</div>

	<div class="container">
		<hr/>

		<footer>
			<p>&copy; Cykla och vält Feb, 2014</p>
		</footer>
	</div> <!-- /container -->
</body>
</html>

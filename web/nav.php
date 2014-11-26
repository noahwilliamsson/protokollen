<nav class="navbar navbar-default" role="navigation">
	<div class="container-fluid">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="/">Protokollen</a>
		</div>
		<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
			<ul class="nav navbar-nav">
				<li <?php if($_SERVER['PHP_SELF'] === '/index.php') echo 'class="active"' ?>><a href="/">Hem <span class="sr-only">(current)</span></a></li>
				<li	<?php if($_SERVER['PHP_SELF'] === '/dnssec.php') echo 'class="active"' ?>><a href="dnssec.php">Domänsäkerhet (DNSSEC)</a></li>
				<li	<?php if($_SERVER['PHP_SELF'] === '/ipv6.php') echo 'class="active"' ?>><a href="ipv6.php">Nåbarhet (IPv6)</a></li>
				<li	<?php if($_SERVER['PHP_SELF'] === '/starttls.php') echo 'class="active"' ?>><a href="starttls.php">Mejlsäkerhet</a></li>
				<li	<?php if($_SERVER['PHP_SELF'] === '/https.php') echo 'class="active"' ?>><a href="#">Webbsäkerhet</a></li>

				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Hjälp <span class="caret"></span></a>
					<ul class="dropdown-menu" role="menu">
						<li><a href="#">FAQ</a></li>
						<li><a href="#">Data API</a></li>
						<li><a href="#">DNSSEC</a></li>
						<li><a href="#">IPv6</a></li>
						<li><a href="#">TLS</a></li>
					</ul>
				</li>
			</ul>
			<ul class="nav navbar-nav navbar-right">
				<li><a href="https://github.com/noahwilliamsson/protokollen">GitHub (utveckling)</a></li>
			</ul>
		</div><!-- /.navbar-collapse -->
	</div><!-- /.container-fluid -->
</nav>

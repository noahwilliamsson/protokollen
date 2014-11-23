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

    <!-- Collect the nav links, forms, and other content for toggling -->
    <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
      <ul class="nav navbar-nav">
        <li <?php if($_SERVER['PHP_SELF'] === '/index.php') echo 'class="active"' ?>><a href="/">Hem <span class="sr-only">(current)</span></a></li>
		<!--
        <li class="dropdown <?php if(in_array($_SERVER['PHP_SELF'], array('/dnssec.php', '/ipv6.php'))) echo 'active' ?>">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Listor <span class="caret"></span></a>
          <ul class="dropdown-menu" role="menu">
            <li  <?php if($_SERVER['PHP_SELF'] === '/dnssec.php') echo 'class="active"' ?>><a href="dnssec.php">DNSSEC-stöd på internettjänster</a></li>
            <li  <?php if($_SERVER['PHP_SELF'] === '/ipv6.php') echo 'class="active"' ?>><a href="ipv6.php">Nåbarhet över IPv6</a></li>
          </ul>
        </li>
		-->
            <li  <?php if($_SERVER['PHP_SELF'] === '/dnssec.php') echo 'class="active"' ?>><a href="dnssec.php">DNSSEC-stöd på internettjänster</a></li>
            <li  <?php if($_SERVER['PHP_SELF'] === '/ipv6.php') echo 'class="active"' ?>><a href="ipv6.php">Nåbarhet över IPv6</a></li>
      </ul>
      <ul class="nav navbar-nav navbar-right">
        <li><a href="https://github.com/noahwilliamsson/protokollen">GitHub (utveckling)</a></li>
      </ul>
    </div><!-- /.navbar-collapse -->
  </div><!-- /.container-fluid -->
</nav>

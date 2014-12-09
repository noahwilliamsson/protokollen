	<h2>Användning av moderna protokoll (<?php echo strftime('%F') ?>)</h2>

	<p>Klicka på <span class="glyphicon glyphicon-plus"></span> för att fälla ut kategorin (det tar lite tid så håll ut..). Klicka på en enskild organisation för att se mer detaljer. Tabellen kan laddas ner i <a href="/reports.php?date=<?php echo strftime('%F', strtotime('3 hours ago')) ?>&amp;charset=UTF-8">CSV-format</a> (UTF-8 eller <a href="/reports.php?date=<?php echo strftime('%F', strtotime('3 hours ago')) ?>&amp;charset=ISO-8859-1">ISO-8859-1</a> för Excel på Mac).</p>
	<table class="table table-condensed table-striped" id="https">
		<thead>
			<tr>
				<th>Organisation</th>
				<th>Allt OK</th>
				<th>HTTPS</th>
				<th>DNSSEC</th>
				<th>IPv6</th>
				<th>STARTTLS</th>
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
			ORDER BY
				IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total AND mx_ip_starttls=mx_ip_total AND https="yes" AND https_ip_tlsv1=https_ip_total AND https_ip_total>0 AND https_ip_sslv2=0 AND ns_ipv6>0 AND mx_ipv6>0 AND web_ipv6>0 AND web_total=web_ipv6, 1, 0) DESC,
				IF(https="yes" AND https_ip_tlsv1=https_ip_total AND https_ip_total>0 AND https_ip_sslv2=0, 1, 0) DESC,
				IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total, 1, 0) DESC,
				IF(ns_ipv6>0 AND mx_ipv6>0 AND web_ipv6>0 AND web_total=web_ipv6, 1, 0) DESC,
				IF(mx_ip_starttls=mx_ip_total, 1, 0) DESC,
				org
			';
	$r = $m->query($q) or die($m->error);
	while($row = $r->fetch_object())
		$entityIds[] = $row->entity_id;
	$r->close();

	$q = '	SELECT
				SUM(IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total AND mx_ip_starttls=mx_ip_total AND https="yes" AND https_ip_tlsv1=https_ip_total AND https_ip_total>0 AND https_ip_sslv2=0 AND ns_ipv6>0 AND mx_ipv6>0 AND web_ipv6>0 AND web_total=web_ipv6, 1, 0)) all_ok,
				SUM(IF(https="yes" AND https_ip_tlsv1=https_ip_total AND https_ip_total>0 AND https_ip_sslv2=0, 1, 0)) AS https_ok,
				SUM(IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total, 1, 0)) dnssec_ok,
				SUM(IF(ns_ipv6>0 AND mx_ipv6>0 AND web_ipv6>0 AND web_total=web_ipv6, 1, 0)) ipv6_ok,
				SUM(IF(mx_ip_starttls=mx_ip_total, 1, 0)) AS starttls_ok
			FROM reports
			WHERE entity_id IN('. implode(',', $entityIds) .')
			AND created=CURDATE()
			';
	$r = $m->query($q)
	or die("$m->error, SQL: $q");
	$header = $r->fetch_object();
	$r->close();
?>
	<tr id="meta-tag<?php echo $tagId ?>">
		<td>
			<span class="glyphicon glyphicon-plus"></span><a href="#meta-tag<?php echo $tagId ?>" onclick="return false" data-toggle="collapse" data-target="tr.tag<?php echo $tagId ?>">
			<?php echo htmlspecialchars($tag, ENT_NOQUOTES) ?></a>
			(<?php echo count($entityIds) ?>st)
		</td>
		<?php foreach($header as $key => $value): ?>
		<td><?php echo htmlspecialchars(sprintf('%.1f%% (%dst)', 100.0*$value/count($entityIds), $value), ENT_NOQUOTES) ?></td>
		<?php endforeach; ?>
	</tr>

	<?php
	$entityNum = 0;
	foreach($entityIds as $entityId):
		$entityNum++;
		$e = $p->getEntityById($entityId);
		$q = 'SELECT
				IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total AND mx_ip_starttls=mx_ip_total AND https="yes" AND https_ip_tlsv1=https_ip_total AND https_ip_total>0 AND https_ip_sslv2=0 AND ns_ipv6>0 AND mx_ipv6>0 AND web_ipv6>0 AND web_total=web_ipv6, 1, 0) all_ok,
				IF(https="yes" AND https_ip_tlsv1=https_ip_total AND https_ip_total>0 AND https_ip_sslv2=0, 1, 0) AS https_ok,
				IF(ns_dnssec=ns_total AND mx_dnssec=mx_total AND web_dnssec=web_total, 1, 0) dnssec_ok,
				IF(ns_ipv6>0 AND mx_ipv6>0 AND web_ipv6>0 AND web_total=web_ipv6, 1, 0) ipv6_ok,
				IF(mx_ip_starttls=mx_ip_total, 1, 0) AS starttls_ok
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
				<th>Allt OK</th>
				<th>HTTPS</th>
				<th>DNSSEC</th>
				<th>IPv6</th>
				<th>STARTTLS</th>
			</tr>
			<?php endif; ?>
			<tr class="collapse out tag<?php echo $tagId ?>">
				<td><a href="/view.php?domain=<?php echo urlencode($e->domain); ?>"><?php echo htmlspecialchars($title, ENT_NOQUOTES) ?></a></td>
				<?php
				foreach($row as $key => $value):
					$class = '';
					if($value > 0) {
						$class = 'success';
						$value = '<i class="glyphicon glyphicon-ok"></i> ';
					}
					else {
						$class = 'warning';
						$value = '<i class="glyphicon glyphicon-remove"></i> ';
					}

					switch($key) {
					case 'all_ok': $value .= 'Alla'; break;
					case 'dnssec_ok': $value .= 'DNSSEC'; break;
					case 'ipv6_ok': $value .= 'IPv6'; break;
					case 'https_ok': $value .= 'HTTPS'; break;
					case 'starttls_ok': $value .= 'STARTTLS'; break;
					}
				?>
				<td class="<?php echo $class ?>"><?php echo $value ?></td>
				<?php endforeach; ?>
			</tr>
	<?php endforeach; /* entity IDs with tag */?>
<?php endforeach; /* entity IDs with tag */?>
		</tbody>
	</table>

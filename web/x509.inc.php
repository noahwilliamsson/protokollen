<?php
/**
 * Helper routines to display certificate data in view.php
 */

function dateToDays($utcDate) {
	$tz = new DateTimeZone('UTC');
	$now = new DateTime('now', $tz);
	$other = new DateTime($utcDate, $tz);

	$interval = $now->diff($other);
	$numDays = intval($interval->format('%R%a'));
	return $numDays;
}

function getCertificateChainsFromSslprobes($probes) {
	$chains = array();
	foreach($probes as $probe) {
		foreach($probe->protocols as $proto) {
			if(!$proto->supported)
				continue;

			$hash = hash('sha256', json_encode($proto->certificates));
			$chains[$hash] = $proto->certificates;
		}
	}

	return $chains;
}

function parsePemEncodedCert($pem) {
	$cert = @openssl_x509_read($pem);
	if($cert === FALSE)
		return NULL;

	$info = openssl_x509_parse($cert);
	$obj = new stdClass();
	$obj->names = array();
	$obj->altNames = array();
	$obj->validFrom = NULL;
	$obj->validTo = NULL;
	$obj->subject = NULL;
	$obj->issuer = NULL;
	$obj->signatureAlgorithm = NULL;
	$obj->constraints = new stdClass();
	$obj->ski = NULL;
	$obj->aki = NULL;
	$obj->selfSigned = FALSE;
	if(isset($info['subject']))
		$obj->subject = (object)$info['subject'];
	if(isset($info['issuer']))
		$obj->issuer = $info['issuer'];
	if(json_encode($obj->subject) === json_encode($obj->issuer))
		$obj->selfSigned = TRUE;
	if(isset($info['subject']) && isset($info['subject']['CN'])) {
		if(!is_array($info['subject']['CN']))
			$obj->names = array($info['subject']['CN']);
		else
			$obj->names = $info['subject']['CN'];
	}
	if(isset($info['extensions'])) {
		if(isset($info['extensions']['subjectAltName'])) {
			foreach(explode(',', $info['extensions']['subjectAltName']) as $altName) {
				if(preg_match('@^DNS:(.*)@', trim($altName), $matches))
					$obj->altNames[] = trim($matches[1]);
			}
		}
		if(isset($info['extensions']['subjectKeyIdentifier']))
			$obj->ski = $info['extensions']['subjectKeyIdentifier'];
		if(isset($info['extensions']['authorityKeyIdentifier'])) {
			$obj->aki = trim($info['extensions']['authorityKeyIdentifier']);
			if(preg_match('@(([0-9A-F][0-9A-F]:){19}[0-9A-F][0-9A-F])@i', $obj->aki, $matches))
				$obj->aki = $matches[1];
		}
	}
	if(isset($info['validFrom_time_t']))
		$obj->validFrom = gmstrftime('%FT%TZ', $info['validFrom_time_t']);
	if(isset($info['validTo_time_t']))
		$obj->validTo = gmstrftime('%FT%TZ', $info['validTo_time_t']);
	if(isset($info['extensions']) && isset($info['extensions']['basicConstraints'])) {
		foreach(explode(',', $info['extensions']['basicConstraints']) as $constraint) {
			list($key, $value) = explode(':', trim($constraint));
			$value = trim($value);
			switch($key) {
			case 'CA': $value = (!strcasecmp($value, 'true')? TRUE: FALSE); break;
			case 'pathlen': $value = intval($value); break;
			default: break;
			}
			$obj->constraints->$key = $value;
		}
	}
	openssl_x509_export($cert, $data, FALSE);
	if(preg_match('@Signature Algorithm:\s*(.*)@', $data, $matches))
		$obj->signatureAlgorithm = trim($matches[1]);

	return $obj;
}

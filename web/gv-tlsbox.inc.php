<?php
/**
 * Helper routine for parsing an sslprobe(1) probe.
 * Used by the graphviz code.
 */
function parseProbe($probe) {
	$opts = array();
	$certData = array();
	$numSupportedProtocols = 0;
	foreach($probe->protocols as $proto) {
		$obj = new stdClass();
		$obj->color = '';
		$obj->title = $proto->name;
		$obj->body = '';

		if(!$proto->supported) {
				if(in_array($proto->name, array('SSL 2.0', 'SSL 3.0')))
						continue;
		}
		else {
			$numSupportedProtocols++;
		}

		switch($proto->name) {
		case 'SSL 2.0':
			$obj->color = 'green';
			$obj->body = 'NOT SUPPORTED';
			if($proto->supported) {
				$obj->color = 'red';
				$obj->body = 'DANGEROUS';
			}
			break;
		case 'SSL 3.0':
			$obj->color = 'green';
			$obj->body = 'NOT SUPPORTED';
			if($proto->supported) {
				$obj->color = 'red';
				$obj->body = 'OBSOLETE';
			}
			break;
		case 'TLS 1.0':
		case 'TLS 1.1':
			$obj->color = 'yellow';
			$obj->body = 'SUPPORTED';
			if(!$proto->supported) {
				$obj->color = 'red';
				$obj->body = 'MISSING';
			}
			break;
		case 'TLS 1.2':
			$obj->color = 'green';
			$obj->body = 'PERFECT';
			if(!$proto->supported) {
				$obj->color = 'red';
				$obj->body = 'MISSING';
			}
			break;
		}

		//if($proto->name !== 'TLS 1.0') continue;
		$certIdx = 0;
		$leafCertIdx = 0;
		foreach($proto->certificates as $pem) {
			$certIdx++;
			$x509 = openssl_x509_parse($pem, true);
			if(!isset($x509['extensions'])) {
				error_log(__FUNCTION__  .": $probe->host ($probe->ip) cert index $certIdx: No extensions in cert");
				continue;
			}

			$ext = $x509['extensions'];
			if(!isset($ext['basicConstraints'])) {
				error_log(__FUNCTION__ .": $probe->host ($probe->ip): cert index $certIdx: No constraints in cert");
				continue;
			}

			$c = new stdClass();
			$c->altName = NULL;
			$c->ca = NULL;
			$c->cn = NULL;
			$c->sigAlg = NULL;
			if($ext['basicConstraints'] === 'CA:FALSE') {
				$leafCertIdx = $certIdx;
				$c->ca = FALSE;
			}
			else if($ext['basicConstraints'] === 'CA:TRUE') {
				$c->ca = TRUE;
			}
			if(isset($x509['subject']) && isset($x509['subject']['CN']))
				$c->cn = $x509['subject']['CN'];
			if(isset($ext['subjectAltName']))
				$c->altName = $ext['subjectAltName'];

			$c->validFrom = strftime('%F %T', $x509['validFrom_time_t']);
			$c->validTo = strftime('%F %T', $x509['validTo_time_t']);
			openssl_x509_export($pem, $text, false);
			if(preg_match('@Signature Algorithm:\s+(.*)@', $text, $matches))
				$c->sigAlg = $matches[1];
			$certData[] = json_encode($c);
		}

		$obj->invalidChain = ($leafCertIdx === 1)? FALSE: TRUE;
		$opts[$proto->name] = $obj;
	}

	if($numSupportedProtocols === 0) {
		$obj = new stdClass();
		$obj->color = 'red';
		$obj->title = 'SSL/TLS';
		$obj->body = 'NOT SUPPORTED';
		$opts = array($obj);
	}

	$certData = array_unique($certData);
	$opts['certs'] = $certData;
	return $opts;
}

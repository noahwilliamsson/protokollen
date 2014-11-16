<?php
/**
 * Protokollen PHP class
 *
 */

require_once(dirname(__FILE__) .'/ServiceGroup.class.php');

class TestSslprobe extends ServiceGroup {

	function __construct() {
		parent::__construct();
	}

	/**
	 * Add sslprobe output to service set
	 * @param $svcId Service ID
	 * @param $svcGroupId Service group ID
	 * @param $json JSON output from sslprobe
	 *
	 */
	function addSslprobeJson($svcId, $svcGrpId, $json) {
		$m = $this->getMySQLHandle();

		if(($svc = $this->getServiceById($svcId)) === NULL) {
			$err = __METHOD__ .": Unknown service ($svcId)";
			throw new Exception($err);
		}

		if(($group = $this->getServiceGroupById($svcGrpId)) === NULL) {
			$err = __METHOD__ .": Unknown service group ($svcGrpId)";
			throw new Exception($err);
		}

		$probes = json_decode($json);
		if(!is_array($probes))
			throw new Exception(__METHOD__ .": Invalid JSON"
						." ($svc->id, $group->id)");
		if(empty($probes))
			return NULL;

		$hash = hash('sha256', $json);
		$jsonId = $this->addJson($svc->id, $json);
		foreach($probes as $probe) {
			$q = 'SELECT id, json_id, json_sha256
				FROM test_sslprobes
				WHERE service_id=? AND svc_group_id=?
				AND hostname=? AND entry_type="current"';
			$st = $m->prepare($q);
			$st->bind_param('iis', $svc->id, $group->id,
					$probe->host);
			if(!$st->execute()) {
				$err = "Sslprobe lookup"
					." ($svc->id, $group->id, $hostname)"
					." failed:". $m->error;
				throw new Exception($err);
			}

			$r = $st->get_result();
			$row = $r->fetch_object();
			$r->close();
			$st->close();

			if($row && $row->json_id == $jsonId)
				continue;

			$sslv2 = 0;
			$sslv3 = 0;
			$tlsv1 = 0;
			$tlsv1_1 = 0;
			$tlsv1_2 = 0;
			foreach($probe->protocols as $p) {
				if(!$p->supported)
					continue;

				switch($p->name) {
				case 'SSL 2.0': $sslv2++; break;
				case 'SSL 3.0': $sslv3++; break;
				case 'TLS 1.0': $tlsv1++; break;
				case 'TLS 1.1': $tlsv1_1++; break;
				case 'TLS 1.2': $tlsv1_2++; break;
				default: break;
				}

				foreach($p->certificates as $pem) {
					$certId = $this->addCert($pem);
					$this->addCertHostnameMapping($certId, $group->id, $probe->host);
				}
			}

			$q = 'INSERT INTO test_sslprobes SET service_id=?,
				svc_group_id=?, entry_type="current",
				json_id=?, json_sha256=?, hostname=?,
				sslv2=?, sslv3=?, tlsv1=?, tlsv1_1=?, tlsv1_2=?,
				created=NOW()';
			$st = $m->prepare($q);
			$st->bind_param('iiisssssss', $svc->id, $group->id,
					$jsonId, $hash, $probe->host, $sslv2,
					$sslv3, $tlsv1, $tlsv1_1, $tlsv1_2);
			if(!$st->execute()) {
				$err = "Sslprobe update"
					." ($svc->id, $hostname)"
					." failed: ". $m->error;
				throw new Exception($err);
			}

			$st->close();

			$log = sprintf('%s sslprobe created: %s (%s) [SSLv2:%d,'
					.' SSLv3:%d, TLSv1:%d, TLSv1.1:%d,'
					.' TLSv1.2:%d]', $svc->service_type,
					$probe->host, $probe->ip, $sslv2,
					$sslv3, $tlsv1, $tlsv1_1, $tlsv1_2);
			if($row) {
				$q = 'UPDATE test_sslprobes
					SET entry_type="revision", until=NOW()
					WHERE id=?';
				$st = $m->prepare($q);
				$st->bind_param('i', $row->id);
				if(!$st->execute()) {
					$err = "Sslprobe revision update"
						." ($svc->id, $hostname)"
						." failed: ". $m->error;
					throw new Exception($err);
				}

				$st->close();

				$changes = $this->computeSslprobeChanges(
						$probe, $svc->id,
						$row->json_sha256);

				$log = sprintf('%s sslprobe changed:'
						.' %s (%s) [%s]',
						$svc->service_type,
						$probe->host, $probe->ip,
						implode('. ', $changes));
			}

			$this->logEntry($svc->id, $svc->service_name,
					$log, $jsonId);
		} /* End foreach */
	}

	function computeSslprobeChanges($probe, $svcId, $prevJsonHash) {

		$foundProbe = FALSE;
		$jsonRow = $this->getJsonByHash($svcId, $prevJsonHash);
		foreach(json_decode($jsonRow->json) as $prevProbe) {
			if($prevProbe->ip !== $probe->ip) continue;
			if($prevProbe->port !== $probe->port) continue;
			if($prevProbe->host !== $probe->host) continue;
			$foundProbe = TRUE;
			break;
		}

		if(!$foundProbe)
			return array();

		$prev = array();
		foreach($prevProbe->protocols as $p)
			$prev[$p->name] = $p;

		$cur = array();
		foreach($probe->protocols as $p)
			$cur[$p->name] = $p;

		$names = array_keys($cur);
		$prevArr = array();
		$curArr = array();
		foreach($names as $n) {
			if($cur[$n]->supported) $curArr[] = $n;
			if($prev[$n]->supported) $prevArr[] = $n;
		}

		$changes = array();

		$arr = array();
		$add = array_diff($curArr, $prevArr);
		$del = array_diff($prevArr, $curArr);
		if(count($add))
			$arr[] = 'ENABLED ('. implode('; ', $add) .')';
		if(count($del))
			$arr[] = 'DISABLED ('. implode('; ', $del) .')';
		if(count($arr)) {
			$changes[] = 'Protocols changed:'
					.' '. implode(', ', $arr);
		}

		foreach($names as $n) {
			if(!$cur[$n]->supported) continue;
			if(!$prev[$n]->supported) continue;

			/* Error message */
			$prevErr = $prev[$n]->lastError;
			$curErr = $cur[$n]->lastError;
			if($prevErr !== $curErr) {
				if(!$prevErr) $prevErr = '(null)';
				if(!$curErr) $curErr = '(null)';
				$log = 'Last connection error changed from ('.
					$prevErr .') to ('. $curErr .')';
				$changes[] = $log;
			}

			/* Compression algorithm */
			$prevCA = $prev[$n]->compressionAlgorithm;
			$curCA = $cur[$n]->compressionAlgorithm;
			if($prevCA !== $curCA) {
				$log = 'SSL/TLS compression changed to';
				if($curCA > 0)
					$changes[] = $log .' ENABLED (CRIME)';
				else
					$changes[] = $log .' DISABLED';
			}

			/* Secure renegotation */
			$prevReneg = $curReneg = 0;
			if(isset($prev[$n]->extensions))
			$prevReneg = $prev[$n]->extensions->secureRenegotiation;
			if(isset($cur[$n]->extensions))
			$curReneg = $cur[$n]->extensions->secureRenegotiation;
			if($prevReneg !== $curReneg) {
				$log = 'Secure renegotiation changed to';
				if($curReneg > 0)
					$changes[] = $log .' ENABLED';
				else
					$changes[] = $log .' DISABLED';
			}

			/* Cipher suite preference */
			$prevCSP = $prev[$n]->cipherSuitePreference;
			$curCSP = $cur[$n]->cipherSuitePreference;
			if($prevCSP !== $curCSP) {
				$log = 'Cipher suite preference changed to';
				if($curCSP > 0)
					$changes[] = $log .' ENABLED';
				else
					$changes[] = $log .' DISABLED';
			}

			/* Cipher suite */
			$prevArr = array();
			$curArr = array();
			foreach($prev[$n]->cipherSuites as $cs)
				$prevArr[] = $cs->name;
			foreach($cur[$n]->cipherSuites as $cs)
				$curArr[] = $cs->name;

			$arr = array();
			$add = array_diff($curArr, $prevArr);
			$del = array_diff($prevArr, $curArr);
			if(count($add))
				$arr[] = 'added ('. implode('; ', $add) .')';
			if(count($del))
				$arr[] = 'removed ('. implode('; ', $del) .')';
			if(count($arr))
				$changes[] = $n. ' cipher suites changed:'
						.' '. implode(', ', $arr);

			/* NPN */
			$prevArr = array();
			$curArr = array();
			if(isset($prev[$n]->extensions))
			foreach($prev[$n]->extensions->npn as $npn)
				$prevArr[] = $npn;
			if(isset($cur[$n]->extensions))
			foreach($cur[$n]->extensions->npn as $npn)
				$curArr[] = $npn;

			$arr = array();
			$add = array_diff($curArr, $prevArr);
			$del = array_diff($prevArr, $curArr);
			if(count($add))
				$arr[] = 'added ('. implode(', ', $add) .')';
			if(count($del))
				$arr[] = 'removed ('. implode(', ', $del) .')';
			if(count($arr))
				$changes[] = $n .' NPN protocols changed:'
						.' '. implode(', ', $arr);

			/* Certificates */
			$prevArr = array();
			$curArr = array();
			foreach($prev[$n]->certificates as $pem) {
				$cert = openssl_x509_parse($pem, TRUE);
				$prevArr[] = $cert['name'];
			}
			foreach($cur[$n]->certificates as $pem) {
				$cert = openssl_x509_parse($pem, TRUE);
				$curArr[] = $cert['name'];
			}

			$arr = array();
			$add = array_diff($curArr, $prevArr);
			$del = array_diff($prevArr, $curArr);
			if(count($add))
				$arr[] = 'added ('. implode('; ', $add) .')';
			if(count($del))
				$arr[] = 'removed ('. implode('; ', $del) .')';
			if(count($arr))
				$changes[] = 'Certificates changed:'
						.' '. implode(', ', $arr);
		} /* End foreach */

		return array_unique($changes);
	}

	function getCertByHash($sha256Hash) {
		$m = $this->getMySQLHandle();

		$q = 'SELECT * FROM certs WHERE pem_sha256=?';
		$st = $m->prepare($q);
		$st->bind_param('s', $sha256Hash);
		if(!$st->execute()) {
			$err = "Cert lookup ($sha256Hash)"
				." failed: ". $m->error;
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();

		return $row;
	}

	/**
	 * Add X.509 certificate in PEM format to store
	 * @param $pem X.509 cert PEM-encoded
	 * @return ID from certs table, throws on error
	 */
	function addCert($pem) {
		$m = $this->getMySQLHandle();

		$hash = hash('sha256', $pem);
		$cert = $this->getCertByHash($hash);
		if($cert !== NULL)
			return $cert->id;

		$q = 'INSERT INTO certs
			SET pem_sha256=?, x509=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('ss', $hash, $pem);
		if(!$st->execute()) {
			$err = "Cert add ($hash) failed: ". $m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}

	function addCertHostnameMapping($certId, $svcGrpId, $hostname) {
		$m = $this->getMySQLHandle();

		$q = 'SELECT id FROM test_sslprobe_certs
			WHERE cert_id=? AND svc_group_id=? AND hostname=?';
		$st = $m->prepare($q);
		$st->bind_param('iis', $certId, $svcGrpId, $hostname);
		if(!$st->execute()) {
			$err = "Cert vhost lookup ($certId, $svcGrpIp, $hostname)"
				." failed: ". $m->error;
			throw new Exception($err);
		}

		$r = $st->get_result();
		$row = $r->fetch_object();
		$r->close();
		$st->close();
		if($row !== NULL)
			return $row->id;

		$q = 'INSERT INTO test_sslprobe_certs
			SET cert_id=?, svc_group_id=?, hostname=?, created=NOW()';
		$st = $m->prepare($q);
		$st->bind_param('iis', $certId, $svcGrpId, $hostname);
		if(!$st->execute()) {
			$err = "Cert vhost add ($certId, $svcGrpId, $hostname)"
				." failed: ". $m->error;
			throw new Exception($err);
		}

		$id = $st->insert_id;
		$st->close();

		return $id;
	}
}

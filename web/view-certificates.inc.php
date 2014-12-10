<?php
/**
 * Expects:
 * - uniqueCertChains[] to be defined
 * - x509.inc.php to be included
 */
?>
			<h3>Certifikat</h3>
			<?php
			foreach($uniqueCertChains as $hash => $obj):
			?>
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><?php echo htmlspecialchars(implode(', ', $obj->hosts), ENT_NOQUOTES) ?></h3>
				</div>
				<div class="panel-body">
					<h4>Certifikatkedja</h4>
					<?php
						$arr = array();
						foreach($chain as $pem)
							$arr[] = parsePemEncodedCert($pem);

						$i = 0;
						$n = count($arr);
						foreach($arr as $cert):
							$i++;
					?>
					<ul>
						<li>
							<strong><?php echo htmlspecialchars(implode(', ', $cert->names), ENT_NOQUOTES) ?></strong>
							<?php if(!empty($cert->altNames)): ?>
								(alternativa namn: <?php echo htmlspecialchars(implode(', ', $cert->altNames), ENT_NOQUOTES) ?>)
							<?php endif; ?>

							<?php if(isset($cert->constraints->CA) && $cert->constraints->CA): ?>
							<span class="label label-info"><strong>CA-certifikat</strong></span>
							<?php endif; ?>

							<?php if($cert->selfSigned && isset($cert->constraints->CA) && $cert->constraints->CA): ?>
							<span class="label label-warning"><strong>rotcertifikat (onödigt att skicka med)</strong></span>
							<?php elseif($cert->selfSigned): ?>
							<span class="label label-warning"><strong>självsignerat</strong></span>
							<?php endif; ?>

							<br />
							<?php
							$numDays = dateToDays($cert->validTo);
							if($numDays < 0):
							?>
								<strong>Gick ut för <time datetime="<?php echo htmlspecialchars($cert->validTo) ?>"><?php echo $numDays ?></time> dagar sedan</strong>
							<?php elseif($numDays < 21): ?>
								<strong>Går ut om <time datetime="<?php echo htmlspecialchars($cert->validTo) ?>"><?php echo $numDays ?></time> dagar</strong>
							<?php else: ?>
								Giltigt i <strong><time datetime="<?php echo htmlspecialchars($cert->validTo) ?>"><?php echo $numDays ?></time> dagar till</strong>
							<?php endif; ?>
							,

							signaturalgoritm
							<em><?php echo htmlspecialchars($cert->signatureAlgorithm, ENT_NOQUOTES) ?></em>
							<?php
								switch($cert->signatureAlgorithm) {
								case "md2WithRSAEncryption":
								case "md5WithRSAEncryption":
								case "sha1WithRSAEncryption":
									if($i === 1) {
										/* Weak algorithms are mostly a problem for leaf certs */
										echo '<span class="label label-danger">OSÄKERT</span>';
										break;
									}
									/* Fall through */
								case "sha256WithRSAEncryption":
								case "sha384WithRSAEncryption":
								case "sha512WithRSAEncryption":
								default:
									echo '<span class="label label-success">OK</span>';
									break;
								}
							?>

							<hr style="margin:0"/>
					<?php endforeach; // cert ?>
					<?php for(; $i >= 0; $i--): ?>
						</li>
					</ul>
					<?php endfor; ?>
				</div>
			</div>
			<?php endforeach; // certficate chains ?>

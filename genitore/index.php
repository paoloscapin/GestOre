<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/programmiPubbliciLib.php';

?>

<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>GestOre Genitori</title>
	<?php
	require_once '../common/header-common.php';
	require_once '../common/style.php';
	ruoloRichiesto('genitore', 'segreteria-didattica', 'dirigente');
	?>
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/mobile-home.css">
</head>

<body>
	<?php
	function isMobile()
	{
		return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT']);
	}

	$query = "SELECT COUNT(s.id) AS num_studenti FROM studente s INNER JOIN genitori_studenti gs ON gs.id_studente = s.id INNER JOIN genitori g ON g.id = gs.id_genitore WHERE s.attivo = '1' AND g.id = $__genitore_id";
	$ris = dbGetValue($query);
	if ($ris == 0)
	{
	if (isMobile()) {
		header("location: ../error/error_mobile.php?message=Non hai studenti attivi associati al tuo account.&num_studenti=$ris");
	} else {
		header("location: ../error/error_desktop.php?message=Non hai studenti attivi associati al tuo account.&num_studenti=$ris");
	}
	}
	
	if (isMobile()) {
		require_once '../common/header-genitore-mobile.php';
	} else {
		require_once '../common/header-genitore.php';
	}
	?>
	<!-- Content Section -->
	<div class="container-fluid">
		<?php if (isMobile()): ?>
			<div class="mobile-home">
				<div class="mobile-home-title">GestOre Genitori</div>
				<div class="mobile-home-subtitle">Accesso rapido alle funzioni principali</div>
				<div class="mobile-home-user">
					<?php echo htmlspecialchars($__genitore_nome . ' ' . $__genitore_cognome, ENT_QUOTES, 'UTF-8'); ?>
				</div>

				<?php if (getSettingsValue('config', 'sportelli', false) && getSettingsValue('sportelli', 'visibile_genitori', false)): ?>
					<a href="../genitore/sportello_mobile.php" class="mobile-home-card">
						<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-blackboard"></span></div>
						<div class="mobile-home-card-title">Sportelli</div>
						<div class="mobile-home-card-desc">Consulta gli sportelli disponibili e le prenotazioni</div>
					</a>
				<?php endif; ?>

				<?php if (getSettingsValue('config', 'permessi', false) && getSettingsValue('permessi', 'visibile_genitori', false)): ?>
					<a href="../genitore/permessi_mobile.php" class="mobile-home-card">
						<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-log-out"></span></div>
						<div class="mobile-home-card-title">Permessi di uscita</div>
						<div class="mobile-home-card-desc">Richiedi o consulta i permessi di uscita degli studenti</div>
					</a>
				<?php endif; ?>

				<?php if (getSettingsValue('config', 'carenzeObiettiviMinimi', false) && getSettingsValue('carenzeObiettiviMinimi', 'visibile_genitori', false)): ?>
					<a href="../genitore/carenze_mobile.php" class="mobile-home-card">
						<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-film"></span></div>
						<div class="mobile-home-card-title">Carenze</div>
						<div class="mobile-home-card-desc">Visualizza carenze, recuperi e obiettivi minimi</div>
					</a>
				<?php endif; ?>

				<?php if (programmiPubbliciVisibleForRole('materie', 'genitore')): ?>
					<a href="../genitore/programmi.php" class="mobile-home-card">
						<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-list-alt"></span></div>
						<div class="mobile-home-card-title">Programmi didattica</div>
						<div class="mobile-home-card-desc">Consulta e scarica i programmi didattici dell'anno corrente</div>
					</a>
				<?php endif; ?>

				<?php if (programmiPubbliciVisibleForRole('minimi', 'genitore')): ?>
					<a href="../genitore/programmiMinimi.php" class="mobile-home-card">
						<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-check"></span></div>
						<div class="mobile-home-card-title">Programmi obiettivi minimi</div>
						<div class="mobile-home-card-desc">Consulta e scarica i programmi degli obiettivi minimi</div>
					</a>
				<?php endif; ?>

				<?php if (programmiPubbliciVisibleForRole('svolti', 'genitore')): ?>
					<a href="../genitore/programmiSvolti.php" class="mobile-home-card">
						<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-education"></span></div>
						<div class="mobile-home-card-title">Programmi svolti</div>
						<div class="mobile-home-card-desc">Consulta e scarica i programmi svolti della classe dello studente</div>
					</a>
				<?php endif; ?>

				<?php if (getSettingsValue('config', 'profiloGenitore', false) && getSettingsValue('profiloGenitore', 'visibile_genitori', false)): ?>
					<a href="../genitore/profilo.php" class="mobile-home-card">
						<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-user"></span></div>
						<div class="mobile-home-card-title">Profilo</div>
						<div class="mobile-home-card-desc">Controlla e aggiorna i dati del profilo genitore</div>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</body>

</html>

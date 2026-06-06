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
	<title>GestOre studente</title>
<?php
require_once '../common/header-common.php';
require_once '../common/style.php';
ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');
?>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/mobile-home.css">
</head>

<body >
<?php 
	function isMobile()
	{
		return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT']);
	}

	if (isMobile()) {
		require_once '../common/header-studente-mobile.php';
	} else {
		require_once '../common/header-studente.php';
	}
	?>

<!-- Content Section -->
<div class="container-fluid">
	<?php if (isMobile()): ?>
		<div class="mobile-home">
			<div class="mobile-home-title">GestOre Studenti</div>
			<div class="mobile-home-subtitle">Accesso rapido alle funzioni principali</div>
			<div class="mobile-home-user">
				<?php echo htmlspecialchars($__studente_nome . ' ' . $__studente_cognome, ENT_QUOTES, 'UTF-8'); ?>
			</div>

			<?php if (getSettingsValue('config', 'sportelli', false) && getSettingsValue('sportelli', 'visibile_studenti', false)): ?>
				<a href="../studente/sportello_mobile.php" class="mobile-home-card">
					<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-blackboard"></span></div>
					<div class="mobile-home-card-title">Sportelli</div>
					<div class="mobile-home-card-desc">Prenota sportelli e consulta quelli gia richiesti</div>
				</a>
			<?php endif; ?>

			<?php if (getSettingsValue('config', 'permessi', false) && getSettingsValue('permessi', 'visibile_studenti', false)): ?>
				<a href="../studente/permessi_mobile.php" class="mobile-home-card">
					<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-log-out"></span></div>
					<div class="mobile-home-card-title">Permessi di uscita</div>
					<div class="mobile-home-card-desc">Consulta i permessi richiesti dai genitori</div>
				</a>
			<?php endif; ?>

			<?php if (getSettingsValue('config', 'biglietti', true)): ?>
				<a href="../common/biglietti_prenotazioni.php" class="mobile-home-card">
					<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-barcode"></span></div>
					<div class="mobile-home-card-title">Biglietti</div>
					<div class="mobile-home-card-desc">Prenota i biglietti per gli eventi disponibili</div>
				</a>
			<?php endif; ?>

			<?php if (getSettingsValue('config', 'carenzeObiettiviMinimi', false) && getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false)): ?>
				<a href="../studente/carenze_mobile.php" class="mobile-home-card">
					<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-film"></span></div>
					<div class="mobile-home-card-title">Carenze</div>
					<div class="mobile-home-card-desc">Visualizza carenze, recuperi e obiettivi minimi</div>
				</a>
			<?php endif; ?>

			<?php if (programmiPubbliciVisibleForRole('materie', 'studente')): ?>
				<a href="../studente/programmi.php" class="mobile-home-card">
					<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-list-alt"></span></div>
					<div class="mobile-home-card-title">Programmi didattica</div>
					<div class="mobile-home-card-desc">Consulta e scarica i programmi didattici dell'anno corrente</div>
				</a>
			<?php endif; ?>

			<?php if (programmiPubbliciVisibleForRole('minimi', 'studente')): ?>
				<a href="../studente/programmiMinimi.php" class="mobile-home-card">
					<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-check"></span></div>
					<div class="mobile-home-card-title">Programmi obiettivi minimi</div>
					<div class="mobile-home-card-desc">Consulta e scarica i programmi degli obiettivi minimi</div>
				</a>
			<?php endif; ?>

			<a href="../studente/profilo.php" class="mobile-home-card">
				<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-user"></span></div>
				<div class="mobile-home-card-title">Profilo</div>
				<div class="mobile-home-card-desc">Gestisci le preferenze delle notifiche</div>
			</a>

			<a href="../help/GestOre - Guida Studenti.pdf" target="_blank" class="mobile-home-card">
				<div class="mobile-home-card-icon"><span class="glyphicon glyphicon-question-sign"></span></div>
				<div class="mobile-home-card-title">Guida</div>
				<div class="mobile-home-card-desc">Apri la guida rapida per studenti</div>
			</a>
		</div>
	<?php endif; ?>
</div>

</body>
</html>

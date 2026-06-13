<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
<link rel="stylesheet" href="../css/header-style.css">

<?php
require_once __DIR__ . '/programmiPubbliciLib.php';

$studenteImpersonato = isset($session)
	&& intval($session->get('impersona_attiva') ?? 0) === 1
	&& (string)($session->get('impersona_ruolo') ?? '') === 'studente';
?>

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
		<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav studente-navbar-nav">
			<?php if (getSettingsValue('config', 'sportelli', false) && getSettingsValue('sportelli', 'visibile_studenti', false)): ?>
				<li><a href="../studente/sportello.php" class="nav-btn btn btn-default btn-orange4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli</a></li>
			<?php endif; ?>
			<?php if (getSettingsValue('config', 'permessi', false) && getSettingsValue('permessi', 'visibile_studenti', false)): ?>
				<li><a href="../studente/permessi.php" class="nav-btn btn btn-default btn-yellow4" role="button"><span class="glyphicon glyphicon-log-out"></span>&ensp;Permessi di uscita</a></li>
			<?php endif; ?>
			<?php if (getSettingsValue('config', 'biglietti', true)): ?>
				<li><a href="../common/biglietti_prenotazioni.php" class="nav-btn btn btn-default btn-yellow4" role="button"><span class="glyphicon glyphicon-barcode"></span>&ensp;Biglietti</a></li>
			<?php endif; ?>
			<?php if (getSettingsValue('config', 'carenzeObiettiviMinimi', false) && getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false)): ?>
				<li><a href="../studente/carenze.php" class="nav-btn btn btn-default btn-lightblue4" role="button"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze</a></li>
			<?php endif; ?>
			<?php if (programmiPubbliciVisibleForRole('materie', 'studente')): ?>
				<li><a href="../studente/programmi.php" class="nav-btn btn btn-default btn-teal4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Programmi didattica</a></li>
			<?php endif; ?>
			<?php if (programmiPubbliciVisibleForRole('minimi', 'studente')): ?>
				<li><a href="../studente/programmiMinimi.php" class="nav-btn btn btn-default btn-teal4" role="button"><span class="glyphicon glyphicon-check"></span>&ensp;Programmi obiettivi minimi</a></li>
			<?php endif; ?>
			<?php if (programmiPubbliciVisibleForRole('svolti', 'studente')): ?>
				<li><a href="../studente/programmiSvolti.php" class="nav-btn btn btn-default btn-teal4" role="button"><span class="glyphicon glyphicon-education"></span>&ensp;Programmi svolti</a></li>
			<?php endif; ?>
		</ul>

		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="../studente/profilo.php"><span class="glyphicon glyphicon-user"></span>&ensp;Profilo</a></li>
			<li><a href="../help/GestOre - Guida Studenti.pdf" target="_blank"><span class="glyphicon glyphicon-question-sign"></span>&ensp;Guida</a></li>
			<li><a><span class=""></span>
					<?php if (haRuolo('admin')) echo "(A)" ?>
					<?php echo '[' . $__studente_id . '] ' . $__studente_nome . ' ' . $__studente_cognome ?></a></li>
			<li> 
				<?php
				if ($studenteImpersonato) {
					echo '<a href="../common/logout.php?impersona=stop&close=1"><span class="glyphicon glyphicon-log-out"></span></a>';
				}
				else
				{
					echo '<a href="../common/logout.php?base=studente"><span class="glyphicon glyphicon-log-out"></span></a>';
				}

				?>
			</li>
		</ul>
	</div>
</nav>

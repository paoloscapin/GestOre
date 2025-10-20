<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
<link rel="stylesheet" href="../css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
		<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav">

			<a href="../docente/index.php"
				class="btn btn-default navbar-btn btn-lima4" role="button"  data-toggle="tooltip"
     data-placement="bottom" title="Vedi qui le tue ore da fare"><span
					class="glyphicon glyphicon-time"></span>&ensp;Ore </a>

			<?php if ($__settings->config->corsiDiRecupero): ?>
				<a href="../docente/corsoDiRecupero.php"
					class="btn btn-default navbar-btn btn-lightblue4" role="button"><span
						class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero </a>
			<?php endif; ?>

			<a href="../docente/previste.php"
				class="btn btn-default navbar-btn btn-orange4" role="button" data-toggle="tooltip"
     data-placement="bottom" title="Gestisci qui le tue ore previste ad inizio anno"><span
					class="glyphicon glyphicon-list-alt"></span>&ensp;Previste </a>
			<a href="../docente/attivita.php"
				class="btn btn-default navbar-btn btn-teal4" role="button" data-toggle="tooltip"
     data-placement="bottom" title="Rendiconta qui le tue ore fatte"><span
					class="glyphicon glyphicon-folder-close"></span>&ensp;Fatte </a>

			<?php if ($__settings->config->bonus): ?>
				<a href="../docente/bonus.php"
					class="btn btn-default navbar-btn btn-lima4" role="button" data-toggle="tooltip"
     data-placement="bottom" title="Gestione della valorizzazione docente"><span
						class="glyphicon glyphicon-list-alt"></span>&ensp;Bonus </a>
			<?php endif; ?>

			<?php
			if (getSettingsValue('config', 'corsi', false)) {
				if (getSettingsValue('corsi', 'visibile_docenti', false)) {
					echo '
			<div class="btn-group">
			<a href="../didattica/corsi.php" class="btn btn-default navbar-btn btn-yellow" role="button"  data-toggle="tooltip"
     data-placement="bottom" title="Gestisci i tuoi corsi per le carenze e relativi esami, oppure corsi svolti durante l\'anno"><span class="glyphicon glyphicon-th-list"></span>&ensp;I miei Corsi </a>
			</div>
			';
				}
			}
			?>

			<?php if (getSettingsValue('programmiMinimi', 'visibile_docenti', false)): ?>
				<div class="btn-group">
					<a href="../didattica/programmaMinimi.php" class="btn btn-default navbar-btn btn-purple" role="button" data-toggle="tooltip"
     data-placement="bottom"  title="Visualizza gli obiettivi minimi delle materie definiti in dipartimento"><span class="glyphicon glyphicon-th-list"></span>&ensp;Ob.Minimi </a>
				</div>
			<?php endif; ?>
			<?php if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)): ?>
				<div class="btn-group">
					<a href="../didattica/programmaMaterie.php"
						class="btn btn-default navbar-btn btn-orange4" role="button" data-toggle="tooltip"
    						 data-placement="bottom" title="Visualizza i programmi delle materie definiti in dipartimento"><span
							class="glyphicon glyphicon-th-large"></span>&ensp;Programmi </a>
				</div>
			<?php endif; ?>
			<?php if (getSettingsValue('programmiIniziali', 'visibile_docenti', false)) : ?>
				<div class="btn-group">
					<a href="../didattica/programmiIniziali.php" class="btn btn-default navbar-btn btn-yellow" role="button" data-toggle="tooltip"
     data-placement="bottom" title="Visualizza ed inserisci i programmi iniziali nei propri corsi"><span class="glyphicon glyphicon-th-list"></span>&ensp;Progr.Iniziali </a>
				</div>
			<?php endif; ?>
			<?php if (getSettingsValue('programmiSvolti', 'visibile_docenti', false)) : ?>
				<div class="btn-group">
					<a href="../didattica/programmiSvolti.php" class="btn btn-default navbar-btn btn-lightblue4" role="button" data-toggle="tooltip"
     data-placement="bottom" title="Visualizza ed inserisci i programmi svolti nei propri corsi"><span class="glyphicon glyphicon-th-list"></span>&ensp;Progr.Svolti </a>
				</div>
			<?php endif; ?>

			<?php if ((getSettingsValue('config', 'carenzeObiettiviMinimi', false)) && (getSettingsValue('carenzeObiettiviMinimi', 'visibile_docenti', false))) : ?>
				<div class="btn-group">
					<a href="../didattica/carenzeMinimi.php" class="btn btn-default navbar-btn btn-beige" role="button" data-toggle="tooltip"
     data-placement="bottom" title="Visualizza e gestisci le carenze di tua competenza a fine anno"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze </a>
				</div>
			<?php endif; ?>


			<?php
			if ($__utente_ruolo == 'docente') {
				require_once '../common/connect.php';
				$num = dbGetValue("SELECT COUNT(id) FROM gruppo WHERE gruppo.dipartimento = false AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id AND gruppo.responsabile_docente_id = $__docente_id;");
				if ($num > 0) {
					echo '<a href="../docente/gruppo.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-user"></span>&ensp;Gruppi </a>';
				}
			}
			?>
			<!--<a href="../docente/index.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-time"></span>&ensp;80 Ore</a> -->
			<?php if (getSettingsValue('config', 'uscite', false)): ?>
			<a href="../docente/viaggio.php"
				class="btn btn-default navbar-btn btn-deeporange4" role="button"><span
					class="glyphicon glyphicon-picture"></span>&ensp;Uscite</a>
			<?php endif; ?>
			<?php if (getSettingsValue('config', 'sportelli', false)): ?>
				<div class="btn-group">
					<a href="../docente/sportello.php"
						class="btn btn-default navbar-btn btn-orange4" role="button"  data-toggle="tooltip"
     data-placement="bottom" title="Gestisci i tuoi sportelli didattici"><span
							class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli </a>
					<button type="button" class="btn btn-default navbar-btn btn-orange4 dropdown-toggle"
						data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<span class="caret"></span>
						<span class="sr-only">Toggle Dropdown</span>
					</button>
					<ul class="dropdown-menu btn-orange4">
						<li><a href="../segreteria/sportelloReportEffettuati.php">Report
								Sportelli Effettuati</a></li>
					</ul>
				</div>
			<?php endif; ?>
			<?php if (getSettingsValue('config', 'pianiDiLavoro', false)): ?>
				<div class="btn-group">
					<a href="../docente/pianoDiLavoro.php"
						class="btn btn-default navbar-btn btn-lima4" role="button"><span
							class="glyphicon glyphicon-th-large"></span>&ensp;Piani di Lavoro </a>
					<?php if (getSettingsValue('config', 'carenze', false)): ?>
						<button type="button" class="btn btn-default navbar-btn btn-lima4 dropdown-toggle"
							data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							<span class="caret"></span>
							<span class="sr-only">Toggle Dropdown</span>
						</button>
						<ul class="dropdown-menu btn-lima4">
							<li><a href="../docente/carenza.php">Lettere Carenze</a></li>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if (getSettingsValue('config', 'pianiDiLavoroEstesi', false)): ?>
				<?php if (getSettingsValue('pianiDiLavoroEstesi', 'visibile_docente', false)): ?>
					<div class="btn-group">
						<a href="../docente/pdl.php"
							class="btn btn-default navbar-btn btn-lima4" role="button"><span
								class="glyphicon glyphicon-th-large"></span>&ensp;Piani di Lavoro </a>
					</div>
				<?php endif; ?>
			<?php endif; ?>

		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="<?php echo $__settings->local->helpLinkDocente; ?>" target="_blank"><span
						class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span>
					<?php if (haRuolo('admin'))
						echo "(A)" ?>
					<?php echo $__docente_nome . ' ' . $__docente_cognome ?></a></li>
			<li>
				<?php
					echo '<a href="../common/logout.php?base=docente"><span class="glyphicon glyphicon-log-out"></span></a>';
				?>
			</li>
		</ul>
	</div>
</nav>
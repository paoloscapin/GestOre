<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
<link rel="stylesheet" href="../css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
		<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav admin-navbar-nav">
			<li><a href="../orario/orario.php" class="btn btn-default nav-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-time"></span>&ensp;Orario </a></li>

			<?php if (haRuolo('segreteria-didattica') || haRuolo('personale-ata') || haRuolo('admin')) : ?>
				<li class="dropdown">
					<a href="#" class="btn btn-default btn-lima4 nav-btn dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<span class="glyphicon glyphicon-briefcase"></span>&ensp;Segreteria <span class="caret"></span>
					</a>
					<ul class="dropdown-menu">
						<li><a href="../didattica/studente.php"><span class="glyphicon glyphicon-education"></span>&ensp;Studenti</a></li>
						<li><a href="../didattica/genitore.php"><span class="glyphicon glyphicon-user"></span>&ensp;Genitori</a></li>
						<?php if (getSettingsValue('config', 'permessi', false)) : ?>
							<li><a href="../didattica/permessi.php"><span class="glyphicon glyphicon-time"></span>&ensp;Permessi</a></li>
						<?php endif; ?>
						<li><a href="../didattica/creditiFormativi.php"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Crediti formativi</a></li>
					</ul>
				</li>

				<li class="dropdown">
					<a href="#" class="btn btn-default btn-yellow4 nav-btn dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<span class="glyphicon glyphicon-th-list"></span>&ensp;Attività <span class="caret"></span>
					</a>
					<ul class="dropdown-menu">
						<?php if (getSettingsValue('config', 'sportelli', false)) : ?>
							<li><a href="../didattica/sportello.php"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli</a></li>
							<li><a href="../didattica/reportSportelli.php"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Report Sportelli</a></li>
							<li role="separator" class="divider"></li>
						<?php endif; ?>
						<?php if (getSettingsValue('config', 'corsi', false)) : ?>
							<li><a href="../didattica/corsi.php"><span class="glyphicon glyphicon-th-list"></span>&ensp;Corsi</a></li>
						<?php endif; ?>
						<?php if ($__settings->config->corsiDiRecupero) : ?>
							<li><a href="../docente/corsoDiRecuperoVoti.php"><span class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero</a></li>
						<?php endif; ?>
						<?php if (getSettingsValue('config', 'carenzeObiettiviMinimi', false)) : ?>
							<li><a href="../didattica/carenzeMinimi.php"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze</a></li>
						<?php endif; ?>
						<li><a href="../didattica/geometri.php"><span class="glyphicon glyphicon-education"></span>&ensp;Esami CAT</a></li>
					</ul>
				</li>
			<?php endif; ?>

			<?php
			$showProgrammiMenu =
				getSettingsValue('config', 'pianiDiLavoro', false) ||
				getSettingsValue('config', 'pianiDiLavoroEstesi', false) ||
				getSettingsValue('config', 'programmaMaterie', false) ||
				getSettingsValue('config', 'programmiMinimi', false) ||
				getSettingsValue('config', 'programmiIniziali', false) ||
				getSettingsValue('config', 'programmiSvolti', false);
			?>
			<?php if ($showProgrammiMenu) : ?>
				<li class="dropdown">
					<a href="#" class="btn btn-default btn-lightblue4 nav-btn dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<span class="glyphicon glyphicon-folder-open"></span>&ensp;Programmi <span class="caret"></span>
					</a>
					<ul class="dropdown-menu">
						<?php if (getSettingsValue('config', 'pianiDiLavoro', false)) : ?>
							<li><a href="../docente/pianoDiLavoro.php"><span class="glyphicon glyphicon-th-large"></span>&ensp;Piani di Lavoro</a></li>
						<?php endif; ?>
						<?php if (getSettingsValue('config', 'pianiDiLavoroEstesi', false)) : ?>
							<li><a href="../docente/pdl.php"><span class="glyphicon glyphicon-th-large"></span>&ensp;Piani di Lavoro Estesi</a></li>
						<?php endif; ?>
						<?php if (getSettingsValue('config', 'programmaMaterie', false)) : ?>
							<li><a href="../didattica/programmaMaterie.php"><span class="glyphicon glyphicon-th-large"></span>&ensp;Programmi Materie</a></li>
						<?php endif; ?>
						<?php if (getSettingsValue('config', 'programmiIniziali', false)) : ?>
							<li><a href="../didattica/programmiIniziali.php"><span class="glyphicon glyphicon-th-list"></span>&ensp;Programmi Iniziali</a></li>
						<?php endif; ?>
						<?php if (getSettingsValue('config', 'programmiSvolti', false)) : ?>
							<li><a href="../didattica/programmiSvolti.php"><span class="glyphicon glyphicon-th-list"></span>&ensp;Programmi Svolti</a></li>
							<?php if (haRuolo('segreteria-didattica') || haRuolo('dirigente') || haRuolo('admin')) : ?>
								<li><a href="../didattica/programmiSvoltiCopertine.php"><span class="glyphicon glyphicon-folder-open"></span>&ensp;Gestione Copertine</a></li>
							<?php endif; ?>
						<?php endif; ?>
						<?php if (getSettingsValue('config', 'programmiMinimi', false)) : ?>
							<li><a href="../didattica/programmaMinimi.php"><span class="glyphicon glyphicon-th-list"></span>&ensp;Obiettivi Minimi</a></li>
						<?php endif; ?>
					</ul>
				</li>
			<?php endif; ?>

			<?php if (haRuolo('segreteria-didattica') || haRuolo('admin')) : ?>
				<li class="dropdown">
					<a href="#" class="btn btn-default btn-lightblue4 nav-btn dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<span class="glyphicon glyphicon-transfer"></span>&ensp;MasterCom <span class="caret"></span>
					</a>
					<ul class="dropdown-menu mastercom-dropdown-menu">
						<li><a href="../admin/mastercom.php"><span class="glyphicon glyphicon-dashboard"></span>&ensp;Dashboard</a></li>
						<li role="separator" class="divider"></li>
						<li class="dropdown-header">Anagrafiche e collegamenti</li>
						<li><a href="../admin/mastercom_students.php"><span class="glyphicon glyphicon-education"></span>&ensp;Studenti</a></li>
						<li><a href="../admin/classi.php"><span class="glyphicon glyphicon-th-large"></span>&ensp;Classi GestOre</a></li>
						<li><a href="../admin/dipartimenti.php"><span class="glyphicon glyphicon-list"></span>&ensp;Dipartimenti</a></li>
						<li><a href="../admin/mastercom_parents.php"><span class="glyphicon glyphicon-user"></span>&ensp;Genitori</a></li>
						<li><a href="../admin/mastercom_teachers.php"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Docenti</a></li>
						<li><a href="../admin/mastercom_classes.php"><span class="glyphicon glyphicon-th-large"></span>&ensp;Classi</a></li>
						<li><a href="../admin/mastercom_docente_insegna_sync.php"><span class="glyphicon glyphicon-link"></span>&ensp;Docenti - classi/materie</a></li>
						<li role="separator" class="divider"></li>
						<li class="dropdown-header">Rilevazioni</li>
						<li><a href="../admin/mastercom_teacher_snapshot.php"><span class="glyphicon glyphicon-eye-open"></span>&ensp;Docenti in classe adesso</a></li>
						<li><a href="../admin/mastercom_presence.php"><span class="glyphicon glyphicon-ok-circle"></span>&ensp;Presenze studenti adesso</a></li>
						<li role="separator" class="divider"></li>
						<li class="dropdown-header">Agenda, eventi e voti</li>
						<li><a href="../admin/mastercom_calendar.php"><span class="glyphicon glyphicon-calendar"></span>&ensp;Agenda classe</a></li>
						<li><a href="../didattica/stampaTag.php"><span class="glyphicon glyphicon-tags"></span>&ensp;Stampa TAG</a></li>
						<li><a href="../admin/mastercom_events.php"><span class="glyphicon glyphicon-list"></span>&ensp;Eventi</a></li>
						<li><a href="../admin/mastercom_event_create.php"><span class="glyphicon glyphicon-plus"></span>&ensp;Nuovo evento</a></li>
						<li><a href="../admin/mastercom_grades.php"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Voti</a></li>
						<li><a href="../admin/mastercom_grade_insert.php"><span class="glyphicon glyphicon-pencil"></span>&ensp;Inserisci voti</a></li>
						<li><a href="../admin/mastercom_debts.php"><span class="glyphicon glyphicon-alert"></span>&ensp;Carenze</a></li>
						<li role="separator" class="divider"></li>
						<li class="dropdown-header">Registri speciali</li>
						<li><a href="../admin/mastercom_noirc.php"><span class="glyphicon glyphicon-book"></span>&ensp;NO IRC - Configurazione</a></li>
						<li><a href="../admin/mastercom_noirc_registro.php"><span class="glyphicon glyphicon-check"></span>&ensp;NO IRC - Registro</a></li>
						<li><a href="../admin/mastercom_noirc_assignments.php"><span class="glyphicon glyphicon-user"></span>&ensp;NO IRC - Docenti</a></li>
						<li><a href="../admin/mastercom_noirc_rooms.php"><span class="glyphicon glyphicon-th-large"></span>&ensp;NO IRC - Aule</a></li>
						<li><a href="../admin/mastercom_l2.php"><span class="glyphicon glyphicon-education"></span>&ensp;L2 - Configurazione</a></li>
						<li><a href="../admin/mastercom_l2_registro.php"><span class="glyphicon glyphicon-check"></span>&ensp;L2 - Registro</a></li>
					</ul>
				</li>
			<?php endif; ?>

			<?php
			$showCarenzeStandalone = !haRuolo('segreteria-didattica') && !haRuolo('personale-ata') && !haRuolo('admin');
			if ($showCarenzeStandalone && haRuolo('docente')) {
				if ((getSettingsValue('config', 'carenzeObiettiviMinimi', false)) && (getSettingsValue('carenzeObiettiviMinimi', 'visibile_docenti', false))) {
					echo '<li><a href="../didattica/carenzeMinimi.php" class="btn btn-default nav-btn btn-beige" role="button"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze </a></li>';
				}
			} elseif ($showCarenzeStandalone && haRuolo('studente')) {
				if ((getSettingsValue('config', 'carenzeObiettiviMinimi', false)) && (getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false))) {
					echo '<li><a href="../didattica/carenzeMinimi.php" class="btn btn-default nav-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-th-list"></span>&ensp;Carenze </a></li>';
				}
			}
			?>

		</ul>

		<ul class="nav navbar-nav navbar-right top-navbar-nav admin-navbar-nav-right">
			<li><a href="<?php echo $__settings->local->helpLinkDidattica; ?>" target="_blank"><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span>
					<?php if (haRuolo('admin')) echo "(A)" ?>
					<?php echo $__utente_nome . ' ' . $__utente_cognome ?></a></li>
			<li>
				<?php
				if (haRuolo('admin')) {
					echo '<a href="../common/logout.php?base=admin"><span class="glyphicon glyphicon-log-out"></span></a>';
				} else {
					echo '<a href="../common/logout.php?base=didattica"><span class="glyphicon glyphicon-log-out"></span></a>';
				}
				?>
			</li>
		</ul>
	</div>
</nav>

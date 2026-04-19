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
			<?php if (getSettingsValue('config', 'sportelli', false)) : ?>
				<li><a href="../didattica/sportello.php" class="btn btn-default nav-btn btn-orange4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli </a></li>
				<li><a href="../didattica/reportSportelli.php" class="btn btn-default nav-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Report Sportelli </a></li>
			<?php endif; ?>

			<li><a href="../didattica/studente.php" class="btn btn-default nav-btn btn-lima4" role="button"><span class="glyphicon glyphicon-pawn"></span>&ensp;Studenti </a></li>
			<li><a href="../didattica/genitore.php" class="btn btn-default nav-btn btn-purple" role="button"><span class="glyphicon glyphicon-pawn"></span>&ensp;Genitori </a></li>

			<?php if ($__settings->config->corsiDiRecupero) : ?>
				<li><a href="../docente/corsoDiRecuperoVoti.php" class="btn btn-default nav-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero </a></li>
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
						<?php endif; ?>
						<?php if (getSettingsValue('config', 'programmiMinimi', false)) : ?>
							<li><a href="../didattica/programmaMinimi.php"><span class="glyphicon glyphicon-th-list"></span>&ensp;Obiettivi Minimi</a></li>
						<?php endif; ?>
					</ul>
				</li>
			<?php endif; ?>

			<?php
			if (haRuolo('segreteria-didattica')) {
				if (getSettingsValue('config', 'corsi', false)) {
					echo '<li><a href="../didattica/corsi.php" class="btn btn-default nav-btn btn-yellow" role="button"><span class="glyphicon glyphicon-th-list"></span>&ensp;Corsi </a></li>';
				}

				if (getSettingsValue('config', 'carenzeObiettiviMinimi', false)) {
					echo '<li><a href="../didattica/carenzeMinimi.php" class="btn btn-default nav-btn btn-beige" role="button"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze </a></li>';
				}

				if (getSettingsValue('config', 'permessi', false)) {
					echo '<li><a href="../didattica/permessi.php" class="btn btn-default nav-btn btn-lima4" role="button"><span class="glyphicon glyphicon-time"></span>&ensp;Permessi </a></li>';
				}
			} elseif (haRuolo('docente')) {
				if ((getSettingsValue('config', 'carenzeObiettiviMinimi', false)) && (getSettingsValue('carenzeObiettiviMinimi', 'visibile_docenti', false))) {
					echo '<li><a href="../didattica/carenzeMinimi.php" class="btn btn-default nav-btn btn-beige" role="button"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze </a></li>';
				}
			} elseif (haRuolo('studente')) {
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

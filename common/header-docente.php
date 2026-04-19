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
$docenteHeaderNome = '';
$docenteHeaderCognome = '';

if (!empty($docente_view_nome) || !empty($docente_view_cognome)) {
	$docenteHeaderNome = (string)$docente_view_nome;
	$docenteHeaderCognome = (string)$docente_view_cognome;
} elseif (!empty($__docente_nome) || !empty($__docente_cognome)) {
	$docenteHeaderNome = (string)$__docente_nome;
	$docenteHeaderCognome = (string)$__docente_cognome;
} else {
	$docenteHeaderNome = (string)$__utente_nome;
	$docenteHeaderCognome = (string)$__utente_cognome;
}
?>

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
		<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav docente-navbar-nav">
			<li><a href="../docente/index.php"
				class="btn btn-default btn-lima4 nav-btn" role="button" data-toggle="tooltip"
				data-placement="bottom" title="Vedi qui le tue ore da fare"><span
					class="glyphicon glyphicon-time"></span>&ensp;Ore</a></li>
			<li><a href="../docente/previste.php"
				class="btn btn-default btn-orange4 nav-btn" role="button" data-toggle="tooltip"
				data-placement="bottom" title="Gestisci qui le tue ore previste ad inizio anno"><span
					class="glyphicon glyphicon-list-alt"></span>&ensp;Previste</a></li>
			<li><a href="../docente/attivita.php"
				class="btn btn-default btn-teal4 nav-btn" role="button" data-toggle="tooltip"
				data-placement="bottom" title="Rendiconta qui le tue ore fatte"><span
					class="glyphicon glyphicon-folder-close"></span>&ensp;Fatte</a></li>
			<li><a href="../common/biglietti_prenotazioni.php"
				class="btn btn-default btn-yellow4 nav-btn" role="button" data-toggle="tooltip"
				data-placement="bottom" title="Prenota i biglietti degli eventi aperti"><span
					class="glyphicon glyphicon-barcode"></span>&ensp;Biglietti</a></li>
			<?php if ($__settings->config->bonus): ?>
				<li><a href="../docente/bonus.php"
					class="btn btn-default btn-lima4 nav-btn" role="button" data-toggle="tooltip"
					data-placement="bottom" title="Gestione della valorizzazione docente"><span
						class="glyphicon glyphicon-list-alt"></span>&ensp;Bonus</a></li>
			<?php endif; ?>
			<?php if (getSettingsValue('config', 'sportelli', false)): ?>
				<li class="dropdown">
					<a href="#" class="btn btn-default btn-orange4 nav-btn dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli <span class="caret"></span>
					</a>
					<ul class="dropdown-menu">
						<li><a href="../docente/sportello.php"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Apri sportelli</a></li>
						<li><a href="../segreteria/sportelloReportEffettuati.php"><span class="glyphicon glyphicon-stats"></span>&ensp;Report sportelli</a></li>
					</ul>
				</li>
			<?php endif; ?>
			<li><a href="../orario/orario.php"
				class="btn btn-default btn-lightblue4 nav-btn" role="button" data-toggle="tooltip"
				data-placement="bottom" title="Orario docenti e classi ed eventi"><span
					class="glyphicon glyphicon-time"></span>&ensp;Orario</a></li>

			<li class="dropdown">
				<a href="#" class="btn btn-default btn-orange4 nav-btn dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<span class="glyphicon glyphicon-book"></span>&ensp;Didattica <span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<?php if (getSettingsValue('config', 'corsi', false) && getSettingsValue('corsi', 'visibile_docenti', false)): ?>
						<li><a href="../didattica/corsi.php"><span class="glyphicon glyphicon-th-list"></span>&ensp;I miei corsi</a></li>
					<?php endif; ?>
					<?php if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)): ?>
						<li><a href="../didattica/programmaMaterie.php"><span class="glyphicon glyphicon-th-large"></span>&ensp;Programmi materie</a></li>
					<?php endif; ?>
					<?php if (getSettingsValue('programmiIniziali', 'visibile_docenti', false)) : ?>
						<li><a href="../didattica/programmiIniziali.php"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Programmi iniziali</a></li>
					<?php endif; ?>
					<?php if (getSettingsValue('programmiSvolti', 'visibile_docenti', false)) : ?>
						<li><a href="../didattica/programmiSvolti.php"><span class="glyphicon glyphicon-list"></span>&ensp;Programmi svolti</a></li>
					<?php endif; ?>
					<?php if (getSettingsValue('programmiMinimi', 'visibile_docenti', false)): ?>
						<li><a href="../didattica/programmaMinimi.php"><span class="glyphicon glyphicon-check"></span>&ensp;Obiettivi minimi</a></li>
					<?php endif; ?>
					<?php if ((getSettingsValue('config', 'carenzeObiettiviMinimi', false)) && (getSettingsValue('carenzeObiettiviMinimi', 'visibile_docenti', false))) : ?>
						<li><a href="../didattica/carenzeMinimi.php"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze</a></li>
					<?php endif; ?>
				</ul>
			</li>

			<?php if ($__settings->config->corsiDiRecupero): ?>
				<li><a href="../docente/corsoDiRecupero.php"
					class="btn btn-default btn-lightblue4 nav-btn" role="button"><span
						class="glyphicon glyphicon-repeat"></span>&ensp;Recupero</a></li>
			<?php endif; ?>
			<?php
			if ($__utente_ruolo == 'docente') {
				require_once '../common/connect.php';
				$num = dbGetValue("SELECT COUNT(id) FROM gruppo WHERE gruppo.dipartimento = false AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id AND gruppo.responsabile_docente_id = $__docente_id;");
				if ($num > 0) {
					echo '<li><a href="../docente/gruppo.php" class="btn btn-default btn-lightblue4 nav-btn" role="button"><span class="glyphicon glyphicon-user"></span>&ensp;Gruppi</a></li>';
				}
			}
			?>
			<?php if (getSettingsValue('config', 'uscite', false)): ?>
				<li><a href="../docente/viaggio.php"
					class="btn btn-default btn-deeporange4 nav-btn" role="button"><span
						class="glyphicon glyphicon-picture"></span>&ensp;Uscite</a></li>
			<?php endif; ?>
			<?php if (getSettingsValue('config', 'pianiDiLavoro', false)): ?>
				<li class="dropdown">
					<a href="#" class="btn btn-default btn-lima4 nav-btn dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<span class="glyphicon glyphicon-th-large"></span>&ensp;Piani <span class="caret"></span>
					</a>
					<ul class="dropdown-menu">
						<li><a href="../docente/pianoDiLavoro.php"><span class="glyphicon glyphicon-th-large"></span>&ensp;Piani di lavoro</a></li>
						<?php if (getSettingsValue('config', 'carenze', false)): ?>
							<li><a href="../docente/carenza.php"><span class="glyphicon glyphicon-envelope"></span>&ensp;Lettere carenze</a></li>
						<?php endif; ?>
						<?php if (getSettingsValue('config', 'pianiDiLavoroEstesi', false) && getSettingsValue('pianiDiLavoroEstesi', 'visibile_docente', false)): ?>
							<li><a href="../docente/pdl.php"><span class="glyphicon glyphicon-th"></span>&ensp;Piani di lavoro estesi</a></li>
						<?php endif; ?>
					</ul>
				</li>
			<?php elseif (getSettingsValue('config', 'pianiDiLavoroEstesi', false) && getSettingsValue('pianiDiLavoroEstesi', 'visibile_docente', false)): ?>
				<li><a href="../docente/pdl.php"
					class="btn btn-default btn-lima4 nav-btn" role="button"><span
						class="glyphicon glyphicon-th"></span>&ensp;Piani</a></li>
			<?php endif; ?>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="<?php echo $__settings->local->helpLinkDocente; ?>" target="_blank"><span
						class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span>
					<?php if (haRuolo('admin'))
						echo "(A)" ?>
					<?php echo htmlspecialchars(trim($docenteHeaderNome . ' ' . $docenteHeaderCognome), ENT_QUOTES, 'UTF-8'); ?></a></li>
			<li>
				<?php
				if (haRuolo('admin')) {
					echo '<a href="#" onclick="window.close(); return false;"><span class="glyphicon glyphicon-log-out"></span></a>';
				}
				else
				{
					echo '<a href="../common/logout.php?base=docente"><span class="glyphicon glyphicon-log-out"></span></a>';
				}
				?>
			</li>
		</ul>
	</div>
</nav>

<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
	<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav">

			<a href="<?php echo $__application_base_path; ?>/docente/index.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-time"></span>&ensp;Ore </a>

			<?php if($__settings->config->corsiDiRecupero) : ?>
			<a href="<?php echo $__application_base_path; ?>/docente/corsoDiRecupero.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero </a>
			<?php endif; ?>

			<a href="<?php echo $__application_base_path; ?>/docente/previste.php" class="btn btn-default navbar-btn btn-orange4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Previste </a>
			<a href="<?php echo $__application_base_path; ?>/docente/attivita.php" class="btn btn-default navbar-btn btn-teal4" role="button"><span class="glyphicon glyphicon-folder-close"></span>&ensp;Fatte </a>
			
			<?php if($__settings->config->bonus) : ?>
			<a href="<?php echo $__application_base_path; ?>/docente/bonus.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Bonus </a>
			<?php endif; ?>

<?php
require_once '../common/connect.php';
if (empty($__docente_id)) {
	$num = 1;
} else {
	$num = dbGetValue("SELECT COUNT(id) FROM gruppo WHERE gruppo.dipartimento = false AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id AND gruppo.responsabile_docente_id = $__docente_id;");
}
if ($num > 0) {
	echo '<a href="'.$__application_base_path.'/docente/gruppo.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-user"></span>&ensp;Gruppi </a>';
}
?>
<!--			<a href="<?php echo $__application_base_path; ?>/docente/index.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-time"></span>&ensp;80 Ore</a> -->
			<a href="<?php echo $__application_base_path; ?>/docente/viaggio.php" class="btn btn-default navbar-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-picture"></span>&ensp;Uscite</a>
<?php if(getSettingsValue('config','sportelli', false)) : ?>
<div class="btn-group">
	<a href="<?php echo $__application_base_path; ?>/docente/sportello.php" class="btn btn-default navbar-btn btn-orange4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli </a>
	<button type="button" class="btn btn-default navbar-btn btn-orange4 dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <span class="caret"></span>
    <span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu btn-orange4">
    <li><a href="<?php echo $__application_base_path; ?>/segreteria/sportelloReportEffettuati.php">Report Sportelli Effettuati</a></li>
  </ul>
</div>
<?php endif; ?>
<?php if(getSettingsValue('config','pianiDiLavoro', false)) : ?>
	<div class="btn-group">
	<a href="<?php echo $__application_base_path; ?>/docente/pianoDiLavoro.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-th-large"></span>&ensp;Piani di Lavoro </a>
<?php if(getSettingsValue('config','carenze', false)) : ?>
	<button type="button" class="btn btn-default navbar-btn btn-lima4 dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<span class="caret"></span>
		<span class="sr-only">Toggle Dropdown</span>
	</button>
	<ul class="dropdown-menu btn-lima4">
		<li><a href="<?php echo $__application_base_path; ?>/docente/carenza.php">Lettere Carenze</a></li>
	</ul>
<?php endif; ?>
	</div>
<?php endif; ?>
<?php if(getSettingsValue('config','modulisticaDocenti', false)) : ?>
	<a href="<?php echo $__application_base_path; ?>/docente/modulisticaList.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-tag"></span>&ensp;Modulistica </a>
<?php endif; ?>
</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="<?php echo $__settings->local->helpLinkDocente; ?>" target="_blank" ><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span>
			<?php if (haRuolo('admin')) echo "(A)" ?>
			<?php echo $__docente_nome.' '.$__docente_cognome ?></a></li>
			<li>
			<?php
			if (haRuolo('admin')) {
				echo '<a href='.$__application_base_path.'/admin/index.php><span class="glyphicon glyphicon-log-out"></span></a>';
			} else {
				echo '<a href='.$__application_base_path.'/common/logout.php?base=docente><span class="glyphicon glyphicon-log-out"></span></a>';
			}
			?>
			</li>
		</ul>
	</div>
</nav>

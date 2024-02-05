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

			<a href="<?php echo $__application_base_path; ?>/segreteria/docente.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-education"></span>&ensp;Docenti </a>
			<a href="<?php echo $__application_base_path; ?>/segreteria/oreAssegnate.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Assegnate </a>
			<a href="<?php echo $__application_base_path; ?>/segreteria/sostituzione.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-retweet"></span>&ensp;Sostituzioni </a>
			<a href="<?php echo $__application_base_path; ?>/segreteria/gruppoGestione.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-user"></span>&ensp;Gruppi </a>
			<a href="<?php echo $__application_base_path; ?>/segreteria/index.php" class="btn btn-default navbar-btn btn-teal4" role="button"><span class="glyphicon glyphicon-scale"></span>&ensp;Udienze </a>
			<a href="<?php echo $__application_base_path; ?>/segreteria/fuisDocenti.php" class="btn btn-default navbar-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-scale"></span>&ensp;Fuis Totale </a>
			<?php if($__settings->config->corsiDiRecupero) : ?>
				<a href="<?php echo $__application_base_path; ?>/segreteria/corsiDiRecupero.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero </a>
			<?php endif; ?>

<div class="btn-group">
<a href="<?php echo $__application_base_path; ?>/segreteria/viaggio.php" class="btn btn-default navbar-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-picture"></span>&ensp;Uscite </a>
  <button type="button" class="btn btn-default navbar-btn btn-deeporange4 dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <span class="caret"></span>
    <span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu btn-deeporange4">
    <li><a href="<?php echo $__application_base_path; ?>/segreteria/viaggioDiaria.php">Diaria</a></li>
  </ul>
</div>
<?php if(getSettingsValue('config','sportelli', false)) : ?>
	<div class="btn-group">
<a href="<?php echo $__application_base_path; ?>/segreteria/sportello.php" class="btn btn-default navbar-btn btn-orange4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli </a>
  <button type="button" class="btn btn-default navbar-btn btn-orange4 dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <span class="caret"></span>
    <span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu btn-orange4">
    <li><a href="<?php echo $__application_base_path; ?>/segreteria/sportelloReportEffettuati.php">Report Sportelli Effettuati</a></li>
  </ul>
</div>
<?php endif; ?>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="<?php echo $__settings->local->helpLinkSegreteria; ?>" target="_blank" ><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span><?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li><?php echo '<a href='.$__application_base_path.'/common/logout.php?base=segreteria><span class="glyphicon glyphicon-log-out"></span></a>'; ?></li>
		</ul>
	</div>
</nav>

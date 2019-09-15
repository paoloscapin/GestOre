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
			<li class="active"><a href="<?php echo $__application_base_path; ?>/dirigente/index.php"><span class="glyphicon glyphicon-home"></span> Home </a></li>
			<li><a href="#">  </a></li>

			<a href="<?php echo $__application_base_path; ?>/dirigente/selezionaDocente.php" class="btn btn-default navbar-btn btn-primary" role="button"><span class="glyphicon glyphicon-education"></span>&ensp;Docente </a>
			<a href="<?php echo $__application_base_path; ?>/dirigente/quadroOreDovute.php" class="btn btn-default navbar-btn btn-success" role="button"><span class="glyphicon glyphicon-dashboard"></span>&ensp;Ore Dovute </a>
			<a href="<?php echo $__application_base_path; ?>/dirigente/corsoDiRecuperoReport.php" class="btn btn-default navbar-btn btn-info" role="button"><span class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero </a>
			<a href="<?php echo $__application_base_path; ?>/dirigente/configurazione.php" class="btn btn-default navbar-btn btn-warning" role="button"><span class="glyphicon glyphicon-cog"></span>&ensp;Configura </a>

<div class="btn-group">

<a href="<?php echo $__application_base_path; ?>/dirigente/fuis.php" class="btn btn-default navbar-btn btn-danger" role="button"><span class="glyphicon glyphicon-euro"></span>&ensp;Fuis </a>
  <button type="button" class="btn btn-default navbar-btn btn-danger dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <span class="caret"></span>
    <span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu">
    <li><a href="<?php echo $__application_base_path; ?>/dirigente/fuisViaggi.php">Fuis Viaggi</a></li>
    <li><a href="<?php echo $__application_base_path; ?>/dirigente/fuisAssegnato.php">Fuis Assegnato</a></li>
    <li><a href="<?php echo $__application_base_path; ?>/dirigente/fuisDocenti.php">Fuis Docenti</a></li>
  </ul>
</div>
<?php
// if ($__config->getBonus_rendiconto_aperto() || $__config->getBonus_adesione_aperto()) {
    echo '<a href="'.$__application_base_path.'/dirigente/bonusDocenti.php" class="btn btn-default navbar-btn btn-success" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Bonus </a>';
// }
?>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="<?php echo $__settings->local->helpLinkDirigente; ?>" target="_blank" ><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span><?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li><?php echo '<a href='.$__application_base_path.'/common/logout.php><span class="glyphicon glyphicon-log-out"></span></a>'; ?></li>
		</ul>
	</div>
</nav>

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
			<li class="active"><a href="<?php echo $__application_base_path; ?>/segreteria/index.php"><span class="glyphicon glyphicon-home"></span> Home </a></li>
			<li><a href="#">  </a></li>

			<a href="<?php echo $__application_base_path; ?>/segreteria/docente.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-education"></span>&ensp;Docenti </a>
			<a href="<?php echo $__application_base_path; ?>/segreteria/oreAssegnate.php" class="btn btn-default navbar-btn btn-success" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Assegnate </a>
			<a href="<?php echo $__application_base_path; ?>/segreteria/sostituzione.php" class="btn btn-default navbar-btn btn-danger" role="button"><span class="glyphicon glyphicon-retweet"></span>&ensp;Sostituzioni </a>
			<a href="<?php echo $__application_base_path; ?>/segreteria/index.php" class="btn btn-default navbar-btn btn-warning" role="button"><span class="glyphicon glyphicon-user"></span>&ensp;Udienze </a>
<div class="btn-group">

<a href="<?php echo $__application_base_path; ?>/segreteria/viaggio.php" class="btn btn-default navbar-btn btn-danger" role="button"><span class="glyphicon glyphicon-picture"></span>&ensp;Uscite </a>
  <button type="button" class="btn btn-default navbar-btn btn-danger dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <span class="caret"></span>
    <span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu">
    <li><a href="<?php echo $__application_base_path; ?>/segreteria/viaggioDiaria.php">Diaria</a></li>
  </ul>
</div>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="<?php echo $__settings->local->helpLinkSegreteria; ?>" target="_blank" ><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span><?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li><?php echo '<a href='.$__application_base_path.'/common/logout.php?base=segreteria><span class="glyphicon glyphicon-log-out"></span></a>'; ?></li>
		</ul>
	</div>
</nav>

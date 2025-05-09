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

			<a href="<?php echo $__application_base_path; ?>/dirigente/index.php" class="btn btn-default navbar-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-king"></span>&ensp;Dirigente </a>
			<a href="<?php echo $__application_base_path; ?>/segreteria/index.php" class="btn btn-default navbar-btn btn-teal4" role="button"><span class="glyphicon glyphicon-tower"></span>&ensp;Segreteria </a>
			<a href="<?php echo $__application_base_path; ?>/dirigente/selezionaDocente.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-education"></span>&ensp;Docente </a>
			<a href="<?php echo $__application_base_path; ?>/didattica/index.php" class="btn btn-default navbar-btn btn-orange4" role="button"><span class="glyphicon glyphicon-knight"></span>&ensp;Didattica </a>
			<a href="<?php echo $__application_base_path; ?>/admin/materia.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-compressed"></span>&ensp;Materie </a>
			<a href="<?php echo $__application_base_path; ?>/admin/attivita.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Tipo Attività </a>
			<a href="<?php echo $__application_base_path; ?>/admin/fuisAssegnatoTipo.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Tipo FuisAssegnato </a>
			<a href="<?php echo $__application_base_path; ?>/admin/annoScolastico.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-calendar"></span>&ensp;Anno Scolastico </a>
<?php if(getSettingsValue('config','modulisticaDocenti', false)) : ?>
			<a href="<?php echo $__application_base_path; ?>/modulistica/modulistica.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-tag"></span>&ensp;Modulistica </a>
<?php endif; ?>
			<a href="<?php echo $__application_base_path; ?>/admin/utente.php" class="btn btn-default navbar-btn btn-teal4" role="button"><span class="glyphicon glyphicon-user"></span>&ensp;Utente </a>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
		<li><a href="<?php echo $__application_base_path; ?>/help/GestOre - Guida Studenti.pdf" target="_blank" ><span class="glyphicon glyphicon-bishop"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkDocente; ?>" target="_blank" ><span class="glyphicon glyphicon-education"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkSegreteria; ?>" target="_blank" ><span class="glyphicon glyphicon-tower"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkDirigente; ?>" target="_blank" ><span class="glyphicon glyphicon-king"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkAdmin; ?>" target="_blank" ><span class="glyphicon glyphicon-text-color"></span></a></li>
			<li><a><span class=""></span><?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li><?php echo '<a href='.$__application_base_path.'/common/logout.php?base=segreteria><span class="glyphicon glyphicon-log-out"></span></a>'; ?></li>
		</ul>
	</div>
</nav>

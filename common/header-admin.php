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
	<?php require_once __DIR__ . '/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav admin-navbar-nav">

			<li><a href="<?php echo $__application_base_path; ?>/dirigente/index.php" class="btn btn-default nav-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-king"></span>&ensp;Dirigente </a></li>
			<li><a href="<?php echo $__application_base_path; ?>/segreteria/index.php" class="btn btn-default btn-yellow4 nav-btn" role="button"><span class="glyphicon glyphicon-tower"></span>&ensp;Segr. Docenti </a></li>
			<li><a href="<?php echo $__application_base_path; ?>/segrata/index.php" class="btn btn-default btn-teal4 nav-btn" role="button"><span class="glyphicon glyphicon-tower"></span>&ensp;Segr. ATA </a></li>
			<li><a href="<?php echo $__application_base_path; ?>/dirigente/selezionaDocente.php" class="btn btn-default btn-lightblue4 nav-btn" role="button"><span class="glyphicon glyphicon-education"></span>&ensp;Docente </a></li>
			<li><a href="<?php echo $__application_base_path; ?>/didattica/index.php" class="btn btn-default btn-orange4 nav-btn" role="button"><span class="glyphicon glyphicon-knight"></span>&ensp;Segr. Didattica </a></li>
			<li class="dropdown">
				<a href="#" class="btn btn-default btn-lima4 nav-btn dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<span class="glyphicon glyphicon-cog"></span>&ensp;Admin <span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li><a href="<?php echo $__application_base_path; ?>/admin/materia.php"><span class="glyphicon glyphicon-compressed"></span>&ensp;Materie</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/attivita.php"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Tipo Attivita</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/fuisAssegnatoTipo.php"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Tipo FuisAssegnato</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/annoScolastico.php"><span class="glyphicon glyphicon-calendar"></span>&ensp;Anno Scolastico</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/utente.php"><span class="glyphicon glyphicon-user"></span>&ensp;Utente</a></li>
					<li role="separator" class="divider"></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/biglietti_eventi.php"><span class="glyphicon glyphicon-calendar"></span>&ensp;Eventi Biglietti</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/tickets.php"><span class="glyphicon glyphicon-barcode"></span>&ensp;Assegna Biglietti</a></li>
				</ul>
			</li>
			<li class="dropdown">
				<a href="#" class="btn btn-default btn-lightblue4 nav-btn dropdown-toggle" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<span class="glyphicon glyphicon-transfer"></span>&ensp;MasterCom <span class="caret"></span>
				</a>
				<ul class="dropdown-menu">
					<li><a href="<?php echo $__application_base_path; ?>/admin/mastercom.php"><span class="glyphicon glyphicon-dashboard"></span>&ensp;Dashboard</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/mastercom_students.php"><span class="glyphicon glyphicon-education"></span>&ensp;Studenti</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/mastercom_parents.php"><span class="glyphicon glyphicon-user"></span>&ensp;Genitori</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/mastercom_teachers.php"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Docenti</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/mastercom_classes.php"><span class="glyphicon glyphicon-th-large"></span>&ensp;Classi</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/mastercom_calendar.php"><span class="glyphicon glyphicon-calendar"></span>&ensp;Agenda Classe</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/mastercom_grades.php"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Voti</a></li>
					<li><a href="<?php echo $__application_base_path; ?>/admin/mastercom_presence.php"><span class="glyphicon glyphicon-ok-circle"></span>&ensp;Presence Snapshot</a></li>
				</ul>
			</li>
			<li><a href="<?php echo $__application_base_path; ?>/orario/orario.php" class="btn btn-default btn-lightblue4 nav-btn" role="button"><span class="glyphicon glyphicon-time"></span>&ensp;Orario </a></li>

		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav admin-navbar-nav-right">
		<li><a href="<?php echo $__application_base_path; ?>/help/GestOre - Guida Studenti.pdf" target="_blank" ><span class="glyphicon glyphicon-bishop"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkDocente; ?>" target="_blank" ><span class="glyphicon glyphicon-education"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkSegreteria; ?>" target="_blank" ><span class="glyphicon glyphicon-tower"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkDirigente; ?>" target="_blank" ><span class="glyphicon glyphicon-king"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkAdmin; ?>" target="_blank" ><span class="glyphicon glyphicon-text-color"></span></a></li>
			<li><a><span class=""></span><?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li><a href="<?php echo $__application_base_path . '/common/logout.php?base=admin' ?>"><span class="glyphicon glyphicon-log-out"></span></a></li>
		</ul>
	</div>
</nav>

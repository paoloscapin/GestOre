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

			<li><a href="../dirigente/index.php" class="btn btn-default nav-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-king"></span>&ensp;Dirigente </a></li>
			<li><a href="../segreteria/index.php" class="btn btn-default btn-teal4 nav-btn " role="button"><span class="glyphicon glyphicon-tower"></span>&ensp;Segreteria Didattica </a></li>
			<li><a href="../segrata/index.php" class="btn btn-default btn-teal4 nav-btn" role="button"><span class="glyphicon glyphicon-tower"></span>&ensp;Segreteria ATA </a></li>
			<li><a href="../dirigente/selezionaDocente.php" class="btn btn-default btn-lightblue4 nav-btn" role="button"><span class="glyphicon glyphicon-education"></span>&ensp;Docente </a></li>
			<li><a href="../didattica/index.php" class="btn btn-default btn-orange4 nav-btn" role="button"><span class="glyphicon glyphicon-knight"></span>&ensp;Didattica </a></li>
			<li><a href="../admin/materia.php" class="btn btn-default btn-yellow4 nav-btn" role="button"><span class="glyphicon glyphicon-compressed"></span>&ensp;Materie </a></li>
			<li><a href="../admin/attivita.php" class="btn btn-default btn-lima4 nav-btn" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Tipo Attività </a></li>
			<li><a href="../admin/fuisAssegnatoTipo.php" class="btn btn-default btn-lima4 nav-btn" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Tipo FuisAssegnato </a></li>
			<li><a href="../admin/annoScolastico.php" class="btn btn-default btn-yellow4 nav-btn" role="button"><span class="glyphicon glyphicon-calendar"></span>&ensp;Anno Scolastico </a></li>
			<li><a href="../admin/utente.php" class="btn btn-default btn-teal4 nav-btn" role="button"><span class="glyphicon glyphicon-user"></span>&ensp;Utente </a></li>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
		<li><a href="../help/GestOre - Guida Studenti.pdf" target="_blank" ><span class="glyphicon glyphicon-bishop"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkDocente; ?>" target="_blank" ><span class="glyphicon glyphicon-education"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkSegreteria; ?>" target="_blank" ><span class="glyphicon glyphicon-tower"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkDirigente; ?>" target="_blank" ><span class="glyphicon glyphicon-king"></span></a></li>
		<li><a href="<?php echo $__settings->local->helpLinkAdmin; ?>" target="_blank" ><span class="glyphicon glyphicon-text-color"></span></a></li>
			<li><a><span class=""></span><?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li><a href="<?php echo $__application_base_path . '/common/logout.php?base=admin' ?>"><span class="glyphicon glyphicon-log-out"></span></a></li>
		</ul>
	</div>
</nav>

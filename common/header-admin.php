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
		<div class="navbar-header">
			<a href="<?php echo $__application_base_path; ?>/admin/index.php" class="navbar-brand top-navbar-brand" >
				<img style="height: 44px; margin-top: -10px;" src="<?php echo $__application_base_path; ?>/img/logo.png" alt="Logo">
			</a>
			<a class="navbar-brand top-navbar-brand" href="#"> </a>
		</div>

		<ul class="nav navbar-nav top-navbar-nav">
			<li class="active"><a href="<?php echo $__application_base_path; ?>/admin/index.php"><span class="glyphicon glyphicon-home"></span> Home </a></li>
			<li><a href="#">  </a></li>

			<a href="<?php echo $__application_base_path; ?>/segreteria/index.php" class="btn btn-default navbar-btn btn-teal4" role="button"><span class="glyphicon glyphicon-education"></span>&ensp;Segreteria </a>
			<a href="<?php echo $__application_base_path; ?>/dirigente/index.php" class="btn btn-default navbar-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Dirigente </a>
			<a href="<?php echo $__application_base_path; ?>/admin/materia.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Materie </a>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="/help/GestOre/html/GestOre.html" target="_blank" ><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span><?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li><?php echo '<a href='.$__application_base_path.'/common/logout.php?base=segreteria><span class="glyphicon glyphicon-log-out"></span></a>'; ?></li>
		</ul>
	</div>
</nav>

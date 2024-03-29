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
		<?php if(getSettingsValue('config','sportelli', false)) : ?>
			<a href="<?php echo $__application_base_path; ?>/didattica/sportello.php" class="btn btn-default navbar-btn btn-orange4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli </a>
			<a href="<?php echo $__application_base_path; ?>/didattica/studente.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-pawn"></span>&ensp;Studenti </a>
			<a href="<?php echo $__application_base_path; ?>/didattica/reportSportelli.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Report Sportelli </a>
		<?php endif; ?>
		<?php if($__settings->config->corsiDiRecupero) : ?>
			<a href="<?php echo $__application_base_path; ?>/docente/corsoDiRecuperoVoti.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero </a>
		<?php endif; ?>
		<?php if(getSettingsValue('config','pianiDiLavoro', false)) : ?>
			<div class="btn-group">
			<a href="<?php echo $__application_base_path; ?>/docente/pianoDiLavoro.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-th-large"></span>&ensp;Piani di Lavoro </a>
		<?php endif; ?>
		</ul>

		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="<?php echo $__settings->local->helpLinkDidattica; ?>" target="_blank" ><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span>
			<?php if (haRuolo('admin')) echo "(A)" ?>
			<?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li>
			<?php
			if (haRuolo('admin')) {
				echo '<a href='.$__application_base_path.'/admin/index.php><span class="glyphicon glyphicon-log-out"></span></a>';
			} else {
				echo '<a href='.$__application_base_path.'/common/logout.php?base=studente><span class="glyphicon glyphicon-log-out"></span></a>';
			}
			?>
			</li>
		</ul>
	</div>
</nav>

<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
	<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav">
		<?php if(getSettingsValue('config','sportelli', false)) : ?>
			<a href="<?php echo $__application_base_path; ?>/genitore/sportello.php" class="btn btn-default navbar-btn btn-orange4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli </a>
		<?php endif; ?>
		</ul>
		<ul class="nav navbar-nav top-navbar-nav">
		<?php 
		if((getSettingsValue('config','carenzeObiettiviMinimi', false))&&(getSettingsValue('carenzeObiettiviMinimi','visibile_studenti', false))) :?>
			<a href="<?php echo $__application_base_path; ?>/genitore/carenze.php" class="btn btn-default navbar-btn btn-teal4" role="button"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze </a>
		<?php endif; ?>
		</ul>

		<?php 
		if((getSettingsValue('config','permessi', false))&&(getSettingsValue('permessi','visibile_genitori', false))) :?>
			<a href="<?php echo $__application_base_path; ?>/genitore/permessi.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-log-out"></span>&ensp;Permessi di uscita </a>
		<?php endif; ?>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="<?php echo $__application_base_path; ?>/help/GestOre - Guida Studenti.pdf" target="_blank" ><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span>
			<?php if (haRuolo('admin')) echo "(A)" ?>
			<?php echo $__genitore_nome.' '.$__genitore_cognome ?></a></li>
			<li>
			<?php
			if (haRuolo('admin')) {
				echo '<a href='.$__application_base_path.'/admin/index.php><span class="glyphicon glyphicon-log-out"></span></a>';
			} else {
				echo '<a href='.$__application_base_path.'/common/logout.php?base=genitore><span class="glyphicon glyphicon-log-out"></span></a>';
			}
			?>
			</li>
		</ul>
	</div>
</nav>

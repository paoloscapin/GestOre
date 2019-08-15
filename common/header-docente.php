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
			<a href="<?php echo $__application_base_path; ?>/.." class="navbar-brand top-navbar-brand" >
				<img style="height: 44px; margin-top: -10px;" src="<?php echo $__application_base_path; ?>/img/logo.png" alt="Istituto Martino Martini">
			</a>
			<a class="navbar-brand top-navbar-brand" href="#"> </a>
		</div>

		<ul class="nav navbar-nav top-navbar-nav">
			<li class="active"><a href="<?php echo $__application_base_path; ?>/docente/index.php"><span class="glyphicon glyphicon-home"></span> Home </a></li>
			<li><a href="#">  </a></li>

			<a href="<?php echo $__application_base_path; ?>/docente/corsoDiRecupero.php" class="btn btn-default navbar-btn btn-primary" role="button"><span class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero </a>
			<a href="<?php echo $__application_base_path; ?>/docente/attivita.php" class="btn btn-default navbar-btn btn-info" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Attività </a>
<?php
// if ($__config->getBonus_rendiconto_aperto() || $__config->getBonus_adesione_aperto()) {
    echo '<a href="'.$__application_base_path.'/docente/bonus.php" class="btn btn-default navbar-btn btn-success" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Bonus </a>';
// }
?>
			<a href="<?php echo $__application_base_path; ?>/docente/index.php" class="btn btn-default navbar-btn btn-warning" role="button"><span class="glyphicon glyphicon-time"></span>&ensp;80 Ore</a>
			<a href="<?php echo $__application_base_path; ?>/docente/viaggio.php" class="btn btn-default navbar-btn btn-danger" role="button"><span class="glyphicon glyphicon-picture"></span>&ensp;Uscite</a>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="/help/GestOre/html/GestOre.html" target="_blank" ><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span>
			<?php if (haRuolo('dirigente')) echo "(D)" ?>
			<?php echo $__docente_nome.' '.$__docente_cognome ?></a></li>
			<li>
			<?php
			if (haRuolo('dirigente')) {
				echo '<a href='.$__application_base_path.'/dirigente/selezionaDocente.php><span class="glyphicon glyphicon-log-out"></span></a>';
			} else {
				echo '<a href='.$__application_base_path.'/common/logout.php?base=docente><span class="glyphicon glyphicon-log-out"></span></a>';
			}
			?>
			</li>
		</ul>
	</div>
</nav>

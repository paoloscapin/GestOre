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

			<?php if($__settings->config->corsiDiRecupero) : ?>
				<a href="<?php echo $__application_base_path; ?>/dirigente/corsoDiRecuperoReport.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero </a>
			<?php endif; ?>
			<a href="<?php echo $__application_base_path; ?>/dirigente/configurazione.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-cog"></span>&ensp;Configura </a>

<div class="btn-group">
	<a href="<?php echo $__application_base_path; ?>/dirigente/previsteList.php" class="btn btn-default navbar-btn btn-orange4" role="button"><span class="glyphicon glyphicon-dashboard"></span>&ensp;Previste </a>
	<button type="button" class="btn btn-default navbar-btn btn-orange4 dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<span class="caret"></span>
		<span class="sr-only">Toggle Dropdown</span>
	</button>
	<ul class="dropdown-menu btn-orange4">
		<li><a href="<?php echo $__application_base_path; ?>/dirigente/filtroPreviste.php">Filtro Attività Previste</a></li>
	</ul>
</div>
<div class="btn-group">
	<a href="<?php echo $__application_base_path; ?>/dirigente/fatteList.php" class="btn btn-default navbar-btn btn-teal4" role="button"><span class="glyphicon glyphicon-folder-close"></span>&ensp;Fatte </a>
	<button type="button" class="btn btn-default navbar-btn btn-teal4 dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<span class="caret"></span>
		<span class="sr-only">Toggle Dropdown</span>
	</button>
	<ul class="dropdown-menu btn-teal4">
		<li><a href="<?php echo $__application_base_path; ?>/dirigente/filtroFatte.php">Filtro Attività Fatte</a></li>
		<li><a href="<?php echo $__application_base_path; ?>/dirigente/fatteListOreRimaste.php">Ore da Fare Rimaste</a></li>
	</ul>
</div>

<?php
echo ' <a href="'.$__application_base_path.'/dirigente/fuisAssegnato.php" class="btn btn-default navbar-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-euro"></span>&ensp;Fuis Assegnato </a>';
?>

<?php
// if ($__config->getBonus_rendiconto_aperto() || $__config->getBonus_adesione_aperto()) {
    echo ' <a href="'.$__application_base_path.'/dirigente/bonusDocenti.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Bonus </a>';
// }
?>
<?php if(getSettingsValue('interfaccia','extraMenuDirigente', false)) : ?>
	<a href="<?php echo $__application_base_path; ?>/segreteria/oreAssegnate.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Assegnate </a>
	<div class="btn-group">

	<a href="<?php echo $__application_base_path; ?>/segreteria/viaggio.php" class="btn btn-default navbar-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-picture"></span>&ensp;Uscite </a>
	<button type="button" class="btn btn-default navbar-btn btn-deeporange4 dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<span class="caret"></span>
		<span class="sr-only">Toggle Dropdown</span>
	</button>
	<ul class="dropdown-menu">
		<li><a href="<?php echo $__application_base_path; ?>/segreteria/viaggioDiaria.php">Diaria</a></li>
	</ul>
	</div>

<?php endif; ?>
<?php
echo ' <a href="'.$__application_base_path.'/dirigente/storico.php" class="btn btn-default navbar-btn btn-deeporange4" role="button"><span class="glyphicon glyphicon-list-alt"></span>&ensp;Storico </a>';
?>
<?php if(getSettingsValue('config','pianiDiLavoro', false)) : ?>
	<a href="<?php echo $__application_base_path; ?>/docente/pianoDiLavoro.php" class="btn btn-default navbar-btn btn-lima4" role="button"><span class="glyphicon glyphicon-th-large"></span>&ensp;Piani di Lavoro </a>
<?php endif; ?>
<?php if(getSettingsValue('config','modulisticaDocenti', false)) : ?>
	<a href="<?php echo $__application_base_path; ?>/segreteria/modulisticaRichiestaList.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-tag"></span>&ensp;Modulistica </a>
<?php endif; ?>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="<?php echo $__settings->local->helpLinkDirigente; ?>" target="_blank" ><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span><?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li><?php echo '<a href='.$__application_base_path.'/common/logout.php><span class="glyphicon glyphicon-log-out"></span></a>'; ?></li>
		</ul>
	</div>
</nav>

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

<?php if(getSettingsValue('config','modulisticaDocenti', false)) : ?>
			<a href="<?php echo $__application_base_path; ?>/modulistica/modulistica.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-tag"></span>&ensp;Modulistica </a>
<?php endif; ?>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
		<li><a href="<?php echo $__settings->local->helpLinkDocente; ?>" target="_blank" ><span class="glyphicon glyphicon-education"></span></a></li>
			<li><a><span class=""></span><?php echo $__utente_nome.' '.$__utente_cognome ?></a></li>
			<li><?php echo '<a href='.$__application_base_path.'/common/logout.php?base=segreteria><span class="glyphicon glyphicon-log-out"></span></a>'; ?></li>
		</ul>
	</div>
</nav>

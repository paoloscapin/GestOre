<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
<link rel="stylesheet" href="../css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
		<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav">
			<?php 
			if ((getSettingsValue('config', 'sportelli', false)) && (getSettingsValue('sportelli','visibile_genitori', false)))
				{
				echo '		
				<a href="../genitore/sportello.php" class="btn btn-default navbar-btn btn-orange4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli </a>';
				}
			?>
		</ul>
		<ul class="nav navbar-nav top-navbar-nav">
			<?php
			if ((getSettingsValue('config', 'carenzeObiettiviMinimi', false)) && (getSettingsValue('carenzeObiettiviMinimi', 'visibile_genitori', false))) {
				?>
				<a href="../genitore/carenze.php" class="btn btn-default navbar-btn btn-teal4" role="button"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze </a>
			<?php } ?>
		</ul>

		<?php
		if ((getSettingsValue('config', 'permessi', false)) && (getSettingsValue('permessi', 'visibile_genitori', false)))
			echo '<a href="../genitore/permessi.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-log-out"></span>&ensp;Permessi di uscita </a>';
		?>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a><span class=""></span>
					<?php if (haRuolo('admin')) echo "(A)" ?>
					<?php echo $__genitore_nome . ' ' . $__genitore_cognome ?></a></li>
			<li>
				<?php
				echo '<a href="../common/logout.php?base=genitore"><span class="glyphicon glyphicon-log-out"></span></a>';
				?>
			</li>
		</ul>
	</div>
</nav>
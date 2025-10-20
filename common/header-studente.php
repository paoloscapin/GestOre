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
		<?php 
			if (getSettingsValue('config', 'sportelli', false))
			{
				if (getSettingsValue('sportelli','visibile_studenti', false))
				{
					echo '
				<a href="../studente/sportello.php" class="btn btn-default navbar-btn btn-orange4" role="button"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli </a>';
				}				
			}
		?>
		</ul>
		<ul class="nav navbar-nav top-navbar-nav">
			<?php
			if (getSettingsValue('config', 'carenzeObiettiviMinimi', false) && getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false)) : ?>
				<a href="../studente/carenze.php" class="btn btn-default navbar-btn btn-lightblue4" role="button"><span class="glyphicon glyphicon-film"></span>&ensp;Carenze </a>
			<?php endif; ?>
		</ul>

		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a href="../help/GestOre - Guida Studenti.pdf" target="_blank"><span class="glyphicon glyphicon-question-sign"></span></a></li>
			<li><a><span class=""></span>
					<?php if (haRuolo('admin')) echo "(A)" ?>
					<?php echo '[' . $__studente_id . '] ' . $__studente_nome . ' ' . $__studente_cognome ?></a></li>
			<li> 
				<?php

				echo '<a href="../common/logout.php?base=studente"><span class="glyphicon glyphicon-log-out"></span></a>';

				?>
			</li>
		</ul>
	</div>
</nav>
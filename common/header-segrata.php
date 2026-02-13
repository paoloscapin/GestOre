<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
?>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">

		<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav">

			<!-- PERSONALE ATA -->
			<li>
				<a href="<?php echo $__application_base_path; ?>/segrata/personaleAta.php"
				   class="btn-lightblue4 nav-btn">
					<span class="glyphicon glyphicon-user"></span>&ensp;Personale ATA
				</a>
			</li>

			<!-- PERMESSI ATA (gestione segreteria) -->
			<li>
				<a href="<?php echo $__application_base_path; ?>/segrata/permessi.php"
				   class="btn-teal4 nav-btn">
					<span class="glyphicon glyphicon-folder-open"></span>&ensp;Permessi ATA
				</a>
			</li>

		</ul>

		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li>
				<a href="<?php echo $__settings->local->helpLinkSegreteria; ?>" target="_blank">
					<span class="glyphicon glyphicon-question-sign"></span>
				</a>
			</li>
			<li>
				<a>
					<?php echo $__utente_nome . ' ' . $__utente_cognome; ?>
				</a>
			</li>
			<li>
				<a href="../common/logout.php?base=segrata">
					<span class="glyphicon glyphicon-log-out"></span>
				</a>
			</li>
		</ul>

	</div>
</nav>

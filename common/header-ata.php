<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
?>
<link rel="stylesheet" href="../css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">

		<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav">

			<div class="btn-group">

				<!-- PERMESSI PERSONALI (dipendente ATA – futuro step) -->
				<a href="../ata/permessi.php"
				   class="btn btn-default navbar-btn btn-yellow4"
				   role="button"
				   data-toggle="tooltip"
				   data-placement="bottom"
				   title="Le mie richieste di permesso">
					<span class="glyphicon glyphicon-th-list"></span>&ensp;I miei permessi
				</a>

			</div>
		</ul>

		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li>
				<a>
					<?php if (haRuolo('admin')) echo "(A) "; ?>
					<?php echo $__ata_nome . ' ' . $__ata_cognome; ?>
				</a>
			</li>
			<li>
				<a href="../common/logout.php?base=ata">
					<span class="glyphicon glyphicon-log-out"></span>
				</a>
			</li>
		</ul>

	</div>
</nav>

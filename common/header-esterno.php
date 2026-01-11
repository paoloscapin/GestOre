<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
<link rel="stylesheet" href="../css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
		<?php require_once '../common/header-_logo.php'; ?>
		<ul class="nav navbar-nav top-navbar-nav">
			<div class="btn-group">
			<a href="../didattica/corsi.php" class="btn btn-default navbar-btn btn-yellow" role="button"  data-toggle="tooltip"
     		data-placement="bottom" title="Gestisci i tuoi corsi e relativi esami"><span class="glyphicon glyphicon-th-list"></span>&ensp;I miei Corsi </a>
			</div>
		</ul>
		<ul class="nav navbar-nav navbar-right top-navbar-nav">
			<li><a><span class=""></span>
					<?php if (haRuolo('admin')) echo "(A)" ?>
					<?php echo $__esterno_nome . ' ' . $__esterno_cognome ?></a></li>
			<li>
				<?php
				echo '<a href="../common/logout.php?base=esterno"><span class="glyphicon glyphicon-log-out"></span></a>';
				?>
			</li>
		</ul>
	</div>
</nav>
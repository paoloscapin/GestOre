<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// session_start();
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/connect.php';
?>

<link rel="stylesheet" href="../css/header-style.css">
<link rel="stylesheet" href="../css/releaseversion.css">
<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
	
		<ul class="nav navbar-nav top-navbar-nav">
			<li class="active"><a href="../common/logout.php"><span class="glyphicon glyphicon-home"></span> Home </a></li>
			<li><a href="#">  </a></li>
		</ul>

		<ul class="nav navbar-nav navbar-right top-navbar-nav">
<?php
if (! empty ( $__utente_nome )) {
	echo "<li><a><span class=\"\"></span>echo $__utente_nome.' '.$__utente_cognome </a></li>";
}
?>
			<li><?php echo '<a href="../common/logout.php"><span class="glyphicon glyphicon-log-out"></span></a>'; ?></li>
		</ul>
	</div>
</nav>

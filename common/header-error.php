<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
require_once __DIR__ . '/path.php';
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top top-navbar top-navbar-default">
	<div class="container-fluid">
	<?php require_once '../common/header-_logo.php'; ?>

		<ul class="nav navbar-nav top-navbar-nav">
			<li class="active"><a href="<?php echo $__application_base_path; ?>"><span class="glyphicon glyphicon-home"></span> Home </a></li>
			<li><a href="#">  </a></li>
		</ul>

		<ul class="nav navbar-nav navbar-right top-navbar-nav">
<?php
	if (! empty($__utente_nome)) {
		echo "<li><a><span class=\"\"></span>$__utente_nome $__utente_cognome </a></li>";
	}
	echo '<li><a href='.$__application_base_path.'/common/logout.php><span class="glyphicon glyphicon-log-out"></span></a></li>';
?>
		</ul>
	</div>
</nav>

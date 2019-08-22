<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<!DOCTYPE html>
<html>
<head>
	<title>Segreteria</title>
</head>

<body >
<?php
require_once '../common/checkSession.php';
require_once '../common/header-segreteria.php';
require_once '../common/style.php';
ruoloRichiesto('dirigente','segreteria-docenti');
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/indexScript.js"></script>
</body>
</html>
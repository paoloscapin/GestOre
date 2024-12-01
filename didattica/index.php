<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
?>

<!DOCTYPE html>
<html>
<head>
	<title>GestOre didattica</title>
<?php
require_once '../common/header-common.php';
require_once '../common/style.php';
ruoloRichiesto('segreteria-didattica');
?>
</head>

<body >
<?php require_once '../common/header-didattica.php'; ?>
<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
</div>

</body>
</html>
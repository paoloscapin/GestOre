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
	<title>Segreteria ATA</title>
<?php
require_once '../common/style.php';
require_once '../common/header-common.php';
ruoloRichiesto('dirigente','segreteria-ata');
?>
</head>

<body >
<?php require_once '../common/header-segrata.php'; ?>
<!-- Content Section -->
<div class="container-fluid">
</div>

</body>
</html>
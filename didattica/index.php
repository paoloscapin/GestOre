<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
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
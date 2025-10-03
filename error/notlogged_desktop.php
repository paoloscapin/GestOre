<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Util.php';
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';

?>

<!DOCTYPE html>
<html>
<head>
	<title>Non Collegato</title>
</head>

<body >
<?php
	require_once '../common/header-error-min.php';
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-success">
<div class="panel-heading">Non collegato</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
		<h3>Non hai fatto login: torna alla pagina principale</h3>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
</div>

<!-- Bootstrap, jquery etc (css + js) -->
<?php
	require_once '../common/style.php';
?>

<!-- Custom JS file -->
</body>
</html>
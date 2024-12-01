<?php
/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');
?>
<!DOCTYPE html>
<html>
<head>
	<title>Dirigente</title>
<?php
	require_once '../common/header-common.php';
	require_once '../common/style.php';
?>
</head>
<body >
<?php
	require_once '../common/header-dirigente.php';
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-success">
<div class="panel-heading">Dirigente</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
</div>

</body>
</html>
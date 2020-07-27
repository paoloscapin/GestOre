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
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';
ruoloRichiesto('studente','dirigente');
?>

<title>Sportelli</title>

<!-- bootbox notificator -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
</head>

<body >
<?php
require_once '../common/header-studente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-orange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-object-align-horizontal"></span>&ensp;Sportelli
		</div>
		<div class="col-md-4 text-center">
<!--
            <label class="checkbox-inline">
                <input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="ancheChiusiCheckBox" >Anche Chiusi
            </label>
-->
		</div>
	</div>
</div>
<div class="panel-body">
<div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/sportello.js"></script>
</body>
</html>
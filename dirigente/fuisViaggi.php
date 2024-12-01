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
	<title>Fuis e Bonus</title>
<?php

require_once '../common/header-common.php';
require_once '../common/style.php';
//require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('dirigente');
?>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
<script type="text/javascript" src="js/scriptFuisViaggi.js"></script>

</head>

<body >
<?php
require_once '../common/header-dirigente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">

<!-- pannello Diaria Viaggi -->
<div class="panel panel-danger">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-picture"></span>&ensp;<strong>Diaria Viaggi</strong>
		</div>
		<div class="col-md-4 text-center">
			<strong>Totale
<?php
$query = "	SELECT SUM(importo)
				FROM fuis_viaggio_diaria fuis_viaggio_diaria
				INNER JOIN viaggio viaggio
				ON fuis_viaggio_diaria.viaggio_id = viaggio.id
				WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
            ";
$totale = dbGetValue($query);
echo $totale;
?>
			</strong> 
		</div>
		<div class="col-md-4 text-right">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
            <div class="viaggioDiaria_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
<!-- END pannello Diaria Viaggi -->

</div>
</body>
</html>

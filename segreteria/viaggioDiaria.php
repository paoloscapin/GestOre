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
//require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
//require_once '../common/_include_flatpickr.php';
ruoloRichiesto('segreteria-docenti','dirigente');
?>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
	<title>Diaria</title>
</head>

<body >
<?php
require_once '../common/header-segreteria.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-deeporange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-list-alt"></span>&ensp;Diaria Viaggi
		</div>
		<div class="col-md-4 text-center">
		</div>
		<div class="col-md-4 text-right">
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
            <div class="viaggioDiaria_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<!-- Bootstrap Modals -->
<!-- Modal - Update Record -->
<div class="modal fade" id="viaggioDiariaModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Aggiorna Ore e Diaria</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group" id="ore-part">
                    <label class="col-sm-2 control-label" for="ore">Ore</label>
					<div class="col-sm-4"><input type="text" value="" id="ore" placeholder="" class="form-control" /></div>
                </div>
                <div class="form-group" id="diaria-part">
                    <label class="col-sm-2 control-label" for="diaria">Diaria</label>
					<div class="col-sm-4"><input type="text" value="" id="diaria" placeholder="" class="form-control" /></div>
                </div>
			</form>
            <input type="hidden" id="hidden_diaria_id">
            <input type="hidden" id="hidden_diaria">
            <input type="hidden" id="hidden_ore_id">
            <input type="hidden" id="hidden_ore">
            <input type="hidden" id="hidden_docente_id">

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="viaggioDiariaSalva()">Salva</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add New Record -->





</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptViaggioDiaria.js"></script>

</body>
</html>
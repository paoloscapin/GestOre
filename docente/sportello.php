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
ruoloRichiesto('docente');
?>
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
	<title>Sportelli</title>
<style>
/* Tooltip */
.tooltip > .tooltip-inner {
    background-color: #73AD21; 
    color: #FFFFFF; 
    border: 1px solid green; 
    padding: 15px;
    font-size: 20px;
}
.tooltip.top > .tooltip-arrow {
    border-top: 5px solid green;
}
.tooltip.bottom > .tooltip-arrow {
    border-bottom: 5px solid blue;
}
.tooltip.left > .tooltip-arrow {
    border-left: 5px solid red;
}
.tooltip.right > .tooltip-arrow {
    border-right: 5px solid black;
}
.tooltip-inner {
    max-width: 450px;
    /* If max-width does not work, try using width instead */
    width: 450px;
    text-align: left;
}
</style>
</head>

<body >
<?php
require_once '../common/header-docente.php';
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
            <label class="checkbox-inline">
                <input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloNuoviCheckBox" >Solo Nuovi
            </label>
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
            <div class="records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<!-- Modal - Add/Update Record -->
<div class="modal fade" id="sportello_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-orange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Sportello</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data" class="form-control" readonly="readonly" /></div>

                    <label class="col-sm-2 control-label" for="ora">Ora</label>
                    <div class="col-sm-4"><input type="text" id="ora" class="form-control" readonly="readonly" /></div>
                </div>

                <div class="form-group docente_selector">
                    <label class="col-sm-2 control-label" for="docente">Docente</label>
                    <div class="col-sm-4"><input type="text" id="docente" class="form-control" readonly="readonly" /></div>
                </div>

                <div class="form-group materia_selector">
                    <label class="col-sm-2 control-label" for="materia">Materia</label>
                    <div class="col-sm-4"><input type="text" id="materia" class="form-control" readonly="readonly" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="numero_ore">Numero di ore</label>
                    <div class="col-sm-4"><input type="text" id="numero_ore" class="form-control" readonly="readonly" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="argomento">Argomento</label>
                    <div class="col-sm-8"><input type="text" id="argomento" class="form-control" readonly="readonly" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="luogo">Luogo</label>
                    <div class="col-sm-8"><input type="text" id="luogo" class="form-control" readonly="readonly" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="classe">Classe</label>
                    <div class="col-sm-8"><input type="text" id="classe" class="form-control" readonly="readonly" /></div>
                </div>

                <div class="form-group">
                    <label for="cancellato" class="col-sm-2 control-label">Cancellato</label>
                    <div class="col-sm-1 "><input type="checkbox" id="cancellato" disabled="disabled" ></div>
                </div>

                <div class="form-group">
                    <label for="firmato" class="col-sm-2 control-label">Firmato</label>
                    <div class="col-sm-1 "><input type="checkbox" id="firmato" ></div>
                </div>

                <div class="form-group text-center" id="studenti-part">
                    <hr>
                    <label for="studenti_table">Studenti</label>
					<div class="table-wrapper">
					<table class="table table-bordered table-striped" id="studenti_table">
						<thead>
						<tr>
							<th>id</th>
							<th>studenteId</th>
							<th class="text-center">Studente</th>
							<th class="text-center">Presente</th>
						</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
                </div>

                <input type="hidden" id="hidden_sportello_id">
			</form>

            </div>
            <div class="modal-footer">
			<div class="col-sm-12 text-center">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="sportelloSave()">Salva</button>
            </div>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update Record -->

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/sportello.js"></script>
</body>
</html>
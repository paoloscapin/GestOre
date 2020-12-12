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
ruoloRichiesto('segreteria-didattica','dirigente');
?>
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
	<title>Studenti</title>
</head>

<body >
<?php
require_once '../common/header-didattica.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-orange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-object-align-horizontal"></span>&ensp;Studenti
		</div>
		<div class="col-md-4 text-center">
            <label id="import_btn" class="btn btn-xs btn-lima4 btn-file"><span class="glyphicon glyphicon-upload"></span>&emsp;Importa<input type="file" id="file_select_id" style="display: none;"></label>
		</div>
		<div class="col-md-4 text-right">
            <div class="pull-right">
				<button class="btn btn-xs btn-orange4" onclick="studenteGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
            </div>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-12 text-center" id='result_text'>
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
<div class="modal fade" id="studente_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lima4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Studente</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">


                <div class="form-group">
                    <label class="col-sm-2 control-label" for="cognome">Cognome</label>
                    <div class="col-sm-8"><input type="text" id="cognome" placeholder="cognome" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="nome">Nome</label>
                    <div class="col-sm-8"><input type="text" id="nome" placeholder="nome" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="email">email</label>
                    <div class="col-sm-8"><input type="text" id="email" placeholder="email" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="classe">Classe</label>
                    <div class="col-sm-2"><input type="text" id="classe" placeholder="classe" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="anno">Anno</label>
                    <div class="col-sm-2"><input type="text" id="anno" placeholder="anno" class="form-control"/></div>
                </div>

                <input type="hidden" id="hidden_studente_id">
			</form>

            </div>
            <div class="modal-footer text-center">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="studenteSave()">Salva</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update Record -->

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/studente.js"></script>
</body>
</html>
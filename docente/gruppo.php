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
<title>Gruppi</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';
ruoloRichiesto('docente','segreteria-docenti','dirigente');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
</head>

<body >
<?php require_once '../common/header-docente.php'; ?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<?php
$data = '';

// potrebbe variare in seguito se introduciamo lo stesso per dirigente e segreteria
$docente_id = $__docente_id;
$docente_condition = ($docente_id == null ? " 1 " : " responsabile_docente_id = $docente_id; ");

// prepara l'elenco delle categorie di attivita'
$query = "	SELECT * FROM `gruppo` WHERE dipartimento = false AND anno_scolastico_id = $__anno_scolastico_corrente_id AND $docente_condition;";
foreach(dbGetAll($query) as $gruppo) {
	$data .= '
    <div class="panel panel-lightblue4">
    <div class="panel-heading">
        <div class="row">
            <div class="col-md-4">
                <span class="glyphicon glyphicon-user"></span>&ensp;'.$gruppo['nome'].'
            </div>
            <div class="col-md-4 text-center">
            </div>
            <div class="col-md-4 text-right">
                        <button onclick="gruppoIncontroGetDetails('.$gruppo['id'].',-1)" class="btn btn-xs btn-lightblue4"><span class="glyphicon glyphicon-plus"></span></button>
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
                <div class="gruppo_records_content_'.$gruppo['id'].'"></div>
            </div>
        </div>
    </div>
    </div>
    ';
}
echo $data;
?>

<!-- Bootstrap Modals -->
<!-- Modal - Add/Update Record -->
<div class="modal fade" id="update_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
        <div class="modal-body">
			<div class="panel panel-lightblue4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Incontro</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data_incontro">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data_incontro" placeholder="data" class="form-control" /></div>
                    <label class="col-sm-2 control-label" for="ora_incontro">Ora</label>
                    <div class="col-sm-4"><input type="text" id="ora_incontro" placeholder="ora" class="form-control"/></div>
                </div>
                <hr>

                <div class="form-group">
                    <label for="ordine_del_giorno" class="col-sm-2 control-label">Ordine del Giorno</label>
                    <div class="col-sm-10"><textarea rows="3" id="ordine_del_giorno" placeholder="Ordine del Giorno" class="form-control" ></textarea></div>
                </div>
                </form>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="gruppoIncontroSave()">Salva</button>
				<input type="hidden" id="hidden_record_id">
				<input type="hidden" id="hidden_gruppo_id">
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update New Record -->

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptGruppo.js"></script>
</body>
</html>
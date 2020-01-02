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
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_select2.php';
ruoloRichiesto('dirigente','segreteria-docenti');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<script type="text/javascript" src="js/gruppoGestione.js"></script>
</head>

<body >
<?php require_once '../common/header-segreteria.php'; ?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lightblue4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-user"></span>&emsp;Gruppi
		</div>
        <div class="col-md-4">
        </div>
        <div class="col-md-4">
            <div class="pull-right">
				<button class="btn btn-xs btn-lightblue4" onclick="openModal()" ><span class="glyphicon glyphicon-plus"></span></button>
            </div>
        </div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<?php
// prepara l'elenco dei docenti
$docenteOptionList = '				<option value="0"></option>';
$query = "	SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC;";
foreach(dbGetAll($query) as $docenteRow) {
	$docenteOptionList .= '<option value="'.$docenteRow['id'].'">'.$docenteRow['cognome'].' '.$docenteRow['nome'].'</option>';
}
?>

<!-- Bootstrap Modals -->
<!-- Modal - Add/Update Record -->
<div class="modal fade" id="add_new_record_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lightblue4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Gruppo</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-3 control-label"  for="nome">Nome</label>
                    <div class="col-sm-8"><input type="text" id="nome" placeholder="nome del gruppo" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label"  for="commento">Commento</label>
                    <div class="col-sm-8"><input type="text" id="commento" placeholder="commento" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label"  for="max_ore">Max Ore</label>
                    <div class="col-sm-8"><input type="text" id="max_ore" placeholder="massimo ore previste per incontri del gruppo" class="form-control"/></div>
                </div>

                <div class="form-group responsabile_selector">
                    <label class="col-sm-3 control-label" for="responsabile">Responsabile</label>
					<div class="col-sm-8"><select id="responsabile" name="responsabile" class="responsabile selectpicker" data-style="btn-success" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" data-selectOnTab="true" >
                    <?php echo $docenteOptionList ?>
					</select></div>
                </div>
			</form>

            </div>
            <div class="panel-footer text-center">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="gruppoGestioneSave()">Salva</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add New Record -->

<!-- Modal - partecipanti -->
<div class="modal fade" id="partecipanti_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="partecipantiModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lightblue4">
			<div class="panel-heading">
				<h5 class="modal-title" id="partecipantiModalLabel">Partecipanti Gruppo</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <select id="partecipanti" class="js-example-basic-multiple form-control" multiple="multiple" style="width: 75%">
                    <?php echo $docenteOptionList ?>
                </select>
				<input type="hidden" id="hidden_gruppo_id">

			</form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="gruppoPartecipantiSave()">Salva</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add New Record -->

</div>

</body>
</html>

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
?>
	<title>Viaggi e Uscite</title>
</head>

<body >
<?php
	require_once '../common/header-docente.php';
	require_once '../common/connect.php';
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-deeporange4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-picture"></span>&emsp;Viaggi e Uscite
		</div>
		<div class="col-md-4 text-center">
			<label for="ancheChiuseCheckBox" class=""> Anche Chiuse </label>
			<input type="checkbox" class="checkbox-inline pull-right" data-toggle="toggle" data-size="mini" data-onstyle="success" id="ancheChiuseCheckBox" checked="checked" >
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
            <div class="records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

</div>
<div class="modal fade" id="update_spesa_row_modal" data-backdrop="static" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title text-center">Spesa</h5>
			</div>
			<div class="modal-body">
                <div class="form-group">
                    <label for="update_spesa_data">data</label>
                    <input type="text" id="update_spesa_data" placeholder="data" class="form-control"/>
                </div>
                <div class="form-group">
                    <label for="update_spesa_tipo">tipo</label>
                    <input type="text" id="update_spesa_tipo" placeholder="tipo" class="form-control"/>
                </div>
                <div class="form-group">
                    <label for="update_spesa_importo">importo</label>
                    <input type="text" id="update_spesa_importo" placeholder="importo" class="form-control"/>
                </div>
                <div class="form-group">
                    <label for="update_spesa_note">note</label>
					<textarea class="form-control" rows="5" id="update_spesa_note" placeholder="note" ></textarea>
                </div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button type="button" class="btn btn-primary" onclick="viaggioSpesaUpdateDetails()" >Salva</button>
			</div>
			<input type="hidden" id="hidden_spesa_viaggio_id">
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<!-- Modal - Update viaggio details: eliminato data-keyboard="false" -->
<div class="modal fade" id="update_viaggio_modal" data-backdrop="static" tabindex="3" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-info">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Aggiorna viaggio</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">
                <div class="form-group">
                    <label for="update_viaggio_destinazione" class="col-sm-2 control-label">Destinazione</label>
                    <div class="col-sm-8"><input type="text" id="update_viaggio_destinazione" placeholder="Destinazione" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label for="update_viaggio_classe" class="col-sm-2 control-label">Classe</label>
                    <div class="col-sm-8"><input type="text" id="update_viaggio_classe" placeholder="classe" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label for="update_viaggio_data_partenza" class="col-sm-2 control-label">Dal</label>
                    <div class="col-sm-4"><input type="text" id="update_viaggio_data_partenza" placeholder="Data" class="form-control"/></div>

                    <label for="update_viaggio_data_rientro" class="col-sm-2 control-label">Al</label>
                    <div class="col-sm-4"><input type="text" id="update_viaggio_data_rientro" placeholder="Data" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label for="update_viaggio_ora_partenza" class="col-sm-2 control-label">Partenza</label>
                    <div class="col-sm-4"><input type="text" id="update_viaggio_ora_partenza" placeholder="partenza" class="form-control"/></div>
                    <label for="update_viaggio_ora_rientro" class="col-sm-2 control-label">Rientro</label>
                    <div class="col-sm-4"><input type="text" id="update_viaggio_ora_rientro" placeholder="rientro" class="form-control"/></div>
                </div>
<hr>
                <div class="form-group">
                    <label for="update_viaggio_spese_table">Spese</label>
					<div class="table-wrapper">
					<table class="table table-bordered table-striped" id="update_viaggio_spese_table">
						<thead>
						<tr>
							<th>id</th>
							<th>data</th>
							<th>tipo</th>
							<th>note</th>
							<th class="text-right">importo</th>
							<th class="text-center"></th>
						</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
					</div>
					<button type="button" class="btn btn-success" data-toggle="modal" onclick="viaggioAddSpesa()"><span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi Spesa</button>
                </div>
				<?php if(getSettingsValue('viaggi','richiesta_diaria', true)) : ?>
<hr>

                <div class="form-group">
                    <label for="update_viaggio_ore_richieste" class="col-sm-3 control-label">ore di recupero (max 8)</label>
                    <div class="col-sm-1"><input type="text" id="update_viaggio_ore_richieste" placeholder="0" class="form-control"/></div>
             		<div class="col-sm-4 text-center"><h4 class="form-control-static" id="lab" ><Strong><u>Oppure</u></Strong></h4></div>
                    <label for="update_viaggio_richiesta_fuis" class="col-sm-3 control-label">Indennità forfettaria</label>
                    <div class="col-sm-1 "><input type="checkbox" id="update_viaggio_richiesta_fuis" ></div>
                </div>
				<?php endif; ?>

			</form>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="viaggioUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_viaggio_id">
			</div>
            </div>
            </div>
        </div>
    </div>
</div>
<!-- // Modal - Update docente details -->

<!-- Bootstrap, jquery etc (css + js) -->
<?php
	require_once '../common/style.php';
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/common/bootstrap-toggle-master/css/bootstrap-toggle.min.css">
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootstrap-toggle-master/js/bootstrap-toggle.min.js"></script>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<!-- flatpickr -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/flatpickr/dist/flatpickr.min.js"></script>
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/flatpickr/dist/l10n/it.js"></script>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/common/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/common/flatpickr/dist/themes/material_red.css">

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptViaggio.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>

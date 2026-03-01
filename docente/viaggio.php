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
	<title>Viaggi e Uscite</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
// require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';
ruoloRichiesto('docente');
?>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>
<!-- bootbox notificator -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<!-- Custom JS file moved to the end -->

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
		<div class="col-md-4">
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

<!-- Modal - Inoltra viaggio details" -->
<div class="modal fade" id="update_viaggio_modal" data-backdrop="static" tabindex="3" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-info">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Inoltra viaggio</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">
                <div class="form-group">
                    <label for="update_viaggio_destinazione" class="col-sm-2 control-label">Destinazione</label>
                    <div class="col-sm-8"><input type="text" id="update_viaggio_destinazione" placeholder="Destinazione" class="form-control" readonly="readonly"/></div>
                </div>

                <div class="form-group">
                    <label for="update_viaggio_classe" class="col-sm-2 control-label">Classe</label>
                    <div class="col-sm-8"><input type="text" id="update_viaggio_classe" placeholder="classe" class="form-control" readonly="readonly"/></div>
                </div>

                <div class="form-group">
                    <label for="update_viaggio_data_partenza" class="col-sm-2 control-label">Dal</label>
                    <div class="col-sm-4"><input type="text" id="update_viaggio_data_partenza" placeholder="Data" class="form-control" readonly="readonly"/></div>

                    <label for="update_viaggio_data_rientro" class="col-sm-2 control-label">Al</label>
                    <div class="col-sm-4"><input type="text" id="update_viaggio_data_rientro" placeholder="Data" class="form-control" readonly="readonly"/></div>
                </div>

                <?php if(getSettingsValue('viaggi','richiesta_diaria', true)) : ?>
                <hr>
                <div class="form-group">
                    <label for="update_viaggio_ore_richieste" class="col-sm-3 control-label">ore di recupero (max 8)</label>
                    <div class="col-sm-1"><input type="text" id="update_viaggio_ore_richieste" placeholder="0" class="form-control"/></div>
             		<div class="col-sm-4 text-center"><h4 class="form-control-static" id="lab" ><Strong><u>Oppure</u></Strong></h4></div>
                    <label for="update_viaggio_diaria" class="col-sm-3 control-label">Diaria</label>
                    <div class="col-sm-1 "><input type="checkbox" id="update_viaggio_diaria" ></div>
                </div>
				<?php endif; ?>

            </form>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="viaggioInoltra()" >Inoltra</button>
				<input type="hidden" id="hidden_viaggio_id">
			</div>
            </div>
            </div>
        </div>
    </div>
</div>
<!-- // Modal - Update docente details -->

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptViaggio.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>

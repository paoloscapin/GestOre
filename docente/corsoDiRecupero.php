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
ruoloRichiesto('segreteria-docenti','segreteria-didattica','dirigente','docente');
?>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green.css">
	<title>Corsi di Recupero</title>
</head>

<body >
<?php
	require_once '../common/header-docente.php';
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lightblue4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-3">
			<span class="glyphicon glyphicon-repeat"></span>&ensp;Corsi di Recupero
		</div>
		<div class="col-md-2 text-center">
			<a href="<?php echo $__application_base_path; ?>/docente/corsoDiRecuperoReportStudenti.php" class="btn btn-xs btn-default btn-yellow4" role="button"><span class="glyphicon glyphicon-repeat"></span>&ensp;Risultati </a>
		</div>
		<div class="col-md-2 text-center">
			<a href="<?php echo $__application_base_path; echo ($__config->getVoti_recupero_novembre_aperto() ? '/docente/corsoDiRecuperoReportStudenti.php' : '/docente/corsoDiRecuperoVoti.php');?>" class="btn btn-xs btn-teal4 btn-success" role="button"><span class="glyphicon glyphicon-repeat"></span>&ensp;Voti </a>
		</div>
		<div class="col-md-2 text-center">
			<label for="soloOggiCheckBox" class=""> Solo Corsi di Oggi </label>
			<input type="checkbox" class="checkbox-inline pull-right" data-toggle="toggle" data-size="mini" data-onstyle="success" id="soloOggiCheckBox" >
		</div>
		<div class="col-md-2 text-right">
			<label for="soloFirmatiCheckBox" class=""> Mostra anche già Firmati </label>
			<input type="checkbox" class="checkbox-inline pull-right" data-toggle="toggle" data-size="mini" data-onstyle="warning" id="soloFirmatiCheckBox" checked="checked" >
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

<!-- Modal - Update lezione corso di recupero details: eliminato data-keyboard="false" -->
<div class="modal fade" id="update_lezione_corso_di_recupero_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lightblue4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Aggiorna dati lezione</h5>
			</div>
			<div class="panel-body">
                <div class="form-group">
                    <label for="update_argomento">Argomento</label>
                    <input type="text" id="update_argomento" placeholder="argomento" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="update_note">Note</label>
					<textarea class="form-control" rows="5" id="update_note" placeholder="note" ></textarea>
                </div>
                <div class="form-group">
                    <label for="update_studenti_table">Studenti</label>
					<div class="table-wrapper">
					<table class="table table-bordered table-striped" id="update_studenti_table">
						<thead>
						<tr>
							<th>id</th>
							<th>cognome</th>
							<th>nome</th>
							<th>commento</th>
							<th>classe</th>
							<th>presente</th>
						</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
					</div>
                </div>

            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="lezioneCorsoDiRecuperoUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_lezione_corso_di_recupero_id">
			</div>
            </div>
            </div>
        </div>
    </div>
</div>
<!-- // Modal - Update docente details -->

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptCorsoDiRecupero.js"></script>

</body>
</html>

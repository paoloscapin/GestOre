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
	<title>Sostituzioni</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<?php
require_once '../common/checkSession.php';

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_flatpickr.php';
ruoloRichiesto('dirigente','segreteria-docenti');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<!-- Custom JS file moved to the end -->
</head>

<body >
<?php require_once '../common/header-segreteria.php'; ?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-yellow4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-retweet"></span>&emsp;Sostituzioni
		</div>
        <div class="col-md-4">
            <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloOggiCheckBox" >Solo Oggi
				</label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="pull-right">
				<button class="btn btn-xs btn-yellow4" onclick="openModal()" ><span class="glyphicon glyphicon-plus"></span></button>
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

// $query = "SELECT COALESCE(SUM(ora), 0) FROM sostituzione_docente WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId;";
$query = "SELECT docente.id as docente_id, cognome, nome, ore_dovute.ore_40_sostituzioni_di_ufficio - COALESCE(SUM(sostituzione_docente.ora), 0) AS differenza FROM docente
    INNER JOIN ore_dovute ON ore_dovute.docente_id = docente.id AND ore_dovute.anno_scolastico_id = $__anno_scolastico_corrente_id
    LEFT JOIN sostituzione_docente ON sostituzione_docente.docente_id = docente.id AND sostituzione_docente.anno_scolastico_id = $__anno_scolastico_corrente_id
    WHERE docente.attivo = true
    GROUP BY docente.id
    ORDER BY differenza DESC, docente.cognome, docente.nome ASC ;";

foreach(dbGetAll($query) as $docenteRow) {
	$docenteOptionList .= '<option value="'.$docenteRow['docente_id'].'" data-subtext="'.$docenteRow['differenza'].'">'.$docenteRow['cognome'].' '.$docenteRow['nome'].'</option>';
}
?>

<!-- Bootstrap Modals -->
<!-- Modal - Add New Record -->
<div class="modal fade" id="add_new_record_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-yellow4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Sostituzione</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="dataSostituzione">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="dataSostituzione" placeholder="data" class="form-control" /></div>
                </div>

                <div class="form-group docente_sostituzione_selector">
                    <label class="col-sm-2 control-label" for="docente_sostituzione">Docente</label>
					<div class="col-sm-8"><select id="docente_sostituzione" name="docente_sostituzione" class="docente_sostituzione selectpicker" data-style="btn-success" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" data-selectOnTab="true" >
<?php echo $docenteOptionList ?>
					</select></div>
                </div>
			</form>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="sostituzione_docenteAddRecord()">Salva</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add New Record -->

</div>

<!-- Custom JS file MUST be here because of toggle -->
<script type="text/javascript" src="js/scriptSostituzione.js"></script>

</body>
</html>

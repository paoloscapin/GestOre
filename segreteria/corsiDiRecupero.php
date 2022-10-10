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
	<title>Corsi di Recupero</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<?php
require_once '../common/checkSession.php';
require_once '../common/__Settings.php';

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('dirigente','segreteria-docenti');
?>


<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<!-- <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css"> -->
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green.css">

</head>

<body >
<?php require_once '../common/header-segreteria.php'; ?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lightblue4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-3">
			<span class="glyphicon glyphicon-education"></span>&emsp;Corsi di Recupero
		</div>
		<div class="col-md-3 text-center">
            <label id="import_btn" class="btn btn-xs btn-lightblue4 btn-file"><span class="glyphicon glyphicon-upload"></span>&emsp;Importa<input type="file" id="file_select_id" style="display: none;"></label>
		</div>
        <div class="col-md-3">
            <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="inItinereCheckBox" >In Itinere
				</label>
            </div>
        </div>
        <div class="col-md-3">
            <div class="pull-right">
				<button class="btn btn-xs btn-lightblue4" onclick="corsiDiRecuperoGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
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

<div class="panel-footer">
</div>
</div>

<?php
// prepara l'elenco dei docenti
$docenteOptionList = '				<option value="0">Seleziona il docente</option>';
$query = "	SELECT * FROM docente
			WHERE docente.attivo = true
			ORDER BY docente.cognome, docente.nome ASC
			;";
$resultArray = dbGetAll($query);
foreach($resultArray as $row) {
	$docenteOptionList .= '
		<option value="'.$row['id'].'" >'.$row['cognome'].' '.$row['nome'].'</option>
	';
}

// prepara l'elenco delle materie
$materiaOptionList = '				<option value="0">Seleziona la materia</option>';
$query = "	SELECT * FROM materia
			ORDER BY materia.nome ASC
			;";
$resultArray = dbGetAll($query);
foreach($resultArray as $row) {
	$materiaOptionList .= '
		<option value="'.$row['id'].'" >'.$row['nome'].'</option>
	';
}
?>

<!-- Bootstrap Modals -->
<!-- Modal - Add/Update Record -->
<div class="modal fade" id="update_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
        <div class="modal-body">
			<div class="panel panel-lightblue4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Corso di Recupero</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">
                <div class="form-group">
                    <label for="codice" class="col-sm-2 control-label">Codice</label>
                    <div class="col-sm-4"><input type="text" id="codice" placeholder="codice corso" class="form-control"/></div>
                </div>

                <div class="form-group docente_selector">
                    <label class="col-sm-2 control-label" for="docente">Docente</label>
					<div class="col-sm-8"><select id="docente" name="docente" class="docente selectpicker" data-style="btn-teal4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $docenteOptionList ?>
					</select></div>
                </div>

                <div class="form-group">
                    <label for="aula" class="col-sm-2 control-label">Aula</label>
                    <div class="col-sm-4"><input type="text" id="aula" placeholder="aula" class="form-control"/></div>
                </div>

                <div class="form-group materia_selector">
                    <label class="col-sm-2 control-label" for="materia">Materia</label>
					<div class="col-sm-8"><select id="materia" name="materia" class="materia selectpicker" data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $materiaOptionList ?>
					</select></div>
                </div>

                <hr>

                <div class="form-group">
                    <label for="lezioni" class="col-sm-2 control-label">Lezioni</label>
                    <div class="col-sm-10"><textarea rows="8" id="lezioni" placeholder="lezioni" class="form-control" ></textarea></div>
                </div>

                <div class="form-group">
                    <label for="studenti" class="col-sm-2 control-label">Studenti</label>
                    <div class="col-sm-10"><textarea rows="12" id="studenti" placeholder="studenti" class="form-control" ></textarea></div>
                </div>

            </form>

            </div>
			<div class="panel-footer text-center">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="corsiDiRecuperoSave()">Salva</button>
				<input type="hidden" id="hidden_record_id">
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update New Record -->

<!-- Custom JS file MUST be here because of toggle -->
<script type="text/javascript" src="js/corsiDiRecupero.js"></script>

</body>
</html>

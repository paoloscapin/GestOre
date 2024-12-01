<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
?>

<!DOCTYPE html>
<html>
<head>
	<title>Report Sportelli Effettuati</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<?php


require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_flatpickr.php';
ruoloRichiesto('dirigente','segreteria-docenti','docente');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

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

<?php
// prepara l'elenco dei docenti per il filtro e per il dialog
$docenteFiltroOptionList = '<option value="0">tutti</option>';
foreach(dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC ; ")as $docente) {
    $docenteFiltroOptionList .= ' <option value="'.$docente['id'].'" >'.$docente['cognome'].' '.$docente['nome'].'</option> ';
}

// prepara l'elenco delle materie per il filtro e per le materie del dialog
$materiaFiltroOptionList = '<option value="0">tutte</option>';
foreach(dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC ; ")as $materia) {
    $materiaFiltroOptionList .= ' <option value="'.$materia['id'].'" >'.$materia['nome'].'</option> ';
}
?>

<body >
<?php
if (! empty($__utente_ruolo) && $__utente_ruolo == 'docente') {
    require_once '../common/header-docente.php';
} else {
    require_once '../common/header-segreteria.php';
}
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-orange4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-3">
			<span class="glyphicon glyphicon-retweet"></span>&emsp;Report Sportelli Effettuati
		</div>
        <div class="col-md-3">
            <div class="text-center">
                <label class="col-sm-2 control-label" for="materia">Materia</label>
					<div class="col-sm-8"><select id="materia_filtro" name="materia_filtro" class="materia_filtro selectpicker" data-style="btn-lima4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $materiaFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <div class="col-md-3">
            <label class="col-sm-2 control-label" for="docente">Docente</label>
                <div class="col-sm-8"><select id="docente_filtro" name="docente_filtro" class="docente_filtro selectpicker" data-style="btn-lightblue4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                <?php echo $docenteFiltroOptionList ?>
                </select></div>
        </div>
        <div class="col-md-2">
        <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="passatiCheckBox" >Passati
				</label>
            </div>
        </div>
        <div class="col-md-1">
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
</div>

<!-- Custom JS file MUST be here because of toggle -->
<script type="text/javascript" src="js/sportelloReportEffettuati.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>

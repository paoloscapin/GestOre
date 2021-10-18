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
ruoloRichiesto('studente','dirigente');
?>

<!-- bootbox notificator -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
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

<?php
// prepara l'elenco dei docenti per il filtro
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
require_once '../common/header-studente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-orange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-3">
			<span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli
		</div>
        <div class="col-md-3">
            <div class="text-center">
                <label class="col-sm-2 control-label" for="docente">Docente</label>
					<div class="col-sm-8"><select id="docente_filtro" name="docente_filtro" class="docente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $docenteFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <label class="col-sm-2 control-label" for="materia">Materia</label>
					<div class="col-sm-8"><select id="materia_filtro" name="materia_filtro" class="materia_filtro selectpicker" data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $materiaFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloNuoviCheckBox" >Solo Nuovi
				</label>
            </div>
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

</div>
<input type="hidden" id="hidden_unSoloArgomento" value="<?php echo getSettingsValue("sportelli", "unSoloArgomento", true) ? 1 : 0; ?>">

<!-- Custom JS file -->
<script type="text/javascript" src="js/sportello.js"></script>
</body>
</html>
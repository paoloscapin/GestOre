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
	<title>Statistica Sportelli Effettuati</title>
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

<body >
<?php
if (! empty($__utente_ruolo) && $__utente_ruolo == 'docente') {
    require_once '../common/header-docente.php';
} else {
    require_once '../common/header-segreteria.php';
}

// prepara l'elenco dei docenti per il filtro e per il dialog
$categoriaFiltroOptionList = '<option value="0">tutte</option>';
foreach(dbGetAll("SELECT * FROM sportello_categoria")as $categoria) {
    $categoriaFiltroOptionList .= ' <option value="'.$categoria['id'].'" >'.$categoria['nome'].'</option> ';
}

?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-orange4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-9" style="text-align:center">
			<span class="glyphicon glyphicon-retweet"></span><b>&emsp;Statistiche Sportelli Effettuati</b>
		</div>
        <div class="col-md-auto text-right" style="margin:0px 20px 0px 0px;">
                        <label id="export_btn" class="btn btn-xs btn-lima4 btn-file" onclick="export_to_csv()"><span
                                class="glyphicon glyphicon-download"></span>&emsp;Exporta</label>
		</div>
    </div>
    <div class="row">
    <div class="col-md-2" style="padding:15px 0px 0px 0px;">
            <div class="text-center">
                <label class="col-sm-1 control-label" for="materia">Categoria</label>
					<div class="col-sm12"><select id="categoria_filtro" name="categoria_filtro" class="categoria_filtro selectpicker" data-style="btn-lima4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="50%" >
                    <?php echo $categoriaFiltroOptionList ?>
					</select></div>
            </div>
        </div>
	    <div class="col-md-2" style="padding:15px 0px 0px 0px;">
        <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="conSportelliCheckBox" ><b>Con Sportelli</b>
				</label>
            </div>
        </div>
        <div class="col-md-2" style="padding:15px 0px 0px 0px;">
        <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="conSportelliFattiCheckBox" ><b>Con Sportelli Fatti</b>
				</label>
            </div>
        </div>
        <div class="col-md-2" style="padding:15px 0px 0px 0px;">
        <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="senzaSportelliCheckBox" ><b>Senza Sportelli</b>
				</label>
            </div>
        </div>
        <div class="col-md-2" style="padding:15px 0px 0px 0px;">
        <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloPassatiCheckBox" ><b>Passati</b>
				</label>
            </div>
        </div>        
        <div class="col-md-1" style="padding:15px 0px 0px 0px;">
        <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloFuturiCheckBox" ><b>Futuri</b>
				</label>
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
</div>

<!-- Custom JS file MUST be here because of toggle -->
<script type="text/javascript" src="js/sportelloStatisticheEffettuati.js?v=<?php echo $__software_version; ?>"></script> 

</body>
</html>

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
ruoloRichiesto('segreteria-didattica','dirigente');
?>
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
// prepara l'elenco dei docenti
$docenteOptionList = '				<option value="0"></option>';
$query = "	SELECT * FROM docente
            WHERE docente.attivo = true
            ORDER BY docente.cognome, docente.nome ASC
            ;";
if (!$result = mysqli_query($con, $query)) {
    exit(mysqli_error($con));
}
if(mysqli_num_rows($result) > 0) {
    $resultArray = $result->fetch_all(MYSQLI_ASSOC);
    foreach($resultArray as $row) {
        $docenteOptionList .= '
            <option value="'.$row['id'].'" >'.$row['cognome'].' '.$row['nome'].'</option>
        ';
    }
}

// prepara l'elenco delle materie (anche per il filtro)
$materiaOptionList = '				<option value="0"></option>';
$materiaFiltroOptionList = '				<option value="0">tutte</option>';
$query = "	SELECT * FROM materia ORDER BY materia.nome ASC;";
if (!$result = mysqli_query($con, $query)) {
    exit(mysqli_error($con));
}
if(mysqli_num_rows($result) > 0) {
    $resultArray = $result->fetch_all(MYSQLI_ASSOC);
    foreach($resultArray as $row) {
        $materiaOptionList .= ' <option value="'.$row['id'].'" >'.$row['nome'].'</option> ';
        $materiaFiltroOptionList .= ' <option value="'.$row['id'].'" >'.$row['nome'].'</option> ';
    }
}
?>

<body >
<?php
require_once '../common/header-didattica.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-orange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-3">
			<span class="glyphicon glyphicon-object-align-horizontal"></span>&ensp;Sportelli
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
		<div class="col-md-3 text-right">
            <div class="pull-right">
				<button class="btn btn-xs btn-orange4" onclick="sportelloGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
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

<!-- Modal - Add/Update Record -->
<div class="modal fade" id="sportello_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-orange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Sportello</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data" placeholder="data" class="form-control" /></div>

                    <label class="col-sm-2 control-label" for="ora">Ora</label>
                    <div class="col-sm-4"><input type="text" id="ora" placeholder="ora" class="form-control"/></div>
                </div>

                <div class="form-group docente_selector">
                    <label class="col-sm-2 control-label" for="docente">Docente</label>
					<div class="col-sm-8"><select id="docente" name="docente" class="docente selectpicker" data-style="btn-success" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $docenteOptionList ?>
					</select></div>
                </div>

                <div class="form-group materia_selector">
                    <label class="col-sm-2 control-label" for="materia">Materia</label>
					<div class="col-sm-8"><select id="materia" name="materia" class="materia selectpicker" data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $materiaOptionList ?>
					</select></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="numero_ore">Numero di ore</label>
                    <div class="col-sm-8"><input type="text" id="numero_ore" placeholder="numero ore" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="argomento">Argomento</label>
                    <div class="col-sm-8"><input type="text" id="argomento" placeholder="argomento" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="luogo">Luogo</label>
                    <div class="col-sm-8"><input type="text" id="luogo" placeholder="luogo" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="classe">Classe</label>
                    <div class="col-sm-8"><input type="text" id="classe" placeholder="classe" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label for="cancellato" class="col-sm-2 control-label">Cancellato</label>
                    <div class="col-sm-1 "><input type="checkbox" id="cancellato" ></div>
                </div>

                <div class="form-group">
                    <label for="firmato" class="col-sm-2 control-label">Firmato</label>
                    <div class="col-sm-1 "><input type="checkbox" id="firmato" ></div>
                </div>

                <input type="hidden" id="hidden_sportello_id">
			</form>

            </div>
            <div class="modal-footer text-center">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="sportelloSave()">Salva</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update Record -->

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/sportello.js"></script>
</body>
</html>
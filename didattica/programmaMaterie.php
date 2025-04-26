<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-notify.php';
ruoloRichiesto('docente','segreteria-didattica','dirigente');
?>

<!DOCTYPE html>
<html>
<head>
    <script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
	<title>Programma Materie</title>

<style>
    .icon-play{
        background-image : url('../img/pdf-256.png');
        background-size: cover;
        display: inline-block;
        height: 16px;
        width: 16px;
    }
</style>
</head>

<?php

// prepara l'elenco dei docenti
$docenteOptionList = '<option value="0"></option>';
// i docenti non devono essere solo quelli attivi: anche quelli degli anni scorsi
// $query = "	SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC;";
$query = "	SELECT * FROM docente ORDER BY docente.cognome, docente.nome ASC;";
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

// prepara l'elenco dei docenti per il filtro
$docenteFiltroOptionList = '<option value="0">tutti</option>';
// i docenti non devono essere solo quelli attivi: anche quelli degli anni scorsi
// foreach(dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC ; ")as $docente) {
foreach(dbGetAll("SELECT * FROM docente ORDER BY docente.cognome, docente.nome ASC ; ")as $docente) {
    $docenteFiltroOptionList .= ' <option value="'.$docente['id'].'" >'.$docente['cognome'].' '.$docente['nome'].'</option> ';
}

// prepara l'elenco delle materie per il filtro e per le materie del dialog
$materiaFiltroOptionList = '<option value="0">tutte</option>';
$materiaOptionList = '<option value="0"></option>';
foreach(dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC ; ")as $materia) {
    $materiaFiltroOptionList .= '<option value="'.$materia['id'].'" >'.$materia['nome'].'</option> ';
    $materiaOptionList .= '<option value="'.$materia['id'].'" >'.$materia['nome'].'</option> ';
}

// classi da 1 a 5
$annoCorsoFiltroOptionList = '<option value="0">T</option>';
for($i = 1; $i<=5; $i++) {
    $annoCorsoFiltroOptionList .= '<option value="'.$i.'" >'.$i.'</option> ';
}

// prepara l'elenco degli indirizzi per il filtro e per gli indirizi del dialog
$indirizzoCorsoFiltroOptionList = '<option value="0">tutti gli indirizzi</option>';
foreach(dbGetAll("SELECT * FROM indirizzo ORDER BY indirizzo.nome_breve ASC ; ")as $indirizzo) {
    $indirizzoCorsoFiltroOptionList .= '<option value="'.$indirizzo['id'].'" >'.$indirizzo['nome'].'</option> ';
}

// prepara l'elenco delle classi per il filtro
$nomeClasseFiltroOptionList = '<option value=""></option>';
foreach(dbGetAllValues("SELECT DISTINCT nome_classe FROM `piano_di_lavoro` INNER JOIN indirizzo ON piano_di_lavoro.indirizzo_id = indirizzo.id ORDER BY indirizzo.nome_breve, classe, sezione ASC; ")as $nome_classe) {
    $nomeClasseFiltroOptionList .= ' <option value="'.$nome_classe.'" >'.$nome_classe.'</option> ';
}

// possibili valori di stato
$statoFiltroOptionList = '<option value="0">tutti</option>';
$statoFiltroOptionList .= '<option value="'.'draft'.'" data-content="<span class=\'label label-warning\';\'>'.'draft'.'</span>">'.'draft'.'</option>';
$statoFiltroOptionList .= '<option value="'.'pubblicato'.'" data-content="<span class=\'label label-info\';\'>'.'pubblicato'.'</span>">'.'pubblicato'.'</option>';
$statoFiltroOptionList .= '<option value="'.'finale'.'" data-content="<span class=\'label label-success\';\'>'.'finale'.'</span>">'.'finale'.'</option>';
$statoFiltroOptionList .= '<option value="'.'annullato'.'" data-content="<span class=\'label label-danger\';\'>'.'annullato'.'</span>">'.'annullato'.'</option>';
$statoOptionList = '';
$statoOptionList .= '<option value="'.'draft'.'" data-content="<span class=\'label label-warning\';\'>'.'draft'.'</span>">'.'draft'.'</option>';
$statoOptionList .= '<option value="'.'pubblicato'.'" data-content="<span class=\'label label-info\';\'>'.'pubblicato'.'</span>">'.'pubblicato'.'</option>';
$statoOptionList .= '<option value="'.'finale'.'" data-content="<span class=\'label label-success\';\'>'.'finale'.'</span>">'.'finale'.'</option>';
$statoOptionList .= '<option value="'.'annullato'.'" data-content="<span class=\'label label-danger\';\'>'.'annullato'.'</span>">'.'annullato'.'</option>';

// elenco degli anni scolastici
$annoFiltroOptionList = '<option value="0">tutti</option>';
$annoOptionList = '';
$query = "	SELECT * FROM anno_scolastico ORDER BY anno_scolastico.id ASC;";
foreach(dbGetAll($query) as $annoRow) {
    $annoFiltroOptionList .= '<option value="'.$annoRow['id'].'" >'.$annoRow['anno'].'</option> ';
	$annoOptionList .= '<option value="'.$annoRow['id'].'">'.$annoRow['anno'].'</option>';
}



?>

<body >
<?php
require_once '../common/header-didattica.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lima4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-1 text-center">
		<span class="glyphicon glyphicon-list-alt" style="margin:5px"></span><br><b>Programma<br>Discipline</b>
		</div>
		<div class="col-md-1 text-center">
            <label class="col-sm-8 control-label" for="annoCorso">anno</label>
            <div class="text-center">
                <div class="col-sm-8"><select id="annoCorso_filtro" name="annoCorso_filtro" class="annoCorso_filtro selectpicker" data-style="btn-salmon" data-live-search="true" data-noneSelectedText="seleziona..." data-width="100%" ><?php echo $annoCorsoFiltroOptionList ?></select></div>
            </div>
		</div>
		<div class="col-md-2 text-center">
            <label class="col-sm-12 control-label" for="indirizzoCorso">classe</label>
            <div class="text-center">
                <div class="col-sm-12" ><select id="indirizzoCorso_filtro" name="indirizzoCorso_filtro" class="indirizzoCorso_filtro selectpicker" data-style="btn-salmon" data-live-search="true" data-noneSelectedText="seleziona..." data-width="100%" ><?php echo $indirizzoCorsoFiltroOptionList ?></select></div>
            </div>
		</div>
        <div class="col-md-3">
            <div class="text-center">
                <label class="col-sm-12 control-label" for="materia">Materia</label>
					<div class="col-sm-12" ><select id="materia_filtro" name="materia_filtro" class="materia_filtro selectpicker" data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="100%" >
                    <?php echo $materiaFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <!-- <div class="col-md-1">
            <div class="text-center">
				<label class="checkbox-inline">
                <strong>
					<input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloTemplateCheckBox" ><?php echoLabel('Template'); ?>
                </strong>
                </label>
            </div>
        </div>
		<div class="col-md-1 text-right">
            <div class="pull-right">
				<button class="btn btn-xs btn-lima4" onclick="pianoDiLavoroGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
            </div>
		</div> -->
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

<!-- <div class="panel-footer"></div> -->
</div>


</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/programma.js?v=<?php echo $__software_version; ?>"></script>
</body>
</html>
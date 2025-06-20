<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';
require_once '../common/_include_summernote.php';
require_once '../common/_include_bootstrap-notify.php';
ruoloRichiesto('docente','segreteria-didattica','dirigente');
?>

<!DOCTYPE html>
<html>
<head>
    <script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
	<title>Piani di Lavoro</title>

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
$classeOptionList = '';
for($i = 1; $i<=5; $i++) {
    $classeOptionList .= '<option value="'.$i.'" >'.$i.'</option> ';
}

// prepara l'elenco degli indirizzi per il filtro e per gli indirizi del dialog
$indirizzoFiltroOptionList = '<option value="0">tutti gli indirizzi</option>';
$indirizzoOptionList = '';
foreach(dbGetAll("SELECT * FROM indirizzo ORDER BY indirizzo.nome_breve ASC ; ")as $indirizzo) {
    $indirizzoFiltroOptionList .= '<option value="'.$indirizzo['id'].'" >'.$indirizzo['nome_breve'].'</option> ';
    $indirizzoOptionList .= '<option value="'.$indirizzo['id'].'" >'.$indirizzo['nome_breve'].'</option> ';
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

// elenco delle metodologie
$metodologiaOptionList = '';
foreach(dbGetAll("SELECT * FROM piano_di_lavoro_metodologia WHERE attivo IS true ORDER BY piano_di_lavoro_metodologia.nome ASC ; ")as $metodologia) {
    $metodologiaOptionList .= '<option value="'.$metodologia['id'].'" >'.$metodologia['nome'].'</option> ';
}

// elenco tic
$ticOptionList = '';
foreach(dbGetAll("SELECT * FROM piano_di_lavoro_tic WHERE attivo IS true ORDER BY piano_di_lavoro_tic.nome ASC ; ")as $tic) {
    $ticOptionList .= '<option value="'.$tic['id'].'" >'.$tic['nome'].'</option> ';
}

// elenco materiali
$materialeOptionList = '';
foreach(dbGetAll("SELECT * FROM piano_di_lavoro_materiale WHERE attivo IS true ORDER BY piano_di_lavoro_materiale.nome ASC ; ")as $materiale) {
    $materialeOptionList .= '<option value="'.$materiale['id'].'" >'.$materiale['nome'].'</option> ';
}

?>

<body >
<?php
require_once '../common/header-docente.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lima4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-1">
			<b>Piani di<br>Lavoro</b>
		</div>
        <div class="col-md-2">
            <div class="text-center">
                <label class="col-sm-9 control-label" for="anno">Anno</label>
					<div class="col-sm-9"><select id="anno_filtro" name="anno_filtro" class="anno_filtro selectpicker" data-style="btn-teal4" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $annoFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="text-center">
                <label class="col-sm-12 control-label" for="docente">Docente</label>
					<div class="col-sm-12"><select id="docente_filtro" name="docente_filtro" class="docente_filtro selectpicker" data-style="btn-lightblue4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $docenteFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <label class="col-sm-12 control-label" for="materia">Materia</label>
					<div class="col-sm-12"><select id="materia_filtro" name="materia_filtro" class="materia_filtro selectpicker" data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $materiaFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <!-- <div class="col-md-2">
            <div class="text-center">
                <label class="col-sm-12 control-label" for="stato">Stato</label>
					<div class="col-sm-12"><select id="stato_filtro" name="stato_filtro" class="stato_filtro selectpicker" data-style="btn-purple" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php //echo $statoFiltroOptionList ?>
					</select></div>
            </div>
        </div> -->
		<div class="col-md-2 text-center">
            <label class="col-sm-2 control-label" for="nomeClasse">classe</label>
            <div class="text-center">
                <div class="col-sm-8"><select id="nomeClasse_filtro" name="nomeClasse_filtro" class="nomeClasse_filtro selectpicker" data-style="btn-salmon" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" ><?php echo $nomeClasseFiltroOptionList ?></select></div>
            </div>
		</div>
        <div class="col-md-1">
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

<!-- <div class="panel-footer"></div> -->
</div>

<!-- Modal - Add/Update Record -->
<div class="modal fade" id="piano_di_lavoro_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" style="width: 80%;" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-orange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Piano di Lavoro</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group docente_selector">
                    <label class="col-sm-2 control-label" for="docente">Docente</label>
					<div class="col-sm-8"><select id="docente" name="docente" class="docente selectpicker" data-style="btn-success" data-live-search="true" data-width="70%"  <?php if (! haRuolo('dirigente')) {echo 'disabled';} ?> ><?php echo $docenteOptionList ?></select></div>
                </div>

                <div class="form-group materia_selector">
                    <label class="col-sm-2 control-label" for="materia">Materia</label>
					<div class="col-sm-8"><select id="materia" name="materia" class="materia selectpicker" data-style="btn-yellow4" data-live-search="true" data-width="70%" ><?php echo $materiaOptionList ?></select></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_data">Classe</label>
                    <div class="col-sm-2">
                        <select id="classe" name="classe" class="classe selectpicker" data-live-search="false" data-width="70%" > <?php echo $classeOptionList ?></select>
                    </div>

                    <div class="col-sm-2">
                        <select id="indirizzo" name="indirizzo" class="indirizzo selectpicker" data-width="70%" > <?php echo $indirizzoOptionList ?></select>
                    </div>

                    <div class="col-sm-2"><input type="text" id="sezione" placeholder="sezione" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label for="anno" class="col-sm-2 control-label">Anno Scolastico</label>
                    <div class="col-sm-3">
                        <select id="anno" name="anno" class="anno selectpicker" data-live-search="false" data-width="70%" > <?php echo $annoOptionList ?></select>
                    </div>
                    <label for="stato" class="col-sm-1 control-label">Stato</label>
                    <div class="col-sm-2">
                        <select id="stato" name="stato" class="stato selectpicker" data-live-search="false" data-width="70%" > <?php echo $statoOptionList ?></select>
                    </div>
                    <label for="template" class="col-sm-1 control-label"><?php echoLabel('Template'); ?></label>
                    <div class="col-sm-1"><input type="checkbox" id="template" style="vertical-align: middle;"></div>
                    <label for="clil" class="col-sm-1 control-label">Clil</label>
                    <div class="col-sm-1"><input type="checkbox" id="clil" style="vertical-align: middle;"></div>
                </div>
            </form>
            <hr>
<?php if(getSettingsValue('pianiDiLavoro','competenze', true)) : ?>
            <div class="form-group">
                <label for="competenze">competenze</label>
                <div class="summernote" rows="6" id="competenze" placeholder="competenze" ></div>
            </div>
            <hr>
<?php endif; ?>
			<form class="form-horizontal">
<?php if(getSettingsValue('pianiDiLavoro','metodologie', true)) : ?>
                <div class="form-group metodologia_selector">
                    <label class="col-sm-2 control-label" for="metodologia">Metodologie</label>
                    <div class="col-sm-10"><select id="metodologia" name="metodologia" class="metodologia selectpicker" multiple data-selected-text-format="count > 3" data-style="btn-yellow4" data-live-search="true" data-width="100%" ><?php echo $metodologiaOptionList ?></select></div>
                </div>
<?php endif; ?>
<?php if(getSettingsValue('pianiDiLavoro','materiali', true)) : ?>
                <div class="form-group materiale_selector">
                    <label class="col-sm-2 control-label" for="materiale">Materiali</label>
                    <div class="col-sm-10"><select id="materiale" name="materiale" class="materiale selectpicker" multiple data-selected-text-format="count > 3" data-style="btn-yellow4" data-live-search="true" data-width="100%" ><?php echo $materialeOptionList ?></select></div>
                </div>
<?php endif; ?>
<?php if(getSettingsValue('pianiDiLavoro','tic', true)) : ?>
                <div class="form-group tic_selector">
                    <label class="col-sm-2 control-label" for="tic">TIC</label>
                    <div class="col-sm-10"><select id="tic" name="tic" class="tic selectpicker" multiple data-selected-text-format="count > 3" data-style="btn-yellow4" data-live-search="true" data-width="100%" ><?php echo $ticOptionList ?></select></div>
                </div>
<?php endif; ?>
            </form>
<?php if(getSettingsValue('pianiDiLavoro','note_aggiuntive', true)) : ?>
            <hr>
            <div class="form-group">
                <label for="note_aggiuntive">note aggiuntive</label>
                <div class="summernote-small" rows="6" id="note_aggiuntive" placeholder="note_aggiuntive" ></div>
            </div>
<?php endif; ?>

            <div class="form-group" id="_error-piano_di_lavoro-part"><strong>
                <hr>
                <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                <div class="col-sm-9" id="_error-piano_di_lavoro"></div>
                </strong>
            </div>

            <input type="hidden" id="hidden_piano_di_lavoro_id">
            <input type="hidden" id="hidden_docente_id" value="<?php echo $__docente_id; ?>">
            <input type="hidden" id="hidden_anno_scolastico_id" value="<?php echo $__anno_scolastico_corrente_id; ?>">

            </div>
            <div class="panel-footer text-center">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="pianoDiLavoroSave()">Salva</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update Record -->

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/pianoDiLavoro.js?v=<?php echo $__software_version; ?>"></script>
</body>
</html>
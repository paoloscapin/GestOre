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
	<title>Modlistica Richieste</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<?php
require_once '../common/checkSession.php';

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';

ruoloRichiesto('dirigente','segreteria-docenti');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<style>
	#campi { font-family: Arial, Helvetica, sans-serif; border-collapse: collapse; width: 100%; }
	#campi td, #campi th { border: 1px solid #ddd; padding: 6px; }
	#campi tr:nth-child(even) { background-color: #f2f2f2; }
	#campi tr:hover { background-color: #ddd; }
	#campi th { padding-top: 6px; padding-bottom: 6px; text-align: left; background-color: #04AA6D; color: white; }
    .col1 { width: 25%; }
    .col2 { width: 75%; }
	.tick { margin-left: 0.65cm; text-indent: -0.65cm; }
	.btn-ar { color: #fff; padding: 10px 15px; margin: 20px 15px 10px 15px;
		background-image: radial-gradient(93% 87% at 87% 89%, rgba(0, 0, 0, 0.23) 0%, transparent 86.18%), radial-gradient(66% 66% at 26% 20%, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0) 69.79%, rgba(255, 255, 255, 0) 100%);
		box-shadow: inset -3px -3px 9px rgba(255, 255, 255, 0.25), inset 0px 3px 9px rgba(255, 255, 255, 0.3), inset 0px 1px 1px rgba(255, 255, 255, 0.6), inset 0px -8px 36px rgba(0, 0, 0, 0.3), inset 0px 1px 5px rgba(255, 255, 255, 0.6), 2px 19px 31px rgba(0, 0, 0, 0.2);
		border-radius: 12px; font-weight: bold; font-size: 14px; border: 0; user-select: none; -webkit-user-select: none; touch-action: manipulation; cursor: pointer; }
		.btn-approva { background-color: #1CAF43; }
		.btn-respingi { background-color: #F1003C; }
		.btn-waiting { background-color: #f2e829; }
		.btn-chiudi { background-color: #0ae4f0; }
		.btn-pendente { background-color: #777777; }
		.btn-label { padding: 5px 12px; border-radius: 8px; margin: 10px 5px; }
</style>
</head>

<?php
// possibili valori di stato
$statoFiltroOptionList = '<option value="0">tutti</option>';
$statoFiltroOptionList .= '<option value="'.'approvata'.'" data-content="<span class=\'label btn-approva\';\'>'.'approvata'.'</span>">'.'approvata'.'</option>';
$statoFiltroOptionList .= '<option value="'.'respinta'.'" data-content="<span class=\'label btn-respingi\';\'>'.'respinta'.'</span>">'.'respinta'.'</option>';
$statoFiltroOptionList .= '<option value="'.'chiusa'.'" data-content="<span class=\'label btn-chiudi\';\'>'.'chiusa'.'</span>">'.'chiusa'.'</option>';
$statoFiltroOptionList .= '<option value="'.'annullata'.'" data-content="<span class=\'label btn-waiting\';\'>'.'annullata'.'</span>">'.'annullata'.'</option>';
$statoFiltroOptionList .= '<option value="'.'pendente'.'" data-content="<span class=\'label btn-pendente\';\'>'.'pendente'.'</span>">'.'pendente'.'</option>';

// elenco degli anni scolastici
$annoFiltroOptionList = '<option value="0">tutti</option>';
$query = "	SELECT * FROM anno_scolastico ORDER BY anno_scolastico.id ASC;";
foreach(dbGetAll($query) as $annoRow) {
    $annoFiltroOptionList .= '<option value="'.$annoRow['id'].'" >'.$annoRow['anno'].'</option> ';
}

// prepara l'elenco dei tipi di richiesta per il filtro
$tipoFiltroOptionList = '<option value="0">tutti</option>';
foreach(dbGetAll("SELECT * FROM modulistica_template WHERE modulistica_template.valido = true ORDER BY modulistica_template.nome ASC; ")as $modulistica_template) {
    $tipoFiltroOptionList .= ' <option value="'.$modulistica_template['id'].'" >'.$modulistica_template['nome'].'</option> ';
}

// prepara l'elenco dei docenti per il filtro
$docenteFiltroOptionList = '<option value="0">tutti</option>';
foreach(dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC; ")as $docente) {
    $docenteFiltroOptionList .= ' <option value="'.$docente['id'].'" >'.$docente['cognome'].' '.$docente['nome'].'</option> ';
}

?>

<body >
<?php
if ($__utente_ruolo == 'segreteria-docenti') {
    require_once '../common/header-segreteria.php';
} else {
    require_once '../common/header-dirigente.php';
}
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lightblue4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-1">
			<span class="glyphicon glyphicon-folder-close"></span>&emsp;Modlistica Richieste
		</div>
        <div class="col-md-2">
            <div class="text-center">
                <label class="col-sm-2 control-label" for="anno">Anno</label>
					<div class="col-sm-8"><select id="anno_filtro" name="anno_filtro" class="anno_filtro selectpicker" data-style="btn-teal4" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $annoFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="text-center">
                <label class="col-sm-2 control-label" for="tipo">Tipo</label>
					<div class="col-sm-8"><select id="tipo_filtro" name="tipo_filtro" class="tipo_filtro selectpicker" data-style="btn-lightblue4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $tipoFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="text-center">
                <label class="col-sm-2 control-label" for="docente">Docente</label>
					<div class="col-sm-8"><select id="docente_filtro" name="docente_filtro" class="docente_filtro selectpicker" data-style="btn-lightblue4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $docenteFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="text-center">
                <label class="col-sm-2 control-label" for="stato">Stato</label>
					<div class="col-sm-8"><select id="stato_filtro" name="stato_filtro" class="stato_filtro selectpicker" data-style="btn-purple" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $statoFiltroOptionList ?>
					</select></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloApertiCheckBox" >Solo Aperti
				</label>
            </div>
        </div>
        <div class="col-md-1">
            <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloMieiCheckBox" >Solo Miei
				</label>
            </div>
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

<!-- Modal - Add/Update Record -->
<div class="modal fade" id="modulistica_richiesta_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lightblue4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Richiesta</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

            <div class="form-group">
                    <label class="col-sm-2 control-label" for="data">Data</label>
					<div class="col-sm-4"><span id="data" class="form-control">21/8/2019</span></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="docente">Docente</label>
					<div class="col-sm-4"><span id="docente" class="form-control"></span></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="anno">Anno</label>
					<div class="col-sm-2"><span id="anno" class="form-control">1999</span></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="valori">Valori</label>
                    <div class="col-sm-10" id="tabella"></div>
                    <hr>
                </div>

                <div class="form-group" style="text-align: center" id="_status-marker">
                    <hr>
                    <div class="form-group">
                        <span id="_approvata-marker" class="btn-ar btn-approva btn-label">Approvata</span>
                        <span id="_respinta-marker" class="btn-ar btn-respingi btn-label">Respinta</span>
                        <span id="_annullata-marker" class="btn-ar btn-label btn-label">Annullata</span>
                        <span id="_in_lavorazione-marker" class="btn-ar btn-pendente btn-label">In Lavorazione</span>
                        <span id="_chiusa-marker" class="btn-ar btn-chiudi btn-label">Chiusa</span>
                    </div>
                    <div class="form-group" id="_messaggio_approvazione-part">
                        <label class="col-sm-2 control-label" for="messaggio_approvazione">messaggio</label>
	    				<div class="col-sm-10"><span id="messaggio_approvazione" class="form-control"></span></div>
                    </div>
                </div>

                <div class="form-group" style="text-align: center" id="_approva-part">
                    <hr>
                    <button type="button" class="btn-ar btn-approva" onclick="modulisticaRichiestaAggiorna('approva')">Approva</button>
				    <button type="button" class="btn-ar btn-respingi" onclick="modulisticaRichiestaAggiorna('respingi')">Respingi</button>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="messaggio">messaggio</label>
                        <div class="col-sm-10"><input type="text" id="messaggio" class="form-control" /></div>
                    </div>
                </div>

                <div class="form-group" style="text-align: center" id="chiudi-part">
                    <hr>
				    <button type="button" class="btn-ar btn-chiudi" onclick="modulisticaRichiestaAggiorna('chiudi')">Chiudi Richiesta</button>
                </div>

                <input type="hidden" id="hidden_modulistica_richiesta_id">
                <input type="hidden" id="hidden_modulistica_richiesta_uuid">
                <input type="hidden" id="hidden_modulistica_messaggio">
                <input type="hidden" id="hidden_anno_scolastico_id" value="<?php echo $__anno_scolastico_corrente_id; ?>">
                <input type="hidden" id="hidden_utente_ruolo" value="<?php echo $__utente_ruolo; ?>">
                <input type="hidden" id="hidden_useremail" value="<?php echo $__useremail; ?>">

			</form>

            </div>
            <div class="panel-footer text-center">
                <button type="button" class="btn btn-default" data-dismiss="modal">Ritorna</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update Record -->

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/modulisticaRichiesta.js?v=<?php echo $__software_version; ?>"></script>
</body>
</html>
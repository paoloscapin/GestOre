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
require_once '../common/connect.php';
ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti', 'segreteria-didattica', 'docente');
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
.sportelli-report-heading {
    padding: 14px 18px;
}
.sportelli-report-toolbar {
    display: grid;
    grid-template-columns: minmax(210px, 1.15fr) repeat(5, minmax(150px, 1fr)) auto;
    gap: 12px 16px;
    align-items: end;
}
.sportelli-report-title {
    align-self: center;
    font-weight: 700;
    color: #1f2f3a;
    white-space: nowrap;
}
.sportelli-report-title .glyphicon {
    margin-right: 8px;
}
.sportelli-report-field label {
    display: block;
    margin-bottom: 4px;
    font-size: 12px;
    line-height: 1;
    text-transform: uppercase;
    color: #243746;
}
.sportelli-report-field .bootstrap-select,
.sportelli-report-field .bootstrap-select > .dropdown-toggle {
    width: 100% !important;
}
.sportelli-report-actions {
    align-self: end;
    white-space: nowrap;
}
.sportelli-report-actions .btn {
    min-width: 58px;
}
#sportelliReportExportOverlay {
    position: fixed;
    z-index: 99999;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, .42);
}
#sportelliReportExportOverlay .export-wait-box {
    width: min(420px, calc(100vw - 32px));
    padding: 24px 28px;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 14px 40px rgba(0, 0, 0, .28);
    text-align: center;
}
#sportelliReportExportOverlay .export-title {
    margin-bottom: 10px;
    font-size: 20px;
    font-weight: 700;
    color: #263747;
}
#sportelliReportExportOverlay .export-detail {
    margin-bottom: 14px;
    color: #52616e;
}
#sportelliReportExportOverlay .export-percent {
    margin-bottom: 10px;
    font-size: 28px;
    font-weight: 700;
    color: #1f6f9f;
}
#sportelliReportExportOverlay .progress {
    margin-bottom: 0;
}
@media (max-width: 1200px) {
    .sportelli-report-toolbar {
        grid-template-columns: repeat(3, minmax(180px, 1fr));
    }
    .sportelli-report-title,
    .sportelli-report-actions {
        grid-column: span 3;
    }
}
@media (max-width: 768px) {
    .sportelli-report-toolbar {
        grid-template-columns: 1fr;
    }
    .sportelli-report-title,
    .sportelli-report-actions {
        grid-column: auto;
    }
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
} elseif (! empty($__utente_ruolo) && $__utente_ruolo == 'admin') {
    require_once '../common/header-admin.php';
} elseif (! empty($__utente_ruolo) && $__utente_ruolo == 'segreteria-didattica') {
    require_once '../common/header-didattica.php';
} else {
    require_once '../common/header-segreteria.php';
}
?>

<!-- Content Section -->
<div class="container-fluid">
<div class="panel panel-orange4">
<div class="panel-heading sportelli-report-heading">
    <div class="sportelli-report-toolbar">
        <div class="sportelli-report-title">
            <span class="glyphicon glyphicon-retweet"></span>Report sportelli effettuati
        </div>
        <div class="sportelli-report-field">
            <label for="materia_filtro">Materia</label>
            <select id="materia_filtro" name="materia_filtro" class="materia_filtro selectpicker" data-style="btn-lima4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="100%">
                <?php echo $materiaFiltroOptionList ?>
            </select>
        </div>
        <div class="sportelli-report-field">
            <label for="docente_filtro">Docente</label>
            <select id="docente_filtro" name="docente_filtro" class="docente_filtro selectpicker" data-style="btn-lightblue4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="100%">
                <?php echo $docenteFiltroOptionList ?>
            </select>
        </div>
        <div class="sportelli-report-field">
            <label for="periodo_filtro">Periodo</label>
            <select id="periodo_filtro" class="selectpicker" data-style="btn-yellow4" data-width="100%">
                <option value="tutti">Tutti</option>
                <option value="futuri">Solo futuri</option>
                <option value="passati">Solo passati</option>
            </select>
        </div>
        <div class="sportelli-report-field">
            <label for="iscritti_filtro">Iscritti</label>
            <select id="iscritti_filtro" class="selectpicker" data-style="btn-yellow4" data-width="100%">
                <option value="tutti">Tutti</option>
                <option value="con_iscritti">Con studenti iscritti</option>
                <option value="senza_iscritti">Senza studenti iscritti</option>
            </select>
        </div>
        <div class="sportelli-report-field">
            <label for="firmato_filtro">Firma</label>
            <select id="firmato_filtro" class="selectpicker" data-style="btn-salmon" data-width="100%">
                <option value="tutti">Tutti</option>
                <option value="firmati">Firmati</option>
                <option value="non_firmati">Non firmati</option>
            </select>
        </div>
        <div class="sportelli-report-actions">
            <div class="btn-group btn-group-sm" role="group" aria-label="Esporta report sportelli">
                <button type="button" class="btn btn-danger" onclick="sportelloReportEffettuatiExport('pdf')" data-toggle="tooltip" title="Esporta PDF">
                    <span class="glyphicon glyphicon-file"></span> PDF
                </button>
                <button type="button" class="btn btn-success" onclick="sportelloReportEffettuatiExport('xlsx')" data-toggle="tooltip" title="Esporta Excel">
                    <span class="glyphicon glyphicon-list-alt"></span> XLS
                </button>
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
<div id="sportelliReportExportOverlay">
    <div class="export-wait-box">
        <div class="export-title">Preparazione export</div>
        <div class="export-detail" id="sportelliReportExportDetail">Sto generando il file. Attendi qualche istante...</div>
        <div class="export-percent" id="sportelliReportExportPercent">0%</div>
        <div class="progress progress-striped active">
            <div id="sportelliReportExportProgress" class="progress-bar progress-bar-info" style="width:0%;">0%</div>
        </div>
    </div>
</div>

<!-- Custom JS file MUST be here because of toggle -->
<script type="text/javascript" src="js/sportelloReportEffettuati.js?v=<?php echo filemtime(__DIR__ . '/js/sportelloReportEffettuati.js'); ?>&r=export-inline"></script>

</body>
</html>

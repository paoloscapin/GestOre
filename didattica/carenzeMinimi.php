<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-notify.php';
ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');
$carenzeMinimiDocenteDaParametro = applicaDocenteDaParametroSeAutorizzato();
$carenzeMinimiRuoloEffettivo = $__utente_ruolo ?? '';
$carenzeMinimiVistaDocente = in_array($carenzeMinimiRuoloEffettivo, ['docente'], true) || $carenzeMinimiDocenteDaParametro != null;

if (!getSettingsValue('config', 'carenzeObiettiviMinimi', false)) {
    redirect("/error/unauthorized.php");
}

if (!getSettingsValue('carenzeObiettiviMinimi', 'visibile_docenti', false)) {
    ruoloRichiesto('segreteria-didattica');
}
?>

<!DOCTYPE html>
<html>

<head>
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <title>Carenze Studenti</title>

    <style>
        .icon-play {
            background-image: url('../img/pdf-256.png');
            background-size: cover;
            display: inline-block;
            height: 16px;
            width: 16px;
        }
    </style>
</head>

<?php

$modificheDisabilitate = "";

$id_docente_utente = 0;
if ($carenzeMinimiDocenteDaParametro != null && intval($__docente_id ?? 0) > 0) {
    $id_docente_utente = intval($__docente_id);
} elseif ($__utente_ruolo == 'docente') {
    $query = "SELECT * from docente WHERE docente.username='" . $__username . "'";
    $result = dbGetFirst($query);
    if ($result != null) {
        $id_docente_utente = $result['id'];
    }
}

// prepara l'elenco delle materie per il filtro e per le materie del dialog
$materiaFiltroOptionList = '<option value="0">Tutte</option>';
$materiaOptionList = '<option value="0"></option>';
foreach (dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC ; ") as $materia) {
    $materiaFiltroOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
    $materiaOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
}

// classi 
$classiFiltroOptionList = '<option value="0">T</option>';
$classiOptionList = '<option value="0">selezionare classe</option>';
foreach (dbGetAll("SELECT * FROM classi WHERE attiva=1 ORDER BY classi.classe ASC ; ") as $classe) {
    $classiFiltroOptionList .= '<option value="' . $classe['id'] . '" >' . $classe['classe'] . '</option> ';
    $classiOptionList .= '<option value="' . $classe['id'] . '" >' . $classe['classe'] . '</option> ';
}

$query = "SELECT COUNT(id) FROM carenze WHERE id_anno_scolastico=" . $__anno_scolastico_corrente_id;
$count = dbGetValue($query);
if ($count == 0) {
    $anno_carenze = $__anno_scolastico_scorso_id;
} else {
    $anno_carenze = $__anno_scolastico_corrente_id;
}

// anni
$anniFiltroOptionList = '<option value="0">Tutti</option>';
$anniOptionList      = '<option value="0">Selezionare anno</option>';

foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC;") as $anno) {
    $selected = ($anno['id'] == $anno_carenze) ? ' selected' : '';
    $option   = '<option value="' . htmlspecialchars($anno['id']) . '"' . $selected . '>' . htmlspecialchars($anno['anno']) . '</option>';

    $anniFiltroOptionList .= $option;
    $anniOptionList      .= $option;
}

// anno 
$annoFiltroOptionList = '<option value="0">T</option>';
$annoOptionList = '<option value="0">selezionare anno</option>';
$annoFiltroOptionList .= '<option value="1">1</option> ';
$annoOptionList .= '<option value="1">Classi prime</option>';
$annoFiltroOptionList .= '<option value="2">2</option> ';
$annoOptionList .= '<option value="2">Classi seconde</option>';
$annoFiltroOptionList .= '<option value="3">3</option> ';
$annoOptionList .= '<option value="3">Classi terze/option>';
$annoFiltroOptionList .= '<option value="4">4</option> ';
$annoOptionList .= '<option value="4">Classi quarte</option>';
$annoFiltroOptionList .= '<option value="5">5</option> ';
$annoOptionList .= '<option value="5">Classi quinte</option>';


// prepara l'elenco dei docenti
$docentiFiltroOptionList = '<option value="0">Tutti</option>';
$docentiOptionList = '<option value="0"></option>';
foreach (dbGetAll("SELECT * FROM docente WHERE docente.attivo=1 ORDER BY docente.cognome ASC ; ") as $docente) {
    if (($docente['id']) == $id_docente_utente) {
        $docentiFiltroOptionList .= '<option value="' . $docente['id'] . '" selected>' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
        $docentiOptionList .= '<option value="' . $docente['id'] . '" selected>' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
    } else {
        $docentiFiltroOptionList .= '<option value="' . $docente['id'] . '" >' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
        $docentiOptionList .= '<option value="' . $docente['id'] . '" >' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
    }
}

// studenti
$studentiFiltroOptionList = '<option value="0">T</option>';
$studentiOptionList = '<option value="0">selezionare studente</option>';
foreach (
    dbGetAll("
    SELECT studente.*,
           studente_frequenta.id_classe AS id_classe
    FROM studente
    INNER JOIN studente_frequenta 
        ON studente.id = studente_frequenta.id_studente
    WHERE studente.attivo = 1
      AND studente_frequenta.id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
    ORDER BY studente.cognome, studente.nome ASC
") as $studente
) {
    $query2 = "SELECT classe from classi where id=" . $studente['id_classe'];
    $classe = dbGetValue($query2);
    $studentiFiltroOptionList .= '<option value="' . $studente['id'] . '" >' . $studente['cognome'] . ' ' . $studente['nome'] . ' - ' . $classe . '</option> ';
    $studentiOptionList .= '<option value="' . $studente['id'] . '" >' . $studente['cognome'] . ' ' . $studente['nome'] . ' - ' . $classe . '</option> ';
}

?>

<body>
    <input type="hidden" id="hidden_docente_id" value="<?php echo $id_docente_utente ?>">
    <?php
    if ($carenzeMinimiVistaDocente) {
        require_once '../common/header-docente.php';
    } else if (haRuolo('segreteria-didattica')) {
        require_once '../common/header-didattica.php';
    }
    ?>
    <style>
        .col-md-2-custom {
            width: 20%;
        }

        .col-md-1-custom {
            width: 10%;
        }

        .col-md-1-2-custom {
            width: 12%;
        }

        .col-md-1-5-custom {
            width: 15%;
        }

        .col-md-0-5-custom {
            width: 5%;
        }

        #progressOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            /* Sfondo semi-trasparente */
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        #progressContent {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            text-align: center;
            width: 300px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }

        #progressBarContainer {
            background: #ddd;
            border-radius: 10px;
            overflow: hidden;
            height: 25px;
            margin-top: 10px;
        }

        #progressBar {
            background: green;
            width: 0%;
            height: 100%;
            color: white;
            text-align: center;
            line-height: 25px;
            transition: width 0.3s;
        }

        .toggle.btn {
            width: auto !important;
            min-width: 120px;
            /* regola a seconda della lunghezza del testo */
            padding: 0 10px;
            white-space: nowrap;
        }

        .toggle.btn .toggle-on {
            background-color: blue;
            padding-left: 10px;
            padding-right: 10px;
        }

        .toggle.btn .toggle-off {
            background-color: red;
            padding-left: 10px;
            padding-right: 10px;
        }

        .carenze-docente-tabs {
            margin-bottom: 12px;
        }

        .carenze-toolbar {
            align-items: flex-end;
            display: flex;
            flex-wrap: wrap;
            gap: 12px 16px;
            padding: 4px 6px 10px;
        }

        .carenze-toolbar-title {
            align-self: center;
            font-weight: 700;
            line-height: 1.25;
            min-width: 120px;
            text-align: center;
        }

        .carenze-toolbar-title .glyphicon {
            display: block;
            margin-bottom: 5px;
        }

        .carenze-filter {
            min-width: 110px;
        }

        .carenze-filter-xs {
            min-width: 70px;
            width: 76px;
        }

        .carenze-filter-sm {
            min-width: 98px;
            width: 108px;
        }

        .carenze-filter-md {
            min-width: 170px;
        }

        .carenze-filter-lg {
            flex: 1 1 260px;
            min-width: 230px;
        }

        .carenze-filter label,
        .carenze-actions label {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
            text-align: center;
            white-space: nowrap;
        }

        .carenze-filter .bootstrap-select,
        .carenze-filter .btn-group {
            width: 100% !important;
        }

        .carenze-actions {
            align-items: flex-end;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-left: auto;
        }

        .carenze-action-stack {
            text-align: center;
        }

        .carenze-action-stack .btn,
        .carenze-actions .btn-file {
            min-height: 30px;
        }

        .carenze-view-toggle {
            min-width: 150px;
            text-align: center;
        }

        .carenze-view-toggle label {
            line-height: 1.2;
            margin-bottom: 5px;
        }

        .carenze-view-toggle .toggle {
            min-width: 142px !important;
        }

        .carenze-view-toggle .toggle-on,
        .carenze-view-toggle .toggle-off {
            font-weight: 700;
            padding-left: 12px;
            padding-right: 12px;
        }

        .carenze-coord-actions {
            margin: 10px 0;
            text-align: right;
        }

        .carenze-coord-summary {
            background: #eef8fc;
            border: 1px solid #b9ddeb;
            border-radius: 4px;
            color: #0b4f71;
            margin: 8px 0 12px;
            padding: 9px 12px;
        }

        .carenze-coord-table > tbody > tr.carenze-coord-success > td {
            background: #dff3d7;
        }

        .carenze-coord-table > tbody > tr.carenze-coord-warning > td {
            background: #fff0b8;
        }

        .carenze-coord-table > tbody > tr.carenze-coord-danger > td {
            background: #ffd8d8;
        }

        .carenze-coord-table > tbody > tr.carenze-coord-info > td {
            background: #d9edf7;
        }
    </style>
    <!-- OVERLAY con progress bar -->
    <div id="progressOverlay" style="display: none;">
        <div id="progressContent">
            <p>Operazione in corso...</p>
            <div id="progressBarContainer">
                <div id="progressBar">0%</div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="panel panel-lima4">
            <div class="panel-heading">
                <div class="carenze-toolbar">
                    <div class="carenze-toolbar-title">
                        <span class="glyphicon glyphicon-list-alt"></span>
                        Elenco<br>Carenze
                    </div>
                    <div class="carenze-filter carenze-filter-sm">
                        <label class="control-label" for="classe_filtro">Classe</label>
                        <select id="classe_filtro" name="classe_filtro"
                                class="classe_filtro selectpicker" data-style="btn-salmon" data-live-search="true"
                                data-noneSelectedText="seleziona..."
                                data-width="100%"><?php echo $classiFiltroOptionList ?></select>
                    </div>
                    <div class="carenze-filter carenze-filter-xs">
                        <label class="control-label" for="anno_filtro">Anno</label>
                        <select id="anno_filtro" name="anno_filtro"
                                class="anno_filtro selectpicker" data-style="btn-salmon" data-live-search="true"
                                data-noneSelectedText="seleziona..."
                                data-width="100%"><?php echo $annoFiltroOptionList ?></select>
                    </div>
                    <div class="carenze-filter carenze-filter-lg">
                        <label class="control-label" for="materia_filtro">Materia</label>
                        <select id="materia_filtro" name="materia_filtro"
                                class="mamteria_filtro selectpicker" data-style="btn-salmon" data-live-search="true"
                                data-noneSelectedText="seleziona..."
                                data-width="100%"><?php echo $materiaFiltroOptionList ?></select>
                    </div>
                    <?php
                    if (!$carenzeMinimiVistaDocente && haRuolo('segreteria-didattica')) {
                        echo '
                    <div class="carenze-filter carenze-filter-md">
                            <label class="control-label" for="docente_filtro">Docente</label>
                            <select id="docente_filtro" name="docente_filtro"
                                    class="docente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="100%">';
                        echo $docentiFiltroOptionList;
                        echo '
                                </select>
                    </div>
                    ';
                    }
                    ?>
                    <div class="carenze-filter carenze-filter-lg">
                        <label class="control-label" for="studente_filtro">Studente</label>
                        <select id="studente_filtro" name="studente_filtro"
                                    class="studente_filtro selectpicker" data-style="btn-yellow4"
                                    data-live-search="true" data-noneSelectedText="seleziona..." data-width="100%">
                                    <?php echo $studentiFiltroOptionList ?>
                        </select>
                    </div>

                    <div class="carenze-filter carenze-filter-md">
                        <label class="control-label" for="anni_filtro">Anno scolastico</label>
                        <select id="anni_filtro" style="margin:0;" name="anni_filtro"
                                    class="anni_filtro selectpicker"
                                    data-style="btn-yellow4"
                                    data-live-search="true"
                                    data-noneSelectedText="Seleziona..."
                                    data-width="100%">
                                    <?php echo $anniFiltroOptionList ?>
                        </select>
                    </div>
                    <?php
                    if ($carenzeMinimiVistaDocente && !getSettingsValue('carenzeObiettiviMinimi', 'docente_vede_solo_le_sue', false)) {
                        echo '
                    <div class="carenze-view-toggle">
                        <label class="control-label" for="docenteScopeCheckBox">Vista docente</label><br>
                        <input type="checkbox" data-toggle="toggle" data-size="small" data-width="142" data-height="30"
                            data-onstyle="primary" data-offstyle="default"
                            id="docenteScopeCheckBox" data-on="Aperte + mie" data-off="Tutte" checked>
                    </div>
                        ';
                    }
                    ?>
                    <?php
                    if (!$carenzeMinimiVistaDocente && ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica')))) {
                        echo '
                    <div class="carenze-actions">
                    <div class="carenze-action-stack">
                        <label class="control-label">Azioni</label>
                        <button class="btn btn-xs btn-lima4" onclick="carenzeGetDetails(-1)" data-toggle="tooltip" title="Aggiungi carenza">
                            <span class="glyphicon glyphicon-plus"></span>
                        </button>
                        <button id="genera_btn" type="button" class="btn btn-xs btn-lima4" data-toggle="tooltip" title="Genera i PDF di tutte le carenze">
                            <span class="glyphicon glyphicon-fire"></span> Genera
                        </button>
                        <button id="send_btn" type="button" class="btn btn-xs btn-lima4" data-toggle="tooltip" title="Invia mail delle carenze">
                            <span class="glyphicon glyphicon-send"></span> Mail
                        </button>
                    </div>
                    <div class="carenze-action-stack">
                        <label class="control-label">&nbsp;</label>
                        <label id="import_btn" class="btn btn-xs btn-lima4 btn-file" data-toggle="tooltip" title="Importa le carenze">
                            <span class="glyphicon glyphicon-upload"></span> Importa<input type="file" id="file_select_id" style="display: none;">
                        </label>
                    </div>
                    <div class="carenze-action-stack">
                        <label class="control-label">&nbsp;</label>
                        <label id="export_btn" class="btn btn-xs btn-lima4 btn-file" data-toggle="tooltip"
                            title="Esporta le carenze"><span id="file_export_id"
                                class="glyphicon glyphicon-download"></span> Esporta</label>
                    </div>
                    <div class="carenze-action-stack">
                        <label class="control-label">Filtro</label>
                        <label class="checkbox-inline">
                            <input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary"
                                id="daValidareCheckBox" data-on="Tutte" data-off="Solo da validare">
                        </label>
                    </div>
                    </div>
                    ';
                    }
                    ?>
                </div>
                    <div class="panel-body">
                        <div class="row" style="margin-bottom:10px;">
                            <div class="col-md-12 text-center" id='result_text'>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <?php if ($carenzeMinimiVistaDocente) { ?>
                                    <ul class="nav nav-tabs carenze-docente-tabs" role="tablist">
                                        <li role="presentation" class="active">
                                            <a href="#tab-carenze-docente" aria-controls="tab-carenze-docente" role="tab" data-toggle="tab">
                                                Carenze docente
                                            </a>
                                        </li>
                                        <li role="presentation">
                                            <a href="#tab-carenze-coordinatore" aria-controls="tab-carenze-coordinatore" role="tab" data-toggle="tab">
                                                Classe coordinata
                                            </a>
                                        </li>
                                    </ul>
                                    <div class="tab-content">
                                        <div role="tabpanel" class="tab-pane active" id="tab-carenze-docente">
                                            <div class="records_content"></div>
                                        </div>
                                        <div role="tabpanel" class="tab-pane" id="tab-carenze-coordinatore">
                                            <div class="carenze-coord-actions">
                                                <button type="button" class="btn btn-danger btn-sm" id="coord_export_pdf_btn">
                                                    <span class="glyphicon glyphicon-file"></span> PDF
                                                </button>
                                                <button type="button" class="btn btn-success btn-sm" id="coord_export_xlsx_btn">
                                                    <span class="glyphicon glyphicon-list-alt"></span> XLS
                                                </button>
                                            </div>
                                            <div class="coordinatore_records_content"></div>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <div class="records_content"></div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="panel-footer"></div> -->
                </div>

                <!-- Modal - Add/Update Record -->
                <div class="modal fade" id="carenza_modal" data-backdrop="static" tabindex="-1" role="dialog"
                    aria-labelledby="myModalLabel">
                    <div class="modal-dialog modal-lg" style="margin:auto;width:%30" role="document">
                        <div class="modal-content">
                            <div class="modal-body">
                                <div class="panel panel-orange4">
                                    <div class="panel-heading">
                                        <h3 class="modal-title" style="text-align:center" id="myModalLabel">Carenza
                                            Studente
                                        </h3>
                                    </div>
                                    <div class="panel-body">
                                        <form class="form-horizontal">

                                            <div class="form-group studente_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    for="categoria">Studente</label>
                                                <div class="col-sm-10"><select id="studente" name="studente"
                                                        class="studente selectpicker" data-style="btn-yellow4"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        <?php echo $modificheDisabilitate ?> data-width="100%">
                                                        <?php echo $studentiFiltroOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group materia_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    accesskey="" for="materia">Materia</label>
                                                <div class="col-sm-10"><select id="materia" name="materia"
                                                        class="materia selectpicker" data-style="btn-yellow4"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        <?php echo $modificheDisabilitate ?> data-width="100%">
                                                        <?php echo $materiaOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group" id="_error-carenza-part">
                                                <strong>
                                                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                                    <div class="col-sm-9" id="error-carenza"></div>
                                                </strong>
                                            </div>
                                            <input type="hidden" id="hidden_carenza_id">
                                            <input type="hidden" id="hidden_anno_carenza_id">
                                        </form>
                                    </div>
                                </div>
                                <div class="panel-footer text-center">
                                    <?php

                                    if (!$carenzeMinimiVistaDocente && ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica')))) {
                                        echo '
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                    <button type="button" class="btn btn-primary" onclick="carenzaSave()">Salva</button>
                                    ';
                                    } else if ($carenzeMinimiVistaDocente) {
                                        echo '
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                                    ';
                                    }

                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- // Modal - Add/Update Record -->

        </div>

        <!-- Custom JS file -->
        <script type="text/javascript" src="js/carenze.js?v=<?php echo $__software_version; ?>&a=<?php echo $anno_carenze; ?>"></script>
</body>

</html>

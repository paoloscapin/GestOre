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
applicaDocenteDaParametroSeAutorizzato();
?>

<!DOCTYPE html>
<html>

<head>
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <title>Programmi Svolti</title>

    <style>
        .icon-play {
            background-image: url('../img/pdf-256.png');
            background-size: cover;
            display: inline-block;
            height: 16px;
            width: 16px;
        }

        .toggle.btn {
            width: auto !important;
            min-width: 160px;
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

        .programma-preview-row {
            display: none;
            margin-top: 8px;
        }

        .programma-preview-row.is-active {
            display: block;
        }

        #programma_modal.programma-editing-mode .classe_selector,
        #programma_modal.programma-editing-mode .docente_selector,
        #programma_modal.programma-editing-mode .materia_selector {
            display: none;
        }

        #programma_modal.programma-editing-mode form.form-horizontal > .form-group,
        #programma_modal.programma-editing-mode #quinta_programma_fields_wrap > .form-group,
        #programma_modal.programma-editing-mode .container-fluid,
        #programma_modal.programma-editing-mode .panel-footer {
            display: none;
        }

        #programma_modal.programma-editing-mode .form-group.programma-active-edit-group,
        #programma_modal.programma-editing-mode .programma-preview-row.is-active {
            display: block;
        }

        #programma_modal.programma-editing-mode #quinta_programma_fields_wrap,
        #programma_modal.programma-editing-mode #quinta_programma_fields_wrap > .form-group.programma-active-edit-group,
        #programma_modal.programma-editing-mode #quinta_programma_fields_wrap > .programma-preview-row.is-active {
            display: block;
        }

        #modulo_modal.programma-editing-mode .modulo_ordine_group,
        #modulo_modal.programma-editing-mode .modulo_titolo_group {
            display: none;
        }

        #modulo_modal.programma-editing-mode #contenuto_preview_row .col-sm-2 {
            margin-top: -96px;
        }

        #modulo_modal.programma-editing-mode #contenuto_preview_top_actions {
            margin-top: -36px;
            margin-bottom: 6px;
        }

        .programma-preview-side {
            background: #eef5fd;
            border: 1px solid #d6e4f3;
            border-radius: 6px;
            padding: 10px;
            min-height: 100%;
        }

        .programma-preview-side .title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #35608d;
            margin-bottom: 6px;
            letter-spacing: .4px;
        }

        .programma-preview-side .hint {
            font-size: 12px;
            color: #4e647a;
            margin-bottom: 0;
            line-height: 1.5;
        }

        .programma-guide-example {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #d6e4f3;
        }

        .programma-guide-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #35608d;
            margin-bottom: 3px;
        }

        .programma-guide-code {
            display: block;
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
            color: #1f3550;
            white-space: pre-line;
        }

        .programma-syntax-box {
            border: 1px solid #d9e7f5;
            border-radius: 8px;
            background: #f8fbff;
            padding: 10px 12px;
        }

        .programma-preview-render {
            background: #fff;
            border: 1px solid #dbe7f1;
            border-radius: 6px;
            padding: 10px 12px;
            min-height: 44px;
            max-height: 260px;
            overflow-y: auto;
        }

        .programma-preview-render p {
            margin: 0 0 8px 0;
        }

        .programma-preview-render ul {
            margin: 0 0 6px 18px;
            padding-left: 12px;
        }

        .programma-preview-render li {
            margin-bottom: 4px;
        }

        .programma-preview-lines {
            margin-top: 8px;
            padding: 8px 10px;
            border-radius: 6px;
            background: #f3f6fa;
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
            color: #4f5d6b;
            max-height: 180px;
            overflow-y: auto;
        }

        .programma-preview-line {
            white-space: pre-wrap;
            margin-bottom: 2px;
        }

        .programma-preview-line-active {
            background: #e6f2ff;
            border-radius: 4px;
            padding: 2px 4px;
            color: #1d4f80;
            font-weight: 600;
        }

        .programma-preview-line-empty {
            color: #8a97a4;
            font-style: italic;
        }

        .programma-preview-crlf {
            display: inline-block;
            margin-left: 4px;
            color: #1f7acc;
            font-weight: 700;
        }

        .programma-preview-actions {
            margin-top: 10px;
            text-align: right;
        }

        #programma_modal .modal-dialog,
        #modulo_modal .modal-dialog {
            width: 94vw;
            max-width: 1700px;
        }
    </style>
</head>

<?php
function renderProgrammaSyntaxPreview(string $fieldId, string $previewId, string $linesId): string
{
    return '
        <div id="' . htmlspecialchars($fieldId) . '_preview_row" class="form-group programma-preview-row" data-preview-field="' . htmlspecialchars($fieldId) . '">
            <div class="col-sm-2">
                <div class="programma-preview-side">
                    <div class="title">Sintassi</div>
                    <div class="hint">Regole applicate:</div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Titolo senza pallino</div>
                        <span class="programma-guide-code">>> Metodo scientifico</span>
                        <div class="programma-guide-label">Appare</div>
                        <span class="programma-guide-code">Metodo scientifico</span>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Titolo automatico se scrivi tutto in maiuscolo</div>
                        <span class="programma-guide-code">METODO SCIENTIFICO</span>
                        <div class="programma-guide-label">Appare</div>
                        <span class="programma-guide-code">METODO SCIENTIFICO</span>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Ogni riga nuova crea una voce con pallino</div>
                        <span class="programma-guide-code">Le coordinate geografiche.
I moti della Terra.</span>
                        <div class="programma-guide-label">Appare</div>
                        <span class="programma-guide-code">• Le coordinate geografiche
• I moti della Terra</span>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Un punto singolo puo separare due voci sulla stessa riga</div>
                        <span class="programma-guide-code">Sistema Solare. Galassie.</span>
                        <div class="programma-guide-label">Appare</div>
                        <span class="programma-guide-code">• Sistema Solare
• Galassie</span>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">`..`, `...`, `....` restano punti veri nel testo</div>
                        <span class="programma-guide-code">A.. Rossi
ecc...
approfondimento.... finale</span>
                        <div class="programma-guide-label">Appare</div>
                        <span class="programma-guide-code">• A. Rossi
• ecc...
• approfondimento.... finale</span>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Se una riga finisce con `:` la riga dopo diventa dettaglio</div>
                        <span class="programma-guide-code">Metodologie:
lavoro di gruppo</span>
                        <div class="programma-guide-label">Appare</div>
                        <span class="programma-guide-code">• Metodologie:
  • lavoro di gruppo</span>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Sottopunti anche con `-`, `*`, `>`, `--` o almeno due spazi</div>
                        <span class="programma-guide-code">Metodologie:
- lavoro di gruppo
  problem solving
* cooperative learning</span>
                        <div class="programma-guide-label">Appare</div>
                        <span class="programma-guide-code">• Metodologie:
  • lavoro di gruppo
  • problem solving
  • cooperative learning</span>
                    </div>
                </div>
            </div>
            <div class="col-sm-10">
                <div class="programma-preview-actions" id="' . htmlspecialchars($fieldId) . '_preview_top_actions" style="display:none;">
                    <button type="button" class="btn btn-default btn-xs programma-preview-done" data-preview-field="' . htmlspecialchars($fieldId) . '">Ho finito di modificare questo campo</button>
                </div>
                <div id="' . htmlspecialchars($fieldId) . '_preview_box" class="programma-syntax-box" data-preview-field="' . htmlspecialchars($fieldId) . '">
                    <div class="title">Anteprima durante la modifica</div>
                    <div id="' . htmlspecialchars($previewId) . '" class="programma-preview-render"><span class="text-muted">Anteprima non disponibile: inizia a scrivere.</span></div>
                    <div id="' . htmlspecialchars($linesId) . '" class="programma-preview-lines"><span class="text-muted">Qui vedi la riga corrente e quelle vicine, con `↵` a fine riga.</span></div>
                    <div class="programma-preview-actions">
                        <button type="button" class="btn btn-default btn-xs programma-preview-done" data-preview-field="' . htmlspecialchars($fieldId) . '">Ho finito di modificare questo campo</button>
                    </div>
                </div>
            </div>
        </div>';
}

// if (((haRuolo('dirigente')) || (haRuolo('segreteria-didattica')))  || ((haRuolo('docente')) && (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) && (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false))) )
// {
//     $modificheDisabilitate = '';
// } else {
//     $modificheDisabilitate = ' disabled ';
// }

$id_docente_utente = 0;
if (intval($__docente_id ?? 0) > 0) {
    $id_docente_utente = intval($__docente_id);
} elseif ($__utente_ruolo == 'docente') {
    $query = "SELECT * from docente WHERE docente.username='" . $__username . "'";
    $result = dbGetFirst($query);
    if ($result != null) {
        $id_docente_utente = $result['id'];
    }
}
// prepara l'elenco delle materie per il filtro e per le materie del dialog
$modificheDisabilitate = 'disabled';
$annoCorsoOptionList = "";
$indirizzoCorsoOptionList = "";
$materiaFiltroOptionList = '<option value="0">Tutte</option>';
$materiaOptionList = '<option value="0"></option>';
foreach (dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC ; ") as $materia) {
    $materiaFiltroOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
    $materiaOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
}

// anni
$anniFiltroOptionList = '<option value="0">Tutti</option>';
$anniOptionList      = '<option value="0">Selezionare anno</option>';

foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC;") as $anno) {
    $selected = ($anno['id'] == $__anno_scolastico_corrente_id) ? ' selected' : '';
    $option   = '<option value="' . htmlspecialchars($anno['id']) . '"' . $selected . '>' . htmlspecialchars($anno['anno']) . '</option>';

    $anniFiltroOptionList .= $option;
    $anniOptionList      .= $option;
}

// classi 
$classiFiltroOptionList = '<option value="0">T</option>';
$classiOptionList = '<option value="0" data-anno="0">selezionare classe</option>';
foreach (dbGetAll("SELECT * FROM classi WHERE attiva=1 ORDER BY classi.classe ASC ; ") as $classe) {
    $classiFiltroOptionList .= '<option value="' . $classe['id'] . '" >' . $classe['classe'] . '</option> ';
    $classiOptionList .= '<option value="' . $classe['id'] . '" data-anno="' . intval($classe['anno']) . '" >' . $classe['classe'] . '</option> ';
}

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

?>

<body>
    <!-- OVERLAY con progress bar -->
    <div id="progressOverlay" style="display: none;">
        <div id="progressContent">
            <p>Invio email in corso...</p>
            <div id="progressBarContainer">
                <div id="progressBar">0%</div>
            </div>
        </div>
    </div>
    <?php
    if (isset($_GET['docente_id']) && intval($_GET['docente_id']) > 0 && intval($__docente_id ?? 0) > 0) {
        require_once '../common/header-docente.php';
    } else
    if (haRuolo('segreteria-didattica')) {
        require_once '../common/header-didattica.php';
    } else
    if (haRuolo('docente')) {
        require_once '../common/header-docente.php';
    } else
    if (haRuolo('studente')) {
        require_once '../common/header-studente.php';
    }

    ?>
    <input type="hidden" id="hidden_docente_id" value="<?php echo $id_docente_utente ?>">
    <div class="container-fluid">
        <div class="panel panel-lima4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-1 text-center">
                        <span class="glyphicon glyphicon-list-alt"
                            style="margin:5px"></span><br><b>Programmi<br>Svolti</b>
                    </div>
                    <div class="col-md-1 text-center">
                        <label class="col-sm-12 control-label" for="classi">Classe</label>
                        <div class="text-center">
                            <div class="col-sm-12"><select id="classi_filtro" name="classi_filtro"
                                    class="classi_filtro selectpicker" data-style="btn-salmon" data-live-search="true"
                                    data-noneSelectedText="seleziona..."
                                    data-width="100%"><?php echo $classiFiltroOptionList ?></select></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <label class="col-sm-12 control-label" for="materia">Materia</label>
                            <div class="col-sm-12"><select id="materia_filtro" name="materia_filtro"
                                    class="materia_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="100%">
                                    <?php echo $materiaFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="text-center">
                            <label class="col-sm-12 control-label" for="docente">Docente</label>
                            <div class="col-sm-12"><select id="docente_filtro" name="docente_filtro"
                                    class="docente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..."
                                    <?php if (!(haRuolo("segreteria-didattica"))) echo ' disabled '; ?>
                                    data-width="100%">
                                    <?php echo $docentiFiltroOptionList ?>
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
        </div>-->
                    <div>
                        <div>

                            <div class="col-md-2 text-right">
                                <div class="text-center">
                                    <?php 
                                                                        if (getSettingsValue('programmiSvolti', 'docente_puo_inserire', false) || (haRuolo('segreteria-didattica')) || (haRuolo('dirigente'))) 
                                                                        {
                                                                            echo '
                                                                        <label class="col-sm-12 control-label" for="materia">Aggiungi Programma</label>
                                                                        <button class="btn btn-xs btn-lima4" onclick="programmiSvoltiGetDetails(-1,&#39;false&#39;,&#39;false&#39;)"><span
                                                                                style="font-size:20px" class="glyphicon glyphicon-plus"></span></button>';
                                                                        } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2" style="margin:0;">
                        <div class="text-center">
                            <label class="col-sm-10 control-label" style="margin:0;" for="anni_filtro">Anno scolastico</label>
                            <div class="col-sm-10">
                                <select id="anni_filtro" style="margin:0;" name="anni_filtro"
                                    class="anni_filtro selectpicker"
                                    data-style="btn-yellow4"
                                    data-live-search="true"
                                    data-noneSelectedText="Seleziona..."
                                    data-width="60%">
                                    <?php echo $anniFiltroOptionList ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <?php
                    if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
                        echo '                    
                                    <div class="col-md-auto text-center">
                                                                <label class="checkbox-inline">
                                                <input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary"
                                                    id="daCompletareCheckBox" data-on="Tutti" data-off="Chi non ha completato">
                                            </label>
                                    </div>
                                    <div class="col-md-auto text-center">
                                        <label id="send_btn" class="btn btn-xs btn-lima4 btn-file" data-toggle="tooltip" title="Invia mail sollecito"><span
                                        class="glyphicon glyphicon-send" ></span>&emsp;Mail Sollecito</label></div>
                                    <div class="col-md-auto text-center"></div>
                                        ';
                    }
                    ?>

                    <div class="panel-body">
                        <div class="row" style="margin-bottom:10px;">
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
                <div class="modal fade" id="programma_modal" data-backdrop="static" tabindex="-1" role="dialog"
                    aria-labelledby="myModalLabel1">
                    <div class="modal-dialog modal-lg" style="margin:auto;" role="document">
                        <div class="modal-content">
                            <div class="modal-body">
                                <div class="panel panel-orange4">
                                    <div class="panel-heading">
                                        <h3 class="modal-title" style="text-align:center" id="myModalLabel1">Programma
                                            Svolto
                                        </h3>
                                    </div>
                                    <div class="panel-body">
                                        <form class="form-horizontal">

                                            <div class="form-group classe_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    for="classe">Classe</label>
                                                <div class="col-sm-10"><select id="classe" name="classe"
                                                        class="classe selectpicker" data-style="btn-success"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        data-width="100%">
                                                        <?php echo $classiOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group docente_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    for="docente">Docente</label>
                                                <div class="col-sm-10"><select id="docente" name="docente"
                                                        class="indirizzo selectpicker" data-style="btn-yellow4"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        data-width="100%">
                                                        <?php echo $docentiOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group materia_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    accesskey="" for="materia">Materia</label>
                                                <div class="col-sm-10"><select id="materia" name="materia"
                                                        class="materia selectpicker" data-style="btn-yellow4"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        data-width="100%">
                                                        <?php echo $materiaOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div id="quinta_programma_fields_wrap" style="display:none;">
                                                <div class="form-group">
                                                    <label class="col-sm-2 control-label" for="metodologie_programma">Metodologie</label>
                                                    <div class="col-sm-10"><textarea id="metodologie_programma" rows="4"
                                                            placeholder="metodologie"
                                                            class="form-control" data-toggle="tooltip" data-placement="top"
                                                            title="Inserisci le metodologie dell'intero programma"></textarea>
                                                    </div>
                                                </div>
                                                <?php echo renderProgrammaSyntaxPreview('metodologie_programma', 'metodologie_programma_preview', 'metodologie_programma_lines'); ?>

                                                <div class="form-group">
                                                    <label class="col-sm-2 control-label" for="criteri_valutazione_programma">Criteri di valutazione</label>
                                                    <div class="col-sm-10"><textarea id="criteri_valutazione_programma" rows="4"
                                                            placeholder="criteri di valutazione"
                                                            class="form-control" data-toggle="tooltip" data-placement="top"
                                                            title="Inserisci i criteri di valutazione dell'intero programma"></textarea>
                                                    </div>
                                                </div>
                                                <?php echo renderProgrammaSyntaxPreview('criteri_valutazione_programma', 'criteri_valutazione_programma_preview', 'criteri_valutazione_programma_lines'); ?>

                                                <div class="form-group">
                                                    <label class="col-sm-2 control-label" for="testi_materiali_programma">Testi e materiali / strumenti</label>
                                                    <div class="col-sm-10"><textarea id="testi_materiali_programma" rows="4"
                                                            placeholder="testi e materiali / strumenti adottati"
                                                            class="form-control" data-toggle="tooltip" data-placement="top"
                                                            title="Inserisci testi e materiali / strumenti adottati per l'intero programma"></textarea>
                                                    </div>
                                                </div>
                                                <?php echo renderProgrammaSyntaxPreview('testi_materiali_programma', 'testi_materiali_programma_preview', 'testi_materiali_programma_lines'); ?>
                                            </div>

                                            <div class="form-group" id="_error-programma-part"><strong>

                                                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                                    <div class="col-sm-9" id="_error-programma"></div>
                                                </strong></div>

                                            <input type="hidden" id="hidden_programma_id">
                                            <input type="hidden" id="hidden_duplica">
                                            <input type="hidden" id="hidden_share">
                                            <input type="hidden" id="hidden_readonly" value="false">
                                            <input type="hidden" id="hidden_programma_classe_anno" value="0">
                                        </form>

                                    </div>
                                    <div class="container-fluid"">
                                <div class=" panel panel-lima4">
                                        <div class="panel-body" style="padding:0px">
                                            <div class="row">
                                                <div class="col-md-2"></div>
                                                <div class="col-md-4">
                                                    <h3 style="text-align:center">Elenco Moduli
                                                        <?php
                                                        if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
                                                            echo '
                                                        <button id="btn-modulo-add" class="btn btn-xs btn-lima4"
                                                            onclick="moduloSvoltiGetDetails(-1)"><span style="font-size:14px"
                                                                class="glyphicon glyphicon-plus"></span></button>
                                                        ';
                                                        } else if (haRuolo('docente')) {
                                                            if (getSettingsValue('programmiSvolti', 'docente_puo_modificare', false)) {
                                                                echo '
                                                                <button id="btn-modulo-add" class="btn btn-xs btn-lima4"
                                                                onclick="moduloSvoltiGetDetails(-1)"><span style="font-size:14px"
                                                                class="glyphicon glyphicon-plus"></span></button>
                                                        ';
                                                            }
                                                        }
                                                        ?>
                                                    </h3>
                                                </div>
                                                <div class="col-md-4">
                                                    <h3 style="text-align:center">Importa Moduli
                                                        <?php
                                                        if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
                                                            echo '
                                                        <button id="btn-modulo-import" class="btn btn-xs btn-lima4"
                                                            onclick="moduliSvoltiImport()"><span style="font-size:14px"
                                                                class="glyphicon glyphicon-cloud-upload"></span></button>
                                                        ';
                                                        } else if (haRuolo('docente')) {
                                                            if (getSettingsValue('programmiSvolti', 'docente_puo_modificare', false)) {
                                                                echo '
                                                                <button id="btn-modulo-import" class="btn btn-xs btn-lima4"
                                                                onclick="moduliSvoltiImport()"><span style="font-size:14px"
                                                                class="glyphicon glyphicon-cloud-upload"></span></button>
                                                                ';
                                                            }
                                                        }
                                                        ?>
                                                    </h3>
                                                </div>
                                                <div class="col-md-2"></div>
                                                <div class="moduli_content"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="panel-footer text-center">
                                <?php
                                if (haRuolo('docente')) {
                                    if (getSettingsValue('programmiSvolti', 'docente_puo_modificare', false)) {
                                        echo '
                                        <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                        <button type="button" id="btn-programma-save" class="btn btn-primary" onclick="programmiSvoltiSave()">Salva</button>
                                ';
                                    } else {
                                        echo '
                                        <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                                ';
                                    }
                                } else
                                if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
                                    echo '
                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                <button type="button" id="btn-programma-save" class="btn btn-primary" onclick="programmiSvoltiSave()">Salva</button>
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

        <!-- Modal - Add/Update Record -->
        <div class="modal fade" id="modulo_modal" data-backdrop="static" tabindex="-1" role="dialog"
            aria-labelledby="myModalLabel">
            <div class="modal-dialog modal-lg" style="margin:auto;" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-orange4">
                            <div class="panel-heading">
                                <h3 class="modal-title" style="text-align:center" id="myModalLabel">Dati del modulo
                                </h3>
                            </div>
                            <div class="panel-body">
                                <form class="form-horizontal">

                                    <div class="form-group modulo_ordine_group">
                                        <label class="col-sm-2 control-label" for="ordine">Ordine</label>
                                        <div class="col-sm-10"><input type="text" id="ordine" placeholder="ordine"
                                                class="form-control" data-toggle="tooltip" data-placement="top"
                                                title="Inserisci il numero del modulo" />
                                        </div>
                                    </div>

                                    <div class="form-group modulo_titolo_group">
                                        <label class="col-sm-2 control-label" for="titolo">Titolo</label>
                                        <div class="col-sm-10"><input type="text" id="titolo" placeholder="titolo"
                                                class="form-control" data-toggle="tooltip" data-placement="top"
                                                title="Inserisci il titolo del modulo" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="contenuto">Contenuto</label>
                                        <div class="col-sm-10"><textarea id="contenuto" rows="5" placeholder="contenuto"
                                                class="form-control" data-toggle="tooltip" data-placement="top"
                                                title="Inserisci il contenuto relativo a questo modulo"></textarea>
                                            <div class="help-block" style="margin-top:6px; color:#4f6b88;">
                                                Clicca dentro il testo per vedere sotto l'anteprima live di come verra' formattato.
                                            </div>
                                        </div>
                                    </div>
                                    <?php echo renderProgrammaSyntaxPreview('contenuto', 'contenuto_preview', 'contenuto_lines'); ?>

                                    <div id="quinta_fields_wrap" style="display:none;">
                                        <div class="form-group">
                                            <label class="col-sm-2 control-label" for="competenze_raggiunte">Competenze raggiunte</label>
                                            <div class="col-sm-10"><textarea id="competenze_raggiunte" rows="4"
                                                    placeholder="competenze raggiunte"
                                                    class="form-control" data-toggle="tooltip" data-placement="top"
                                                    title="Inserisci le competenze raggiunte alla fine dell'anno per la disciplina"></textarea>
                                            </div>
                                        </div>
                                        <?php echo renderProgrammaSyntaxPreview('competenze_raggiunte', 'competenze_raggiunte_preview', 'competenze_raggiunte_lines'); ?>

                                        <div class="form-group">
                                            <label class="col-sm-2 control-label" for="contenuti_trattati">Conoscenze / contenuti trattati</label>
                                            <div class="col-sm-10"><textarea id="contenuti_trattati" rows="5"
                                                    placeholder="conoscenze o contenuti trattati"
                                                    class="form-control" data-toggle="tooltip" data-placement="top"
                                                    title="Inserisci conoscenze o contenuti trattati, anche attraverso UDA o moduli"></textarea>
                                            </div>
                                        </div>
                                        <?php echo renderProgrammaSyntaxPreview('contenuti_trattati', 'contenuti_trattati_preview', 'contenuti_trattati_lines'); ?>

                                        <div class="form-group">
                                            <label class="col-sm-2 control-label" for="abilita_quinta">Abilita'</label>
                                            <div class="col-sm-10"><textarea id="abilita_quinta" rows="4"
                                                    placeholder="abilita"
                                                    class="form-control" data-toggle="tooltip" data-placement="top"
                                                    title="Inserisci le abilita'"></textarea>
                                            </div>
                                        </div>
                                        <?php echo renderProgrammaSyntaxPreview('abilita_quinta', 'abilita_quinta_preview', 'abilita_quinta_lines'); ?>

                                    </div>

                                    <div class="form-group" id="_error-modulo-part"><strong>

                                            <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                            <div class="col-sm-9" id="_error-modulo"></div>
                                        </strong>
                                    </div>



                                    <input type="hidden" id="hidden_modulo_id">
                                </form>

                            </div>
                            <div class="panel-footer text-center">
                                <?php

                                if (haRuolo('segreteria-didattica')) {
                                    echo '
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                    <button type="button" id="btn-modulo-save" class="btn btn-primary" onclick="moduloSvoltiSave()">Salva</button>';
                                } else
                                    if (haRuolo('docente')) {
                                    if (getSettingsValue('programmiSvolti', 'visibile_docenti', false)) {
                                        if (getSettingsValue('programmiSvolti', 'docente_puo_modificare', false)) {
                                            echo '
                                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                                <button type="button" id="btn-modulo-save" class="btn btn-primary" onclick="moduloSvoltiSave()">Salva</button>';
                                        } else {
                                            echo '
                                                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>';
                                        }
                                    }
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
    <script type="text/javascript" src="js/svolti.js?v=<?php echo filemtime(__DIR__ . '/js/svolti.js'); ?>&a=<?php echo $__anno_scolastico_corrente_id; ?>"></script>
</body>

</html>

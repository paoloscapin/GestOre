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
$programmaSvoltiDocenteDaParametro = applicaDocenteDaParametroSeAutorizzato();
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
            width: min(520px, 92vw);
            box-shadow: 0 22px 60px rgba(0, 0, 0, 0.35);
        }

        #progressBarContainer {
            background: #ddd;
            border-radius: 10px;
            overflow: hidden;
            height: 25px;
            margin-top: 10px;
        }

        #progressBar {
            background: #15803d;
            width: 0%;
            height: 100%;
            color: white;
            text-align: center;
            line-height: 25px;
            transition: width 0.3s;
        }

        .programmi-progress-title {
            font-size: 20px;
            font-weight: 800;
            color: #172033;
            margin-bottom: 8px;
        }

        .programmi-progress-text {
            color: #475569;
            line-height: 1.45;
            margin-bottom: 12px;
        }

        .programmi-progress-status {
            margin-top: 12px;
            text-align: left;
            background: #f8fafc;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            padding: 10px 12px;
            min-height: 42px;
            color: #334155;
        }

        .programmi-sollecito-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.62);
            z-index: 10000;
            padding: 18px;
        }

        .programmi-sollecito-modal.open {
            display: flex;
        }

        .programmi-sollecito-box {
            width: min(620px, 100%);
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 24px 70px rgba(0,0,0,.35);
            overflow: hidden;
        }

        .programmi-sollecito-head {
            background: #1f4e79;
            color: #fff;
            padding: 18px 20px;
            font-size: 20px;
            font-weight: 800;
        }

        .programmi-sollecito-body {
            padding: 18px 20px;
            color: #172033;
            line-height: 1.5;
        }

        .programmi-sollecito-summary {
            background: #eff6ff;
            border-left: 5px solid #2563eb;
            border-radius: 8px;
            padding: 12px 14px;
            margin-top: 12px;
            font-weight: 700;
        }

        .programmi-sollecito-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 14px 20px;
            border-top: 1px solid #e5e7eb;
            background: #f8fafc;
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

        .programma-preview-render ul,
        .programma-preview-render ol {
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

        .programma-rich-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            margin-bottom: 6px;
            padding: 5px 6px;
            border: 1px solid #cfd8e3;
            border-radius: 6px;
            background: linear-gradient(#fbfcff, #edf3fb);
        }

        .programma-rich-toolbar .programma-rich-group {
            display: inline-flex;
            box-shadow: 0 1px 1px rgba(0,0,0,.06);
        }

        .programma-rich-toolbar .programma-rich-btn {
            min-width: 34px;
            height: 30px;
            padding: 4px 7px;
            border-color: #b8c4d2;
            color: #263647;
            background: linear-gradient(#ffffff, #eef3f8);
            font-weight: 700;
        }

        .programma-rich-toolbar .programma-rich-btn:hover,
        .programma-rich-toolbar .programma-rich-btn:focus,
        .programma-rich-toolbar .programma-rich-btn.active {
            border-color: #6aa7e8;
            background: linear-gradient(#fdfefe, #dceeff);
            color: #0f4c81;
        }

        .programma-rich-toolbar .programma-rich-btn.active {
            box-shadow: inset 0 2px 4px rgba(15,76,129,.22);
        }

        .programma-rich-toolbar .word-icon {
            display: inline-block;
            min-width: 16px;
            line-height: 1;
            text-align: center;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 16px;
        }

        .programma-rich-toolbar .word-icon-bold {
            font-weight: 800;
        }

        .programma-rich-toolbar .word-icon-italic {
            font-style: italic;
        }

        .programma-rich-toolbar .word-icon-underline {
            text-decoration: underline;
        }

        .programma-rich-toolbar .word-icon-title {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 17px;
            font-weight: 800;
        }

        .programma-rich-toolbar .word-icon-list,
        .programma-guide-button-icon .word-icon-list {
            font-family: Consolas, Monaco, monospace;
            font-size: 10px;
            line-height: 7px;
            letter-spacing: .5px;
        }

        .programma-guide-buttons {
            display: grid;
            grid-template-columns: 34px 1fr;
            gap: 6px 8px;
            align-items: center;
            margin-top: 6px;
        }

        .programma-guide-button-icon {
            display: inline-flex;
            width: 30px;
            height: 28px;
            align-items: center;
            justify-content: center;
            border: 1px solid #b8c4d2;
            border-radius: 4px;
            background: linear-gradient(#ffffff, #eef3f8);
            color: #263647;
            font-weight: 700;
            box-shadow: 0 1px 1px rgba(0,0,0,.06);
        }

        .programma-guide-button-text {
            color: #1f3550;
            font-size: 12px;
            line-height: 1.25;
        }

        .programma-rich-editor {
            height: auto;
            min-height: 110px;
            max-height: 260px;
            overflow-y: auto;
            line-height: 1.5;
            white-space: normal;
        }

        .programma-rich-editor:focus {
            border-color: #66afe9;
            outline: 0;
            box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(102,175,233,.6);
        }

        .programma-rich-editor.disabled {
            background: #eee;
            cursor: not-allowed;
        }

        .programma-rich-editor p {
            margin: 0 0 6px;
        }

        .programma-rich-editor ul,
        .programma-rich-editor ol {
            margin: 0 0 6px 22px;
            padding-left: 16px;
        }

        .programma-rich-editor blockquote,
        .programma-preview-render blockquote {
            margin: 0 0 6px 28px;
            padding: 0 0 0 12px;
            border-left: 3px solid #d6e4f3;
            font-size: inherit;
            color: inherit;
        }

        .programma-rich-editor h4 {
            margin: 6px 0 5px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            color: #173f68;
        }

        .programma-preview-render h4 {
            margin: 10px 0 7px;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            color: #173f68;
        }

        #programma_modal .modal-dialog,
        #modulo_modal .modal-dialog {
            width: 94vw;
            max-width: 1700px;
        }
    </style>
</head>

<?php
function renderProgrammaSyntaxPreviewLegacy(string $fieldId, string $previewId, string $linesId): string
{
    return '
        <div id="' . htmlspecialchars($fieldId) . '_preview_row" class="form-group programma-preview-row" data-preview-field="' . htmlspecialchars($fieldId) . '">
            <div class="col-sm-2">
                <div class="programma-preview-side">
                    <div class="title">Editor</div>
                    <div class="hint">Puoi scrivere come in Word, usare i pulsanti sopra il testo oppure incollare da Word. GestOre conserva solo formati compatibili e sicuri.</div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Pulsanti principali</div>
                        <span class="programma-guide-code">B = grassetto
I = corsivo
U = sottolineato
Lista puntata = elenco con pallini
Lista 1. = elenco numerato
Lista a. = elenco con lettere</span>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Rientri e pulizia</div>
                        <span class="programma-guide-code">Aumenta rientro = sottopunto
Riduci rientro = torna al livello prima
Titolo = trasforma la riga in titolo
Pulisci = rimuove la formattazione</span>
                    </div>
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

function renderProgrammaSyntaxPreview(string $fieldId, string $previewId, string $linesId): string
{
    return '
        <div id="' . htmlspecialchars($fieldId) . '_preview_row" class="form-group programma-preview-row" data-preview-field="' . htmlspecialchars($fieldId) . '">
            <div class="col-sm-2">
                <div class="programma-preview-side">
                    <div class="title">Editor</div>
                    <div class="hint">Scrivi come in Word: seleziona il testo, usa i pulsanti sopra l editor oppure incolla direttamente da Word.</div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Formato testo</div>
                        <span class="programma-guide-code">B = grassetto
I = corsivo
U = sottolineato
T = titolo grande</span>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Elenchi</div>
                        <div class="programma-guide-buttons">
                            <span class="programma-guide-button-icon"><span class="word-icon word-icon-list">&bull;<br>&bull;<br>&bull;</span></span>
                            <span class="programma-guide-button-text">elenco puntato</span>
                            <span class="programma-guide-button-icon"><span class="word-icon word-icon-list">1<br>2<br>3</span></span>
                            <span class="programma-guide-button-text">elenco numerato</span>
                            <span class="programma-guide-button-icon"><span class="word-icon word-icon-list">a<br>b<br>c</span></span>
                            <span class="programma-guide-button-text">elenco con lettere</span>
                        </div>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Rientri</div>
                        <div class="programma-guide-buttons">
                            <span class="programma-guide-button-icon"><span class="glyphicon glyphicon-indent-left"></span></span>
                            <span class="programma-guide-button-text">aumenta rientro / sottopunto</span>
                            <span class="programma-guide-button-icon"><span class="glyphicon glyphicon-indent-right"></span></span>
                            <span class="programma-guide-button-text">riduci rientro</span>
                        </div>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Incolla da Word</div>
                        <span class="programma-guide-code">Puoi copiare da Word e incollare qui.
GestOre prova a mantenere grassetto, corsivo, sottolineato, titoli, elenchi e rientri compatibili.</span>
                    </div>
                    <div class="programma-guide-example">
                        <div class="programma-guide-label">Pulisci formato</div>
                        <div class="programma-guide-buttons">
                            <span class="programma-guide-button-icon"><span class="glyphicon glyphicon-erase"></span></span>
                            <span class="programma-guide-button-text">pulisce le righe selezionate mantenendole separate</span>
                        </div>
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
                    <div id="' . htmlspecialchars($linesId) . '" class="programma-preview-lines"><span class="text-muted">Qui vedi la riga corrente e quelle vicine.</span></div>
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
if ($programmaSvoltiDocenteDaParametro != null && intval($__docente_id ?? 0) > 0) {
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
// classi
$classiFiltroOptionList = '<option value="0">T</option>';
$classiOptionList = '<option value="0" data-anno="0" data-tipo="classe">selezionare classe</option>';

foreach (dbGetAll("SELECT * FROM classi WHERE attiva=1 ORDER BY classi.classe ASC") as $classe) {
    $classiFiltroOptionList .= '<option value="' . intval($classe['id']) . '">' . htmlspecialchars($classe['classe']) . '</option>';
    $classiOptionList .= '<option value="' . intval($classe['id']) . '" data-tipo="classe" data-anno="' . intval($classe['anno']) . '">' . htmlspecialchars($classe['classe']) . '</option>';
}

// classi articolate, solo anno scolastico corrente
$queryArticolate = "
    SELECT 
        ca.id,
        ca.nome,
        GROUP_CONCAT(c.classe ORDER BY c.classe SEPARATOR ' / ') AS classi_nomi,
        GROUP_CONCAT(c.id ORDER BY c.classe SEPARATOR ',') AS classi_ids,
        MAX(c.anno) AS anno_classe
    FROM classi_articolate ca
    INNER JOIN classi_articolate_classi cac ON cac.id_articolata = ca.id
    INNER JOIN classi c ON c.id = cac.id_classe
    WHERE ca.attiva = 1
      AND ca.id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
    GROUP BY ca.id, ca.nome
    ORDER BY classi_nomi
";

foreach (dbGetAll($queryArticolate) as $art) {
    $label = 'Art: ' . ($art['nome'] ?: $art['classi_nomi']);

    $classiOptionList .= '<option value="A' . intval($art['id']) . '" 
        data-tipo="articolata" 
        data-articolata-id="' . intval($art['id']) . '" 
        data-classi="' . htmlspecialchars($art['classi_ids'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"
        data-anno="' . intval($art['anno_classe']) . '">' 
        . htmlspecialchars($label) . 
    '</option>';
    $classiFiltroOptionList .= '<option value="A' . intval($art['id']) . '" 
        data-tipo="articolata" 
        data-articolata-id="' . intval($art['id']) . '" 
        data-classi="' . htmlspecialchars($art['classi_ids'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"
        data-anno="' . intval($art['anno_classe']) . '">' 
        . htmlspecialchars($label) . 
    '</option>';
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
            <div class="programmi-progress-title" id="programmiProgressTitle">Invio email in corso</div>
            <div class="programmi-progress-text" id="programmiProgressText">Preparazione invio...</div>
            <div id="progressBarContainer">
                <div id="progressBar">0%</div>
            </div>
            <div class="programmi-progress-status" id="programmiProgressStatus">Attendere...</div>
        </div>
    </div>
    <div id="programmiSollecitoConfirmModal" class="programmi-sollecito-modal" aria-hidden="true">
        <div class="programmi-sollecito-box" role="dialog" aria-modal="true" aria-labelledby="programmiSollecitoConfirmTitle">
            <div class="programmi-sollecito-head" id="programmiSollecitoConfirmTitle">
                Conferma invio sollecito programmi svolti
            </div>
            <div class="programmi-sollecito-body">
                <p>
                    GestOre inviera' una mail ai docenti che hanno programmi svolti mancanti o non compilati.
                    Ogni docente ricevera' una sola mail con il riepilogo delle proprie classi e materie.
                </p>
                <div class="programmi-sollecito-summary" id="programmiSollecitoConfirmSummary">
                    Calcolo destinatari...
                </div>
                <p class="text-muted" style="margin-top:12px;">
                    Mittente previsto: <strong>noreplyGestOre@buonarroti.tn.it</strong>.
                </p>
            </div>
            <div class="programmi-sollecito-actions">
                <button type="button" class="btn btn-default" id="programmiSollecitoCancelBtn">Annulla</button>
                <button type="button" class="btn btn-warning" id="programmiSollecitoConfirmBtn">
                    <span class="glyphicon glyphicon-envelope"></span> Invia solleciti
                </button>
            </div>
        </div>
    </div>
    <?php
    if ($programmaSvoltiDocenteDaParametro != null && intval($__docente_id ?? 0) > 0) {
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
                                    <div class="col-md-auto text-center">
                                        <label id="report_mancanti_btn" class="btn btn-xs btn-info btn-file" data-toggle="tooltip" title="Scarica elenco docenti/materia/classe con programmi mancanti o vuoti"><span
                                        class="glyphicon glyphicon-download-alt" ></span>&emsp;Report mancanti</label></div>
                                    <div class="col-md-auto text-center">
                                        <label id="report_mancanti_pdf_btn" class="btn btn-xs btn-info btn-file" data-toggle="tooltip" title="Scarica PDF docenti/materia/classe con programmi mancanti o vuoti"><span
                                        class="glyphicon glyphicon-file" ></span>&emsp;PDF mancanti</label></div>
                                    <div class="col-md-auto text-center">
                                        <label id="sollecito_mancanti_btn" class="btn btn-xs btn-warning btn-file" data-toggle="tooltip" title="Invia sollecito ai docenti con programmi mancanti o vuoti"><span
                                        class="glyphicon glyphicon-envelope" ></span>&emsp;Sollecito mancanti</label></div>
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
                                            <input type="hidden" id="hidden_admin_programmi" value="<?php echo (haRuolo('dirigente') || haRuolo('segreteria-didattica')) ? '1' : '0'; ?>">
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

        <div class="modal fade" id="programmi_svolti_verifiche_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="programmiSvoltiVerificheLabel">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-info" style="margin-bottom:0;">
                            <div class="panel-heading">
                                <h3 class="modal-title" id="programmiSvoltiVerificheLabel" style="text-align:center">Verifiche digitali</h3>
                            </div>
                            <div class="panel-body">
                                <input type="hidden" id="programmi_svolti_verifiche_programma_id" value="0">

                                <div class="alert alert-info">
                                    <strong>Come preparare i file:</strong>
                                    raggruppa ogni verifica in una cartella separata e comprimi la cartella in un file ZIP.
                                    In alternativa puoi inserire tutte le cartelle delle verifiche in un'unica cartella e comprimere quella cartella in un file ZIP.
                                    Puoi caricare uno o piu file ZIP.
                                </div>

                                <div class="well well-sm">
                                    <strong>Cartella Drive:</strong>
                                    <span id="programmi_svolti_verifiche_folder_name" class="text-muted">caricamento...</span>
                                </div>

                                <div class="form-group">
                                    <label for="programmi_svolti_verifiche_files">File ZIP da caricare</label>
                                    <input type="file" id="programmi_svolti_verifiche_files" class="form-control" accept=".zip,application/zip,application/x-zip-compressed" multiple>
                                </div>

                                <div id="programmi_svolti_verifiche_progress_box" class="progress" style="display:none;">
                                    <div id="programmi_svolti_verifiche_progress" class="progress-bar progress-bar-success" role="progressbar" style="width:0%;">0%</div>
                                </div>

                                <div class="text-right" style="margin-bottom:12px;">
                                    <button type="button" class="btn btn-primary" id="programmi_svolti_verifiche_upload_btn" onclick="programmiSvoltiVerificheDigitaliUpload()">
                                        <span class="glyphicon glyphicon-cloud-upload"></span> Carica ZIP
                                    </button>
                                </div>

                                <h4>File caricati</h4>
                                <div id="programmi_svolti_verifiche_list">
                                    <div class="text-muted">Caricamento...</div>
                                </div>
                            </div>
                            <div class="panel-footer text-center">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Custom JS file -->
    <script type="text/javascript" src="js/svolti.js?v=<?php echo filemtime(__DIR__ . '/js/svolti.js'); ?>&a=<?php echo $__anno_scolastico_corrente_id; ?>"></script>
</body>

</html>

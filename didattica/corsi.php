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
ruoloRichiesto('esterno', 'docente', 'segreteria-didattica', 'dirigente');

if (!getSettingsValue('config', 'corsi', false)) {
    redirect("/error/unauthorized.php");
}

if (!getSettingsValue('corsi', 'visibile_docenti', false)) {
    ruoloRichiesto('segreteria-didattica', 'esterno', 'dirigente');
}

// ✅ AGGIUNTA: flag vista esterno (robusto)
// ✅ Esterno SOLO se è la vista/ruolo effettivo (non se è un ruolo "anche presente")
$ruolo_eff = $__utente_ruolo ?? '';
if (impersonaRuolo('docente')) $ruolo_eff = 'docente';
if (impersonaRuolo('esterno')) $ruolo_eff = 'esterno';

$isEsterno = ($ruolo_eff === 'esterno');

?>

<!DOCTYPE html>
<html>

<head>
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <?php
    if ((impersonaRuolo('docente')) || (impersonaRuolo('esterno'))) {
        echo ' <title>I miei corsi</title>';
    } else {
        echo ' <title>Corsi studenti</title>';
    } ?>
    <style>
        /* Tooltip */
        .tooltip>.tooltip-inner {
            background-color: #73AD21;
            color: #FFFFFF;
            border: 1px solid green;
            padding: 15px;
            font-size: 20px;
        }

        .tooltip.top>.tooltip-arrow {
            border-top: 5px solid green;
        }

        .tooltip.bottom>.tooltip-arrow {
            border-bottom: 5px solid blue;
        }

        .tooltip.left>.tooltip-arrow {
            border-left: 5px solid red;
        }

        .tooltip.right>.tooltip-arrow {
            border-right: 5px solid black;
        }

        .tooltip-inner {
            max-width: 450px;
            width: 450px;
            text-align: left;
        }

        /* Tabella studenti iscritti compatta e centrata */
        #iscritti_table {
            width: 60% !important;
            margin: 0 auto;
        }

        /* Colonna Nominativo più larga */
        #iscritti_table th:nth-child(1),
        #iscritti_table td:nth-child(1) {
            width: 600px;
        }

        /* Colonna Classe stretta e centrata */
        #iscritti_table th:nth-child(2),
        #iscritti_table td:nth-child(2) {
            width: 100px;
            text-align: center;
            white-space: nowrap;
        }

        /* Colonna Azioni stretta e centrata */
        #iscritti_table th:nth-child(3),
        #iscritti_table td:nth-child(3) {
            width: 100px;
            text-align: center;
            white-space: nowrap;
        }

        /* Pulsanti più piccoli e centrati */
        #iscritti_table td>button {
            padding: 2px 6px;
            font-size: 12px;
            margin: 0 2px;
        }

        .label-rosso {
            color: red;
            font-weight: bold;
            text-align: center;
            display: block;
        }

        /* Tabella date */
        #date_table {
            width: 50% !important;
            margin: 0 auto;
        }

        /* Colonna Data più stretta */
        #date_table th:nth-child(1),
        #date_table td:nth-child(1) {
            width: 160px;
            white-space: nowrap;
        }

        /* Colonna Aula stretta */
        #date_table th:nth-child(2),
        #date_table td:nth-child(2) {
            width: 80px;
            white-space: nowrap;
        }

        /* Colonna Azioni stretta */
        #date_table th:nth-child(3),
        #date_table td:nth-child(3) {
            width: 100px;
            text-align: center;
            white-space: nowrap;
        }

        /* Pulsanti più piccoli e centrati */
        #date_table td>button {
            padding: 2px 6px;
            font-size: 12px;
            margin: 0 2px;
        }

        /* Prima modale */
        #corsi_modal {
            z-index: 1050;
        }

        /* Seconda modale */
        #modificaDataModal {
            z-index: 1060;
        }

        /* Registro lezione sopra */
        #registroLezioneModal {
            z-index: 1070;
        }

        /* Aggiungi studenti sopra */
        #aggiungiStudentiModal {
            z-index: 1080;
        }

        /* Backdrop */
        .modal-backdrop.in {
            z-index: 1045;
        }

        .modal-header-orange4 {
            background-color: hsl(36, 100%, 72%) !important;
            background-repeat: repeat-x;
            background-image: linear-gradient(#fffaf4, #ffc570);
            border-color: #ffc570 #ffc570 hsl(36, 100%, 65.5%);
            color: #333 !important;
            text-align: center;
            position: relative;
            text-shadow: 0 1px 1px rgba(255, 255, 255, 0.42);
        }

        .modal-header-orange4 .modal-title {
            margin: 0 auto;
            font-weight: bold;
        }

        .modal-header-orange4 .close {
            color: #333;
            opacity: 1;
            position: absolute;
            right: 15px;
            top: 15px;
        }

        .modal-footer {
            text-align: center;
        }

        .modal-header-blu {
            background-color: #cce5ff;
            background-repeat: repeat-x;
            background-image: linear-gradient(#e6f0ff, #3399ff);
            border-color: #3399ff #3399ff #2673cc;
            color: #000;
            text-align: center;
            position: relative;
            text-shadow: none;
        }

        .modal-header-blu .modal-title {
            margin: 0 auto;
            font-weight: bold;
            color: #000;
        }

        .modal-header-blu .close {
            color: #000;
            opacity: 1;
            position: absolute;
            right: 15px;
            top: 15px;
        }

        .modal-basso {
            margin-top: 150px;
        }

        #toastMessage {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #333;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            display: none;
            font-family: Arial, sans-serif;
            font-size: 16px;
            opacity: 0.95;
        }

        /* Box "Firmato da" - registro lezione */
        .firme-box {
            margin-top: 10px;
            padding: 8px 12px;
            background-color: #f9f9f9;
            border-left: 4px solid #5bc0de;
            border-radius: 4px;
            font-size: 13px;
        }

        /* Allinea "Data lezione" al testo di "Firmato da" */
        #registroLezioneModal .data-lezione-box {
            padding-left: 31px;
            /* 15 (bootstrap) + 16 (border+padding firme-box) */
            padding-right: 15px;
        }

        #prevede_esami_group .toggle {
            margin-left: 8px;
        }

        #prevede_esami_forzato_msg {
            padding: 8px 10px;
        }

        /* ============================================================
           ✅ AGGIUNTA: NASCONDO (SOLO UI) BLOCCO HEADER FILTRI/AZIONI
           quando il ruolo effettivo è ESTERNO.
           Nascondo le COLONNE intere (così spariscono anche label/spazi).
           ============================================================ */
        <?php if ($isEsterno) { ?>#col-filtro-materia,
        #col-filtro-docente,
        #col-aggiungi-corso,
        #col-segreteria-tools,
        #col-report-1,
        #col-report-2 {
            display: none !important;
        }

        <?php } ?>
    </style>

</head>

<?php

$modificheDisabilitate = "";

$id_docente_utente = 0;
if ($__utente_ruolo == 'docente') {
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

// anni
$anno_corsi = $__anno_scolastico_corrente_id;
$anniFiltroOptionList = '<option value="0">Tutti</option>';
$anniOptionList      = '<option value="0">Selezionare anno</option>';

foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC;") as $anno) {
    $selected = ($anno['id'] == $anno_corsi) ? ' selected' : '';
    $option   = '<option value="' . htmlspecialchars($anno['id']) . '"' . $selected . '>' . htmlspecialchars($anno['anno']) . '</option>';

    $anniFiltroOptionList .= $option;
    $anniOptionList      .= $option;
}
?>

<body class="<?php echo $isEsterno ? 'role-esterno' : ''; ?>">

    <?php
    if (impersonaRuolo('docente')) {
        require_once '../common/header-docente.php';
    } else if (impersonaRuolo('esterno')) {
        require_once '../common/header-esterno.php';
    } else if (haRuolo('segreteria-didattica')) {
        require_once '../common/header-segreteria.php';
    }
    ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row align-items-start" style="margin-bottom:10px;">
                    <div class="col-md-1 text-center">
                        <span class="glyphicon glyphicon-list-alt" style="margin:5px"></span><br><b>Elenco<br>Corsi</b>
                    </div>

                    <div class="col-md-3 text-center" id="col-filtro-materia">

                        <label class="col-sm-12 control-label" for="materia">Materia</label>
                        <div class="text-center">
                            <div class="col-sm-12">
                                <select id="materia_filtro" name="materia_filtro"
                                    class="mamteria_filtro selectpicker" data-style="btn-salmon" data-live-search="true"
                                    data-noneSelectedText="seleziona..."
                                    data-width="100%"><?php echo $materiaFiltroOptionList ?></select>
                            </div>
                        </div>
                    </div>

                    <?php
                    if (haRuolo('segreteria-didattica')) {
                        echo '
                    <div class="col-md-2" id="col-filtro-docente">
                        <div class="text-center">
                            <label class="col-sm-12 control-label" for="docente">Docente</label>
                            <div class="col-sm-12"><select id="docente_filtro" name="docente_filtro"
                                    class="docente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="100%">';
                        echo $docentiFiltroOptionList;
                        echo '
                                </select>
                            </div>
                        </div>
                    </div>
                    ';
                    }
                    ?>

                    <?php
                    if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
                        echo '
    <div class="col-md-1 text-center" id="col-aggiungi-corso">
        <label class="control-label" for="corso">Aggiungi</label>
        <button class="btn btn-xs btn-lima4" style="display:block; margin: 5px auto 0;"
                onclick="corsiGetDetails(-1)">
            <span style="font-size:15px" class="glyphicon glyphicon-plus"></span>
        </button>
    </div>

    <div class="col-md-1 text-center" style="margin-top:20px;" id="col-segreteria-tools">
        <label id="import_btn" class="btn btn-xs btn-lima4 btn-file" data-toggle="tooltip" title="Importa i corsi">
            <span class="glyphicon glyphicon-upload"></span>&emsp;Importa
            <input type="file" id="file_select_id" style="display: none;">
        </label>

        <label id="export_btn" class="btn btn-xs btn-lima4 btn-file" data-toggle="tooltip" title="Esporta esiti esami">
            <span class="glyphicon glyphicon-download"></span>&emsp;Esporta esiti
        </label>

        <label id="btn-invia-esiti" class="btn btn-xs btn-lima4 btn-file" data-toggle="tooltip" title="Invia esiti ai coordinatori di classe">
            <span class="glyphicon glyphicon-envelope"></span>&emsp;Invia esiti
        </label>

        <label id="report_itinere_csv_btn"
               class="btn btn-xs btn-lima4 btn-file"
               data-toggle="tooltip"
               title="Scarica report iscritti corsi in itinere (CSV)"
               onclick="
                 var solo = (document.getElementById(\'carenze\') && document.getElementById(\'carenze\').checked) ? 1 : 0;
                 window.location.href = \'reportCorsiItinereIscritti.php?format=csv&anno_id=' . intval($anno_corsi) . '&solo_carenze=\' + solo;
               ">
            <span class="glyphicon glyphicon-list-alt"></span>&emsp;Report itinere CSV
        </label>

        <label id="report_itinere_pdf_btn"
               class="btn btn-xs btn-lima4 btn-file"
               data-toggle="tooltip"
               title="Scarica report iscritti corsi in itinere (PDF)"
               onclick="
                 var solo = (document.getElementById(\'carenze\') && document.getElementById(\'carenze\').checked) ? 1 : 0;
                 window.location.href = \'reportCorsiItinereIscritti.php?format=pdf&anno_id=' . intval($anno_corsi) . '&solo_carenze=\' + solo;
               ">
            <span class="glyphicon glyphicon-file"></span>&emsp;Report itinere PDF
        </label>

        <div id="stato-invio" style="margin-top:10px; font-weight:bold; color:#333;"></div>
    </div>
    ';
                    }
                    ?>


                    <div class="col-md-2 text-center" style="margin-top:20px;" id="col-report-1">
                        <label class="checkbox-inline mb-0" style="line-height: 1; vertical-align: top;">
                            <input type="checkbox" data-toggle="toggle" data-size="mini"
                                data-onstyle="primary" id="futuri"> Solo Nuovi
                        </label><br>
                        <label id="incompleti" class="btn btn-xs btn-lima4 btn-file" data-toggle="tooltip" title="Esami incompleti">
                            <span class="glyphicon glyphicon-download"></span>&emsp;Incompleti
                        </label><br>
                        <label id="report_carenze_btn"
                            class="btn btn-xs btn-lima4 btn-file"
                            data-toggle="tooltip"
                            title="Scarica elenco corsi carenze senza date esame e/o non firmati CSV"
                            onclick="
                            var sess = (document.getElementById('carenza_sessione') ? document.getElementById('carenza_sessione').value : 0);
                            window.location.href='corsiCarenzeReport.php?format=csv&sessione=' + encodeURIComponent(sess);
                            ">
                            <span class="glyphicon glyphicon-list-alt"></span>&emsp;Report carenze CSV
                        </label>
                        <label class="btn btn-xs btn-lima4"
                            onclick="window.location.href='corsiCarenzeReport.php?format=pdf'">
                            <span class="glyphicon glyphicon-file"></span>&emsp;PDF carenze
                        </label>

                    </div>

                    <div class="col-md-2 text-center" style="margin-top:20px;" id="col-report-2">
                        <label class="checkbox-inline mb-0" style="line-height: 1; vertical-align: top;">
                            <input type="checkbox" data-toggle="toggle" data-size="mini"
                                data-onstyle="primary" id="carenze">Corsi carenze
                        </label><br>
                        <label class="checkbox-inline mb-0" style="line-height: 1; vertical-align: top;">
                            <input type="checkbox" data-toggle="toggle" data-size="mini"
                                data-onstyle="primary" id="filtro_itinere">Corsi in itinere
                        </label><br>
                        <div id="carenza_sessione_box" class="text-center" style="margin-top:8px; display:none;">
                            <label class="col-sm-12 control-label" for="carenza_sessione">Sessione carenze</label>
                            <div class="col-sm-12">
                                <select id="carenza_sessione"
                                    class="selectpicker"
                                    data-style="btn-salmon"
                                    data-width="100%">
                                    <option value="0" selected>Tutte</option>
                                    <option value="1">Solo 1ª sessione</option>
                                    <option value="2">Solo 2ª sessione</option>
                                </select>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="panel-body">
                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-12 text-center" id='result_text'></div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="records_content"></div>
                    </div>
                </div>
            </div>

            <input type="hidden" id="hidden_corso_id">
        </div>
    </div>

    <!-- ===================== -->
    <!-- MODALE DETTAGLI CORSO -->
    <!-- ===================== -->
    <div class="modal fade" id="corsi_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">

                <div class="modal-header modal-header-orange4">
                    <h4 class="modal-title w-100 text-center" id="myModalLabel">Dettagli Corso</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <form class="form-horizontal">

                        <!-- ✅ Vecchio docente (compatibilità JS, nascosto) -->
                        <div class="form-group" style="display:none;">
                            <label class="col-sm-2 control-label">Docente</label>
                            <div class="col-sm-10">
                                <select id="docente" class="selectpicker form-control" data-live-search="true">
                                    <?php echo $docentiFiltroOptionList ?>
                                </select>
                            </div>
                        </div>

                        <!-- ✅ NUOVO: docenti multipli -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Docenti</label>
                            <div class="col-sm-10">
                                <select id="docenti_multi"
                                    class="selectpicker form-control"
                                    data-live-search="true"
                                    data-width="100%"
                                    multiple
                                    title="Seleziona uno o più docenti">
                                    <?php echo $docentiFiltroOptionList ?>
                                </select>
                                <small class="text-muted">
                                    Il primo selezionato viene salvato come docente principale (compatibilità con corso.id_docente).
                                </small>
                            </div>
                        </div>

                        <!-- materia -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Materia</label>
                            <div class="col-sm-10">
                                <select id="materia" class="selectpicker form-control" data-live-search="true">
                                    <?php echo $materiaOptionList ?>
                                </select>
                            </div>
                        </div>

                        <!-- titolo -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Titolo</label>
                            <div class="col-sm-10">
                                <input type="text" id="titolo" class="form-control" placeholder="titolo" style="text-align:center;">
                            </div>
                        </div>

                        <hr>

                        <!-- in_itinere -->
                        <div class="form-group text-center">
                            <label for="in_itinere" class="control-label" style="margin-right:10px;">In itinere</label>
                            <input type="checkbox" id="in_itinere"
                                data-toggle="toggle"
                                data-on="Sì" data-off="No"
                                data-onstyle="success" data-offstyle="danger">
                        </div>

                        <!-- ✅ NUOVO: prevede esami -->
                        <div class="form-group text-center" id="prevede_esami_group" style="margin-top:10px;">
                            <label for="prevede_esami" class="control-label" style="margin-right:10px;">Prevede esame</label>
                            <input type="checkbox" id="prevede_esami"
                                data-toggle="toggle"
                                data-on="Sì" data-off="No"
                                data-onstyle="success" data-offstyle="danger">
                        </div>

                        <!-- ✅ Nota: esami forzati (carenze / itinere) -->
                        <div class="alert alert-info text-center" id="prevede_esami_forzato_msg" style="display:none; margin-top:10px;">
                            Per i corsi <b>carenze</b> o <b>in itinere</b> l’esame è <b>obbligatorio</b>.
                        </div>


                        <div id="date_section">
                            <hr>
                            <div class="form-group text-center">
                                <label>Date del corso</label>
                                <table class="table table-bordered table-striped" id="date_table">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">Inizio</th>
                                            <th style="text-align: center;">Fine</th>
                                            <th style="text-align: center;">Aula</th>
                                            <th style="text-align: center;">Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <button type="button" class="btn btn-success" style="margin: 10px auto; display:block;" onclick="aggiungiDate()">Aggiungi Date</button>
                            </div>
                        </div>

                        <!-- studenti -->
                        <div class="form-group text-center">
                            <label>Studenti iscritti</label>
                            <table class="table table-bordered table-striped" id="iscritti_table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">Nominativo</th>
                                        <th style="text-align: center;">Classe</th>
                                        <th style="text-align: center;">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                            <button type="button" class="btn btn-success" style="margin: 10px auto; display:block;" onclick="iscriviStudenti()">Iscrivi Studenti</button>
                        </div>

                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                    <button type="button" class="btn btn-primary" onclick="corsiSave()">Salva</button>
                </div>

            </div>
        </div>
    </div>

    <!-- ===================== -->
    <!-- MODALE MODIFICA DATA  -->
    <!-- ===================== -->
    <div class="modal fade" id="modificaDataModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-md modal-basso" role="document">
            <div class="modal-content">
                <div class="modal-header modal-header-blu">
                    <h4 class="modal-title">Modifica data</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <input type="hidden" id="hidden_data_id" value="-1">

                    <div id="error-modifica-data" class="alert alert-danger" style="display:none;"></div>

                    <div class="form-group">
                        <label>Data inizio</label>
                        <input type="datetime-local" id="mod_data_inizio" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Data fine</label>
                        <input type="datetime-local" id="mod_data_fine" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Aula</label>
                        <input type="text" id="mod_aula" class="form-control" placeholder="Aula">
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button class="btn btn-primary" onclick="salvaModificaData()">Salva</button>
                </div>

            </div>
        </div>
    </div>

    <!-- ============================= -->
    <!-- MODALE AGGIUNGI STUDENTI      -->
    <!-- ============================= -->
    <div class="modal fade" id="aggiungiStudentiModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-md modal-basso" role="document">
            <div class="modal-content">
                <div class="modal-header modal-header-blu">
                    <h4 class="modal-title">Aggiungi studenti</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <div id="error-aggiungi-studenti" class="alert alert-danger" style="display:none;"></div>
                    <div id="container_studenti"></div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button class="btn btn-primary" onclick="salvaNuoviStudenti()">Salva</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================= -->
    <!-- MODALE REGISTRO LEZIONE       -->
    <!-- ============================= -->
    <div class="modal fade" id="registroLezioneModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">

                <div class="modal-header modal-header-blu">
                    <h4 class="modal-title">Registro lezione</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <div class="row" style="margin-bottom:10px;">
                        <div class="col-md-8 data-lezione-box">
                            <label>Data lezione</label>
                            <select id="select_data_corso"
                                class="selectpicker"
                                data-live-search="true"
                                data-width="100%">
                            </select>
                        </div>
                        <div class="col-md-4 text-center" style="padding-top:25px;">
                            <label style="margin-right:10px;">Lezione firmata</label>
                            <input type="checkbox" id="lezioneFirmata"
                                data-toggle="toggle"
                                data-on="Sì" data-off="No"
                                data-onstyle="success" data-offstyle="danger">
                        </div>
                        <!-- Box firme (docente) -->
                        <div class="col-md-12">
                            <div id="firmeLezioneBox" class="text-muted firme-box" style="display:none;"></div>
                        </div>

                        <!-- Lista firme docenti (solo segreteria/dirigente/admin) -->
                        <div class="col-md-12">
                            <div id="firmeDocentiWrap" style="margin-top:10px; display:none;">
                                <label>Firme docenti</label>
                                <table class="table table-bordered table-striped" id="tabellaFirmeDocenti">
                                    <thead>
                                        <tr>
                                            <th>Docente</th>
                                            <th class="text-center" style="width:120px;">Firmato</th>
                                            <th style="width:200px;">Firmato il</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <div class="row" style="margin-bottom:10px;">
                        <div class="col-md-12">
                            <label>Argomenti svolti</label>
                            <textarea id="argomentiLezione" class="form-control" rows="3"
                                placeholder="Inserisci argomenti..."></textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <label>Presenze</label>
                            <table class="table table-bordered table-striped" id="tabellaStudenti">
                                <thead>
                                    <tr>
                                        <th>Nominativo</th>
                                        <th>Classe</th>
                                        <th class="text-center">Presente</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Chiudi</button>
                    <button class="btn btn-primary" onclick="salvaRegistroLezione()">Salva</button>
                </div>

            </div>
        </div>
    </div>

    <!-- ============================= -->
    <!-- MODALE ESAME                  -->
    <!-- ============================= -->
    <div class="modal fade" id="esameModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">

                <div class="modal-header modal-header-blu">
                    <h4 class="modal-title">Gestione esame</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <input type="hidden" id="hidden_esame_data_id" value="">

                    <div class="row" style="margin-bottom:10px;">
                        <div class="col-md-6">
                            <label>Tentativo</label>
                            <select id="select_tentativo" class="form-control"></select>
                        </div>
                    </div>

                    <div class="row" style="margin-bottom:10px;">
                        <div class="col-md-3">
                            <label>Inizio (data)</label>
                            <input type="date" id="esame_inizio_data" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Inizio (ora)</label>
                            <input type="time" id="esame_inizio_ora" class="form-control">
                        </div>
                        <div class="row" style="margin-bottom:10px;">
                        </div>
                        <div class="col-md-3">
                            <label>Fine (data)</label>
                            <input type="date" id="esame_fine_data" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Fine (ora)</label>
                            <input type="time" id="esame_fine_ora" class="form-control">
                        </div>
                    </div>

                    <div class="row" style="margin-bottom:10px;">
                        <div class="col-md-4">
                            <label>Aula</label>
                            <input type="text" id="esame_aula" class="form-control" placeholder="Aula">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <label>Studenti</label>
                            <table class="table table-bordered table-striped" id="tabellaEsameStudenti">
                                <thead>
                                    <tr>
                                        <th>Nominativo</th>
                                        <th>Classe</th>
                                        <th class="text-center">Presente</th>
                                        <th class="text-center">Ass. giust.</th>
                                        <th>Motivo assenza</th>
                                        <th>Tipo prova</th>
                                        <th>Voto</th>
                                        <th class="text-center">Recuperata</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- DOPO: argomenti + firme (in basso) -->
                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-9">
                            <label>Argomenti</label>
                            <textarea id="argomentiEsame" class="form-control" rows="3"
                                placeholder="Inserisci argomenti..."></textarea>
                        </div>

                        <!-- Toggle (solo docente) -->
                        <div class="col-md-3 text-center" id="esameFirmatoRow" style="padding-top:25px;">
                            <label style="margin-right:10px;">Esame firmato</label><br>
                            <input type="checkbox" id="esameFirmato"
                                data-toggle="toggle"
                                data-on="Sì" data-off="No"
                                data-onstyle="success" data-offstyle="danger">
                        </div>
                    </div>

                    <!-- Box firme (docente) -->
                    <div id="firmeEsameBox" class="text-muted" style="margin-top:8px; display:none;"></div>

                    <!-- Tabella firme docenti (segreteria/dirigente/admin) -->
                    <div id="firmeDocentiEsameWrap" style="margin-top:10px; display:none;">
                        <label>Firme docenti</label>
                        <table class="table table-bordered table-striped" id="tabellaFirmeDocentiEsame">
                            <thead>
                                <tr>
                                    <th>Docente</th>
                                    <th class="text-center" style="width:120px;">Firmato</th>
                                    <th style="width:200px;">Firmato il</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Chiudi</button>
                    <button class="btn btn-primary" onclick="salvaEsame()">Salva</button>
                </div>

            </div>
        </div>
    </div>

    <!-- ============================= -->
    <!-- MODALE DUPLICA CORSO          -->
    <!-- ============================= -->
    <div class="modal fade" id="duplica_corso_modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-md modal-basso" role="document">
            <div class="modal-content">

                <div class="modal-header modal-header-blu">
                    <h4 class="modal-title">Duplica corso</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <div id="duplica_err" class="alert alert-danger" style="display:none;"></div>
                    <p>Verrà creato un nuovo corso duplicando <b>docenti (anche multipli), studenti e date</b> del corso corrente.</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button class="btn btn-primary" onclick="corsiDuplicaConfirm()">Duplica</button>
                </div>

            </div>
        </div>
    </div>


    <div id="toastMessage" style="
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        padding: 12px 20px;
        background: #28a745;
        color: white;
        border-radius: 5px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        display: none;
        z-index: 9999;
        text-align: center;
        white-space: nowrap;
    "></div>

    <!-- Custom JS file -->
    <!-- Custom JS file -->
    <script>
        window.GESTORE_RUOLO_EFF = <?php
                                    $ruolo_eff = $__utente_ruolo ?? '';
                                    if (impersonaRuolo('docente')) $ruolo_eff = 'docente';
                                    if (impersonaRuolo('esterno')) $ruolo_eff = 'esterno';
                                    echo json_encode($ruolo_eff);
                                    ?>;

        window.GESTORE_DOCENTE_ID_EFF = <?php
                                        $did = 0;

                                        // docente o esterno: prova $__docente_id, fallback username->docente.id
                                        if (impersonaRuolo('docente') || impersonaRuolo('esterno')) {
                                            $did = intval($__docente_id ?? 0);
                                            if ($did <= 0) {
                                                $u = addslashes($__username ?? '');
                                                $r = dbGetFirst("SELECT id FROM docente WHERE username='$u' LIMIT 1");
                                                if ($r) $did = intval($r['id']);
                                            }
                                        }

                                        echo json_encode($did);
                                        ?>;
    </script>
    <script src="js/corsi.00.core.js?v=<?php echo time(); ?>&a=<?php echo $anno_corsi; ?>"></script>
    <script src="js/corsi.10.corsi-modal.js?v=<?php echo time(); ?>&a=<?php echo $anno_corsi; ?>"></script>
    <script src="js/corsi.20.registro-esami.js?v=<?php echo time(); ?>&a=<?php echo $anno_corsi; ?>"></script>
</body>

</html>
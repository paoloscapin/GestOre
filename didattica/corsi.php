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
ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

if (!getSettingsValue('config', 'corsi', false)) {
    redirect("/error/unauthorized.php");
}

if (!getSettingsValue('corsi', 'visibile_docenti', false)) {
    ruoloRichiesto('segreteria-didattica');
}
?>

<!DOCTYPE html>
<html>

<head>
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <?php
    if (impersonaRuolo('docente')) {
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
            /* If max-width does not work, try using width instead */
            width: 450px;
            text-align: left;
        }

        /* Tabella studenti iscritti compatta e centrata */
        #iscritti_table {
            width: 60% !important;
            /* 50% della form */
            margin: 0 auto;
            /* centrata */
        }

        /* Colonna Nominativo più larga */
        #iscritti_table th:nth-child(1),
        #iscritti_table td:nth-child(1) {
            width: 600px;
            /* puoi regolare */
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
            /* regola secondo necessità */
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
            /* opzionale, per renderlo più visibile */
            text-align: center;
            display: block;
            /* utile per centrare il testo */
        }

        /* Tabella date */
        #date_table {
            width: 50% !important;
            margin: 0 auto;
        }

        /* Colonna Data più stretta (già a posto) */
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
            /* puoi regolare tra 80-120px */
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
            /* stesso colore testo della panel-heading */
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
            /* come i link e il testo della barra principale */
            opacity: 1;
            position: absolute;
            right: 15px;
            top: 15px;
        }

        .modal-footer {
            text-align: center;
            /* centra i pulsanti */
        }

        .modal-header-blu {
            background-color: #cce5ff;
            /* fallback */
            background-repeat: repeat-x;
            background-image: linear-gradient(#e6f0ff, #3399ff);
            /* gradiente celeste → blu */
            border-color: #3399ff #3399ff #2673cc;
            color: #000;
            /* testo nero */
            text-align: center;
            position: relative;
            text-shadow: none;
            /* rimuove ombra bianca */
        }

        .modal-header-blu .modal-title {
            margin: 0 auto;
            font-weight: bold;
            color: #000;
            /* assicura che il titolo sia nero */
        }

        .modal-header-blu .close {
            color: #000;
            /* X nera */
            opacity: 1;
            position: absolute;
            right: 15px;
            top: 15px;
        }

        /* sposta la modale più in basso */
        .modal-basso {
            margin-top: 150px;
            /* regola a piacere */
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

<body>
    <?php
    if (impersonaRuolo('docente')) {
        require_once '../common/header-docente.php';
    } else if (haRuolo('segreteria-didattica')) {
        require_once '../common/header-segreteria.php';
    }
    ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row align-items-start" style="margin-bottom:10px;">
                    <div class="col-md-1 text-center">
                        <span class="glyphicon glyphicon-list-alt"
                            style="margin:5px"></span><br><b>Elenco<br>Corsi</b>
                    </div>

                    <div class="col-md-3 text-center">
                        <label class="col-sm-12 control-label" for="materia">Materia</label>
                        <div class="text-center">
                            <div class="col-sm-12"><select id="materia_filtro" name="materia_filtro"
                                    class="mamteria_filtro selectpicker" data-style="btn-salmon" data-live-search="true"
                                    data-noneSelectedText="seleziona..."
                                    data-width="100%"><?php echo $materiaFiltroOptionList ?></select></div>
                        </div>
                    </div>
                    <?php
                    if (haRuolo('segreteria-didattica')) {
                        echo '
                    <div class="col-md-2">
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
                        <div class="col-md-1 text-center">
                            <label class="control-label" for="corso">Aggiungi</label>
                            <button class="btn btn-xs btn-lima4" style="display:block; margin: 5px auto 0;"
                                    onclick="corsiGetDetails(-1)">
                                <span style="font-size:15px" class="glyphicon glyphicon-plus"></span>
                            </button>
                        </div>

                        <div class="col-md-1 text-center" style="margin-top:20px;">
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
                           <div id="stato-invio" style="margin-top:10px; font-weight:bold; color:#333;"></div>
                        </div>
                    ';
                    }
                    ?>

                    <div class="col-md-2 text-center" style="margin-top:20px;">
                        <label class="checkbox-inline mb-0" style="line-height: 1; vertical-align: top;">
                            <input type="checkbox" data-toggle="toggle" data-size="mini"
                                data-onstyle="primary" id="futuri"> Solo Nuovi
                        </label><br>
                        <label id="incompleti" class="btn btn-xs btn-lima4 btn-file" data-toggle="tooltip" title="Esami incompleti">
                            <span class="glyphicon glyphicon-download"></span>&emsp;Incompleti
                        </label>
                    </div>


                    <div class="col-md-2 text-center" style="margin-top:20px;">
                        <label class="checkbox-inline mb-0" style="line-height: 1; vertical-align: top;">
                            <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                                data-onstyle="primary" id="carenze">Corsi carenze
                        </label><br>
                        <label class="checkbox-inline mb-0" style="line-height: 1; vertical-align: top;">
                            <input type="checkbox" data-toggle="toggle" data-size="mini"
                                data-onstyle="primary" id="filtro_itinere">Corsi in itinere
                        </label>
                    </div>
                </div>
            </div>
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
            <input type="hidden" id="hidden_corso_id">
        </div>
    </div>

    <div class="modal fade" id="corsi_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">

                <div class="modal-header modal-header-orange4">
                    <h4 class="modal-title w-100 text-center" id="myModalLabel">Dettagli Corso</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal">

                        <!-- docenti -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Docente</label>
                            <div class="col-sm-10">
                                <select id="docente" class="selectpicker form-control" data-live-search="true"><?php echo $docentiFiltroOptionList ?></select>
                            </div>
                        </div>

                        <!-- materia -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Materia</label>
                            <div class="col-sm-10">
                                <select id="materia" class="selectpicker form-control" data-live-search="true"><?php echo $materiaOptionList ?></select>
                            </div>
                        </div>

                        <!-- titolo -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Titolo</label>
                            <div class="col-sm-10">
                                <input type="text" id="titolo" class="form-control" placeholder="titolo" style="text-align:center;">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                            <button type="button" class="btn btn-primary" onclick="corsiSave()">Salva</button>
                        </div>
                        <hr> <!-- date -->
                        <!-- in_itinere -->
                        <div class="form-group text-center">
                            <label for="in_itinere" class="control-label" style="margin-right:10px;">In itinere</label>
                            <input type="checkbox" id="in_itinere"
                                data-toggle="toggle"
                                data-on="Sì" data-off="No"
                                data-onstyle="success" data-offstyle="danger">
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
                            <!-- Pulsante iscrivi studenti centrato -->
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

    <!-- Modal modifica data corso -->
    <div class="modal fade" id="modificaDataModal" tabindex="-1" role="dialog"
        data-backdrop="static" data-keyboard="false" aria-labelledby="modificaDataLabel">
        <div class="modal-dialog modal-sm modal-basso" role="document"> <!-- aggiunta classe modal-basso -->
            <div class="modal-content">

                <div class="modal-header modal-header-blu">
                    <h4 class="modal-title w-100 text-center" id="modificaDataLabel">Modifica Data</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <form id="formModificaData" class="form-horizontal">
                        <input type="hidden" id="hidden_data_id">

                        <div class="form-group text-center">
                            <label for="mod_data_inizio" class="control-label">Data e Ora Inizio</label>
                            <input type="datetime-local" id="mod_data_inizio" class="form-control" style="max-width:200px; margin:0 auto;">
                        </div>

                        <div class="form-group text-center">
                            <label for="mod_data_fine" class="control-label">Data e Ora Fine</label>
                            <input type="datetime-local" id="mod_data_fine" class="form-control" style="max-width:200px; margin:0 auto;">
                        </div>

                        <div class="form-group text-center">
                            <label for="mod_aula" class="control-label">Aula</label>
                            <input type="text" id="mod_aula" class="form-control" placeholder="Inserisci aula" style="max-width:200px; margin:0 auto;">
                        </div>

                        <div class="form-group text-danger text-center" id="error-modifica-data" style="display:none;"></div>
                    </form>
                </div>

                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="salvaModificaData()">Salva</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal aggiungi più studenti -->
    <div class="modal fade" id="aggiungiStudentiModal" tabindex="-1" role="dialog"
        data-backdrop="static" data-keyboard="false" aria-labelledby="aggiungiStudentiLabel">
        <div class="modal-dialog modal-sm modal-basso" role="document">
            <div class="modal-content">

                <div class="modal-header modal-header-blu">
                    <h4 class="modal-title w-100 text-center" id="aggiungiStudentiLabel">Aggiungi Studenti</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <form id="formAggiungiStudenti" class="form-horizontal">

                        <div id="container_studenti">
                            <!-- Qui appariranno i select dinamici -->
                        </div>

                        <div class="form-group text-danger text-center" id="error-aggiungi-studenti" style="display:none;"></div>
                    </form>
                </div>

                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" onclick="salvaNuoviStudenti()">Aggiungi</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal Registro Lezione -->
    <div class="modal fade" id="registroLezioneModal" tabindex="-1" role="dialog" aria-labelledby="registroLezioneLabel" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">

                <div class="modal-header modal-header-orange4">
                    <h4 class="modal-title w-100 text-center" id="registroLezioneLabel">Registro Lezione</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <form class="form-horizontal">

                        <!-- Select Date del Corso -->
                        <div class="form-group row justify-content-center">
                            <label class="col-sm-2 control-label text-center">Data Corso</label>
                            <div class="col-sm-6"> <!-- leggermente più largo -->
                                <select id="select_data_corso" class="selectpicker form-control" data-live-search="true">
                                    <!-- Le date verranno caricate tramite JS -->
                                </select>
                            </div>
                        </div>

                        <!-- Tabella studenti -->
                        <div class="form-group">
                            <label class="col-sm-12 text-center label-rosso">Studenti Iscritti</label>
                            <div class="col-sm-12">
                                <table class="table table-bordered table-striped text-center" id="tabellaStudenti">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Studente</th>
                                            <th class="text-center">Classe</th>
                                            <th class="text-center">Presente</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Popolato tramite JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Argomenti -->
                        <div class="form-group">
                            <label class="col-sm-12 text-center label-rosso">Argomenti Svolti</label>
                            <div class="col-sm-12">
                                <textarea id="argomentiLezione" class="form-control" rows="4" placeholder="Inserisci gli argomenti svolti..."></textarea>
                            </div>
                        </div>

                    </form>
                </div>

                <!-- Checkbox Firmato -->
                <div class="form-group text-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="lezioneFirmata" value="1">
                        <label class="form-check-label" for="lezioneFirmata">
                            FIRMA LA LEZIONE
                        </label>
                    </div>
                </div>

                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                    <button type="button" class="btn btn-primary" onclick="salvaRegistroLezione()">Salva</button>
                </div>

            </div>
        </div>
    </div>

<!-- Modal Esame -->
<div class="modal fade" id="esameModal" tabindex="-1" role="dialog" aria-labelledby="esameLabel" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header modal-header-orange4">
                <h4 class="modal-title w-100 text-center" id="esameLabel">Gestione Esame</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">
                <form class="form-horizontal">

                    <!-- 🔹 Nuovo selettore tentativo -->
                    <div class="form-group row">
                        <label class="col-sm-2 col-form-label text-right">Sessione</label>
                        <div class="col-sm-4">
                            <select id="select_tentativo" class="form-control">
                                <!-- Popolato via JS -->
                                <!-- <option value="1">Primo tentativo (01/06/2025)</option> -->
                                <!-- <option value="2">Secondo tentativo (non programmato)</option> -->
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="hidden_esame_data_id" value="">
                    <!-- Data, Ora, Aula -->
                    <div class="form-group row">
                        <label class="col-sm-2 col-form-label text-right">Data</label>
                        <div class="col-sm-2">
                            <input type="date" id="esame_inizio_data" class="form-control" style="text-align: center;">
                        </div>

                        <label class="col-sm-1 col-form-label text-center">Ora inizio</label>
                        <div class="col-sm-2">
                            <input type="time" id="esame_inizio_ora" class="form-control" style="text-align: center;">
                        </div>

                        <label class="col-sm-1 col-form-label text-center">Aula</label>
                        <div class="col-sm-2">
                            <input type="text" id="esame_aula" class="form-control">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-2 col-form-label text-right">Data</label>
                        <div class="col-sm-2">
                            <input type="date" id="esame_fine_data" class="form-control" style="text-align: center;">
                        </div>

                        <label class="col-sm-1 col-form-label text-center">Ora fine</label>
                        <div class="col-sm-2">
                            <input type="time" id="esame_fine_ora" class="form-control" style="text-align: center;">
                        </div>
                    </div>

                    <!-- Tabella studenti -->
                    <div class="form-group">
                        <label class="col-sm-12 text-center label-rosso">Studenti Iscritti</label>
                        <div class="col-sm-12">
                            <table class="table table-bordered table-striped text-center" id="tabellaEsameStudenti">
                                <thead>
                                    <tr>
                                        <th class="text-center">Studente</th>
                                        <th class="text-center">Classe</th>
                                        <th class="text-center">Presente</th>
                                        <th class="text-center">Tipo Prova</th>
                                        <th class="text-center">Voto</th>
                                        <th class="text-center">Carenza Recuperata</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Popolato via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Argomenti -->
                    <div class="form-group">
                        <label class="col-sm-12 text-center label-rosso">Argomenti della Verifica</label>
                        <div class="col-sm-12">
                            <textarea id="argomentiEsame" class="form-control" rows="4"
                                placeholder="Inserisci gli argomenti della prova..."></textarea>
                        </div>
                    </div>

                    <!-- Checkbox Firmato -->
                    <div class="form-group text-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="esameFirmato" value="1">
                            <label class="form-check-label" for="esameFirmato">
                                FIRMA L'ESAME
                            </label>
                        </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer center">
                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                <button type="button" class="btn btn-primary" onclick="salvaEsame()">Salva Esame</button>
            </div>

        </div>
    </div>
</div>




    <div id="toastMessage" style="
    position: fixed;
    top: 50%; /* centro verticale */
    left: 50%; /* centro orizzontale */
    transform: translate(-50%, -50%); /* correzione esatta del centro */
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
    <script type="text/javascript" src="js/corsi.js?v=<?php echo time(); ?>&a=<?php echo $anno_corsi; ?>"></script>
</body>

</html>
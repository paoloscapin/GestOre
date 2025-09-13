<?php


/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
?>

<!DOCTYPE html>
<html>

<head>
    <?php

    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-toggle.php';
    require_once '../common/_include_bootstrap-select.php';
    require_once '../common/_include_flatpickr.php';
    ruoloRichiesto('segreteria-didattica', 'dirigente');

    if (!(getSettingsValue('config', 'permessi', false))) {
        redirect("/error/unauthorized.php");
    }

    ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/it.js"></script>

    <!-- bootbox notificator -->
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

    <title>Permessi di uscita</title>
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

        th.sortable {
            cursor: pointer;
        }

        th.sorted-asc::after {
            content: " ▲";
            font-size: 0.8em;
        }

        th.sorted-desc::after {
            content: " ▼";
            font-size: 0.8em;
        }

        .date-picker-wrapper {
            display: inline-flex;
            align-items: center;
            background: #f5f5f5;
            border: 1px solid #ccc;
            border-radius: 25px;
            padding: 6px 12px;
            cursor: pointer;
            transition: 0.2s;
        }

        .date-picker-wrapper:hover {
            background: #e9ecef;
        }

        .date-picker-wrapper i {
            margin-right: 8px;
            color: #007bff;
        }

        .date-picker-wrapper input {
            border: none;
            background: transparent;
            font-weight: 500;
            width: 100px;
            text-align: center;
        }

        .date-picker-wrapper input:focus {
            outline: none;
        }
    </style>

</head>

<?php
// prepara l'elenco degli studenti per il filtro
$studenteFiltroOptionList = '<option value="0">Tutti</option>';

$studenti = dbGetAll("SELECT * FROM studente WHERE attivo=1 ORDER BY cognome, nome ASC");

foreach ($studenti as $studente) {
    $studenteFiltroOptionList .= '<option value="' . $studente['id'] . '">'
        . $studente['cognome'] . ' ' . $studente['nome'] . '</option>';
}
?>

<body>
    <?php
    require_once '../common/header-didattica.php';
    require_once '../common/connect.php';
    ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-2" style="padding:10px">
                        <span class="glyphicon glyphicon-blackboard"></span>&ensp;Permessi di uscita
                    </div>
                    <div class="col-md-4">
                    </div>
                    <div class="col-md-2" style="padding:0px; text-align:center">
                        <div class="checkbox" style="margin-top:10px;">
                            <label>
                                <input type="checkbox" id="solo_richiesti" checked> Solo richiesti
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2" style="padding:0px">
                        <div class="date-picker-wrapper">
                            <i class="glyphicon glyphicon-calendar"></i>
                            <input type="text" id="data_filtro" readonly>
                        </div>
                    </div>

                    <div class="col-md-2" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-2 control-label" for="studente"
                                style="margin:10px 0px 0px 0px; text-align:right">Studente</label>
                            <div class="col-sm-10" style="padding:0px 10px 0px 10px;text-align:right"><select id="studente_filtro" name="studente_filtro"
                                    class="studente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="85%">
                                    <?php echo $studenteFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="row" style="margin-bottom:10px;">
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

    </div>

    <!-- Modal - Add/Update Record -->
    <!-- Modale Permesso -->
    <div class="modal fade" id="permesso_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-lg" style="width:500px" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="panel panel-lima4">
                        <div class="panel-heading">
                            <h5 class="modal-title" style="text-align:center" id="myModalLabel">Permesso di uscita</h5>
                        </div>
                        <div class="panel-body">
                            <form class="form-horizontal">

                                <!-- Studente -->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label">Studente</label>
                                    <div class="col-sm-10">
                                        <input type="text" id="studente_nome" class="form-control" readonly />
                                    </div>
                                </div>

                                <!-- Classe -->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label">Classe</label>
                                    <div class="col-sm-10">
                                        <input type="text" id="studente_classe" class="form-control" readonly />
                                    </div>
                                </div>

                                <!-- Genitore -->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label">Genitore</label>
                                    <div class="col-sm-10">
                                        <input type="text" id="genitore_nome" class="form-control" readonly />
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="data">Data</label>
                                    <div class="col-sm-10">
                                        <input type="date" id="data" class="form-control" readonly />
                                        <small id="avvisoData" class="text-danger fw-bold" style="display:none;">
                                            ⚠️ Attenzione: la data del permesso sarà domani.
                                        </small>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="ora_uscita">Ora uscita</label>
                                    <div class="col-sm-10">
                                        <input type="time" id="ora_uscita" class="form-control step=" 60" placeholder="HH:MM" />
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="motivo">Motivo</label>
                                    <div class="col-sm-10">
                                        <textarea id="motivo" placeholder="motivo" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="rientro" class="col-sm-2 control-label">Rientro</label>
                                    <div class="col-sm-1">
                                        <input type="checkbox" id="rientro">
                                    </div>
                                </div>

                                <div class="form-group" id="ora_rientro_group" style="display:none;">
                                    <label class="col-sm-2 control-label" for="ora_rientro">Ora rientro</label>
                                    <div class="col-sm-10">
                                        <input type="time" id="ora_rientro" class="form-control" step="60" placeholder="HH:MM" />
                                    </div>
                                </div>

                                <!-- Stato del permesso -->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="stato">Stato</label>
                                    <div class="col-sm-10">
                                        <select id="stato" class="form-control">
                                            <option value="1">⏳ Richiesto</option>
                                            <option value="2">✅ Confermato</option>
                                            <option value="3">🚸 Assente</option>
                                            <option value="4">❌ Rifiutato</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Note della segreteria -->
                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="note_segreteria">Note</label>
                                    <div class="col-sm-10">
                                        <textarea id="note_segreteria" placeholder="Note della segreteria"
                                            class="form-control" rows="3"></textarea>
                                    </div>
                                </div>

                                <div class="form-group" id="_error-permesso-part">
                                    <strong>
                                        <hr>
                                        <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                        <div class="col-sm-9" id="_error-permesso"></div>
                                    </strong>
                                </div>

                                <input type="hidden" id="hidden_permesso_id">
                                <input type="hidden" id="hidden_studente_id">
                                <input type="hidden" id="hidden_rientro">
                            </form>
                        </div>
                        <div class="panel-footer text-center">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                            <button id="btn-save" type="button" class="btn btn-primary" onclick="permessoSave()">Salva</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS per Timepicker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/css/bootstrap-timepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/js/bootstrap-timepicker.min.js"></script>
    <script>
        $('.timepicker').timepicker({
            showMeridian: false, // 24h
            showSeconds: false, // nessun secondo
            defaultTime: false
        });
    </script>

    <!-- // Modal - Add/Update Record -->

    <!-- Custom JS file -->
    <script type="text/javascript" src="js/permessi.js?v=<?php echo $__software_version; ?>&d=desktop"></script>
</body>

</html>
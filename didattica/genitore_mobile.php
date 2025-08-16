<?php

/**
 *  Versione mobile della pagina Genitori - GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-didattica', 'dirigente');

$classiOptionList = '<option value="0">scegli classe</option>';
$classiFiltroOptionList = '<option value="0">Tutte</option>';
foreach (dbGetAll("SELECT * FROM classi WHERE attiva = '1' ORDER BY classe ASC") as $classi) {
    $classiOptionList .= '<option value="' . $classi['id'] . '">' . $classi['classe'] . '</option>';
    $classiFiltroOptionList .= '<option value="' . $classi['id'] . '">' . $classi['classe'] . '</option>';
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-toggle.php';
    require_once '../common/_include_bootstrap-select.php';
    require_once '../common/_include_flatpickr.php';
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/mobile.css">
    <title>Genitori - Mobile</title>
    <style>
        /* Mobile specific styles */
        body {
            font-size: 14px;
        }

        .container-fluid {
            margin-top: 10px;
            padding: 5px;
        }

        .panel-heading .row > div {
            margin-bottom: 5px;
        }

        .btn-xs {
            font-size: 12px;
            padding: 3px 6px;
        }

        .table {
            font-size: 12px;
        }

        .form-horizontal .form-group {
            margin-bottom: 10px;
        }

        .panel-body {
            padding: 10px;
        }

        .modal-dialog {
            width: 90%;
        }

        .selectpicker {
            width: 100% !important;
        }

        input[type="text"], input[type="email"] {
            font-size: 14px;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-didattica.php'; ?>

    <div class="container-fluid">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <span class="glyphicon glyphicon-pawn"></span>&ensp;Genitori
                    </div>
                    <div class="col-xs-12" style="margin-top:5px;">
                        <label for="classe_filtro">Classe</label>
                        <select id="classe_filtro" name="classe_filtro" class="classe_filtro selectpicker"
                            data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona...">
                            <?php echo $classiFiltroOptionList ?>
                        </select>
                    </div>
                    <div class="col-xs-12 text-center" style="margin-top:5px;">
                        <label class="btn btn-xs btn-lima4 btn-file">
                            <span class="glyphicon glyphicon-upload"></span> Importa
                            <input type="file" id="file_select_id" style="display: none;">
                        </label>
                    </div>
                    <div class="col-xs-12 text-center" style="margin-top:5px;">
                        <label class="checkbox-inline">
                            <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                                data-onstyle="primary" id="ancheSenzaStudentiCheckBox"> Anche senza studenti
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                                data-onstyle="primary" id="soloAttiviCheckBox"> Solo attivi
                        </label>
                    </div>
                    <div class="col-xs-12 text-center" style="margin-top:5px;">
                        <button class="btn btn-xs btn-orange4" onclick="genitoreGetDetails(-1)">
                            <span class="glyphicon glyphicon-plus"></span> Nuovo
                        </button>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div class="records_content"></div>
            </div>
        </div>

        <!-- Modal Mobile -->
        <div class="modal fade" id="genitore_modal" data-backdrop="static" tabindex="-1" role="dialog"
            aria-labelledby="myModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-lima4">
                            <div class="panel-heading">
                                <h5 class="modal-title">Genitore</h5>
                            </div>
                            <div class="panel-body">
                                <form class="form-horizontal">
                                    <div class="form-group">
                                        <label>Cognome</label>
                                        <input type="text" id="cognome" placeholder="Cognome" class="form-control" />
                                    </div>
                                    <div class="form-group">
                                        <label>Nome</label>
                                        <input type="text" id="nome" placeholder="Nome" class="form-control" />
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="text" id="email" placeholder="Email" class="form-control" />
                                    </div>
                                    <div class="form-group">
                                        <label>Codice Fiscale</label>
                                        <input type="text" id="codice_fiscale" placeholder="Codice Fiscale"
                                            class="form-control" />
                                    </div>
                                    <div class="form-group">
                                        <label>UserID MasterCom</label>
                                        <input type="text" id="userId" placeholder="UserID" class="form-control" />
                                    </div>
                                    <div class="form-group">
                                        <label>Attivo</label>
                                        <input type="checkbox" id="attivo">
                                    </div>

                                    <div class="form-group text-center" id="relazioni-part">
                                        <hr>
                                        <label>Studenti collegati</label>
                                        <div class="table-wrapper">
                                            <table class="table table-bordered table-striped" id="relazioni_table">
                                                <thead>
                                                    <tr>
                                                        <th>Studente</th>
                                                        <th>Relazione</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <input type="hidden" id="hidden_genitore_id">
                                    <input type="hidden" id="hidden_attivo">
                                    <input type="hidden" id="hidden_anno_id">
                                </form>
                            </div>
                            <div class="panel-footer text-center">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                <button id="btn-save" type="button" class="btn btn-primary"
                                    onclick="genitoreSave()">Salva</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- // Modal Mobile -->

    </div>

    <script type="text/javascript" src="js/genitore.js"></script>
</body>

</html>

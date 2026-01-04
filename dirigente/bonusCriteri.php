<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';

$anno_scolastico_id = isset($_GET['anno_scolastico_id'])
    ? intval($_GET['anno_scolastico_id'])
    : $__anno_scolastico_corrente_id;

$anni = dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY id DESC;");
?>
<!DOCTYPE html>
<html>

<head>
    <title>Gestione Criteri Bonus</title>

    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    ?>

    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
    <style>
        /* Colore fisso per le righe indicatore (applicato ai TD, così batte table-green) */
        .table-green tbody tr.bonus-indicatore-row td {
            background-color: #ffd901ff !important;
            /* colore fisso (azzurro chiaro) */
            font-weight: 600;
        }

        /* Mantieni l'hover giallo */
        .table-green tbody tr.bonus-indicatore-row:hover td {
            background-color: #fff3cd !important;
            /* giallo hover */
        }
    </style>
    <script type="text/javascript" src="js/scriptBonusCriteri.js"></script>
</head>

<body>
    <?php require_once '../common/header-dirigente.php'; ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-success">
            <div class="panel-heading container-fluid">
                <div class="row">
                    <div class="col-md-4">
                        <span class="glyphicon glyphicon-list-alt"></span>&emsp;<strong>Gestione Criteri Bonus</strong>
                    </div>

                    <div class="col-md-4 text-center">
                        <select id="anno_scolastico_select" class="form-control" style="display:inline-block; width:auto;">
                            <?php foreach ($anni as $a) {
                                $selected = ($a['id'] == $anno_scolastico_id) ? 'selected' : '';
                                echo '<option value="' . $a['id'] . '" ' . $selected . '>' . $a['anno'] . '</option>';
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-4 text-right">
                        <button class="btn btn-default btn-sm" id="btn_export_csv">
                            <span class="glyphicon glyphicon-download"></span>&ensp;CSV
                        </button>
                        &ensp;
                        <button class="btn btn-default btn-sm" id="btn_print_pdf">
                            <span class="glyphicon glyphicon-print"></span>&ensp;Stampa PDF
                        </button>
                        &ensp;
                        <button class="btn btn-primary btn-sm" id="btn_copy_prev">
                            <span class="glyphicon glyphicon-duplicate"></span>&ensp;Copia da anno precedente
                        </button>
                        &ensp;
                        <button class="btn btn-default btn-sm" id="btn_reload">
                            <span class="glyphicon glyphicon-refresh"></span>&ensp;Ricarica
                        </button>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div id="bonus_criteri_content"></div>
            </div>
        </div>
    </div>

    <!-- MODAL: Indicatore -->
    <div class="modal fade" id="modal_indicatore" tabindex="-1" role="dialog" aria-labelledby="modalIndicatoreLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h5 class="modal-title" id="modalIndicatoreLabel">Indicatore</h5>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="indicatore_id" value="0">
                    <input type="hidden" id="indicatore_area_id" value="0">

                    <div class="form-group">
                        <label for="indicatore_codice">Codice</label>
                        <input type="text" id="indicatore_codice" class="form-control" placeholder="es. A.1">
                    </div>

                    <div class="form-group">
                        <label for="indicatore_descrizione">Descrizione</label>
                        <input type="text" id="indicatore_descrizione" class="form-control" placeholder="descrizione">
                    </div>

                    <div class="form-group">
                        <label for="indicatore_valore_massimo">Valore massimo</label>
                        <input type="number" id="indicatore_valore_massimo" class="form-control" placeholder="es. 10">
                    </div>

                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="indicatore_valido" checked> Valido
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button class="btn btn-primary" id="btn_save_indicatore">Salva</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Bonus voce -->
    <div class="modal fade" id="modal_bonus" tabindex="-1" role="dialog" aria-labelledby="modalBonusLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h5 class="modal-title" id="modalBonusLabel">Bonus</h5>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="bonus_id" value="0">
                    <input type="hidden" id="bonus_indicatore_id" value="0">

                    <div class="form-group">
                        <label for="bonus_codice">Codice</label>
                        <input type="text" id="bonus_codice" class="form-control" placeholder="es. A.1.1">
                    </div>

                    <div class="form-group">
                        <label for="bonus_descrittori">Descrittori</label>
                        <textarea id="bonus_descrittori" class="form-control" rows="3" placeholder="descrittori"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="bonus_evidenze">Evidenze</label>
                        <textarea id="bonus_evidenze" class="form-control" rows="3" placeholder="evidenze"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="bonus_valore_previsto">Valore previsto</label>
                        <input type="number" id="bonus_valore_previsto" class="form-control" placeholder="es. 2">
                    </div>

                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="bonus_valido" checked> Valido
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button class="btn btn-primary" id="btn_save_bonus">Salva</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
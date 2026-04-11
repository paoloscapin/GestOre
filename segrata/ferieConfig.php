<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

$tipi = ['ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA'];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <title>Configurazione ferie ATA</title>
    <meta charset="UTF-8">
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    require_once '../common/_include_bootstrap-select.php';
    ?>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <style>
        .cfg-toolbar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-bottom: 12px;
        }

        .cfg-card {
            background: #fff;
            border: 1px solid #d9e2ea;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
            margin-bottom: 14px;
        }

        .cfg-card-head {
            padding: 12px 14px;
            border-bottom: 1px solid #e8edf2;
            font-weight: 700;
            color: #213047;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .cfg-card-body {
            padding: 14px;
        }

        .cfg-meta {
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .cfg-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cfg-table th,
        .cfg-table td {
            border: 1px solid #d9e2ea;
            padding: 8px 10px;
            vertical-align: middle;
            white-space: nowrap;
        }

        .cfg-table th {
            background: #f6f8fa;
            font-weight: 700;
        }

        .cfg-table td.wrap {
            white-space: normal;
        }

        .cfg-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .cfg-badge.ok {
            background: #dcfce7;
            color: #166534;
        }

        .cfg-badge.off {
            background: #e5e7eb;
            color: #374151;
        }

        .cfg-badge.exc {
            background: #fee2e2;
            color: #991b1b;
        }

        .cfg-badge.info {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .cfg-empty {
            padding: 18px;
            text-align: center;
            color: #6b7280;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            background: #fafafa;
        }

        .cfg-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        @media (max-width: 992px) {
            .cfg-split {
                grid-template-columns: 1fr;
            }
        }

        .table-responsive {
            overflow-x: auto;
        }

        .help-block-top {
            margin-top: 0;
            margin-bottom: 12px;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-segrata.php'; ?>

    <div class="container-fluid">
        <div class="panel panel-teal4">
            <div class="panel-heading container-fluid">
                <div class="row">
                    <div class="col-md-5">
                        <a href="permessi.php" class="btn btn-default btn-sm" style="margin-right:10px;">
                            <span class="glyphicon glyphicon-arrow-left"></span>
                        </a>

                        <span class="glyphicon glyphicon-calendar"></span>&ensp;Configurazione ferie ATA
                    </div>

                    <div class="col-md-7">
                        <div class="pull-right cfg-toolbar">
                            <button class="btn btn-primary btn-sm" type="button" id="btnNuovaFinestra">
                                <span class="glyphicon glyphicon-plus"></span>&ensp;Nuova finestra
                            </button>

                            <button class="btn btn-warning btn-sm" type="button" id="btnNuovoGiorno">
                                <span class="glyphicon glyphicon-plus"></span>&ensp;Nuovo giorno speciale
                            </button>

                            <button class="btn btn-default btn-sm" type="button" id="btnCfgRefresh">
                                <span class="glyphicon glyphicon-refresh"></span>&ensp;Aggiorna
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <p class="help-block help-block-top">
                    Qui la segreteria definisce le finestre ferie disponibili e i giorni speciali esclusi
                    (patrono, chiusure, festività locali, ecc.).
                </p>

                <div class="cfg-split">
                    <div class="cfg-card">
                        <div class="cfg-card-head">
                            <span>Finestre ferie</span>
                            <button class="btn btn-primary btn-xs" type="button" id="btnNuovaFinestraTop">
                                <span class="glyphicon glyphicon-plus"></span>&ensp;Nuova
                            </button>
                        </div>
                        <div class="cfg-card-body">
                            <div id="cfgFinestreMeta" class="cfg-meta"></div>
                            <div id="cfgFinestreWrap"></div>
                        </div>
                    </div>

                    <div class="cfg-card">
                        <div class="cfg-card-head">
                            <span>Giorni speciali</span>
                            <button class="btn btn-warning btn-xs" type="button" id="btnNuovoGiornoTop">
                                <span class="glyphicon glyphicon-plus"></span>&ensp;Nuovo
                            </button>
                        </div>
                        <div class="cfg-card-body">
                            <div id="cfgGiorniMeta" class="cfg-meta"></div>
                            <div id="cfgGiorniWrap"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal finestra -->
    <div class="modal fade" id="modalFinestra" tabindex="-1" role="dialog" aria-labelledby="modalFinestraLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="panel panel-teal4" style="margin-bottom:0;">
                    <div class="panel-heading">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="modalFinestraLabel">Finestra ferie</h4>
                    </div>

                    <div class="panel-body">
                        <input type="hidden" id="ff_id" value="0">

                        <div class="form-group">
                            <label for="ff_codice">Codice</label>
                            <select id="ff_codice" class="selectpicker form-control" data-style="btn-default" data-width="100%">
                                <?php foreach ($tipi as $t) { ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="ff_data_inizio">Data inizio</label>
                                    <input type="date" id="ff_data_inizio" class="form-control">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="ff_data_fine">Data fine</label>
                                    <input type="date" id="ff_data_fine" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="ff_valido" checked> Finestra attiva
                            </label>
                        </div>
                    </div>

                    <div class="panel-footer text-right">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                        <button type="button" class="btn btn-primary" id="btnSaveFinestra">
                            <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal giorno speciale -->
    <div class="modal fade" id="modalGiorno" tabindex="-1" role="dialog" aria-labelledby="modalGiornoLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="panel panel-teal4" style="margin-bottom:0;">
                    <div class="panel-heading">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="modalGiornoLabel">Giorno speciale</h4>
                    </div>

                    <div class="panel-body">
                        <input type="hidden" id="fg_id" value="0">

                        <div class="form-group">
                            <label for="fg_sottotipo">Sottotipo</label>
                            <select id="fg_sottotipo" class="selectpicker form-control" data-style="btn-default" data-width="100%">
                                <?php foreach ($tipi as $t) { ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="fg_data_giorno">Data giorno</label>
                            <input type="date" id="fg_data_giorno" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="fg_descrizione">Descrizione</label>
                            <input type="text" id="fg_descrizione" class="form-control" maxlength="255" placeholder="Es. Patrono / Chiusura amministrativa">
                        </div>

                        <div class="checkbox">
                            <label>
                                <input type="checkbox" id="fg_valido" checked> Giorno speciale attivo
                            </label>
                        </div>
                    </div>

                    <div class="panel-footer text-right">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                        <button type="button" class="btn btn-warning" id="btnSaveGiorno">
                            <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/ferieConfig.js?v=<?php echo time(); ?>"></script>
</body>

</html>
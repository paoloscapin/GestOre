<?php

/**
 * Dashboard ferie ATA - Segreteria
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

$finestraDefault = 'ESTIVE';
$modeDefault = 'APPROVATI_E_RICHIESTI';
$ordinarieDateFromDefault = date('Y-m-01');
$ordinarieDateToDefault = date('Y-m-t');

?>
<!DOCTYPE html>
<html lang="it">

<head>
    <title>Dashboard ferie ATA</title>
    <meta charset="UTF-8">
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    require_once '../common/_include_bootstrap-select.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .dash-wrap {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .dash-wrap .bootstrap-select {
            min-width: 210px !important;
        }

        .fd-range-wrap {
            display: none;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .fd-range-wrap.active {
            display: inline-flex;
        }

        .fd-range-wrap .form-control {
            width: 150px;
            height: 30px;
            padding: 4px 8px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid #d9e2ea;
            border-radius: 10px;
            padding: 12px 14px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        }

        .summary-card .k {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .summary-card .v {
            font-size: 24px;
            font-weight: 700;
            color: #213047;
            line-height: 1.1;
        }

        .summary-card .s {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .chart-card,
        .table-card,
        .heatmap-card {
            background: #fff;
            border: 1px solid #d9e2ea;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
            margin-bottom: 14px;
        }

        .card-head {
            padding: 12px 14px;
            border-bottom: 1px solid #e8edf2;
            font-weight: 700;
            color: #213047;
        }

        .card-body {
            padding: 14px;
        }

        #ferieChartWrap {
            min-height: 420px;
        }

        #ferieChart {
            width: 100%;
            height: 400px !important;
        }

        .office-summary-table,
        .heatmap-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .office-summary-table th,
        .office-summary-table td,
        .heatmap-table th,
        .heatmap-table td {
            border: 1px solid #d9e2ea;
            padding: 8px 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .office-summary-table th,
        .heatmap-table th {
            background: #f6f8fa;
            font-weight: 700;
        }

        .office-summary-table td.num,
        .heatmap-table td.num {
            text-align: center;
        }

        .heatmap-scroll {
            overflow-x: auto;
        }

        .heatmap-table th:first-child,
        .heatmap-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background: #fff;
            min-width: 190px;
            max-width: 190px;
        }

        .heatmap-table th:first-child {
            background: #f6f8fa;
            z-index: 3;
        }

        .heat-cell {
            text-align: center;
            font-weight: 700;
            min-width: 38px;
            max-width: 38px;
            padding: 6px 2px !important;
        }

        .legend {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 8px;
            font-size: 12px;
            color: #5b6570;
        }

        .legend .sw {
            width: 16px;
            height: 16px;
            border-radius: 3px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
            border: 1px solid rgba(0, 0, 0, .08);
        }

        .empty-box {
            padding: 20px;
            text-align: center;
            color: #6b7280;
        }

        @media (max-width: 1200px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .dash-wrap {
                justify-content: stretch;
            }

            .dash-wrap .bootstrap-select,
            .dash-wrap .btn,
            .dash-wrap .form-control {
                width: 100% !important;
            }
        }

        .dash-wrap {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .dash-wrap .bootstrap-select {
            min-width: 210px !important;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid #d9e2ea;
            border-radius: 10px;
            padding: 12px 14px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        }

        .summary-card .k {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .summary-card .v {
            font-size: 24px;
            font-weight: 700;
            color: #213047;
            line-height: 1.1;
        }

        .summary-card .s {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .chart-card,
        .table-card,
        .heatmap-card {
            background: #fff;
            border: 1px solid #d9e2ea;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
            margin-bottom: 14px;
        }

        .card-head {
            padding: 12px 14px;
            border-bottom: 1px solid #e8edf2;
            font-weight: 700;
            color: #213047;
        }

        .card-body {
            padding: 14px;
        }

        /* GRAFICO */
        #ferieChartWrap {
            position: relative;
            width: 100%;
            min-height: 520px;
            height: 520px;
        }

        #ferieChart {
            display: block;
            width: 100% !important;
            height: 520px !important;
        }

        /* TABELLA RIEPILOGO */
        .office-summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        .office-summary-table th,
        .office-summary-table td {
            border: 1px solid #d9e2ea;
            padding: 10px 12px;
            white-space: nowrap;
        }

        .office-summary-table th {
            background: #f6f8fa;
            font-weight: 700;
        }

        .office-summary-table td.num {
            text-align: center;
        }

        /* HEATMAP */
        .heatmap-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 4px;
        }

        .heatmap-table {
            width: max-content;
            min-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .heatmap-table th,
        .heatmap-table td {
            border: 1px solid #d9e2ea;
            padding: 6px 4px;
            white-space: nowrap;
            text-align: center;
        }

        .heatmap-table th {
            background: #f6f8fa;
            font-weight: 700;
            font-size: 11px;
        }

        .heatmap-table th:first-child,
        .heatmap-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background: #fff;
            min-width: 220px;
            max-width: 220px;
            text-align: left;
            padding-left: 10px;
        }

        .heatmap-table th:first-child {
            background: #f6f8fa;
            z-index: 3;
        }

        .heat-cell {
            text-align: center;
            font-weight: 700;
            min-width: 30px;
            width: 30px;
            max-width: 30px;
            height: 30px;
            padding: 0 !important;
            line-height: 30px;
            font-size: 11px;
        }

        .legend {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 8px;
            font-size: 12px;
            color: #5b6570;
        }

        .legend .sw {
            width: 16px;
            height: 16px;
            border-radius: 3px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
            border: 1px solid rgba(0, 0, 0, .08);
        }

        .empty-box {
            padding: 20px;
            text-align: center;
            color: #6b7280;
        }

        @media (max-width: 1200px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            #ferieChartWrap,
            #ferieChart {
                height: 480px !important;
                min-height: 480px;
            }
        }

        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .dash-wrap {
                justify-content: stretch;
            }

            .dash-wrap .bootstrap-select,
            .dash-wrap .btn,
            .dash-wrap .form-control {
                width: 100% !important;
            }

            .heatmap-table th:first-child,
            .heatmap-table td:first-child {
                min-width: 170px;
                max-width: 170px;
            }
        }

        #ferieChartWrap {
            position: relative;
            width: 100%;
            min-height: 560px;
            height: 560px;
        }

        #ferieChart {
            display: block;
            width: 100% !important;
            height: 560px !important;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-segrata.php'; ?>

    <div class="container-fluid">
        <div class="panel panel-teal4">
            <div class="panel-heading container-fluid">
                <div class="row">
                    <div class="col-md-4">
                        <a href="permessi.php" class="btn btn-default btn-sm" style="margin-right:10px;">
                            <span class="glyphicon glyphicon-arrow-left"></span>
                        </a>

                        <span class="glyphicon glyphicon-stats"></span>&ensp;Dashboard ferie ATA
                    </div>
                    <div class="col-md-8">
                        <div class="pull-right dash-wrap">
                            <select id="fd_finestra" class="selectpicker" data-width="220px" data-style="btn-default btn-sm">
                                <option value="ESTIVE" selected>Ferie estive</option>
                                <option value="NATALE">Ferie Natale</option>
                                <option value="CARNEVALE">Ferie Carnevale</option>
                                <option value="PASQUA">Ferie Pasqua</option>
                                <option value="ORDINARIE">Ferie ordinarie</option>
                            </select>

                            <div id="fd_range_wrap" class="fd-range-wrap">
                                <input type="date" id="fd_date_from" class="form-control input-sm" value="<?php echo htmlspecialchars($ordinarieDateFromDefault); ?>">
                                <input type="date" id="fd_date_to" class="form-control input-sm" value="<?php echo htmlspecialchars($ordinarieDateToDefault); ?>">
                            </div>

                            <select id="fd_mode" class="selectpicker" data-width="260px" data-style="btn-default btn-sm">
                                <option value="APPROVATI_E_RICHIESTI" selected>Approvati + richiesti</option>
                                <option value="APPROVATI_ONLY">Solo approvati</option>
                                <option value="RICHIESTI_ONLY">Solo richiesti</option>
                            </select>

                            <button class="btn btn-default btn-sm" id="fd_refresh" type="button">
                                <span class="glyphicon glyphicon-refresh"></span>&ensp;Aggiorna
                            </button>
                            <a href="#" class="btn btn-success btn-sm" id="fd_export_xls">
                                <span class="glyphicon glyphicon-download-alt"></span>&ensp;Export XLS
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div id="fd_meta" class="text-center text-muted" style="margin-bottom:12px;"></div>

                <div class="summary-grid" id="fd_summary">
                    <div class="summary-card">
                        <div class="k">Periodo</div>
                        <div class="v">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="k">Picco giornaliero</div>
                        <div class="v">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="k">Totale giorni/persona</div>
                        <div class="v">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="k">Uffici presenti</div>
                        <div class="v">-</div>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="card-head">Andamento giornaliero ferie per ufficio</div>
                    <div class="card-body">
                        <div id="ferieChartWrap">
                            <canvas id="ferieChart"></canvas>
                        </div>
                        <div class="legend">
                            <span><span class="sw" style="background:#dbeafe;"></span>basso</span>
                            <span><span class="sw" style="background:#93c5fd;"></span>medio</span>
                            <span><span class="sw" style="background:#3b82f6;"></span>alto</span>
                            <span><span class="sw" style="background:#1d4ed8;"></span>picco</span>
                        </div>
                    </div>
                </div>

                <div class="table-card">
                    <div class="card-head">Riepilogo per ufficio</div>
                    <div class="card-body">
                        <div id="fd_office_summary"></div>
                    </div>
                </div>

                <div class="heatmap-card">
                    <div class="card-head">Heatmap presenze ferie per ufficio</div>
                    <div class="card-body">
                        <div class="heatmap-scroll" id="fd_heatmap"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ferieDayModal" tabindex="-1" role="dialog" aria-labelledby="ferieDayModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="panel panel-teal4" style="margin-bottom:0;">
                    <div class="panel-heading">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="ferieDayModalLabel">Dettaglio ferie del giorno</h4>
                    </div>
                    <div class="panel-body">
                        <div class="well well-sm">
                            <strong>Data:</strong> <span id="fdd_date"></span>
                            &ensp;|&ensp;
                            <strong>Totale:</strong> <span id="fdd_total"></span>
                        </div>

                        <div id="fdd_content"></div>
                    </div>
                    <div class="panel-footer text-center">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/ferieDashboard.js?v=<?php echo filemtime(__DIR__ . '/js/ferieDashboard.js'); ?>"></script>
</body>

</html>

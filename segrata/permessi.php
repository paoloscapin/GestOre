<?php

/**
 * Permessi - Segreteria ATA
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

$tipiPermesso = dbGetAll("
    SELECT id, codice, descrizione
    FROM permesso_ata_tipo
    WHERE (valido IS NULL OR valido=1)
    ORDER BY codice
");
if (!is_array($tipiPermesso)) {
    $tipiPermesso = [];
}

$profiliAta = dbGetAll("
    SELECT id, nome
    FROM personale_ata_profili
    WHERE (attivo IS NULL OR attivo=1)
    ORDER BY nome
");
if (!is_array($profiliAta)) {
    $profiliAta = [];
}

$ufficiAta = dbGetAll("
    SELECT id, nome
    FROM personale_ata_uffici
    WHERE (attivo IS NULL OR attivo=1)
    ORDER BY nome
");
if (!is_array($ufficiAta)) {
    $ufficiAta = [];
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <title>Segreteria ATA - Permessi</title>
    <meta charset="UTF-8">
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    require_once '../common/_include_bootstrap-select.php';
    ?>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <style>
        .permessi-filters {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .permessi-filters .bootstrap-select {
            width: auto !important;
            min-width: unset !important;
        }

        .permessi-search {
            width: 360px;
            max-width: 100%;
        }

        .permessi-filters .btn-sm,
        .permessi-filters .bootstrap-select>.dropdown-toggle {
            height: 30px;
            padding: 4px 10px;
            line-height: 20px;
        }

        .bootstrap-select>.dropdown-toggle {
            padding-right: 34px !important;
        }

        .bootstrap-select>.dropdown-toggle .caret {
            right: 10px;
            margin-top: -2px;
            position: absolute;
            top: 50%;
        }

        .dash-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border: 1px solid rgba(0, 0, 0, .12);
            border-radius: 6px;
            background: #fff;
            min-height: 34px;
        }

        .dash-title {
            font-weight: 600;
            margin-right: 6px;
            white-space: nowrap;
        }

        .dash-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 16px;
            font-size: 12px;
            line-height: 1;
            border: 1px solid rgba(0, 0, 0, .08);
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }

        .dash-item .badge {
            margin-left: 2px;
            font-size: 11px;
        }

        .dash-item:hover {
            filter: brightness(0.97);
        }

        .dash-item.active {
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.10) inset;
            transform: scale(1.03);
        }

        .dash-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dash-mini {
            font-family: monospace;
            font-size: 12px;
            opacity: .85;
        }

        .dash-inviato {
            background: rgb(219, 248, 5);
        }

        .dash-approvato {
            background: rgb(4, 241, 95);
        }

        .dash-respinto {
            background: rgba(217, 83, 79, .35);
        }

        .dash-annullato {
            background: rgba(240, 173, 78, .35);
        }

        .dash-bozza {
            background: rgba(103, 155, 211, 0.83);
        }

        .trend-wrap {
            display: inline-block;
            vertical-align: middle;
            margin: 0 6px;
            white-space: nowrap;
        }

        .trend-dot {
            font-size: 18px;
            margin: 0 1px;
            line-height: 1;
            cursor: default;
        }

        .trend-low {
            color: #9aa0a6;
        }

        .trend-mid {
            color: #f0ad4e;
        }

        .trend-high {
            color: #5cb85c;
        }

        .trend-labels {
            color: #777;
            font-size: 12px;
            vertical-align: middle;
        }

        .permessi-table {
            table-layout: fixed;
            width: 100%;
        }

        .permessi-table th,
        .permessi-table td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .permessi-table td:nth-child(2) {
            white-space: normal;
        }

        .ferie-modal-wrap {
            background: #f5f6f8;
            border-radius: 18px;
        }

        .ferie-modal-top {
            background: #fff8dc;
            border: 1px solid #edd37a;
            border-radius: 18px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .ferie-modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #283548;
            margin-bottom: 6px;
        }

        .ferie-modal-subtitle {
            font-size: 14px;
            color: #5f6c7b;
            margin-bottom: 10px;
        }

        .ferie-modal-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
            margin-bottom: 14px;
            overflow: hidden;
        }

        .ferie-modal-card-head {
            padding: 16px 16px 8px 16px;
            border-bottom: 1px solid #edf0f3;
        }

        .ferie-modal-card-body {
            padding: 16px;
        }

        .ferie-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .ferie-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 700;
            background: #f7f8fa;
            border: 1px solid #e5e7eb;
            color: #364152;
        }

        .ferie-badge.selected {
            background: #fff3cd;
            border-color: #f0d36b;
            color: #7a5b00;
        }

        .ferie-badge.lock {
            background: #eef2ff;
            border-color: #cbd5ff;
            color: #3547a5;
        }

        .ferie-months-wrap {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 10px;
        }

        .ferie-month-card {
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
            background: #fff;
        }

        .ferie-month-head {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 14px;
            font-size: 20px;
            font-weight: 700;
            color: #24324a;
        }

        .ferie-month-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
            padding: 12px;
        }


        .ferie-day-cell {
            min-height: 78px;
            border-radius: 14px;
            border: 2px solid #d6dde6;
            background: #fff;
            padding: 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .ferie-day-cell.selected {
            background: #fff200;
            border-color: #d4b300;
            color: #2d2400;
        }

        .ferie-day-cell.locked {
            background: #e5e7eb;
            color: #6b7280;
            border-color: #c7ccd4;
        }

        .ferie-day-dow {
            font-size: 11px;
            font-weight: 700;
            color: #4b5563;
        }

        .ferie-day-num {
            font-size: 22px;
            font-weight: 800;
            color: #1f2a44;
            line-height: 1.1;
        }

        .ferie-day-meta {
            font-size: 11px;
            line-height: 1.2;
        }

        @media (max-width:768px) {
            .dash-bar {
                flex-wrap: wrap;
            }

            .dash-right {
                width: 100%;
                margin-left: 0;
                justify-content: flex-end;
            }
        }

        /* === GIORNI NORMALI (DISPONIBILI) === */
        .ferie-day-cell {
            min-height: 84px;
            border-radius: 14px;
            border: 2px solid #bfc8d4;
            background: #ffffff;
            padding: 8px 10px;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: stretch;
            gap: 8px;

            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* === GIORNI BLOCCATI (weekend, esclusi) === */
        .ferie-day-cell.locked {
            background: #cfd5dc;
            /* GRIGIO PIÙ SCURO */
            border-color: #9ea7b3;
            color: #5a6472;
        }

        /* === GIORNI SELEZIONATI === */
        .ferie-day-cell.selected {
            background: #ffe600;
            /* GIALLO PIÙ FORTE */
            border-color: #c9a800;
            color: #1f1a00;

            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.08) inset;
        }

        /* === BLOCCATI + SELEZIONATI (se capita) === */
        .ferie-day-cell.locked.selected {
            background: #d6c94a;
            /* giallo “spento” */
            border-color: #9e9230;
        }

        /* === COLONNE INTERNE === */
        .ferie-day-left {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex: 1;
        }

        .ferie-day-right {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-end;
            min-width: 48px;
        }

        /* === TESTI === */
        .ferie-day-dow {
            font-size: 11px;
            font-weight: 700;
            color: #4b5563;
        }

        .ferie-day-num {
            font-size: 24px;
            /* PIÙ GRANDE */
            font-weight: 900;
            /* PIÙ FORTE */
            color: #1f2a44;
        }

        /* contatori P/U */
        .ferie-day-meta-counts {
            font-size: 15px;
            font-weight: 800;
            color: #2f3a4a;

            display: flex;
            flex-direction: column;
            /* <-- verticale */
            align-items: flex-end;
            /* allineato a destra */
            gap: 2px;
        }

        .ferie-day-meta-counts .p-line,
        .ferie-day-meta-counts .u-line {
            display: block;
        }

        /* motivo (Sabato, Domenica, Patrono) */
        .ferie-day-meta-reason {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
        }

        /* === COLORI SPECIFICI SU LOCKED === */
        .ferie-day-cell.locked .ferie-day-num {
            color: #4b5563;
        }

        .ferie-day-cell.locked .ferie-day-dow {
            color: #6b7280;
        }

        /* === HOVER (opzionale ma bello) === */
        .ferie-day-cell:not(.locked):hover {
            transform: scale(1.03);
            transition: 0.15s;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.12);
        }

        .ferie-day-meta-counts .p-line,
        .ferie-day-meta-counts .u-line {
            display: block;
        }

        .p-line {
            color: #1f4ed8;
        }

        /* blu */
        .u-line {
            color: #047857;
        }

        /* verde */

        /* soglie occupazione */
        .ferie-day-cell.load-50 {
            background: #ffe08a;
            /* arancione chiaro */
        }

        .ferie-day-cell.load-75 {
            background: #ffb84d;
            /* arancione medio */
        }

        .ferie-day-cell.load-100 {
            background: #ff5c5c;
            /* rosso */
            color: #fff;
        }

        /* testo su rosso */
        .ferie-day-cell.load-100 .ferie-day-num,
        .ferie-day-cell.load-100 .ferie-day-dow,
        .ferie-day-cell.load-100 .ferie-day-meta-counts {
            color: #fff;
        }

        .ferie-day-cell.day-requested {
            background: #fff200;
            border-color: #d4b300;
            color: #2d2400;
        }

        .ferie-day-cell.day-approved {
            background: #b9f6ca;
            border-color: #4caf50;
            color: #124a1d;
        }

        .ferie-day-cell.day-rejected {
            background: #ffcdd2;
            border-color: #d32f2f;
            color: #7f1d1d;
        }

        .ferie-day-cell[data-clickable="1"] {
            cursor: pointer;
        }

        .ferie-day-cell[data-clickable="1"]:hover {
            transform: scale(1.03);
            transition: 0.15s;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.12);
        }

        .records_content .table-responsive {
            overflow-x: auto;
        }

        .permessi-table {
            table-layout: fixed;
            width: 100%;
            font-size: 13px;
        }

        .permessi-table th,
        .permessi-table td {
            padding: 8px 10px;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .permessi-table th:nth-child(1),
        .permessi-table td:nth-child(1) {
            width: 70px;
            /* ID */
        }

        .permessi-table th:nth-child(2),
        .permessi-table td:nth-child(2) {
            width: 210px;
            /* Dipendente */
        }

        .permessi-table th:nth-child(3),
        .permessi-table td:nth-child(3) {
            width: 120px;
            /* Matricola */
        }

        .permessi-table th:nth-child(4),
        .permessi-table td:nth-child(4) {
            width: 170px;
            /* Profilo */
        }

        .permessi-table th:nth-child(5),
        .permessi-table td:nth-child(5) {
            width: 170px;
            /* Ufficio */
        }

        .permessi-table th:nth-child(6),
        .permessi-table td:nth-child(6) {
            width: 240px;
            /* Tipo */
        }

        .permessi-table th:nth-child(7),
        .permessi-table td:nth-child(7) {
            width: 120px;
            /* Stato */
            text-align: center;
        }

        .permessi-table th:nth-child(8),
        .permessi-table td:nth-child(8) {
            width: 165px;
            /* Inviato */
            text-align: center;
        }

        .permessi-table th:nth-child(9),
        .permessi-table td:nth-child(9) {
            width: 90px;
            /* Azioni */
            text-align: center;
        }

        /* desktop medio, tipo portatile 15" */
        @media (max-width: 1500px) {
            .permessi-table {
                font-size: 12px;
            }

            .permessi-table th,
            .permessi-table td {
                padding: 7px 8px;
            }

            .permessi-table th:nth-child(3),
            .permessi-table td:nth-child(3) {
                display: none;
                /* Matricola */
            }

            .permessi-table th:nth-child(8),
            .permessi-table td:nth-child(8) {
                display: none;
                /* Inviato */
            }

            .permessi-table th:nth-child(2),
            .permessi-table td:nth-child(2) {
                width: 190px;
            }

            .permessi-table th:nth-child(4),
            .permessi-table td:nth-child(4),
            .permessi-table th:nth-child(5),
            .permessi-table td:nth-child(5) {
                width: 150px;
            }

            .permessi-table th:nth-child(6),
            .permessi-table td:nth-child(6) {
                width: 220px;
            }
        }

        .records_content .table-responsive {
            overflow-x: auto;
        }

        .permessi-table {
            table-layout: fixed;
            width: 100%;
            font-size: 13px;
        }

        .permessi-table th,
        .permessi-table td {
            padding: 8px 10px;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .permessi-table th:nth-child(1),
        .permessi-table td:nth-child(1) {
            width: 70px;
        }

        .permessi-table th:nth-child(2),
        .permessi-table td:nth-child(2) {
            width: 220px;
        }

        .permessi-table th:nth-child(3),
        .permessi-table td:nth-child(3) {
            width: 120px;
        }

        .permessi-table th:nth-child(4),
        .permessi-table td:nth-child(4) {
            width: 170px;
        }

        .permessi-table th:nth-child(5),
        .permessi-table td:nth-child(5) {
            width: 170px;
        }

        .permessi-table th:nth-child(6),
        .permessi-table td:nth-child(6) {
            width: 250px;
        }

        .permessi-table th:nth-child(7),
        .permessi-table td:nth-child(7) {
            width: 120px;
            text-align: center;
        }

        .permessi-table th:nth-child(8),
        .permessi-table td:nth-child(8) {
            width: 165px;
            text-align: center;
        }

        .permessi-table th:nth-child(9),
        .permessi-table td:nth-child(9) {
            width: 90px;
            text-align: center;
        }

        /* portatili 15" circa */
        @media (max-width: 1500px) {
            .permessi-table {
                font-size: 12px;
            }

            .permessi-table th,
            .permessi-table td {
                padding: 7px 8px;
            }

            .permessi-table th:nth-child(3),
            .permessi-table td:nth-child(3) {
                display: none;
                /* Matricola */
            }

            .permessi-table th:nth-child(8),
            .permessi-table td:nth-child(8) {
                display: none;
                /* Inviato */
            }

            /* Dipendente più stretto */
            .permessi-table th:nth-child(2),
            .permessi-table td:nth-child(2) {
                width: 180px;
                /* prima era 210/220 */
            }

            /* Profilo più largo */
            .permessi-table th:nth-child(4),
            .permessi-table td:nth-child(4) {
                width: 220px;
                /* prima era 150/170 */
            }

            /* Ufficio leggermente più largo */
            .permessi-table th:nth-child(5),
            .permessi-table td:nth-child(5) {
                width: 190px;
            }

            .permessi-table th:nth-child(6),
            .permessi-table td:nth-child(6) {
                width: 230px;
            }
        }

        .permessi-table .label {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-block;
            min-width: 90px;
            text-align: center;
            font-weight: 600;
        }

        .permessi-table .label {
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-width: 100px;
            justify-content: center;
            font-weight: 600;
        }

        .ferie-day-cell.other-approved {
            background: #d8f5dd;
            border-color: #5cb85c;
            color: #1f5d2a;
        }

        .ferie-day-cell.other-rejected {
            background: #f8d7da;
            border-color: #d9534f;
            color: #7a1f26;
        }

        .ferie-day-cell.other-requested {
            background: #fff3cd;
            border-color: #e0b84b;
            color: #7a5b00;
        }

        .ferie-day-cell.other-draft {
            background: #d9ecff;
            border-color: #5bc0de;
            color: #1c4f70;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-segrata.php'; ?>

    <div class="container-fluid">
        <div class="panel panel-teal4">
            <div class="panel-heading container-fluid">
                <div class="row">
                    <div class="col-md-3">
                        <span class="glyphicon glyphicon-th-list"></span>&ensp;Permessi ATA (Segreteria)

                        <a href="ferieDashboard.php"
                            class="btn btn-warning btn-sm"
                            style="margin-right:10px; font-weight:600;">
                            <span class="glyphicon glyphicon-stats"></span>&ensp;Dashboard ferie
                        </a>

                    </div>

                    <div class="col-md-9">
                        <div class="pull-right permessi-filters">
                            <select id="f_stato" class="selectpicker" data-width="160px" data-style="btn-default btn-sm">
                                <option value="" selected>Tutti gli stati</option>
                                <option value="INVIATO">INVIATO</option>
                                <option value="BOZZA">BOZZA</option>
                                <option value="APPROVATO">APPROVATO</option>
                                <option value="RESPINTO">RESPINTO</option>
                                <option value="ANNULLATO">ANNULLATO</option>
                            </select>

                            <select id="f_tipo" class="selectpicker" data-width="260px" data-style="btn-default btn-sm" data-live-search="true">
                                <option value="">Tutti i tipi</option>
                                <?php foreach ($tipiPermesso as $t) { ?>
                                    <option value="<?php echo intval($t['id']); ?>">
                                        <?php echo htmlspecialchars($t['codice'] . ' - ' . $t['descrizione']); ?>
                                    </option>
                                <?php } ?>
                            </select>

                            <select id="f_profilo" class="selectpicker" data-width="220px" data-style="btn-default btn-sm" data-live-search="true">
                                <option value="">Tutti i profili</option>
                                <?php foreach ($profiliAta as $p) { ?>
                                    <option value="<?php echo intval($p['id']); ?>">
                                        <?php echo htmlspecialchars($p['nome']); ?>
                                    </option>
                                <?php } ?>
                            </select>

                            <select id="f_ufficio" class="selectpicker" data-width="220px" data-style="btn-default btn-sm" data-live-search="true">
                                <option value="">Tutti gli uffici</option>
                                <?php foreach ($ufficiAta as $u) { ?>
                                    <option value="<?php echo intval($u['id']); ?>">
                                        <?php echo htmlspecialchars($u['nome']); ?>
                                    </option>
                                <?php } ?>
                            </select>

                            <div class="input-group input-group-sm permessi-search">
                                <input type="text" id="f_search" class="form-control" placeholder="Cerca (cognome, nome, matricola, email)">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" id="btn_refresh" type="button">
                                        <span class="glyphicon glyphicon-refresh"></span>&ensp;Aggiorna
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-12 text-center" id="result_text"></div>
                </div>

                <div class="row" style="margin-bottom:8px;">
                    <div class="col-md-12">
                        <div id="dash_bar" class="dash-bar">
                            <span class="dash-title">
                                <span class="glyphicon glyphicon-dashboard"></span>&ensp;Cruscotto
                            </span>

                            <span class="dash-item dash-inviato" id="d_inviato" data-stato="INVIATO"><span class="glyphicon glyphicon-send"></span> INVIATI <span class="badge">0</span></span>
                            <span class="dash-item dash-approvato" id="d_approvato" data-stato="APPROVATO"><span class="glyphicon glyphicon-ok"></span> APPROVATI <span class="badge">0</span></span>
                            <span class="dash-item dash-respinto" id="d_respinto" data-stato="RESPINTO"><span class="glyphicon glyphicon-remove"></span> RESPINTI <span class="badge">0</span></span>
                            <span class="dash-item dash-annullato" id="d_annullato" data-stato="ANNULLATO"><span class="glyphicon glyphicon-ban-circle"></span> ANNULLATI <span class="badge">0</span></span>
                            <span class="dash-item dash-bozza" id="d_bozza" data-stato="BOZZA"><span class="glyphicon glyphicon-edit"></span> BOZZE <span class="badge">0</span></span>

                            <span class="dash-right">
                                <span class="dash-mini" id="d_trend" title="Trend ultimi mesi"></span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="records_content"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="permesso_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="permessoModalLabel">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-teal4">
                            <div class="panel-heading">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                <h5 class="modal-title" id="permessoModalLabel">Gestione permesso</h5>
                            </div>

                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="well well-sm">
                                            <div><strong>Dipendente:</strong> <span id="d_nome"></span></div>
                                            <div><strong>Email:</strong> <span id="d_email"></span></div>
                                            <div><strong>Matricola:</strong> <span id="d_matricola"></span></div>
                                            <div><strong>Contratto:</strong> <span id="d_contratto"></span></div>
                                            <div><strong>Profilo:</strong> <span id="d_profilo"></span></div>
                                            <div><strong>Ufficio:</strong> <span id="d_ufficio"></span></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="well well-sm">
                                            <div><strong>Tipo:</strong> <span id="p_tipo"></span></div>
                                            <div><strong>Stato:</strong> <span id="p_stato"></span></div>
                                            <div><strong>Inviato il:</strong> <span id="p_created"></span></div>
                                            <div><strong>Ultimo aggiornamento:</strong> <span id="p_updated"></span></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Note del richiedente</label>
                                    <textarea class="form-control" rows="3" id="p_note_richiedente" readonly></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Note Segreteria</label>
                                    <textarea class="form-control" rows="3" id="p_note_segreteria" placeholder="Note interne / motivazione esito..."></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Intervalli / righe</label>
                                    <div id="righe_list"></div>
                                </div>

                                <input type="hidden" id="hidden_permesso_id" value="">
                            </div>

                            <div class="panel-footer text-center">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                                <button type="button" class="btn btn-primary" id="btn_save_permesso">
                                    <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva
                                </button>
                                <button type="button" class="btn btn-success" id="btn_approve">
                                    <span class="glyphicon glyphicon-ok"></span>&ensp;Approva
                                </button>
                                <button type="button" class="btn btn-danger" id="btn_reject">
                                    <span class="glyphicon glyphicon-remove"></span>&ensp;Respingi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="permesso_ferie_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="permessoFerieModalLabel">
            <div class="modal-dialog modal-lg" role="document" style="width:95%; max-width:1200px;">
                <div class="modal-content ferie-modal-wrap">
                    <div class="modal-body">
                        <div class="ferie-modal-top">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <div class="ferie-modal-title" id="fm_title">Dettaglio ferie</div>
                            <div class="ferie-modal-subtitle" id="fm_subtitle">Richiesta ferie del dipendente</div>

                            <div class="ferie-badges">
                                <span class="ferie-badge selected">
                                    <span class="glyphicon glyphicon-ok-circle"></span>
                                    Giorni selezionati: <span id="fm_count_selected">0</span>
                                </span>
                                <span class="ferie-badge lock">
                                    <span class="glyphicon glyphicon-lock"></span>
                                    Stato: <span id="fm_stato_badge">-</span>
                                </span>
                            </div>
                        </div>

                        <div class="ferie-modal-card">
                            <div class="ferie-modal-card-head"><strong>Dati dipendente</strong></div>
                            <div class="ferie-modal-card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div><strong>Dipendente:</strong> <span id="fm_nome"></span></div>
                                        <div><strong>Email:</strong> <span id="fm_email"></span></div>
                                        <div><strong>Matricola:</strong> <span id="fm_matricola"></span></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div><strong>Contratto:</strong> <span id="fm_contratto"></span></div>
                                        <div><strong>Profilo:</strong> <span id="fm_profilo"></span></div>
                                        <div><strong>Ufficio:</strong> <span id="fm_ufficio"></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ferie-modal-card">
                            <div class="ferie-modal-card-head"><strong>Calendario ferie</strong></div>
                            <div class="ferie-modal-card-body">
                                <div id="fm_other_requests_box"></div>
                                <div id="fm_months_wrap" class="ferie-months-wrap"></div>
                            </div>
                        </div>

                        <div class="ferie-modal-card">
                            <div class="ferie-modal-card-head"><strong>Note</strong></div>
                            <div class="ferie-modal-card-body">
                                <div class="form-group">
                                    <label>Note del richiedente</label>
                                    <textarea class="form-control" rows="3" id="fm_note_richiedente" readonly></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Note Segreteria</label>
                                    <textarea class="form-control" rows="3" id="fm_note_segreteria"></textarea>
                                </div>

                                <input type="hidden" id="fm_hidden_permesso_id" value="">
                            </div>
                        </div>

                        <div class="text-center" style="padding-top:8px;">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                            <button type="button" class="btn btn-primary" id="fm_btn_save_notes">
                                <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva note segreteria
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ferie_giorno_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="ferieGiornoModalLabel">
        <div class="modal-dialog" role="document" style="max-width:560px;">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="panel panel-teal4" style="margin-bottom:0;">
                        <div class="panel-heading">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <h5 class="modal-title" id="ferieGiornoModalLabel">Gestione singolo giorno ferie</h5>
                        </div>

                        <div class="panel-body">
                            <div class="well well-sm">
                                <div><strong>Dipendente:</strong> <span id="fg_dipendente"></span></div>
                                <div><strong>Data:</strong> <span id="fg_iso"></span></div>
                            </div>

                            <div class="form-group">
                                <label>Stato giorno</label>
                                <select id="fg_stato_giorno" class="form-control" style="width:auto;">
                                    <option value="RICHIESTO">RICHIESTO</option>
                                    <option value="APPROVATO">APPROVATO</option>
                                    <option value="RESPINTO">RESPINTO</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Nota approvatore</label>
                                <textarea id="fg_nota_approvatore" class="form-control" rows="3"></textarea>
                            </div>

                            <input type="hidden" id="fg_riga_id" value="">
                        </div>

                        <div class="panel-footer text-center">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>

                            <button type="button" class="btn btn-primary" id="fg_btn_save">
                                <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva
                            </button>

                            <button type="button" class="btn btn-success" id="fg_btn_approve">
                                <span class="glyphicon glyphicon-ok"></span>&ensp;Approva giorno
                            </button>

                            <button type="button" class="btn btn-danger" id="fg_btn_reject">
                                <span class="glyphicon glyphicon-remove"></span>&ensp;Respingi giorno
                            </button>

                            <hr style="margin:12px 0;">

                            <button type="button" class="btn btn-success" id="fg_btn_approve_all">
                                <span class="glyphicon glyphicon-ok-circle"></span>&ensp;Approva tutti i giorni
                            </button>

                            <button type="button" class="btn btn-danger" id="fg_btn_reject_all">
                                <span class="glyphicon glyphicon-remove-circle"></span>&ensp;Respingi tutti i giorni
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript" src="js/scriptPermessi.js?v=<?php echo time(); ?>"></script>
</body>

</html>
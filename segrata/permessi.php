<?php

/**
 * Permessi - Segreteria ATA
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata', 'ras');

$tipiPermesso = dbGetAll("
    SELECT id, codice, descrizione
    FROM permesso_ata_tipo
    WHERE (valido IS NULL OR valido=1)
    ORDER BY codice
");
if (!is_array($tipiPermesso)) {
    $tipiPermesso = [];
}

$ferieSottotipi = dbGetAll("
    SELECT DISTINCT UPPER(TRIM(codice)) AS sottotipo
    FROM permesso_ata_ferie_finestra
    WHERE codice IS NOT NULL
      AND TRIM(codice) <> ''
      AND (valido IS NULL OR valido=1)
    ORDER BY
      FIELD(UPPER(TRIM(codice)), 'ORDINARIE', 'ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA'),
      UPPER(TRIM(codice))
");
if (!is_array($ferieSottotipi)) {
    $ferieSottotipi = [];
}

$ferieSottotipiList = ['ORDINARIE'];
foreach ($ferieSottotipi as $fs) {
    $sottotipo = strtoupper(trim((string)($fs['sottotipo'] ?? '')));
    if ($sottotipo !== '' && !in_array($sottotipo, $ferieSottotipiList, true)) {
        $ferieSottotipiList[] = $sottotipo;
    }
}

function ferieSottotipoFilterLabel(string $sottotipo): string
{
    $map = [
        'ORDINARIE' => 'Ferie ordinarie',
        'ESTIVE' => 'Ferie estive',
        'NATALE' => 'Ferie Natale',
        'CARNEVALE' => 'Ferie Carnevale',
        'PASQUA' => 'Ferie Pasqua',
    ];

    $key = strtoupper(trim($sottotipo));
    return $map[$key] ?? ('Ferie ' . ucfirst(strtolower($key)));
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
            opacity: .38;
            color: #6f7a86;
            border-color: #c9d1d9;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .7);
            transform: none;
            filter: grayscale(.92) saturate(.28) brightness(1.03);
        }

        .dash-item .badge {
            margin-left: 2px;
            font-size: 11px;
        }

        .dash-item:hover {
            filter: brightness(0.97);
        }

        .dash-item.active {
            opacity: 1;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.10) inset;
            transform: scale(1.03);
            color: inherit;
            filter: none;
        }

        .dash-item.disabled {
            opacity: .22;
            cursor: not-allowed;
            pointer-events: none;
            filter: grayscale(.75);
            box-shadow: none;
            transform: none;
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

        .dash-parziale {
            background: rgba(255, 193, 7, .45);
        }

        .dash-da-registrare {
            background: rgba(91, 192, 222, .28);
        }

        .dash-registrato {
            background: rgba(92, 184, 92, .30);
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

        .ferie-day-cell.selected {
            background: #ffe600;
            border-color: #c9a800;
            color: #1f1a00;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.08) inset;
        }

        .ferie-day-cell.locked {
            background: #cfd5dc;
            border-color: #9ea7b3;
            color: #5a6472;
        }

        .ferie-day-cell.locked.selected {
            background: #d6c94a;
            border-color: #9e9230;
        }

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

        .ferie-day-dow {
            font-size: 11px;
            font-weight: 700;
            color: #4b5563;
        }

        .ferie-day-num {
            font-size: 24px;
            font-weight: 900;
            color: #1f2a44;
            line-height: 1.1;
        }

        .ferie-day-meta {
            font-size: 11px;
            line-height: 1.2;
        }

        .ferie-day-meta-counts {
            font-size: 15px;
            font-weight: 800;
            color: #2f3a4a;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
        }

        .ferie-day-meta-counts .p-line,
        .ferie-day-meta-counts .u-line {
            display: block;
        }

        .ferie-day-meta-reason {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
        }

        .ferie-day-cell.locked .ferie-day-num {
            color: #4b5563;
        }

        .ferie-day-cell.locked .ferie-day-dow {
            color: #6b7280;
        }

        .ferie-day-cell:not(.locked):hover {
            transform: scale(1.03);
            transition: 0.15s;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.12);
        }

        .p-line {
            color: #1f4ed8;
        }

        .u-line {
            color: #047857;
        }

        .ferie-day-cell.load-50 {
            background: #ffe08a;
        }

        .ferie-day-cell.load-75 {
            background: #ffb84d;
        }

        .ferie-day-cell.load-100 {
            background: #ff5c5c;
            color: #fff;
        }

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

        .ferie-day-cell.day-added {
            background: #d9f99d;
            border-color: #65a30d;
            color: #1f3b08;
        }

        .ferie-day-cell.day-removed,
        .ferie-day-cell.other-removed {
            background: #fed7aa;
            border-color: #f97316;
            color: #7c2d12;
            text-decoration: line-through;
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

        /* Colonne */
        .permessi-table th:nth-child(1),
        .permessi-table td:nth-child(1) {
            width: 70px;
            text-align: center;
        }

        /* Dipendente */
        .permessi-table th:nth-child(2),
        .permessi-table td:nth-child(2) {
            width: 150px;
            white-space: normal;
        }

        /* Matricola */
        .permessi-table th:nth-child(3),
        .permessi-table td:nth-child(3) {
            width: 80px;
        }

        /* Profilo */
        .permessi-table th:nth-child(4),
        .permessi-table td:nth-child(4) {
            width: 240px;
        }

        /* Ufficio */
        .permessi-table th:nth-child(5),
        .permessi-table td:nth-child(5) {
            width: 170px;
        }

        /* Tipo */
        .permessi-table th:nth-child(6),
        .permessi-table td:nth-child(6) {
            width: 330px;
            white-space: normal;
        }

        /* Stato */
        .permessi-table th:nth-child(7),
        .permessi-table td:nth-child(7) {
            width: 120px;
            text-align: center;
        }

        /* Inviato */
        .permessi-table th:nth-child(8),
        .permessi-table td:nth-child(8) {
            width: 165px;
            text-align: center;
        }

        /* Registro */
        .permessi-table th:nth-child(9),
        .permessi-table td:nth-child(9) {
            width: 130px;
            text-align: center;
        }

        /* Azioni */
        .permessi-table th:nth-child(10),
        .permessi-table td:nth-child(10) {
            width: 110px;
            text-align: center;
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

        @media (max-width: 1500px) {
            .permessi-table {
                font-size: 12px;
            }

            .permessi-table th,
            .permessi-table td {
                padding: 7px 8px;
            }

            /* nascondo matricola */
            .permessi-table th:nth-child(3),
            .permessi-table td:nth-child(3) {
                display: none;
            }

            /* nascondo inviato */
            .permessi-table th:nth-child(8),
            .permessi-table td:nth-child(8) {
                display: none;
            }

            .permessi-table th:nth-child(2),
            .permessi-table td:nth-child(2) {
                width: 180px;
            }

            .permessi-table th:nth-child(4),
            .permessi-table td:nth-child(4) {
                width: 220px;
            }

            .permessi-table th:nth-child(5),
            .permessi-table td:nth-child(5) {
                width: 190px;
            }

            .permessi-table th:nth-child(6),
            .permessi-table td:nth-child(6) {
                width: 240px;
            }

            .permessi-table th:nth-child(9),
            .permessi-table td:nth-child(9) {
                width: 120px;
            }

            .permessi-table th:nth-child(10),
            .permessi-table td:nth-child(10) {
                width: 100px;
            }
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

        .ferie-timeline {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .ferie-timeline li {
            padding: 8px 0;
            border-bottom: 1px solid #edf0f3;
        }

        .ferie-timeline li:last-child {
            border-bottom: 0;
        }

        .ferie-timeline-main {
            font-weight: 700;
            color: #273447;
        }

        .ferie-timeline-meta {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }

        .permessi-table th:nth-child(2),
        .permessi-table td:nth-child(2) {
            width: 150px;
            /* Dipendente */
        }

        .permessi-table th:nth-child(3),
        .permessi-table td:nth-child(3) {
            width: 80px;
            /* Matricola */
        }

        .permessi-table th:nth-child(4),
        .permessi-table td:nth-child(4) {
            width: 240px;
            /* Profilo */
        }

        .permessi-table th:nth-child(6),
        .permessi-table td:nth-child(6) {
            width: 330px;
            /* Tipo */
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

                        <a href="ferieConfig.php"
                            class="btn btn-info btn-sm"
                            style="margin-right:10px; font-weight:600;">
                            <span class="glyphicon glyphicon-calendar"></span>&ensp;Config ferie
                        </a>

                    </div>

                    <div class="col-md-9">
                        <div class="pull-right permessi-filters">
                            <select id="f_stato" class="selectpicker" data-width="160px" data-style="btn-default btn-sm">
                                <option value="" selected>Tutti gli stati</option>
                                <option value="INVIATO">INVIATO</option>
                                <option value="AGGIORNATA">AGGIORNATA</option>
                                <option value="APPROVATO">APPROVATO</option>
                                <option value="PARZIALE">PARZIALE</option>
                                <option value="RESPINTO">RESPINTO</option>
                                <option value="ANNULLATO">ANNULLATO</option>
                            </select>

                            <select id="f_tipo" class="selectpicker" data-width="260px" data-style="btn-default btn-sm" data-live-search="true">
                                <option value="">Tutti i tipi</option>
                                <?php foreach ($tipiPermesso as $t) { ?>
                                    <option value="<?php echo intval($t['id']); ?>">
                                        <?php echo htmlspecialchars($t['codice'] . ' - ' . $t['descrizione']); ?>
                                    </option>
                                    <?php if (strtoupper(trim((string)$t['codice'])) === 'FERIE') { ?>
                                        <?php foreach ($ferieSottotipiList as $sottotipo) { ?>
                                            <option value="FERIE:<?php echo htmlspecialchars($sottotipo); ?>">
                                                <?php echo htmlspecialchars('FERIE - ' . ferieSottotipoFilterLabel($sottotipo)); ?>
                                            </option>
                                        <?php } ?>
                                    <?php } ?>
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
                            <span class="dash-item dash-parziale" id="d_parziale" data-stato="PARZIALE">
                                <span class="glyphicon glyphicon-adjust"></span> PARZIALI <span class="badge">0</span></span>
                            <span class="dash-item dash-respinto" id="d_respinto" data-stato="RESPINTO"><span class="glyphicon glyphicon-remove"></span> RESPINTI <span class="badge">0</span></span>
                            <span class="dash-item dash-annullato" id="d_annullato" data-stato="ANNULLATO"><span class="glyphicon glyphicon-ban-circle"></span> ANNULLATI <span class="badge">0</span></span>
                            <span class="dash-item dash-da-registrare active" id="d_da_registrare" data-filter="registrazione" data-reg-filter="DA_REGISTRARE"><span class="glyphicon glyphicon-book"></span> DA REGISTRARE <span class="badge">0</span></span>
                            <span class="dash-item dash-registrato active" id="d_registrato" data-filter="registrazione" data-reg-filter="REGISTRATO"><span class="glyphicon glyphicon-folder-open"></span> REGISTRATO <span class="badge">0</span></span>

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
                                            <div id="p_gestito_wrap" style="display:none; margin-top:8px; font-size:13px; color:#5f6c7b;">
                                                <strong id="p_gestito_label">Gestito da:</strong>
                                                <span id="p_gestito_da"></span>
                                                <span id="p_gestito_il"></span>
                                            </div>
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
                                    <label style="display:block;">Registrazione segreteria</label>

                                    <div class="checkbox" style="margin-top:0;">
                                        <label style="font-weight:600;">
                                            <input type="checkbox" id="p_registrato_segreteria" value="1">
                                            Permesso registrato sul registro esterno
                                        </label>
                                    </div>

                                    <div id="p_registrazione_info" style="font-size:12px; color:#666; display:none;">
                                        Registrato da <span id="p_registrato_da"></span>
                                        <span id="p_registrato_il"></span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Intervalli / righe</label>
                                    <div id="righe_list"></div>
                                </div>

                                <input type="hidden" id="hidden_permesso_id" value="">
                            </div>

                            <div class="panel-footer text-center">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>

                                <a id="btn_print_permesso"
                                    href="#"
                                    class="btn btn-default"
                                    target="_blank"
                                    title="Stampa PDF">
                                    <span class="glyphicon glyphicon-print"></span>&ensp;Stampa
                                </a>

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
                            <div id="fm_gestito_wrap" style="display:none; margin-top:8px; font-size:13px; color:#5f6c7b;">
                                <strong id="fm_gestito_label">Aggiornata da:</strong>
                                <span id="fm_gestito_da"></span>
                                <span id="fm_gestito_il"></span>
                            </div>
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
                            <div class="ferie-modal-card-head"><strong>Ultime operazioni</strong></div>
                            <div class="ferie-modal-card-body" id="fm_timeline_wrap">
                                <div class="text-muted">Nessuna operazione registrata.</div>
                            </div>
                        </div>

                        <div class="ferie-modal-card">
                            <div class="ferie-modal-card-head"><strong>Registrazione segreteria</strong></div>
                            <div class="ferie-modal-card-body">
                                <div class="checkbox" style="margin-top:0;">
                                    <label style="font-weight:600;">
                                        <input type="checkbox" id="fm_registrato_segreteria" value="1">
                                        Richiesta registrata sul registro esterno
                                    </label>
                                </div>

                                <div id="fm_registrazione_info" style="font-size:12px; color:#666; display:none;">
                                    Registrato da <span id="fm_registrato_da"></span>
                                    <span id="fm_registrato_il"></span>
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

                            <a id="fm_btn_print_permesso"
                                href="#"
                                class="btn btn-default"
                                target="_blank"
                                title="Stampa PDF">
                                <span class="glyphicon glyphicon-print"></span>&ensp;Stampa
                            </a>

                            <button type="button" class="btn btn-primary" id="fm_btn_save_notes">
                                <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva
                            </button>

                            <button type="button" class="btn btn-success" id="fm_btn_save_send_mail">
                                <span class="glyphicon glyphicon-envelope"></span>&ensp;Salva e invia mail
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

    <script type="text/javascript" src="js/scriptPermessi.js?v=<?php echo filemtime(__DIR__ . '/js/scriptPermessi.js'); ?>"></script>
</body>

</html>

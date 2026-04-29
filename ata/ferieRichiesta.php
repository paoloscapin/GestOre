<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

$ferieTipo = dbGetFirst("
  SELECT id, codice, descrizione
  FROM permesso_ata_tipo
  WHERE codice = 'FERIE'
    AND (valido IS NULL OR valido=1)
  LIMIT 1
");

if (!$ferieTipo || !is_array($ferieTipo)) {
    throw new Exception('Tipo permesso FERIE non configurato o query fallita.');
}

$sottotipo = isset($_GET['sottotipo']) ? strtoupper(trim((string)$_GET['sottotipo'])) : 'ESTIVE';

$giorniSpecialiRows = dbGetAll("
  SELECT data_giorno, tipo, descrizione
  FROM permesso_ata_ferie_giorni_speciali
  WHERE UPPER(TRIM(sottotipo)) = " . dbQ($sottotipo) . "
    AND (valido IS NULL OR valido = 1)
  ORDER BY data_giorno ASC
");

$giorniSpeciali = [];
if (is_array($giorniSpecialiRows)) {
    foreach ($giorniSpecialiRows as $r) {
        $giorniSpeciali[] = [
            'data' => (string)$r['data_giorno'],
            'tipo' => strtoupper((string)$r['tipo']),
            'descrizione' => (string)($r['descrizione'] ?? ''),
        ];
    }
}

$finestraFerie = dbGetFirst("
  SELECT data_inizio, data_fine
  FROM permesso_ata_ferie_finestra
  WHERE UPPER(TRIM(codice)) = " . dbQ($sottotipo) . "
    AND (valido IS NULL OR valido=1)
  LIMIT 1
");

if (!$finestraFerie || !is_array($finestraFerie)) {
    throw new Exception('Finestra ferie ' . $sottotipo . ' non configurata o query fallita.');
}

$editId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$editHead = null;

if ($editId > 0) {
    $editHead = dbGetFirst("
      SELECT r.*, t.codice AS tipo_codice
      FROM permesso_ata_richiesta r
      INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
      WHERE r.id = $editId
        AND r.personale_ata_id = $__ata_id
        AND t.codice = 'FERIE'
        AND UPPER(TRIM(r.ferie_sottotipo)) = " . dbQ($sottotipo) . "
      LIMIT 1
    ");

    if (!$editHead) {
        $editId = 0;
    }
}

$titoli = [
    'ESTIVE' => 'Ferie estive',
    'NATALE' => 'Ferie Natale',
    'CARNEVALE' => 'Ferie Carnevale',
    'PASQUA' => 'Ferie Pasqua',
    'ORDINARIE' => 'Ferie ordinarie',
];

$finestreEscluseOrdinarie = [];

if ($sottotipo === 'ORDINARIE') {
    $rowsEscluse = dbGetAll("
      SELECT codice, data_inizio, data_fine
      FROM permesso_ata_ferie_finestra
      WHERE UPPER(TRIM(codice)) IN ('ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA')
        AND (valido IS NULL OR valido = 1)
      ORDER BY data_inizio ASC
    ");

    if (is_array($rowsEscluse)) {
        foreach ($rowsEscluse as $r) {
            $finestreEscluseOrdinarie[] = [
                'codice' => strtoupper(trim((string)$r['codice'])),
                'data_inizio' => (string)$r['data_inizio'],
                'data_fine' => (string)$r['data_fine'],
            ];
        }
    }
}

$bootstrapData = [
    'ferie_tipo_id' => (int)$ferieTipo['id'],
    'finestra' => [
        'data_inizio' => (string)$finestraFerie['data_inizio'],
        'data_fine'   => (string)$finestraFerie['data_fine'],
    ],
    'giorni_speciali' => $giorniSpeciali,
    'patrono_mmdd' => ($sottotipo === 'ESTIVE') ? '06-26' : '',
    'edit_id' => (int)$editId,
    'sottotipo' => $sottotipo,
    'finestre_escluse_ordinarie' => $finestreEscluseOrdinarie,
    'titolo' => $titoli[$sottotipo] ?? ('Ferie ' . $sottotipo),
    'calendar_state_url' => 'ferieRichiestaReadCalendarState.php'
];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($bootstrapData['titolo'], ENT_QUOTES, 'UTF-8'); ?> ATA</title>

    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    ?>

    <style>
        body {
            background: #f5f6f8;
        }

        .ferie-page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 12px 0 40px 0;
        }

        .ferie-top-card,
        .ferie-editor-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
            margin-bottom: 14px;
            overflow: hidden;
        }

        .ferie-top-card {
            background: #fff8dc;
            border-color: #edd37a;
            padding: 16px;
        }

        .ferie-title {
            font-size: 28px;
            font-weight: 700;
            color: #283548;
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .ferie-subtitle {
            font-size: 15px;
            color: #5f6c7b;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .ferie-toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ferie-toolbar .btn {
            min-height: 48px;
            border-radius: 14px;
            font-weight: 700;
        }

        .ferie-editor-head {
            padding: 16px 16px 8px 16px;
            border-bottom: 1px solid #edf0f3;
        }

        .ferie-editor-body {
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
            font-size: 14px;
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

        .ferie-badge.skip {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #4b5563;
        }

        .ferie-note textarea.form-control {
            min-height: 90px;
            border-radius: 14px;
            font-size: 16px;
            resize: vertical;
        }

        .ferie-alert {
            border-radius: 14px;
            font-size: 15px;
            line-height: 1.5;
        }

        .months-wrap {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 14px;
        }

        .month-card {
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
            background: #fff;
        }

        .month-head {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 14px;
            font-size: 20px;
            font-weight: 700;
            color: #24324a;
        }

        .month-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
            padding: 12px;
            width: 100%;
            box-sizing: border-box;
        }

        .day-cell {
            min-height: 92px;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            padding: 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all .15s ease;
            cursor: pointer;
            user-select: none;
            min-width: 0;
            overflow: hidden;
        }

        .day-cell:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .06);
        }

        .day-cell.empty {
            visibility: hidden;
            pointer-events: none;
            min-height: 0;
            padding: 0;
            border: none;
        }

        .day-cell {
            min-height: 92px;
            border-radius: 16px;
            border: 2px solid #d6dde6;
            background: #ffffff;
            padding: 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all .15s ease;
            cursor: pointer;
            user-select: none;
            min-width: 0;
            overflow: hidden;
        }

        .day-cell:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .06);
            border-color: #8fc7e8;
        }

        .day-cell.locked {
            cursor: not-allowed;
            background: #e5e7eb;
            color: #6b7280;
            border-color: #c7ccd4;
            box-shadow: none;
        }

        .day-cell.selected {
            background: #fff200;
            border-color: #d4b300;
            color: #2d2400;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .35);
        }

        .day-cell.readonly-selected {
            background: #b9f6ca;
            border-color: #4caf50;
            color: #124a1d;
            cursor: default;
        }

        .day-num {
            display: flex;
            align-items: baseline;
            gap: 6px;
            font-weight: 700;
            line-height: 1.2;
        }

        .day-meta {
            font-size: 12px;
            line-height: 1.3;
            min-height: 30px;
        }

        .day-dow {
            font-size: 12px;
            color: #4b5563;
            font-weight: 700;
        }

        .day-day {
            font-size: 22px;
            color: #1f2a44;
            font-weight: 800;
        }

        .day-lock-reason {
            font-size: 11px;
            line-height: 1.2;
            color: #4b5563;
            font-weight: 600;
        }

        .ferie-footer-actions {
            margin-top: 16px;
            padding-top: 10px;
        }

        .ferie-footer-actions .box {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
            padding: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ferie-footer-actions .btn {
            min-height: 52px;
            border-radius: 14px;
            font-size: 17px;
            font-weight: 700;
            flex: 1 1 180px;
        }

        @media (max-width: 767px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }

            .ferie-title {
                font-size: 24px;
            }

            .ferie-subtitle {
                font-size: 16px;
            }

            .month-grid {
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: 3px;
                padding: 4px;
                width: 100%;
                box-sizing: border-box;
            }

            .day-cell {
                min-height: 58px;
                border-radius: 10px;
                padding: 4px;
                min-width: 0;
                overflow: hidden;
                border-width: 2px;
            }

            .day-num {
                display: flex;
                align-items: baseline;
                gap: 2px;
                line-height: 1.1;
                flex-wrap: nowrap;
                white-space: nowrap;
            }

            .day-dow {
                font-size: 9px;
            }

            .day-day {
                font-size: 15px;
            }

            .day-meta {
                font-size: 9px;
                min-height: 14px;
                line-height: 1.1;
                overflow: hidden;
            }

            .day-meta {
                font-size: 12px;
                min-height: 18px;
                font-weight: 700;
                text-align: center;
            }

            .day-meta.status-meta {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .day-lock-reason {
                font-size: 8px;
                line-height: 1.05;
                overflow: hidden;
            }

            .ferie-footer-actions {
                position: static;
                bottom: auto;
                z-index: auto;
                background: transparent;
                padding-top: 10px;
                backdrop-filter: none;
            }

            .ferie-footer-actions .box {
                flex-direction: row;
                gap: 8px;
            }

            .ferie-footer-actions .btn {
                flex: 1 1 auto;
                min-height: 40px;
                font-size: 14px;
                padding: 6px 8px;
                border-radius: 10px;
            }

            .ferie-footer-actions .btn .glyphicon {
                font-size: 13px;
            }
        }

        .day-cell.historical-approved {
            background: #b9f6ca;
            border-color: #4caf50;
            color: #124a1d;
            cursor: not-allowed;
        }

        .day-cell.historical-rejected {
            background: #fff3b0;
            border-color: #d4b300;
            color: #5a4a00;
            cursor: not-allowed;
        }

        .day-cell.historical-requested {
            background: #fff3b0;
            border-color: #d4b300;
            color: #5a4a00;
            cursor: not-allowed;
        }

        .day-cell.historical-draft {
            background: #fff3b0;
            border-color: #d4b300;
            color: #5a4a00;
            cursor: not-allowed;
        }

        .day-cell.current-draft {
            background: #fff200;
            border-color: #d4b300;
            color: #2d2400;
        }

        .day-meta.status-meta {
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
        }

        .status-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.1;
        }

        .meta-icon {
            font-size: 14px;
            font-weight: 800;
        }

        .meta-label {
            font-size: 10px;
            font-weight: 600;
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-ata.php'; ?>

    <div class="container-fluid">
        <div class="ferie-page">
            <div class="ferie-top-card">
                <div class="ferie-title">
                    <span class="glyphicon glyphicon-calendar"></span>
                    <?php echo htmlspecialchars($bootstrapData['titolo'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="ferie-subtitle">
                    Seleziona i giorni desiderati nel calendario. Il conteggio ignora automaticamente sabato e domenica<?php echo ($sottotipo === 'ESTIVE') ? ', e il 26 giugno' : ''; ?>.
                </div>

                <div class="ferie-toolbar">
                    <a href="permessi.php" class="btn btn-default">
                        <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Torna ai permessi
                    </a>
                    <button type="button" class="btn btn-warning" id="btn_new_ferie">
                        <span class="glyphicon glyphicon-plus"></span>&ensp;Nuova richiesta
                    </button>
                </div>
            </div>

            <div class="ferie-editor-card">
                <div class="ferie-editor-head">
                    <div style="font-size:22px; font-weight:700; color:#24324a;" id="editor_title">
                        Nuova richiesta <?php echo htmlspecialchars(strtolower($bootstrapData['titolo']), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div style="margin-top:6px; color:#5f6c7b; font-size:15px;">
                        Periodo disponibile:
                        <strong id="periodo_testo"></strong>
                    </div>

                    <div class="ferie-badges">
                        <span class="ferie-badge selected">
                            <span class="glyphicon glyphicon-ok-circle"></span>
                            Giorni selezionati: <span id="count_selected">0</span>
                        </span>
                        <span class="ferie-badge skip">
                            <span class="glyphicon glyphicon-ban-circle"></span>
                            Esclusi automaticamente: sabati, domeniche<?php echo ($sottotipo === 'ESTIVE') ? ', 26 giugno' : ''; ?>
                        </span>
                        <span class="ferie-badge lock">
                            <span class="glyphicon glyphicon-lock"></span>
                            Stato: <span id="badge_stato">BOZZA</span>
                        </span>
                    </div>
                </div>

                <div class="ferie-editor-body">
                    <div id="ferie_alert" class="alert alert-danger ferie-alert" style="display:none;"></div>

                    <div class="ferie-note">
                        <div class="form-group">
                            <label for="ferie_note" style="font-size:16px; font-weight:700; color:#24324a;">Note</label>
                            <textarea id="ferie_note" class="form-control" placeholder="Note facoltative"></textarea>
                        </div>
                    </div>

                    <div class="alert alert-info ferie-alert">
                        Tocca o clicca i giorni disponibili per selezionarli o deselezionarli.
                    </div>

                    <div id="months_wrap" class="months-wrap"></div>

                    <input type="hidden" id="richiesta_id" value="<?php echo (int)$editId; ?>">
                </div>
            </div>

            <div class="ferie-footer-actions">
                <div class="box">
                    <button type="button" class="btn btn-danger" id="btn_delete_bozza_ferie" style="display:none;">
                        <span class="glyphicon glyphicon-trash"></span>&ensp;Elimina bozza
                    </button>
                    <button type="button" class="btn btn-warning" id="btn_rimetti_bozza_ferie" style="display:none;">
                        <span class="glyphicon glyphicon-repeat"></span>&ensp;Rimetti in bozza
                    </button>
                    <button type="button" class="btn btn-default" id="btn_cancel_ferie">
                        <span class="glyphicon glyphicon-remove"></span>&ensp;Annulla
                    </button>
                    <button type="button" class="btn btn-primary" id="btn_save_bozza_ferie">
                        <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva bozza
                    </button>
                    <button type="button" class="btn btn-success" id="btn_invia_ferie">
                        <span class="glyphicon glyphicon-send"></span>&ensp;Invia richiesta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.__FERIE_BOOTSTRAP = <?php echo json_encode($bootstrapData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script type="text/javascript" src="js/scriptFerieRichiestaAta.js?v=<?php echo filemtime(__DIR__ . '/js/scriptFerieRichiestaAta.js'); ?>"></script>
</body>

</html>

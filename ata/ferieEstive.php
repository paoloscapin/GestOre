<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$finestraEstive = dbGetFirst("
  SELECT data_inizio, data_fine
  FROM permesso_ata_ferie_finestra
  WHERE codice = 'ESTIVE'
    AND (valido IS NULL OR valido=1)
  LIMIT 1
");

if (!$finestraEstive || !is_array($finestraEstive)) {
    throw new Exception('Finestra ferie ESTIVE non configurata o query fallita.');
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
      AND r.ferie_sottotipo = 'ESTIVE'
    LIMIT 1
  ");

    if (!$editHead) {
        $editId = 0;
    }
}

$bootstrapData = [
    'ferie_tipo_id' => (int)$ferieTipo['id'],
    'finestra' => [
        'data_inizio' => (string)$finestraEstive['data_inizio'],
        'data_fine'   => (string)$finestraEstive['data_fine'],
    ],
    'patrono_mmdd' => '06-26',
    'edit_id' => (int)$editId,
];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ferie estive ATA</title>

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
            padding: 12px 0 100px 0;
        }

        .ferie-top-card,
        .ferie-editor-card,
        .ferie-records-card {
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

        .dow-cell {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            color: #6b7280;
            padding: 6px 0;
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

        .day-cell.locked {
            cursor: not-allowed;
            background: #f3f4f6;
            color: #9ca3af;
            border-color: #e5e7eb;
            box-shadow: none;
        }

        .day-cell.selected {
            background: #fff7d6;
            border-color: #efcf5b;
            color: #533f00;
        }

        .day-cell.readonly-selected {
            background: #e9f7ef;
            border-color: #9ad3ac;
            color: #245c39;
            cursor: default;
        }

        .day-num {
            display: flex;
            align-items: baseline;
            gap: 6px;
            font-weight: 700;
            line-height: 1.2;
        }

        .day-dow {
            font-size: 12px;
            color: #6b7280;
            text-transform: capitalize;
            /* opzionale */
        }

        .day-day {
            font-size: 22px;
            color: #24324a;
        }



        .day-meta {
            font-size: 12px;
            line-height: 1.3;
            min-height: 30px;
        }

        .day-lock-reason {
            font-size: 11px;
            line-height: 1.2;
            color: #6b7280;
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
                /* NON colonna */
                gap: 8px;
            }

            .ferie-footer-actions .btn {
                flex: 1 1 auto;
                min-height: 40px;
                /* più basso */
                font-size: 14px;
                /* testo più proporzionato */
                padding: 6px 8px;
                border-radius: 10px;
            }

            /* opzionale: icone più piccole */
            .ferie-footer-actions .btn .glyphicon {
                font-size: 13px;
            }

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
                    Richiesta ferie estive
                </div>
                <div class="ferie-subtitle">
                    Seleziona i giorni desiderati nel calendario. Il conteggio ignora automaticamente sabato, domenica e il 26 giugno.
                </div>

                <div class="ferie-toolbar">
                    <a href="permessi.php" class="btn btn-default">
                        <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Torna ai permessi
                    </a>
                    <button type="button" class="btn btn-warning" id="btn_new_estive">
                        <span class="glyphicon glyphicon-plus"></span>&ensp;Nuova richiesta ferie estive
                    </button>
                </div>
            </div>

            <div class="ferie-editor-card">
                <div class="ferie-editor-head">
                    <div style="font-size:22px; font-weight:700; color:#24324a;" id="editor_title">Nuova richiesta ferie estive</div>
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
                            Esclusi automaticamente: sabati, domeniche, 26 giugno
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
                    <button type="button" class="btn btn-default" id="btn_cancel_estive">
                        <span class="glyphicon glyphicon-remove"></span>&ensp;Annulla
                    </button>
                    <button type="button" class="btn btn-primary" id="btn_save_bozza_estive">
                        <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva bozza
                    </button>
                    <button type="button" class="btn btn-success" id="btn_invia_estive">
                        <span class="glyphicon glyphicon-send"></span>&ensp;Invia richiesta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.__FERIE_ESTIVE_BOOTSTRAP = <?php echo json_encode($bootstrapData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script type="text/javascript" src="js/scriptFerieEstiveAta.js"></script>
</body>

</html>
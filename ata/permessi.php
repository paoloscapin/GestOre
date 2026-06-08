<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

// tipi permesso validi
$tipi = dbGetAll("
  SELECT id, codice, descrizione
  FROM permesso_ata_tipo
  WHERE (valido IS NULL OR valido=1)
  ORDER BY codice;
");

// finestre ferie lato amministrativo (CARNEVALE/PASQUA/ESTIVE/NATALE)
$finestreFerie = dbGetAll("
  SELECT codice, data_inizio, data_fine
  FROM permesso_ata_ferie_finestra
  WHERE (valido IS NULL OR valido=1)
");
$finestreMap = [];
foreach ($finestreFerie as $f) {
    $cod = strtoupper(trim((string)$f['codice']));
    $finestreMap[$cod] = [
        'data_inizio' => $f['data_inizio'],
        'data_fine'   => $f['data_fine'],
    ];
}

$timeOptionsHtml = '';
for ($h = 7; $h <= 18; $h++) {
    foreach ([0, 15, 30, 45] as $m) {
        $timeOptionsHtml .= '<option value="' . sprintf('%02d:%02d', $h, $m) . '"></option>';
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permessi ATA - Le mie richieste</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    ?>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <style>
        body {
            background: #f5f6f8;
        }

        .permessi-page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 12px 0 40px 0;
        }

        .permessi-top-card,
        .permessi-editor-card,
        .permessi-records-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
            margin-bottom: 14px;
            overflow: hidden;
        }

        .permessi-top-card {
            background: #fff8dc;
            border-color: #edd37a;
            padding: 16px;
        }

        .permessi-title {
            font-size: 28px;
            font-weight: 700;
            color: #283548;
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .permessi-subtitle {
            font-size: 15px;
            color: #5f6c7b;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .permessi-toolbar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }

        .btn-estive-main {
            background: #ffefb0;
            border-color: #e7c85f;
            color: #4b3b05;
        }

        .btn-estive-main:hover,
        .btn-estive-main:focus {
            background: #ffe486;
            border-color: #d3b24d;
            color: #3a2d04;
        }

        .permessi-editor-card {
            display: none;
        }

        .permessi-editor-head {
            padding: 16px 16px 8px 16px;
            border-bottom: 1px solid #edf0f3;
            background: #fff8dc;
        }

        .permessi-editor-title {
            font-size: 22px;
            font-weight: 700;
            color: #24324a;
        }

        .permessi-editor-subtitle {
            margin-top: 6px;
            color: #5f6c7b;
            font-size: 15px;
            line-height: 1.4;
        }

        .permessi-editor-body {
            padding: 16px;
        }

        .permessi-footer-actions {
            margin-top: 16px;
            padding-top: 10px;
        }

        .permessi-footer-actions .box {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
            padding: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .permessi-footer-actions .btn {
            min-height: 46px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            flex: 1 1 180px;
        }

        .permesso-block-card {
            background: #fafbfc;
            border: 1px solid #e8ecf1;
            border-radius: 18px;
            padding: 14px;
            margin-bottom: 14px;
        }

        .permesso-block-title {
            font-size: 18px;
            font-weight: 700;
            color: #24324a;
            margin-bottom: 10px;
        }

        #permesso_editor label {
            font-size: 15px;
            font-weight: 700;
            color: #24324a;
            margin-bottom: 6px;
        }

        #permesso_editor .form-group {
            margin-bottom: 16px;
        }

        #permesso_editor .form-control {
            min-height: 46px;
            font-size: 16px;
            border-radius: 12px;
            padding: 10px 12px;
            border: 1px solid #d9dee5;
            box-shadow: none;
        }

        #permesso_editor .form-control:focus {
            border-color: #77bde6;
            box-shadow: 0 0 0 3px rgba(20, 170, 226, .10);
        }

        #permesso_editor textarea.form-control {
            min-height: 96px;
            resize: vertical;
        }

        #permesso_alert {
            border-radius: 14px;
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 16px !important;
        }

        #singolo_hint,
        #ferie_periodo_box,
        #block_104_multi .alert {
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.5;
        }

        .well.well-sm.ferie-riga,
        .well.well-sm.riga-104 {
            border-radius: 16px;
            padding: 14px;
            margin-bottom: 12px;
            background: #ffffff;
            border: 1px solid #e6ebf0;
            box-shadow: none;
        }

        #btn_add_ferie,
        #btn_add_104,
        #btn_add_singolo {
            min-height: 42px;
            font-size: 14px;
            border-radius: 10px;
            font-weight: 700;
        }

        .btn_del_ferie,
        .btn_del_104,
        .btn_del_singolo {
            min-height: 40px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .records_content .panel {
            border-radius: 18px !important;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        }

        .records_content .btn-lg {
            min-height: 52px;
            border-radius: 14px;
            font-size: 20px;
            font-weight: 700;
        }

        .records_content .label {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 13px;
        }

        .ferie-summary-modal .modal-content {
            border-radius: 20px;
            overflow: hidden;
            border: 0;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .25);
        }

        .ferie-summary-modal .modal-header {
            background: linear-gradient(135deg, #0f766e 0%, #22a8cf 100%);
            color: #fff;
            border-bottom: 0;
            padding: 18px 20px;
        }

        .ferie-summary-modal .modal-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .ferie-summary-modal .close {
            color: #fff;
            opacity: .9;
            text-shadow: none;
        }

        .ferie-summary-body {
            background: #f8fafc;
            padding: 16px;
        }

        .ferie-summary-cards {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .ferie-summary-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 12px;
            min-height: 76px;
        }

        .ferie-summary-card span {
            display: block;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .ferie-summary-card strong {
            display: block;
            color: #1e293b;
            font-size: 24px;
            line-height: 1.1;
            margin-top: 7px;
        }

        .ferie-summary-month {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 13px;
            margin-bottom: 12px;
        }

        .ferie-summary-month-title {
            color: #0f172a;
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .ferie-summary-days {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(86px, 1fr));
            gap: 8px;
        }

        .ferie-summary-day {
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 8px 6px;
            min-height: 68px;
            text-align: center;
            background: #f8fafc;
        }

        .ferie-summary-day .weekday {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #475569;
        }

        .ferie-summary-day .number {
            display: block;
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
            margin: 3px 0 4px;
        }

        .ferie-summary-day .state {
            display: block;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .ferie-summary-day.APPROVATO {
            background: #dcfce7;
            border-color: #86efac;
        }

        .ferie-summary-day.RESPINTO {
            background: #fee2e2;
            border-color: #fca5a5;
        }

        .ferie-summary-day.AGGIUNTO,
        .ferie-summary-day.RICHIESTO {
            background: #fef9c3;
            border-color: #fde047;
        }

        .ferie-summary-day.BOZZA {
            background: #e0f2fe;
            border-color: #7dd3fc;
        }

        .ferie-summary-day.current {
            box-shadow: inset 0 0 0 2px #0ea5e9;
        }

        .ferie-summary-empty {
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            padding: 18px;
            color: #64748b;
            text-align: center;
            font-size: 15px;
        }

        @media (max-width: 767px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }

            .permessi-page {
                max-width: 100%;
            }

            .permessi-title {
                font-size: 24px;
            }

            .permessi-subtitle {
                font-size: 16px;
            }

            .permessi-editor-title {
                font-size: 21px;
            }

            .permessi-editor-body {
                padding: 14px;
            }

            .permessi-footer-actions .box {
                flex-direction: row;
                gap: 8px;
            }

            .permessi-footer-actions .btn {
                flex: 1 1 auto;
                min-height: 40px;
                font-size: 14px;
                padding: 6px 8px;
                border-radius: 10px;
            }

            .permessi-footer-actions .btn .glyphicon {
                font-size: 13px;
            }

            .permesso-block-card {
                padding: 12px;
                border-radius: 16px;
            }

            .permesso-block-title {
                font-size: 17px;
            }

            .well.well-sm.ferie-riga .text-right,
            .well.well-sm.riga-104 .text-right,
            .well.well-sm.riga-singolo-extra .text-right {
                text-align: left !important;
            }

            .ferie-summary-cards {
                grid-template-columns: 1fr;
            }

            .ferie-summary-days {
                grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
            }

            .ferie-summary-day {
                min-height: 62px;
            }

            .ferie-summary-day .number {
                font-size: 21px;
            }
        }

        
    </style>
</head>

<body>
    <?php require_once '../common/header-ata.php'; ?>

    <div class="container-fluid">
        <div class="permessi-page">
            <div class="permessi-top-card">
                <div class="permessi-title">
                    <span class="glyphicon glyphicon-folder-open"></span>
                    Permessi ATA
                </div>
                <div class="permessi-subtitle">
                    Consulta le tue richieste oppure inseriscine una nuova.
                </div>

                <div class="permessi-toolbar">
                    <button class="btn btn-warning" id="btn_new">
                        <span class="glyphicon glyphicon-plus"></span>&ensp;Nuova richiesta
                    </button>

                    <?php if (isset($finestreMap['ESTIVE'])): ?>
                        <a class="btn btn-estive-main" href="ferieRichiesta.php?sottotipo=ESTIVE">
                            <span class="glyphicon glyphicon-calendar"></span>&ensp;Ferie estive
                        </a>
                    <?php endif; ?>

                    <?php if (isset($finestreMap['NATALE'])): ?>
                        <a class="btn btn-estive-main" href="ferieRichiesta.php?sottotipo=NATALE">
                            <span class="glyphicon glyphicon-tree-conifer"></span>&ensp;Ferie Natale
                        </a>
                    <?php endif; ?>

                    <?php if (isset($finestreMap['CARNEVALE'])): ?>
                        <a class="btn btn-estive-main" href="ferieRichiesta.php?sottotipo=CARNEVALE">
                            <span class="glyphicon glyphicon-star"></span>&ensp;Ferie Carnevale
                        </a>
                    <?php endif; ?>

                    <?php if (isset($finestreMap['PASQUA'])): ?>
                        <a class="btn btn-estive-main" href="ferieRichiesta.php?sottotipo=PASQUA">
                            <span class="glyphicon glyphicon-leaf"></span>&ensp;Ferie Pasqua
                        </a>
                    <?php endif; ?>

                    <?php if (isset($finestreMap['ORDINARIE'])): ?>
                        <a class="btn btn-estive-main" href="ferieRichiesta.php?sottotipo=ORDINARIE">
                            <span class="glyphicon glyphicon-briefcase"></span>&ensp;Ferie ordinarie
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="permessi-editor-card" id="permesso_editor">
                <div class="permessi-editor-head">
                    <div class="permessi-editor-title" id="permesso_editor_title">Nuova richiesta</div>
                    <div class="permessi-editor-subtitle">
                        Compila i campi richiesti e salva in bozza oppure invia la richiesta.
                    </div>
                </div>

                <div class="permessi-editor-body">
                    <div id="permesso_alert" class="alert alert-danger" style="display:none;"></div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="permesso_tipo_id">Tipo permesso</label>
                                <select class="form-control" id="permesso_tipo_id">
                                    <option value="">Seleziona...</option>
                                    <?php foreach ($tipi as $t): ?>
                                        <?php if (strtoupper(trim((string)$t['codice'])) === 'FERIE') continue; ?>
                                        <option value="<?php echo (int)$t['id']; ?>"
                                            data-codice="<?php echo htmlspecialchars($t['codice']); ?>">
                                            <?php echo htmlspecialchars($t['codice'] . ' - ' . $t['descrizione']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Stato</label>
                                <input type="text" class="form-control" id="permesso_stato" readonly value="BOZZA">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="permesso_note">Note</label>
                        <textarea class="form-control" rows="3" id="permesso_note" placeholder="Note (facoltative)"></textarea>
                    </div>

                    <div id="block_singolo" class="permesso-block-card" style="display:none;">
                        <div class="permesso-block-title">Dettagli richiesta</div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="singolo_data">Data</label>
                                    <input type="date" class="form-control" id="singolo_data">
                                </div>
                            </div>

                            <div class="col-md-4" id="block_singolo_ora_da" style="display:none;">
                                <div class="form-group">
                                    <label for="singolo_ora_da">Dalle ore</label>
                                    <input type="text" class="form-control time-input" id="singolo_ora_da" list="ata_time_options" inputmode="numeric" maxlength="5" placeholder="HH:MM" autocomplete="off">
                                </div>
                            </div>

                            <div class="col-md-4" id="block_singolo_ora_a" style="display:none;">
                                <div class="form-group">
                                    <label for="singolo_ora_a">Ora rientro</label>
                                    <input type="text" class="form-control time-input" id="singolo_ora_a" list="ata_time_options" inputmode="numeric" maxlength="5" placeholder="HH:MM" autocomplete="off">
                                </div>
                            </div>

                            <div class="col-md-4" id="block_singolo_durata_ore" style="display:none;">
                                <div class="form-group">
                                    <label for="singolo_durata_ore">Recupero di ore</label>
                                    <input type="number" class="form-control" id="singolo_durata_ore" min="1" step="1" inputmode="numeric" placeholder="N ore">
                                </div>
                            </div>
                        </div>
                        <div id="righe_singolo_extra_container" style="margin-top:10px;"></div>
                        <div class="alert alert-info" id="singolo_hint" style="display:none; padding:8px; margin-bottom:0;"></div>
                    </div>

                    <div id="block_104_multi" class="permesso-block-card" style="display:none;">
                        <div class="alert alert-info" style="padding:8px; margin-top:8px; margin-bottom:8px;">
                            Puoi inserire:
                            <ul style="margin:6px 0 0 18px;">
                                <li><b>GIORNI</b>: dal/al (senza ore)</li>
                                <li><b>ORE</b>: un solo giorno + ora di inizio + numero di ore</li>
                            </ul>
                        </div>

                        <div id="righe_104_container" style="margin-top:10px;"></div>
                    </div>

                    <div class="permessi-footer-actions">
                        <div class="box">
                            <button type="button" class="btn btn-default" id="btn_cancel_permesso">
                                <span class="glyphicon glyphicon-remove"></span>&ensp;Annulla
                            </button>

                            <button type="button" class="btn btn-default" id="btn_add_singolo" style="display:none;">
                                <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi riga
                            </button>

                            <button type="button" class="btn btn-default" id="btn_add_104" style="display:none;">
                                <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi riga
                            </button>

                            <button type="button" class="btn btn-warning" id="btn_rimetti_bozza" style="display:none;">
                                <span class="glyphicon glyphicon-repeat"></span>&ensp;Rimetti in bozza
                            </button>

                            <button type="button" class="btn btn-primary" id="btn_save_bozza">
                                <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva bozza
                            </button>

                            <button type="button" class="btn btn-success" id="btn_invia">
                                <span class="glyphicon glyphicon-send"></span>&ensp;Invia richiesta
                            </button>

                            <input type="hidden" id="permesso_id" value="">
                        </div>
                    </div>
                </div>
            </div>

            <div class="permessi-records-card" id="permessi_records_wrap">
                <div style="padding:16px;" id="records_content"></div>
            </div>
        </div>
    </div>

    <div class="modal fade ferie-summary-modal" id="ferie_summary_modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><span class="glyphicon glyphicon-calendar"></span> Riepilogo ferie</h4>
                </div>
                <div class="ferie-summary-body" id="ferie_summary_content">
                    <div class="ferie-summary-empty">Caricamento riepilogo...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-lg" data-dismiss="modal">Chiudi</button>
                </div>
            </div>
        </div>
    </div>

    <datalist id="ata_time_options">
        <?php echo $timeOptionsHtml; ?>
    </datalist>

    <script>
        window.__FERIE_FINESTRE = <?php echo json_encode($finestreMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>

    <script type="text/javascript" src="js/scriptPermessiAta.js?v=<?php echo filemtime(__DIR__ . '/js/scriptPermessiAta.js'); ?>"></script>
</body>

</html>

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
                                    <label for="singolo_ora_a">Ore a</label>
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

    <datalist id="ata_time_options">
        <?php echo $timeOptionsHtml; ?>
    </datalist>

    <script>
        window.__FERIE_FINESTRE = <?php echo json_encode($finestreMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>

    <script type="text/javascript" src="js/scriptPermessiAta.js?v=<?php echo filemtime(__DIR__ . '/js/scriptPermessiAta.js'); ?>"></script>
</body>

</html>

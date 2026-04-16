<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/connectMBApp.php';

ruoloRichiesto('personale-ata', 'portineria', 'segreteria-ata', 'docente', 'dirigente', 'studente', 'genitore');

global $__utente_ruolo;
$ruolo = strtoupper(trim((string)$__utente_ruolo));

$isPublicOrario = in_array($ruolo, ['STUDENTE', 'GENITORE'], true);

global $__utente_nome, $__utente_cognome, $__utente_username, $__username;

$displayUser = trim(
    (string)($__utente_cognome ?? '') . ' ' . (string)($__utente_nome ?? '')
);

if ($displayUser === '') {
    $displayUser = trim((string)($__utente_username ?? ''));
}
if ($displayUser === '') {
    $displayUser = trim((string)($__username ?? ''));
}

function renderOrarioHeaderByRole($ruolo)
{
    switch ($ruolo) {
        case 'ADMIN':
            require_once '../common/header-admin.php';
            break;

        case 'SEGRETERIA-DIDATTICA':
            require_once '../common/header-didattica.php';
            break;

        case 'SEGRETERIA-ATA':
            require_once '../common/header-segrata.php';
            break;

        case 'SEGRETERIA-DOCENTI':
        case 'SEGRETERIA':
            require_once '../common/header-segreteria.php';
            break;

        case 'DOCENTE':
            require_once '../common/header-docente.php';
            break;

        case 'PERSONALE-ATA':
        case 'PORTINERIA':
            require_once '../common/header-ata.php';
            break;

        case 'STUDENTE':
            require_once '../common/header-studente.php';
            break;

        case 'GENITORE':
            require_once '../common/header-genitore.php';
            break;

        default:
            require_once '../common/header-docente.php';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Orario mobile (MBApp)</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="./css/orario_mobile.css?t=<?= time() ?>">
</head>

<body class="orario-mobile-page">
    <div class="mobile-topbar">
        <div class="mobile-topbar-inner">
            <div class="mobile-topbar-left">
                <div class="mobile-topbar-brand">
                    <span class="glyphicon glyphicon-calendar"></span>
                    <span>GestOre</span>
                </div>
            </div>

            <div class="mobile-topbar-actions">
                <a href="../index.php" class="btn btn-warning btn-sm mobile-home-btn">
                    <span class="glyphicon glyphicon-home"></span>
                    Home
                </a>

                <a href="../logout.php" class="btn btn-default btn-sm mobile-logout-btn" title="Esci">
                    <span class="glyphicon glyphicon-log-out"></span>
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid mobile-orario-shell">
        <div class="panel panel-teal4 mobile-panel">
            <div class="panel-heading mobile-panel-heading">
                <div class="mobile-scope-tabs" id="mobile_scope_tabs">
                    <button type="button" class="mobile-scope-tab" data-scope="EVENTI">Eventi</button>
                    <button type="button" class="mobile-scope-tab" data-scope="AULA">Aule</button>
                    <button type="button" class="mobile-scope-tab" data-scope="CLASSE">Classi</button>
                    <button type="button" class="mobile-scope-tab" data-scope="DOCENTE">Docenti</button>
                    <?php if (!$isPublicOrario): ?>
                        <button type="button" class="mobile-scope-tab" data-scope="ASSENZE">Assenze</button>
                        <button type="button" class="mobile-scope-tab" data-scope="SOSTITUZIONI">Sostit.</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel-body mobile-panel-body">
                <div class="mobile-toolbar">

                    <div class="mobile-toolbar-block mobile-toolbar-block-scope-select" style="display:none;">
                        <label class="mobile-label" for="v_scope_mobile">Vista</label>
                        <select id="v_scope_mobile" class="form-control input-sm">
                            <option value="AULA">Aula</option>
                            <option value="CLASSE">Classe</option>
                            <option value="DOCENTE">Docente</option>
                            <option value="EVENTI">Eventi</option>
                            <?php if (!$isPublicOrario): ?>
                                <option value="ASSENZE">Assenze</option>
                                <option value="SOSTITUZIONI">Sostituzioni</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div id="target_block_mobile" class="mobile-toolbar-block">
                        <div class="mobile-label-row">
                            <label class="mobile-label" for="v_target_mobile">Elemento</label>
                            <button type="button" id="btn_open_target_sheet" class="btn btn-default btn-xs">
                                Cambia
                            </button>
                        </div>

                        <div id="mobile_target_card" class="mobile-target-card" tabindex="0">
                            <button type="button" id="btn_prev_target_mobile" class="btn btn-default btn-sm mobile-target-nav" title="Precedente">
                                <span class="glyphicon glyphicon-chevron-left"></span>
                            </button>

                            <div class="mobile-target-center">
                                <div id="mobile_target_scope_label" class="mobile-target-scope">AULA</div>
                                <div id="mobile_target_label" class="mobile-target-name">Seleziona...</div>
                                <div class="mobile-target-hint">Swipe destra/sinistra per cambiare</div>
                            </div>

                            <button type="button" id="btn_next_target_mobile" class="btn btn-default btn-sm mobile-target-nav" title="Successivo">
                                <span class="glyphicon glyphicon-chevron-right"></span>
                            </button>
                        </div>

                        <select id="v_target_mobile" class="form-control input-sm mobile-picker-select" size="1">
                            <option value="">Seleziona...</option>
                        </select>
                    </div>

                    <div id="mobile_target_modal" class="mobile-modal-backdrop" style="display:none;">
                        <div class="mobile-modal-sheet">
                            <div class="mobile-modal-head">
                                <div class="mobile-modal-title">
                                    Seleziona <span id="mobile_target_modal_scope">elemento</span>
                                </div>
                                <button type="button" id="btn_close_target_modal" class="btn btn-default btn-sm">
                                    <span class="glyphicon glyphicon-remove"></span>
                                </button>
                            </div>

                            <div class="mobile-modal-body">
                                <input type="text" id="mobile_target_search" class="form-control input-sm" placeholder="Cerca...">

                                <div id="mobile_target_list" class="mobile-target-list"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mobile-toolbar-block">
                        <div class="mobile-toolbar-title-row">
                            <label class="mobile-label">Data</label>

                            <button type="button" id="mobile_today_btn" class="btn btn-xs btn-primary">
                                Oggi
                            </button>
                        </div>

                        <div class="mobile-search-row">
                            <input type="text"
                                id="mobile_search_input"
                                class="form-control input-sm mobile-search-wide"
                                placeholder="Cerca..." />
                        </div>

                        <div id="mobile_day_card" class="mobile-day-card" tabindex="0">
                            <button type="button" id="btn_prev_day_mobile" class="btn btn-default btn-sm mobile-day-nav" title="Giorno precedente">
                                <span class="glyphicon glyphicon-chevron-left"></span>
                            </button>

                            <div class="mobile-day-center">
                                <div id="mobile_day_label" class="mobile-day-label">--</div>
                                <div class="mobile-day-hint">Swipe destra/sinistra per cambiare giorno</div>
                            </div>

                            <button type="button" id="btn_next_day_mobile" class="btn btn-default btn-sm mobile-day-nav" title="Giorno successivo">
                                <span class="glyphicon glyphicon-chevron-right"></span>
                            </button>
                        </div>

                    </div>

                </div>

                <div id="orario_title_mobile" class="mobile-title"></div>
                <div id="orario_content_mobile" class="mobile-content"></div>
            </div>
        </div>
    </div>

    <script>
        window.ORARIO_USER_ROLE = <?= json_encode($ruolo, JSON_UNESCAPED_UNICODE) ?>;
        window.ORARIO_IS_DOCENTE = <?= json_encode($ruolo === 'DOCENTE') ?>;
        window.ORARIO_IS_PUBLIC = <?= json_encode($isPublicOrario) ?>;
    </script>

    <script src="js/scriptOrario_mobile.js?t=<?= time() ?>"></script>
</body>

</html>
<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/connectMBApp.php';

ruoloRichiesto('admin', 'personale-ata', 'portineria', 'segreteria-ata', 'segreteria-didattica', 'segreteria-docenti', 'docente', 'dirigente', 'studente', 'genitore');
applicaDocenteDaParametroSeAutorizzato();

$orarioGoogleCalendarDocentiCfg = $__settings->local->googleCalendarDocenti ?? null;
$orarioGoogleCalendarDocentiSelfService = !empty($orarioGoogleCalendarDocentiCfg->enabled)
    && !empty($orarioGoogleCalendarDocentiCfg->teacherSelfServiceEnabled);

$orarioDefaultScope = 'EVENTI';
$orarioDefaultPeriod = 'GIORNO';
$orarioDefaultTarget = '';
if (isset($_GET['docente_id']) && intval($_GET['docente_id']) > 0 && intval($__docente_id ?? 0) > 0) {
    $orarioDefaultScope = 'DOCENTE';
    $orarioDefaultPeriod = 'SETTIMANA';
    $docenteIdOrario = intval($__docente_id);
    $orarioDefaultTarget = (string) dbGetValue("SELECT username FROM docente WHERE id = $docenteIdOrario LIMIT 1");
}

function isMobileOrarioClient()
{
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

    if ($ua === '') return false;

    return (strpos($ua, 'android') !== false)
        || (strpos($ua, 'iphone') !== false)
        || (strpos($ua, 'ipod') !== false)
        || (strpos($ua, 'mobile') !== false)
        || (strpos($ua, 'windows phone') !== false);
}

if (isMobileOrarioClient()) {
    require_once __DIR__ . '/orario_mobile.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Orario (MBApp)</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    require_once '../common/_include_bootstrap-select.php';
    ?>
    <link rel="stylesheet" href="./css/orario.css?t=<?= time() ?>">
</head>

<body>
    <?php
    require_once '../common/checkSession.php';

    global $__utente_ruolo;
    $ruolo = strtoupper(trim((string)$__utente_ruolo));
    if (isset($_GET['docente_id']) && intval($_GET['docente_id']) > 0 && intval($__docente_id ?? 0) > 0) {
        $ruolo = 'DOCENTE';
    }
    $isPublicOrario = in_array($ruolo, ['STUDENTE', 'GENITORE'], true);

    switch ($ruolo) {

        // =========================
        // ADMIN
        // =========================
        case 'ADMIN':
            require_once '../common/header-admin.php';
            break;

        // =========================
        // SEGRETERIE
        // =========================
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

        // =========================
        // DOCENTE
        // =========================
        case 'DOCENTE':
            require_once '../common/header-docente.php';
            break;

        // =========================
        // PERSONALE ATA / CS
        // =========================
        case 'PERSONALE-ATA':
            require_once '../common/header-ata.php';
            break;

        // =========================
        // PORTINERIA
        // =========================
        case 'PORTINERIA':
            require_once '../common/header-ata.php';
            break;

        // =========================
        // STUDENTI
        // =========================
        case 'STUDENTE':
            require_once '../common/header-studente.php';
            break;

        // =========================
        // GENITORI
        // =========================
        case 'GENITORE':
            require_once '../common/header-genitore.php';
            break;

        // =========================
        // DEFAULT (sicurezza)
        // =========================
        default:
            require_once '../common/header-docente.php';
            break;
    }
    ?>

    <div class="container-fluid">
        <div class="panel panel-teal4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-2">
                        <span class="glyphicon glyphicon-calendar"></span>&ensp;Orario (MBApp)
                    </div>
                    <div class="col-md-10">
                        <div class="pull-right orario-toolbar">

                            <!-- select reali (nascosti) per compatibilità col JS -->
                            <select id="v_scope" class="selectpicker sr-only" data-width="150px" data-style="btn-default btn-sm">
                                <option value="AULA">AULA</option>
                                <option value="CLASSE">CLASSE</option>
                                <option value="DOCENTE">DOCENTE</option>
                                <option value="EVENTI">EVENTI</option>
                                <?php if (!$isPublicOrario): ?>
                                <option value="ASSENZE">ASSENZE</option>
                                <option value="SOSTITUZIONI">SOSTITUZIONI</option>
                                <?php endif; ?>
                            </select>

                            <select id="v_period" class="selectpicker sr-only" data-width="150px" data-style="btn-default btn-sm">
                                <option value="GIORNO">GIORNO</option>
                                <option value="SETTIMANA" selected>SETTIMANA</option>
                            </select>

                            <!-- Segmented: SCOPE -->
                            <div class="seg seg-scope" id="seg_scope" role="group" aria-label="Vista">
                                <button type="button" class="seg-btn" data-target="#v_scope" data-value="AULA">Aula</button>
                                <button type="button" class="seg-btn" data-target="#v_scope" data-value="CLASSE">Classe</button>
                                <button type="button" class="seg-btn" data-target="#v_scope" data-value="DOCENTE">Docente</button>
                                <button type="button" class="seg-btn" data-target="#v_scope" data-value="EVENTI">Eventi</button>
                                <?php if (!$isPublicOrario): ?>
                                <button type="button" class="seg-btn" data-target="#v_scope" data-value="ASSENZE">Assenze</button>
                                <button type="button" class="seg-btn" data-target="#v_scope" data-value="SOSTITUZIONI">Sostituzioni</button>
                                <?php endif; ?>
                            </div>

                            <!-- Segmented: PERIOD (lo nasconderai via JS in GIORNO+AULA) -->
                            <div class="seg seg-period" id="seg_period" role="group" aria-label="Periodo">
                                <button type="button" class="seg-btn" data-target="#v_period" data-value="GIORNO">Giorno</button>
                                <button type="button" class="seg-btn" data-target="#v_period" data-value="SETTIMANA">Settimana</button>
                            </div>

                            <!-- =====================================================
                                 NAV SETTIMANA (wrap)
                                 - visibile in modalità SETTIMANA
                                 ===================================================== -->
                            <div id="wrap_week" class="toolbar-item nav-group nav-week">
                                <button class="btn btn-default btn-sm" id="btn_prev_week" title="Settimana precedente">
                                    <span class="glyphicon glyphicon-chevron-left"></span>
                                </button>

                                <select id="v_week" class="selectpicker"
                                    data-width="230px" data-style="btn-default btn-sm"
                                    data-live-search="true" title="Vai alla settimana...">
                                </select>

                                <button class="btn btn-default btn-sm" id="btn_next_week" title="Settimana successiva">
                                    <span class="glyphicon glyphicon-chevron-right"></span>
                                </button>
                            </div>

                            <!-- =====================================================
                                 NAV GIORNO (wrap)
                                 - visibile in modalità GIORNO
                                 - in GIORNO+AULA resta visibile (coppia frecce + data)
                                 ===================================================== -->
                            <div id="wrap_date" class="toolbar-item nav-group nav-date">
                                <button class="btn btn-default btn-sm" id="btn_prev_day" title="Giorno precedente">
                                    <span class="glyphicon glyphicon-chevron-left"></span>
                                </button>

                                <input type="date" id="v_date" class="form-control input-sm" style="width:160px;">

                                <button class="btn btn-default btn-sm" id="btn_next_day" title="Giorno successivo">
                                    <span class="glyphicon glyphicon-chevron-right"></span>
                                </button>
                            </div>

                            <!-- =====================================================
                                 TARGET + NAV AULA (wrap)
                                 - sempre serve il target (aula/classe/docente)
                                 - le frecce aula le mostri SOLO in GIORNO+AULA (via JS)
                                 ===================================================== -->
                            <div id="wrap_target" class="toolbar-item nav-group nav-target">
                                <button class="btn btn-default btn-sm" id="btn_prev_aula" title="Aula precedente">
                                    <span class="glyphicon glyphicon-chevron-left"></span>
                                </button>

                                <select id="v_target" class="selectpicker"
                                    data-width="300px" data-style="btn-default btn-sm"
                                    data-live-search="true" title="Seleziona...">
                                    <option value="">Seleziona...</option>
                                </select>

                                <button class="btn btn-default btn-sm" id="btn_next_aula" title="Aula successiva">
                                    <span class="glyphicon glyphicon-chevron-right"></span>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <?php if ($ruolo === 'DOCENTE' && $orarioGoogleCalendarDocentiSelfService): ?>
                <div id="google_calendar_docenti_box" class="orario-calendar-box">
                    <div class="orario-calendar-status">
                        <strong>Google Calendar</strong>
                        <span id="google_calendar_docenti_status" class="text-muted">Verifica stato...</span>
                    </div>
                    <div class="orario-calendar-actions">
                        <button type="button" class="btn btn-success btn-sm" id="btn_google_calendar_docenti_enable">
                            <span class="glyphicon glyphicon-ok"></span>&ensp;Abilita sync
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" id="btn_google_calendar_docenti_disable">
                            <span class="glyphicon glyphicon-remove"></span>&ensp;Disabilita sync
                        </button>
                        <button type="button" class="btn btn-info btn-sm" id="btn_google_calendar_docenti_force">
                            <span class="glyphicon glyphicon-refresh"></span>&ensp;Sync ultimi 15 giorni
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                <div id="orario_title" style="margin-bottom:10px;font-weight:600; font-size:24px"></div>
                <div id="orario_content"></div>
            </div>
        </div>
    </div>

    <script>
    window.ORARIO_USER_ROLE = <?= json_encode($ruolo, JSON_UNESCAPED_UNICODE) ?>;
    window.ORARIO_IS_DOCENTE = <?= json_encode($ruolo === 'DOCENTE') ?>;
    window.ORARIO_DEFAULT_SCOPE = <?= json_encode($orarioDefaultScope, JSON_UNESCAPED_UNICODE) ?>;
    window.ORARIO_DEFAULT_PERIOD = <?= json_encode($orarioDefaultPeriod, JSON_UNESCAPED_UNICODE) ?>;
    window.ORARIO_DEFAULT_TARGET = <?= json_encode($orarioDefaultTarget, JSON_UNESCAPED_UNICODE) ?>;
    window.ORARIO_GOOGLE_CALENDAR_DOCENTI_SELF_SERVICE = <?= json_encode($orarioGoogleCalendarDocentiSelfService && $ruolo === 'DOCENTE') ?>;
    </script>

    <script src="js/scriptAssenze.js?t=<?= time() ?>"></script>
    <script src="js/scriptSostituzioni.js?t=<?= time() ?>"></script>
    <?php if ($orarioGoogleCalendarDocentiSelfService && $ruolo === 'DOCENTE'): ?>
    <script src="js/googleCalendarDocentiSelfService.js?t=<?= time() ?>"></script>
    <?php endif; ?>
    <script src="js/scriptOrario.js?t=<?= time() ?>"></script></body>

</html>

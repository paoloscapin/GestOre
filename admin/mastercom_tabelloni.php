<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/tabelloni_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mct_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mct_audit_type_label(string $type): string
{
    $labels = [
        'MANCA_IMPORT_CARENZE' => 'Manca in import carenze',
        'CARENZA_IMPORTATA_NON_NEL_TABELLONE' => 'Carenza non confermata dal tabellone',
        'MATERIA_NON_ABBINATA' => 'Materia non abbinata',
        'STUDENTE_NON_ABBINATO' => 'Studente non abbinato',
    ];
    return $labels[$type] ?? $type;
}

function mct_class_year_label(int $year): string
{
    $labels = [
        1 => 'Classi prime',
        2 => 'Classi seconde',
        3 => 'Classi terze',
        4 => 'Classi quarte',
        5 => 'Classi quinte',
    ];
    return $labels[$year] ?? ('Classi anno ' . $year);
}

function mct_datetime_it($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTime($value))->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $value;
    }
}

function mct_avg_value($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float)$value, 2, ',', '');
}

mastercomTabelloniEnsureTables();

$message = '';
$error = '';
$debugInfo = null;
$selectedClassId = intval($_REQUEST['class_id'] ?? 0);
$auditYearId = intval($_REQUEST['audit_school_year_id'] ?? 0);
$auditClassId = intval($_REQUEST['audit_class_id'] ?? 0);
$selectedPeriod = trim((string)($_REQUEST['period'] ?? '9'));
if ($selectedPeriod === '') {
    $selectedPeriod = '9';
}
$periodLabels = mastercomTabelloniPeriodLabels();
$classRows = mastercomTabelloniImportClassRows('mastercom_id_classe, nome');
$importClassMap = mastercomTabelloniImportClassMap();
$schoolYears = mastercomTabelloniSchoolYears();
if ($auditYearId <= 0) {
    global $__anno_scolastico_corrente_id;
    $auditYearId = intval($__anno_scolastico_corrente_id ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && trim((string)($_GET['ajax'] ?? '')) === 'tabellone_detail') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(mastercomTabelloniDetail(intval($_GET['id'] ?? 0)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $params = [
        'param_tabellone_periodo' => $selectedPeriod,
        'param_tabellone_esposizione_archivio' => 'archivio',
    ];
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        header('Content-Type: application/json; charset=UTF-8');
        if ($action !== 'fetch_mastercom') {
            echo json_encode(['ok' => false, 'message' => 'Azione AJAX non valida.']);
            exit;
        }
        if ($selectedClassId <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Classe MasterCom non valida.']);
            exit;
        }
        if (!isset($importClassMap[$selectedClassId])) {
            echo json_encode(['ok' => false, 'message' => 'Classe esclusa dall import tabelloni.']);
            exit;
        }

        $result = mastercomTabelloniFetchAndStoreClass($selectedClassId, $params);
        echo json_encode([
            'ok' => !empty($result['ok']),
            'message' => $result['message'] ?? (!empty($result['ok']) ? 'Import completato.' : 'Import non riuscito.'),
            'stats' => $result['stats'] ?? [],
            'class_id' => $selectedClassId,
            'class_name' => $importClassMap[$selectedClassId]['nome'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'fetch_mastercom') {
        if ($selectedClassId <= 0) {
            $error = 'Seleziona una classe MasterCom da importare.';
        } elseif (!isset($importClassMap[$selectedClassId])) {
            $error = 'La classe selezionata non e importabile come tabellone: e una sottoclasse articolata oppure una classe serale.';
        } else {
            $result = mastercomTabelloniFetchAndStoreClass($selectedClassId, $params);
            $debugInfo = $result['debug'] ?? null;
            if (!empty($result['ok'])) {
                $stats = $result['stats'] ?? [];
                $message = ($result['message'] ?? 'Import completato')
                    . ' Celle salvate: ' . intval($stats['votes'] ?? 0)
                    . '. Studenti non abbinati: ' . intval($stats['without_student'] ?? 0) . '.';
                $missingStudentNames = array_filter(array_map('trim', (array)($stats['without_student_names'] ?? [])));
                if (!empty($missingStudentNames)) {
                    $message .= ' Non abbinati: ' . implode(', ', array_slice($missingStudentNames, 0, 8)) . '.';
                    if (count($missingStudentNames) > 8) {
                        $message .= ' ...';
                    }
                }
            } else {
                $error = $result['message'] ?? 'Import tabellone non riuscito.';
            }
        }
    } elseif ($action === 'fetch_mastercom_all') {
        $result = mastercomTabelloniFetchAndStoreAllClasses($params);
        $stats = $result['stats'] ?? [];
        if (!empty($result['ok'])) {
            $message = $result['message'] ?? 'Import globale completato.';
            $missingStudentNames = array_filter(array_map('trim', (array)($stats['without_student_names'] ?? [])));
            if (!empty($missingStudentNames)) {
                $message .= ' Non abbinati: ' . implode(' | ', array_slice($missingStudentNames, 0, 12));
                if (count($missingStudentNames) > 12) {
                    $message .= ' ...';
                }
            }
            if (!empty($result['errors'])) {
                $message .= ' Classi con errore: ' . implode(' | ', array_slice((array)$result['errors'], 0, 8));
                if (count((array)$result['errors']) > 8) {
                    $message .= ' ...';
                }
            }
        } else {
            $error = ($result['message'] ?? 'Import globale non riuscito.')
                . ' Classi lette: ' . intval($stats['classes'] ?? 0)
                . ', errori: ' . intval($stats['errors'] ?? 0) . '.';
            if (!empty($result['errors'])) {
                $error .= ' ' . implode(' | ', array_slice((array)$result['errors'], 0, 8));
            }
        }
    }
}

$recentRows = mastercomTabelloniRecentRows(100);
mastercomTabelloniRefreshDerivedFields();
$outcomeSummary = mastercomTabelloniOutcomeSummary($auditYearId, $selectedPeriod);
$averagesSummary = mastercomTabelloniAveragesSummary($auditYearId, $selectedPeriod);
$auditRows = mastercomTabelloniAuditRows($auditYearId, $auditClassId, 300);
$auditStats = mastercomTabelloniAuditStats($auditYearId, $auditClassId);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tabelloni MasterCom</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .mct-toolbar {
            background: linear-gradient(#f8fbff, #d9edf7);
            border: 1px solid #8ec5df;
            border-radius: 4px;
            padding: 14px;
            margin-bottom: 16px;
        }
        .mct-toolbar .row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .mct-filter-class {
            flex: 1 1 360px;
        }
        .mct-filter-period {
            flex: 0 0 300px;
        }
        .mct-actions {
            flex: 1 1 420px;
            white-space: nowrap;
        }
        .mct-actions .btn {
            margin-bottom: 3px;
        }
        .mct-table td, .mct-table th {
            vertical-align: middle !important;
        }
        .mct-audit-stats {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 10px 0 14px;
        }
        .mct-audit-stat {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 10px;
            background: #fafafa;
        }
        .mct-audit-stat strong {
            display: block;
            font-size: 18px;
        }
        .mct-summary-table tfoot tr {
            background: #f5f5f5;
            font-weight: 700;
        }
        .mct-summary-section {
            margin-bottom: 18px;
        }
        .mct-summary-year {
            margin-top: 14px;
            padding: 6px 8px;
            background: #eef7fb;
            border-left: 4px solid #337ab7;
            font-weight: 700;
        }
        .mct-row-danger {
            background: #ffe5e5;
        }
        .mct-row-warning {
            background: #fff3cd;
        }
        .mct-muted {
            color: #777;
            font-size: 12px;
        }
        .mct-debug pre {
            max-height: 360px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 12px;
        }
        .mct-wait-overlay {
            position: fixed;
            z-index: 9999;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(17, 24, 39, 0.42);
        }
        .mct-wait-card {
            width: min(520px, calc(100vw - 32px));
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
            padding: 24px;
            text-align: center;
        }
        .mct-spinner {
            width: 42px;
            height: 42px;
            margin: 0 auto 14px;
            border: 4px solid #d9edf7;
            border-top-color: #337ab7;
            border-radius: 50%;
            animation: mct-spin 0.8s linear infinite;
        }
        .mct-progress-wrap {
            display: none;
            height: 14px;
            margin-top: 16px;
            overflow: hidden;
            background: #edf2f7;
            border-radius: 999px;
        }
        .mct-progress-bar {
            width: 0;
            height: 100%;
            background: #337ab7;
            transition: width 0.2s ease;
        }
        .mct-wait-detail {
            margin-top: 10px;
            color: #555;
            font-size: 13px;
        }
        .mct-tabellone-link {
            border: 0;
            background: transparent;
            color: #337ab7;
            padding: 0;
            font-weight: 700;
            text-align: left;
        }
        .mct-tabellone-link:hover {
            color: #23527c;
            text-decoration: underline;
        }
        .mct-tabellone-modal .modal-dialog {
            width: 96vw;
            max-width: 1500px;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .mct-tabellone-modal .modal-body {
            padding: 10px;
        }
        .mct-tabellone-modal .modal-header {
            padding: 10px 15px;
        }
        .mct-tabellone-scroll {
            max-height: calc(100vh - 135px);
            overflow: auto;
            border: 1px solid #ddd;
        }
        .mct-tabellone-grid {
            margin-bottom: 0;
            white-space: nowrap;
        }
        .mct-tabellone-grid th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #eef7fb;
        }
        .mct-tabellone-grid th:first-child,
        .mct-tabellone-grid td:first-child {
            position: sticky;
            left: 0;
            z-index: 3;
            background: #fff;
        }
        .mct-tabellone-grid th:first-child {
            background: #d9edf7;
            z-index: 4;
        }
        .mct-tabellone-grid .mct-voto-insufficiente {
            background: #ffd6d6;
            color: #9f1239;
            font-weight: 700;
        }
        .mct-tabellone-grid .mct-esito-ammesso,
        .mct-tabellone-grid .mct-esito-anno_estero {
            background: #d9f0d3;
            font-weight: 700;
        }
        .mct-tabellone-grid .mct-esito-non_ammesso,
        .mct-tabellone-grid .mct-esito-in_corso {
            background: #d9534f;
            color: #ffffff;
            font-weight: 700;
        }
        @keyframes mct-spin {
            to { transform: rotate(360deg); }
        }
        @media (max-width: 900px) {
            .mct-actions {
                white-space: normal;
            }
            .mct-actions .btn {
                width: 100%;
                margin-bottom: 6px;
            }
        }
    </style>
</head>
<body>
<?php require_once headerAdminDidatticaPath('../common'); ?>
<div class="mct-wait-overlay" id="mctWaitOverlay" aria-live="polite" aria-busy="true">
    <div class="mct-wait-card">
        <div class="mct-spinner"></div>
        <h4 id="mctWaitTitle">Import tabellone in corso...</h4>
        <div id="mctWaitText">Attendere il completamento dell'operazione.</div>
        <div class="mct-progress-wrap" id="mctProgressWrap">
            <div class="mct-progress-bar" id="mctProgressBar"></div>
        </div>
        <div class="mct-wait-detail" id="mctWaitDetail"></div>
    </div>
</div>
<div class="container-fluid">
    <div class="panel panel-info">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-list-alt"></span>&emsp;Tabelloni scrutini MasterCom
        </div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo mct_h($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo mct_h($error); ?></div>
            <?php endif; ?>
            <?php if (is_array($debugInfo)): ?>
                <div class="panel panel-default mct-debug">
                    <div class="panel-heading">
                        <span class="glyphicon glyphicon-search"></span>&emsp;Debug ultima importazione
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Parametri inviati a MasterCom</h5>
                                <pre><?php echo mct_h(json_encode($debugInfo['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                            <div class="col-md-6">
                                <h5>Risposta e parsing</h5>
                                <pre><?php echo mct_h(json_encode([
                                    'http_code' => $debugInfo['http_code'] ?? null,
                                    'content_type' => $debugInfo['content_type'] ?? null,
                                    'response_length' => $debugInfo['response_length'] ?? null,
                                    'generated_path' => $debugInfo['generated_path'] ?? null,
                                    'download_url' => $debugInfo['download_url'] ?? null,
                                    'download_http_code' => $debugInfo['download_http_code'] ?? null,
                                    'download_content_type' => $debugInfo['download_content_type'] ?? null,
                                    'download_response_length' => $debugInfo['download_response_length'] ?? null,
                                    'download_attempts' => $debugInfo['download_attempts'] ?? null,
                                    'parse' => $debugInfo['parse'] ?? null,
                                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                        </div>
                        <h5>Prime righe ricevute</h5>
                        <pre><?php echo mct_h($debugInfo['response_preview'] ?? ''); ?></pre>
                        <?php if (!empty($debugInfo['download_response_preview'])): ?>
                            <h5>Prime righe file XLS scaricato</h5>
                            <pre><?php echo mct_h($debugInfo['download_response_preview'] ?? ''); ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" class="mct-toolbar" id="mctImportForm">
                <div class="row">
                    <div class="form-group mct-filter-class">
                        <label for="class_id">Classe MasterCom</label>
                        <select name="class_id" id="class_id" class="form-control">
                            <option value="0">- seleziona classe -</option>
                            <?php foreach ($classRows as $classRow): ?>
                                <?php $classId = intval($classRow['mastercom_id_classe'] ?? 0); ?>
                                <option value="<?php echo $classId; ?>" <?php echo $classId === $selectedClassId ? 'selected' : ''; ?>>
                                    <?php echo mct_h(($classRow['nome'] ?? '') . ' [' . $classId . ']'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mct-filter-period">
                        <label for="period">Periodo</label>
                        <select name="period" id="period" class="form-control">
                            <?php foreach ($periodLabels as $periodValue => $periodLabel): ?>
                                <option value="<?php echo mct_h($periodValue); ?>" <?php echo $selectedPeriod === (string)$periodValue ? 'selected' : ''; ?>>
                                    <?php echo mct_h($periodValue . ' - ' . $periodLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mct-actions">
                        <button type="submit" name="action" value="fetch_mastercom" class="btn btn-primary" id="mctSingleButton"
                                onclick="return confirm('Importare il tabellone della classe selezionata da MasterCom?');">
                            <span class="glyphicon glyphicon-download-alt"></span> Importa classe
                        </button>
                        <button type="submit" name="action" value="fetch_mastercom_all" class="btn btn-warning" id="mctGlobalButton"
                                onclick="return confirm('Importare i tabelloni di tutte le classi operative da MasterCom? L operazione puo richiedere tempo.');">
                            <span class="glyphicon glyphicon-cloud-download"></span> Importa tutte
                        </button>
                    </div>
                </div>
                <div class="mct-muted">
                    L'import usa sempre la versione ARCHIVIO del tabellone e sostituisce il dettaglio gia salvato per la stessa classe, anno scolastico e periodo.
                    Il file MasterCom viene conservato anche in forma grezza nella tabella principale.
                </div>
            </form>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <span class="glyphicon glyphicon-stats"></span>&emsp;Riepilogo esiti tabelloni
                </div>
                <div class="panel-body">
                    <div class="text-right" style="margin-bottom: 10px;">
                        <?php
                        $summaryExportParams = [
                            'school_year_id' => $auditYearId,
                            'period' => $selectedPeriod,
                        ];
                        ?>
                        <a class="btn btn-sm btn-success" href="mastercom_tabelloni_export.php?<?php echo http_build_query($summaryExportParams + ['format' => 'xlsx']); ?>">
                            <span class="glyphicon glyphicon-list-alt"></span> Esporta Excel
                        </a>
                        <a class="btn btn-sm btn-danger" href="mastercom_tabelloni_export.php?<?php echo http_build_query($summaryExportParams + ['format' => 'pdf']); ?>">
                            <span class="glyphicon glyphicon-file"></span> Esporta PDF
                        </a>
                    </div>
                    <div class="mct-muted" style="margin-bottom: 10px;">
                        Promossi = ammessi + anno estero. Bocciati = non ammessi + in corso. Promossi con carenze = promossi con almeno un voto 4 o 5 su materia non informativa.
                    </div>

                    <div class="mct-summary-section">
                        <h4>Dettaglio classi</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-condensed mct-table mct-summary-table">
                                <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th class="text-center">Studenti</th>
                                    <th class="text-center">Promossi</th>
                                    <th class="text-center">Bocciati</th>
                                    <th class="text-center">Promossi con carenze</th>
                                    <th class="text-center">1 carenza</th>
                                    <th class="text-center">2 o pi&ugrave; carenze</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($outcomeSummary['classes'])): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Nessun tabellone disponibile per anno scolastico e periodo selezionati.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach (($outcomeSummary['classes'] ?? []) as $summaryRow): ?>
                                    <tr>
                                        <td><?php echo mct_h($summaryRow['label'] ?? ''); ?></td>
                                        <td class="text-center"><?php echo intval($summaryRow['students'] ?? 0); ?></td>
                                        <td class="text-center success"><?php echo intval($summaryRow['promossi'] ?? 0); ?></td>
                                        <td class="text-center danger"><?php echo intval($summaryRow['bocciati'] ?? 0); ?></td>
                                        <td class="text-center warning"><?php echo intval($summaryRow['promossi_con_carenze'] ?? 0); ?></td>
                                        <td class="text-center warning"><?php echo intval($summaryRow['promossi_una_carenza'] ?? 0); ?></td>
                                        <td class="text-center warning"><?php echo intval($summaryRow['promossi_due_o_piu_carenze'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mct-summary-section">
                        <h4>Totali</h4>
                        <?php foreach (($outcomeSummary['totals'] ?? []) as $year => $yearRows): ?>
                            <?php if (empty($yearRows)): ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <div class="mct-summary-year">
                                <?php echo mct_h(mct_class_year_label(intval($year))); ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed mct-table mct-summary-table">
                                    <thead>
                                    <tr>
                                        <th><?php echo intval($year) <= 2 ? 'Totale' : 'Indirizzo'; ?></th>
                                        <th class="text-center">Classi</th>
                                        <th class="text-center">Studenti</th>
                                        <th class="text-center">Promossi</th>
                                        <th class="text-center">Bocciati</th>
                                        <th class="text-center">Promossi con carenze</th>
                                        <th class="text-center">1 carenza</th>
                                        <th class="text-center">2 o pi&ugrave; carenze</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($yearRows as $summaryRow): ?>
                                        <tr>
                                            <td><?php echo mct_h($summaryRow['label'] ?? ''); ?></td>
                                            <td class="text-center"><?php echo intval($summaryRow['classes'] ?? 0); ?></td>
                                            <td class="text-center"><?php echo intval($summaryRow['students'] ?? 0); ?></td>
                                            <td class="text-center success"><?php echo intval($summaryRow['promossi'] ?? 0); ?></td>
                                            <td class="text-center danger"><?php echo intval($summaryRow['bocciati'] ?? 0); ?></td>
                                            <td class="text-center warning"><?php echo intval($summaryRow['promossi_con_carenze'] ?? 0); ?></td>
                                            <td class="text-center warning"><?php echo intval($summaryRow['promossi_una_carenza'] ?? 0); ?></td>
                                            <td class="text-center warning"><?php echo intval($summaryRow['promossi_due_o_piu_carenze'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mct-summary-section">
                        <h4>Medie voti</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-condensed mct-table mct-summary-table">
                                <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th class="text-center">Studenti</th>
                                    <?php foreach (($averagesSummary['subjects'] ?? []) as $subjectConfig): ?>
                                        <th class="text-center"><?php echo mct_h($subjectConfig['label'] ?? ''); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($averagesSummary['classes'])): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Nessun voto disponibile per anno scolastico e periodo selezionati.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach (($averagesSummary['classes'] ?? []) as $summaryRow): ?>
                                    <tr>
                                        <td><?php echo mct_h($summaryRow['label'] ?? ''); ?></td>
                                        <td class="text-center"><?php echo intval($summaryRow['students'] ?? 0); ?></td>
                                        <?php foreach (array_keys((array)($averagesSummary['subjects'] ?? [])) as $subjectKey): ?>
                                            <td class="text-center"><?php echo mct_avg_value($summaryRow[$subjectKey . '_avg'] ?? null); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <h4>Medie gruppi</h4>
                        <?php foreach (($averagesSummary['totals'] ?? []) as $year => $yearRows): ?>
                            <?php if (empty($yearRows)): ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <div class="mct-summary-year">
                                <?php echo mct_h(mct_class_year_label(intval($year))); ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed mct-table mct-summary-table">
                                    <thead>
                                    <tr>
                                        <th><?php echo intval($year) <= 2 ? 'Totale' : 'Indirizzo'; ?></th>
                                        <th class="text-center">Classi</th>
                                        <th class="text-center">Studenti</th>
                                        <?php foreach (($averagesSummary['subjects'] ?? []) as $subjectConfig): ?>
                                            <th class="text-center"><?php echo mct_h($subjectConfig['label'] ?? ''); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($yearRows as $summaryRow): ?>
                                        <tr>
                                            <td><?php echo mct_h($summaryRow['label'] ?? ''); ?></td>
                                            <td class="text-center"><?php echo intval($summaryRow['classes'] ?? 0); ?></td>
                                            <td class="text-center"><?php echo intval($summaryRow['students'] ?? 0); ?></td>
                                            <?php foreach (array_keys((array)($averagesSummary['subjects'] ?? [])) as $subjectKey): ?>
                                                <td class="text-center"><?php echo mct_avg_value($summaryRow[$subjectKey . '_avg'] ?? null); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <span class="glyphicon glyphicon-check"></span>&emsp;Controllo carenze da tabelloni
                </div>
                <div class="panel-body">
                    <form method="get" class="form-inline" style="margin-bottom: 12px;">
                        <input type="hidden" name="period" value="<?php echo mct_h($selectedPeriod); ?>">
                        <div class="form-group">
                            <label for="audit_school_year_id">Anno scolastico</label>
                            <select name="audit_school_year_id" id="audit_school_year_id" class="form-control">
                                <option value="0">Tutti</option>
                                <?php foreach ($schoolYears as $yearRow): ?>
                                    <?php $yearId = intval($yearRow['id'] ?? 0); ?>
                                    <option value="<?php echo $yearId; ?>" <?php echo $auditYearId === $yearId ? 'selected' : ''; ?>>
                                        <?php echo mct_h($yearRow['anno'] ?? $yearId); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-left: 8px;">
                            <label for="audit_class_id">Classe</label>
                            <select name="audit_class_id" id="audit_class_id" class="form-control">
                                <option value="0">Tutte</option>
                                <?php foreach ($classRows as $classRow): ?>
                                    <?php $classId = intval($classRow['mastercom_id_classe'] ?? 0); ?>
                                    <option value="<?php echo $classId; ?>" <?php echo $auditClassId === $classId ? 'selected' : ''; ?>>
                                        <?php echo mct_h(($classRow['nome'] ?? '') . ' [' . $classId . ']'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-default" style="margin-left: 8px;">
                            <span class="glyphicon glyphicon-refresh"></span> Aggiorna controllo
                        </button>
                    </form>

                    <div class="mct-audit-stats">
                        <div class="mct-audit-stat"><strong><?php echo intval($auditStats['totale']); ?></strong> problemi</div>
                        <div class="mct-audit-stat"><strong><?php echo intval($auditStats['manca_import_carenze']); ?></strong> mancano nell'import carenze</div>
                        <div class="mct-audit-stat"><strong><?php echo intval($auditStats['carenza_importata_non_nel_tabellone']); ?></strong> non confermate dal tabellone</div>
                        <div class="mct-audit-stat"><strong><?php echo intval($auditStats['materia_non_abbinata']); ?></strong> materie non abbinate</div>
                        <div class="mct-audit-stat"><strong><?php echo intval($auditStats['studente_non_abbinato']); ?></strong> studenti non abbinati</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed mct-table">
                            <thead>
                            <tr>
                                <th>Problema</th>
                                <th>Classe</th>
                                <th>Studente</th>
                                <th>Materia</th>
                                <th class="text-center">Voto</th>
                                <th>Esito</th>
                                <th>Dettaglio</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($auditRows)): ?>
                                <tr>
                                    <td colspan="7" class="text-center success">Nessuna differenza rilevata per i filtri selezionati.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($auditRows as $row): ?>
                                <?php
                                $type = (string)($row['tipo'] ?? '');
                                $rowClass = in_array($type, ['MANCA_IMPORT_CARENZE', 'CARENZA_IMPORTATA_NON_NEL_TABELLONE'], true) ? 'mct-row-danger' : 'mct-row-warning';
                                $subjectLabel = trim((string)(($row['materia_gestore'] ?? '') ?: ($row['materia_codice'] ?? '')));
                                if (!empty($row['id_materia_gestore'])) {
                                    $subjectLabel .= ' [' . intval($row['id_materia_gestore']) . ']';
                                } elseif (!empty($row['materia_codice'])) {
                                    $subjectLabel .= ' [' . $row['materia_codice'] . ']';
                                }
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td><?php echo mct_h(mct_audit_type_label($type)); ?></td>
                                    <td><?php echo mct_h($row['classe'] ?? ''); ?></td>
                                    <td><?php echo mct_h($row['studente_nome'] ?? ''); ?></td>
                                    <td><?php echo mct_h($subjectLabel); ?></td>
                                    <td class="text-center"><?php echo mct_h($row['valore'] ?? ''); ?></td>
                                    <td><?php echo mct_h($row['esito_key'] ?? ''); ?></td>
                                    <td><?php echo mct_h($row['messaggio'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($auditRows) >= 300): ?>
                        <p class="text-muted">Mostrate le prime 300 differenze. Raffina i filtri per vedere il dettaglio completo.</p>
                    <?php endif; ?>
                </div>
            </div>

            <h4>Tabelloni importati</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-condensed mct-table">
                    <thead>
                    <tr>
                        <th>Classe</th>
                        <th>Classe tabellone</th>
                        <th>A.S.</th>
                        <th>Periodo</th>
                        <th class="text-center">Studenti</th>
                        <th class="text-center">Celle</th>
                        <th class="text-center">Importato</th>
                        <th>Hash</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recentRows)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Nessun tabellone importato.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($recentRows as $row): ?>
                        <tr>
                            <td>
                                <button type="button" class="mct-tabellone-link" data-tabellone-id="<?php echo intval($row['id'] ?? 0); ?>">
                                    <?php echo mct_h($row['classe'] ?? ''); ?>
                                </button>
                            </td>
                            <td><?php echo mct_h($row['classe_tabellone'] ?? ''); ?></td>
                            <td class="text-center"><?php echo mct_h($row['anno_label'] ?? ''); ?></td>
                            <td><?php echo mct_h($row['periodo_label'] ?: $row['periodo']); ?></td>
                            <td class="text-center"><?php echo intval($row['studenti_count'] ?? 0); ?></td>
                            <td class="text-center"><?php echo intval($row['celle_count'] ?? 0); ?></td>
                            <td class="text-center"><?php echo mct_h(mct_datetime_it($row['imported_at'] ?? '')); ?></td>
                            <td><code><?php echo mct_h(substr((string)($row['source_hash'] ?? ''), 0, 10)); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade mct-tabellone-modal" id="mctTabelloneModal" tabindex="-1" role="dialog" aria-labelledby="mctTabelloneTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="mctTabelloneTitle">Tabellone</h4>
                <div class="mct-muted" id="mctTabelloneMeta"></div>
                <div class="text-right" style="margin-top: 8px;">
                    <a class="btn btn-xs btn-success disabled" id="mctTabelloneExportXlsx" href="#" target="_blank">
                        <span class="glyphicon glyphicon-list-alt"></span> Excel
                    </a>
                    <a class="btn btn-xs btn-danger disabled" id="mctTabelloneExportPdf" href="#" target="_blank">
                        <span class="glyphicon glyphicon-file"></span> PDF
                    </a>
                </div>
            </div>
            <div class="modal-body">
                <div id="mctTabelloneStatus" class="alert alert-info">Caricamento tabellone...</div>
                <div class="mct-tabellone-scroll" id="mctTabelloneScroll" style="display:none;">
                    <table class="table table-bordered table-condensed mct-tabellone-grid" id="mctTabelloneGrid"></table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('mctImportForm');
    var overlay = document.getElementById('mctWaitOverlay');
    var title = document.getElementById('mctWaitTitle');
    var text = document.getElementById('mctWaitText');
    var detail = document.getElementById('mctWaitDetail');
    var progressWrap = document.getElementById('mctProgressWrap');
    var progressBar = document.getElementById('mctProgressBar');
    var globalButton = document.getElementById('mctGlobalButton');
    var tabelloneModal = $('#mctTabelloneModal');
    var tabelloneTitle = document.getElementById('mctTabelloneTitle');
    var tabelloneMeta = document.getElementById('mctTabelloneMeta');
    var tabelloneStatus = document.getElementById('mctTabelloneStatus');
    var tabelloneScroll = document.getElementById('mctTabelloneScroll');
    var tabelloneGrid = document.getElementById('mctTabelloneGrid');
    var tabelloneExportXlsx = document.getElementById('mctTabelloneExportXlsx');
    var tabelloneExportPdf = document.getElementById('mctTabelloneExportPdf');
    var importClasses = <?php echo json_encode(array_map(function ($row) {
        return [
            'id' => intval($row['mastercom_id_classe'] ?? 0),
            'name' => (string)($row['nome'] ?? ''),
        ];
    }, $classRows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function showWait(nextTitle, nextText, withProgress) {
        title.textContent = nextTitle;
        text.textContent = nextText;
        detail.textContent = '';
        progressBar.style.width = '0%';
        progressWrap.style.display = withProgress ? 'block' : 'none';
        overlay.style.display = 'flex';
    }

    function setProgress(done, total, className) {
        var percent = total > 0 ? Math.round((done / total) * 100) : 0;
        progressBar.style.width = percent + '%';
        text.textContent = percent + '% completato';
        detail.textContent = className ? ('Classe ' + className + ' (' + done + ' di ' + total + ')') : (done + ' di ' + total);
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (event.submitter && event.submitter.id === 'mctGlobalButton') {
                event.preventDefault();
                runGlobalImport();
                return;
            }
            showWait('Import tabellone in corso...', 'Sto leggendo e salvando il tabellone selezionato.', false);
        });
    }

    function postClass(row) {
        var data = new FormData();
        data.append('ajax', '1');
        data.append('action', 'fetch_mastercom');
        data.append('class_id', String(row.id));
        data.append('period', document.getElementById('period').value);
        return fetch(window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
        }).then(function (response) {
            return response.json();
        });
    }

    async function runGlobalImport() {
        if (!importClasses.length) {
            alert('Nessuna classe importabile.');
            return;
        }
        showWait('Import globale tabelloni...', '0% completato', true);
        if (globalButton) {
            globalButton.disabled = true;
        }

        var ok = 0;
        var errors = [];
        for (var i = 0; i < importClasses.length; i++) {
            var row = importClasses[i];
            setProgress(i, importClasses.length, row.name);
            try {
                var result = await postClass(row);
                if (result && result.ok) {
                    ok++;
                } else {
                    errors.push(row.name + ': ' + ((result && result.message) || 'errore import'));
                }
            } catch (err) {
                errors.push(row.name + ': ' + err.message);
            }
            setProgress(i + 1, importClasses.length, row.name);
        }

        title.textContent = 'Import globale completato';
        text.textContent = 'Classi importate: ' + ok + ' su ' + importClasses.length + '. Errori: ' + errors.length + '.';
        detail.textContent = errors.length ? errors.slice(0, 5).join(' | ') : 'Aggiorno la pagina...';
        setTimeout(function () {
            window.location.href = window.location.pathname + '?period=' + encodeURIComponent(document.getElementById('period').value);
        }, errors.length ? 3500 : 900);
    }

    function htmlEscape(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function tabelloneClassYear(label) {
        var match = String(label || '').match(/^\s*([1-5])/);
        return match ? parseInt(match[1], 10) : 0;
    }

    function cellClass(value, column, student) {
        var classes = ['text-center'];
        if (value && value.insufficiente) {
            classes.push('mct-voto-insufficiente');
        }
        if (column && column.tipo === 'risultato') {
            classes.push('mct-esito-' + String((student && student.esito_key) || ''));
        }
        return classes.join(' ');
    }

    function renderTabellone(payload) {
        var tabellone = payload.tabellone || {};
        var columns = payload.columns || [];
        var students = payload.students || [];
        var titleLabel = tabellone.classe_tabellone || tabellone.classe || 'Tabellone';
        var classYear = tabelloneClassYear(titleLabel);
        var visibleColumns = columns.filter(function (column) {
            if (classYear <= 2 && (column.tipo === 'credito' || column.tipo === 'credito_totale')) {
                return false;
            }
            return true;
        });
        tabelloneTitle.textContent = titleLabel;
        tabelloneMeta.textContent = 'A.S. ' + (tabellone.anno_label || '')
            + ' - ' + (tabellone.periodo_label || tabellone.periodo || '')
            + ' - importato ' + (tabellone.imported_at || '');

        var html = '<thead><tr>';
        html += '<th>Studente</th>';
        visibleColumns.forEach(function (column) {
            var label = column.codice || column.descrizione || '';
            label = htmlEscape(label);
            if (column.sub_header && column.sub_header !== label) {
                label += '<br><small>' + htmlEscape(column.sub_header) + '</small>';
            }
            html += '<th class="text-center" title="' + htmlEscape(column.tipo || '') + '">' + label + '</th>';
        });
        html += '</tr></thead><tbody>';

        if (!students.length) {
            html += '<tr><td colspan="' + (visibleColumns.length + 1) + '" class="text-center">Nessuno studente nel tabellone.</td></tr>';
        }

        students.forEach(function (student) {
            html += '<tr>';
            html += '<td><strong>' + htmlEscape(student.numero || '') + '</strong> ' + htmlEscape(student.nome || '') + '</td>';
            visibleColumns.forEach(function (column) {
                var values = student.values || {};
                var value = values[String(column.col_index)] || values[column.col_index] || null;
                html += '<td class="' + cellClass(value, column, student) + '">' + htmlEscape(value ? value.value : '') + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody>';

        tabelloneGrid.innerHTML = html;
        tabelloneStatus.style.display = 'none';
        tabelloneScroll.style.display = 'block';
    }

    function loadTabellone(tabelloneId) {
        tabelloneTitle.textContent = 'Tabellone';
        tabelloneMeta.textContent = '';
        tabelloneGrid.innerHTML = '';
        if (tabelloneExportXlsx && tabelloneExportPdf) {
            tabelloneExportXlsx.classList.add('disabled');
            tabelloneExportPdf.classList.add('disabled');
            tabelloneExportXlsx.setAttribute('href', '#');
            tabelloneExportPdf.setAttribute('href', '#');
        }
        tabelloneScroll.style.display = 'none';
        tabelloneStatus.className = 'alert alert-info';
        tabelloneStatus.textContent = 'Caricamento tabellone...';
        tabelloneStatus.style.display = 'block';
        tabelloneModal.modal('show');

        fetch(window.location.pathname + '?ajax=tabellone_detail&id=' + encodeURIComponent(tabelloneId), {
            method: 'GET',
            credentials: 'same-origin'
        }).then(function (response) {
            return response.text();
        }).then(function (text) {
            try {
                return JSON.parse(text);
            } catch (err) {
                throw new Error(text ? text.substring(0, 300) : 'Risposta vuota dal server.');
            }
        }).then(function (payload) {
            if (!payload || !payload.ok) {
                throw new Error((payload && payload.message) || 'Tabellone non disponibile.');
            }
            if (tabelloneExportXlsx && tabelloneExportPdf) {
                var exportBase = 'mastercom_tabelloni_export.php?mode=tabellone&id=' + encodeURIComponent(tabelloneId);
                tabelloneExportXlsx.setAttribute('href', exportBase + '&format=xlsx');
                tabelloneExportPdf.setAttribute('href', exportBase + '&format=pdf');
                tabelloneExportXlsx.classList.remove('disabled');
                tabelloneExportPdf.classList.remove('disabled');
            }
            renderTabellone(payload);
        }).catch(function (err) {
            tabelloneStatus.className = 'alert alert-danger';
            tabelloneStatus.textContent = err.message || 'Errore caricamento tabellone.';
        });
    }

    document.querySelectorAll('.mct-tabellone-link').forEach(function (button) {
        button.addEventListener('click', function () {
            loadTabellone(button.getAttribute('data-tabellone-id'));
        });
    });
})();
</script>
</body>
</html>

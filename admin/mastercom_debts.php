<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/debts_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mcd_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mcd_state_label($value): string
{
    return intval($value) === 1 ? 'Recuperato' : 'Non recuperato';
}

function mcd_report_table_html(array $rows, bool $forExport = false): string
{
    $html = '<table class="table table-bordered table-condensed mcd-table">';
    $html .= '<thead><tr>';
    foreach (['Classe', 'Studente', 'Materia', 'A.S.', 'Tipo debito', 'MasterCom', 'GestOre', 'Corso recupero', 'Data recupero', 'Confronto'] as $head) {
        $html .= '<th>' . mcd_h($head) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    if (empty($rows)) {
        $html .= '<tr><td colspan="10" class="text-center">Nessuna carenza caricata per i filtri selezionati.</td></tr>';
    }

    foreach ($rows as $row) {
        $comparison = (string)($row['confronto'] ?? '');
        $class = '';
        if ($comparison === 'Da verificare') {
            $class = ' class="mcd-row-check"';
        } elseif (($row['corso_recuperato'] ?? null) !== null && intval($row['corso_recuperato']) === 0) {
            $class = ' class="mcd-row-not-recovered"';
        } elseif (($row['corso_recuperato'] ?? null) !== null && intval($row['corso_recuperato']) === 1 && intval($row['corso_appello'] ?? 0) >= 2) {
            $class = ' class="mcd-row-second-appeal"';
        } elseif (($row['corso_recuperato'] ?? null) !== null && intval($row['corso_recuperato']) === 1) {
            $class = ' class="mcd-row-first-appeal"';
        } elseif (intval($row['recuperato_mastercom'] ?? 0) === 1 && ($row['corso_recuperato'] ?? null) === null) {
            $class = ' class="mcd-row-mastercom-only"';
        } elseif ($comparison === 'Da abbinare') {
            $class = ' class="warning"';
        } elseif ($comparison === 'OK') {
            $class = ' class="success"';
        }

        $gestore = intval($row['carenza_id'] ?? 0) > 0 ? 'Presente' : 'Non salvata';
        if (intval($row['id_studente_gestore'] ?? 0) <= 0) {
            $gestore = 'Studente non abbinato';
        } elseif (intval($row['id_materia_gestore'] ?? 0) <= 0) {
            $gestore = 'Materia non abbinata';
        }

        $html .= '<tr' . $class . '>';
        $html .= '<td>' . mcd_h($row['classe_gestore'] ?: $row['classe']) . '</td>';
        $html .= '<td>' . mcd_h($row['studente_gestore'] ?: $row['studente_nome']) . '</td>';
        $html .= '<td>' . mcd_h($row['materia_gestore'] ?: $row['materia']) . '</td>';
        $html .= '<td class="text-center">' . mcd_h($row['anno_label']) . '</td>';
        $html .= '<td>' . mcd_h($row['tipo_debito']) . '</td>';
        $html .= '<td class="text-center">' . mcd_h(mcd_state_label($row['recuperato_mastercom'])) . '</td>';
        $html .= '<td class="text-center">' . mcd_h($gestore) . '</td>';
        $html .= '<td>' . mcd_h($row['corso_label'] ?? '') . '</td>';
        $html .= '<td class="text-center">' . mcd_h($row['data_recupero'] ?? '') . '</td>';
        $html .= '<td class="text-center">' . mcd_h($comparison) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    if ($forExport) {
        $html = str_replace([' class="table table-bordered table-condensed mcd-table"', ' class="text-center"', ' class="danger"', ' class="warning"', ' class="success"'], ['', '', '', '', ''], $html);
        $html = str_replace([' class="mcd-row-not-recovered"', ' class="mcd-row-second-appeal"', ' class="mcd-row-first-appeal"', ' class="mcd-row-mastercom-only"', ' class="mcd-row-check"'], ['', '', '', '', ''], $html);
    }

    return $html;
}

mastercomDebtsEnsureTables();

global $__anno_scolastico_corrente_id;

$message = '';
$error = '';
$selectedClassId = intval($_REQUEST['class_id'] ?? 0);
$selectedYearId = intval($_REQUEST['school_year_id'] ?? ($__anno_scolastico_corrente_id ?? 0));
$selectedIssueFilter = trim((string)($_REQUEST['issue_filter'] ?? 'all'));
if (!in_array($selectedIssueFilter, ['all', 'problems', 'check'], true)) {
    $selectedIssueFilter = 'all';
}
$selectedRecoveryFilter = trim((string)($_REQUEST['recovery_filter'] ?? 'all'));
if (!in_array($selectedRecoveryFilter, ['all', 'recovered', 'not_recovered'], true)) {
    $selectedRecoveryFilter = 'all';
}
$selectedAppealFilter = trim((string)($_REQUEST['appeal_filter'] ?? 'all'));
if (!in_array($selectedAppealFilter, ['all', 'first', 'second'], true)) {
    $selectedAppealFilter = 'all';
}
if ($selectedRecoveryFilter !== 'recovered') {
    $selectedAppealFilter = 'all';
}
$classRows = mastercomAdminOperationalClassRows('mastercom_id_classe, nome');
$schoolYears = mastercomDebtsSchoolYears();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'fetch_mastercom') {
        if ($selectedClassId <= 0) {
            $error = 'Seleziona una classe MasterCom da leggere.';
        } else {
            $result = mastercomDebtsFetchAndStoreClass($selectedClassId);
            if (!empty($result['ok'])) {
                $stats = $result['stats'] ?? [];
                $message = ($result['message'] ?? 'Lettura completata')
                    . ' Cache precedente ' . intval($stats['deleted_stale'] ?? 0) . '.'
                    . ' Non abbinate: studenti ' . intval($stats['without_student'] ?? 0)
                    . ', materie ' . intval($stats['without_subject'] ?? 0)
                    . ', anni ' . intval($stats['without_year'] ?? 0) . '.';
            } else {
                $error = $result['message'] ?? 'Lettura MasterCom non riuscita.';
            }
        }
    } elseif ($action === 'fetch_mastercom_all') {
        $result = mastercomDebtsFetchAndStoreAllClasses();
        $stats = $result['stats'] ?? [];
        if (!empty($result['ok'])) {
            $message = ($result['message'] ?? 'Lettura globale completata')
                . ' Cache precedente ' . intval($stats['deleted_stale'] ?? 0) . '.'
                . ' Non abbinate: studenti ' . intval($stats['without_student'] ?? 0)
                . ', materie ' . intval($stats['without_subject'] ?? 0)
                . ', anni ' . intval($stats['without_year'] ?? 0) . '.';
            if (!empty($result['errors'])) {
                $message .= ' Classi con errore: ' . implode(' | ', array_slice((array)$result['errors'], 0, 8));
                if (count((array)$result['errors']) > 8) {
                    $message .= ' ...';
                }
            }
        } else {
            $error = $result['message'] ?? 'Lettura globale MasterCom non riuscita.';
        }
    } elseif ($action === 'save_gestore') {
        if ($selectedYearId <= 0) {
            $error = 'Per salvare in GestOre seleziona un anno scolastico specifico.';
        } else {
            $stats = mastercomDebtsSaveToGestoreCarenze($selectedYearId, $selectedClassId);
            $message = 'Aggiornamento GestOre completato: inserite ' . intval($stats['inserted'])
                . ', aggiornate ' . intval($stats['updated'])
                . ', saltate ' . intval($stats['skipped']) . '.';
        }
    }
}

mastercomDebtsRefreshMissingSubjectMatches();
mastercomDebtsRefreshCachedClassMatches();
$reportRows = mastercomDebtsReportRows($selectedYearId, $selectedClassId);
if ($selectedIssueFilter === 'check') {
    $reportRows = array_values(array_filter($reportRows, function ($row) {
        return (string)($row['confronto'] ?? '') === 'Da verificare';
    }));
} elseif ($selectedIssueFilter === 'problems') {
    $reportRows = array_values(array_filter($reportRows, function ($row) {
        return (string)($row['confronto'] ?? '') !== 'OK';
    }));
}
if ($selectedRecoveryFilter === 'recovered') {
    $reportRows = array_values(array_filter($reportRows, function ($row) {
        return intval($row['recuperato_mastercom'] ?? 0) === 1;
    }));
    if ($selectedAppealFilter === 'first') {
        $reportRows = array_values(array_filter($reportRows, function ($row) {
            return intval($row['corso_recuperato'] ?? 0) === 1 && intval($row['corso_appello'] ?? 0) === 1;
        }));
    } elseif ($selectedAppealFilter === 'second') {
        $reportRows = array_values(array_filter($reportRows, function ($row) {
            return intval($row['corso_recuperato'] ?? 0) === 1 && intval($row['corso_appello'] ?? 0) >= 2;
        }));
    }
} elseif ($selectedRecoveryFilter === 'not_recovered') {
    $reportRows = array_values(array_filter($reportRows, function ($row) {
        return intval($row['recuperato_mastercom'] ?? 0) !== 1;
    }));
}

if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    $fileName = mastercomDebtsExportFileName('carenze_mastercom', 'xls');
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h2>Stato carenze MasterCom</h2>';
    echo mcd_report_table_html($reportRows, true);
    echo '</body></html>';
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf)) {
        require_once $tcpdf;
    } else {
        require_once '../common/tcpdf/tcpdf.php';
    }

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GestOre');
    $pdf->SetAuthor('GestOre');
    $pdf->SetTitle('Stato carenze MasterCom');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(true, 8);
    $pdf->AddPage();
    $html = '<style>
        h1 { color: #0b5d7e; font-size: 18px; }
        table { border-collapse: collapse; width: 100%; font-size: 8px; }
        th { background-color: #2f7d32; color: #fff; font-weight: bold; border: 1px solid #222; padding: 4px; }
        td { border: 1px solid #555; padding: 3px; }
    </style>';
    $html .= '<h1>Stato carenze MasterCom</h1>';
    $html .= mcd_report_table_html($reportRows, true);
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output(mastercomDebtsExportFileName('carenze_mastercom', 'pdf'), 'D');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Carenze MasterCom</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .mcd-toolbar {
            background: linear-gradient(#fff7e8, #ffd082);
            border: 1px solid #f0ad4e;
            border-radius: 4px;
            padding: 14px;
            margin-bottom: 16px;
        }
        .mcd-toolbar .row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: nowrap;
        }
        .mcd-toolbar .mcd-filter-class {
            flex: 0 1 420px;
            min-width: 210px;
        }
        .mcd-toolbar .mcd-filter-year {
            flex: 0 1 260px;
            min-width: 160px;
        }
        .mcd-toolbar .mcd-filter-issue {
            flex: 0 1 230px;
            min-width: 150px;
        }
        .mcd-toolbar .mcd-filter-recovery {
            flex: 0 1 210px;
            min-width: 145px;
        }
        .mcd-toolbar .mcd-actions {
            flex: 1 0 auto;
            min-width: 430px;
            white-space: nowrap;
        }
        .mcd-toolbar .mcd-actions .btn {
            margin-bottom: 3px;
        }
        @media (max-width: 1200px) {
            .mcd-toolbar {
                padding: 10px;
            }
            .mcd-toolbar .row {
                gap: 6px;
            }
            .mcd-toolbar .mcd-filter-class {
                flex-basis: 310px;
                min-width: 190px;
            }
            .mcd-toolbar .mcd-filter-year {
                flex-basis: 190px;
                min-width: 140px;
            }
            .mcd-toolbar .mcd-filter-issue {
                flex-basis: 170px;
                min-width: 130px;
            }
            .mcd-toolbar .mcd-filter-recovery {
                flex-basis: 165px;
                min-width: 140px;
            }
            .mcd-toolbar .mcd-actions {
                min-width: 390px;
            }
            .mcd-toolbar .mcd-actions .btn {
                padding-left: 7px;
                padding-right: 7px;
            }
            .mcd-toolbar .mcd-actions .mcd-btn-text {
                display: none;
            }
        }
        .mcd-table th,
        .mcd-table td {
            vertical-align: middle !important;
        }
        .mcd-table th:nth-child(1),
        .mcd-table th:nth-child(4),
        .mcd-table th:nth-child(6),
        .mcd-table th:nth-child(7),
        .mcd-table th:nth-child(9),
        .mcd-table th:nth-child(10) {
            text-align: center;
        }
        .mcd-table tbody tr.mcd-row-first-appeal > td {
            background-color: #c7ebc1 !important;
            border-color: #9ac990 !important;
        }
        .mcd-table tbody tr.mcd-row-second-appeal > td {
            background-color: #ffd98a !important;
            border-color: #e2a842 !important;
        }
        .mcd-table tbody tr.mcd-row-not-recovered > td {
            background-color: #f4b4ad !important;
            border-color: #d77c72 !important;
        }
        .mcd-table tbody tr.mcd-row-mastercom-only > td {
            background-color: #cfe6ff !important;
            border-color: #87b8e8 !important;
        }
        .mcd-table tbody tr.mcd-row-check > td {
            background-color: #fff200 !important;
            border-color: #d8c900 !important;
            font-weight: 600;
        }
        .mcd-table tbody tr.mcd-row-first-appeal > td:first-child {
            border-left: 7px solid #2f8f2f !important;
        }
        .mcd-table tbody tr.mcd-row-second-appeal > td:first-child {
            border-left: 7px solid #d98200 !important;
        }
        .mcd-table tbody tr.mcd-row-not-recovered > td:first-child {
            border-left: 7px solid #b7322c !important;
        }
        .mcd-table tbody tr.mcd-row-mastercom-only > td:first-child {
            border-left: 7px solid #2b73b9 !important;
        }
        .mcd-table tbody tr.mcd-row-check > td:first-child {
            border-left: 7px solid #111 !important;
        }
        #mcdWaitOverlay {
            position: fixed;
            z-index: 9999;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.25);
        }
        #mcdWaitOverlay .box {
            width: 420px;
            max-width: 92vw;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 8px 35px rgba(0,0,0,.25);
            padding: 22px;
            text-align: center;
        }
    </style>
</head>
<body>
<?php require_once headerAdminDidatticaPath('../common'); ?>
<div id="mcdWaitOverlay">
    <div class="box">
        <h4 id="mcdWaitTitle">Operazione in corso</h4>
        <div class="progress">
            <div id="mcdWaitBar" class="progress-bar progress-bar-striped active" style="width: 0%">0%</div>
        </div>
        <p id="mcdWaitText">Attendere il completamento.</p>
    </div>
</div>
<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-alert"></span>&emsp;Carenze MasterCom
        </div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo mcd_h($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo mcd_h($error); ?></div>
            <?php endif; ?>

            <form method="get" class="mcd-toolbar" id="mcdFilterForm">
                <div class="row">
                    <div class="mcd-filter-class">
                        <label for="class_id">Classe</label>
                        <select id="class_id" name="class_id" class="form-control">
                            <option value="0">Tutte le classi caricate</option>
                            <?php foreach ($classRows as $classRow): ?>
                                <option value="<?php echo intval($classRow['mastercom_id_classe']); ?>" <?php echo intval($classRow['mastercom_id_classe']) === $selectedClassId ? 'selected' : ''; ?>>
                                    <?php echo mcd_h(($classRow['nome'] ?? '') . ' [' . ($classRow['mastercom_id_classe'] ?? '') . ']'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mcd-filter-year">
                        <label for="school_year_id">Anno scolastico</label>
                        <select id="school_year_id" name="school_year_id" class="form-control">
                            <option value="0" <?php echo $selectedYearId === 0 ? 'selected' : ''; ?>>Tutti gli anni</option>
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?php echo intval($year['id']); ?>" <?php echo intval($year['id']) === $selectedYearId ? 'selected' : ''; ?>>
                                    <?php echo mcd_h($year['anno']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mcd-filter-issue">
                        <label for="issue_filter">Confronto</label>
                        <select id="issue_filter" name="issue_filter" class="form-control">
                            <option value="all" <?php echo $selectedIssueFilter === 'all' ? 'selected' : ''; ?>>Tutte le righe</option>
                            <option value="problems" <?php echo $selectedIssueFilter === 'problems' ? 'selected' : ''; ?>>Solo problemi</option>
                            <option value="check" <?php echo $selectedIssueFilter === 'check' ? 'selected' : ''; ?>>Solo Da verificare</option>
                        </select>
                    </div>
                    <div class="mcd-filter-recovery">
                        <label for="recovery_filter">MasterCom</label>
                        <select id="recovery_filter" name="recovery_filter" class="form-control">
                            <option value="all" <?php echo $selectedRecoveryFilter === 'all' ? 'selected' : ''; ?>>Tutti</option>
                            <option value="recovered" <?php echo $selectedRecoveryFilter === 'recovered' ? 'selected' : ''; ?>>Recuperato</option>
                            <option value="not_recovered" <?php echo $selectedRecoveryFilter === 'not_recovered' ? 'selected' : ''; ?>>Non recuperato</option>
                        </select>
                    </div>
                    <div class="mcd-filter-recovery" id="mcdAppealFilterWrap" style="<?php echo $selectedRecoveryFilter === 'recovered' ? '' : 'display:none;'; ?>">
                        <label for="appeal_filter">Appello</label>
                        <select id="appeal_filter" name="appeal_filter" class="form-control">
                            <option value="all" <?php echo $selectedAppealFilter === 'all' ? 'selected' : ''; ?>>Tutti</option>
                            <option value="first" <?php echo $selectedAppealFilter === 'first' ? 'selected' : ''; ?>>Primo appello</option>
                            <option value="second" <?php echo $selectedAppealFilter === 'second' ? 'selected' : ''; ?>>Secondo appello</option>
                        </select>
                    </div>
                    <div class="mcd-actions">
                        <label>&nbsp;</label><br>
                        <button type="submit" form="mcdFetchForm" class="btn btn-info" onclick="mcdSyncHiddenForms(); mcdShowWait('Lettura MasterCom', 'Sto leggendo le carenze della classe da MasterCom.');">
                            <span class="glyphicon glyphicon-cloud-download"></span> <span class="mcd-btn-text">Leggi da MasterCom</span>
                        </button>
                        <button type="submit" form="mcdFetchAllForm" class="btn btn-warning" onclick="mcdSyncHiddenForms(); return confirm('Leggere le carenze da MasterCom per tutte le classi? Operazione lunga: puo richiedere diversi minuti.') && (mcdShowWait('Lettura globale MasterCom', 'Sto leggendo le carenze di tutte le classi da MasterCom.'), true);">
                            <span class="glyphicon glyphicon-cloud-download"></span> <span class="mcd-btn-text">Leggi tutte</span>
                        </button>
                        <button type="submit" form="mcdSaveForm" class="btn btn-success" onclick="mcdSyncHiddenForms(); mcdShowWait('Aggiornamento GestOre', 'Sto salvando lo stato delle carenze in GestOre.');">
                            <span class="glyphicon glyphicon-floppy-disk"></span> <span class="mcd-btn-text">Salva in GestOre</span>
                        </button>
                        <a class="btn btn-primary" href="mastercom_debts_stats.php?school_year_id=<?php echo intval($selectedYearId); ?>">
                            <span class="glyphicon glyphicon-stats"></span> <span class="mcd-btn-text">Statistiche</span>
                        </a>
                        <a class="btn btn-default" href="mastercom_debts_plan.php?school_year_id=<?php echo intval($selectedYearId); ?>">
                            <span class="glyphicon glyphicon-calendar"></span> <span class="mcd-btn-text">Pianifica</span>
                        </a>
                        <a class="btn btn-danger" onclick="mcdExportWait(this); return false;" href="?class_id=<?php echo intval($selectedClassId); ?>&school_year_id=<?php echo intval($selectedYearId); ?>&issue_filter=<?php echo urlencode($selectedIssueFilter); ?>&recovery_filter=<?php echo urlencode($selectedRecoveryFilter); ?>&appeal_filter=<?php echo urlencode($selectedAppealFilter); ?>&export=pdf">
                            <span class="glyphicon glyphicon-file"></span> <span class="mcd-btn-text">PDF</span>
                        </a>
                        <a class="btn btn-success" onclick="mcdExportWait(this); return false;" href="?class_id=<?php echo intval($selectedClassId); ?>&school_year_id=<?php echo intval($selectedYearId); ?>&issue_filter=<?php echo urlencode($selectedIssueFilter); ?>&recovery_filter=<?php echo urlencode($selectedRecoveryFilter); ?>&appeal_filter=<?php echo urlencode($selectedAppealFilter); ?>&export=xls">
                            <span class="glyphicon glyphicon-list-alt"></span> <span class="mcd-btn-text">XLS</span>
                        </a>
                    </div>
                </div>
            </form>

            <form id="mcdFetchForm" method="post" style="display:none;">
                <input type="hidden" name="action" value="fetch_mastercom">
                <input type="hidden" name="class_id" value="<?php echo intval($selectedClassId); ?>">
                <input type="hidden" name="school_year_id" value="<?php echo intval($selectedYearId); ?>">
                <input type="hidden" name="issue_filter" value="<?php echo mcd_h($selectedIssueFilter); ?>">
                <input type="hidden" name="recovery_filter" value="<?php echo mcd_h($selectedRecoveryFilter); ?>">
                <input type="hidden" name="appeal_filter" value="<?php echo mcd_h($selectedAppealFilter); ?>">
            </form>
            <form id="mcdFetchAllForm" method="post" style="display:none;">
                <input type="hidden" name="action" value="fetch_mastercom_all">
                <input type="hidden" name="class_id" value="<?php echo intval($selectedClassId); ?>">
                <input type="hidden" name="school_year_id" value="<?php echo intval($selectedYearId); ?>">
                <input type="hidden" name="issue_filter" value="<?php echo mcd_h($selectedIssueFilter); ?>">
                <input type="hidden" name="recovery_filter" value="<?php echo mcd_h($selectedRecoveryFilter); ?>">
                <input type="hidden" name="appeal_filter" value="<?php echo mcd_h($selectedAppealFilter); ?>">
            </form>
            <form id="mcdSaveForm" method="post" style="display:none;">
                <input type="hidden" name="action" value="save_gestore">
                <input type="hidden" name="class_id" value="<?php echo intval($selectedClassId); ?>">
                <input type="hidden" name="school_year_id" value="<?php echo intval($selectedYearId); ?>">
                <input type="hidden" name="issue_filter" value="<?php echo mcd_h($selectedIssueFilter); ?>">
                <input type="hidden" name="recovery_filter" value="<?php echo mcd_h($selectedRecoveryFilter); ?>">
                <input type="hidden" name="appeal_filter" value="<?php echo mcd_h($selectedAppealFilter); ?>">
            </form>

            <div class="alert alert-info">
                La lettura da MasterCom aggiorna una cache locale. Il salvataggio in GestOre inserisce le carenze mancanti e aggiorna solo i campi di stato MasterCom, senza cancellare note, validazioni o invii gia presenti.
            </div>

            <?php echo mcd_report_table_html($reportRows); ?>
        </div>
    </div>
</div>
<script>
    function mcdShowWait(title, text) {
        var overlay = document.getElementById('mcdWaitOverlay');
        var bar = document.getElementById('mcdWaitBar');
        document.getElementById('mcdWaitTitle').textContent = title || 'Operazione in corso';
        document.getElementById('mcdWaitText').textContent = text || 'Attendere il completamento.';
        overlay.style.display = 'flex';
        var value = 0;
        bar.style.width = '0%';
        bar.textContent = '0%';
        if (window.mcdWaitTimer) window.clearInterval(window.mcdWaitTimer);
        window.mcdWaitTimer = window.setInterval(function () {
            value = Math.min(96, value + (value < 70 ? 4 : 1));
            bar.style.width = value + '%';
            bar.textContent = value + '%';
        }, 700);
    }
    function mcdSyncHiddenForms() {
        var classId = document.getElementById('class_id').value;
        var yearId = document.getElementById('school_year_id').value;
        var issueFilter = document.getElementById('issue_filter').value;
        var recoveryFilter = document.getElementById('recovery_filter').value;
        var appealFilter = document.getElementById('appeal_filter').value;
        document.querySelectorAll('input[name="class_id"]').forEach(function (input) { input.value = classId; });
        document.querySelectorAll('input[name="school_year_id"]').forEach(function (input) { input.value = yearId; });
        document.querySelectorAll('input[name="issue_filter"]').forEach(function (input) { input.value = issueFilter; });
        document.querySelectorAll('input[name="recovery_filter"]').forEach(function (input) { input.value = recoveryFilter; });
        document.querySelectorAll('input[name="appeal_filter"]').forEach(function (input) { input.value = appealFilter; });
    }
    function mcdUpdateAppealFilterVisibility() {
        var recovery = document.getElementById('recovery_filter');
        var appeal = document.getElementById('appeal_filter');
        var wrap = document.getElementById('mcdAppealFilterWrap');
        if (!recovery || !appeal || !wrap) return;
        var visible = recovery.value === 'recovered';
        wrap.style.display = visible ? '' : 'none';
        if (!visible) appeal.value = 'all';
    }
    document.querySelectorAll('#mcdFilterForm select').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.id === 'recovery_filter') mcdUpdateAppealFilterVisibility();
            document.getElementById('mcdFilterForm').submit();
        });
    });
    mcdUpdateAppealFilterVisibility();
    function mcdExportWait(link) {
        mcdShowWait('Preparazione export', 'Il download partira appena il file sara pronto.');
        window.location.href = link.href;
        window.setTimeout(function () {
            var overlay = document.getElementById('mcdWaitOverlay');
            if (overlay) overlay.style.display = 'none';
            if (window.mcdWaitTimer) window.clearInterval(window.mcdWaitTimer);
        }, 9000);
    }
</script>
</body>
</html>

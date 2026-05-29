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
    foreach (['Classe', 'Studente', 'Materia', 'A.S.', 'Tipo debito', 'MasterCom', 'GestOre', 'Corso recupero', 'Confronto'] as $head) {
        $html .= '<th>' . mcd_h($head) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    if (empty($rows)) {
        $html .= '<tr><td colspan="9" class="text-center">Nessuna carenza caricata per i filtri selezionati.</td></tr>';
    }

    foreach ($rows as $row) {
        $comparison = (string)($row['confronto'] ?? '');
        $class = '';
        if ($comparison === 'Da verificare') {
            $class = ' class="danger"';
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
        $html .= '<td class="text-center">' . mcd_h($comparison) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    if ($forExport) {
        $html = str_replace([' class="table table-bordered table-condensed mcd-table"', ' class="text-center"', ' class="danger"', ' class="warning"', ' class="success"'], ['', '', '', '', ''], $html);
    }

    return $html;
}

mastercomDebtsEnsureTables();

global $__anno_scolastico_corrente_id;

$message = '';
$error = '';
$selectedClassId = intval($_REQUEST['class_id'] ?? 0);
$selectedYearId = intval($_REQUEST['school_year_id'] ?? ($__anno_scolastico_corrente_id ?? 0));
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
                    . ' Non abbinate: studenti ' . intval($stats['without_student'] ?? 0)
                    . ', materie ' . intval($stats['without_subject'] ?? 0)
                    . ', anni ' . intval($stats['without_year'] ?? 0) . '.';
            } else {
                $error = $result['message'] ?? 'Lettura MasterCom non riuscita.';
            }
        }
    } elseif ($action === 'save_gestore') {
        if ($selectedYearId <= 0) {
            $error = 'Seleziona un anno scolastico valido.';
        } else {
            $stats = mastercomDebtsSaveToGestoreCarenze($selectedYearId, $selectedClassId);
            $message = 'Aggiornamento GestOre completato: inserite ' . intval($stats['inserted'])
                . ', aggiornate ' . intval($stats['updated'])
                . ', saltate ' . intval($stats['skipped']) . '.';
        }
    }
}

$reportRows = $selectedYearId > 0 ? mastercomDebtsReportRows($selectedYearId, $selectedClassId) : [];

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
        .mcd-table th,
        .mcd-table td {
            vertical-align: middle !important;
        }
        .mcd-table th:nth-child(1),
        .mcd-table th:nth-child(4),
        .mcd-table th:nth-child(6),
        .mcd-table th:nth-child(7),
        .mcd-table th:nth-child(9) {
            text-align: center;
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
<?php require_once '../common/header-admin.php'; ?>
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

            <form method="get" class="mcd-toolbar">
                <div class="row">
                    <div class="col-md-4">
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
                    <div class="col-md-3">
                        <label for="school_year_id">Anno scolastico</label>
                        <select id="school_year_id" name="school_year_id" class="form-control">
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?php echo intval($year['id']); ?>" <?php echo intval($year['id']) === $selectedYearId ? 'selected' : ''; ?>>
                                    <?php echo mcd_h($year['anno']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label>&nbsp;</label><br>
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-search"></span> Visualizza
                        </button>
                        <button type="submit" form="mcdFetchForm" class="btn btn-info" onclick="mcdSyncHiddenForms(); mcdShowWait('Lettura MasterCom', 'Sto leggendo le carenze della classe da MasterCom.');">
                            <span class="glyphicon glyphicon-cloud-download"></span> Leggi da MasterCom
                        </button>
                        <button type="submit" form="mcdSaveForm" class="btn btn-success" onclick="mcdSyncHiddenForms(); mcdShowWait('Aggiornamento GestOre', 'Sto salvando lo stato delle carenze in GestOre.');">
                            <span class="glyphicon glyphicon-floppy-disk"></span> Salva in GestOre
                        </button>
                        <a class="btn btn-danger" onclick="mcdExportWait(this); return false;" href="?class_id=<?php echo intval($selectedClassId); ?>&school_year_id=<?php echo intval($selectedYearId); ?>&export=pdf">
                            <span class="glyphicon glyphicon-file"></span> PDF
                        </a>
                        <a class="btn btn-success" onclick="mcdExportWait(this); return false;" href="?class_id=<?php echo intval($selectedClassId); ?>&school_year_id=<?php echo intval($selectedYearId); ?>&export=xls">
                            <span class="glyphicon glyphicon-list-alt"></span> XLS
                        </a>
                    </div>
                </div>
            </form>

            <form id="mcdFetchForm" method="post" style="display:none;">
                <input type="hidden" name="action" value="fetch_mastercom">
                <input type="hidden" name="class_id" value="<?php echo intval($selectedClassId); ?>">
                <input type="hidden" name="school_year_id" value="<?php echo intval($selectedYearId); ?>">
            </form>
            <form id="mcdSaveForm" method="post" style="display:none;">
                <input type="hidden" name="action" value="save_gestore">
                <input type="hidden" name="class_id" value="<?php echo intval($selectedClassId); ?>">
                <input type="hidden" name="school_year_id" value="<?php echo intval($selectedYearId); ?>">
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
        document.querySelectorAll('input[name="class_id"]').forEach(function (input) { input.value = classId; });
        document.querySelectorAll('input[name="school_year_id"]').forEach(function (input) { input.value = yearId; });
    }
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

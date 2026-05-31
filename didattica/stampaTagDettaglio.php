<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/mastercom/tag_report_lib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'admin');

$stampaId = intval($_GET['id'] ?? 0);
$stampa = $stampaId > 0 ? mastercomTagReportLoadStampa($stampaId) : null;
if (!$stampa) {
    http_response_code(404);
    echo 'Stampa TAG non trovata';
    exit;
}

$filters = [
    'tag' => trim((string)($_GET['tag'] ?? '')),
    'docente' => trim((string)($_GET['docente'] ?? '')),
    'materia' => trim((string)($_GET['materia'] ?? '')),
    'classe' => trim((string)($_GET['classe'] ?? '')),
    'q' => trim((string)($_GET['q'] ?? '')),
];
$rows = mastercomTagReportLoadRows($stampaId, $filters);
$summary = mastercomTagReportSummary($stampaId);

function st_h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function st_option_list(int $stampaId, string $field, string $selected): string
{
    $html = '<option value="">Tutti</option>';
    foreach (mastercomTagReportDistinctValues($stampaId, $field) as $value) {
        $value = (string)$value;
        $html .= '<option value="' . st_h($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . st_h($value) . '</option>';
    }
    return $html;
}

function st_export_query(int $stampaId, array $filters, string $format): string
{
    $params = array_filter(array_merge(['id' => $stampaId, 'format' => $format], $filters), function ($value) {
        return $value !== '' && $value !== null;
    });
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dettaglio stampa TAG</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .tag-report-wrap { padding: 0 8px 24px; }
        .tag-report-hero {
            background: #0b4f71;
            color: #fff;
            padding: 18px 22px;
            border-radius: 4px;
            margin-bottom: 14px;
        }
        .tag-report-hero h2 { margin: 0 0 6px; font-weight: 700; }
        .tag-report-meta { color: #d9edf7; font-size: 13px; }
        .tag-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }
        .tag-kpi {
            border: 1px solid #d8e2ea;
            border-left: 5px solid #0b79a5;
            border-radius: 4px;
            padding: 11px 13px;
            background: #fff;
        }
        .tag-kpi-label { color: #667085; font-size: 12px; text-transform: uppercase; font-weight: 700; }
        .tag-kpi-value { font-size: 24px; line-height: 1.1; font-weight: 800; color: #0b4f71; }
        .tag-chip {
            display: inline-block;
            background: #eaf6fb;
            border: 1px solid #c8e6f2;
            color: #0b4f71;
            border-radius: 4px;
            padding: 4px 7px;
            margin: 0 4px 4px 0;
            font-size: 12px;
        }
        .tag-table th {
            background: #0b79a5;
            color: #fff;
            vertical-align: middle !important;
            white-space: nowrap;
        }
        .tag-table td { vertical-align: top !important; }
        .tag-table .argomento { min-width: 360px; }
        .tag-filter-panel {
            background: #f7fafc;
            border: 1px solid #d8e2ea;
            border-radius: 4px;
            padding: 14px;
            margin-bottom: 14px;
        }
    </style>
</head>
<body>
<?php
if (haRuolo('docente') && !(haRuolo('admin') || haRuolo('segreteria-didattica'))) {
    require_once '../common/header-docente.php';
} elseif (haRuolo('admin')) {
    require_once '../common/header-admin.php';
} else {
    require_once '../common/header-didattica.php';
}
?>
<div class="container-fluid tag-report-wrap">
    <div class="tag-report-hero">
        <h2><span class="glyphicon glyphicon-tags"></span>&ensp;Stampa TAG importata</h2>
        <div class="tag-report-meta">
            Periodo: <?php echo st_h($stampa['data_inizio']); ?> - <?php echo st_h($stampa['data_fine']); ?>
            &ensp;|&ensp; Classi: <?php echo st_h($stampa['classi_label']); ?>
            &ensp;|&ensp; Creata: <?php echo st_h(mastercomTagReportFormatDateTime($stampa['created_at'])); ?>
        </div>
    </div>

    <div class="tag-kpis">
        <div class="tag-kpi">
            <div class="tag-kpi-label">Righe totali</div>
            <div class="tag-kpi-value"><?php echo intval($summary['totale']); ?></div>
        </div>
        <div class="tag-kpi">
            <div class="tag-kpi-label">Righe filtrate</div>
            <div class="tag-kpi-value"><?php echo count($rows); ?></div>
        </div>
        <div class="tag-kpi">
            <div class="tag-kpi-label">Tag distinti</div>
            <div class="tag-kpi-value"><?php echo count($summary['tag']); ?></div>
        </div>
        <div class="tag-kpi">
            <div class="tag-kpi-label">File sorgente</div>
            <div style="font-weight:700;color:#0b4f71;word-break:break-word;"><?php echo st_h($stampa['source_filename']); ?></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="tag-filter-panel">
                <form method="get" class="row">
                    <input type="hidden" name="id" value="<?php echo intval($stampaId); ?>">
                    <div class="col-sm-2">
                        <label>Tag</label>
                        <select name="tag" class="form-control input-sm"><?php echo st_option_list($stampaId, 'tag', $filters['tag']); ?></select>
                    </div>
                    <div class="col-sm-2">
                        <label>Docente</label>
                        <select name="docente" class="form-control input-sm"><?php echo st_option_list($stampaId, 'docente', $filters['docente']); ?></select>
                    </div>
                    <div class="col-sm-2">
                        <label>Materia</label>
                        <select name="materia" class="form-control input-sm"><?php echo st_option_list($stampaId, 'materia', $filters['materia']); ?></select>
                    </div>
                    <div class="col-sm-2">
                        <label>Classe</label>
                        <select name="classe" class="form-control input-sm"><?php echo st_option_list($stampaId, 'classe', $filters['classe']); ?></select>
                    </div>
                    <div class="col-sm-3">
                        <label>Cerca</label>
                        <input type="text" name="q" value="<?php echo st_h($filters['q']); ?>" class="form-control input-sm">
                    </div>
                    <div class="col-sm-1">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm btn-block"><span class="glyphicon glyphicon-filter"></span></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-4 text-right" style="margin-bottom:14px;">
            <a class="btn btn-default" href="stampaTag.php"><span class="glyphicon glyphicon-plus"></span>&ensp;Nuova stampa</a>
            <a class="btn btn-success" href="stampaTagExport.php?<?php echo st_h(st_export_query($stampaId, $filters, 'xlsx')); ?>">
                <span class="glyphicon glyphicon-download-alt"></span>&ensp;Excel
            </a>
            <a class="btn btn-danger" href="stampaTagExport.php?<?php echo st_h(st_export_query($stampaId, $filters, 'pdf')); ?>">
                <span class="glyphicon glyphicon-file"></span>&ensp;PDF
            </a>
        </div>
    </div>

    <div style="margin-bottom:10px;">
        <?php foreach (array_slice($summary['tag'], 0, 12) as $item): ?>
            <span class="tag-chip"><?php echo st_h($item['label']); ?>: <strong><?php echo intval($item['totale']); ?></strong></span>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-condensed tag-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tag</th>
                    <th>Docente</th>
                    <th>Materia</th>
                    <th>Classe</th>
                    <th>Argomento</th>
                    <th>Modulo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted">Nessuna riga trovata con i filtri selezionati.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo st_h(mastercomTagReportFormatDateTime($row['data_ora'])); ?></td>
                        <td><?php echo st_h($row['tag']); ?></td>
                        <td><?php echo st_h($row['docente']); ?></td>
                        <td><?php echo st_h($row['materia']); ?></td>
                        <td><?php echo st_h($row['classe']); ?></td>
                        <td class="argomento"><?php echo st_h($row['argomento']); ?></td>
                        <td><?php echo st_h($row['modulo']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

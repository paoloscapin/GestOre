<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/tabelloni_lib.php';
require_once __DIR__ . '/../common/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ruoloRichiesto('admin', 'segreteria-didattica');

function mcte_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mcte_filename(string $extension): string
{
    return 'riepilogo_esiti_tabelloni_' . mcte_now()->format('Ymd_His') . '.' . $extension;
}

function mcte_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Europe/Rome'));
}

function mcte_class_year_label(int $year): string
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

function mcte_school_year_label(int $schoolYearId): string
{
    if ($schoolYearId <= 0) {
        return 'tutti';
    }

    $label = dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($schoolYearId) . " LIMIT 1");
    return $label !== null ? (string)$label : (string)$schoolYearId;
}

function mcte_row_values(array $row, bool $withClasses = false): array
{
    $values = [
        (string)($row['label'] ?? ''),
    ];
    if ($withClasses) {
        $values[] = intval($row['classes'] ?? 0);
    }
    $values[] = intval($row['students'] ?? 0);
    $values[] = intval($row['promossi'] ?? 0);
    $values[] = intval($row['bocciati'] ?? 0);
    $values[] = intval($row['promossi_con_carenze'] ?? 0);
    $values[] = intval($row['promossi_una_carenza'] ?? 0);
    $values[] = intval($row['promossi_due_o_piu_carenze'] ?? 0);

    return $values;
}

function mcte_headers(int $year, bool $withClasses = false, bool $withYear = false): array
{
    $positive = $year === 5 ? 'Ammessi esame di maturita' : 'Promossi';
    $withIssues = $year === 5 ? 'Ammessi con insufficienze' : 'Promossi con carenze';
    $oneIssue = $year === 5 ? '1 insufficienza' : '1 carenza';
    $twoIssues = $year === 5 ? '2 o piu insufficienze' : '2 o piu carenze';

    $headers = [];
    if ($withYear) {
        $headers[] = 'Anno';
    }
    $headers[] = $withClasses ? 'Raggruppamento' : 'Classe';
    if ($withClasses) {
        $headers[] = 'Classi';
    }
    $headers[] = 'Studenti';
    $headers[] = $positive;
    $headers[] = 'Bocciati';
    $headers[] = $withIssues;
    $headers[] = $oneIssue;
    $headers[] = $twoIssues;

    return $headers;
}

function mcte_average_headers(array $subjects, bool $withClasses = false, bool $withYear = false): array
{
    $headers = [];
    if ($withYear) {
        $headers[] = 'Anno';
    }
    $headers[] = $withClasses ? 'Raggruppamento' : 'Classe';
    if ($withClasses) {
        $headers[] = 'Classi';
    }
    $headers[] = 'Studenti';
    foreach ($subjects as $subject) {
        $headers[] = (string)($subject['label'] ?? '');
    }

    return $headers;
}

function mcte_average_row_values(array $row, array $subjects, bool $withClasses = false, ?int $year = null): array
{
    $values = [];
    if ($year !== null) {
        $values[] = mcte_class_year_label($year);
    }
    $values[] = (string)($row['label'] ?? '');
    if ($withClasses) {
        $values[] = intval($row['classes'] ?? 0);
    }
    $values[] = intval($row['students'] ?? 0);
    foreach (array_keys($subjects) as $subjectKey) {
        $avg = $row[$subjectKey . '_avg'] ?? null;
        $values[] = $avg === null ? '' : round((float)$avg, 2);
    }

    return $values;
}

function mcte_average_cell($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float)$value, 2, ',', '');
}

function mcte_classes_by_year(array $classes): array
{
    $grouped = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 0 => []];
    foreach ($classes as $row) {
        $year = intval($row['class_year'] ?? 0);
        if ($year < 1 || $year > 5) {
            $year = 0;
        }
        $grouped[$year][] = $row;
    }

    return $grouped;
}

function mcte_tabellone_class_year(array $detail): int
{
    $tabellone = $detail['tabellone'] ?? [];
    $label = trim((string)(($tabellone['classe_tabellone'] ?? '') ?: ($tabellone['classe'] ?? '')));
    return mastercomTabelloniClassYearFromName($label);
}

function mcte_tabellone_visible_columns(array $detail): array
{
    $year = mcte_tabellone_class_year($detail);
    $columns = [];
    foreach ((array)($detail['columns'] ?? []) as $column) {
        $type = (string)($column['tipo'] ?? '');
        if ($year <= 2 && in_array($type, ['credito', 'credito_totale'], true)) {
            continue;
        }
        $columns[] = $column;
    }

    return $columns;
}

function mcte_tabellone_title(array $detail): string
{
    $tabellone = $detail['tabellone'] ?? [];
    return trim((string)(($tabellone['classe_tabellone'] ?? '') ?: ($tabellone['classe'] ?? 'Tabellone')));
}

function mcte_tabellone_filename(array $detail, string $extension): string
{
    $title = mastercomAdminNormCompact(mcte_tabellone_title($detail));
    if ($title === '') {
        $title = 'tabellone';
    }

    return 'tabellone_' . strtolower($title) . '_' . mcte_now()->format('Ymd_His') . '.' . $extension;
}

function mcte_tabellone_export(int $tabelloneId, string $format): void
{
    $detail = mastercomTabelloniDetail($tabelloneId);
    if (empty($detail['ok'])) {
        http_response_code(404);
        echo mcte_h($detail['message'] ?? 'Tabellone non trovato.');
        exit;
    }

    $tabellone = $detail['tabellone'] ?? [];
    $columns = mcte_tabellone_visible_columns($detail);
    $students = (array)($detail['students'] ?? []);
    $title = mcte_tabellone_title($detail);
    $meta = 'A.S. ' . (string)($tabellone['anno_label'] ?? '')
        . ' - ' . (string)(($tabellone['periodo_label'] ?? '') ?: ($tabellone['periodo'] ?? ''))
        . ' - importato ' . (string)($tabellone['imported_at'] ?? '');

    if ($format === 'xlsx') {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator('GestOre')->setTitle($title);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tabellone');
        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', $meta);

        $headers = ['Studente'];
        foreach ($columns as $column) {
            $headers[] = (string)($column['codice'] ?? $column['descrizione'] ?? '');
        }
        $sheet->fromArray($headers, null, 'A4', true);

        $rowIndex = 5;
        foreach ($students as $student) {
            $rowValues = [
                trim((string)($student['numero'] ?? '') . ' ' . (string)($student['nome'] ?? '')),
            ];
            foreach ($columns as $column) {
                $values = (array)($student['values'] ?? []);
                $value = $values[intval($column['col_index'] ?? 0)] ?? null;
                $rowValues[] = is_array($value) ? (string)($value['value'] ?? '') : '';
            }
            $sheet->fromArray($rowValues, null, 'A' . $rowIndex, true);
            $rowIndex++;
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($headers)));
        $lastRow = max(4, $rowIndex - 1);
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0B4F71');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('667085');
        $sheet->getStyle('A1:' . $lastCol . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A4:' . $lastCol . '4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4:' . $lastCol . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B79A5');
        $sheet->getStyle('A4:' . $lastCol . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A4:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C7D2DA');
        $sheet->getStyle('A4:' . $lastCol . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('B5');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . mcte_tabellone_filename($detail, 'xlsx') . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    if (!class_exists('TCPDF')) {
        $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdf)) {
            require_once $tcpdf;
        } else {
            require_once __DIR__ . '/../common/tcpdf/tcpdf.php';
        }
    }

    $studentWidth = 20;
    $resultColumnIndexes = [];
    foreach ($columns as $index => $column) {
        if (($column['tipo'] ?? '') === 'risultato') {
            $resultColumnIndexes[$index] = true;
        }
    }
    $resultWidthTotal = !empty($resultColumnIndexes) ? 14 : 0;
    $otherColumnCount = max(1, count($columns) - count($resultColumnIndexes));
    $otherWidth = (100 - $studentWidth - $resultWidthTotal) / $otherColumnCount;
    $html = '<style>
        h1 { color:#0b4f71; font-size:18px; }
        .meta { color:#667085; font-size:9px; margin-bottom:8px; }
        table { border-collapse:collapse; font-size:7px; }
        th { background-color:#0b79a5; color:#ffffff; font-weight:bold; text-align:center; vertical-align:middle; }
        td, th { border:1px solid #c7d2da; padding:3px; }
        .center { text-align:center; }
        .insuff { background-color:#ffd6d6; color:#9f1239; font-weight:bold; }
        .ok { background-color:#d9f0d3; font-weight:bold; }
        .ko { background-color:#d9534f; color:#ffffff; font-weight:bold; }
    </style>';
    $html .= '<h1>' . mcte_h($title) . '</h1>';
    $html .= '<div class="meta">' . mcte_h($meta) . '</div>';
    $html .= '<table width="100%" cellpadding="2"><thead><tr>';
    $html .= '<th width="' . $studentWidth . '%" align="center">Studente</th>';
    foreach ($columns as $index => $column) {
        $width = isset($resultColumnIndexes[$index]) ? $resultWidthTotal : $otherWidth;
        $html .= '<th width="' . $width . '%" align="center">' . mcte_h($column['codice'] ?? $column['descrizione'] ?? '') . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($students as $student) {
        $html .= '<tr>';
        $html .= '<td width="' . $studentWidth . '%">' . mcte_h(trim((string)($student['numero'] ?? '') . ' ' . (string)($student['nome'] ?? ''))) . '</td>';
        foreach ($columns as $index => $column) {
            $width = isset($resultColumnIndexes[$index]) ? $resultWidthTotal : $otherWidth;
            $values = (array)($student['values'] ?? []);
            $value = $values[intval($column['col_index'] ?? 0)] ?? [];
            $class = 'center';
            if (!empty($value['insufficiente'])) {
                $class .= ' insuff';
            }
            if (($column['tipo'] ?? '') === 'risultato') {
                $esito = (string)($student['esito_key'] ?? '');
                $class .= in_array($esito, ['non_ammesso', 'in_corso'], true) ? ' ko' : ' ok';
            }
            $html .= '<td width="' . $width . '%" class="' . mcte_h($class) . '">' . mcte_h(is_array($value) ? ($value['value'] ?? '') : '') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GestOre');
    $pdf->SetAuthor('GestOre');
    $pdf->SetTitle($title);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(6, 6, 6);
    $pdf->SetAutoPageBreak(true, 6);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output(mcte_tabellone_filename($detail, 'pdf'), 'D');
    exit;
}

$format = strtolower(trim((string)($_GET['format'] ?? 'xlsx')));
if (!in_array($format, ['xlsx', 'pdf'], true)) {
    $format = 'xlsx';
}

$mode = strtolower(trim((string)($_GET['mode'] ?? 'summary')));
if ($mode === 'tabellone') {
    mcte_tabellone_export(intval($_GET['id'] ?? 0), $format);
}

$schoolYearId = intval($_GET['school_year_id'] ?? 0);
if ($schoolYearId <= 0) {
    global $__anno_scolastico_corrente_id;
    $schoolYearId = intval($__anno_scolastico_corrente_id ?? 0);
}
$period = trim((string)($_GET['period'] ?? '9'));
if ($period === '') {
    $period = '9';
}

mastercomTabelloniRefreshDerivedFields();
$summary = mastercomTabelloniOutcomeSummary($schoolYearId, $period);
$averagesSummary = mastercomTabelloniAveragesSummary($schoolYearId, $period);
$schoolYearLabel = mcte_school_year_label($schoolYearId);
$periodLabel = mastercomTabelloniPeriodLabel($period);
$generatedAt = mcte_now()->format('d/m/Y H:i');

if ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('GestOre')
        ->setTitle('Riepilogo esiti tabelloni');

    $detailSheet = $spreadsheet->getActiveSheet();
    $detailSheet->setTitle('Classi');
    $detailSheet->setCellValue('A1', 'Riepilogo esiti tabelloni');
    $detailSheet->setCellValue('A2', 'Anno scolastico: ' . $schoolYearLabel . ' - Periodo: ' . $periodLabel . ' - Generato: ' . $generatedAt);

    $rowIndex = 4;
    $detailHeaderRows = [];
    $classGroups = mcte_classes_by_year((array)($summary['classes'] ?? []));
    foreach ($classGroups as $year => $classRows) {
        if (empty($classRows)) {
            continue;
        }
        $detailSheet->setCellValue('A' . $rowIndex, $year > 0 ? mcte_class_year_label(intval($year)) : 'Classi n/d');
        $detailSheet->mergeCells('A' . $rowIndex . ':G' . $rowIndex);
        $detailSheet->getStyle('A' . $rowIndex)->getFont()->setBold(true)->getColor()->setRGB('0B4F71');
        $rowIndex++;
        $detailHeaderRows[] = $rowIndex;
        $detailSheet->fromArray(mcte_headers(intval($year), false), null, 'A' . $rowIndex, true);
        $rowIndex++;
        foreach ($classRows as $row) {
            $detailSheet->fromArray(mcte_row_values($row), null, 'A' . $rowIndex, true);
            $rowIndex++;
        }
        $rowIndex++;
    }
    $lastDetailRow = max(4, $rowIndex - 1);

    $totalsSheet = $spreadsheet->createSheet();
    $totalsSheet->setTitle('Totali');
    $totalsSheet->setCellValue('A1', 'Totali esiti tabelloni');
    $totalsSheet->setCellValue('A2', 'Anno scolastico: ' . $schoolYearLabel . ' - Periodo: ' . $periodLabel . ' - Generato: ' . $generatedAt);

    $rowIndex = 4;
    $totalsHeaderRows = [];
    foreach (($summary['totals'] ?? []) as $year => $yearRows) {
        if (empty($yearRows)) {
            continue;
        }
        $totalsSheet->setCellValue('A' . $rowIndex, mcte_class_year_label(intval($year)));
        $totalsSheet->mergeCells('A' . $rowIndex . ':I' . $rowIndex);
        $totalsSheet->getStyle('A' . $rowIndex)->getFont()->setBold(true)->getColor()->setRGB('0B4F71');
        $rowIndex++;
        $totalsHeaderRows[] = $rowIndex;
        $totalsSheet->fromArray(mcte_headers(intval($year), true, true), null, 'A' . $rowIndex, true);
        $rowIndex++;
        foreach ($yearRows as $summaryRow) {
            $totalsSheet->fromArray([
                mcte_class_year_label(intval($year)),
                (string)($summaryRow['label'] ?? ''),
                intval($summaryRow['classes'] ?? 0),
                intval($summaryRow['students'] ?? 0),
                intval($summaryRow['promossi'] ?? 0),
                intval($summaryRow['bocciati'] ?? 0),
                intval($summaryRow['promossi_con_carenze'] ?? 0),
                intval($summaryRow['promossi_una_carenza'] ?? 0),
                intval($summaryRow['promossi_due_o_piu_carenze'] ?? 0),
            ], null, 'A' . $rowIndex, true);
            $rowIndex++;
        }
        $rowIndex++;
    }
    $lastTotalsRow = max(4, $rowIndex - 1);

    $averageSubjects = (array)($averagesSummary['subjects'] ?? []);

    $averagesClassSheet = $spreadsheet->createSheet();
    $averagesClassSheet->setTitle('Medie classi');
    $averagesClassSheet->setCellValue('A1', 'Medie voti per classe');
    $averagesClassSheet->setCellValue('A2', 'Anno scolastico: ' . $schoolYearLabel . ' - Periodo: ' . $periodLabel . ' - Generato: ' . $generatedAt);
    $rowIndex = 4;
    $averageClassHeaderRows = [];
    foreach (mcte_classes_by_year((array)($averagesSummary['classes'] ?? [])) as $year => $classRows) {
        if (empty($classRows)) {
            continue;
        }
        $averagesClassSheet->setCellValue('A' . $rowIndex, $year > 0 ? mcte_class_year_label(intval($year)) : 'Classi n/d');
        $averagesClassSheet->mergeCells('A' . $rowIndex . ':H' . $rowIndex);
        $averagesClassSheet->getStyle('A' . $rowIndex)->getFont()->setBold(true)->getColor()->setRGB('0B4F71');
        $rowIndex++;
        $averageClassHeaderRows[] = $rowIndex;
        $averagesClassSheet->fromArray(mcte_average_headers($averageSubjects, false), null, 'A' . $rowIndex, true);
        $rowIndex++;
        foreach ($classRows as $row) {
            $averagesClassSheet->fromArray(mcte_average_row_values($row, $averageSubjects), null, 'A' . $rowIndex, true);
            $rowIndex++;
        }
        $rowIndex++;
    }
    $lastAverageClassRow = max(4, $rowIndex - 1);

    $averagesTotalsSheet = $spreadsheet->createSheet();
    $averagesTotalsSheet->setTitle('Medie totali');
    $averagesTotalsSheet->setCellValue('A1', 'Medie voti per gruppi');
    $averagesTotalsSheet->setCellValue('A2', 'Anno scolastico: ' . $schoolYearLabel . ' - Periodo: ' . $periodLabel . ' - Generato: ' . $generatedAt);
    $rowIndex = 4;
    $averageTotalsHeaderRows = [];
    foreach (($averagesSummary['totals'] ?? []) as $year => $yearRows) {
        if (empty($yearRows)) {
            continue;
        }
        $averagesTotalsSheet->setCellValue('A' . $rowIndex, mcte_class_year_label(intval($year)));
        $averagesTotalsSheet->mergeCells('A' . $rowIndex . ':J' . $rowIndex);
        $averagesTotalsSheet->getStyle('A' . $rowIndex)->getFont()->setBold(true)->getColor()->setRGB('0B4F71');
        $rowIndex++;
        $averageTotalsHeaderRows[] = $rowIndex;
        $averagesTotalsSheet->fromArray(mcte_average_headers($averageSubjects, true, true), null, 'A' . $rowIndex, true);
        $rowIndex++;
        foreach ($yearRows as $summaryRow) {
            $averagesTotalsSheet->fromArray(mcte_average_row_values($summaryRow, $averageSubjects, true, intval($year)), null, 'A' . $rowIndex, true);
            $rowIndex++;
        }
        $rowIndex++;
    }
    $lastAverageTotalsRow = max(4, $rowIndex - 1);

    foreach ([
        [$detailSheet, 'G', $lastDetailRow, $detailHeaderRows],
        [$totalsSheet, 'I', $lastTotalsRow, $totalsHeaderRows],
        [$averagesClassSheet, 'H', $lastAverageClassRow, $averageClassHeaderRows],
        [$averagesTotalsSheet, 'J', $lastAverageTotalsRow, $averageTotalsHeaderRows],
    ] as $sheetInfo) {
        [$sheet, $lastCol, $lastRow, $headerRows] = $sheetInfo;
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0B4F71');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('667085');
        $sheet->getStyle('A1:' . $lastCol . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        foreach ($headerRows as $headerRow) {
            $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B79A5');
            $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        }
        $sheet->getStyle('A4:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C7D2DA');
        $sheet->getStyle('A4:' . $lastCol . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A5');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . mcte_filename('xlsx') . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

if (!class_exists('TCPDF')) {
    $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf)) {
        require_once $tcpdf;
    } else {
        require_once __DIR__ . '/../common/tcpdf/tcpdf.php';
    }
}

$html = '<style>
    h1 { color:#0b4f71; font-size:18px; }
    h2 { color:#0b4f71; font-size:12px; margin-top:10px; }
    .meta { color:#667085; font-size:9px; margin-bottom:8px; }
    table { border-collapse:collapse; font-size:8px; margin-bottom:8px; }
    th { background-color:#0b79a5; color:#ffffff; font-weight:bold; text-align:center; vertical-align:middle; }
    td, th { border:1px solid #c7d2da; padding:4px; }
    .center { text-align:center; }
    .success { background-color:#d9f0d3; }
    .warning { background-color:#fff1b8; }
    .danger { background-color:#ffd6d6; }
    .group { background-color:#eef7fb; font-weight:bold; color:#0b4f71; }
</style>';
$html .= '<h1>Riepilogo esiti tabelloni</h1>';
$html .= '<div class="meta">Anno scolastico: ' . mcte_h($schoolYearLabel) . ' - Periodo: ' . mcte_h($periodLabel) . ' - Generato: ' . mcte_h($generatedAt) . '</div>';
$html .= '<h2>Dettaglio classi</h2>';
$classGroups = mcte_classes_by_year((array)($summary['classes'] ?? []));
$hasClassRows = false;
foreach ($classGroups as $year => $classRows) {
    if (empty($classRows)) {
        continue;
    }
    $hasClassRows = true;
    $headers = mcte_headers(intval($year), false);
    $widths = ['34%', '10%', '10%', '10%', '12%', '12%', '12%'];
    $html .= '<table width="100%" cellpadding="3"><thead>';
    $html .= '<tr><th colspan="7" align="center" class="group">' . mcte_h($year > 0 ? mcte_class_year_label(intval($year)) : 'Classi n/d') . '</th></tr>';
    $html .= '<tr>';
    foreach ($headers as $index => $header) {
        $html .= '<th width="' . $widths[$index] . '" align="center">' . mcte_h($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($classRows as $row) {
        $html .= '<tr>';
        $html .= '<td width="' . $widths[0] . '">' . mcte_h($row['label'] ?? '') . '</td>';
        $html .= '<td width="' . $widths[1] . '" class="center">' . intval($row['students'] ?? 0) . '</td>';
        $html .= '<td width="' . $widths[2] . '" class="center success">' . intval($row['promossi'] ?? 0) . '</td>';
        $html .= '<td width="' . $widths[3] . '" class="center danger">' . intval($row['bocciati'] ?? 0) . '</td>';
        $html .= '<td width="' . $widths[4] . '" class="center warning">' . intval($row['promossi_con_carenze'] ?? 0) . '</td>';
        $html .= '<td width="' . $widths[5] . '" class="center warning">' . intval($row['promossi_una_carenza'] ?? 0) . '</td>';
        $html .= '<td width="' . $widths[6] . '" class="center warning">' . intval($row['promossi_due_o_piu_carenze'] ?? 0) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
}
if (!$hasClassRows) {
    $html .= '<table width="100%" cellpadding="3"><tbody><tr><td class="center">Nessun tabellone disponibile.</td></tr></tbody></table>';
}

$html .= '<h2>Totali</h2>';
foreach (($summary['totals'] ?? []) as $year => $yearRows) {
    if (empty($yearRows)) {
        continue;
    }
    $html .= '<table width="100%" cellpadding="3"><thead>';
    $html .= '<tr><th colspan="8" align="center" class="group">' . mcte_h(mcte_class_year_label(intval($year))) . '</th></tr>';
    $headers = mcte_headers(intval($year), true);
    $headers[0] = intval($year) <= 2 ? 'Totale' : 'Indirizzo';
    $totalWidths = ['28%', '9%', '9%', '10%', '10%', '12%', '11%', '11%'];
    $html .= '<tr>';
    foreach ($headers as $index => $header) {
        $html .= '<th width="' . $totalWidths[$index] . '" align="center">' . mcte_h($header) . '</th>';
    }
    $html .= '</tr>';
    $html .= '</thead><tbody>';
    foreach ($yearRows as $summaryRow) {
        $html .= '<tr>';
        $html .= '<td width="28%">' . mcte_h($summaryRow['label'] ?? '') . '</td>';
        $html .= '<td width="9%" class="center">' . intval($summaryRow['classes'] ?? 0) . '</td>';
        $html .= '<td width="9%" class="center">' . intval($summaryRow['students'] ?? 0) . '</td>';
        $html .= '<td width="10%" class="center success">' . intval($summaryRow['promossi'] ?? 0) . '</td>';
        $html .= '<td width="10%" class="center danger">' . intval($summaryRow['bocciati'] ?? 0) . '</td>';
        $html .= '<td width="12%" class="center warning">' . intval($summaryRow['promossi_con_carenze'] ?? 0) . '</td>';
        $html .= '<td width="11%" class="center warning">' . intval($summaryRow['promossi_una_carenza'] ?? 0) . '</td>';
        $html .= '<td width="11%" class="center warning">' . intval($summaryRow['promossi_due_o_piu_carenze'] ?? 0) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
}

$averageSubjects = (array)($averagesSummary['subjects'] ?? []);
$html .= '<h2>Medie voti per classe</h2>';
$hasAverageClassRows = false;
foreach (mcte_classes_by_year((array)($averagesSummary['classes'] ?? [])) as $year => $classRows) {
    if (empty($classRows)) {
        continue;
    }
    $hasAverageClassRows = true;
    $headers = mcte_average_headers($averageSubjects, false);
    $widths = ['28%', '9%', '10.5%', '10.5%', '10.5%', '10.5%', '10.5%', '10.5%'];
    $html .= '<table width="100%" cellpadding="3"><thead>';
    $html .= '<tr><th colspan="8" align="center" class="group">' . mcte_h($year > 0 ? mcte_class_year_label(intval($year)) : 'Classi n/d') . '</th></tr>';
    $html .= '<tr>';
    foreach ($headers as $index => $header) {
        $html .= '<th width="' . $widths[$index] . '" align="center">' . mcte_h($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($classRows as $row) {
        $html .= '<tr>';
        $html .= '<td width="' . $widths[0] . '">' . mcte_h($row['label'] ?? '') . '</td>';
        $html .= '<td width="' . $widths[1] . '" class="center">' . intval($row['students'] ?? 0) . '</td>';
        $cellIndex = 2;
        foreach (array_keys($averageSubjects) as $subjectKey) {
            $html .= '<td width="' . $widths[$cellIndex] . '" class="center">' . mcte_h(mcte_average_cell($row[$subjectKey . '_avg'] ?? null)) . '</td>';
            $cellIndex++;
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
}
if (!$hasAverageClassRows) {
    $html .= '<table width="100%" cellpadding="3"><tbody><tr><td class="center">Nessun voto disponibile.</td></tr></tbody></table>';
}

$html .= '<h2>Medie voti per gruppi</h2>';
foreach (($averagesSummary['totals'] ?? []) as $year => $yearRows) {
    if (empty($yearRows)) {
        continue;
    }
    $headers = mcte_average_headers($averageSubjects, true);
    $headers[0] = intval($year) <= 2 ? 'Totale' : 'Indirizzo';
    $widths = ['22%', '7%', '8%', '10.5%', '10.5%', '10.5%', '10.5%', '10.5%', '10.5%'];
    $html .= '<table width="100%" cellpadding="3"><thead>';
    $html .= '<tr><th colspan="9" align="center" class="group">' . mcte_h(mcte_class_year_label(intval($year))) . '</th></tr>';
    $html .= '<tr>';
    foreach ($headers as $index => $header) {
        $html .= '<th width="' . $widths[$index] . '" align="center">' . mcte_h($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($yearRows as $summaryRow) {
        $html .= '<tr>';
        $html .= '<td width="' . $widths[0] . '">' . mcte_h($summaryRow['label'] ?? '') . '</td>';
        $html .= '<td width="' . $widths[1] . '" class="center">' . intval($summaryRow['classes'] ?? 0) . '</td>';
        $html .= '<td width="' . $widths[2] . '" class="center">' . intval($summaryRow['students'] ?? 0) . '</td>';
        $cellIndex = 3;
        foreach (array_keys($averageSubjects) as $subjectKey) {
            $html .= '<td width="' . $widths[$cellIndex] . '" class="center">' . mcte_h(mcte_average_cell($summaryRow[$subjectKey . '_avg'] ?? null)) . '</td>';
            $cellIndex++;
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
}

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Riepilogo esiti tabelloni');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 8);
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output(mcte_filename('pdf'), 'D');

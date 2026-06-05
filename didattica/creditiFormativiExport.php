<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once __DIR__ . '/../common/vendor/autoload.php';
require_once __DIR__ . '/../common/crediti_formativi_mbapp_lib.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
if (!in_array($format, ['pdf', 'xlsx'], true)) {
    $format = 'pdf';
}

$class = trim((string)($_GET['classe'] ?? ''));
$selectedYear = cfm_normalize_year_label($_GET['anno'] ?? cfm_current_year_label());
if ($class === '') {
    http_response_code(400);
    echo 'Parametro classe mancante';
    exit;
}

if ($selectedYear === '' || !cfm_year_enabled($selectedYear)) {
    http_response_code(404);
    echo 'Anno scolastico non disponibile';
    exit;
}

$classes = cfm_classes($selectedYear);
if (!in_array($class, $classes, true)) {
    http_response_code(404);
    echo 'Classe non trovata tra le classi del triennio in MBApp per l\'anno selezionato';
    exit;
}

$rows = cfm_rows($class, $selectedYear);
$filenameBase = 'crediti_formativi_' . cfm_clean_filename($class) . '_' . cfm_clean_filename($selectedYear) . '_' . date('Ymd_His');
$annoScolasticoLabel = $selectedYear;
$titleSuffix = $annoScolasticoLabel !== '' ? ' - A.S. ' . $annoScolasticoLabel : '';

if ($format === 'xlsx') {
    $columns = cfm_all_columns();
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('GestOre')
        ->setTitle('Crediti formativi ' . $class . $titleSuffix);

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr('Crediti ' . $class, 0, 31));
    $sheet->setCellValue('A1', 'Crediti formativi - classe ' . $class . $titleSuffix);
    $sheet->setCellValue('A2', 'Generato il ' . date('d/m/Y H:i') . ' - studenti: ' . count($rows));

    $labels = array_values($columns);
    array_unshift($labels, 'N.');
    $sheet->fromArray($labels, null, 'A4');

    $rowNum = 5;
    $n = 1;
    foreach ($rows as $row) {
        $values = [$n++];
        foreach (array_keys($columns) as $key) {
            $values[] = cfm_row_value($row, $key);
        }
        $sheet->fromArray($values, null, 'A' . $rowNum);
        $rowNum++;
    }

    $lastRow = max(4, $rowNum - 1);
    $lastColIndex = count($labels);
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);

    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->freezePane('A5');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0B4F71');
    $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('667085');
    $sheet->getStyle('A4:' . $lastCol . '4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A4:' . $lastCol . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B79A5');
    $sheet->getStyle('A4:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C7D2DA');
    $sheet->getStyle('A4:' . $lastCol . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    $sheet->getStyle('A4:' . $lastCol . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    if ($lastRow >= 5) {
        $sheet->getStyle('D5:G' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I5:M' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    for ($i = 1; $i <= $lastColIndex; $i++) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    foreach (['H'] as $col) {
        if ($sheet->getColumnDimension($col)) {
            $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth(42);
        }
    }

    for ($r = 5; $r <= $lastRow; $r++) {
        $fill = ($r % 2 === 0) ? 'EEF9F0' : 'FFFBEA';
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
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

function cfm_pdf_table(array $rows, array $columns, array $widths)
{
    $centerKeys = ['rownum', 'esito', 'media', 'assenze', 'interesse', 'IRC', 'ASL_positivo', 'credito', 'credito_precedente', 'integrazione'];
    $html = '<table width="100%" cellpadding="3"><thead><tr>';
    foreach ($columns as $key => $label) {
        $width = isset($widths[$key]) ? ' width="' . cfm_h($widths[$key]) . '"' : '';
        $html .= '<th' . $width . ' style="background-color:#164e63;color:#ffffff;font-weight:bold;text-align:center;">' . cfm_h($label) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    $n = 1;
    foreach ($rows as $row) {
        $rowBg = ($n % 2 === 0) ? '#f3f8fb' : '#fffaf0';
        $html .= '<tr>';
        foreach ($columns as $key => $label) {
            $value = $key === 'rownum' ? (string)$n : cfm_row_value($row, $key);
            $width = isset($widths[$key]) ? ' width="' . cfm_h($widths[$key]) . '"' : '';
            $styleParts = ['background-color:' . $rowBg . ';'];
            if (in_array($key, $centerKeys, true)) {
                $styleParts[] = 'text-align:center;';
            }
            $style = ' style="' . implode('', $styleParts) . '"';
            $html .= '<td' . $width . $style . '>' . nl2br(cfm_h($value)) . '</td>';
        }
        $html .= '</tr>';
        $n++;
    }

    $html .= '</tbody></table>';
    return $html;
}

$mainColumns = cfm_main_columns();

$mainWidths = [
    'rownum' => '3%',
    'cognome' => '8%',
    'nome' => '8%',
    'esito' => '12%',
    'media' => '5%',
    'assenze' => '5%',
    'interesse' => '8%',
    'crediti_formativi' => '21%',
    'IRC' => '4%',
    'ASL_positivo' => '5%',
    'credito' => '4%',
    'credito_precedente' => '7%',
    'integrazione' => '10%',
];

$html = '<style>
    .titlebar { background-color:#0e6f8f; color:#ffffff; font-size:22px; font-weight:bold; letter-spacing:0; padding:8px 10px; }
    h2 { color:#194866; font-size:12px; margin-top:10px; }
    .meta { color:#475467; font-size:8.5px; margin:6px 0 10px 0; }
    table { border-collapse:collapse; font-size:6.2px; }
    th { background-color:#164e63; color:#ffffff; font-weight:bold; text-align:center; }
    td, th { border:1px solid #c8d8df; padding:3px; vertical-align:top; }
</style>';
$html .= '<div class="titlebar">Crediti formativi - classe ' . cfm_h($class) . cfm_h($titleSuffix) . '</div>';
$html .= '<div class="meta">Generato il ' . date('d/m/Y H:i') . ' - studenti: ' . count($rows) . '</div>';
$html .= '<h2>Esiti, credito e crediti formativi</h2>';
$html .= cfm_pdf_table($rows, $mainColumns, $mainWidths);

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Crediti formativi ' . $class . $titleSuffix);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 7);
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output($filenameBase . '.pdf', 'D');
exit;

<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/mastercom/tag_report_lib.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ruoloRichiesto('docente', 'segreteria-didattica', 'admin');

$stampaId = intval($_GET['id'] ?? 0);
$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
if ($format === 'xls') {
    $format = 'xlsx';
}
if (!in_array($format, ['pdf', 'xlsx'], true)) {
    $format = 'pdf';
}

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
$safeName = mastercomTagReportSafeFilename('stampa_tag_' . ($stampa['classi_label'] ?? '') . '_' . date('Ymd_His'));

function ste_h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()->setCreator('GestOre')->setTitle('Stampa TAG');

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B79A5']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $cellStyle = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B7C7D4']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
    ];

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Dettaglio TAG');
    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', 'Stampa TAG - ' . ($stampa['classi_label'] ?? ''));
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('0B4F71');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->fromArray([
        ['Periodo', ($stampa['data_inizio'] ?? '') . ' - ' . ($stampa['data_fine'] ?? ''), '', 'Docente', $stampa['docente_label'] ?? '', 'Righe', count($rows)],
    ], null, 'A2');
    $sheet->getStyle('A2:G2')->getFont()->setBold(true);
    $sheet->fromArray(['Data', 'Tag', 'Docente', 'Materia', 'Classe', 'Argomento', 'Modulo'], null, 'A4');
    $sheet->getStyle('A4:G4')->applyFromArray($headerStyle);

    $rowNum = 5;
    foreach ($rows as $row) {
        $sheet->fromArray([
            mastercomTagReportFormatDateTime($row['data_ora']),
            $row['tag'],
            $row['docente'],
            $row['materia'],
            $row['classe'],
            $row['argomento'],
            $row['modulo'],
        ], null, 'A' . $rowNum);
        $rowNum++;
    }
    $lastRow = max(4, $rowNum - 1);
    $sheet->getStyle('A4:G' . $lastRow)->applyFromArray($cellStyle);
    $sheet->setAutoFilter('A4:G' . $lastRow);
    $sheet->freezePane('A5');
    $sheet->getColumnDimension('A')->setWidth(18);
    $sheet->getColumnDimension('B')->setWidth(24);
    $sheet->getColumnDimension('C')->setWidth(28);
    $sheet->getColumnDimension('D')->setWidth(28);
    $sheet->getColumnDimension('E')->setWidth(22);
    $sheet->getColumnDimension('F')->setWidth(70);
    $sheet->getColumnDimension('G')->setWidth(42);

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Riepilogo');
    $sheet2->fromArray([
        ['Stampa', $stampa['source_filename'] ?? ''],
        ['Classi', $stampa['classi_label'] ?? ''],
        ['Periodo', ($stampa['data_inizio'] ?? '') . ' - ' . ($stampa['data_fine'] ?? '')],
        ['Righe totali', intval($summary['totale'])],
        ['Righe esportate', count($rows)],
    ], null, 'A1');
    $sheet2->getStyle('A1:A5')->getFont()->setBold(true);
    $sheet2->fromArray(['Tag', 'Righe'], null, 'A7');
    $sheet2->getStyle('A7:B7')->applyFromArray($headerStyle);
    $r = 8;
    foreach ($summary['tag'] as $item) {
        $sheet2->fromArray([$item['label'], intval($item['totale'])], null, 'A' . $r);
        $r++;
    }
    $sheet2->getStyle('A7:B' . max(7, $r - 1))->applyFromArray($cellStyle);
    $sheet2->getColumnDimension('A')->setWidth(34);
    $sheet2->getColumnDimension('B')->setWidth(12);

    foreach ($spreadsheet->getAllSheets() as $ws) {
        $highestColumnIndex = Coordinate::columnIndexFromString($ws->getHighestColumn());
        for ($i = 1; $i <= $highestColumnIndex; $i++) {
            $ws->getStyle(Coordinate::stringFromColumnIndex($i) . '1:' . Coordinate::stringFromColumnIndex($i) . $ws->getHighestRow())
                ->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }
    }
    $spreadsheet->setActiveSheetIndex(0);

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $safeName . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

if (!class_exists('TCPDF')) {
    $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf)) {
        require_once $tcpdf;
    }
}

if (!class_exists('TCPDF')) {
    echo 'Errore: libreria TCPDF non trovata';
    exit;
}

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Stampa TAG');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$pdfColumnWidths = ['9%', '12%', '14%', '14%', '10%', '29%', '12%'];

$html = '
<style>
    h1 { color:#0b4f71; font-size:18px; }
    .meta { color:#555; font-size:9px; }
    table { border-collapse: collapse; width: 100%; }
    th { background-color:#0b79a5; color:#ffffff; font-weight:bold; text-align:center; }
    td, th { border: 1px solid #b7c7d4; padding: 3px; font-size: 7px; vertical-align: top; }
    .tag { color:#0b4f71; font-weight:bold; }
</style>
<h1>Stampa TAG - ' . ste_h($stampa['classi_label'] ?? '') . '</h1>
<div class="meta">Periodo: ' . ste_h($stampa['data_inizio'] ?? '') . ' - ' . ste_h($stampa['data_fine'] ?? '') . ' | Righe esportate: ' . count($rows) . ' | File: ' . ste_h($stampa['source_filename'] ?? '') . '</div>
<br>
<table>
<thead>
<tr>
<th width="' . $pdfColumnWidths[0] . '" bgcolor="#0b79a5" color="#ffffff">Data</th>
<th width="' . $pdfColumnWidths[1] . '" bgcolor="#0b79a5" color="#ffffff">Tag</th>
<th width="' . $pdfColumnWidths[2] . '" bgcolor="#0b79a5" color="#ffffff">Docente</th>
<th width="' . $pdfColumnWidths[3] . '" bgcolor="#0b79a5" color="#ffffff">Materia</th>
<th width="' . $pdfColumnWidths[4] . '" bgcolor="#0b79a5" color="#ffffff">Classe</th>
<th width="' . $pdfColumnWidths[5] . '" bgcolor="#0b79a5" color="#ffffff">Argomento</th>
<th width="' . $pdfColumnWidths[6] . '" bgcolor="#0b79a5" color="#ffffff">Modulo</th>
</tr>
</thead>
<tbody>';

if (empty($rows)) {
    $html .= '<tr><td colspan="7" align="center" width="100%">Nessuna riga trovata con i filtri selezionati.</td></tr>';
}

foreach ($rows as $row) {
    $html .= '<tr>'
        . '<td width="' . $pdfColumnWidths[0] . '">' . ste_h(mastercomTagReportFormatDateTime($row['data_ora'])) . '</td>'
        . '<td width="' . $pdfColumnWidths[1] . '" class="tag">' . ste_h($row['tag']) . '</td>'
        . '<td width="' . $pdfColumnWidths[2] . '">' . ste_h($row['docente']) . '</td>'
        . '<td width="' . $pdfColumnWidths[3] . '">' . ste_h($row['materia']) . '</td>'
        . '<td width="' . $pdfColumnWidths[4] . '">' . ste_h($row['classe']) . '</td>'
        . '<td width="' . $pdfColumnWidths[5] . '">' . ste_h($row['argomento']) . '</td>'
        . '<td width="' . $pdfColumnWidths[6] . '">' . ste_h($row['modulo']) . '</td>'
        . '</tr>';
}
$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');
while (ob_get_level()) ob_end_clean();
$pdf->Output($safeName . '.pdf', 'D');
exit;

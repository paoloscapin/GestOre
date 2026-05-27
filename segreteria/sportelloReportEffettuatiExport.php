<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once __DIR__ . '/../common/vendor/autoload.php';
require_once 'sportelloReportEffettuatiLib.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti', 'segreteria-didattica', 'docente');

$format = strtolower(trim((string)($_GET['format'] ?? 'xlsx')));
if ($format === 'xls') $format = 'xlsx';
if (!in_array($format, ['xlsx', 'pdf'], true)) $format = 'xlsx';

$filters = sportelloReportEffettuatiFilters();
$rows = sportelloReportEffettuatiRows($filters);
$title = sportelloReportEffettuatiTitle($filters);
$totals = sportelloReportEffettuatiTotals($rows);

function sre_filename($prefix, $ext)
{
    return $prefix . '_' . date('Ymd_His') . '.' . $ext;
}

function sre_students_label($sportelloId, $field)
{
    $names = [];
    foreach (sportelloReportEffettuatiStudenti($sportelloId) as $studente) {
        if (empty($studente[$field])) continue;
        $names[] = trim((string)$studente['studente_cognome'] . ' ' . (string)$studente['studente_nome'] . ' ' . (string)$studente['studente_classe']);
    }
    return implode("\n", $names);
}

if ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('GestOre')
        ->setTitle('Report sportelli');

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $borderStyle = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
    ];

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Report');
    $sheet->mergeCells('A1:J1');
    $sheet->setCellValue('A1', $title);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('145A32');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $headers = ['Data', 'Ora', 'Materia', 'Docente', 'Email docente', 'Ore', 'Stato', 'Iscritti', 'Presenti', 'Classi/iscritti'];
    $sheet->fromArray($headers, null, 'A3');
    $sheet->getStyle('A3:J3')->applyFromArray($headerStyle);

    $rowNum = 4;
    foreach ($rows as $row) {
        $sportelloId = intval($row['sportello_id']);
        $sheet->fromArray([
            sportelloReportEffettuatiDateIt($row['sportello_data']),
            (string)$row['sportello_ora'],
            (string)$row['materia_nome'],
            trim((string)$row['docente_nome'] . ' ' . (string)$row['docente_cognome']),
            (string)($row['docente_email'] ?? ''),
            (string)$row['sportello_numero_ore'],
            sportelloReportEffettuatiRowState($row),
            intval($row['numero_iscritti']),
            intval($row['numero_presenti']),
            sre_students_label($sportelloId, 'sportello_studente_iscritto'),
        ], null, 'A' . $rowNum);
        $rowNum++;
    }

    $lastDataRow = max(3, $rowNum - 1);
    $sheet->getStyle('A3:J' . $lastDataRow)->applyFromArray($borderStyle);
    $sheet->getStyle('A4:B' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F4:I' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowNum += 1;
    $sheet->fromArray(['Totale sportelli fatti', $totals['sportelli_fatti'], 'Ore fatte', $totals['ore_fatte']], null, 'A' . $rowNum);
    $sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8E6C9');

    $rowNum++;
    $sheet->fromArray(['Totale sportelli saltati', $totals['sportelli_saltati'], 'Ore saltate', $totals['ore_saltate']], null, 'A' . $rowNum);
    $sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCDD2');

    $sheet->freezePane('A4');
    $sheet->setAutoFilter('A3:J' . $lastDataRow);
    $sheet->getColumnDimension('A')->setWidth(13);
    $sheet->getColumnDimension('B')->setWidth(10);
    $sheet->getColumnDimension('C')->setWidth(34);
    $sheet->getColumnDimension('D')->setWidth(28);
    $sheet->getColumnDimension('E')->setWidth(34);
    $sheet->getColumnDimension('F')->setWidth(8);
    $sheet->getColumnDimension('G')->setWidth(14);
    $sheet->getColumnDimension('H')->setWidth(10);
    $sheet->getColumnDimension('I')->setWidth(10);
    $sheet->getColumnDimension('J')->setWidth(38);

    $summary = $spreadsheet->createSheet();
    $summary->setTitle('Riepilogo');
    $summary->fromArray([
        ['Report', $title],
        ['Sportelli fatti', $totals['sportelli_fatti']],
        ['Ore fatte', $totals['ore_fatte']],
        ['Sportelli saltati', $totals['sportelli_saltati']],
        ['Ore saltate', $totals['ore_saltate']],
    ], null, 'A1');
    $summary->getStyle('A1:A5')->getFont()->setBold(true);
    $summary->getColumnDimension('A')->setAutoSize(true);
    $summary->getColumnDimension('B')->setAutoSize(true);

    foreach ($spreadsheet->getAllSheets() as $ws) {
        $highestColumnIndex = Coordinate::columnIndexFromString($ws->getHighestColumn());
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            if ($ws->getColumnDimension($col)->getWidth() < 0) {
                $ws->getColumnDimension($col)->setAutoSize(true);
            }
        }
    }
    $spreadsheet->setActiveSheetIndex(0);

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . sre_filename('report_sportelli', 'xlsx') . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if (!class_exists('TCPDF')) {
    $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf)) require_once $tcpdf;
}
if (!class_exists('TCPDF')) {
    http_response_code(500);
    echo 'TCPDF non trovato';
    exit;
}

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle($title);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 8);

$html = '<h2 style="text-align:center;color:#145a32;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
$html .= '<table border="1" cellpadding="4">
<thead>
<tr style="background-color:#2e7d32;color:#ffffff;font-weight:bold;">
<th width="7%" align="center">Data</th><th width="5%" align="center">Ora</th><th width="19%" align="center">Materia</th><th width="18%" align="center">Docente</th><th width="4%" align="center">Ore</th><th width="10%" align="center">Stato</th><th width="6%" align="center">Iscritti</th><th width="6%" align="center">Presenti</th><th width="25%" align="center">Classi/iscritti</th>
</tr>
</thead><tbody>';
foreach ($rows as $row) {
    $students = sre_students_label(intval($row['sportello_id']), 'sportello_studente_iscritto');
    $html .= '<tr>
        <td width="7%" align="center">' . htmlspecialchars(sportelloReportEffettuatiDateIt($row['sportello_data']), ENT_QUOTES, 'UTF-8') . '</td>
        <td width="5%" align="center">' . htmlspecialchars((string)$row['sportello_ora'], ENT_QUOTES, 'UTF-8') . '</td>
        <td width="19%">' . htmlspecialchars((string)$row['materia_nome'], ENT_QUOTES, 'UTF-8') . '</td>
        <td width="18%">' . htmlspecialchars(trim((string)$row['docente_nome'] . ' ' . (string)$row['docente_cognome']), ENT_QUOTES, 'UTF-8') . '</td>
        <td width="4%" align="center">' . htmlspecialchars((string)$row['sportello_numero_ore'], ENT_QUOTES, 'UTF-8') . '</td>
        <td width="10%" align="center">' . htmlspecialchars(sportelloReportEffettuatiRowState($row), ENT_QUOTES, 'UTF-8') . '</td>
        <td width="6%" align="center">' . intval($row['numero_iscritti']) . '</td>
        <td width="6%" align="center">' . intval($row['numero_presenti']) . '</td>
        <td width="25%">' . nl2br(htmlspecialchars($students, ENT_QUOTES, 'UTF-8')) . '</td>
    </tr>';
}
$html .= '</tbody></table>';
$html .= '<br><table border="1" cellpadding="4">
<tr style="background-color:#c8e6c9;"><td><b>Totale sportelli fatti</b></td><td align="center">' . $totals['sportelli_fatti'] . '</td><td><b>Ore fatte</b></td><td align="center">' . $totals['ore_fatte'] . '</td></tr>
<tr style="background-color:#ffcdd2;"><td><b>Totale sportelli saltati</b></td><td align="center">' . $totals['sportelli_saltati'] . '</td><td><b>Ore saltate</b></td><td align="center">' . $totals['ore_saltate'] . '</td></tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
while (ob_get_level()) ob_end_clean();
$pdf->Output(sre_filename('report_sportelli', 'pdf'), 'D');
exit;

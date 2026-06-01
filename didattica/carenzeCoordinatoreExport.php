<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once __DIR__ . '/../common/vendor/autoload.php';
require_once __DIR__ . '/carenzeCoordinatoreLib.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
if (!in_array($format, ['pdf', 'xlsx'], true)) {
    $format = 'pdf';
}

$vistaDocente = intval($_GET['vista_docente'] ?? 0) === 1;
$docenteId = intval($_GET['docente_id'] ?? 0);
$anniFiltroId = intval($_GET['anni_id'] ?? 0);
$classeFiltroId = intval($_GET['classe_id'] ?? 0);
$materiaFiltroId = intval($_GET['materia_id'] ?? 0);
$studenteFiltroId = intval($_GET['studente_id'] ?? 0);
$annoClasseFiltro = intval($_GET['anno'] ?? 0);

if ($docenteId <= 0 || !$vistaDocente) {
    $docenteId = carenzeCoordCurrentDocenteId();
}

$annoCorrente = intval($__anno_scolastico_corrente_id);
$classLabel = carenzeCoordClassLabel($docenteId, $annoCorrente);
$rows = carenzeCoordRows($docenteId, $annoCorrente, $anniFiltroId, $classeFiltroId, $materiaFiltroId, $studenteFiltroId, $annoClasseFiltro);

if ($classLabel === '') {
    http_response_code(403);
    echo 'Non risulti coordinatore di una classe nell\'anno scolastico corrente.';
    exit;
}

$columns = [
    'Studente',
    'Classe attuale',
    'Classe carenza',
    'Materia',
    'Anno carenza',
    'Docente',
    'Stato',
    'Tentativo',
    'Dettaglio',
    'Primo tentativo',
    'Secondo tentativo',
];

function cce_row_values(array $row)
{
    $esito = $row['esito'];
    return [
        trim((string)$row['stud_cognome'] . ' ' . (string)$row['stud_nome']),
        (string)($row['classe_attuale'] ?? ''),
        (string)($row['classe_carenza'] ?? ''),
        (string)($row['materia'] ?? ''),
        (string)($row['anno_scolastico'] ?? ''),
        trim((string)($row['doc_cognome'] ?? '') . ' ' . (string)($row['doc_nome'] ?? '')),
        (string)($esito['stato'] ?? ''),
        (string)($esito['tentativo'] ?? ''),
        (string)($esito['dettaglio'] ?? ''),
        (string)($esito['primo'] ?? ''),
        (string)($esito['secondo'] ?? ''),
    ];
}

function cce_filename($ext)
{
    return 'carenze_classe_coordinata_' . date('Ymd_His') . '.' . $ext;
}

if ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Carenze classe');
    $sheet->setCellValue('A1', 'Carenze classe coordinata');
    $sheet->setCellValue('A2', 'Classe: ' . $classLabel);
    $sheet->fromArray($columns, null, 'A4');

    $r = 5;
    foreach ($rows as $row) {
        $sheet->fromArray(cce_row_values($row), null, 'A' . $r);
        $r++;
    }

    $lastCol = 'K';
    $lastRow = max(4, $r - 1);
    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0B4F71');
    $sheet->getStyle('A4:' . $lastCol . '4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A4:' . $lastCol . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B79A5');
    $sheet->getStyle('A4:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C7D2DA');
    $sheet->getStyle('A4:' . $lastCol . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

    foreach (range('A', $lastCol) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . cce_filename('xlsx') . '"');
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
    .meta { color:#667085; font-size:9px; margin-bottom:8px; }
    table { border-collapse:collapse; font-size:7.5px; }
    th { background-color:#0b79a5; color:#ffffff; font-weight:bold; text-align:center; }
    td, th { border:1px solid #c7d2da; padding:4px; }
    .success { background-color:#d9f0d3; }
    .warning { background-color:#fff1b8; }
    .danger { background-color:#ffd6d6; }
    .info { background-color:#d9edf7; }
</style>';
$html .= '<h1>Carenze classe coordinata</h1>';
$html .= '<div class="meta">Classe: ' . carenzeCoordH($classLabel) . ' - righe: ' . count($rows) . ' - generato il ' . date('d/m/Y H:i') . '</div>';
$html .= '<table width="100%" cellpadding="3"><thead><tr>';
$widths = ['17%', '8%', '20%', '9%', '16%', '9%', '8%', '13%'];
$headers = ['Studente', 'Classe', 'Materia', 'A.S.', 'Docente', 'Stato', 'Tent.', 'Dettaglio'];
foreach ($headers as $i => $header) {
    $html .= '<th width="' . $widths[$i] . '">' . carenzeCoordH($header) . '</th>';
}
$html .= '</tr></thead><tbody>';

foreach ($rows as $row) {
    $esito = $row['esito'];
    $classe = (string)($row['classe_attuale'] ?? '');
    if (($row['classe_carenza'] ?? '') !== '' && $row['classe_carenza'] !== $row['classe_attuale']) {
        $classe .= ' (car. ' . (string)$row['classe_carenza'] . ')';
    }
    $html .= '<tr class="' . carenzeCoordH($esito['classe_css']) . '">';
    $html .= '<td width="' . $widths[0] . '">' . carenzeCoordH(trim($row['stud_cognome'] . ' ' . $row['stud_nome'])) . '</td>';
    $html .= '<td width="' . $widths[1] . '">' . carenzeCoordH($classe) . '</td>';
    $html .= '<td width="' . $widths[2] . '">' . carenzeCoordH($row['materia']) . '</td>';
    $html .= '<td width="' . $widths[3] . '">' . carenzeCoordH($row['anno_scolastico']) . '</td>';
    $html .= '<td width="' . $widths[4] . '">' . carenzeCoordH(trim(($row['doc_cognome'] ?? '') . ' ' . ($row['doc_nome'] ?? ''))) . '</td>';
    $html .= '<td width="' . $widths[5] . '">' . carenzeCoordH($esito['stato']) . '</td>';
    $html .= '<td width="' . $widths[6] . '">' . carenzeCoordH($esito['tentativo']) . '</td>';
    $html .= '<td width="' . $widths[7] . '">' . carenzeCoordH($esito['dettaglio']) . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Carenze classe coordinata');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 8);
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output(cce_filename('pdf'), 'D');

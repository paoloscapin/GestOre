<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

ruoloRichiesto('dirigente', 'segreteria-ata');


/* =========================================================
   FUNZIONI
   ========================================================= */

function expandDateRangeIso($from, $to)
{
    $out = [];
    if (!$from) return $out;
    if (!$to) $to = $from;

    $start = DateTime::createFromFormat('Y-m-d', $from);
    $end   = DateTime::createFromFormat('Y-m-d', $to);

    if (!$start || !$end || $end < $start) return $out;

    $cur = clone $start;
    while ($cur <= $end) {
        $out[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }
    return $out;
}

function fmtDate($iso)
{
    return date('d/m', strtotime($iso));
}

function ferieDashboardResolvePeriod(string $finestra, string $dateFrom, string $dateTo): ?array
{
    if ($finestra === 'ORDINARIE') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            return null;
        }
        if ($dateTo < $dateFrom) {
            return null;
        }

        return [
            'codice' => 'ORDINARIE',
            'data_inizio' => $dateFrom,
            'data_fine' => $dateTo
        ];
    }

    return dbGetFirst("
        SELECT data_inizio, data_fine
        FROM permesso_ata_ferie_finestra
        WHERE UPPER(TRIM(codice)) = " . dbQ($finestra) . "
        LIMIT 1
    ");
}

/* =========================================================
   PARAMETRI
   ========================================================= */

$finestra = strtoupper(trim((string)($_GET['finestra'] ?? 'ESTIVE')));
$mode = strtoupper(trim((string)($_GET['mode'] ?? 'APPROVATI_E_RICHIESTI')));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

/* =========================================================
   FINESTRA
   ========================================================= */

$win = ferieDashboardResolvePeriod($finestra, $dateFrom, $dateTo);

if (!$win) die("Finestra non trovata");

$days = expandDateRangeIso($win['data_inizio'], $win['data_fine']);
$daySet = array_fill_keys($days, true);

/* =========================================================
   DATI
   ========================================================= */

$rows = dbGetAll("
SELECT
    rr.data_dal, rr.data_al, rr.dettagli_json,
    p.username, p.cognome, p.nome,
    pr.codice AS profilo,
    u.nome AS ufficio
FROM permesso_ata_richiesta req
JOIN permesso_ata_tipo t ON t.id = req.permesso_ata_tipo_id
JOIN permesso_ata_richiesta_riga rr ON rr.permesso_ata_richiesta_id = req.id
JOIN personale_ata p ON p.id = req.personale_ata_id
LEFT JOIN personale_ata_profili pr ON pr.id = p.id_profilo
LEFT JOIN personale_ata_assegnazioni pa ON pa.username = p.username AND pa.attiva = 1
LEFT JOIN personale_ata_uffici u ON u.id = pa.id_ufficio
WHERE t.codice = 'FERIE'
  AND UPPER(TRIM(req.ferie_sottotipo)) = " . dbQ($finestra) . "
");

if (!is_array($rows)) $rows = [];

/* =========================================================
   ELABORAZIONE
   ========================================================= */

$heatmap = [];
$people = [];

foreach ($rows as $r) {

    $det = json_decode($r['dettagli_json'] ?? '{}', true);
    $stato = strtoupper(trim((string)($det['stato_giorno'] ?? 'RICHIESTO')));

    $ok = false;
    if ($mode === 'APPROVATI_ONLY') $ok = ($stato === 'APPROVATO');
    elseif ($mode === 'RICHIESTI_ONLY') $ok = ($stato === 'RICHIESTO');
    else $ok = in_array($stato, ['APPROVATO', 'RICHIESTO']);

    if (!$ok) continue;

    $ufficio = $r['ufficio'] ?: 'Senza ufficio';

    if (!isset($heatmap[$ufficio])) {
        $heatmap[$ufficio] = array_fill_keys($days, 0);
    }

    $nome = trim($r['cognome'] . ' ' . $r['nome']);
    $key = $r['username'] ?: md5($nome);

    if (!isset($people[$key])) {
        $people[$key] = [
            'nome' => $nome,
            'profilo' => $r['profilo'],
            'ufficio' => $ufficio,
            'days' => array_fill_keys($days, '')
        ];
    }

    foreach (expandDateRangeIso($r['data_dal'], $r['data_al']) as $d) {
        if (!isset($daySet[$d])) continue;

        $heatmap[$ufficio][$d]++;
        $people[$key]['days'][$d] = 'X';
    }
}

/* =========================================================
   EXCEL
   ========================================================= */

$spreadsheet = new Spreadsheet();

/* =======================
   FOGLIO 1: HEATMAP
   ======================= */

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Heatmap uffici');

$col = 2;
foreach ($days as $d) {
    $cell = Coordinate::stringFromColumnIndex($col) . '1';
    $sheet->setCellValue($cell, fmtDate($d));
    $col++;
}

$lastCol1 = Coordinate::stringFromColumnIndex(count($days) + 1);

$sheet->getStyle('A1:' . $lastCol1 . '1')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF1F4E78'],
    ],
]);

$row = 2;
foreach ($heatmap as $uff => $vals) {

    $sheet->setCellValue('A' . $row, $uff);

    $col = 2;
    foreach ($vals as $v) {
        $cell = Coordinate::stringFromColumnIndex($col) . $row;
        $sheet->setCellValue($cell, $v);
        if ($v > 0) {
            $color = $v > 5 ? 'FF305496' : ($v > 2 ? 'FF5B9BD5' : 'FFBDD7EE');
            $sheet->getStyle($cell)
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($color);
        }

        $col++;
    }

    $row++;
}

$sheet->getStyle('B2:' . $lastCol1 . $sheet->getHighestRow())
    ->getAlignment()
    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

$lastCol1 = Coordinate::stringFromColumnIndex(count($days) + 1);
$lastRow1 = $sheet->getHighestRow();

$sheet->getStyle('A1:' . $lastCol1 . $lastRow1)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF9CA3AF'],
        ],
    ],
]);

/* =======================
   FOGLIO 2: PERSONE
   ======================= */

$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Persone');

$sheet2->setCellValue('A1', 'Nome');
$sheet2->setCellValue('B1', 'Profilo');
$sheet2->setCellValue('C1', 'Ufficio');

$col = 4;
foreach ($days as $d) {
    $cell = Coordinate::stringFromColumnIndex($col) . '1';
    $sheet2->setCellValue($cell, fmtDate($d));
    $col++;
}

$lastCol2 = Coordinate::stringFromColumnIndex(count($days) + 3);

$sheet2->getStyle('A1:' . $lastCol2 . '1')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF1F4E78'],
    ],
]);

$row = 2;
foreach ($people as $p) {

    $sheet2->setCellValue("A$row", $p['nome']);
    $sheet2->setCellValue("B$row", $p['profilo']);
    $sheet2->setCellValue("C$row", $p['ufficio']);

    $col = 4;
    foreach ($days as $d) {

        if ($p['days'][$d] === 'X') {
            $cell = Coordinate::stringFromColumnIndex($col) . $row;
            $sheet2->setCellValue($cell, 'X');

            $sheet2->getStyle($cell)
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFC6EFCE');
        }

        $col++;
    }

    $row++;
}

// HEATMAP
$sheet->getColumnDimension('A')->setAutoSize(true);
for ($i = 2; $i <= count($days) + 1; $i++) {
    $colLetter = Coordinate::stringFromColumnIndex($i);
    $sheet->getColumnDimension($colLetter)->setWidth(6);
}

// PERSONE
$sheet2->getColumnDimension('A')->setAutoSize(true);
$sheet2->getColumnDimension('B')->setAutoSize(true);
$sheet2->getColumnDimension('C')->setAutoSize(true);

for ($i = 4; $i <= count($days) + 3; $i++) {
    $colLetter = Coordinate::stringFromColumnIndex($i);
    $sheet2->getColumnDimension($colLetter)->setWidth(6);
}

$sheet2->getStyle('D2:' . $lastCol2 . $sheet2->getHighestRow())
    ->getAlignment()
    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $lastCol2 = Coordinate::stringFromColumnIndex(count($days) + 3);
$lastRow2 = $sheet2->getHighestRow();

$sheet2->getStyle('A1:' . $lastCol2 . $lastRow2)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF9CA3AF'],
        ],
    ],
]);

$sheet->getStyle('B1:' . $lastCol1 . '1')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$sheet2->getStyle('D1:' . $lastCol2 . '1')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

/* =========================================================
   DOWNLOAD
   ========================================================= */

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="ferie_' . strtolower($finestra) . '_' . $win['data_inizio'] . '_' . $win['data_fine'] . '.xlsx"');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

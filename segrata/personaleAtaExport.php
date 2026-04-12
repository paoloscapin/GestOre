<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

ruoloRichiesto('dirigente', 'segreteria-ata');

function paEsc($s)
{
    if (isset($GLOBALS['__conn']) && $GLOBALS['__conn']) {
        return mysqli_real_escape_string($GLOBALS['__conn'], $s);
    }
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
        return mysqli_real_escape_string($GLOBALS['conn'], $s);
    }
    return addslashes($s);
}

$soloAttivi = isset($_GET["soloAttivi"]) ? intval($_GET["soloAttivi"]) : 1;
$search = trim($_GET["search"] ?? '');
$idUfficio = isset($_GET["id_ufficio"]) && $_GET["id_ufficio"] !== '' ? intval($_GET["id_ufficio"]) : 0;
$idProfilo = isset($_GET["id_profilo"]) && $_GET["id_profilo"] !== '' ? intval($_GET["id_profilo"]) : 0;
$tipoContratto = trim($_GET["tipo_contratto"] ?? '');

$orderBy  = $_GET['order_by'] ?? 'cognome';
$orderDir = strtoupper($_GET['order_dir'] ?? 'ASC');
if (!in_array($orderDir, ['ASC', 'DESC'], true)) $orderDir = 'ASC';

$allowedOrder = [
    'cognome' => 'p.cognome',
    'nome' => 'p.nome',
    'email' => 'p.email',
    'tipo_contratto' => 'p.tipo_contratto',
    'profilo' => 'pr.nome',
    'ufficio' => 'u.nome',
    'attivo' => 'p.attivo'
];

$orderSql = $allowedOrder[$orderBy] ?? 'p.cognome';

$where = [];
if ($soloAttivi == 1) $where[] = "p.attivo = 1";

if ($search !== '') {
    $s = paEsc($search);
    $where[] = "(
        p.cognome LIKE '%$s%' OR
        p.nome LIKE '%$s%' OR
        p.email LIKE '%$s%' OR
        p.username LIKE '%$s%' OR
        p.matricola LIKE '%$s%' OR
        p.codice_fiscale LIKE '%$s%' OR
        p.tipo_contratto LIKE '%$s%' OR
        pr.nome LIKE '%$s%' OR
        u.nome LIKE '%$s%'
    )";
}

if ($idUfficio > 0) $where[] = "curr.id_ufficio = $idUfficio";
if ($idProfilo > 0) $where[] = "p.id_profilo = $idProfilo";
if ($tipoContratto !== '') $where[] = "p.tipo_contratto = '" . paEsc($tipoContratto) . "'";

$sqlWhere = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

$query = "
SELECT
    p.id,
    p.cognome,
    p.nome,
    p.email,
    p.username,
    p.matricola,
    p.tipo_contratto,
    p.codice_fiscale,
    p.id_profilo,
    p.attivo,
    pr.codice AS profilo_codice,
    pr.nome AS profilo_nome,
    pr.descrizione AS profilo_descrizione,
    pr.richiede_ufficio,
    curr.id AS assegnazione_corrente_id,
    curr.id_ufficio AS assegnazione_corrente_id_ufficio,
    curr.data_inizio AS assegnazione_corrente_data_inizio,
    curr.data_fine AS assegnazione_corrente_data_fine,
    curr.attiva AS assegnazione_corrente_attiva,
    u.nome AS ufficio_corrente_nome,
    u.codice AS ufficio_corrente_codice
FROM personale_ata p
LEFT JOIN personale_ata_profili pr ON pr.id = p.id_profilo
LEFT JOIN (
    SELECT a1.*
    FROM personale_ata_assegnazioni a1
    INNER JOIN (
        SELECT username, MAX(id) AS max_id
        FROM personale_ata_assegnazioni
        WHERE attiva = 1 OR data_fine IS NULL
        GROUP BY username
    ) a2 ON a1.username = a2.username AND a1.id = a2.max_id
) curr ON curr.username = p.username
LEFT JOIN personale_ata_uffici u ON u.id = curr.id_ufficio
$sqlWhere
ORDER BY $orderSql $orderDir, p.cognome, p.nome
";

$rows = dbGetAll($query);
if (!is_array($rows)) $rows = [];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Personale ATA');

/* =========================
   HEADER
   ========================= */

$headers = [
    'ID',
    'Cognome',
    'Nome',
    'Email',
    'Matricola',
    'Tipo contratto',
    'Codice fiscale',
    'Profilo codice',
    'Profilo nome',
    'Ufficio'
];

$col = 1;
foreach ($headers as $h) {
    $cell = Coordinate::stringFromColumnIndex($col) . '1';
    $sheet->setCellValue($cell, $h);
    $col++;
}

/* =========================
   STILE HEADER
   ========================= */

$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

$sheet->getStyle("A1:$lastCol" . "1")->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF1F4E78'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER
    ]
]);

/* =========================
   DATI
   ========================= */

$rowNum = 2;

foreach ($rows as $r) {

    $cognome = isset($r['cognome']) ? mb_convert_case($r['cognome'], MB_CASE_TITLE, "UTF-8") : '';
    $nome    = isset($r['nome']) ? mb_convert_case($r['nome'], MB_CASE_TITLE, "UTF-8") : '';

    $values = [
        $r['id'] ?? '',
        $cognome,
        $nome,
        $r['email'] ?? '',
        $r['matricola'] ?? '',
        $r['tipo_contratto'] ?? '',
        $r['codice_fiscale'] ?? '',
        $r['profilo_codice'] ?? '',
        $r['profilo_nome'] ?? '',
        $r['ufficio_corrente_nome'] ?? ''
    ];

    $col = 1;
    foreach ($values as $value) {
        $cell = Coordinate::stringFromColumnIndex($col) . $rowNum;
        $sheet->setCellValue($cell, $value);
        $col++;
    }
    $rowNum++;
}

/* =========================
   AUTO WIDTH
   ========================= */

foreach (range('A', $lastCol) as $colLetter) {
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

/* =========================
   BORDI (griglia visibile)
   ========================= */

$lastRow = $sheet->getHighestRow();

$sheet->getStyle("A1:$lastCol$lastRow")->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF9CA3AF'],
        ],
    ],
]);

/* =========================
   DOWNLOAD
   ========================= */

$filename = 'personale_ata_' . date('d-m-Y_H-i-s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

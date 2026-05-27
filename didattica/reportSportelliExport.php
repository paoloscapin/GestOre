<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once __DIR__ . '/../common/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ruoloRichiesto('admin', 'segreteria-didattica', 'segreteria-docenti', 'dirigente');

$anno_id = intval($_GET['anno_id'] ?? $__anno_scolastico_corrente_id);
if ($anno_id <= 0) {
    $anno_id = intval($__anno_scolastico_corrente_id);
}

$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
if ($format === 'xls') $format = 'xlsx';
if (!in_array($format, ['pdf', 'xlsx'], true)) $format = 'pdf';

$nome_anno_scolastico = (string)dbGetValue("SELECT anno FROM anno_scolastico WHERE id=" . dbI($anno_id));
if ($nome_anno_scolastico === '') {
    $nome_anno_scolastico = (string)$anno_id;
}

function rs_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rs_date_it($value)
{
    if (!$value) return '';
    try {
        return (new DateTime((string)$value))->format('d/m/Y');
    } catch (Exception $e) {
        return (string)$value;
    }
}

function rs_filename($prefix, $anno, $ext)
{
    $safeAnno = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$anno);
    return $prefix . '_' . trim($safeAnno, '_') . '_' . date('Ymd_His') . '.' . $ext;
}

$studenti = dbGetAll("
    SELECT
        s.id,
        s.cognome,
        s.nome,
        c.classe AS classe
    FROM studente s
    INNER JOIN studente_frequenta sf ON sf.id_studente = s.id
    INNER JOIN classi c ON sf.id_classe = c.id
    WHERE sf.id_anno_scolastico = " . dbI($anno_id) . "
    ORDER BY c.classe ASC, s.cognome ASC, s.nome ASC
") ?: [];

$dettaglio = [];
$studentGroups = [];
$senzaSportelli = [];
$oreStudenti = [];
$totaleOreFrequentate = 0;

foreach ($studenti as $studente) {
    $studenteId = intval($studente['id']);
    $sportelli = dbGetAll("
        SELECT
            sportello_studente.argomento AS argomento_studente,
            sportello_studente.presente AS studente_presente,
            sportello_studente.iscritto AS studente_iscritto,
            materia.nome AS nome_materia,
            docente.cognome AS cognome_docente,
            docente.nome AS nome_docente,
            docente.email AS email_docente,
            sportello.data,
            sportello.numero_ore
        FROM sportello_studente
        INNER JOIN sportello ON sportello_studente.sportello_id = sportello.id
        INNER JOIN docente ON sportello.docente_id = docente.id
        INNER JOIN materia ON sportello.materia_id = materia.id
        WHERE sportello_studente.studente_id = " . dbI($studenteId) . "
          AND sportello.anno_scolastico_id = " . dbI($anno_id) . "
          AND (sportello_studente.presente = 1 OR sportello_studente.iscritto = 1)
        ORDER BY materia.nome ASC, sportello.data ASC
    ") ?: [];

    if (count($sportelli) === 0) {
        $senzaSportelli[] = $studente;
        continue;
    }

    $studentGroups[$studenteId] = [
        'classe' => $studente['classe'],
        'studente' => trim((string)$studente['cognome'] . ' ' . (string)$studente['nome']),
        'rows' => [],
        'totale_ore' => 0,
    ];

    foreach ($sportelli as $sportello) {
        $ore = floatval($sportello['numero_ore'] ?? 0);
        $presente = intval($sportello['studente_presente'] ?? 0) === 1;
        if ($presente) {
            $totaleOreFrequentate += $ore;
            if (!isset($oreStudenti[$studenteId])) $oreStudenti[$studenteId] = 0;
            $oreStudenti[$studenteId] += $ore;
        }

        $detailRow = [
            'classe' => $studente['classe'],
            'studente' => trim((string)$studente['cognome'] . ' ' . (string)$studente['nome']),
            'materia' => $sportello['nome_materia'],
            'data' => rs_date_it($sportello['data']),
            'docente' => trim((string)$sportello['nome_docente'] . ' ' . (string)$sportello['cognome_docente']),
            'email_docente' => $sportello['email_docente'] ?? '',
            'argomento' => $sportello['argomento_studente'] ?? '',
            'stato' => $presente ? 'Presente' : 'Assente',
            'ore' => $ore,
        ];
        $dettaglio[] = $detailRow;
        $studentGroups[$studenteId]['rows'][] = $detailRow;
        if ($presente) {
            $studentGroups[$studenteId]['totale_ore'] += $ore;
        }
    }
}

$riepilogoMaterie = dbGetAll("
    SELECT
        materia.nome AS materia,
        COALESCE(SUM(sportello.numero_ore), 0) AS ore
    FROM materia
    LEFT JOIN sportello
        ON sportello.materia_id = materia.id
       AND sportello.anno_scolastico_id = " . dbI($anno_id) . "
       AND NOT sportello.cancellato
    GROUP BY materia.id, materia.nome
    ORDER BY materia.nome ASC
") ?: [];

$totaleOreProgrammate = 0;
foreach ($riepilogoMaterie as $materia) {
    $totaleOreProgrammate += floatval($materia['ore'] ?? 0);
}

if ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('GestOre')
        ->setTitle('Report sportelli ' . $nome_anno_scolastico);

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $cellStyle = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
    ];

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Dettaglio studenti');
    $sheet->mergeCells('A1:I1');
    $sheet->setCellValue('A1', 'Report sportelli anno scolastico ' . $nome_anno_scolastico);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->fromArray(['Classe', 'Studente', 'Materia', 'Data', 'Docente', 'Email docente', 'Argomento', 'Stato', 'Ore'], null, 'A3');
    $sheet->getStyle('A3:I3')->applyFromArray($headerStyle);

    $rowNum = 4;
    foreach ($dettaglio as $row) {
        $sheet->fromArray([$row['classe'], $row['studente'], $row['materia'], $row['data'], $row['docente'], $row['email_docente'], $row['argomento'], $row['stato'], $row['ore']], null, 'A' . $rowNum);
        if ($row['stato'] === 'Assente') {
            $sheet->getStyle('A' . $rowNum . ':I' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2DEDE');
        }
        $rowNum++;
    }
    $lastRow = max(3, $rowNum - 1);
    $sheet->getStyle('A3:I' . $lastRow)->applyFromArray($cellStyle);
    $sheet->getStyle('A4:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D4:D' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H4:I' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->setAutoFilter('A3:I' . $lastRow);
    $sheet->freezePane('A4');

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Senza sportelli');
    $sheet2->fromArray(['Classe', 'Studente'], null, 'A1');
    $sheet2->getStyle('A1:B1')->applyFromArray($headerStyle);
    $rowNum = 2;
    foreach ($senzaSportelli as $studente) {
        $sheet2->fromArray([$studente['classe'], trim((string)$studente['cognome'] . ' ' . (string)$studente['nome'])], null, 'A' . $rowNum);
        $rowNum++;
    }
    $sheet2->getStyle('A1:B' . max(1, $rowNum - 1))->applyFromArray($cellStyle);

    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle('Riepilogo');
    $sheet3->fromArray([
        ['Anno scolastico', $nome_anno_scolastico],
        ['Ore frequentate dagli studenti', $totaleOreFrequentate],
        ['Ore sportelli programmate', $totaleOreProgrammate],
    ], null, 'A1');
    $sheet3->getStyle('A1:A3')->getFont()->setBold(true);
    $sheet3->fromArray(['Materia', 'Ore programmate'], null, 'A5');
    $sheet3->getStyle('A5:B5')->applyFromArray($headerStyle);
    $rowNum = 6;
    foreach ($riepilogoMaterie as $materia) {
        $sheet3->fromArray([$materia['materia'], floatval($materia['ore'])], null, 'A' . $rowNum);
        $rowNum++;
    }
    $sheet3->getStyle('A5:B' . max(5, $rowNum - 1))->applyFromArray($cellStyle);

    foreach ($spreadsheet->getAllSheets() as $ws) {
        $highestColumnIndex = Coordinate::columnIndexFromString($ws->getHighestColumn());
        for ($i = 1; $i <= $highestColumnIndex; $i++) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
    }
    $spreadsheet->setActiveSheetIndex(0);

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . rs_filename('report_sportelli', $nome_anno_scolastico, 'xlsx') . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
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

$html = '<h1 style="text-align:center;">Report Sportelli anno scolastico ' . rs_h($nome_anno_scolastico) . '</h1>';
$html .= '<h3>Statistiche</h3>';
$html .= '<p><strong>Ore complessive frequentate dagli studenti:</strong> ' . rs_h($totaleOreFrequentate) . '</p>';
$html .= '<table border="1" cellpadding="4"><thead><tr style="background-color:#2e7d32;color:#fff;"><th width="85%">Materia</th><th width="15%" align="center">Ore</th></tr></thead><tbody>';
foreach ($riepilogoMaterie as $materia) {
    $html .= '<tr><td width="85%">' . rs_h($materia['materia']) . '</td><td width="15%" align="center">' . rs_h($materia['ore']) . '</td></tr>';
}
$html .= '<tr><td width="85%" align="right"><strong>Totale Ore Sportelli</strong></td><td width="15%" align="center"><strong>' . rs_h($totaleOreProgrammate) . '</strong></td></tr>';
$html .= '</tbody></table>';

$classeCorrente = '';
foreach ($studentGroups as $group) {
    if ($classeCorrente !== $group['classe']) {
        $classeCorrente = $group['classe'];
        $html .= '<h2 style="page-break-before:always;text-align:center;">' . rs_h($classeCorrente) . '</h2>';
    }
    $html .= '<h4 style="background-color:#dff0d8;">' . rs_h($group['studente']) . ' (' . rs_h($group['classe']) . ')</h4>';
    $html .= '<table border="1" cellpadding="4"><thead><tr style="background-color:#2e7d32;color:#fff;"><th width="20%">Materia</th><th width="15%">Data</th><th width="20%">Docente</th><th width="27%">Argomento</th><th width="10%">Stato</th><th width="8%">Ore</th></tr></thead><tbody>';
    foreach ($group['rows'] as $row) {
        $style = $row['stato'] === 'Assente' ? ' style="background-color:#f2dede;"' : '';
        $html .= '<tr' . $style . '><td width="20%">' . rs_h($row['materia']) . '</td><td width="15%" align="center">' . rs_h($row['data']) . '</td><td width="20%">' . rs_h($row['docente']) . '</td><td width="27%">' . rs_h($row['argomento']) . '</td><td width="10%" align="center">' . rs_h($row['stato']) . '</td><td width="8%" align="center">' . rs_h($row['ore']) . '</td></tr>';
    }
    $html .= '<tr><td width="92%" colspan="5" align="right"><strong>Totale ore sportelli frequentate</strong></td><td width="8%" align="center"><strong>' . rs_h($group['totale_ore']) . '</strong></td></tr>';
    $html .= '</tbody></table>';
}

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Report sportelli ' . $nome_anno_scolastico);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 9);
$pdf->writeHTML($html, true, false, true, false, '');
while (ob_get_level()) ob_end_clean();
$pdf->Output(rs_filename('report_sportelli', $nome_anno_scolastico, 'pdf'), 'D');
exit;

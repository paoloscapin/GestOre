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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ruoloRichiesto('docente', 'esterno', 'segreteria-didattica', 'dirigente');

$corso_id = intval($_GET['corso_id'] ?? 0);
$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
if (!in_array($format, ['pdf', 'xlsx'], true)) {
    $format = 'pdf';
}

if ($corso_id <= 0) {
    http_response_code(400);
    echo 'Parametro corso_id mancante';
    exit;
}

function cr_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function cr_dt($value)
{
    if (empty($value)) return '';
    try {
        return (new DateTime((string)$value))->format('d/m/Y H:i');
    } catch (Exception $e) {
        return (string)$value;
    }
}

function cr_clean_filename($value)
{
    $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$value);
    $value = trim($value, '_');
    return $value !== '' ? $value : 'corso';
}

function cr_current_docente_id()
{
    global $__docente_id, $__username;

    $id = intval($__docente_id ?? 0);
    if ($id > 0) return $id;

    $username = dbEscape($__username ?? '');
    if ($username === '') return 0;

    $row = dbGetFirst("SELECT id FROM docente WHERE username='$username' LIMIT 1");
    return $row ? intval($row['id']) : 0;
}

function cr_is_docente_view()
{
    global $__utente_ruolo;
    if (impersonaRuolo('docente') || impersonaRuolo('esterno')) return true;
    return in_array(strtolower((string)($__utente_ruolo ?? '')), ['docente', 'esterno'], true);
}

function cr_can_access_course($corso_id)
{
    global $__utente_ruolo;

    if (!cr_is_docente_view()) {
        return haRuolo('segreteria-didattica') || haRuolo('dirigente') || (($__utente_ruolo ?? '') === 'admin');
    }

    $docente_id = cr_current_docente_id();
    if ($docente_id <= 0) return false;

    $count = dbGetValue("
        SELECT COUNT(*)
        FROM corso c
        LEFT JOIN corso_docenti cd
               ON cd.id_corso = c.id
              AND cd.id_docente = " . dbI($docente_id) . "
        WHERE c.id = " . dbI($corso_id) . "
          AND (c.id_docente = " . dbI($docente_id) . " OR cd.id_docente IS NOT NULL)
    ");

    return intval($count) > 0;
}

function cr_load_course($corso_id)
{
    return dbGetFirst("
        SELECT
            c.id,
            c.titolo,
            c.id_anno_scolastico,
            c.carenza,
            c.in_itinere,
            c.carenza_sessione,
            m.nome AS materia,
            a.anno AS anno,
            COALESCE(
                GROUP_CONCAT(DISTINCT CONCAT(d2.cognome, ' ', d2.nome) ORDER BY cd.principale DESC, d2.cognome, d2.nome SEPARATOR ', '),
                CONCAT(d.cognome, ' ', d.nome)
            ) AS docenti
        FROM corso c
        LEFT JOIN materia m ON m.id = c.id_materia
        LEFT JOIN anno_scolastico a ON a.id = c.id_anno_scolastico
        LEFT JOIN docente d ON d.id = c.id_docente
        LEFT JOIN corso_docenti cd ON cd.id_corso = c.id
        LEFT JOIN docente d2 ON d2.id = cd.id_docente
        WHERE c.id = " . dbI($corso_id) . "
        GROUP BY c.id, c.titolo, c.id_anno_scolastico, c.carenza, c.in_itinere, c.carenza_sessione, m.nome, a.anno, d.cognome, d.nome
    ");
}

function cr_load_dates($corso_id)
{
    $rows = dbGetAll("
        SELECT
            cd.id,
            cd.data_inizio,
            cd.data_fine,
            cd.aula,
            COALESCE(cd.firmato, 0) AS firmato,
            ca.argomento,
            GROUP_CONCAT(DISTINCT CONCAT(d.cognome, ' ', d.nome) ORDER BY d.cognome, d.nome SEPARATOR ', ') AS firme
        FROM corso_date cd
        LEFT JOIN corso_argomenti ca ON ca.id_data_corso = cd.id
        LEFT JOIN corso_date_firme cdf ON cdf.id_data_corso = cd.id
        LEFT JOIN docente d ON d.id = cdf.id_docente
        WHERE cd.id_corso = " . dbI($corso_id) . "
        GROUP BY cd.id, cd.data_inizio, cd.data_fine, cd.aula, cd.firmato, ca.argomento
        ORDER BY cd.data_inizio ASC, cd.id ASC
    ");

    return $rows ?: [];
}

function cr_load_students($corso_id, $anno_id, $total_dates)
{
    $rows = dbGetAll("
        SELECT
            s.id,
            s.cognome,
            s.nome,
            cl.classe,
            COUNT(cp.id_studente) AS presenze
        FROM corso_iscritti ci
        INNER JOIN studente s ON s.id = ci.id_studente
        LEFT JOIN studente_frequenta sf
               ON sf.id_studente = s.id
              AND sf.id_anno_scolastico = " . dbI($anno_id) . "
        LEFT JOIN classi cl ON cl.id = sf.id_classe
        LEFT JOIN corso_date cd ON cd.id_corso = ci.id_corso
        LEFT JOIN corso_presenti cp
               ON cp.id_data_corso = cd.id
              AND cp.id_studente = s.id
        WHERE ci.id_corso = " . dbI($corso_id) . "
        GROUP BY s.id, s.cognome, s.nome, cl.classe
        ORDER BY s.cognome, s.nome
    ");

    if (!$rows) return [];

    foreach ($rows as &$row) {
        $presenti = intval($row['presenze'] ?? 0);
        $row['assenze'] = max(0, intval($total_dates) - $presenti);
        $row['percentuale_presenza'] = intval($total_dates) > 0
            ? round(($presenti / intval($total_dates)) * 100, 1)
            : null;
    }
    unset($row);

    return $rows;
}

function cr_load_presence_map($corso_id)
{
    $rows = dbGetAll("
        SELECT cp.id_data_corso, cp.id_studente
        FROM corso_presenti cp
        INNER JOIN corso_date cd ON cd.id = cp.id_data_corso
        WHERE cd.id_corso = " . dbI($corso_id) . "
    ");

    $map = [];
    foreach (($rows ?: []) as $row) {
        $map[intval($row['id_data_corso'])][intval($row['id_studente'])] = 1;
    }
    return $map;
}

function cr_matrix_date_label(array $date)
{
    try {
        return (new DateTime((string)$date['data_inizio']))->format('d/m') . "\n" . (new DateTime((string)$date['data_inizio']))->format('H:i');
    } catch (Exception $e) {
        return (string)($date['data_inizio'] ?? '');
    }
}

$corso = cr_load_course($corso_id);
if (!$corso) {
    http_response_code(404);
    echo 'Corso non trovato';
    exit;
}

if (!cr_can_access_course($corso_id)) {
    redirect('/error/unauthorized.php');
}

$dates = cr_load_dates($corso_id);
$students = cr_load_students($corso_id, intval($corso['id_anno_scolastico']), count($dates));
$presenceMap = cr_load_presence_map($corso_id);

$safeName = cr_clean_filename(($corso['materia'] ?? 'corso') . '_' . ($corso['titolo'] ?? '') . '_' . $corso_id);

if ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('GestOre')
        ->setTitle('Riepilogo corso');

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Date');
    $sheet->fromArray(['Inizio', 'Fine', 'Aula', 'Argomenti', 'Firmato', 'Firme'], null, 'A1');
    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

    $rowNum = 2;
    foreach ($dates as $date) {
        $sheet->fromArray([
            cr_dt($date['data_inizio']),
            cr_dt($date['data_fine']),
            $date['aula'] ?? '',
            $date['argomento'] ?? '',
            intval($date['firmato']) === 1 ? 'SI' : 'NO',
            $date['firme'] ?? '',
        ], null, 'A' . $rowNum);
        $rowNum++;
    }

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Studenti');
    $sheet2->fromArray(['Studente', 'Classe', 'Presenze', 'Assenze', 'Presenza %'], null, 'A1');
    $sheet2->getStyle('A1:E1')->applyFromArray($headerStyle);

    $rowNum = 2;
    foreach ($students as $student) {
        $sheet2->fromArray([
            trim(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '')),
            $student['classe'] ?? '',
            intval($student['presenze'] ?? 0),
            intval($student['assenze'] ?? 0),
            $student['percentuale_presenza'] === null ? '' : $student['percentuale_presenza'],
        ], null, 'A' . $rowNum);
        $rowNum++;
    }

    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle('Presenze');
    $sheet3->fromArray(['Data', 'Studente', 'Classe', 'Presente'], null, 'A1');
    $sheet3->getStyle('A1:D1')->applyFromArray($headerStyle);

    $rowNum = 2;
    foreach ($dates as $date) {
        $dateId = intval($date['id']);
        foreach ($students as $student) {
            $studentId = intval($student['id']);
            $sheet3->fromArray([
                cr_dt($date['data_inizio']),
                trim(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '')),
                $student['classe'] ?? '',
                isset($presenceMap[$dateId][$studentId]) ? 'SI' : 'NO',
            ], null, 'A' . $rowNum);
            $rowNum++;
        }
    }

    $sheet4 = $spreadsheet->createSheet();
    $sheet4->setTitle('Matrice presenze');
    $matrixHeader = ['Studente', 'Classe'];
    foreach ($dates as $date) {
        $matrixHeader[] = cr_dt($date['data_inizio']);
    }
    $sheet4->fromArray($matrixHeader, null, 'A1');
    $sheet4->getStyle('A1:' . $sheet4->getHighestColumn() . '1')->applyFromArray($headerStyle);

    $rowNum = 2;
    foreach ($students as $student) {
        $studentId = intval($student['id']);
        $row = [
            trim(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '')),
            $student['classe'] ?? '',
        ];

        foreach ($dates as $date) {
            $dateId = intval($date['id']);
            $row[] = isset($presenceMap[$dateId][$studentId]) ? 'P' : 'A';
        }

        $sheet4->fromArray($row, null, 'A' . $rowNum);
        $rowNum++;
    }

    foreach ($spreadsheet->getAllSheets() as $ws) {
        $highestColumnIndex = Coordinate::columnIndexFromString($ws->getHighestColumn());
        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
        $ws->freezePane('A2');
    }

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="riepilogo_corso_' . $safeName . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if (!class_exists('TCPDF')) {
    $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf)) {
        require_once $tcpdf;
    }
}

if (!class_exists('TCPDF')) {
    http_response_code(500);
    echo 'Errore: libreria TCPDF non trovata';
    exit;
}

function cr_pdf_draw_presence_matrix($pdf, array $dates, array $students, array $presenceMap)
{
    if (count($dates) === 0 || count($students) === 0) return;

    $left = 8;
    $top = 18;
    $bottom = 198;
    $studentW = 62;
    $classW = 14;
    $availableDateW = 281 - ($left * 2) - $studentW - $classW;
    $maxDateCols = max(1, (int)floor($availableDateW / 8));
    $chunks = array_chunk($dates, $maxDateCols);
    $chunkIndex = 0;

    foreach ($chunks as $dateChunk) {
        $chunkIndex++;
        $dateW = $availableDateW / max(1, count($dateChunk));

        $pdf->AddPage('L');
        $pdf->SetAutoPageBreak(false, 8);
        $pdf->SetDrawColor(190, 202, 211);
        $pdf->SetLineWidth(0.15);

        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->SetTextColor(11, 79, 113);
        $pdf->Cell(0, 7, 'Matrice presenze per data' . (count($chunks) > 1 ? ' - parte ' . $chunkIndex : ''), 0, 1, 'L');

        $pdf->SetFont('dejavusans', '', 7);
        $pdf->SetTextColor(70, 80, 90);
        $pdf->Cell(0, 5, 'Legenda: P = presente, A = assente', 0, 1, 'L');

        $y = $top + 3;
        $headerH = 12;
        $rowH = 6;

        $pdf->SetFillColor(11, 121, 165);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', 'B', 6);
        $pdf->SetXY($left, $y);
        $pdf->Cell($studentW, $headerH, 'Studente', 1, 0, 'C', true);
        $pdf->Cell($classW, $headerH, 'Classe', 1, 0, 'C', true);

        foreach ($dateChunk as $date) {
            $label = cr_matrix_date_label($date);
            $x = $pdf->GetX();
            $pdf->Cell($dateW, $headerH, '', 1, 0, 'C', true);
            $pdf->SetXY($x, $y + 2.2);
            $pdf->MultiCell($dateW, $headerH - 4.4, $label, 0, 'C', false, 0);
            $pdf->SetXY($x + $dateW, $y);
        }

        $y += $headerH;
        $pdf->SetFont('dejavusans', '', 6);

        foreach ($students as $student) {
            if ($y + $rowH > $bottom) {
                $pdf->AddPage('L');
                $y = $top;

                $pdf->SetFillColor(11, 121, 165);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('dejavusans', 'B', 6);
                $pdf->SetXY($left, $y);
                $pdf->Cell($studentW, $headerH, 'Studente', 1, 0, 'C', true);
                $pdf->Cell($classW, $headerH, 'Classe', 1, 0, 'C', true);
                foreach ($dateChunk as $date) {
                    $label = cr_matrix_date_label($date);
                    $x = $pdf->GetX();
                    $pdf->Cell($dateW, $headerH, '', 1, 0, 'C', true);
                    $pdf->SetXY($x, $y + 2.2);
                    $pdf->MultiCell($dateW, $headerH - 4.4, $label, 0, 'C', false, 0);
                    $pdf->SetXY($x + $dateW, $y);
                }
                $y += $headerH;
                $pdf->SetFont('dejavusans', '', 6);
            }

            $studentId = intval($student['id']);
            $name = trim(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? ''));

            $pdf->SetXY($left, $y);
            $pdf->SetFillColor(250, 252, 253);
            $pdf->SetTextColor(31, 41, 51);
            $pdf->Cell($studentW, $rowH, $name, 1, 0, 'L', true);
            $pdf->Cell($classW, $rowH, (string)($student['classe'] ?? ''), 1, 0, 'C', true);

            foreach ($dateChunk as $date) {
                $dateId = intval($date['id']);
                $present = isset($presenceMap[$dateId][$studentId]);
                if ($present) {
                    $pdf->SetFillColor(226, 246, 232);
                    $pdf->SetTextColor(19, 111, 45);
                    $value = 'P';
                } else {
                    $pdf->SetFillColor(255, 235, 230);
                    $pdf->SetTextColor(180, 35, 24);
                    $value = 'A';
                }
                $pdf->Cell($dateW, $rowH, $value, 1, 0, 'C', true);
            }

            $y += $rowH;
        }
    }

    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetTextColor(0, 0, 0);
}

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Riepilogo corso');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 9);

$tipo = [];
if (intval($corso['carenza'] ?? 0) === 1) $tipo[] = 'Carenze';
if (intval($corso['in_itinere'] ?? 0) === 1) $tipo[] = 'In itinere';
$tipoTxt = count($tipo) ? implode(' - ', $tipo) : 'Ordinario';
$logoPath = realpath(__DIR__ . '/../img/logoB_google.png');
if (!$logoPath) {
    $logoPath = realpath(__DIR__ . '/../img/logo_Buonarroti.png');
}
$totalePresenze = array_sum(array_map(function ($student) {
    return intval($student['presenze'] ?? 0);
}, $students));

if ($logoPath) {
    $pdf->Image($logoPath, 10, 10, 32, 0, '', '', '', false, 300);
}
$pdf->SetDrawColor(184, 197, 207);
$pdf->SetLineWidth(0.2);
$pdf->Line(10, 42, 200, 42);
$pdf->SetFillColor(11, 121, 165);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetXY(160, 11);
$pdf->Cell(40, 9, $tipoTxt, 0, 0, 'C', true);
$pdf->SetTextColor(11, 79, 113);
$pdf->SetFont('dejavusans', 'B', 17);
$pdf->SetXY(48, 10);
$pdf->MultiCell(105, 8, (string)($corso['titolo'] ?? 'Riepilogo corso'), 0, 'L', false, 1);
$pdf->SetTextColor(102, 112, 133);
$pdf->SetFont('dejavusans', '', 9);
$pdf->SetX(49);
$pdf->Cell(105, 5, 'Riepilogo corso - date, argomenti, firme e presenze studenti', 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetY(46);

$html = '
<style>
    body { color: #1f2933; }
    h2 { font-size: 12px; color: #0b4f71; margin-top: 8px; margin-bottom: 4px; }
    table { border-collapse: collapse; width: 100%; }
    th { background-color: #0b79a5; color: #ffffff; font-weight: bold; text-align: center; }
    th, td { border: 1px solid #b8c5cf; padding: 4px; vertical-align: top; }
    .meta th { background-color: #f5a623; color: #1f2933; width: 24%; text-align: left; font-size: 9px; }
    .meta td { background-color: #fffaf0; width: 76%; font-size: 10px; }
    .meta .docenti { font-size: 11px; font-weight: bold; color: #1f2933; }
    .stat th { background-color: #eef7fb; color: #0b4f71; border: 1px solid #b8c5cf; font-size: 15px; text-align: center; }
    .stat td { background-color: #eef7fb; border: 1px solid #b8c5cf; text-align: center; font-size: 9px; }
    .table-center th { text-align: center; }
    .table-center .centered { text-align: center; }
    .center { text-align: center; }
    .small { font-size: 8px; }
</style>
<table class="meta">
    <tr><th>Materia</th><td>' . cr_h($corso['materia']) . '</td></tr>
    <tr><th>Docenti</th><td class="docenti">' . cr_h($corso['docenti']) . '</td></tr>
    <tr><th>Anno scolastico</th><td>' . cr_h($corso['anno']) . '</td></tr>
</table>

<table class="stat">
    <tr>
        <th width="33%">' . count($dates) . '</th>
        <th width="33%">' . count($students) . '</th>
        <th width="34%">' . $totalePresenze . '</th>
    </tr>
    <tr>
        <td width="33%">date previste</td>
        <td width="33%">studenti iscritti</td>
        <td width="34%">presenze registrate</td>
    </tr>
</table>

<h2>Date, argomenti e firme</h2>
<table class="table-center">
    <tr>
        <th width="15%">Inizio</th>
        <th width="15%">Fine</th>
        <th width="9%">Aula</th>
        <th width="39%">Argomenti</th>
        <th width="22%">Firmato da</th>
    </tr>';

if (count($dates) === 0) {
    $html .= '<tr><td colspan="5" class="center">Nessuna data inserita</td></tr>';
} else {
    foreach ($dates as $date) {
        $html .= '<tr>
            <td class="centered">' . cr_h(cr_dt($date['data_inizio'])) . '</td>
            <td class="centered">' . cr_h(cr_dt($date['data_fine'])) . '</td>
            <td class="centered">' . cr_h($date['aula']) . '</td>
            <td>' . nl2br(cr_h($date['argomento'])) . '</td>
            <td class="centered">' . cr_h($date['firme'] ?: 'Non firmata') . '</td>
        </tr>';
    }
}

$html .= '</table>

<h2>Studenti - presenze e assenze</h2>
<table class="table-center">
    <tr>
        <th width="44%">Studente</th>
        <th width="16%">Classe</th>
        <th width="13%">Presenze</th>
        <th width="13%">Assenze</th>
        <th width="14%">Presenza %</th>
    </tr>';

if (count($students) === 0) {
    $html .= '<tr><td colspan="5" class="center">Nessuno studente iscritto</td></tr>';
} else {
    foreach ($students as $student) {
        $percent = $student['percentuale_presenza'] === null ? '' : number_format(floatval($student['percentuale_presenza']), 1, ',', '') . '%';
        $html .= '<tr>
            <td>' . cr_h(trim(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? ''))) . '</td>
            <td class="center">' . cr_h($student['classe']) . '</td>
            <td class="center">' . intval($student['presenze'] ?? 0) . '</td>
            <td class="center">' . intval($student['assenze'] ?? 0) . '</td>
            <td class="center">' . cr_h($percent) . '</td>
        </tr>';
    }
}

$html .= '</table>';

$html .= '
<p class="small">La matrice presenze per data e riportata nelle pagine successive. Generato il ' . date('d/m/Y H:i') . '</p>';

$pdf->writeHTML($html, true, false, true, false, '');
cr_pdf_draw_presence_matrix($pdf, $dates, $students, $presenceMap);

while (ob_get_level()) ob_end_clean();
$pdf->Output('riepilogo_corso_' . $safeName . '.pdf', 'D');
exit;

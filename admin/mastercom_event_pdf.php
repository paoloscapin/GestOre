<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/events_lib.php';
require_once '../common/vendor/autoload.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mastercomEventPdfH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mastercomEventPdfDate(string $date): string
{
    $date = trim($date);
    $dt = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->format('d/m/Y') : $date;
}

function mastercomEventPdfStudentRows(array $studentIds): array
{
    $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), function ($id) {
        return $id > 0;
    })));
    if (empty($studentIds) || !mastercomAdminTableExists('mastercom_studenti')) {
        return [];
    }

    $idsSql = implode(',', $studentIds);
    $classJoin = mastercomAdminTableExists('mastercom_classi')
        ? 'LEFT JOIN mastercom_classi c ON c.mastercom_id_classe = s.mastercom_id_classe_corrente'
        : '';
    $classSelect = mastercomAdminTableExists('mastercom_classi') ? 'c.nome AS classe' : "'' AS classe";

    $rows = dbGetAll("
        SELECT
            s.mastercom_id_studente,
            s.registro_numero,
            s.cognome,
            s.nome,
            $classSelect
        FROM mastercom_studenti s
        $classJoin
        WHERE s.mastercom_id_studente IN ($idsSql)
        ORDER BY classe ASC, s.cognome ASC, s.nome ASC
    ") ?: [];

    $known = [];
    foreach ($rows as $row) {
        $known[intval($row['mastercom_id_studente'] ?? 0)] = true;
    }
    foreach ($studentIds as $studentId) {
        if (empty($known[$studentId])) {
            $rows[] = [
                'mastercom_id_studente' => $studentId,
                'registro_numero' => '',
                'cognome' => '',
                'nome' => '',
                'classe' => '',
            ];
        }
    }

    return $rows;
}

$eventId = intval($_GET['id_evento'] ?? 0);
if ($eventId <= 0) {
    http_response_code(400);
    exit('ID evento non valido');
}

$detailResult = mastercomEventFetchDetail($eventId);
if (empty($detailResult['ok'])) {
    http_response_code(500);
    exit('Dettaglio evento MasterCom non letto: ' . trim((string)($detailResult['error'] ?? 'FETCH_DETAIL_FAILED')));
}

$detail = is_array($detailResult['event_detail'] ?? null) ? $detailResult['event_detail'] : [];
$studentIds = is_array($detail['studenti'] ?? null) ? $detail['studenti'] : [];
$students = mastercomEventPdfStudentRows($studentIds);
$participantCount = count($studentIds);
$generatedAt = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('d/m/Y H:i');

$studentRowsHtml = '';
if (empty($students)) {
    $studentRowsHtml = '<tr><td colspan="5" class="muted center">Nessun partecipante letto da MasterCom.</td></tr>';
} else {
    $index = 1;
    foreach ($students as $student) {
        $studentRowsHtml .= '<tr>'
            . '<td class="center" width="7%">' . $index . '</td>'
            . '<td width="18%">' . mastercomEventPdfH($student['classe'] ?? '') . '</td>'
            . '<td class="center" width="12%">' . mastercomEventPdfH($student['registro_numero'] ?? '') . '</td>'
            . '<td width="43%">' . mastercomEventPdfH(trim((string)($student['cognome'] ?? '') . ' ' . (string)($student['nome'] ?? ''))) . '</td>'
            . '<td class="center" width="20%">' . mastercomEventPdfH($student['mastercom_id_studente'] ?? '') . '</td>'
            . '</tr>';
        $index++;
    }
}

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            color: #1f2933;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }
        h1 {
            color: #17456b;
            font-size: 22px;
            margin: 0 0 6px 0;
        }
        h2 {
            border-bottom: 1px solid #b8c7d6;
            color: #17456b;
            font-size: 15px;
            margin: 20px 0 8px 0;
            padding-bottom: 4px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th,
        td {
            border: 1px solid #d7dde3;
            padding: 6px 7px;
            vertical-align: top;
        }
        th {
            background: #e7f1f9;
            color: #17456b;
            font-weight: bold;
        }
        .meta td:first-child {
            background: #f4f8fb;
            color: #52616f;
            font-weight: bold;
            width: 24%;
        }
        .center {
            text-align: center;
        }
        .muted {
            color: #697785;
        }
        .summary {
            background: #eef7ff;
            border: 1px solid #b8d9ef;
            margin: 10px 0 14px 0;
            padding: 8px 10px;
        }
        .description {
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <h1>Evento MasterCom #' . intval($eventId) . '</h1>
    <div class="muted">Riepilogo generato da GestOre il ' . mastercomEventPdfH($generatedAt) . '</div>
    <div class="summary"><strong>Partecipanti:</strong> ' . intval($participantCount) . '</div>

    <h2>Dati evento</h2>
    <table class="meta">
        <tr><td>Titolo</td><td>' . mastercomEventPdfH($detail['nome'] ?? '') . '</td></tr>
        <tr><td>Descrizione</td><td class="description">' . nl2br(mastercomEventPdfH($detail['descrizione'] ?? '')) . '</td></tr>
        <tr><td>Inizio</td><td>' . mastercomEventPdfH(mastercomEventPdfDate((string)($detail['data_inizio'] ?? '')) . ' ' . (string)($detail['ora_inizio'] ?? '')) . '</td></tr>
        <tr><td>Fine</td><td>' . mastercomEventPdfH(mastercomEventPdfDate((string)($detail['data_fine'] ?? '')) . ' ' . (string)($detail['ora_fine'] ?? '')) . '</td></tr>
        <tr><td>Libera docenti</td><td>' . mastercomEventPdfH($detail['libera_docenti'] ?? '') . '</td></tr>
        <tr><td>Tipo permesso</td><td>' . mastercomEventPdfH($detail['tipo_permesso'] ?? '') . '</td></tr>
    </table>

    <h2>Partecipanti</h2>
    <table>
        <thead>
            <tr>
                <th class="center" width="7%">#</th>
                <th width="18%">Classe</th>
                <th class="center" width="12%">Registro</th>
                <th width="43%">Studente</th>
                <th class="center" width="20%">ID</th>
            </tr>
        </thead>
        <tbody>' . $studentRowsHtml . '</tbody>
    </table>
</body>
</html>';

class MastercomEventPdf extends TCPDF
{
    public function Footer()
    {
        $this->SetY(-12);
        $this->SetDrawColor(190, 190, 190);
        $this->Line(10, $this->GetY(), $this->getPageWidth() - 10, $this->GetY());
        $this->Ln(2);
        $this->SetFont('dejavusans', 'I', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 5, 'GestOre - Pag. ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new MastercomEventPdf('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Evento MasterCom #' . $eventId);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 16);
$pdf->SetFont('dejavusans', '', 10);
$pdf->AddPage();
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('evento_mastercom_' . $eventId . '.pdf', 'I');

<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

// opzionale: se vuoi rispettare config corsi
if (!getSettingsValue('config', 'corsi', false)) {
    redirect("/error/unauthorized.php");
}

// formato
$format = strtolower($_GET['format'] ?? 'csv');
if (!in_array($format, ['csv', 'pdf'], true)) {
    $format = 'csv';
}

// anno (default: corrente)
$anno_id = intval($_GET['anno_id'] ?? $__anno_scolastico_corrente_id);

// sessione (0=tutte, 1 o 2)
$sessione = intval($_GET['sessione'] ?? 0);

$whereSessione = '';
if ($sessione === 1 || $sessione === 2) {
    $whereSessione = " AND COALESCE(co.carenza_sessione,1) = {$sessione} ";
}

// Query: corsi carenze problematici (no date OR non firmati OR date incomplete)
$sql = "
SELECT
    co.id AS id_corso,
    a.anno AS anno_scolastico,
    m.nome AS materia,
    CONCAT(d.cognome,' ',d.nome) AS docente,
    co.titolo,
    COALESCE(co.carenza_sessione,1) AS sessione,
    COALESCE(co.in_itinere,0) AS in_itinere,

    COUNT(DISTINCT ci.id_studente) AS iscritti,

    COUNT(DISTINCT ced.id) AS num_date_esame,
    COUNT(DISTINCT CASE
        WHEN ced.id IS NOT NULL
        AND (ced.data_inizio_esame IS NULL OR ced.data_fine_esame IS NULL)
        THEN ced.id
    END) AS num_date_incomplete,

    COUNT(DISTINCT CASE
        WHEN ced.id IS NOT NULL
        AND COALESCE(ced.firmato,0)=0
        THEN ced.id
    END) AS num_date_non_firmate,

    MIN(ced.data_inizio_esame) AS prima_data_esame
FROM corso co
INNER JOIN anno_scolastico a ON a.id = co.id_anno_scolastico
INNER JOIN materia m ON m.id = co.id_materia
INNER JOIN docente d ON d.id = co.id_docente

LEFT JOIN corso_iscritti ci ON ci.id_corso = co.id
LEFT JOIN corso_esami_date ced ON ced.id_corso = co.id

WHERE co.carenza = 1
  AND co.id_anno_scolastico = {$anno_id}
  {$whereSessione}

GROUP BY co.id, a.anno, m.nome, d.cognome, d.nome, co.titolo, co.carenza_sessione, co.in_itinere

HAVING
    COUNT(DISTINCT ced.id) = 0
    OR COUNT(DISTINCT CASE
            WHEN ced.id IS NOT NULL
             AND (ced.data_inizio_esame IS NULL OR ced.data_fine_esame IS NULL)
            THEN ced.id
       END) > 0
    OR COUNT(DISTINCT CASE
            WHEN ced.id IS NOT NULL
             AND COALESCE(ced.firmato,0)=0
            THEN ced.id
       END) > 0

ORDER BY m.nome ASC, d.cognome ASC, d.nome ASC, co.titolo ASC
";

$rows = dbGetAll($sql) ?: [];

$anno_txt = dbGetValue("
    SELECT anno
    FROM anno_scolastico
    WHERE id = {$anno_id}
    LIMIT 1
");

if (!$anno_txt) {
    $anno_txt = 'N/D';
}

// versione sicura per filename: 2025/2026 → 2025-2026
$anno_filename = preg_replace('/[^0-9]+/', '-', $anno_txt);
$anno_filename = trim($anno_filename, '-');

// ======================================================
// PDF
// ======================================================
if ($format === 'pdf') {

    // include TCPDF (adatta il path se necessario)
    $tcpdf1 = __DIR__ . '/../common/tcpdf/tcpdf.php';
    $tcpdf2 = __DIR__ . '/../common/tcpdf_min/tcpdf.php';

    if (file_exists($tcpdf1)) {
        require_once $tcpdf1;
    } elseif (file_exists($tcpdf2)) {
        require_once $tcpdf2;
    } else {
        // fallback chiaro (evita schermata bianca)
        header('Content-Type: text/plain; charset=utf-8');
        echo "Errore: libreria TCPDF non trovata. Atteso: {$tcpdf1} (o {$tcpdf2})";
        exit;
    }

    $filename = "report_corsi_carenze_anno_{$anno_filename}_" . date('d-m-Y_H-i-s') . ".pdf";

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GestOre');
    $pdf->SetAuthor('GestOre');
    $pdf->SetTitle('Report corsi carenze');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);

    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'Report corsi carenze – corsi senza date o non firmati', 0, 1, 'C');
    date_default_timezone_set('Europe/Rome');
    $pdf->SetFont('helvetica', '', 9);
    $sSess = ($sessione === 1 || $sessione === 2) ? " – Sessione: {$sessione}" : " – Sessione: tutte";
    $pdf->Cell(0, 6, "Anno scolastico ID: {$anno_txt}{$sSess} – Generato il " . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Ln(4);

    // Tabella HTML (TCPDF): colgroup + table-layout fixed per allineare THEAD/TBODY
    $html = '
<style>
  table{ border-collapse:collapse; width:100%; }
  th{ background-color:#eeeeee; font-weight:bold; }
  th,td{ border:1px solid #666; padding:3px; font-size:8.5pt; }
  td.num{ text-align:center; }
</style>

<table width="100%" cellpadding="2">
  <colgroup>
    <col width="20" />  <!-- ID -->
    <col width="210" />  <!-- Materia -->
    <col width="100" />  <!-- Docente -->
    <col width="180" />  <!-- Titolo -->
    <col width="30" />  <!-- Sess. -->
    <col width="30" />  <!-- Itinere -->
    <col width="30" />  <!-- Iscritti -->
    <col width="40" />  <!-- N. date -->
    <col width="50" />  <!-- Non firmate -->
    <col width="100" />  <!-- Prima data -->
  </colgroup>

  <thead>
    <tr>
      <th width="20">ID</th>
      <th width="210">Materia</th>
      <th width="100">Docente</th>
      <th width="180">Titolo</th>
      <th width="30">Sess.</th>
      <th width="30">Itinere</th>
      <th width="30">Iscritti</th>
      <th width="40">N. date</th>
      <th width="50">Non firmate</th>
      <th width="100">Prima data esame</th>
    </tr>
  </thead>
  <tbody>
';

    foreach ($rows as $r) {
        $prima = 'nessuna data inserita';
        if (!empty($r['prima_data_esame'])) {
            try {
                $prima = (new DateTime($r['prima_data_esame']))->format('d-m-Y H:i');
            } catch (Exception $e) {
                $prima = $r['prima_data_esame'];
            }
        }

        $html .= '
    <tr>
      <td class="num" width="20">' . intval($r['id_corso']) . '</td>
      <td width="210">' . htmlspecialchars($r['materia']) . '</td>
      <td width="100">' . htmlspecialchars($r['docente']) . '</td>
      <td width="180">' . htmlspecialchars($r['titolo']) . '</td>
      <td class="num" width="30">' . intval($r['sessione']) . '</td>
      <td class="num" width="30">' . (intval($r['in_itinere']) === 1 ? 'SI' : 'NO') . '</td>
      <td class="num" width="30">' . intval($r['iscritti']) . '</td>
      <td class="num" width="40">' . intval($r['num_date_esame']) . '</td>
      <td class="num" width="50">' . intval($r['num_date_non_firmate']) . '</td>
      <td width="100">' . htmlspecialchars($prima) . '</td>
    </tr>';
    }

    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    // output download
    $pdf->Output($filename, 'D');
    exit;
}


// ======================================================
// CSV
// ======================================================
$filename = "report_corsi_carenze_anno_{$anno_id}_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM per Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

fputcsv($out, [
    'ID Corso',
    'Anno scolastico',
    'Materia',
    'Docente',
    'Titolo',
    'Sessione',
    'In itinere',
    'Iscritti',
    'N. date esame',
    'N. date incomplete',
    'N. date NON firmate',
    'Prima data esame'
], ';');

foreach ($rows as $r) {
    $prima = 'nessuna data inserita';
    if (!empty($r['prima_data_esame'])) {
        try {
            $prima = (new DateTime($r['prima_data_esame']))->format('d-m-Y H:i');
        } catch (Exception $e) {
            $prima = $r['prima_data_esame'];
        }
    }

    fputcsv($out, [
        $r['id_corso'],
        $r['anno_scolastico'],
        $r['materia'],
        $r['docente'],
        $r['titolo'],
        $r['sessione'],
        (intval($r['in_itinere']) === 1 ? 'SI' : 'NO'),
        $r['iscritti'],
        $r['num_date_esame'],
        $r['num_date_incomplete'],
        $r['num_date_non_firmate'],
        $prima
    ], ';');
}

fclose($out);
exit;

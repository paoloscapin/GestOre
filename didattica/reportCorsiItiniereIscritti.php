<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

if (!getSettingsValue('config', 'corsi', false)) {
    redirect("/error/unauthorized.php");
}

date_default_timezone_set('Europe/Rome');

// -----------------------------
// PARAMETRI
// -----------------------------
$format = strtolower($_GET['format'] ?? 'csv');
if (!in_array($format, ['csv', 'pdf'], true)) $format = 'csv';

$anno_id = intval($_GET['anno_id'] ?? $__anno_scolastico_corrente_id);

$solo_carenze = intval($_GET['solo_carenze'] ?? 1); // 1=solo carenze, 0=tutti
$whereCarenze = ($solo_carenze === 1) ? " AND co.carenza=1 " : "";

// testo anno
$anno_txt = dbGetValue("SELECT anno FROM anno_scolastico WHERE id={$anno_id} LIMIT 1");
if (!$anno_txt) $anno_txt = (string)$anno_id;
$anno_filename = preg_replace('/[^0-9]+/', '-', $anno_txt);
$anno_filename = trim($anno_filename, '-');

// -----------------------------
// QUERY (MySQL 5.7 safe)
// - stats corso: num_date_esame, num_date_non_firmate, prima_data_esame
// - ultimo esito firmato: join su MAX(data_inizio_esame)
// -----------------------------
$sql = "
SELECT
    a.anno AS anno_scolastico,
    co.id AS id_corso,
    m.nome AS materia,
    CONCAT(d.cognome,' ',d.nome) AS docente,
    co.titolo AS titolo,
    COALESCE(co.carenza_sessione,1) AS sessione,
    COALESCE(co.carenza,0) AS carenza,
    COALESCE(co.in_itinere,0) AS in_itinere,

    CONCAT(s.cognome,' ',s.nome) AS studente,
    COALESCE(cl.classe, '') AS classe,

    COALESCE(ced_stats.num_date_esame,0) AS num_date_esame,
    COALESCE(ced_stats.num_date_non_firmate,0) AS num_date_non_firmate,
    ced_stats.prima_data_esame,

    last_ce.presente AS presente,
    last_ce.recuperato AS superato,
    CASE WHEN last_ce.id_esame_data IS NULL THEN 0 ELSE 1 END AS esame_firmato_any

FROM corso co
INNER JOIN anno_scolastico a ON a.id = co.id_anno_scolastico
INNER JOIN materia m ON m.id = co.id_materia
INNER JOIN docente d ON d.id = co.id_docente
INNER JOIN corso_iscritti ci ON ci.id_corso = co.id
INNER JOIN studente s ON s.id = ci.id_studente

-- ✅ classe dello studente nell'anno del corso
LEFT JOIN (
    SELECT
        id_studente,
        id_anno_scolastico,
        MAX(id_classe) AS id_classe
    FROM studente_frequenta
    GROUP BY id_studente, id_anno_scolastico
) sf ON sf.id_studente = s.id
   AND sf.id_anno_scolastico = co.id_anno_scolastico

LEFT JOIN classi cl ON cl.id = sf.id_classe

-- stats date esame del corso
LEFT JOIN (
    SELECT
        id_corso,
        COUNT(*) AS num_date_esame,
        SUM(CASE WHEN COALESCE(firmato,0)=0 THEN 1 ELSE 0 END) AS num_date_non_firmate,
        MIN(data_inizio_esame) AS prima_data_esame
    FROM corso_esami_date
    GROUP BY id_corso
) AS ced_stats ON ced_stats.id_corso = co.id

-- ultima data esame firmata per (corso, studente)
LEFT JOIN (
    SELECT
        ced.id_corso,
        ce.id_studente,
        MAX(ced.data_inizio_esame) AS max_data
    FROM corso_esami_date ced
    INNER JOIN corso_esiti ce ON ce.id_esame_data = ced.id
    WHERE COALESCE(ced.firmato,0)=1
    GROUP BY ced.id_corso, ce.id_studente
) AS last_dt
    ON last_dt.id_corso = co.id
   AND last_dt.id_studente = s.id

LEFT JOIN corso_esami_date last_ced
    ON last_ced.id_corso = last_dt.id_corso
   AND last_ced.data_inizio_esame = last_dt.max_data
   AND COALESCE(last_ced.firmato,0)=1

-- ultimo esito firmato (per corso + studente) in modo deterministico
LEFT JOIN corso_esiti last_ce
  ON last_ce.id_studente = s.id
 AND last_ce.id_esame_data = (
    SELECT ced2.id
    FROM corso_esami_date ced2
    INNER JOIN corso_esiti ce2
      ON ce2.id_esame_data = ced2.id
     AND ce2.id_studente = s.id
    WHERE ced2.id_corso = co.id
      AND COALESCE(ced2.firmato,0) = 1
    ORDER BY ced2.data_inizio_esame DESC, ced2.id DESC
    LIMIT 1
 )


WHERE
    co.id_anno_scolastico = {$anno_id}
    AND COALESCE(co.in_itinere,0)=1
    {$whereCarenze}


ORDER BY
    m.nome ASC,
    d.cognome ASC, d.nome ASC,
    co.titolo ASC,
    s.cognome ASC, s.nome ASC
";

// --- DEBUG rapito (se serve): decommenta per vedere query a video
//echo "<pre>".htmlspecialchars($sql)."</pre>"; exit;

$rows = dbGetAll($sql) ?: [];

// helper
function formatPrima($dt)
{
    if (empty($dt)) return 'nessuna data inserita';
    try {
        return (new DateTime($dt))->format('d-m-Y H:i');
    } catch (Exception $e) {
        return (string)$dt;
    }
}

// ---------------- CSV ----------------
if ($format === 'csv') {
    $filename = "report_iscritti_corsi_itinere_{$anno_filename}_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    fputcsv($out, [
        'Anno scolastico',
        'ID Corso',
        'Materia',
        'Docente',
        'Titolo corso',
        'Sessione',
        'Carenza',
        'In itinere',
        'Studente',
        'Classe',
        'N. date esame',
        'Prima data esame',
        'Esame firmato',
        'Presente',
        'Superato'
    ], ';');

    foreach ($rows as $r) {
        $prima = formatPrima($r['prima_data_esame'] ?? null);

        // “Esame firmato” = ci sono date e nessuna è non firmata
        $esameFirmatoTxt = (intval($r['num_date_esame']) > 0 && intval($r['num_date_non_firmate']) === 0) ? 'SI' : 'NO';

        $hasEsitoFirmato = (intval($r['esame_firmato_any']) === 1);
        $presenteTxt = $hasEsitoFirmato ? ((intval($r['presente']) === 1) ? 'SI' : 'NO') : 'in attesa';
        $superatoTxt = $hasEsitoFirmato ? ((intval($r['superato']) === 1) ? 'SI' : 'NO') : 'in attesa';

        fputcsv($out, [
            $r['anno_scolastico'],
            $r['id_corso'],
            $r['materia'],
            $r['docente'],
            $r['titolo'],
            $r['sessione'],
            (intval($r['carenza']) === 1 ? 'SI' : 'NO'),
            (intval($r['in_itinere']) === 1 ? 'SI' : 'NO'),
            $r['studente'],
            $r['classe'],
            $r['num_date_esame'],
            $prima,
            $esameFirmatoTxt,
            $presenteTxt,
            $superatoTxt
        ], ';');
    }

    fclose($out);
    exit;
}

// ---------------- PDF ----------------
require_once '../common/tcpdf/tcpdf.php';

$filename = "report_iscritti_corsi_itinere_{$anno_filename}_" . date('Ymd_His') . ".pdf";

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Report iscritti corsi in itinere');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'Report iscritti ai corsi in itinere', 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 9);
$soloTxt = ($solo_carenze === 1) ? ' (solo corsi carenze)' : '';
$pdf->Cell(0, 6, "Anno scolastico: {$anno_txt}{$soloTxt} – Generato il " . date('d/m/Y H:i'), 0, 1, 'C');
$pdf->Ln(4);

$html = '
<style>
  table{ border-collapse:collapse; width:100%; }
  th{ background-color:#eeeeee; font-weight:bold; }
  th,td{ border:1px solid #666; padding:3px; font-size:8.5pt; }
  td.num{ text-align:center; }
</style>

<table width="100%" border="1" cellpadding="3">
<colgroup>
 <col width="30" />
 <col width="180" />
 <col width="100" />
 <col width="100" />
 <col width="30" />
 <col width="20" />
 <col width="10" />
 <col width="40" />
 <col width="20" />
 <col width="80" />
 <col width="30" />
 <col width="30" />
 <col width="30" />
 </colgroup>
 
<thead>
<tr>
  <th width="30">ID</th>
  <th width="180">Materia</th>
  <th width="100">Docente</th>
  <th width="100">Titolo</th>
  <th width="30">Ses</th>
  <th width="20">It.</th>
  <th width="100">Studente</th>
  <th width="40">Cls</th>
  <th width="20">N</th>
  <th width="80">Prima data</th>
  <th width="30">Firm.</th>
  <th width="30">Pres.</th>
  <th width="30">Sup.</th>
</tr>
</thead>
<tbody>
';

foreach ($rows as $r) {
    $prima = formatPrima($r['prima_data_esame'] ?? null);

    $esameFirmatoTxt = (intval($r['num_date_esame']) > 0 && intval($r['num_date_non_firmate']) === 0) ? 'SI' : 'NO';

    $hasEsitoFirmato = (intval($r['esame_firmato_any']) === 1);
    $presenteTxt = $hasEsitoFirmato ? ((intval($r['presente']) === 1) ? 'SI' : 'NO') : 'ATT';
    $superatoTxt = $hasEsitoFirmato ? ((intval($r['superato']) === 1) ? 'SI' : 'NO') : 'ATT';

    $html .= '<tr>
      <td width="30">' . intval($r['id_corso']) . '</td>
      <td width="180">' . htmlspecialchars($r['materia']) . '</td>
      <td width="100">' . htmlspecialchars($r['docente']) . '</td>
      <td width="100">' . htmlspecialchars($r['titolo']) . '</td>
      <td width="30">' . intval($r['sessione']) . '</td>
      <td width="20">' . (intval($r['in_itinere']) === 1 ? 'SI' : 'NO') . '</td>
      <td width="100">' . htmlspecialchars($r['studente']) . '</td>
      <td width="40">' . htmlspecialchars($r['classe']) . '</td>
      <td width="20">' . intval($r['num_date_esame']) . '</td>
      <td width="80">' . htmlspecialchars($prima) . '</td>
      <td width="30">' . $esameFirmatoTxt . '</td>
      <td width="30">' . $presenteTxt . '</td>
      <td width="30">' . $superatoTxt . '</td>
    </tr>';
}

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output($filename, 'D');
exit;

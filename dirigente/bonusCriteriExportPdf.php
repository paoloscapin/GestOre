<?php
require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';

// === TCPDF include: prova path comuni (aggiusta se serve) ===
$tcpdfCandidates = [
  __DIR__ . '/../common/tcpdf/tcpdf.php',
  __DIR__ . '/../common/tcpdf_min/tcpdf.php',
  __DIR__ . '/../tcpdf/tcpdf.php',
  __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php',
];

$tcpdfLoaded = false;
foreach ($tcpdfCandidates as $p) {
  if (is_file($p)) {
    require_once $p;
    $tcpdfLoaded = true;
    break;
  }
}
if (!$tcpdfLoaded) {
  http_response_code(500);
  echo "TCPDF non trovato. Controlla il path in bonusCriteriExportPdf.php";
  exit;
}

/**
 * Disegna riga indicatore (gialla con bordo nero) SENZA HTML
 * -> evita i caratteri '|' e i bordi fantasma di TCPDF con tabelle annidate.
 */
function pdfIndicatoreRow(TCPDF $pdf, $codice, $descrizione, $max)
{
  $margins = $pdf->getMargins();
  $pageW = $pdf->getPageWidth();
  $usableW = $pageW - $margins['left'] - $margins['right'];

  // stile
  $hPadding = 2.0;        // padding interno orizzontale
  $vPadding = 1.5;        // padding interno verticale
  $codeW = 18;            // larghezza colonna codice (mm)
  $gap = 2;               // spazio tra codice e testo

  $x = $margins['left'];
  $y = $pdf->GetY();

  $codice = (string)$codice;
  $testo = trim((string)$descrizione);
  if ($max !== '' && $max !== null) {
    $testo .= " (Max: " . $max . ")";
  }

  $pdf->SetFont('dejavusans', 'B', 10);
  $lineH = 5.0;

  $descW = $usableW - $codeW - $gap;

  $lines = $pdf->getNumLines($testo, $descW - 2 * $hPadding);
  $h = max($lineH, $lines * $lineH) + 2 * $vPadding;

  // rettangolo giallo con bordo nero
  $pdf->SetFillColor(255, 226, 138); // #ffe28a
  $pdf->SetDrawColor(0, 0, 0);
  $pdf->Rect($x, $y, $usableW, $h, 'DF');

  // codice (rientrato)
  $pdf->SetXY($x + $hPadding, $y + $vPadding);
  $pdf->MultiCell(
    $codeW - 2 * $hPadding,
    $h - 2 * $vPadding,
    $codice,
    0,
    'L',
    false,
    0,
    '',
    '',
    true,
    0,
    false,
    true,
    $h - 2 * $vPadding,
    'T',
    true
  );

  // descrizione (rientrata anche sulle righe a capo)
  $pdf->SetXY($x + $codeW + $gap, $y + $vPadding);
  $pdf->MultiCell(
    $descW - $hPadding,
    $h - 2 * $vPadding,
    $testo,
    0,
    'L',
    false,
    1,
    '',
    '',
    true,
    0,
    false,
    true,
    $h - 2 * $vPadding,
    'T',
    true
  );

  $pdf->Ln(1.5);
}

// ===== input =====
$anno = isset($_GET['anno_scolastico_id']) ? intval($_GET['anno_scolastico_id']) : $__anno_scolastico_corrente_id;

$annoRow = dbGetFirst("SELECT anno FROM anno_scolastico WHERE id = $anno");
$annoName = $annoRow ? $annoRow['anno'] : ("id_" . $anno);

// ===== query =====
$query = "
SELECT
  ba.id AS area_id,
  ba.codice AS area_codice,
  ba.descrizione AS area_descrizione,

  bi.id AS indicatore_id,
  bi.codice AS indicatore_codice,
  bi.descrizione AS indicatore_descrizione,
  bi.valore_massimo AS indicatore_valore_massimo,

  b.id AS bonus_id,
  b.codice AS bonus_codice,
  b.descrittori AS bonus_descrittori,
  b.evidenze AS bonus_evidenze,
  b.valore_previsto AS bonus_valore_previsto
FROM bonus_area ba
LEFT JOIN bonus_indicatore bi
  ON bi.bonus_area_id = ba.id
 AND bi.anno_scolastico_id = $anno
 AND (bi.valido IS NULL OR bi.valido = 1)
LEFT JOIN bonus b
  ON b.bonus_indicatore_id = bi.id
 AND b.anno_scolastico_id = $anno
 AND (b.valido IS NULL OR b.valido = 1)
WHERE (ba.valido IS NULL OR ba.valido = 1)
ORDER BY ba.codice, bi.codice, b.codice;
";
$rows = dbGetAll($query);

// ===== build tree =====
$tree = [];
foreach ($rows as $r) {
  $aId = $r['area_id'];
  if (!isset($tree[$aId])) {
    $tree[$aId] = [
      'codice' => $r['area_codice'],
      'descrizione' => $r['area_descrizione'],
      'indicatori' => []
    ];
  }
  if (!empty($r['indicatore_id'])) {
    $iId = $r['indicatore_id'];
    if (!isset($tree[$aId]['indicatori'][$iId])) {
      $tree[$aId]['indicatori'][$iId] = [
        'codice' => $r['indicatore_codice'],
        'descrizione' => $r['indicatore_descrizione'],
        'max' => $r['indicatore_valore_massimo'],
        'bonus' => []
      ];
    }
    if (!empty($r['bonus_id'])) {
      $tree[$aId]['indicatori'][$iId]['bonus'][] = [
        'codice' => $r['bonus_codice'],
        'descrittori' => $r['bonus_descrittori'],
        'evidenze' => $r['bonus_evidenze'],
        'valore' => $r['bonus_valore_previsto'],
      ];
    }
  }
}

// ===== TCPDF setup =====
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Criteri Bonus - ' . $annoName);
$pdf->SetMargins(8, 10, 8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);

// ===== HTML content =====
$title = htmlspecialchars("Criteri Bonus");
$sub = htmlspecialchars("Anno scolastico: " . $annoName);

$html = <<<HTML
<style>
h1 { font-size: 16pt; margin-bottom: 4px; }
.sub { color: #444; margin-bottom: 10px; }
.area { font-size: 12pt; font-weight: bold; margin-top: 10px; }
table { border-collapse: collapse; width: 100%; }
th { background-color: #f0f0f0; font-weight: bold; }
th, td { border: 1px solid #999; padding: 4px; vertical-align: top; }
.small { color: #444; }
</style>

<h1>{$title}</h1>
<div class="sub">{$sub}</div>
HTML;

foreach ($tree as $area) {
  $areaTitle = htmlspecialchars($area['codice'] . " - " . $area['descrizione']);
  $html .= '<div class="area">' . $areaTitle . '</div>';

  if (empty($area['indicatori'])) {
    $html .= '<div class="small">Nessun indicatore per questo anno</div>';
    continue;
  }

  foreach ($area['indicatori'] as $ind) {

    // 1) scrivi l'HTML accumulato fino a qui (area title ecc.)
    if (trim($html) !== '') {
      $pdf->writeHTML($html, true, false, true, false, '');
      $html = '';
    }

    // 2) disegna l'indicatore con API TCPDF (NO HTML => NO '|')
    pdfIndicatoreRow($pdf, $ind['codice'], $ind['descrizione'], $ind['max']);

    // ✅ RESET FONT QUI
    $pdf->SetFont('dejavusans', '', 10);

    // 3) poi riprendi con l'HTML della tabella bonus
    $html .= '
<table width="100%" border="1" cellpadding="4" cellspacing="0">
  <thead>
    <tr style="background-color:#f0f0f0;">
      <th width="10%"><b>Codice</b></th>
      <th width="44%"><b>Descrittori</b></th>
      <th width="10%" align="center"><b>Valore</b></th>
      <th width="36%"><b>Evidenze</b></th>
    </tr>
  </thead>
  <tbody>
';

    if (empty($ind['bonus'])) {
      $html .= '<tr><td colspan="4" class="small">Nessun bonus</td></tr>';
    } else {
      foreach ($ind['bonus'] as $b) {
        $cod = htmlspecialchars($b['codice']);
        $desc = nl2br(htmlspecialchars($b['descrittori']));
        $evi = nl2br(htmlspecialchars($b['evidenze']));
        $val = htmlspecialchars((string)$b['valore']);

        $html .= '
    <tr>
      <td width="10%">' . $cod . '</td>
      <td width="44%">' . $desc . '</td>
      <td width="10%" align="center">' . $val . '</td>
      <td width="36%">' . $evi . '</td>
    </tr>
';
      }
    }

    $html .= '</tbody></table>';
    $html .= '<div style="height:6px;"></div>'; // spazio tra indicatori
  }
}

// scrive l'ultimo pezzo HTML rimasto
if (trim($html) !== '') {
  $pdf->writeHTML($html, true, false, true, false, '');
}

// ===== output =====
$filename = 'criteri_bonus_' . preg_replace('/[^0-9A-Za-z_\-]/', '_', $annoName) . '.pdf';
$pdf->Output($filename, 'I');
exit;

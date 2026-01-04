<?php
/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/importi_load.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';

debug("storicoBonus2: START");

// =========================
// TCPDF include
// =========================
$tcpdfCandidates = [
  __DIR__ . '/../common/tcpdf/tcpdf.php',
  __DIR__ . '/../common/tcpdf_min/tcpdf.php',
  __DIR__ . '/../tcpdf/tcpdf.php',
  __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php',
];
$tcpdfLoaded = false;
foreach ($tcpdfCandidates as $p) {
  if (is_file($p)) { require_once $p; $tcpdfLoaded = true; break; }
}
if (!$tcpdfLoaded) {
  debug("storicoBonus2: TCPDF NOT FOUND");
}

// =========================
// Input
// =========================
$anno_id = isset($_GET['anno_id']) ? intval($_GET['anno_id']) : intval($__anno_scolastico_corrente_id);
$isPdf   = isset($_GET['print']) && (
  $_GET['print'] === '1' || $_GET['print'] === 'true' || $_GET['print'] === 'yes' || $_GET['print'] === 'on'
);

debug("storicoBonus2: anno_id=$anno_id isPdf=" . ($isPdf ? "1" : "0"));

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nl($s){ return nl2br(h($s)); }

// ✅ EURO: aggiunge " €" (richiesto)
function fmtEuro($v){ return number_format((float)$v, 2) . ' €'; }
function fmtNoZero($v){ return ((float)$v != 0.0) ? (number_format((float)$v, 2) . ' €') : ''; }

// =========================
// Totali globali
// =========================
debug("storicoBonus2: calc totals begin");

$totale_bonus_assegnato = dbGetValue("SELECT COALESCE(SUM(importo),0) FROM bonus_assegnato WHERE anno_scolastico_id = $anno_id;");
debug("storicoBonus2: totale_bonus_assegnato=" . $totale_bonus_assegnato);

$punteggioVariabile = getSettingsValue('bonus','punteggio_variabile', false);
debug("storicoBonus2: punteggio_variabile=" . ($punteggioVariabile ? 'true' : 'false'));

if ($punteggioVariabile) {
  $qTotVal = "
    SELECT COALESCE(SUM(bd.approvato), 0)
    FROM bonus_docente bd
    INNER JOIN bonus b ON b.id = bd.bonus_id
    WHERE bd.anno_scolastico_id = $anno_id
      AND (b.valido IS NULL OR b.valido = 1)
      AND (b.anno_scolastico_id IS NULL OR b.anno_scolastico_id = $anno_id)
  ";
} else {
  $qTotVal = "
    SELECT COALESCE(SUM(b.valore_previsto), 0)
    FROM bonus_docente bd
    INNER JOIN bonus b ON b.id = bd.bonus_id
    WHERE bd.anno_scolastico_id = $anno_id
      AND bd.approvato IS TRUE
      AND (b.valido IS NULL OR b.valido = 1)
      AND (b.anno_scolastico_id IS NULL OR b.anno_scolastico_id = $anno_id)
  ";
}
$totale_valore_approvato = dbGetValue($qTotVal);
debug("storicoBonus2: totale_valore_approvato=" . $totale_valore_approvato);

$importo_totale_bonus = $__importo_bonus;
debug("storicoBonus2: importo_totale_bonus=" . $importo_totale_bonus);

$importo_totale_bonus_approvato = $importo_totale_bonus - $totale_bonus_assegnato;
debug("storicoBonus2: importo_totale_bonus_approvato=" . $importo_totale_bonus_approvato);

$importo_per_punto = ($totale_valore_approvato != 0) ? ($importo_totale_bonus_approvato / $totale_valore_approvato) : 0;
debug("storicoBonus2: importo_per_punto=" . $importo_per_punto);

$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$anno_id");
debug("storicoBonus2: nome_anno_scolastico=" . $nome_anno_scolastico);

$annoStampabile = str_replace('/','-',$nome_anno_scolastico);
$nomeIstituto = getSettingsValue('local','nomeIstituto', '');
$title = 'Storico Bonus ' . $annoStampabile . ' - ' . $nomeIstituto;
debug("storicoBonus2: title=" . $title);

// =========================
// Query helpers
// =========================
function getAssegnato($docente_id, $anno_id) {
  $docente_id = intval($docente_id);
  $anno_id = intval($anno_id);
  return dbGetAll("SELECT * FROM bonus_assegnato WHERE anno_scolastico_id = $anno_id AND docente_id = $docente_id");
}

function getBonusRows($docente_id, $anno_id) {
  $docente_id = intval($docente_id);
  $anno_id = intval($anno_id);
  $q = "
    SELECT
      bd.id AS bonus_docente_id,
      bd.approvato AS bonus_docente_approvato,
      bd.rendiconto_evidenze AS bonus_docente_rendiconto_evidenze,

      b.codice AS bonus_codice,
      b.descrittori AS bonus_descrittori,
      b.valore_previsto AS bonus_valore_previsto
    FROM bonus_docente bd
    INNER JOIN bonus b ON bd.bonus_id = b.id
    WHERE bd.docente_id = $docente_id
      AND bd.anno_scolastico_id = $anno_id
      AND (b.valido IS NULL OR b.valido = 1)
      AND (b.anno_scolastico_id IS NULL OR b.anno_scolastico_id = $anno_id)
    ORDER BY b.codice;
  ";
  return dbGetAll($q);
}

function getAllegati($bonus_docente_id) {
  $bonus_docente_id = intval($bonus_docente_id);
  $q = "
    SELECT id, original_name, stored_name, mime_type, file_size, uploaded_at
    FROM bonus_docente_allegato
    WHERE bonus_docente_id = $bonus_docente_id
    ORDER BY uploaded_at ASC, id ASC
  ";
  return dbGetAll($q);
}

// =========================
// Prepara dataset docenti (saltando quelli vuoti)
// + calcola totale approvato istituto (somma importi calcolati)
// =========================
debug("storicoBonus2: fetch docenti begin");

$docenti = dbGetAll("SELECT * FROM docente ORDER BY cognome, nome ASC;");
debug("storicoBonus2: docenti count=" . count($docenti));

$docentiOut = [];
$totaleApprovatoIstituto = 0.0; // somma importi calcolati da approvazioni

foreach ($docenti as $docente) {

  // per anno corrente escludi non attivi
  if ($anno_id == intval($__anno_scolastico_corrente_id) && intval($docente['attivo']) == 0) {
    continue;
  }

  $docente_id = intval($docente['id']);

  $assegnato = getAssegnato($docente_id, $anno_id);
  $bonusRows = getBonusRows($docente_id, $anno_id);

  // ✅ Salta docente senza nulla
  if (empty($assegnato) && empty($bonusRows)) {
    continue;
  }

  // Allegati per ogni bonus_docente + calcolo importi
  foreach ($bonusRows as &$b) {

    $approvato = $b['bonus_docente_approvato'];
    if ($approvato === null) $approvato = 0;

    $importo = 0.0;
    if ($approvato) {
      if ($punteggioVariabile) {
        $punti = (float)$approvato;
      } else {
        $punti = (float)$b['bonus_valore_previsto'];
      }
      $importo = $importo_per_punto * $punti;
      $totaleApprovatoIstituto += $importo;
    }

    $b['calcolato_importo'] = $importo;
    $b['allegati'] = getAllegati($b['bonus_docente_id']);
  }
  unset($b);

  $docentiOut[] = [
    'nome' => $docente['cognome'] . ' ' . $docente['nome'],
    'assegnato' => $assegnato,
    'bonus' => $bonusRows
  ];
}

$totaleAssegnatoIstituto = (float)$totale_bonus_assegnato;
$totaleIstituto = $totaleAssegnatoIstituto + $totaleApprovatoIstituto;

debug("storicoBonus2: docentiOut count=" . count($docentiOut));
debug("storicoBonus2: totaleApprovatoIstituto=" . $totaleApprovatoIstituto);

// =========================
// Bonus assegnati (lista per copertina)
// =========================
$qAssegnati = "
  SELECT
    ba.docente_id,
    d.cognome,
    d.nome,
    ba.commento,
    ba.importo
  FROM bonus_assegnato ba
  INNER JOIN docente d ON d.id = ba.docente_id
  WHERE ba.anno_scolastico_id = $anno_id
  ORDER BY d.cognome, d.nome, ba.id
";
debug("storicoBonus2: qAssegnati=" . $qAssegnati);
$assegnatiIstituto = dbGetAll($qAssegnati);
debug("storicoBonus2: assegnatiIstituto count=" . count($assegnatiIstituto));

// =========================
// HTML PREVIEW (copertina + consuntivo + docenti)
// =========================
if (!$isPdf) {

  $pdfUrl = 'storicoBonus2.php?anno_id=' . $anno_id . '&print=1';

  // logo base64 (come prima)
  $logoBin = dbGetValue("SELECT src FROM immagine WHERE nome = 'Logo.png'");
  $logoB64 = $logoBin ? base64_encode($logoBin) : '';

  echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
  echo '<title>' . h($title) . '</title>';
  echo '<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />';
  echo '<style>
    body { font-family: Helvetica, Arial, sans-serif; max-width: 1100px; margin: 0 auto; padding: 20px; }
    h1,h2,h3,h4 { color:#0e2c50; margin: 10px 0; }
    .topbar { text-align:center; margin: 20px 0 40px 0; }
    .btn { display:inline-block; padding: 10px 14px; background:#4c3635; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold; }
    table { width:100%; border-collapse: collapse; font-size: 13px; table-layout: fixed; }
    th,td { border:1px solid #000; padding:6px; vertical-align: top; word-wrap: break-word; }
    .text-right{ text-align:right; }
    .text-center{ text-align:center; }
    hr{ margin:16px 0; }
    .allegati { margin-top:6px; padding-left: 18px; }
    .allegati li { margin: 2px 0; }
    .small { color:#444; font-size: 12px; }
    .cover { text-align:center; padding: 30px 0 50px 0; }
    .cover img { max-width: 220px; }
  </style></head><body>';

  echo '<div class="topbar"><a class="btn" href="'.h($pdfUrl).'">📄 Scarica PDF</a></div>';

  // COPERTINA
  echo '<div class="cover">';
  if ($logoB64) {
    echo '<div style="margin-bottom:16px;"><img alt="Logo" src="data:image/png;base64,'.$logoB64.'"></div>';
  }
  echo '<h3>'.h($nomeIstituto).'</h3>';
  echo '<h2>Bonus Docenti anno scolastico '.h($nome_anno_scolastico).'</h2>';

  // Tabella bonus assegnati (copertina)
  if (!empty($assegnatiIstituto)) {
    echo '<div style="margin-top:26px; text-align:left;">';
    echo '<h4 style="margin:0 0 8px 0;">Bonus assegnati</h4>';
    echo '<table><thead><tr>
            <th style="width:30%;">Docente</th>
            <th style="width:55%;">Descrizione</th>
            <th style="width:15%;" class="text-right">Importo</th>
          </tr></thead><tbody>';

    foreach ($assegnatiIstituto as $r) {
      $doc = trim($r['cognome'].' '.$r['nome']);
      echo '<tr>
              <td>'.h($doc).'</td>
              <td>'.nl($r['commento']).'</td>
              <td class="text-right">'.fmtEuro($r['importo']).'</td>
            </tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
  }

  echo '</div>';

  // CONSUNTIVO (subito dopo la tabella assegnati, senza forzare nuove pagine)
  echo '<hr style="margin-top:20px;">';
  echo '<h2 style="text-align:center;">Totale Bonus anno scolastico '.h($nome_anno_scolastico).'</h2>';
  echo '<table><thead><tr><th>Tipo</th><th class="text-right">Importo</th></tr></thead><tbody>';
  echo '<tr><td>Totale Bonus Assegnato</td><td class="text-right">'.fmtEuro($totaleAssegnatoIstituto).'</td></tr>';
  echo '<tr><td>Totale Bonus Approvato</td><td class="text-right">'.fmtEuro($totaleApprovatoIstituto).'</td></tr>';
  echo '</tbody><tfoot>';
  echo '<tr><th class="text-right">Totale:</th><th class="text-right">'.fmtEuro($totaleIstituto).'</th></tr>';
  echo '</tfoot></table>';
  echo '<hr>';

  // DOCENTI
  foreach ($docentiOut as $sez) {
    echo '<hr><h3>' . h($sez['nome']) . '</h3>';

    if (!empty($sez['assegnato'])) {
      echo '<h4>Bonus Assegnato</h4>';
      echo '<table><thead><tr><th style="width:85%;">Commento</th><th class="text-right" style="width:15%;">Importo</th></tr></thead><tbody>';
      foreach ($sez['assegnato'] as $a) {
        echo '<tr><td>'.h($a['commento']).'</td><td class="text-right">'.fmtEuro($a['importo']).'</td></tr>';
      }
      echo '</tbody></table>';
    }

    if (!empty($sez['bonus'])) {
      echo '<h4>Bonus</h4>';
      echo '<table><thead><tr>
        <th style="width:10%;">Codice</th>
        <th style="width:60%;">Descrittore</th>
        <th style="width:8%;" class="text-center">Valore</th>
        <th style="width:8%;" class="text-center">Approvato</th>
        <th style="width:14%;" class="text-right">Importo</th>
      </tr></thead><tbody>';

      foreach ($sez['bonus'] as $b) {

        $approvato = $b['bonus_docente_approvato'];
        if ($approvato === null) $approvato = 0;

        $appCol = $punteggioVariabile ? number_format((float)$approvato, 2) : ($approvato ? '✓' : '');
        $importo = (float)$b['calcolato_importo'];

        echo '<tr>
          <td>'.h($b['bonus_codice']).'</td>
          <td>'
            . nl($b['bonus_descrittori'])
            . '<hr><strong>Rendiconto:</strong><br>'
            . nl((string)$b['bonus_docente_rendiconto_evidenze']);

        // Allegati HTML (cliccabili)
        if (!empty($b['allegati'])) {
          echo '<div style="margin-top:8px;"><strong>Allegati:</strong></div><ul class="allegati">';
          foreach ($b['allegati'] as $al) {
            $name = $al['original_name'] ?: ('Allegato #' . $al['id']);
            $kb = round(((int)$al['file_size'])/1024);
            $dt = $al['uploaded_at'];
            $url = '../docente/bonusAllegatoDownload.php?id=' . intval($al['id']);
            echo '<li><a href="'.h($url).'" target="_blank">'.h($name).'</a> <span class="small">('.$kb.' KB, '.h($dt).')</span></li>';
          }
          echo '</ul>';
        }

        echo '</td>
          <td class="text-center">'.h((string)$b['bonus_valore_previsto']).'</td>
          <td class="text-center">'.$appCol.'</td>
          <td class="text-right">'.($approvato ? fmtNoZero($importo) : '').'</td>
        </tr>';
      }

      echo '</tbody></table>';
    }
  }

  echo '</body></html>';
  exit;
}

// =========================
// PDF TCPDF: copertina + consuntivo + docenti
// =========================
if (!$tcpdfLoaded) {
  http_response_code(500);
  echo "TCPDF non trovato. Controlla il path in storicoBonus2.php";
  exit;
}

debug("storicoBonus2: PDF render begin");

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle($title);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// pagina utile
$m = $pdf->getMargins();
$pageW = $pdf->getPageWidth();
$usableW = $pageW - $m['left'] - $m['right'];

// =========================
// COPERTINA
// =========================
$pdf->AddPage();

// Logo
$logoBin = dbGetValue("SELECT src FROM immagine WHERE nome = 'Logo.png'");
if ($logoBin) {
  // centra logo
  $logoW = 60; // mm
  $x = ($pageW - $logoW) / 2;
  $pdf->Image('@' . $logoBin, $x, 30, $logoW, 0, 'PNG');
}

// Testi copertina
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetY(110);
$pdf->MultiCell(0, 8, $nomeIstituto, 0, 'C', false, 1);

$pdf->Ln(4);
$pdf->SetFont('dejavusans', '', 14);
$pdf->MultiCell(0, 8, "Bonus Docenti anno scolastico $nome_anno_scolastico", 0, 'C', false, 1);

// Tabella bonus assegnati in copertina
if (!empty($assegnatiIstituto)) {

  $pdf->Ln(8);

  $pdf->SetFont('dejavusans', 'B', 11);
  $pdf->MultiCell(0, 6, "Bonus assegnati", 0, 'L', false, 1);
  $pdf->Ln(1);

  // ✅ allarga colonna docente e recupera dalla descrizione
  $wDoc = 60;       // prima 45
  $wImp = 25;
  $wCom = $usableW - ($wDoc + $wImp);

  // Header tabella
  $pdf->SetFont('dejavusans', 'B', 9);
  $pdf->SetFillColor(240,240,240);
  $pdf->Cell($wDoc, 6, "Docente", 1, 0, 'L', true);
  $pdf->Cell($wCom, 6, "Descrizione", 1, 0, 'L', true);
  $pdf->Cell($wImp, 6, "Importo", 1, 1, 'R', true);

  $pdf->SetFont('dejavusans', '', 9);

  foreach ($assegnatiIstituto as $r) {
    $doc = trim($r['cognome'].' '.$r['nome']);
    $commento = (string)$r['commento'];
    $imp = fmtEuro($r['importo']);

    $nLines = $pdf->getNumLines($commento, $wCom);
    $rowH = max(6, $nLines * 4.2);

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->MultiCell($wDoc, $rowH, $doc, 1, 'L', false, 0);
    $pdf->SetXY($x + $wDoc, $y);
    $pdf->MultiCell($wCom, $rowH, $commento, 1, 'L', false, 0);
    $pdf->SetXY($x + $wDoc + $wCom, $y);
    $pdf->MultiCell($wImp, $rowH, $imp, 1, 'R', false, 1);
  }
}

// =========================
// CONSUNTIVO
// ✅ NON nuova pagina: subito dopo la tabella assegnati
// =========================
$pdf->Ln(8);
$pdf->SetFont('dejavusans', 'B', 13);
$pdf->MultiCell(0, 8, "Totale Bonus anno scolastico $nome_anno_scolastico", 0, 'C', false, 1);
$pdf->Ln(2);

$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(240,240,240);

$wTipo = $usableW * 0.70;
$wImpC = $usableW - $wTipo;

$pdf->Cell($wTipo, 7, "Tipo", 1, 0, 'L', true);
$pdf->Cell($wImpC,  7, "Importo", 1, 1, 'R', true);

$pdf->SetFont('dejavusans', '', 10);

$pdf->Cell($wTipo, 7, "Totale Bonus Assegnato", 1, 0, 'L', false);
$pdf->Cell($wImpC,  7, fmtEuro($totaleAssegnatoIstituto), 1, 1, 'R', false);

$pdf->Cell($wTipo, 7, "Totale Bonus Approvato", 1, 0, 'L', false);
$pdf->Cell($wImpC,  7, fmtEuro($totaleApprovatoIstituto), 1, 1, 'R', false);

$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell($wTipo, 8, "Totale:", 1, 0, 'R', false);
$pdf->Cell($wImpC,  8, fmtEuro($totaleIstituto), 1, 1, 'R', false);

// =========================
// DOCENTI
// =========================
$pdf->SetFont('dejavusans', '', 10);

// larghezze tabella bonus (coerenti header/righe)
$wCod = 20;
$wVal = 18;
$wApp = 18;
$wImp = 25;
$wDesc = $usableW - ($wCod + $wVal + $wApp + $wImp);

foreach ($docentiOut as $sez) {

  $pdf->AddPage();

  $pdf->SetFont('dejavusans', 'B', 12);
  $pdf->MultiCell(0, 7, $sez['nome'], 0, 'L', false, 1);
  $pdf->Ln(1);

  // Bonus assegnato
  if (!empty($sez['assegnato'])) {

    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->MultiCell(0, 6, "Bonus Assegnato", 0, 'L', false, 1);

    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(240,240,240);
    $pdf->Cell($usableW - 40, 6, "Commento", 1, 0, 'L', true);
    $pdf->Cell(40, 6, "Importo", 1, 1, 'R', true);

    $pdf->SetFont('dejavusans', '', 9);
    foreach ($sez['assegnato'] as $a) {
      $y0 = $pdf->GetY();
      $x0 = $pdf->GetX();

      $pdf->MultiCell($usableW - 40, 0, (string)$a['commento'], 1, 'L', false, 0);
      $pdf->SetXY($x0 + ($usableW - 40), $y0);
      $pdf->MultiCell(40, 0, fmtEuro($a['importo']), 1, 'R', false, 1);
    }
    $pdf->Ln(3);
  }

  // Bonus
  if (!empty($sez['bonus'])) {

    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->MultiCell(0, 6, "Bonus", 0, 'L', false, 1);

    // Header
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(240,240,240);
    $pdf->Cell($wCod, 6, "Codice", 1, 0, 'C', true);
    $pdf->Cell($wDesc, 6, "Descrittore / Rendiconto / Allegati", 1, 0, 'C', true);
    $pdf->Cell($wVal, 6, "Valore", 1, 0, 'C', true);
    $pdf->Cell($wApp, 6, "Approv.", 1, 0, 'C', true);
    $pdf->Cell($wImp, 6, "Importo", 1, 1, 'C', true);

    $pdf->SetFont('dejavusans', '', 9);

    foreach ($sez['bonus'] as $b) {

      $approvato = $b['bonus_docente_approvato'];
      if ($approvato === null) $approvato = 0;

      $importo = (float)$b['calcolato_importo'];
      $appTxt = $punteggioVariabile ? number_format((float)$approvato, 2) : ($approvato ? "SI" : "");

      // testo descrizione + rendiconto
      $desc = (string)$b['bonus_descrittori'] . "\n\nRendiconto:\n" . (string)$b['bonus_docente_rendiconto_evidenze'];

      // allegati in PDF: elenco nomi
      if (!empty($b['allegati'])) {
        $desc .= "\n\nAllegati:";
        foreach ($b['allegati'] as $al) {
          $desc .= "\n- " . ($al['original_name'] ?: ('Allegato #' . $al['id']));
        }
      }

      $nLines = $pdf->getNumLines($desc, $wDesc);
      $rowH = max(6, $nLines * 4.2);

      $x = $pdf->GetX();
      $y = $pdf->GetY();

      $pdf->MultiCell($wCod, $rowH, (string)$b['bonus_codice'], 1, 'L', false, 0);

      $pdf->SetXY($x + $wCod, $y);
      $pdf->MultiCell($wDesc, $rowH, $desc, 1, 'L', false, 0);

      $pdf->SetXY($x + $wCod + $wDesc, $y);
      $pdf->MultiCell($wVal, $rowH, (string)$b['bonus_valore_previsto'], 1, 'C', false, 0);

      $pdf->SetXY($x + $wCod + $wDesc + $wVal, $y);
      $pdf->MultiCell($wApp, $rowH, $appTxt, 1, 'C', false, 0);

      $pdf->SetXY($x + $wCod + $wDesc + $wVal + $wApp, $y);
      $pdf->MultiCell($wImp, $rowH, ($approvato ? fmtNoZero($importo) : ''), 1, 'R', false, 1);
    }
  }
}

$filename = preg_replace('/[^0-9A-Za-z_\-]/', '_', $title) . '.pdf';
$pdf->Output($filename, 'I');
exit;

<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata', 'personale-ata');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
  http_response_code(400);
  exit('Missing id');
}

function fmtDateIT($d): string
{
  if (!$d) return '';
  $ts = strtotime((string)$d);
  if (!$ts) return (string)$d;
  return date('d/m/Y', $ts);
}

function fmtDateTimeIT($d): string
{
  if (!$d) return '';
  $ts = strtotime((string)$d);
  if (!$ts) return (string)$d;
  return date('d/m/Y H:i', $ts);
}

function fmtTimeHHMM($t): string
{
  $t = trim((string)$t);
  return $t !== '' ? substr($t, 0, 5) : '';
}

function ferieRowStatus(array $r): string
{
  $dj = [];
  if (!empty($r['dettagli_json'])) {
    $dj = json_decode($r['dettagli_json'], true);
    if (!is_array($dj)) $dj = [];
  }

  $st = strtoupper(trim((string)($dj['stato_giorno'] ?? '')));
  if ($st === '') {
    $st = strtoupper(trim((string)($r['stato'] ?? '')));
  }
  if ($st === '') $st = '-';

  return $st;
}

function ferieMergeRanges(array $righe): array
{
  $out = [];
  $curr = null;

  foreach ($righe as $r) {
    $dal = (string)($r['data_dal'] ?? '');
    $al  = (string)($r['data_al'] ?? $dal);

    if ($dal === '') continue;

    $stato = ferieRowStatus($r);

    if ($curr === null) {
      $curr = [
        'data_dal' => $dal,
        'data_al'  => $al,
        'stato'    => $stato,
      ];
      continue;
    }

    $canMerge = false;

    if ($curr['stato'] === $stato) {
      $prevDate = $curr['data_al'];
      $nextDate = $dal;

      $d1 = DateTime::createFromFormat('Y-m-d', $prevDate);
      $d2 = DateTime::createFromFormat('Y-m-d', $nextDate);

      if ($d1 && $d2 && $d2 >= $d1) {
        $tmp = clone $d1;
        $tmp->modify('+1 day');
        $soloWeekendInMezzo = true;

        while ($tmp < $d2) {
          $weekday = (int)$tmp->format('N'); // 6=sabato, 7=domenica
          if ($weekday !== 6 && $weekday !== 7) {
            $soloWeekendInMezzo = false;
            break;
          }
          $tmp->modify('+1 day');
        }

        $diffDays = (int)$d1->diff($d2)->days;

        if ($diffDays >= 1 && $soloWeekendInMezzo) {
          $canMerge = true;
        }
      }
    }

    if ($canMerge) {
      $curr['data_al'] = $al;
    } else {
      $out[] = $curr;
      $curr = [
        'data_dal' => $dal,
        'data_al'  => $al,
        'stato'    => $stato,
      ];
    }
  }

  if ($curr !== null) {
    $out[] = $curr;
  }

  return $out;
}

// carico richiesta + dipendente + tipo
$row = dbGetFirst("
  SELECT
    r.*,
    t.codice AS tipo_codice,
    t.descrizione AS tipo_descrizione,
    p.username,
    p.cognome,
    p.nome,
    p.email,
    p.matricola,
    pr.nome AS profilo_nome,
    u.nome  AS ufficio_nome
  FROM permesso_ata_richiesta r
  JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
  JOIN personale_ata p ON p.id = r.personale_ata_id
  LEFT JOIN personale_ata_profili pr
         ON pr.id = p.id_profilo
  LEFT JOIN personale_ata_assegnazioni pa
         ON pa.username = p.username
  LEFT JOIN personale_ata_uffici u
         ON u.id = pa.id_ufficio
  WHERE r.id = $id
  LIMIT 1
");
if (!$row) {
  http_response_code(404);
  exit('Not found');
}

// permessi: se personale-ata può vedere solo i suoi
$ruolo = (string)($__utente_ruolo ?? $session->get('utente_ruolo') ?? '');
if ($ruolo === 'personale-ata') {
  if (intval($row['personale_ata_id']) !== intval($__ata_id)) {
    http_response_code(403);
    exit('Forbidden');
  }
}

$righe = dbGetAll("
  SELECT id, data_dal, data_al, ora_dal, ora_al, dettagli_json
  FROM permesso_ata_richiesta_riga
  WHERE permesso_ata_richiesta_id = $id
  ORDER BY data_dal ASC, id ASC
");

// === TCPDF ===
require_once __DIR__ . '/../common/tcpdf/tcpdf.php'; // <-- ADATTA PATH al tuo TCPDF

class PermessoPDF extends TCPDF
{
  public function Footer()
  {
    // più alto (prima era -10)
    $this->SetY(-15);

    // font più leggibile
    $this->SetFont('helvetica', '', 10);

    $this->SetTextColor(80, 80, 80);
    $this->SetDrawColor(180, 180, 180);

    $leftText  = 'GestOre | ' . $_SERVER['HTTP_HOST'] . ' | ' . date('d/m/Y H:i');
    $rightText = 'Pagina ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages();

    // linea più distante dal fondo
    $this->Cell(0, 0, '', 'T', 1, 'L');
    $this->Ln(0.5);

    // testo
    $this->Cell(130, 5, $leftText, 0, 0, 'L');
    $this->Cell(0, 5, $rightText, 0, 0, 'R');
  }
}

$pdf = new PermessoPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('ITT Buonarroti Trento');
$pdf->SetTitle('Permesso ATA #' . $id);
$pdf->AddPage();

$ferieSottotipo = trim((string)($row['ferie_sottotipo'] ?? ''));
$tipoTxt = $row['tipo_codice'] . ' - ' . $row['tipo_descrizione'];
if ($row['tipo_codice'] === 'FERIE' && $ferieSottotipo !== '') {
  $tipoTxt .= ' (' . $ferieSottotipo . ')';
}
$logoBase64 = base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'logo.png'"));
$nomeIstituto = trim((string)($__settings->local->nomeIstituto ?? ''));

$logoBase64 = base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'logo.png'"));
$nomeIstituto = trim((string)($__settings->local->nomeIstituto ?? ''));
$nomeIstituto = str_replace(' - ', ' – ', $nomeIstituto);
$year = (int)date('Y');
$month = (int)date('n');

if ($month >= 9) {
  $annoScolastico = $year . '/' . ($year + 1);
} else {
  $annoScolastico = ($year - 1) . '/' . $year;
}
$html = '
<table cellpadding="0" cellspacing="0" border="0" width="100%">
  


  <tr>
    <td width="20%" style="text-align:left; vertical-align:middle;">
      <img src="data:image/png;base64,' . $logoBase64 . '" style="height:55px;">
    </td>

<td width="60%" style="text-align:center; vertical-align:middle;">
  
  <div style="font-size:18px; font-weight:bold; line-height:1.2;">
    ' . htmlspecialchars($nomeIstituto) . '
  </div>

  <div style="font-size:13px; line-height:1.3;">
    Richiesta Permesso ATA – a.s. ' . $annoScolastico . '
  </div>

</td>

    <td width="20%"></td>
  </tr>

  <tr>
    <td colspan="3">
      <hr style="margin-top:8px;">
    </td>
  </tr>

</table>

<table cellpadding="4" border="0" width="100%">
  <tr><td width="28%"><b>ID</b></td><td width="72%">' . intval($row['id']) . '</td></tr>
  <tr><td><b>Dipendente</b></td><td>' . htmlspecialchars($row['cognome'] . ' ' . $row['nome']) . ' (' . htmlspecialchars($row['matricola']) . ')</td></tr>
  <tr><td><b>Email</b></td><td>' . htmlspecialchars($row['email']) . '</td></tr>
  <tr><td><b>Tipo</b></td><td>' . htmlspecialchars($tipoTxt) . '</td></tr>
  <tr><td><b>Profilo</b></td><td>' . htmlspecialchars($row['profilo_nome'] ?? '') . '</td></tr>
  <tr><td><b>Ufficio</b></td><td>' . htmlspecialchars($row['ufficio_nome'] ?? '') . '</td></tr>
  <tr><td><b>Stato</b></td><td>' . htmlspecialchars($row['stato']) . '</td></tr>
  <tr><td><b>Creato</b></td><td>' . htmlspecialchars(fmtDateTimeIT($row['created_at'] ?? '')) . '</td></tr>
  <tr><td><b>Aggiornato</b></td><td>' . htmlspecialchars(fmtDateTimeIT($row['updated_at'] ?? '')) . '</td></tr>
</table>
<br>
<h4 style="margin:0;">Intervalli</h4>';

if (($row['tipo_codice'] ?? '') === 'FERIE') {

  $ferieRanges = ferieMergeRanges($righe);

  $html .= '
  <table border="1" cellpadding="4">
    <thead>
      <tr>
        <th width="34%" style="text-align:center; vertical-align:middle;"><b>Dal</b></th>
        <th width="33%" style="text-align:center; vertical-align:middle;"><b>Al</b></th>
        <th width="33%" style="text-align:center; vertical-align:middle;"><b>Stato</b></th>
      </tr>
    </thead>
    <tbody>';

  foreach ($ferieRanges as $fr) {
    $html .= '<tr nobr="true">
    <td width="34%" style="text-align:center; vertical-align:middle;">' . htmlspecialchars(fmtDateIT($fr['data_dal'] ?? '')) . '</td>
    <td width="33%" style="text-align:center; vertical-align:middle;">' . htmlspecialchars(fmtDateIT($fr['data_al'] ?? '')) . '</td>
    <td width="33%" style="text-align:center; vertical-align:middle;">' . htmlspecialchars($fr['stato'] ?? '-') . '</td>
  </tr>';
  }

  $html .= '</tbody></table>';
} else {

  $html .= '
  <table border="1" cellpadding="4">
    <thead>
      <tr>
        <th width="25%" style="text-align:center; vertical-align:middle;"><b>Dal</b></th>
        <th width="25%" style="text-align:center; vertical-align:middle;"><b>Al</b></th>
        <th width="25%" style="text-align:center; vertical-align:middle;"><b>Ora dal</b></th>
        <th width="25%" style="text-align:center; vertical-align:middle;"><b>Ora al</b></th>
      </tr>
    </thead>
    <tbody>';

  foreach ($righe as $r) {
    $html .= '<tr nobr="true">
      <td style="text-align:center; vertical-align:middle;">' . htmlspecialchars(fmtDateIT($r['data_dal'] ?? '')) . '</td>
      <td style="text-align:center; vertical-align:middle;">' . htmlspecialchars(fmtDateIT($r['data_al'] ?? '')) . '</td>
      <td style="text-align:center; vertical-align:middle;">' . htmlspecialchars(fmtTimeHHMM($r['ora_dal'] ?? '')) . '</td>
      <td style="text-align:center; vertical-align:middle;">' . htmlspecialchars(fmtTimeHHMM($r['ora_al'] ?? '')) . '</td>
    </tr>';
  }

  $html .= '</tbody></table>';
}

$noteRich = trim((string)($row['note_richiedente'] ?? ''));
if ($noteRich !== '') {
  $html .= '<br><h4 style="margin:0;">Note del richiedente</h4><div>' . nl2br(htmlspecialchars($noteRich)) . '</div>';
}

$noteSegr = trim((string)($row['note_segreteria'] ?? ''));
if ($noteSegr !== '') {
  $html .= '<br><h4 style="margin:0;">Note segreteria</h4><div>' . nl2br(htmlspecialchars($noteSegr)) . '</div>';
}

$pdf->writeHTML($html, true, false, true, false, '');

$filename = 'permesso_' . $id . '.pdf';

// scarica
$pdf->Output($filename, 'D'); // D = download, I = inline
exit;

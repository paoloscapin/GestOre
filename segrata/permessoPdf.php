<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente','segreteria-ata','personale-ata');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { http_response_code(400); exit('Missing id'); }

// carico richiesta + dipendente + tipo
$row = dbGetFirst("
  SELECT
    r.*,
    t.codice AS tipo_codice, t.descrizione AS tipo_descrizione,
    p.cognome, p.nome, p.email, p.matricola
  FROM permesso_ata_richiesta r
  JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
  JOIN personale_ata p ON p.id = r.personale_ata_id
  WHERE r.id = $id
  LIMIT 1
");
if (!$row) { http_response_code(404); exit('Not found'); }

// permessi: se personale-ata può vedere solo i suoi
$ruolo = (string)($__utente_ruolo ?? $session->get('utente_ruolo') ?? '');
if ($ruolo === 'personale-ata') {
  if (intval($row['personale_ata_id']) !== intval($__ata_id)) {
    http_response_code(403); exit('Forbidden');
  }
}

$righe = dbGetAll("
  SELECT id, data_dal, data_al, ora_dal, ora_al
  FROM permesso_ata_richiesta_riga
  WHERE permesso_ata_richiesta_id = $id
  ORDER BY id ASC
");

// === TCPDF ===
require_once __DIR__ . '/../common/tcpdf/tcpdf.php'; // <-- ADATTA PATH al tuo TCPDF

$pdf = new TCPDF('P','mm','A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('ITT Buonarroti Trento');
$pdf->SetTitle('Permesso ATA #'.$id);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$ferieSottotipo = trim((string)($row['ferie_sottotipo'] ?? ''));
$tipoTxt = $row['tipo_codice'].' - '.$row['tipo_descrizione'];
if ($row['tipo_codice'] === 'FERIE' && $ferieSottotipo !== '') {
  $tipoTxt .= ' ('.$ferieSottotipo.')';
}

$html = '
<h2 style="margin:0;">Richiesta Permesso ATA</h2>
<hr>
<table cellpadding="4" border="0">
  <tr><td width="28%"><b>ID</b></td><td width="72%">'.intval($row['id']).'</td></tr>
  <tr><td><b>Dipendente</b></td><td>'.htmlspecialchars($row['cognome'].' '.$row['nome']).' ('.htmlspecialchars($row['matricola']).')</td></tr>
  <tr><td><b>Email</b></td><td>'.htmlspecialchars($row['email']).'</td></tr>
  <tr><td><b>Tipo</b></td><td>'.htmlspecialchars($tipoTxt).'</td></tr>
  <tr><td><b>Stato</b></td><td>'.htmlspecialchars($row['stato']).'</td></tr>
  <tr><td><b>Creato</b></td><td>'.htmlspecialchars($row['created_at']).'</td></tr>
  <tr><td><b>Aggiornato</b></td><td>'.htmlspecialchars($row['updated_at']).'</td></tr>
</table>
<br>
<h4 style="margin:0;">Intervalli</h4>
<table border="1" cellpadding="4">
  <tr>
    <th width="25%"><b>Dal</b></th>
    <th width="25%"><b>Al</b></th>
    <th width="25%"><b>Ora dal</b></th>
    <th width="25%"><b>Ora al</b></th>
  </tr>';

foreach ($righe as $r) {
  $html .= '<tr>
    <td>'.htmlspecialchars($r['data_dal']).'</td>
    <td>'.htmlspecialchars($r['data_al']).'</td>
    <td>'.htmlspecialchars($r['ora_dal'] ?? '').'</td>
    <td>'.htmlspecialchars($r['ora_al'] ?? '').'</td>
  </tr>';
}
$html .= '</table>';

$noteRich = trim((string)($row['note_richiedente'] ?? ''));
if ($noteRich !== '') {
  $html .= '<br><h4 style="margin:0;">Note del richiedente</h4><div>'.nl2br(htmlspecialchars($noteRich)).'</div>';
}

$noteSegr = trim((string)($row['note_segreteria'] ?? ''));
if ($noteSegr !== '') {
  $html .= '<br><h4 style="margin:0;">Note segreteria</h4><div>'.nl2br(htmlspecialchars($noteSegr)).'</div>';
}

$html .= '<br><br><div style="font-size:10px;color:#666;">
Documento generato da GestOre - '.$_SERVER['HTTP_HOST'].' - '.date('d/m/Y H:i').'
</div>';

$pdf->writeHTML($html, true, false, true, false, '');

$filename = 'permesso_'.$id.'.pdf';

// scarica
$pdf->Output($filename, 'D'); // D = download, I = inline
exit;

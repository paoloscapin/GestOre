<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';
require_once '../common/__Log.php';
require_once '../common/__Settings.php';

ruoloRichiesto('dirigente','segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

global $__con;

function hMail($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmtDateITMail(?string $ymd): string
{
  $ymd = trim((string)$ymd);
  if ($ymd === '') return '';
  $dt = DateTime::createFromFormat('Y-m-d', $ymd);
  if ($dt) return $dt->format('d/m/Y');
  $ts = strtotime($ymd);
  return $ts ? date('d/m/Y', $ts) : $ymd;
}

function formatDateRangeMail($dataDa, $dataA, $oraDa = null, $oraA = null): string
{
  $dataDa = trim((string)$dataDa);
  $dataA  = trim((string)$dataA);
  $oraDa  = trim((string)$oraDa);
  $oraA   = trim((string)$oraA);

  $txt = '';
  if ($dataDa !== '' && $dataA !== '' && $dataDa !== $dataA) {
    $txt = fmtDateITMail($dataDa) . ' - ' . fmtDateITMail($dataA);
  } else {
    $txt = fmtDateITMail($dataDa ?: $dataA);
  }

  if ($oraDa !== '' && $oraA !== '') {
    $txt .= ' dalle ' . substr($oraDa, 0, 5) . ' alle ' . substr($oraA, 0, 5);
  }

  return $txt;
}

function buildPermessoEsitoMailHtml($nomeCompleto, $tipoCodice, $tipoDescrizione, $stato, array $righe, $noteRichiedente, $noteSegreteria, $toName = ''): string
{
  $titolo = $tipoDescrizione !== '' ? $tipoDescrizione : $tipoCodice;
  $stato = strtoupper(trim((string)$stato));

  $theme = 'default';
  if ($tipoCodice === 'LEGGE_104') {
    $theme = 'docente';
  } elseif ($tipoCodice === 'RECUPERO_ORE') {
    $theme = 'mbapp';
  }

  $headerTitle = 'ESITO RICHIESTA PERMESSO';
  $intro = 'La tua richiesta è stata aggiornata in <b>GestOre</b>.';
  $footer = 'Messaggio automatico da <b>GestOre</b>.';
  $badgeHtml = badge('AGGIORNATA', '#dbeafe', '#1e3a8a');
  $esitoLabel = 'Aggiornata';

  if ($stato === 'APPROVATO') {
    $headerTitle = 'PERMESSO APPROVATO';
    $intro = 'La tua richiesta è stata <b>approvata</b> dalla segreteria.';
    $footer = 'Messaggio automatico da <b>GestOre</b>.';
    $badgeHtml = badge('APPROVATO', '#dcfce7', '#14532d');
    $esitoLabel = 'Approvato';
  } elseif ($stato === 'RESPINTO') {
    $headerTitle = 'PERMESSO RESPINTO';
    $intro = 'La tua richiesta è stata <b>respinta</b> dalla segreteria.';
    $footer = 'Messaggio automatico da <b>GestOre</b>.';
    $badgeHtml = badge('RESPINTO', '#fee2e2', '#7f1d1d');
    $esitoLabel = 'Respinto';
    $theme = 'annullamento';
  }

  $rowsHtml = '';
  foreach ($righe as $r) {
    $rowsHtml .= '
      <tr>
        <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . hMail($r['unita'] ?? '') . '</td>
        <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . hMail(formatDateRangeMail(
          $r['data_dal'] ?? '',
          $r['data_al'] ?? '',
          $r['ora_dal'] ?? '',
          $r['ora_al'] ?? ''
        )) . '</td>
      </tr>';
  }

  $content = '
    <div style="margin:0 0 12px 0;">
      ' . $badgeHtml . '
    </div>

    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        ' . kvRow('Dipendente', $nomeCompleto) . '
        ' . kvRow('Tipologia', $titolo) . '
        ' . kvRow('Esito', $esitoLabel) . '
      </table>
    </div>

    <div style="margin-top:14px;">
      <div style="font-weight:900;font-size:14px;margin:0 0 8px 0;">Dettaglio richiesta</div>
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <thead style="background:#f8fafc;">
          <tr>
            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Unità</th>
            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Periodo / orario</th>
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
      </table>
    </div>
  ';

  if (trim((string)$noteRichiedente) !== '') {
    $content .= '
      <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
        <div style="font-weight:800;color:#111827;margin-bottom:6px;">Note del richiedente</div>
        <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($noteRichiedente)) . '</div>
      </div>
    ';
  }

  if (trim((string)$noteSegreteria) !== '') {
    $content .= '
      <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
        <div style="font-weight:800;color:#111827;margin-bottom:6px;">Nota della segreteria</div>
        <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($noteSegreteria)) . '</div>
      </div>
    ';
  }

  return mailWrap(
    $headerTitle,
    $toName !== '' ? $toName : $nomeCompleto,
    $intro,
    $content,
    $footer,
    $theme
  );
}

if (!isset($_POST['id'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Missing id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = intval($_POST['id']);
$note_segr = isset($_POST['note_segreteria']) ? trim((string)$_POST['note_segreteria']) : '';
$stato = isset($_POST['stato']) ? strtoupper(trim((string)$_POST['stato'])) : null;

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'ID non valido'], JSON_UNESCAPED_UNICODE);
  exit;
}

$chk = dbGetFirst("SELECT id FROM permesso_ata_richiesta WHERE id = $id LIMIT 1");
if (!$chk) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Richiesta non trovata'], JSON_UNESCAPED_UNICODE);
  exit;
}

$richiesta = dbGetFirst("
  SELECT
    r.*,
    t.codice AS tipo_codice,
    t.descrizione AS tipo_descrizione,
    p.nome,
    p.cognome,
    p.email
  FROM permesso_ata_richiesta r
  JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
  JOIN personale_ata p ON p.id = r.personale_ata_id
  WHERE r.id = $id
  LIMIT 1
");

if (!$richiesta) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Richiesta non trovata'], JSON_UNESCAPED_UNICODE);
  exit;
}

$righe = dbGetAll("
  SELECT data_dal, data_al, ora_dal, ora_al, dettagli_json
  FROM permesso_ata_richiesta_riga
  WHERE permesso_ata_richiesta_id = $id
  ORDER BY data_dal, ora_dal, id
");
if (!is_array($righe)) {
  $righe = [];
}

$note_esc = mysqli_real_escape_string($__con, $note_segr);
$gestito_da = (isset($__utente_id) && intval($__utente_id) > 0) ? intval($__utente_id) : "NULL";

/**
 * 🔹 CASO 1: SOLO NOTE (FERIE)
 */
if ($stato === null) {

  $query = "
    UPDATE permesso_ata_richiesta
    SET
      note_segreteria = '$note_esc',
      updated_at = NOW()
    WHERE id = $id
    LIMIT 1
  ";

} else {

  /**
   * 🔹 CASO 2: STATO + NOTE (MODAL STANDARD)
   */
  $allowed = ['INVIATO','APPROVATO','RESPINTO','ANNULLATO'];
  if (!in_array($stato, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Stato non valido'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stato_esc = mysqli_real_escape_string($__con, $stato);

  $query = "
    UPDATE permesso_ata_richiesta
    SET
      stato = '$stato_esc',
      note_segreteria = '$note_esc',
      gestito_da_utente_id = $gestito_da,
      gestito_il = NOW(),
      updated_at = NOW()
    WHERE id = $id
    LIMIT 1
  ";
}

$ok = dbExec($query);

if ($ok === false) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => mysqli_error($__con)], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($stato !== null && in_array($stato, ['APPROVATO', 'RESPINTO'], true)) {
  $nomeCompleto = trim((string)($richiesta['cognome'] ?? '') . ' ' . (string)($richiesta['nome'] ?? ''));
  $emailUtente = trim((string)($richiesta['email'] ?? ''));

  if ($emailUtente !== '') {
    $subject = ($stato === 'APPROVATO')
      ? "GestOre - Permesso approvato: " . (string)($richiesta['tipo_descrizione'] ?? $richiesta['tipo_codice'])
      : "GestOre - Permesso respinto: " . (string)($richiesta['tipo_descrizione'] ?? $richiesta['tipo_codice']);

    $body = buildPermessoEsitoMailHtml(
      $nomeCompleto,
      (string)($richiesta['tipo_codice'] ?? ''),
      (string)($richiesta['tipo_descrizione'] ?? ''),
      $stato,
      $righe,
      (string)($richiesta['note_richiedente'] ?? ''),
      $note_segr,
      $nomeCompleto
    );

    $mailOk = sendMail($emailUtente, $nomeCompleto, $subject, $body);
    info("permessoUpdateSegreteria.php: mail esito id=$id stato=$stato to=$emailUtente esito=" . ($mailOk ? 'OK' : 'KO'));
  }
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
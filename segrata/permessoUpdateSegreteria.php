<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';
require_once '../common/__Log.php';
require_once '../common/__Settings.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

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
  } elseif ($oraDa !== '') {
    $txt .= ' dalle ' . substr($oraDa, 0, 5);
  }

  return $txt;
}

function buildFerieEsitoMailHtml($nomeCompleto, $sottotipo, $statoRichiesta, array $giorniRows, $noteRichiedente, $notaApprovatore, $toName = ''): string
{
  $statoRichiesta = strtoupper(trim((string)$statoRichiesta));

  $headerTitle = 'ESITO RICHIESTA FERIE';
  $intro = 'La tua richiesta ferie è stata aggiornata in <b>GestOre</b>.';
  $footer = 'Messaggio automatico da <b>GestOre</b>.';
  $badgeHtml = badge('AGGIORNATA', '#dbeafe', '#1e3a8a');
  $theme = 'warning';
  $esitoLabel = 'Aggiornata';

  if ($statoRichiesta === 'APPROVATO') {
    $headerTitle = 'FERIE APPROVATE';
    $intro = 'La tua richiesta ferie è stata <b>approvata</b>.';
    $footer = 'Messaggio automatico da <b>GestOre</b>.';
    $badgeHtml = badge('APPROVATA', '#dcfce7', '#14532d');
    $esitoLabel = 'Approvata';
  } elseif ($statoRichiesta === 'RESPINTO') {
    $headerTitle = 'FERIE RESPINTE';
    $intro = 'La tua richiesta ferie è stata <b>respinta</b>.';
    $footer = 'Messaggio automatico da <b>GestOre</b>.';
    $badgeHtml = badge('RESPINTA', '#fee2e2', '#7f1d1d');
    $theme = 'annullamento';
    $esitoLabel = 'Respinta';
  } elseif ($statoRichiesta === 'PARZIALE') {
    $headerTitle = 'FERIE PARZIALMENTE AGGIORNATE';
    $intro = 'La tua richiesta ferie è stata <b>aggiornata parzialmente</b>: alcuni giorni risultano approvati o respinti, altri possono essere ancora in attesa.';
    $footer = 'Messaggio automatico da <b>GestOre</b>. Verifica il dettaglio della richiesta.';
    $badgeHtml = badge('PARZIALE', '#fef3c7', '#92400e');
    $theme = 'warning';
    $esitoLabel = 'Parziale';
  }

  $rowsHtml = '';
  $cntRichiesti = 0;
  $cntApprovati = 0;
  $cntRespinti = 0;

  foreach ($giorniRows as $r) {
    $det = [];
    if (!empty($r['dettagli_json'])) {
      $tmp = json_decode($r['dettagli_json'], true);
      if (is_array($tmp)) {
        $det = $tmp;
      }
    }

    $sg = strtoupper((string)($det['stato_giorno'] ?? 'RICHIESTO'));
    if ($sg === 'APPROVATO') $cntApprovati++;
    elseif ($sg === 'RESPINTO') $cntRespinti++;
    else $cntRichiesti++;

    $label = 'Richiesto';
    if ($sg === 'APPROVATO') $label = 'Approvato';
    elseif ($sg === 'RESPINTO') $label = 'Respinto';

    $rowsHtml .= '
          <tr>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . hMail(fmtDateITMail($r['data_dal'] ?? '')) . '</td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . hMail($label) . '</td>
          </tr>';
  }

  $content = '
      <div style="margin:0 0 12px 0;">
        ' . $badgeHtml . '
      </div>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
          ' . kvRow('Dipendente', $nomeCompleto) . '
          ' . kvRow('Tipologia ferie', $sottotipo) . '
          ' . kvRow('Esito', $esitoLabel) . '
          ' . kvRow('Giorni approvati', (string)$cntApprovati) . '
          ' . kvRow('Giorni respinti', (string)$cntRespinti) . '
          ' . kvRow('Giorni in attesa', (string)$cntRichiesti) . '
        </table>
      </div>

      <div style="margin-top:14px;">
        <div style="font-weight:900;font-size:14px;margin:0 0 8px 0;">Dettaglio giorni</div>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Data</th>
              <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Stato giorno</th>
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

  if (trim((string)$notaApprovatore) !== '') {
    $content .= '
          <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
            <div style="font-weight:800;color:#111827;margin-bottom:6px;">Nota della segreteria</div>
            <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($notaApprovatore)) . '</div>
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
  } elseif ($stato === 'PARZIALE' || $stato === 'APPROVATO_PARZIALE') {
    $headerTitle = 'RICHIESTA PARZIALMENTE AGGIORNATA';
    $intro = 'La tua richiesta è stata <b>aggiornata parzialmente</b> dalla segreteria.';
    $footer = 'Messaggio automatico da <b>GestOre</b>. Verifica il dettaglio della richiesta.';
    $badgeHtml = badge('PARZIALE', '#fef3c7', '#92400e');
    $esitoLabel = 'Parziale';
    $theme = 'warning';
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
$finalizzaFerie = intval($_POST['finalizza_ferie'] ?? 0) === 1;
$registratoSegreteria = isset($_POST['registrato_segreteria'])
  ? intval($_POST['registrato_segreteria'])
  : null;

if ($registratoSegreteria !== null) {
  $registratoSegreteria = $registratoSegreteria ? 1 : 0;
}
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
 * CASO 1: SOLO NOTE / SALVATAGGIO FERIE
 */
/**
 * COSTRUZIONE UPDATE UNIFICATO
 * - note segreteria sempre salvabili
 * - stato solo se passato
 * - registrazione segreteria solo se passato il flag
 */
$setParts = [];
$setParts[] = "note_segreteria = '$note_esc'";
$setParts[] = "updated_at = NOW()";

// Se arriva uno stato, aggiorno anche gestione pratica
if ($stato !== null) {
  $allowed = ['INVIATO', 'APPROVATO', 'RESPINTO', 'ANNULLATO'];
  if (!in_array($stato, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Stato non valido'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stato_esc = mysqli_real_escape_string($__con, $stato);
  $setParts[] = "stato = '$stato_esc'";
  $setParts[] = "gestito_da_utente_id = $gestito_da";
  $setParts[] = "gestito_il = NOW()";
}

// Se arriva il flag registrazione, aggiorno lo stato interno segreteria
if ($registratoSegreteria !== null) {
  $setParts[] = "registrato_segreteria = " . intval($registratoSegreteria);

  if ($registratoSegreteria === 1) {
    $setParts[] = "registrato_da_utente_id = $gestito_da";
    $setParts[] = "registrato_il = NOW()";
  } else {
    $setParts[] = "registrato_da_utente_id = NULL";
    $setParts[] = "registrato_il = NULL";
  }
}

$query = "
  UPDATE permesso_ata_richiesta
  SET
    " . implode(",\n    ", $setParts) . "
  WHERE id = $id
  LIMIT 1
";

$ok = dbExec($query);

if ($ok === false) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => mysqli_error($__con)], JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * Rileggo la richiesta aggiornata per usare i dati finali reali
 */
$richiestaAgg = dbGetFirst("
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

if (!$richiestaAgg) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore rilettura richiesta aggiornata'], JSON_UNESCAPED_UNICODE);
  exit;
}

$tipoCodice = strtoupper(trim((string)($richiestaAgg['tipo_codice'] ?? '')));
$statoCorrente = strtoupper(trim((string)($richiestaAgg['stato'] ?? '')));
$noteCorrenti = trim((string)($richiestaAgg['note_segreteria'] ?? ''));

$sendMail = false;

/**
 * FERIE:
 * la mail parte solo dal salvataggio finale del modal ferie
 */
if ($tipoCodice === 'FERIE' && $finalizzaFerie) {
  $sendMail = true;
}

/**
 * PERMESSI STANDARD:
 * la mail parte solo quando si imposta APPROVATO / RESPINTO
 */
if ($tipoCodice !== 'FERIE' && $stato !== null && in_array($stato, ['APPROVATO', 'RESPINTO'], true)) {
  $sendMail = true;
}

if ($sendMail) {
  $nomeCompleto = trim((string)($richiestaAgg['cognome'] ?? '') . ' ' . (string)($richiestaAgg['nome'] ?? ''));
  $emailUtente = trim((string)($richiestaAgg['email'] ?? ''));

  if ($tipoCodice === 'FERIE') {
    $sottotipoMail = (string)($richiestaAgg['ferie_sottotipo'] ?? 'FERIE');

    if ($statoCorrente === 'APPROVATO') {
      $subject = "GestOre - Ferie approvate: " . $sottotipoMail;
    } elseif ($statoCorrente === 'RESPINTO') {
      $subject = "GestOre - Ferie respinte: " . $sottotipoMail;
    } elseif ($statoCorrente === 'PARZIALE') {
      $subject = "GestOre - Ferie aggiornate parzialmente: " . $sottotipoMail;
    } else {
      $subject = "GestOre - Aggiornamento ferie: " . $sottotipoMail;
    }
  } else {
    $subject = "GestOre - Aggiornamento richiesta: " . (string)($richiestaAgg['tipo_descrizione'] ?? $richiestaAgg['tipo_codice']);
  }

  if ($tipoCodice === 'FERIE') {
    $body = buildFerieEsitoMailHtml(
      $nomeCompleto,
      (string)($richiestaAgg['ferie_sottotipo'] ?? ''),
      $statoCorrente,
      $righe,
      (string)($richiestaAgg['note_richiedente'] ?? ''),
      $noteCorrenti,
      $nomeCompleto
    );
  } else {
    $body = buildPermessoEsitoMailHtml(
      $nomeCompleto,
      (string)($richiestaAgg['tipo_codice'] ?? ''),
      (string)($richiestaAgg['tipo_descrizione'] ?? ''),
      $statoCorrente,
      $righe,
      (string)($richiestaAgg['note_richiedente'] ?? ''),
      $noteCorrenti,
      $nomeCompleto
    );
  }
  $mailOk = sendMail($emailUtente, $nomeCompleto, $subject, $body);
  info("permessoUpdateSegreteria.php: mail id=$id tipo=$tipoCodice stato=$statoCorrente finalizzaFerie=" . ($finalizzaFerie ? '1' : '0') . " esito=" . ($mailOk ? 'OK' : 'KO'));
} else {
  warning("permessoUpdateSegreteria.php: nessuna mail inviata per id=$id, email utente vuota");
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);

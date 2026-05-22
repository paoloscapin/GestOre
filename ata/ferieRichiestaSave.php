<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';
require_once '../common/__Log.php';
require_once '../common/__Settings.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

$richiesta_id = isset($_POST['richiesta_id']) ? intval($_POST['richiesta_id']) : 0;
$tipo_id      = isset($_POST['permesso_tipo_id']) ? intval($_POST['permesso_tipo_id']) : 0;
$note         = isset($_POST['note']) ? trim((string)$_POST['note']) : '';
$azione       = isset($_POST['azione']) ? strtoupper(trim((string)$_POST['azione'])) : 'BOZZA';
$giorni_json  = isset($_POST['giorni_json']) ? (string)$_POST['giorni_json'] : '[]';
$sottotipo    = isset($_POST['ferie_sottotipo']) ? strtoupper(trim((string)$_POST['ferie_sottotipo'])) : '';
$MAIL_TEST_OVERRIDE = '';

function fail($msg)
{
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

function historicalPriorityUserSave($stato)
{
  $stato = strtoupper(trim((string)$stato));
  if ($stato === 'APPROVATO') return 400;
  if ($stato === 'AGGIUNTO') return 300;
  if ($stato === 'RICHIESTO') return 300;
  if ($stato === 'RESPINTO')  return 200;
  if ($stato === 'BOZZA')     return 100;
  return 0;
}

function fmtDateIT($ymd)
{
  $ymd = trim((string)$ymd);
  if ($ymd === '') return '';
  $ts = strtotime($ymd);
  return $ts ? date('d/m/Y', $ts) : $ymd;
}

function normalizeDateListSave($items)
{
  $out = [];
  if (!is_array($items)) return $out;

  foreach ($items as $item) {
    $data = trim((string)$item);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
      $out[$data] = true;
    }
  }

  $list = array_keys($out);
  sort($list);
  return $list;
}

function diffDateListsSave(array $base, array $current)
{
  $baseMap = array_fill_keys($base, true);
  $currentMap = array_fill_keys($current, true);

  $added = [];
  foreach ($current as $data) {
    if (!isset($baseMap[$data])) $added[] = $data;
  }

  $removed = [];
  foreach ($base as $data) {
    if (!isset($currentMap[$data])) $removed[] = $data;
  }

  sort($added);
  sort($removed);

  return [$added, $removed];
}

function formatDateListITSave(array $dates)
{
  $out = [];
  foreach ($dates as $date) {
    $out[] = fmtDateIT($date);
  }
  return $out;
}

function appendTimelineSave(array $details, string $action, string $label, string $note = '')
{
  $timeline = is_array($details['timeline'] ?? null) ? $details['timeline'] : [];
  $now = date('Y-m-d H:i:s');
  $timeline[] = [
    'action' => $action,
    'label' => $label,
    'note' => $note,
    'at' => $now,
    'at_fmt' => date('d/m/Y H:i', strtotime($now)),
    'user_id' => intval($GLOBALS['__utente_id'] ?? 0),
    'user_label' => trim((string)($GLOBALS['__utente_cognome'] ?? '') . ' ' . (string)($GLOBALS['__utente_nome'] ?? '')),
  ];
  if (count($timeline) > 20) {
    $timeline = array_slice($timeline, -20);
  }
  $details['timeline'] = $timeline;
  return $details;
}

function hMail($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function buildFerieRichiestaMailHtml($nomeCompleto, $sottotipo, array $giorniValidi, $note, $toName = ''): string
{
  $rowsHtml = '';
  foreach ($giorniValidi as $g) {
    $rowsHtml .= '
      <tr>
        <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . hMail(fmtDateIT($g)) . '</td>
      </tr>';
  }

  $content = '
    <div style="margin:0 0 12px 0;">
      ' . badge('RICHIESTA FERIE', '#dcfce7', '#14532d') . '
    </div>

    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        ' . kvRow('Dipendente', $nomeCompleto) . '
        ' . kvRow('Tipologia ferie', $sottotipo) . '
        ' . kvRow('Numero giorni', (string)count($giorniValidi)) . '
      </table>
    </div>

    <div style="margin-top:14px;">
      <div style="font-weight:900;font-size:14px;margin:0 0 8px 0;">Giorni richiesti</div>
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <thead style="background:#f8fafc;">
          <tr>
            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Data</th>
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
      </table>
    </div>
  ';

  if (trim((string)$note) !== '') {
    $content .= '
      <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
        <div style="font-weight:800;color:#111827;margin-bottom:6px;">Note del richiedente</div>
        <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($note)) . '</div>
      </div>
    ';
  }

  $intro = "La tua richiesta ferie è stata registrata correttamente in <b>GestOre</b>.";
  $footer = "Messaggio automatico da <b>GestOre</b>.";

  return mailWrap(
    "RICHIESTA FERIE",
    $toName !== '' ? $toName : $nomeCompleto,
    $intro,
    $content,
    $footer,
    'warning'
  );
}

function buildFerieRichiestaSegreteriaMailHtml($nomeCompleto, $emailUtente, $sottotipo, array $giorniValidi, $note, $toName = ''): string
{
  $rowsHtml = '';
  foreach ($giorniValidi as $g) {
    $rowsHtml .= '
      <tr>
        <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . hMail(fmtDateIT($g)) . '</td>
      </tr>';
  }

  $content = '
    <div style="margin:0 0 12px 0;">
      ' . badge('NUOVA RICHIESTA FERIE', '#fef3c7', '#92400e') . '
    </div>

    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        ' . kvRow('Dipendente', $nomeCompleto) . '
        ' . kvRow('Email utente', ($emailUtente !== '' ? $emailUtente : '—')) . '
        ' . kvRow('Tipologia ferie', $sottotipo) . '
        ' . kvRow('Numero giorni', (string)count($giorniValidi)) . '
      </table>
    </div>

    <div style="margin-top:14px;">
      <div style="font-weight:900;font-size:14px;margin:0 0 8px 0;">Giorni richiesti</div>
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <thead style="background:#f8fafc;">
          <tr>
            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Data</th>
          </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
      </table>
    </div>
  ';

  if (trim((string)$note) !== '') {
    $content .= '
      <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
        <div style="font-weight:800;color:#111827;margin-bottom:6px;">Note del richiedente</div>
        <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($note)) . '</div>
      </div>
    ';
  }

  $intro = "È stata inviata una <b>nuova richiesta ferie</b> su GestOre e richiede verifica da parte della segreteria.";
  $footer = "Messaggio automatico da <b>GestOre</b>. Accedere al pannello segreteria per la gestione della richiesta.";

  return mailWrap(
    "NUOVA RICHIESTA FERIE",
    $toName !== '' ? $toName : 'Segreteria ATA Permessi',
    $intro,
    $content,
    $footer,
    'warning'
  );
}

function isValidYmd($ymd)
{
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return false;
  $dt = DateTime::createFromFormat('Y-m-d', $ymd);
  return $dt && $dt->format('Y-m-d') === $ymd;
}

function isWeekendYmd($ymd)
{
  $ts = strtotime($ymd);
  if ($ts === false) return false;
  $w = (int)date('N', $ts);
  return ($w >= 6);
}

$allowedSottotipi = ['ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA', 'ORDINARIE'];

if ($tipo_id <= 0) fail('Tipo permesso non valido.');
if (!in_array($azione, ['BOZZA', 'INVIA', 'AGGIORNA'], true)) fail('Azione non valida.');
if (!in_array($sottotipo, $allowedSottotipi, true)) fail('Sottotipo ferie non valido.');

$tipo = dbGetFirst("
  SELECT id, codice
  FROM permesso_ata_tipo
  WHERE id = $tipo_id
    AND codice = 'FERIE'
    AND (valido IS NULL OR valido = 1)
  LIMIT 1
");
if (!$tipo) fail('Tipo FERIE non valido.');

$finestra = dbGetFirst("
  SELECT data_inizio, data_fine
  FROM permesso_ata_ferie_finestra
  WHERE UPPER(TRIM(codice)) = " . dbQ($sottotipo) . "
    AND (valido IS NULL OR valido = 1)
  LIMIT 1
");
if (!$finestra) fail('Finestra ferie ' . $sottotipo . ' non configurata.');

$finestreEscluseOrdinarie = [];

if ($sottotipo === 'ORDINARIE') {
  $finestreEscluseOrdinarie = dbGetAll("
    SELECT codice, data_inizio, data_fine
    FROM permesso_ata_ferie_finestra
    WHERE UPPER(TRIM(codice)) IN ('ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA')
      AND (valido IS NULL OR valido = 1)
  ");

  if (!is_array($finestreEscluseOrdinarie)) {
    $finestreEscluseOrdinarie = [];
  }
}

$specialRows = dbGetAll("
  SELECT data_giorno, tipo, descrizione
  FROM permesso_ata_ferie_giorni_speciali
  WHERE UPPER(TRIM(sottotipo)) = " . dbQ($sottotipo) . "
    AND (valido IS NULL OR valido = 1)
");

$specialExclude = [];
$specialInclude = [];

if (is_array($specialRows)) {
  foreach ($specialRows as $r) {
    $d = (string)$r['data_giorno'];
    $tipoSpeciale = strtoupper((string)$r['tipo']);

    if ($tipoSpeciale === 'ESCLUDI') {
      $specialExclude[$d] = (string)($r['descrizione'] ?? '');
    }

    if ($tipoSpeciale === 'INCLUDI') {
      $specialInclude[$d] = true;
    }
  }
}
$giorni = json_decode($giorni_json, true);
if (!is_array($giorni) || count($giorni) === 0) {
  fail('Seleziona almeno un giorno di ferie.');
}

$giorniValidi = [];
$seen = [];

foreach ($giorni as $g) {
  $data = trim((string)$g);

  if ($data === '' || !isValidYmd($data)) {
    fail('È presente una data non valida nella selezione.');
  }

  if (isset($seen[$data])) continue;
  $seen[$data] = true;

  if ($data < $finestra['data_inizio'] || $data > $finestra['data_fine']) {
    fail('La data ' . fmtDateIT($data) . ' è fuori dalla finestra ferie.');
  }

  if ($sottotipo === 'ORDINARIE') {
    foreach ($finestreEscluseOrdinarie as $fw) {
      $da = (string)($fw['data_inizio'] ?? '');
      $a  = (string)($fw['data_fine'] ?? '');
      $cod = strtoupper(trim((string)($fw['codice'] ?? '')));

      if ($da !== '' && $a !== '' && $data >= $da && $data <= $a) {
        fail('La data ' . fmtDateIT($data) . ' ricade nel periodo ferie ' . $cod . ' e non può essere richiesta come ferie ordinarie.');
      }
    }
  }

  if (!isset($specialInclude[$data]) && isWeekendYmd($data)) {
    fail('La data ' . fmtDateIT($data) . ' è sabato o domenica e non può essere selezionata.');
  }

  if (isset($specialExclude[$data])) {
    $desc = trim((string)$specialExclude[$data]);
    fail('La data ' . fmtDateIT($data) . ' non è selezionabile' . ($desc !== '' ? ' (' . $desc . ')' : '') . '.');
  }

  $giorniValidi[] = $data;
}

sort($giorniValidi);
if (count($giorniValidi) === 0) {
  fail('Seleziona almeno un giorno valido.');
}

$storicoRows = dbGetAll("
  SELECT
    r.id AS richiesta_id,
    r.stato AS richiesta_stato,
    rr.id AS riga_id,
    rr.data_dal,
    rr.data_al,
    rr.dettagli_json
  FROM permesso_ata_richiesta r
  INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
  INNER JOIN permesso_ata_richiesta_riga rr ON rr.permesso_ata_richiesta_id = r.id
  WHERE r.personale_ata_id = $__ata_id
    AND t.codice = 'FERIE'
    AND UPPER(TRIM(r.ferie_sottotipo)) = " . dbQ($sottotipo) . "
    AND (" . intval($richiesta_id) . " <= 0 OR r.id <> " . intval($richiesta_id) . ")
");

if (!is_array($storicoRows)) {
  $storicoRows = [];
}

$storicoMap = [];

foreach ($storicoRows as $sr) {
  $det = [];
  if (!empty($sr['dettagli_json'])) {
    $tmp = json_decode($sr['dettagli_json'], true);
    if (is_array($tmp)) $det = $tmp;
  }

  $statoGiorno = strtoupper(trim((string)($det['stato_giorno'] ?? 'RICHIESTO')));
  $richiestaStato = strtoupper(trim((string)($sr['richiesta_stato'] ?? '')));

  if ($statoGiorno === 'RIMOSSO') {
    continue;
  }
  if ($statoGiorno === 'AGGIUNTO') {
    $statoGiorno = 'RICHIESTO';
  }

  if ($statoGiorno === 'RICHIESTO' && $richiestaStato === 'BOZZA') {
    $statoGiorno = 'BOZZA';
  }

  $from = (string)($sr['data_dal'] ?? '');
  $to   = (string)($sr['data_al'] ?? '');
  if ($to === '') $to = $from;

  $range = [];
  $start = DateTime::createFromFormat('Y-m-d', $from);
  $end   = DateTime::createFromFormat('Y-m-d', $to);
  if ($start && $end && $end >= $start) {
    $cur = clone $start;
    while ($cur <= $end) {
      $range[] = $cur->format('Y-m-d');
      $cur->modify('+1 day');
    }
  }

  foreach ($range as $iso) {
    if (!isset($storicoMap[$iso])) {
      $storicoMap[$iso] = $statoGiorno;
    } else {
      if (historicalPriorityUserSave($statoGiorno) >= historicalPriorityUserSave($storicoMap[$iso])) {
        $storicoMap[$iso] = $statoGiorno;
      }
    }
  }
}

foreach ($giorniValidi as $data) {
  if (!isset($storicoMap[$data])) continue;

  $statoStorico = strtoupper(trim((string)$storicoMap[$data]));

  if ($statoStorico === 'APPROVATO') {
    fail('La data ' . fmtDateIT($data) . ' è già stata approvata in una richiesta precedente.');
  }

  if ($statoStorico === 'RESPINTO') {
    fail('La data ' . fmtDateIT($data) . ' è già stata respinta e non può essere selezionata.');
  }

  if ($statoStorico === 'BOZZA') {
    fail('La data ' . fmtDateIT($data) . ' è già presente in un\'altra bozza ferie.');
  }

  fail('La data ' . fmtDateIT($data) . ' è già presente in una richiesta ferie.');
}

$stato = ($azione === 'INVIA') ? 'INVIATO' : (($azione === 'AGGIORNA') ? 'AGGIORNATA' : 'BOZZA');

dbExec("START TRANSACTION");

try {
  $isAggiornamentoInviata = false;
  $isModificaDopoApprovazione = false;
  $statoCorrente = '';
  $detailsCorrenti = [];
  $giorniOriginali = [];
  $giorniAggiunti = [];
  $giorniRimossi = [];
  $righeDaInserire = [];
  $statiGiorniCorrenti = [];

  if ($richiesta_id > 0) {
    $chk = dbGetFirst("
      SELECT r.id, r.stato, r.dettagli_json, r.registrato_segreteria
      FROM permesso_ata_richiesta r
      INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
      WHERE r.id = $richiesta_id
        AND r.personale_ata_id = $__ata_id
        AND t.codice = 'FERIE'
        AND UPPER(TRIM(r.ferie_sottotipo)) = " . dbQ($sottotipo) . "
      LIMIT 1
    ");

    if (!$chk) throw new Exception('Richiesta non trovata.');

    $statoCorrente = strtoupper(trim((string)$chk['stato']));
    $detailsCorrenti = [];
    if (!empty($chk['dettagli_json'])) {
      $tmp = json_decode((string)$chk['dettagli_json'], true);
      if (is_array($tmp)) $detailsCorrenti = $tmp;
    }

    if ($statoCorrente === 'BOZZA') {
      if ($azione === 'AGGIORNA') {
        throw new Exception('Una bozza va salvata come bozza o inviata.');
      }
      if ($azione === 'INVIA') {
        $giorniOriginali = $giorniValidi;
      }
    } elseif (in_array($statoCorrente, ['INVIATO', 'INVIATA', 'AGGIORNATA', 'MODIFICATA', 'APPROVATO', 'APPROVATA', 'APPROVATO_PARZIALE', 'PARZIALE'], true)) {
      if ($azione !== 'AGGIORNA') {
        throw new Exception('Per una richiesta gia inviata usa Salva aggiornamento.');
      }

      $isAggiornamentoInviata = true;
      $isModificaDopoApprovazione = in_array($statoCorrente, ['APPROVATO', 'APPROVATA', 'APPROVATO_PARZIALE', 'PARZIALE', 'MODIFICATA'], true)
        || intval($chk['registrato_segreteria'] ?? 0) === 1
        || !empty($detailsCorrenti['ultima_modifica_da_approvata']);
      $stato = $isModificaDopoApprovazione ? 'MODIFICATA' : 'AGGIORNATA';
      $existingRows = dbGetAll("
          SELECT data_dal, data_al, dettagli_json
          FROM permesso_ata_richiesta_riga
          WHERE permesso_ata_richiesta_id = $richiesta_id
          ORDER BY data_dal ASC, id ASC
        ");
      if (!is_array($existingRows)) $existingRows = [];

      foreach ($existingRows as $er) {
        $detEr = [];
        if (!empty($er['dettagli_json'])) {
          $tmpEr = json_decode((string)$er['dettagli_json'], true);
          if (is_array($tmpEr)) $detEr = $tmpEr;
        }

        $statoEr = strtoupper(trim((string)($detEr['stato_giorno'] ?? 'RICHIESTO')));

        $fromEr = (string)($er['data_dal'] ?? '');
        $toEr = (string)($er['data_al'] ?? '');
        if ($toEr === '') $toEr = $fromEr;

        $startEr = DateTime::createFromFormat('Y-m-d', $fromEr);
        $endEr = DateTime::createFromFormat('Y-m-d', $toEr);
        if (!$startEr || !$endEr || $endEr < $startEr) continue;

        $curEr = clone $startEr;
        while ($curEr <= $endEr) {
          $dataEr = $curEr->format('Y-m-d');
          $statiGiorniCorrenti[$dataEr] = $statoEr;
          if ($statoEr !== 'RIMOSSO') {
            $giorniOriginali[] = $dataEr;
          }
          $curEr->modify('+1 day');
        }
      }
      $giorniOriginali = normalizeDateListSave($giorniOriginali);

      [$giorniAggiunti, $giorniRimossi] = diffDateListsSave($giorniOriginali, $giorniValidi);
    } else {
      throw new Exception('Questa richiesta non e modificabile.');
    }
  } else {
    if ($azione === 'AGGIORNA') {
      throw new Exception('Non puoi aggiornare una richiesta non ancora creata.');
    }
    if ($azione === 'INVIA') {
      $giorniOriginali = $giorniValidi;
    }
  }

  if ($isAggiornamentoInviata) {
    $unionMap = [];
    foreach ($giorniOriginali as $data) $unionMap[$data] = true;
    foreach ($giorniValidi as $data) $unionMap[$data] = true;
    $unionDates = array_keys($unionMap);
    sort($unionDates);

    $aggiuntiMap = array_fill_keys($giorniAggiunti, true);
    $rimossiMap = array_fill_keys($giorniRimossi, true);

    foreach ($unionDates as $data) {
      $rowState = strtoupper(trim((string)($statiGiorniCorrenti[$data] ?? 'RICHIESTO')));
      if (!in_array($rowState, ['RICHIESTO', 'AGGIUNTO', 'APPROVATO', 'RESPINTO'], true)) {
        $rowState = 'RICHIESTO';
      }
      $variazione = '';
      if (isset($aggiuntiMap[$data])) {
        $rowState = 'AGGIUNTO';
        $variazione = 'AGGIUNTO';
      } elseif (isset($rimossiMap[$data])) {
        $rowState = 'RIMOSSO';
        $variazione = 'RIMOSSO';
      }

      $righeDaInserire[] = [
        'data' => $data,
        'stato_giorno' => $rowState,
        'variazione_modifica' => $variazione
      ];
    }
  } else {
    foreach ($giorniValidi as $data) {
      $righeDaInserire[] = [
        'data' => $data,
        'stato_giorno' => 'RICHIESTO',
        'variazione_modifica' => ''
      ];
    }
  }

  $dettagliRichiesta = is_array($detailsCorrenti) ? $detailsCorrenti : [];
  $dettagliRichiesta = array_merge($dettagliRichiesta, [
    'tipo_codice' => 'FERIE',
    'ferie_sottotipo' => $sottotipo,
    'modo' => 'CALENDARIO_FERIE',
    'giorni_richiesti_count' => count($giorniValidi),
    'giorni_approvati_count' => 0,
    'giorni_respinti_count' => 0,
    'giorni_originali' => $giorniOriginali,
    'giorni_correnti' => $giorniValidi,
    'giorni_aggiunti' => $giorniAggiunti,
    'giorni_rimossi' => $giorniRimossi,
    'giorni_aggiunti_count' => count($giorniAggiunti),
    'giorni_rimossi_count' => count($giorniRimossi),
    'ultima_modifica_dopo_invio' => $isAggiornamentoInviata,
    'ultima_modifica_da_approvata' => $isModificaDopoApprovazione,
    'data_primo_giorno' => $giorniValidi[0],
    'data_ultimo_giorno' => $giorniValidi[count($giorniValidi) - 1],
  ]);

  if ($isAggiornamentoInviata) {
    $noteTimeline = 'Aggiunti: ' . (count($giorniAggiunti) ? implode(', ', formatDateListITSave($giorniAggiunti)) : 'nessuno')
      . ' | Rimossi: ' . (count($giorniRimossi) ? implode(', ', formatDateListITSave($giorniRimossi)) : 'nessuno');
    $dettagliRichiesta = appendTimelineSave(
      $dettagliRichiesta,
      $isModificaDopoApprovazione ? 'FERIE_MODIFICATA_DOPO_APPROVAZIONE' : 'FERIE_AGGIORNATA',
      $isModificaDopoApprovazione ? 'Richiesta ferie modificata dopo approvazione/registrazione' : 'Richiesta ferie aggiornata dal dipendente',
      $noteTimeline
    );
  }
  $dettagliRichiestaJson = json_encode($dettagliRichiesta, JSON_UNESCAPED_UNICODE);

  if ($richiesta_id > 0) {
    $extraUpdateRegistrazione = $isAggiornamentoInviata
      ? ", registrato_segreteria = 0, registrato_da_utente_id = NULL, registrato_il = NULL"
      : "";

    dbExec("
      UPDATE permesso_ata_richiesta
      SET permesso_ata_tipo_id = $tipo_id,
          ferie_sottotipo = " . dbQ($sottotipo) . ",
          stato = " . dbQ($stato) . ",
          note_richiedente = " . dbQ($note) . ",
          dettagli_json = " . dbQ($dettagliRichiestaJson) . ",
          updated_at = NOW()
          $extraUpdateRegistrazione
      WHERE id = $richiesta_id
        AND personale_ata_id = $__ata_id
      LIMIT 1
    ");

    dbExec("DELETE FROM permesso_ata_richiesta_riga WHERE permesso_ata_richiesta_id = $richiesta_id");
  } else {
    dbExec("
      INSERT INTO permesso_ata_richiesta
        (personale_ata_id, permesso_ata_tipo_id, ferie_sottotipo, stato, note_richiedente, dettagli_json, created_at, updated_at)
      VALUES
        ($__ata_id, $tipo_id, " . dbQ($sottotipo) . ", " . dbQ($stato) . ", " . dbQ($note) . ", " . dbQ($dettagliRichiestaJson) . ", NOW(), NOW())
    ");
    $richiesta_id = dblastId();
  }

  foreach ($righeDaInserire as $rigaIns) {
    $data = $rigaIns['data'];
    $statoGiorno = $rigaIns['stato_giorno'];
    $variazione = $rigaIns['variazione_modifica'];

    $dettagliRiga = [
      'unita' => 'GIORNI',
      'modo' => 'CALENDARIO_FERIE',
      'stato_giorno' => $statoGiorno,
      'variazione_modifica' => $variazione,
      'data_originale' => $data,
      'data_definitiva' => $data,
      'nota_approvatore' => '',
    ];

    dbExec("
      INSERT INTO permesso_ata_richiesta_riga
        (permesso_ata_richiesta_id, data_dal, ora_dal, data_al, ora_al, dettagli_json)
      VALUES
        (
          $richiesta_id,
          " . dbQ($data) . ",
          NULL,
          " . dbQ($data) . ",
          NULL,
          " . dbQ(json_encode($dettagliRiga, JSON_UNESCAPED_UNICODE)) . "
        )
    ");
  }

  $ata = dbGetFirst("
  SELECT nome, cognome, email
  FROM personale_ata
  WHERE id = $__ata_id
  LIMIT 1
");

  dbExec("COMMIT");

  if ($stato === 'INVIATO' && $ata && !empty($ata['email'])) {
    $nomeCompleto = trim((string)($ata['cognome'] ?? '') . ' ' . (string)($ata['nome'] ?? ''));
    $destinatarioReale = trim((string)($ata['email'] ?? ''));
    $destinatario = $MAIL_TEST_OVERRIDE ?: $destinatarioReale;

    $subject = "GestOre - Richiesta ferie inviata: " . $sottotipo;

    $body = buildFerieRichiestaMailHtml(
      $nomeCompleto,
      $sottotipo,
      $giorniValidi,
      $note,
      $nomeCompleto
    );

    $mailOk = sendMail($destinatario, $nomeCompleto, $subject, $body);
    info("ferieRichiestaSave.php: mail invio richiesta id=$richiesta_id to_test=$destinatario to_real=$destinatarioReale esito=" . ($mailOk ? 'OK' : 'KO'));


    $segreteriaMail = trim((string)($__settings->segrata->emailSegreteria ?? ''));
    $segreteriaNome = trim((string)($__settings->segrata->destinatariEmail ?? 'Segreteria ATA Permessi'));

    if ($segreteriaMail !== '') {
      $subjectSeg = "GestOre - Nuova richiesta ferie da gestire: " . $nomeCompleto . " - " . $sottotipo;

      $bodySeg = buildFerieRichiestaSegreteriaMailHtml(
        $nomeCompleto,
        $destinatarioReale,
        $sottotipo,
        $giorniValidi,
        $note,
        $segreteriaNome
      );

      $mailSegOk = sendMail($segreteriaMail, $segreteriaNome, $subjectSeg, $bodySeg);
      info("ferieRichiestaSave.php: mail segreteria id=$richiesta_id to=$segreteriaMail esito=" . ($mailSegOk ? 'OK' : 'KO'));
    }
  }
  echo json_encode([
    'ok' => true,
    'id' => $richiesta_id,
    'stato' => $stato,
    'giorni_count' => count($giorniValidi)
  ], JSON_UNESCAPED_UNICODE);
  exit;
} catch (Exception $e) {
  dbExec("ROLLBACK");
  echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}

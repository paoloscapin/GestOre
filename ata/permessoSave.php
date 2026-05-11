<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';
require_once '../common/__Log.php';
require_once '../common/__Settings.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

$richiesta_id    = isset($_POST['permesso_id']) ? intval($_POST['permesso_id']) : 0;
$tipo_id         = isset($_POST['permesso_tipo_id']) ? intval($_POST['permesso_tipo_id']) : 0;
$note            = isset($_POST['note']) ? trim($_POST['note']) : '';
$azione          = isset($_POST['azione']) ? strtoupper(trim($_POST['azione'])) : 'BOZZA';
$righe_json      = isset($_POST['righe_json']) ? $_POST['righe_json'] : '[]';
$MAIL_TEST_OVERRIDE = '';

function hMail($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function formatDateRangeMail($dataDa, $dataA, $oraDa = null, $oraA = null, $durataOre = null): string
{
  $dataDa = trim((string)$dataDa);
  $dataA  = trim((string)$dataA);
  $oraDa  = trim((string)$oraDa);
  $oraA   = trim((string)$oraA);

  $txt = '';
  if ($dataDa !== '' && $dataA !== '' && $dataDa !== $dataA) {
    $txt = fmtDateIT($dataDa) . ' - ' . fmtDateIT($dataA);
  } else {
    $txt = fmtDateIT($dataDa ?: $dataA);
  }

  $durataOre = is_numeric($durataOre) ? (int)$durataOre : 0;

  if ($oraDa !== '' && $durataOre > 0) {
    $txt .= ' dalle ' . substr($oraDa, 0, 5) . ' per ' . $durataOre . ' ore';
  } elseif ($oraDa !== '' && $oraA !== '') {
    $txt .= ' dalle ' . substr($oraDa, 0, 5) . ' alle ' . substr($oraA, 0, 5);
  } elseif ($oraDa !== '') {
    $txt .= ' dalle ' . substr($oraDa, 0, 5);
  }

  return $txt;
}

function buildPermessoRichiestaMailHtml($nomeCompleto, $tipoCodice, $tipoDescrizione, $stato, array $righe, $note, $toName = ''): string
{
  $titolo = $tipoDescrizione !== '' ? $tipoDescrizione : $tipoCodice;

  $theme = 'default';

  if ($tipoCodice === 'FERIE') {
    $theme = 'warning';
  } elseif ($tipoCodice === 'LEGGE_104') {
    $theme = 'docente';
  } elseif ($tipoCodice === 'RECUPERO_ORE') {
    $theme = 'mbapp';
  }
  $stato = strtoupper(trim((string)$stato));

  $headerTitle = "RICHIESTA PERMESSO";
  $intro = "La tua richiesta è stata registrata correttamente in <b>GestOre</b>.";
  $footer = "Messaggio automatico da <b>GestOre</b>.";
  $badgeHtml = badge('RICHIESTA INVIATA', '#dcfce7', '#14532d');

  if ($stato === 'APPROVATO') {
    $headerTitle = "PERMESSO APPROVATO";
    $intro = "La tua richiesta è stata <b>approvata automaticamente</b> in base alla configurazione prevista.";
    $footer = "Messaggio automatico da <b>GestOre</b>. Non è richiesta alcuna ulteriore azione.";
    $badgeHtml = badge('APPROVATA AUTOMATICAMENTE', '#dcfce7', '#14532d');
  } elseif ($stato === 'INVIATO') {
    $headerTitle = "RICHIESTA PERMESSO";
    $intro = "La tua richiesta è stata registrata correttamente in <b>GestOre</b> ed è in attesa di gestione.";
    $footer = "Messaggio automatico da <b>GestOre</b>.";
    $badgeHtml = badge('RICHIESTA INVIATA', '#dcfce7', '#14532d');
  }
  $rowsHtml = '';
  foreach ($righe as $r) {
    $rowsHtml .= '
      <tr>
        <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . hMail($r['unita'] ?? '') . '</td>
        <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . hMail(formatDateRangeMail(
      $r['data_da'] ?? '',
      $r['data_a'] ?? '',
      $r['ora_da'] ?? '',
      $r['ora_a'] ?? '',
      $r['durata_ore'] ?? null
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

  if (trim((string)$note) !== '') {
    $content .= '
      <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
        <div style="font-weight:800;color:#111827;margin-bottom:6px;">Note del richiedente</div>
        <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($note)) . '</div>
      </div>
    ';
  }

  $intro = "La tua richiesta è stata registrata correttamente in <b>GestOre</b>.";
  $footer = "Messaggio automatico da <b>GestOre</b>.";

  return mailWrap(
    $headerTitle,
    $toName !== '' ? $toName : $nomeCompleto,
    $intro,
    $content,
    $footer,
    $theme
  );
}

function buildPermessoRichiestaSegreteriaMailHtml($nomeCompleto, $emailUtente, $tipoCodice, $tipoDescrizione, $stato, array $righe, $note, $toName = ''): string
{
  $titolo = $tipoDescrizione !== '' ? $tipoDescrizione : $tipoCodice;

  $theme = 'default';

  if ($tipoCodice === 'LEGGE_104') {
    $theme = 'docente';
  } elseif ($tipoCodice === 'RECUPERO_ORE') {
    $theme = 'mbapp';
  }

  $stato = strtoupper(trim((string)$stato));

  $headerTitle = "NUOVA RICHIESTA PERMESSO";
  $intro = "È stata inviata una <b>nuova richiesta</b> su GestOre e richiede presa visione da parte della segreteria.";
  $footer = "Messaggio automatico da <b>GestOre</b>. Accedere al pannello segreteria per la gestione della richiesta.";
  $badgeHtml = badge('NUOVA RICHIESTA', '#fef3c7', '#92400e');

  if ($stato === 'APPROVATO') {
    $headerTitle = "PERMESSO AUTO-APPROVATO";
    $intro = "È stata registrata una richiesta su <b>GestOre</b> che risulta <b>approvata automaticamente</b> in base alla configurazione del tipo permesso.";
    $footer = "Messaggio automatico da <b>GestOre</b>. La richiesta è già stata approvata automaticamente; questa email serve come comunicazione e tracciamento.";
    $badgeHtml = badge('AUTO-APPROVATO', '#dcfce7', '#14532d');
  }

  $rowsHtml = '';
  foreach ($righe as $r) {
    $rowsHtml .= '
      <tr>
        <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . hMail($r['unita'] ?? '') . '</td>
        <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . hMail(formatDateRangeMail(
      $r['data_da'] ?? '',
      $r['data_a'] ?? '',
      $r['ora_da'] ?? '',
      $r['ora_a'] ?? '',
      $r['durata_ore'] ?? null
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
        ' . kvRow('Email utente', ($emailUtente !== '' ? $emailUtente : '—')) . '
        ' . kvRow('Tipologia', $titolo) . '
        ' . kvRow('Esito iniziale', ($stato === 'APPROVATO' ? 'Approvato automaticamente' : 'In attesa di gestione')) . '
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

  if (trim((string)$note) !== '') {
    $content .= '
      <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
        <div style="font-weight:800;color:#111827;margin-bottom:6px;">Note del richiedente</div>
        <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($note)) . '</div>
      </div>
    ';
  }

  $intro = "È stata inviata una <b>nuova richiesta</b> su GestOre e richiede presa visione da parte della segreteria.";
  $footer = "Messaggio automatico da <b>GestOre</b>. Accedere al pannello segreteria per la gestione della richiesta.";

  return mailWrap(
    $headerTitle,
    $toName !== '' ? $toName : 'Segreteria ATA Permessi',
    $intro,
    $content,
    $footer,
    $theme
  );
}

function fmtDateIT(?string $ymd): string
{
  $ymd = trim((string)$ymd);
  if ($ymd === '') return '';
  $dt = DateTime::createFromFormat('Y-m-d', $ymd);
  if ($dt) return $dt->format('d/m/Y');
  $ts = strtotime($ymd);
  return $ts ? date('d/m/Y', $ts) : $ymd;
}

function normalizeAtaTimePhp($time): string
{
  $time = trim((string)$time);
  if ($time === '') return '';

  $time = str_replace([',', '.'], ':', $time);
  if (preg_match('/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/', $time, $m)) {
    $h = (int)$m[1];
    $min = (int)$m[2];
  } elseif (preg_match('/^\d{3,4}$/', $time)) {
    $h = (int)(strlen($time) === 3 ? substr($time, 0, 1) : substr($time, 0, 2));
    $min = (int)(strlen($time) === 3 ? substr($time, 1) : substr($time, 2));
  } elseif (preg_match('/^\d{1,2}$/', $time)) {
    $h = (int)$time;
    $min = 0;
  } else {
    return $time;
  }

  if ($h < 0 || $h > 23 || $min < 0 || $min > 59) return $time;
  return sprintf('%02d:%02d', $h, $min);
}

function isValidAtaTime($time): bool
{
  $time = trim((string)$time);
  return $time === '' || preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
}

function parseDurataOre($value): int
{
  $value = trim((string)$value);
  if ($value === '' || preg_match('/^\d+$/', $value) !== 1) return 0;
  return (int)$value;
}

function addHoursToAtaTimePhp(string $oraDa, int $durataOre): string
{
  if (!isValidAtaTime($oraDa) || $oraDa === '' || $durataOre <= 0) return '';

  [$h, $m] = array_map('intval', explode(':', $oraDa));
  $minutes = ($h * 60) + $m + ($durataOre * 60);
  if ($minutes > (23 * 60 + 59)) return '';

  return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function ataPermessoOraRientroFacoltativa(string $tipoCodice, string $tipoDescrizione = ''): bool
{
  $tipoCodice = strtoupper(trim($tipoCodice));
  $tipoDescrizione = strtoupper(trim($tipoDescrizione));
  $testoTipo = $tipoCodice . ' ' . $tipoDescrizione;
  return in_array($tipoCodice, ['VISITA_MEDICA', 'VISITA_SPEC', 'VISITA_SPECIALISTICA', 'PERMESSO_BREVE', 'PERM_BREVE'], true)
    || strpos($testoTipo, 'BREVE') !== false;
}

if ($tipo_id <= 0) {
  echo json_encode(["ok" => false, "error" => "Seleziona il tipo di permesso."], JSON_UNESCAPED_UNICODE);
  exit;
}
if (!in_array($azione, ['BOZZA', 'INVIA'], true)) {
  echo json_encode(["ok" => false, "error" => "Azione non valida."], JSON_UNESCAPED_UNICODE);
  exit;
}

$righe = json_decode($righe_json, true);
if (!is_array($righe) || count($righe) === 0) {
  echo json_encode(["ok" => false, "error" => "Inserisci i dati richiesti."], JSON_UNESCAPED_UNICODE);
  exit;
}

// tipo valido + codice
$tipo = dbGetFirst("
  SELECT codice, descrizione, approvazione_automatica
  FROM permesso_ata_tipo
  WHERE id = $tipo_id
    AND (valido IS NULL OR valido = 1)
  LIMIT 1
");
if (!$tipo) {
  echo json_encode(["ok" => false, "error" => "Tipo permesso non valido."], JSON_UNESCAPED_UNICODE);
  exit;
}
$tipo_codice = (string)$tipo['codice'];
$tipo_descrizione = trim((string)($tipo['descrizione'] ?? ''));
if ($tipo_descrizione === '') {
  $tipo_descrizione = $tipo_codice;
}
$approvazioneAutomatica = (int)($tipo['approvazione_automatica'] ?? 0) === 1;
if ($azione === 'INVIA') {
  $stato = $approvazioneAutomatica ? 'APPROVATO' : 'INVIATO';
} else {
  $stato = 'BOZZA';
}

// VALIDAZIONI + NORMALIZZAZIONE
foreach ($righe as $i => $r) {
  $unita   = isset($r['unita']) ? strtoupper(trim((string)$r['unita'])) : '';
  $data_da = isset($r['data_da']) ? trim((string)$r['data_da']) : '';
  $data_a  = isset($r['data_a']) ? trim((string)$r['data_a']) : '';
  $ora_da  = isset($r['ora_da']) ? normalizeAtaTimePhp($r['ora_da']) : '';
  $ora_a   = isset($r['ora_a']) ? normalizeAtaTimePhp($r['ora_a']) : '';
  $durata_ore = isset($r['durata_ore']) ? parseDurataOre($r['durata_ore']) : 0;

  if (!isValidAtaTime($ora_da) || !isValidAtaTime($ora_a)) {
    echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": inserisci l'orario nel formato HH:MM."], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($tipo_codice === 'RECUPERO_ORE') {

    $unita = 'ORE';
    if ($data_da === '' && $data_a !== '') $data_da = $data_a;
    if ($data_a === '' && $data_da !== '') $data_a = $data_da;

    if ($data_da === '' || $data_a === '') {
      echo json_encode(["ok" => false, "error" => "Inserisci la data del recupero ore."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    if ($data_da !== $data_a) {
      echo json_encode(["ok" => false, "error" => "RECUPERO ORE: inserisci una sola data."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    if ($ora_da === '') {
      echo json_encode(["ok" => false, "error" => "RECUPERO ORE: inserisci l'ora di inizio."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    if ($durata_ore <= 0) {
      echo json_encode(["ok" => false, "error" => "RECUPERO ORE: inserisci un numero intero di ore."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    $ora_a = addHoursToAtaTimePhp($ora_da, $durata_ore);
    if ($ora_a === '') {
      echo json_encode(["ok" => false, "error" => "RECUPERO ORE: la durata supera la giornata."], JSON_UNESCAPED_UNICODE);
      exit;
    }
  } elseif ($tipo_codice === 'LEGGE_104') {

    if (!in_array($unita, ['GIORNI', 'ORE'], true)) {
      echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": unità non valida (GIORNI/ORE)."], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if ($unita === 'GIORNI') {
      if ($data_da === '' || $data_a === '') {
        echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": per GIORNI servono DAL/AL."], JSON_UNESCAPED_UNICODE);
        exit;
      }
      if ($data_da > $data_a) {
        echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": la data DAL non può essere dopo la data AL."], JSON_UNESCAPED_UNICODE);
        exit;
      }
      $ora_da = null;
      $ora_a  = null;
    } else { // ORE
      if ($data_da === '' && $data_a !== '') $data_da = $data_a;
      if ($data_a === '' && $data_da !== '') $data_a = $data_da;

      if ($data_da === '' || $data_a === '') {
        echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": per ORE serve la data."], JSON_UNESCAPED_UNICODE);
        exit;
      }
      if ($data_da !== $data_a) {
        echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": per ORE inserisci una sola data."], JSON_UNESCAPED_UNICODE);
        exit;
      }
      if ($ora_da === '') {
        echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": per ORE serve l'ora di inizio."], JSON_UNESCAPED_UNICODE);
        exit;
      }
      if ($durata_ore <= 0) {
        echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": per ORE inserisci un numero intero di ore."], JSON_UNESCAPED_UNICODE);
        exit;
      }
      $ora_a = addHoursToAtaTimePhp($ora_da, $durata_ore);
      if ($ora_a === '') {
        echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": la durata supera la giornata."], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
  } else {

    // VISITE e altri: data unica, ore facoltative
    $unita = 'ORE';

    if ($data_da === '' && $data_a !== '') $data_da = $data_a;
    if ($data_a === '' && $data_da !== '') $data_a = $data_da;

    if ($data_da === '' || $data_a === '') {
      echo json_encode(["ok" => false, "error" => "Inserisci la data."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    if ($data_da !== $data_a) {
      echo json_encode(["ok" => false, "error" => "$tipo_codice: inserisci una sola data."], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $oraRientroFacoltativa = ataPermessoOraRientroFacoltativa($tipo_codice, $tipo_descrizione);

    if ($ora_da === '' && $ora_a !== '') {
      echo json_encode(["ok" => false, "error" => "Se inserisci l'ora di rientro, devi indicare anche l'ora di uscita."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    if (!$oraRientroFacoltativa && $ora_da !== '' && $ora_a === '') {
      echo json_encode(["ok" => false, "error" => "Se inserisci le ore, devi indicare sia 'da' che 'a'."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    if ($ora_da === '' && $ora_a === '') {
      $ora_da = null;
      $ora_a  = null;
    } elseif ($ora_a !== '') {
      if ($ora_da >= $ora_a) {
        echo json_encode(["ok" => false, "error" => "L'ora 'da' deve essere precedente all'ora 'a'."], JSON_UNESCAPED_UNICODE);
        exit;
      }
    } else {
      $ora_a = null;
    }
  }

  // salvo nel vettore (nomi “logici”)
  $righe[$i]['unita']   = $unita;
  $righe[$i]['data_da'] = $data_da;
  $righe[$i]['data_a']  = $data_a;
  $righe[$i]['ora_da']  = $ora_da;
  $righe[$i]['ora_a']   = $ora_a;
  $righe[$i]['durata_ore'] = $durata_ore > 0 ? $durata_ore : null;
}

dbExec("START TRANSACTION");

try {

  $dettagliRichiesta = [
    'tipo_codice' => $tipo_codice,
    'auto_approvato' => ($stato === 'APPROVATO')
  ];
  $dettagliRichiestaJson = json_encode($dettagliRichiesta, JSON_UNESCAPED_UNICODE);

  if ($richiesta_id > 0) {

    $chk = dbGetFirst("
      SELECT id, stato
      FROM permesso_ata_richiesta
      WHERE id = $richiesta_id
        AND personale_ata_id = $__ata_id
      LIMIT 1
    ");
    if (!$chk) throw new Exception("Richiesta non trovata.");
    if ($chk['stato'] !== 'BOZZA') throw new Exception("Puoi modificare solo richieste in BOZZA.");

    dbExec("
      UPDATE permesso_ata_richiesta
      SET permesso_ata_tipo_id = $tipo_id,
          ferie_sottotipo = null,
          stato = " . dbQ($stato) . ",
          note_richiedente = " . dbQ($note) . ",
          dettagli_json = " . dbQ($dettagliRichiestaJson) . ",
          updated_at = NOW()
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
        ($__ata_id, $tipo_id, null, " . dbQ($stato) . ",
         " . dbQ($note) . ", " . dbQ($dettagliRichiestaJson) . ", NOW(), NOW())
    ");
    $richiesta_id = dblastId();
  }

  // INSERT righe con mapping DB corretto + dettagli_json (metto unita dentro)
  foreach ($righe as $r) {

    $dettagliRiga = [
      'unita' => $r['unita'] // GIORNI/ORE (serve soprattutto per LEGGE_104)
    ];
    if (!empty($r['durata_ore'])) {
      $dettagliRiga['durata_ore'] = (int)$r['durata_ore'];
    }
    $dettagliRigaJson = json_encode($dettagliRiga, JSON_UNESCAPED_UNICODE);

    dbExec("
      INSERT INTO permesso_ata_richiesta_riga
        (permesso_ata_richiesta_id, data_dal, ora_dal, data_al, ora_al, dettagli_json)
      VALUES
        ($richiesta_id,
         " . dbQ($r['data_da']) . ",
         " . dbQ($r['ora_da']) . ",
         " . dbQ($r['data_a']) . ",
         " . dbQ($r['ora_a']) . ",
         " . dbQ($dettagliRigaJson) . "
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
  if (($stato === 'INVIATO' || $stato === 'APPROVATO') && $ata && !empty($ata['email'])) {
    $nomeCompleto = trim((string)($ata['cognome'] ?? '') . ' ' . (string)($ata['nome'] ?? ''));
    $destinatarioReale = trim((string)($ata['email'] ?? ''));
    $destinatario = $MAIL_TEST_OVERRIDE ?: $destinatarioReale;

    if ($stato === 'APPROVATO') {
      $subject = "GestOre - Richiesta approvata automaticamente: " . $tipo_descrizione;
    } else {
      $subject = "GestOre - Richiesta inviata: " . $tipo_descrizione;
    }

    $body = buildPermessoRichiestaMailHtml(
      $nomeCompleto,
      $tipo_codice,
      $tipo_descrizione,
      $stato,
      $righe,
      $note,
      $nomeCompleto
    );

    $mailOk = sendMail($destinatario, $nomeCompleto, $subject, $body);
    info("permessoSave.php: mail invio richiesta id=$richiesta_id to_test=$destinatario to_real=$destinatarioReale esito=" . ($mailOk ? 'OK' : 'KO'));
    $segreteriaMail = trim((string)($__settings->segrata->emailSegreteria ?? ''));
    $segreteriaNome = trim((string)($__settings->segrata->destinatariEmail ?? 'Segreteria ATA Permessi'));

    if ($segreteriaMail !== '') {
      if ($stato === 'APPROVATO') {
        $subjectSeg = "GestOre - Richiesta auto-approvata: " . $nomeCompleto . " - " . $tipo_descrizione;
      } else {
        $subjectSeg = "GestOre - Nuova richiesta da gestire: " . $nomeCompleto . " - " . $tipo_descrizione;
      }
      $bodySeg = buildPermessoRichiestaSegreteriaMailHtml(
        $nomeCompleto,
        $destinatarioReale,
        $tipo_codice,
        $tipo_descrizione,
        $stato,
        $righe,
        $note,
        $segreteriaNome
      );
      $mailSegOk = sendMail($segreteriaMail, $segreteriaNome, $subjectSeg, $bodySeg);
      info("permessoSave.php: mail segreteria id=$richiesta_id to=$segreteriaMail esito=" . ($mailSegOk ? 'OK' : 'KO'));
    }
  }
  echo json_encode(["ok" => true, "id" => $richiesta_id, "stato" => $stato], JSON_UNESCAPED_UNICODE);
  exit;
} catch (Exception $e) {
  dbExec("ROLLBACK");
  echo json_encode(["ok" => false, "error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}

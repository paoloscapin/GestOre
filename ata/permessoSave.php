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
$ferie_sottotipo = isset($_POST['ferie_sottotipo']) ? strtoupper(trim($_POST['ferie_sottotipo'])) : '';
$MAIL_TEST_OVERRIDE = 'massimo.saiani@buonarroti.tn.it';

function hMail($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function formatDateRangeMail($dataDa, $dataA, $oraDa = null, $oraA = null): string
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

  if ($oraDa !== '' && $oraA !== '') {
    $txt .= ' dalle ' . substr($oraDa, 0, 5) . ' alle ' . substr($oraA, 0, 5);
  }

  return $txt;
}

function buildPermessoRichiestaMailHtml($nomeCompleto, $tipoCodice, $tipoDescrizione, $ferieSottotipo, array $righe, $note, $toName = ''): string
{
  $titolo = $tipoDescrizione !== '' ? $tipoDescrizione : $tipoCodice;
  if ($tipoCodice === 'FERIE' && $ferieSottotipo !== '') {
    $titolo .= ' - ' . $ferieSottotipo;
  }

  $theme = 'default';

  if ($tipoCodice === 'FERIE') {
    $theme = 'warning';
  } elseif ($tipoCodice === 'LEGGE_104') {
    $theme = 'docente';
  } elseif ($tipoCodice === 'RECUPERO_ORE') {
    $theme = 'mbapp';
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
      $r['ora_a'] ?? ''
    )) . '</td>
      </tr>';
  }

  $content = '
    <div style="margin:0 0 12px 0;">
      ' . badge('RICHIESTA INVIATA', '#dcfce7', '#14532d') . '
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
    "RICHIESTA PERMESSO",
    $toName !== '' ? $toName : $nomeCompleto,
    $intro,
    $content,
    $footer,
    $theme
  );
}

function buildPermessoRichiestaSegreteriaMailHtml($nomeCompleto, $emailUtente, $tipoCodice, $tipoDescrizione, $ferieSottotipo, array $righe, $note, $toName = ''): string
{
  $titolo = $tipoDescrizione !== '' ? $tipoDescrizione : $tipoCodice;
  if ($tipoCodice === 'FERIE' && $ferieSottotipo !== '') {
    $titolo .= ' - ' . $ferieSottotipo;
  }

  $theme = 'default';

  if ($tipoCodice === 'FERIE') {
    $theme = 'warning';
  } elseif ($tipoCodice === 'LEGGE_104') {
    $theme = 'docente';
  } elseif ($tipoCodice === 'RECUPERO_ORE') {
    $theme = 'mbapp';
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
      $r['ora_a'] ?? ''
    )) . '</td>
      </tr>';
  }

  $content = '
    <div style="margin:0 0 12px 0;">
      ' . badge('NUOVA RICHIESTA', '#fef3c7', '#92400e') . '
    </div>

    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        ' . kvRow('Dipendente', $nomeCompleto) . '
        ' . kvRow('Email utente', ($emailUtente !== '' ? $emailUtente : '—')) . '
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

  $intro = "È stata inviata una <b>nuova richiesta</b> su GestOre e richiede presa visione da parte della segreteria.";
  $footer = "Messaggio automatico da <b>GestOre</b>. Accedere al pannello segreteria per la gestione della richiesta.";

  return mailWrap(
    "NUOVA RICHIESTA PERMESSO",
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
  SELECT codice, descrizione
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
$stato = ($azione === 'INVIA') ? 'INVIATO' : 'BOZZA';

// ferie sottotipo
$ferie_allowed = ['GENERICHE', 'CARNEVALE', 'PASQUA', 'ESTIVE', 'NATALE'];
if ($tipo_codice === 'FERIE') {
  if ($ferie_sottotipo === '' || !in_array($ferie_sottotipo, $ferie_allowed, true)) {
    echo json_encode(["ok" => false, "error" => "Seleziona la tipologia ferie (GENERICHE/CARNEVALE/PASQUA/ESTIVE/NATALE)."], JSON_UNESCAPED_UNICODE);
    exit;
  }
} else {
  $ferie_sottotipo = '';
}

// finestra ferie (solo sottotipi non GENERICHE)
$finestra = null;
if ($tipo_codice === 'FERIE' && $ferie_sottotipo !== 'GENERICHE') {
  $finestra = dbGetFirst("
    SELECT data_inizio, data_fine
    FROM permesso_ata_ferie_finestra
    WHERE codice = " . dbQ($ferie_sottotipo) . "
      AND (valido IS NULL OR valido=1)
    LIMIT 1
  ");
  if (!$finestra) {
    echo json_encode(["ok" => false, "error" => "Finestra ferie non configurata per: $ferie_sottotipo."], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// VALIDAZIONI + NORMALIZZAZIONE
foreach ($righe as $i => $r) {
  $unita   = isset($r['unita']) ? strtoupper(trim((string)$r['unita'])) : '';
  $data_da = isset($r['data_da']) ? trim((string)$r['data_da']) : '';
  $data_a  = isset($r['data_a']) ? trim((string)$r['data_a']) : '';
  $ora_da  = isset($r['ora_da']) ? trim((string)$r['ora_da']) : '';
  $ora_a   = isset($r['ora_a']) ? trim((string)$r['ora_a']) : '';

  if ($tipo_codice === 'FERIE') {

    $unita = 'GIORNI';
    if ($data_da === '' || $data_a === '') {
      echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": date obbligatorie."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    if ($data_da > $data_a) {
      echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": la data DAL non può essere dopo la data AL."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    $ora_da = null;
    $ora_a  = null;

    if ($finestra) {
      $win_from = $finestra['data_inizio'];
      $win_to   = $finestra['data_fine'];
      if ($data_da < $win_from || $data_a > $win_to) {
        echo json_encode([
          "ok" => false,
          "error" => "Riga " . ($i + 1) . ": intervallo fuori finestra $ferie_sottotipo (" .
            fmtDateIT($win_from) . " - " . fmtDateIT($win_to) . ")."
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
  } elseif ($tipo_codice === 'RECUPERO_ORE') {

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
    if ($ora_da === '' || $ora_a === '') {
      echo json_encode(["ok" => false, "error" => "RECUPERO ORE: ore da/ore a obbligatorie."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    if ($ora_da >= $ora_a) {
      echo json_encode(["ok" => false, "error" => "RECUPERO ORE: l'ora 'da' deve essere precedente all'ora 'a'."], JSON_UNESCAPED_UNICODE);
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
      if ($ora_da === '' || $ora_a === '') {
        echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": per ORE servono ora da/ora a."], JSON_UNESCAPED_UNICODE);
        exit;
      }
      if ($ora_da >= $ora_a) {
        echo json_encode(["ok" => false, "error" => "Riga " . ($i + 1) . ": l'ora 'da' deve essere precedente all'ora 'a'."], JSON_UNESCAPED_UNICODE);
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

    if (($ora_da !== '' && $ora_a === '') || ($ora_da === '' && $ora_a !== '')) {
      echo json_encode(["ok" => false, "error" => "Se inserisci le ore, devi indicare sia 'da' che 'a'."], JSON_UNESCAPED_UNICODE);
      exit;
    }
    if ($ora_da === '' && $ora_a === '') {
      $ora_da = null;
      $ora_a  = null;
    } else {
      if ($ora_da >= $ora_a) {
        echo json_encode(["ok" => false, "error" => "L'ora 'da' deve essere precedente all'ora 'a'."], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
  }

  // salvo nel vettore (nomi “logici”)
  $righe[$i]['unita']   = $unita;
  $righe[$i]['data_da'] = $data_da;
  $righe[$i]['data_a']  = $data_a;
  $righe[$i]['ora_da']  = $ora_da;
  $righe[$i]['ora_a']   = $ora_a;
}

dbExec("START TRANSACTION");

try {

  // dettagli_json richiesta: per ora salvo solo ferie_sottotipo (opzionale)
  $dettagliRichiesta = [
    'tipo_codice' => $tipo_codice,
    'ferie_sottotipo' => ($tipo_codice === 'FERIE') ? $ferie_sottotipo : null
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
          ferie_sottotipo = " . dbQ($ferie_sottotipo ?: null) . ",
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
        ($__ata_id, $tipo_id, " . dbQ($ferie_sottotipo ?: null) . ", " . dbQ($stato) . ",
         " . dbQ($note) . ", " . dbQ($dettagliRichiestaJson) . ", NOW(), NOW())
    ");
    $richiesta_id = dblastId();
  }

  // INSERT righe con mapping DB corretto + dettagli_json (metto unita dentro)
  foreach ($righe as $r) {

    $dettagliRiga = [
      'unita' => $r['unita'] // GIORNI/ORE (serve soprattutto per LEGGE_104)
    ];
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
  if ($stato === 'INVIATO' && $ata && !empty($ata['email'])) {
    $nomeCompleto = trim((string)($ata['cognome'] ?? '') . ' ' . (string)($ata['nome'] ?? ''));
    $destinatarioReale = trim((string)($ata['email'] ?? ''));
    $destinatario = $MAIL_TEST_OVERRIDE ?: $destinatarioReale;

    $subject = "GestOre - Richiesta inviata: " . $tipo_descrizione;
    if ($tipo_codice === 'FERIE' && $ferie_sottotipo !== '') {
      $subject .= " - " . $ferie_sottotipo;
    }

    $body = buildPermessoRichiestaMailHtml(
      $nomeCompleto,
      $tipo_codice,
      $tipo_descrizione,
      $ferie_sottotipo,
      $righe,
      $note,
      $nomeCompleto
    );

    $mailOk = sendMail($destinatario, $nomeCompleto, $subject, $body);
    info("permessoSave.php: mail invio richiesta id=$richiesta_id to_test=$destinatario to_real=$destinatarioReale esito=" . ($mailOk ? 'OK' : 'KO'));
    $segreteriaMail = trim((string)($__settings->segrata->emailSegreteria ?? ''));
    $segreteriaNome = trim((string)($__settings->segrata->destinatariEmail ?? 'Segreteria ATA Permessi'));

    if ($segreteriaMail !== '') {
      $subjectSeg = "GestOre - Nuova richiesta da gestire: " . $nomeCompleto . " - " . $tipo_descrizione;
      if ($tipo_codice === 'FERIE' && $ferie_sottotipo !== '') {
        $subjectSeg .= " - " . $ferie_sottotipo;
      }

      $bodySeg = buildPermessoRichiestaSegreteriaMailHtml(
        $nomeCompleto,
        $destinatarioReale,
        $tipo_codice,
        $tipo_descrizione,
        $ferie_sottotipo,
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

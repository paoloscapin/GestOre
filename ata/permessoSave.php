<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

$richiesta_id    = isset($_POST['permesso_id']) ? intval($_POST['permesso_id']) : 0;
$tipo_id         = isset($_POST['permesso_tipo_id']) ? intval($_POST['permesso_tipo_id']) : 0;
$note            = isset($_POST['note']) ? trim($_POST['note']) : '';
$azione          = isset($_POST['azione']) ? strtoupper(trim($_POST['azione'])) : 'BOZZA';
$righe_json      = isset($_POST['righe_json']) ? $_POST['righe_json'] : '[]';
$ferie_sottotipo = isset($_POST['ferie_sottotipo']) ? strtoupper(trim($_POST['ferie_sottotipo'])) : '';

function fmtDateIT(?string $ymd): string {
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
  SELECT codice
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

$stato = ($azione === 'INVIA') ? 'INVIATO' : 'BOZZA';

// ferie sottotipo
$ferie_allowed = ['GENERICHE','CARNEVALE','PASQUA','ESTIVE','NATALE'];
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

    if (!in_array($unita, ['GIORNI','ORE'], true)) {
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

  dbExec("COMMIT");
  echo json_encode(["ok" => true, "id" => $richiesta_id, "stato" => $stato], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Exception $e) {
  dbExec("ROLLBACK");
  echo json_encode(["ok" => false, "error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}

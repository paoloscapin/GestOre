<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

$richiesta_id = isset($_POST['richiesta_id']) ? intval($_POST['richiesta_id']) : 0;
$tipo_id      = isset($_POST['permesso_tipo_id']) ? intval($_POST['permesso_tipo_id']) : 0;
$note         = isset($_POST['note']) ? trim((string)$_POST['note']) : '';
$azione       = isset($_POST['azione']) ? strtoupper(trim((string)$_POST['azione'])) : 'BOZZA';
$giorni_json  = isset($_POST['giorni_json']) ? (string)$_POST['giorni_json'] : '[]';

function fail($msg) {
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

function fmtDateIT($ymd) {
  $ymd = trim((string)$ymd);
  if ($ymd === '') return '';
  $ts = strtotime($ymd);
  return $ts ? date('d/m/Y', $ts) : $ymd;
}

function isValidYmd($ymd) {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return false;
  $dt = DateTime::createFromFormat('Y-m-d', $ymd);
  return $dt && $dt->format('Y-m-d') === $ymd;
}

function isWeekendYmd($ymd) {
  $ts = strtotime($ymd);
  if ($ts === false) return false;
  $w = (int)date('N', $ts); // 6 sab, 7 dom
  return ($w >= 6);
}

if ($tipo_id <= 0) fail('Tipo permesso non valido.');
if (!in_array($azione, ['BOZZA', 'INVIA'], true)) fail('Azione non valida.');

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
  WHERE codice = 'ESTIVE'
    AND (valido IS NULL OR valido = 1)
  LIMIT 1
");
if (!$finestra) fail('Finestra ferie ESTIVE non configurata.');

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
    fail('La data '.fmtDateIT($data).' è fuori dalla finestra ferie estive.');
  }

  if (isWeekendYmd($data)) {
    fail('La data '.fmtDateIT($data).' è sabato o domenica e non può essere selezionata.');
  }

  if (substr($data, 5, 5) === '06-26') {
    fail('Il giorno 26 giugno non può essere selezionato.');
  }

  $giorniValidi[] = $data;
}

sort($giorniValidi);
if (count($giorniValidi) === 0) {
  fail('Seleziona almeno un giorno valido.');
}

$stato = ($azione === 'INVIA') ? 'INVIATO' : 'BOZZA';

$dettagliRichiesta = [
  'tipo_codice' => 'FERIE',
  'ferie_sottotipo' => 'ESTIVE',
  'modo' => 'CALENDARIO_ESTIVO',
  'giorni_richiesti_count' => count($giorniValidi),
  'giorni_approvati_count' => 0,
  'giorni_respinti_count' => 0,
  'data_primo_giorno' => $giorniValidi[0],
  'data_ultimo_giorno' => $giorniValidi[count($giorniValidi) - 1],
];
$dettagliRichiestaJson = json_encode($dettagliRichiesta, JSON_UNESCAPED_UNICODE);

dbExec("START TRANSACTION");

try {
  if ($richiesta_id > 0) {
    $chk = dbGetFirst("
      SELECT r.id, r.stato
      FROM permesso_ata_richiesta r
      INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
      WHERE r.id = $richiesta_id
        AND r.personale_ata_id = $__ata_id
        AND t.codice = 'FERIE'
        AND r.ferie_sottotipo = 'ESTIVE'
      LIMIT 1
    ");

    if (!$chk) throw new Exception('Richiesta non trovata.');
    if (strtoupper((string)$chk['stato']) !== 'BOZZA') {
      throw new Exception('Puoi modificare solo richieste in BOZZA.');
    }

    dbExec("
      UPDATE permesso_ata_richiesta
      SET permesso_ata_tipo_id = $tipo_id,
          ferie_sottotipo = 'ESTIVE',
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
        ($__ata_id, $tipo_id, 'ESTIVE', " . dbQ($stato) . ", " . dbQ($note) . ", " . dbQ($dettagliRichiestaJson) . ", NOW(), NOW())
    ");
    $richiesta_id = dblastId();
  }

  foreach ($giorniValidi as $data) {
    $dettagliRiga = [
      'unita' => 'GIORNI',
      'modo' => 'CALENDARIO_ESTIVO',
      'stato_giorno' => 'RICHIESTO',
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

  dbExec("COMMIT");

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
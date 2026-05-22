<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

function fmtDateITUserDetails($d): string
{
  if (!$d) return '';
  $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
  if ($dt) return $dt->format('d/m/Y');

  $ts = strtotime((string)$d);
  return $ts ? date('d/m/Y', $ts) : (string)$d;
}

function fmtDateTimeITUserDetails($dt): string
{
  if (!$dt) return '';
  $ts = strtotime((string)$dt);
  return $ts ? date('d/m/Y H:i', $ts) : (string)$dt;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$sottotipo = isset($_POST['sottotipo']) ? strtoupper(trim((string)$_POST['sottotipo'])) : '';

if ($id <= 0) {
  echo json_encode(['ok' => false, 'error' => 'ID richiesta non valido.'], JSON_UNESCAPED_UNICODE);
  exit;
}
if ($sottotipo === '') {
  echo json_encode(['ok' => false, 'error' => 'Sottotipo mancante.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$head = dbGetFirst("
  SELECT r.*, t.codice AS tipo_codice, t.descrizione AS tipo_descrizione
  FROM permesso_ata_richiesta r
  INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
  WHERE r.id = $id
    AND r.personale_ata_id = $__ata_id
    AND t.codice = 'FERIE'
    AND UPPER(TRIM(r.ferie_sottotipo)) = " . dbQ($sottotipo) . "
  LIMIT 1
");

if (!$head) {
  echo json_encode(['ok' => false, 'error' => 'Richiesta non trovata.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$giorniRows = dbGetAll("
  SELECT id, data_dal, data_al, dettagli_json
  FROM permesso_ata_richiesta_riga
  WHERE permesso_ata_richiesta_id = $id
  ORDER BY data_dal ASC, id ASC
");

$giorni = [];
$giorniAttivi = [];
foreach ($giorniRows as $rr) {
  $det = [];
  if (!empty($rr['dettagli_json'])) {
    $tmp = json_decode($rr['dettagli_json'], true);
    if (is_array($tmp)) $det = $tmp;
  }

  $statoGiorno = strtoupper((string)($det['stato_giorno'] ?? 'RICHIESTO'));
  $giorni[] = [
    'id' => (int)$rr['id'],
    'data' => $rr['data_dal'],
    'stato_giorno' => $statoGiorno,
    'variazione_modifica' => strtoupper((string)($det['variazione_modifica'] ?? '')),
    'data_originale' => (string)($det['data_originale'] ?? $rr['data_dal']),
    'data_definitiva' => (string)($det['data_definitiva'] ?? $rr['data_dal']),
    'nota_approvatore' => (string)($det['nota_approvatore'] ?? ''),
  ];

  if ($statoGiorno !== 'RIMOSSO') {
    $giorniAttivi[] = (string)$rr['data_dal'];
  }
}

$details = [];
if (!empty($head['dettagli_json'])) {
  $tmp = json_decode((string)$head['dettagli_json'], true);
  if (is_array($tmp)) $details = $tmp;
}

$giorniRichiesti = is_array($details['giorni_originali'] ?? null)
  ? $details['giorni_originali']
  : $giorniAttivi;
$giorniRichiesti = array_values(array_unique(array_filter(array_map('strval', $giorniRichiesti))));
sort($giorniRichiesti);

$giorniRichiestiFmt = array_map('fmtDateITUserDetails', $giorniRichiesti);
$timelineRaw = is_array($details['timeline'] ?? null) ? $details['timeline'] : [];
$timeline = array_values(array_filter($timelineRaw, function ($item) {
  $action = strtoupper((string)($item['action'] ?? ''));
  return in_array($action, [
    'FERIE_AGGIORNATA',
    'FERIE_MODIFICATA_DOPO_APPROVAZIONE',
  ], true);
}));
array_unshift($timeline, [
  'action' => 'FERIE_RICHIESTA_INVIATA',
  'label' => 'Richiesta ferie inviata',
  'note' => 'Giorni richiesti: ' . (count($giorniRichiestiFmt) ? implode(', ', $giorniRichiestiFmt) : 'nessuno'),
  'at' => (string)($head['created_at'] ?? ''),
  'at_fmt' => fmtDateTimeITUserDetails($head['created_at'] ?? ''),
  'user_label' => 'Dipendente'
]);

$head['note'] = $head['note_richiedente'] ?? '';
unset($head['note_richiedente']);

echo json_encode([
  'ok' => true,
  'richiesta' => $head,
  'giorni' => $giorni,
  'timeline' => $timeline
], JSON_UNESCAPED_UNICODE);

<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

function ferieSummaryFail($message)
{
  echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

function ferieSummaryFmtDate($value)
{
  $ts = strtotime((string)$value);
  return $ts ? date('d/m/Y', $ts) : (string)$value;
}

function ferieSummaryMonthLabel($ym)
{
  static $months = [
    1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
    5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
    9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
  ];

  [$year, $month] = array_map('intval', explode('-', $ym));
  return ($months[$month] ?? $ym) . ' ' . $year;
}

function ferieSummaryWeekday($ymd)
{
  static $days = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'];
  $ts = strtotime((string)$ymd);
  return $ts ? ($days[(int)date('N', $ts)] ?? '') : '';
}

function ferieSummaryNormalizeState($state)
{
  $state = strtoupper(trim((string)$state));
  return $state !== '' ? $state : 'RICHIESTO';
}

function ferieSummaryStateLabel($state)
{
  $state = ferieSummaryNormalizeState($state);
  if ($state === 'APPROVATO') return 'Approvato';
  if ($state === 'RESPINTO') return 'Respinto';
  if ($state === 'AGGIUNTO') return 'Aggiunto';
  if ($state === 'BOZZA') return 'Bozza';
  return 'Richiesto';
}

function ferieSummaryStatePriority($state)
{
  $state = ferieSummaryNormalizeState($state);
  if ($state === 'APPROVATO') return 500;
  if ($state === 'RESPINTO') return 450;
  if ($state === 'AGGIUNTO') return 400;
  if ($state === 'RICHIESTO') return 350;
  if ($state === 'BOZZA') return 300;
  return 100;
}

function ferieSummaryNormalizeDayStateForRequest($dayState, $requestState)
{
  $state = ferieSummaryNormalizeState($dayState);
  $requestState = strtoupper(trim((string)$requestState));
  $requestApprovedStates = ['APPROVATO', 'APPROVATA', 'APPROVATO_PARZIALE', 'APPROVATA_PARZIALE', 'PARZIALE'];
  $requestRejectedStates = ['RESPINTO', 'RESPINTA'];

  if ($state === 'RIMOSSO') {
    return 'RIMOSSO';
  }

  if ($state === 'RICHIESTO' && $requestState === 'BOZZA') {
    return 'BOZZA';
  }

  if (in_array($state, ['RICHIESTO', 'AGGIUNTO'], true)) {
    if (in_array($requestState, $requestApprovedStates, true)) {
      return 'APPROVATO';
    }

    if (in_array($requestState, $requestRejectedStates, true)) {
      return 'RESPINTO';
    }
  }

  return $state;
}

function ferieSummaryExpandRange($from, $to)
{
  $out = [];
  if (!$from) return $out;
  if (!$to) $to = $from;

  $start = DateTime::createFromFormat('Y-m-d', (string)$from);
  $end = DateTime::createFromFormat('Y-m-d', (string)$to);
  if (!$start || !$end || $end < $start) return $out;

  $cur = clone $start;
  while ($cur <= $end) {
    $out[] = $cur->format('Y-m-d');
    $cur->modify('+1 day');
  }

  return $out;
}

function ferieSummaryRanges($days)
{
  $days = array_values(array_unique(array_filter(array_map('strval', $days))));
  sort($days);

  $ranges = [];
  $start = null;
  $prev = null;

  foreach ($days as $day) {
    if ($start === null) {
      $start = $day;
      $prev = $day;
      continue;
    }

    $expected = date('Y-m-d', strtotime($prev . ' +1 day'));
    if ($day === $expected) {
      $prev = $day;
      continue;
    }

    $ranges[] = $start === $prev ? ferieSummaryFmtDate($start) : ferieSummaryFmtDate($start) . ' - ' . ferieSummaryFmtDate($prev);
    $start = $day;
    $prev = $day;
  }

  if ($start !== null) {
    $ranges[] = $start === $prev ? ferieSummaryFmtDate($start) : ferieSummaryFmtDate($start) . ' - ' . ferieSummaryFmtDate($prev);
  }

  return $ranges;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$sottotipo = isset($_POST['sottotipo']) ? strtoupper(trim((string)$_POST['sottotipo'])) : '';

if ($id <= 0 || $sottotipo === '') {
  ferieSummaryFail('Richiesta ferie non valida.');
}

$head = dbGetFirst("
  SELECT r.id, r.ferie_sottotipo, r.stato, r.created_at, r.updated_at
  FROM permesso_ata_richiesta r
  INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
  WHERE r.id = " . dbI($id) . "
    AND r.personale_ata_id = " . dbI($__ata_id) . "
    AND t.codice = 'FERIE'
    AND UPPER(TRIM(r.ferie_sottotipo)) = " . dbQ($sottotipo) . "
  LIMIT 1
");

if (!$head) {
  ferieSummaryFail('Richiesta ferie non trovata.');
}

$window = dbGetFirst("
  SELECT data_inizio, data_fine
  FROM permesso_ata_ferie_finestra
  WHERE UPPER(TRIM(codice)) = " . dbQ($sottotipo) . "
  LIMIT 1
");

$windowFrom = ($window && !empty($window['data_inizio'])) ? (string)$window['data_inizio'] : '';
$windowTo = ($window && !empty($window['data_fine'])) ? (string)$window['data_fine'] : '';

$rows = dbGetAll("
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
  WHERE r.personale_ata_id = " . dbI($__ata_id) . "
    AND t.codice = 'FERIE'
    AND UPPER(TRIM(r.ferie_sottotipo)) = " . dbQ($sottotipo) . "
  ORDER BY rr.data_dal ASC, rr.id ASC
");

if (!is_array($rows)) {
  $rows = [];
}

$daysByDate = [];
$requestIds = [];
$clickedDays = [];

foreach ($rows as $row) {
  $details = [];
  if (!empty($row['dettagli_json'])) {
    $tmp = json_decode((string)$row['dettagli_json'], true);
    if (is_array($tmp)) $details = $tmp;
  }

  $requestId = (int)$row['richiesta_id'];
  $requestIds[$requestId] = true;
  $requestState = strtoupper(trim((string)($row['richiesta_stato'] ?? '')));
  $state = ferieSummaryNormalizeDayStateForRequest($details['stato_giorno'] ?? '', $requestState);
  if ($state === 'RIMOSSO') {
    continue;
  }

  if ($requestState === 'MODIFICATA' && $state === 'RICHIESTO') {
    $state = 'AGGIUNTO';
  }

  $range = ferieSummaryExpandRange($row['data_dal'] ?? '', $row['data_al'] ?? '');
  foreach ($range as $day) {
    if ($windowFrom !== '' && $day < $windowFrom) {
      continue;
    }

    if ($windowTo !== '' && $day > $windowTo) {
      continue;
    }

    $candidate = [
      'date' => $day,
      'day' => (int)date('j', strtotime($day)),
      'weekday' => ferieSummaryWeekday($day),
      'state' => $state,
      'state_label' => ferieSummaryStateLabel($state),
      'request_id' => $requestId,
      'current' => $requestId === $id,
    ];

    if ($requestId === $id) {
      $clickedDays[] = $day;
    }

    if (!isset($daysByDate[$day]) || ferieSummaryStatePriority($state) >= ferieSummaryStatePriority($daysByDate[$day]['state'])) {
      $daysByDate[$day] = $candidate;
    }
  }
}

ksort($daysByDate);

$months = [];
$stateCounts = [
  'APPROVATO' => 0,
  'RESPINTO' => 0,
  'AGGIUNTO' => 0,
  'RICHIESTO' => 0,
  'BOZZA' => 0,
];

foreach ($daysByDate as $day => $info) {
  $ym = substr($day, 0, 7);
  if (!isset($months[$ym])) {
    $months[$ym] = [
      'label' => ferieSummaryMonthLabel($ym),
      'days' => [],
    ];
  }

  $months[$ym]['days'][] = $info;
  $state = ferieSummaryNormalizeState($info['state']);
  if (!isset($stateCounts[$state])) $stateCounts[$state] = 0;
  $stateCounts[$state]++;
}

$allDays = array_keys($daysByDate);

echo json_encode([
  'ok' => true,
  'sottotipo' => $sottotipo,
  'title' => $sottotipo === 'ESTIVE' ? 'Ferie estive' : 'Ferie ' . strtolower($sottotipo),
  'request_id' => $id,
  'request_state' => $head['stato'] ?? '',
  'window' => [
    'from' => $window['data_inizio'] ?? '',
    'to' => $window['data_fine'] ?? '',
    'label' => ($window && !empty($window['data_inizio']) && !empty($window['data_fine']))
      ? ferieSummaryFmtDate($window['data_inizio']) . ' - ' . ferieSummaryFmtDate($window['data_fine'])
      : '',
  ],
  'total_days' => count($allDays),
  'clicked_days' => count(array_unique($clickedDays)),
  'request_count' => count($requestIds),
  'ranges' => ferieSummaryRanges($allDays),
  'state_counts' => $stateCounts,
  'months' => array_values($months),
], JSON_UNESCAPED_UNICODE);

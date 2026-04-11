<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente','segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['id'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Missing id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = intval($_POST['id']);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid id'], JSON_UNESCAPED_UNICODE);
  exit;
}

function expandDateRangeIso($from, $to)
{
  $out = [];

  if (!$from) return $out;
  if (!$to) $to = $from;

  $start = DateTime::createFromFormat('Y-m-d', $from);
  $end   = DateTime::createFromFormat('Y-m-d', $to);

  if (!$start || !$end) return $out;
  if ($end < $start) return $out;

  $cur = clone $start;
  while ($cur <= $end) {
    $out[] = $cur->format('Y-m-d');
    $cur->modify('+1 day');
  }

  return $out;
}

function isoInRange($iso, $from, $to)
{
  if (!$iso || !$from || !$to) return false;
  return ($iso >= $from && $iso <= $to);
}

/**
 * Richiesta principale
 */
$query = "
SELECT
    r.*,
    t.codice AS tipo_codice,
    t.descrizione AS tipo_descrizione,

    p.id AS personale_id,
    p.username,
    p.cognome,
    p.nome,
    p.email,
    p.matricola,
    p.tipo_contratto,
    p.id_profilo,

    pr.nome AS profilo_nome,
    pr.codice AS profilo_codice,

    pa.id_ufficio,
    u.nome AS ufficio_nome,

    ff.data_inizio AS ferie_win_da,
    ff.data_fine   AS ferie_win_a

FROM permesso_ata_richiesta r
JOIN permesso_ata_tipo t
  ON t.id = r.permesso_ata_tipo_id
JOIN personale_ata p
  ON p.id = r.personale_ata_id
LEFT JOIN personale_ata_profili pr
  ON pr.id = p.id_profilo
LEFT JOIN personale_ata_assegnazioni pa
  ON pa.username = p.username
 AND pa.attiva = 1
LEFT JOIN personale_ata_uffici u
  ON u.id = pa.id_ufficio
LEFT JOIN permesso_ata_ferie_finestra ff
  ON t.codice = 'FERIE'
 AND r.ferie_sottotipo IS NOT NULL
 AND r.ferie_sottotipo <> ''
 AND UPPER(TRIM(ff.codice)) = UPPER(TRIM(r.ferie_sottotipo))
 AND (ff.valido IS NULL OR ff.valido = 1)
WHERE r.id = $id
LIMIT 1
";

$row = dbGetFirst($query);

if (!$row || !is_array($row)) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * Totali profilo / ufficio
 */
$totProfilo = 0;
$totUfficio = 0;

if (intval($row['id_profilo'] ?? 0) > 0) {
  $tmp = dbGetFirst("
    SELECT COUNT(*) AS n
    FROM personale_ata
    WHERE id_profilo = " . intval($row['id_profilo']) . "
  ");
  $totProfilo = intval($tmp['n'] ?? 0);
}

if (intval($row['id_ufficio'] ?? 0) > 0) {
  $tmp = dbGetFirst("
    SELECT COUNT(*) AS n
    FROM personale_ata_assegnazioni
    WHERE id_ufficio = " . intval($row['id_ufficio']) . "
      AND attiva = 1
  ");
  $totUfficio = intval($tmp['n'] ?? 0);
}

/**
 * Righe della richiesta
 * IMPORTANTE: qui leggiamo anche stato_giorno e nota_approvatore dal dettagli_json
 */
$righeDB = dbGetAll("
    SELECT
        id,
        data_dal,
        ora_dal,
        data_al,
        ora_al,
        dettagli_json
    FROM permesso_ata_richiesta_riga
    WHERE permesso_ata_richiesta_id = $id
    ORDER BY id ASC
");
if (!is_array($righeDB)) {
  $righeDB = [];
}

$righe = [];
$selectedDatesMap = [];

foreach ($righeDB as $rr) {
  $det = [];
  if (!empty($rr['dettagli_json'])) {
    $tmp = json_decode($rr['dettagli_json'], true);
    if (is_array($tmp)) {
      $det = $tmp;
    }
  }

  $unita = strtoupper((string)($det['unita'] ?? ''));
  if ($unita !== 'GIORNI' && $unita !== 'ORE') {
    $unita = (!empty($rr['ora_dal']) || !empty($rr['ora_al'])) ? 'ORE' : 'GIORNI';
  }

  $dataDa = $rr['data_dal'] ?? '';
  $dataA  = $rr['data_al'] ?? '';
  if ($dataA === '') $dataA = $dataDa;

  $righe[] = [
    'id'               => intval($rr['id']),
    'unita'            => $unita,
    'data_da'          => $dataDa,
    'data_a'           => $dataA,
    'ora_da'           => $rr['ora_dal'] ?? '',
    'ora_a'            => $rr['ora_al'] ?? '',
    'modo'             => $det['modo'] ?? '',
    'stato_giorno'     => strtoupper((string)($det['stato_giorno'] ?? 'RICHIESTO')),
    'data_originale'   => $det['data_originale'] ?? $dataDa,
    'data_definitiva'  => $det['data_definitiva'] ?? $dataDa,
    'nota_approvatore' => $det['nota_approvatore'] ?? ''
  ];

  foreach (expandDateRangeIso($dataDa, $dataA) as $iso) {
    $selectedDatesMap[$iso] = true;
  }
}

/**
 * Giorni speciali ferie
 */
$giorniSpeciali = [];
if (($row['tipo_codice'] ?? '') === 'FERIE' && !empty($row['ferie_sottotipo'])) {
  $sottotipo = strtoupper(trim((string)$row['ferie_sottotipo']));

  $giorniSpecialiRows = dbGetAll("
    SELECT
      data_giorno,
      tipo,
      descrizione
    FROM permesso_ata_ferie_giorni_speciali
    WHERE UPPER(TRIM(sottotipo)) = " . dbQ($sottotipo) . "
      AND (valido IS NULL OR valido = 1)
    ORDER BY data_giorno ASC
  ");

  if (is_array($giorniSpecialiRows)) {
    foreach ($giorniSpecialiRows as $g) {
      $giorniSpeciali[] = [
        'data' => (string)($g['data_giorno'] ?? ''),
        'tipo' => strtoupper((string)($g['tipo'] ?? '')),
        'descrizione' => (string)($g['descrizione'] ?? '')
      ];
    }
  }
}

/**
 * Conteggi e tooltip per il calendario ferie
 */
$countsByDate = [];
$tooltipByDate = [];

$ferieFinestra = [
  'data_inizio' => (string)($row['ferie_win_da'] ?? ''),
  'data_fine'   => (string)($row['ferie_win_a'] ?? '')
];

if (($row['tipo_codice'] ?? '') === 'FERIE'
    && !empty($ferieFinestra['data_inizio'])
    && !empty($ferieFinestra['data_fine'])) {

  $winDa = $ferieFinestra['data_inizio'];
  $winA  = $ferieFinestra['data_fine'];

  foreach (expandDateRangeIso($winDa, $winA) as $iso) {
    $countsByDate[$iso] = [
      'same_profile' => 0,
      'same_office'  => 0
    ];

    $tooltipByDate[$iso] = [
      'same_profile_names' => [],
      'same_office_names'  => []
    ];
  }

  $currentProfiloId = intval($row['id_profilo'] ?? 0);
  $currentUfficioId = intval($row['id_ufficio'] ?? 0);

  $ferieRows = dbGetAll("
    SELECT
      req.id AS richiesta_id,
      req.personale_ata_id,
      req.stato,
      rr.id AS riga_id,
      rr.data_dal,
      rr.data_al,
      p.id_profilo,
      pa.id_ufficio,
      p.cognome,
      p.nome
    FROM permesso_ata_richiesta req
    JOIN permesso_ata_tipo t
      ON t.id = req.permesso_ata_tipo_id
    JOIN permesso_ata_richiesta_riga rr
      ON rr.permesso_ata_richiesta_id = req.id
    JOIN personale_ata p
      ON p.id = req.personale_ata_id
    LEFT JOIN personale_ata_assegnazioni pa
      ON pa.username = p.username
     AND pa.attiva = 1
    WHERE t.codice = 'FERIE'
      AND req.stato IN ('INVIATO', 'APPROVATO', 'PARZIALE')
      AND rr.data_dal <= " . dbQ($winA) . "
      AND rr.data_al >= " . dbQ($winDa) . "
  ");

  if (!is_array($ferieRows)) {
    $ferieRows = [];
  }

  foreach ($ferieRows as $fr) {
    $rangeStart = $fr['data_dal'] ?? '';
    $rangeEnd   = $fr['data_al'] ?? '';
    if ($rangeEnd === '') $rangeEnd = $rangeStart;

    $fullName = trim(($fr['cognome'] ?? '') . ' ' . ($fr['nome'] ?? ''));

    $profileMatch = ($currentProfiloId > 0 && intval($fr['id_profilo'] ?? 0) === $currentProfiloId);
    $officeMatch  = ($currentUfficioId > 0 && intval($fr['id_ufficio'] ?? 0) === $currentUfficioId);

    if (!$profileMatch && !$officeMatch) {
      continue;
    }

    foreach (expandDateRangeIso($rangeStart, $rangeEnd) as $iso) {
      if (!isoInRange($iso, $winDa, $winA)) {
        continue;
      }

      if ($profileMatch) {
        $countsByDate[$iso]['same_profile']++;

        if ($fullName !== '' && !in_array($fullName, $tooltipByDate[$iso]['same_profile_names'], true)) {
          $tooltipByDate[$iso]['same_profile_names'][] = $fullName;
        }
      }

      if ($officeMatch) {
        $countsByDate[$iso]['same_office']++;

        if ($fullName !== '' && !in_array($fullName, $tooltipByDate[$iso]['same_office_names'], true)) {
          $tooltipByDate[$iso]['same_office_names'][] = $fullName;
        }
      }
    }
  }
}

/**
 * Label tipo
 */
$tipoLabel = $row['tipo_codice'] . ' - ' . $row['tipo_descrizione'];
if (($row['tipo_codice'] ?? '') === 'FERIE' && !empty($row['ferie_sottotipo'])) {
  $tipoLabel .= ' (' . $row['ferie_sottotipo'] . ')';
}

/**
 * Payload finale
 */
$payload = [
  'ok' => true,

  'permesso' => [
    'id' => intval($row['id']),
    'stato' => $row['stato'],
    'created_at' => $row['created_at'],
    'updated_at' => $row['updated_at'],
    'tipo_codice' => $row['tipo_codice'],
    'tipo' => $tipoLabel,
    'ferie_sottotipo' => $row['ferie_sottotipo'],
    'note_richiedente' => $row['note_richiedente'],
    'note_segreteria' => $row['note_segreteria'],
    'dettagli_json' => $row['dettagli_json'] ?? ''
  ],

  'dipendente' => [
    'id' => intval($row['personale_id']),
    'username' => $row['username'] ?? '',
    'nome' => trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')),
    'email' => $row['email'] ?? '',
    'matricola' => $row['matricola'] ?? '',
    'tipo_contratto' => $row['tipo_contratto'] ?? '',
    'profilo' => $row['profilo_nome'] ?? '',
    'profilo_codice' => $row['profilo_codice'] ?? '',
    'ufficio' => $row['ufficio_nome'] ?? '',
    'id_profilo' => intval($row['id_profilo'] ?? 0),
    'id_ufficio' => intval($row['id_ufficio'] ?? 0)
  ],

  'righe' => $righe,
  'selected_dates' => array_keys($selectedDatesMap),
  'ferie_finestra' => $ferieFinestra,
  'giorni_speciali' => $giorniSpeciali,

  'totali' => [
    'profilo' => $totProfilo,
    'ufficio' => $totUfficio
  ],

  'counts_by_date' => $countsByDate,
  'tooltip_by_date' => $tooltipByDate
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
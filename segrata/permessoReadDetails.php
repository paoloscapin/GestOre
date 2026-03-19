<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente','segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['id'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Missing id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = intval($_POST['id']);

$query = "
SELECT
  r.*,
  t.codice AS tipo_codice,
  t.descrizione AS tipo_descrizione,
  p.cognome, p.nome, p.email, p.matricola, p.tipo_contratto, p.ruolo,

  ff.data_inizio AS ferie_win_da,
  ff.data_fine   AS ferie_win_a

FROM permesso_ata_richiesta r
JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
JOIN personale_ata p ON p.id = r.personale_ata_id

LEFT JOIN permesso_ata_ferie_finestra ff
  ON (t.codice = 'FERIE'
      AND r.ferie_sottotipo IS NOT NULL
      AND r.ferie_sottotipo <> 'GENERICHE'
      AND ff.codice = r.ferie_sottotipo
      AND (ff.valido IS NULL OR ff.valido = 1))

WHERE r.id = $id
LIMIT 1
";

$row = dbGetFirst($query);
if (!$row) {
  echo json_encode(['ok'=>false,'error'=>'Not found'], JSON_UNESCAPED_UNICODE);
  exit;
}

// righe (mapping corretto dal DB)
$righeDB = dbGetAll("
  SELECT id, data_dal, ora_dal, data_al, ora_al, dettagli_json
  FROM permesso_ata_richiesta_riga
  WHERE permesso_ata_richiesta_id = $id
  ORDER BY id ASC
");

$righe = [];
foreach ($righeDB as $rr) {
  $det = [];
  if (!empty($rr['dettagli_json'])) {
    $tmp = json_decode($rr['dettagli_json'], true);
    if (is_array($tmp)) $det = $tmp;
  }

  $unita = strtoupper((string)($det['unita'] ?? ''));
  // fallback: se non c'è unita, la deduco “a spanne”
  if ($unita !== 'GIORNI' && $unita !== 'ORE') {
    $unita = (!empty($rr['ora_dal']) || !empty($rr['ora_al'])) ? 'ORE' : 'GIORNI';
  }

  $righe[] = [
    'id'      => intval($rr['id']),
    'unita'   => $unita,
    'data_da' => $rr['data_dal'],
    'data_a'  => $rr['data_al'],
    'ora_da'  => $rr['ora_dal'],
    'ora_a'   => $rr['ora_al'],
  ];
}

// payload
$tipoLabel = $row['tipo_codice'].' - '.$row['tipo_descrizione'];
if ($row['tipo_codice'] === 'FERIE' && !empty($row['ferie_sottotipo'])) {
  $tipoLabel .= ' (' . $row['ferie_sottotipo'] . ')';
}

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
  ],
  'dipendente' => [
    'nome' => $row['cognome'].' '.$row['nome'],
    'email' => $row['email'],
    'matricola' => $row['matricola'],
    'tipo_contratto' => $row['tipo_contratto'],
    'ruolo' => $row['ruolo'],
  ],
  'righe' => $righe
];

// finestra ferie (opzionale)
if ($row['tipo_codice'] === 'FERIE' && !empty($row['ferie_sottotipo']) && $row['ferie_sottotipo'] !== 'GENERICHE') {
  $payload['ferie_finestra'] = [
    'data_inizio' => $row['ferie_win_da'],
    'data_fine'   => $row['ferie_win_a'],
  ];
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);

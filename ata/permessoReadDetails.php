<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['id'])) { http_response_code(400); exit; }
$id = intval($_POST['id']);

$head = dbGetFirst("
  SELECT r.*, t.codice AS tipo_codice, t.descrizione AS tipo_descrizione
  FROM permesso_ata_richiesta r
  INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
  WHERE r.id = $id AND r.personale_ata_id = $__ata_id
  LIMIT 1
");
if (!$head) { http_response_code(404); exit; }

// normalizzo note per il frontend
$head['note'] = $head['note_richiedente'] ?? '';
unset($head['note_richiedente']);

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

  $fmtTime = function ($t) {
    $t = trim((string)$t);
    return $t !== '' ? substr($t, 0, 5) : $t;
  };

  $righe[] = [
    'id'      => (int)$rr['id'],
    'unita'   => strtoupper((string)($det['unita'] ?? '')),
    'data_da' => $rr['data_dal'],
    'data_a'  => $rr['data_al'],
    'ora_da'  => $fmtTime($rr['ora_dal']),
    'ora_a'   => $fmtTime($rr['ora_al']),
    'durata_ore' => isset($det['durata_ore']) ? (int)$det['durata_ore'] : null,
  ];
}

echo json_encode([
  "ok" => true,
  "richiesta" => $head,
  "righe" => $righe
], JSON_UNESCAPED_UNICODE);

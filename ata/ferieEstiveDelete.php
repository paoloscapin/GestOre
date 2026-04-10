<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
  echo json_encode(['ok' => false, 'error' => 'ID non valido.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$chk = dbGetFirst("
  SELECT r.id, r.stato
  FROM permesso_ata_richiesta r
  INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
  WHERE r.id = $id
    AND r.personale_ata_id = $__ata_id
    AND t.codice = 'FERIE'
    AND r.ferie_sottotipo = 'ESTIVE'
  LIMIT 1
");

if (!$chk) {
  echo json_encode(['ok' => false, 'error' => 'Richiesta non trovata.'], JSON_UNESCAPED_UNICODE);
  exit;
}
if (strtoupper((string)$chk['stato']) !== 'BOZZA') {
  echo json_encode(['ok' => false, 'error' => 'Puoi eliminare solo richieste in BOZZA.'], JSON_UNESCAPED_UNICODE);
  exit;
}

dbExec("DELETE FROM permesso_ata_richiesta WHERE id = $id AND personale_ata_id = $__ata_id LIMIT 1");

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
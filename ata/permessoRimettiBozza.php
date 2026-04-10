<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
  echo json_encode(["ok" => false, "error" => "ID non valido."], JSON_UNESCAPED_UNICODE);
  exit;
}

$chk = dbGetFirst("
  SELECT id, stato
  FROM permesso_ata_richiesta
  WHERE id = $id
    AND personale_ata_id = $__ata_id
  LIMIT 1
");

if (!$chk) {
  echo json_encode(["ok" => false, "error" => "Richiesta non trovata."], JSON_UNESCAPED_UNICODE);
  exit;
}

$stato = strtoupper(trim((string)$chk['stato']));
if ($stato !== 'INVIATO' && $stato !== 'INVIATA') {
  echo json_encode(["ok" => false, "error" => "Solo una richiesta inviata può essere rimessa in bozza."], JSON_UNESCAPED_UNICODE);
  exit;
}

dbExec("
  UPDATE permesso_ata_richiesta
  SET stato = 'BOZZA',
      updated_at = NOW()
  WHERE id = $id
    AND personale_ata_id = $__ata_id
  LIMIT 1
");

echo json_encode(["ok" => true], JSON_UNESCAPED_UNICODE);
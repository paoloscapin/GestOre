<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['id'])) { http_response_code(400); exit; }
$id = intval($_POST['id']);

$chk = dbGetFirst("
  SELECT id, stato
  FROM permesso_ata_richiesta
  WHERE id=$id AND personale_ata_id=$__ata_id
  LIMIT 1
");
if (!$chk) { echo json_encode(["ok"=>false,"error"=>"Richiesta non trovata."], JSON_UNESCAPED_UNICODE); exit; }
if ($chk['stato'] !== 'BOZZA') { echo json_encode(["ok"=>false,"error"=>"Puoi eliminare solo richieste in BOZZA."], JSON_UNESCAPED_UNICODE); exit; }

dbExec("DELETE FROM permesso_ata_richiesta WHERE id=$id AND personale_ata_id=$__ata_id LIMIT 1");
echo json_encode(["ok"=>true], JSON_UNESCAPED_UNICODE);

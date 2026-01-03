<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente');

header('Content-Type: application/json; charset=utf-8');

function out($ok, $msg){
  echo json_encode(['success'=>$ok,'message'=>$msg]);
  exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$anno = isset($_POST['anno_scolastico_id']) ? intval($_POST['anno_scolastico_id']) : intval($__anno_scolastico_corrente_id);
if ($id<=0) out(false,'id non valido');

// safety: se config non disponibile evita fatal error
if (!isset($__config) || !method_exists($__config, 'getBonus_rendiconto_aperto')) {
  out(false, 'Config non disponibile');
}
if (!$__config->getBonus_rendiconto_aperto()) out(false,'Rendiconto chiuso');
if ($anno != intval($__anno_scolastico_corrente_id)) out(false,'Solo anno corrente');
$row = dbGetFirst("
  SELECT a.*, bd.docente_id, bd.anno_scolastico_id
  FROM bonus_docente_allegato a
  JOIN bonus_docente bd ON bd.id = a.bonus_docente_id
  WHERE a.id = $id
");
if (!$row) out(false,'Allegato non trovato');
if (intval($row['anno_scolastico_id']) !== $anno) out(false,'Anno non coerente');
if (!haRuolo('dirigente') && intval($row['docente_id']) !== intval($__docente_id)) out(false,'Non autorizzato');
$baseDir = realpath(__DIR__ . '/bonus_upload');
if ($baseDir) {
  $path = $baseDir . '/' . $anno . '/' . intval($row['docente_id']) . '/' . intval($row['bonus_docente_id']) . '/' . $row['stored_name'];
  if (is_file($path)) @unlink($path);
}
dbExec("DELETE FROM bonus_docente_allegato WHERE id = $id LIMIT 1");
out(true,'Allegato eliminato');

<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$forceDownload = isset($_GET['download']) && $_GET['download'] == '1';
$disposition = $forceDownload ? 'attachment' : 'inline';

if ($id<=0) { http_response_code(400); exit; }

$row = dbGetFirst("
  SELECT a.*, bd.docente_id, bd.anno_scolastico_id
  FROM bonus_docente_allegato a
  JOIN bonus_docente bd ON bd.id = a.bonus_docente_id
  WHERE a.id = $id
");
if (!$row) {
  http_response_code(404);
  exit;
}

if (!$row) { http_response_code(404); exit; }

if (!haRuolo('dirigente') && intval($row['docente_id']) !== intval($__docente_id)) {
  http_response_code(403); exit;
}

$anno = intval($row['anno_scolastico_id']);
$baseDir = realpath(__DIR__ . '/bonus_upload');
if (!$baseDir) { http_response_code(500); exit; }

$filePath = $baseDir . '/' . $anno . '/' . intval($row['docente_id']) . '/' . intval($row['bonus_docente_id']) . '/' . $row['stored_name'];
if (!is_file($filePath)) { http_response_code(404); exit; }

$downloadName = $row['original_name'];

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: '.$disposition.'; filename="' . str_replace('"','', $downloadName) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($filePath);
exit;

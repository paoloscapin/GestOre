<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

$id_corso = intval($_GET['id_corso'] ?? 0);
$recupero_assenza = intval($_GET['recupero_assenza'] ?? 0); // 0/1

if ($id_corso <= 0) {
  echo json_encode(['success'=>false,'error'=>'Parametro mancante']);
  exit;
}

$corso1 = dbGetFirst("SELECT id_materia,id_anno_scolastico FROM corso WHERE id=$id_corso LIMIT 1");
if (!$corso1) {
  echo json_encode(['success'=>false,'error'=>'Corso non trovato']);
  exit;
}

$id_materia = intval($corso1['id_materia']);
$id_anno    = intval($corso1['id_anno_scolastico']);

// filtro “soft” basato sul titolo (coerente con la tua creazione/riuso)
$like = ($recupero_assenza === 1) ? "%recupero assenza%" : "%2ª sessione%";
$like_esc = mysqli_real_escape_string($__con, $like);

$rows = dbGetAll("
  SELECT c.id, c.titolo,
         d.cognome, d.nome
  FROM corso c
  INNER JOIN docente d ON d.id=c.id_docente
  WHERE c.carenza=1
    AND c.carenza_sessione=2
    AND c.id_materia=$id_materia
    AND c.id_anno_scolastico=$id_anno
    AND c.titolo LIKE '$like_esc'
  ORDER BY d.cognome, d.nome, c.titolo
");

echo json_encode(['success'=>true,'corsi'=>$rows ?: []]);

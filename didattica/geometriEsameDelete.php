<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parametro mancante']);
    exit;
}

$sessioni = intval(dbGetValue("SELECT COUNT(*) FROM geometri_sessioni WHERE id_esame=" . dbI($id)));
if ($sessioni > 0) {
    echo json_encode(['success' => false, 'error' => 'Impossibile cancellare: esistono sessioni collegate']);
    exit;
}

dbExec("DELETE FROM geometri_esami WHERE id=" . dbI($id));

echo json_encode(['success' => true]);

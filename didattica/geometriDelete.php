<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parametro mancante']);
    exit;
}

$esiti = intval(dbGetValue("SELECT COUNT(*) FROM geometri_esiti WHERE id_sessione=" . dbI($id)));
if ($esiti > 0) {
    echo json_encode(['success' => false, 'error' => 'Impossibile cancellare: esistono esiti compilati']);
    exit;
}

dbExec("DELETE FROM geometri_sessioni WHERE id=" . dbI($id));

echo json_encode(['success' => true]);

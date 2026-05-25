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

$row = dbGetFirst("SELECT * FROM geometri_esami WHERE id=" . dbI($id) . " LIMIT 1");
if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Esame non trovato']);
    exit;
}

echo json_encode(['success' => true, 'esame' => $row], JSON_UNESCAPED_UNICODE);

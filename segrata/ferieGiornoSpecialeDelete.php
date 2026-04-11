<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

global $__con;

if (!($__con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Connessione database non disponibile'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID non valido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $__con->prepare("
    DELETE FROM permesso_ata_ferie_giorni_speciali
    WHERE id = ?
    LIMIT 1
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore prepare delete'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore eliminazione giorno speciale'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
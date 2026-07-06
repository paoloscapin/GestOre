<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$praticaId = intval($_POST['pratica_id'] ?? 0);
$titolo = trim((string)($_POST['titolo'] ?? ''));
$messaggio = trim((string)($_POST['messaggio'] ?? ''));
$file = $_FILES['allegato'] ?? null;

if ($praticaId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Richiesta non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = iscrizioniPrimeSaveManualEvent($praticaId, $titolo, $messaggio, is_array($file) ? $file : null);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

<?php

require_once '../common/path.php';
require_once '../common/iscrizioniPrimeLib.php';

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo non consentito.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = trim((string)($_POST['token'] ?? ''));

try {
    if (preg_match('/^admin_preview:\d+$/', $token)) {
        require_once '../common/checkSession.php';
        ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');
    }
    $result = iscrizioniPrimeSubmitByToken($token, $_POST);
    if (!$result['ok']) {
        http_response_code(400);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

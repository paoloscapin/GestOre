<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/gmail_api_lib.php';

header('Content-Type: application/json');

ruoloRichiesto('admin');

try {
    $res = gmailStartWatch();

    echo json_encode([
        'ok' => true,
        'response' => $res,
        'state_file' => GMAIL_STATE_FILE
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
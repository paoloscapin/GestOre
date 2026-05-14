<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/gmail_api_lib.php';

header('Content-Type: application/json');
@set_time_limit(60);

ruoloRichiesto('admin');

try {
    $res = gmailStartWatch();
    infoGmail('watch Gmail avviato manualmente da admin historyId=' . trim((string)($res['historyId'] ?? '')));

    echo json_encode([
        'ok' => true,
        'response' => $res,
        'state_file' => GMAIL_STATE_FILE
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    errorGmail('avvio manuale watch Gmail errore: ' . $e->getMessage());
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

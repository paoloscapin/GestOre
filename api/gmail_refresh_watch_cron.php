<?php
require_once __DIR__ . '/../common/gmail_api_lib.php';
require_once __DIR__ . '/../common/__Settings.php';

if (function_exists('initCronLog')) {
    initCronLog('gmail_refresh_watch_cron');
}

header('Content-Type: application/json');
@set_time_limit(60);

global $__settings;
// 🔐 protezione semplice
$secret = $_GET['secret'] ?? '';
$configSecret = $__settings->local->watch_secret ?? '';

if ($secret !== $configSecret) {
    warningGmail('refresh watch negato: secret non valido');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden', 'secret_provided' => $secret]);
    exit;
}

$lock = gmailAcquireLock(GMAIL_CRON_LOCK_FILE, 1800);
if (!$lock) {
    warningGmail('refresh watch ignorato: cron gia in esecuzione');
    echo json_encode([
        'ok' => true,
        'skipped' => true,
        'message' => 'Refresh Gmail watch gia in esecuzione',
        'time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $res = gmailStartWatch();
    infoGmail('refresh watch completato historyId=' . trim((string)($res['historyId'] ?? '')));

    echo json_encode([
        'ok' => true,
        'response' => $res,
        'time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    errorGmail('refresh watch errore: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} finally {
    gmailReleaseLock($lock, GMAIL_CRON_LOCK_FILE);
}

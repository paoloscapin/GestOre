<?php

require_once __DIR__ . '/../common/permessi_uscita_lib.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Script eseguibile solo da CLI/cron.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$date = '';
$date = trim((string)($argv[1] ?? ''));

header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode(permessiUscitaSyncPending($date), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error('tools/permessi_uscita_mastercom_sync.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

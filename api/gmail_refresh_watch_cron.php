<?php
require_once __DIR__ . '/../common/gmail_api_lib.php';
require_once __DIR__ . '/../common/__Settings.php';

header('Content-Type: application/json');

global $__settings;
// 🔐 protezione semplice
$secret = $_GET['secret'] ?? '';
$configSecret = $__settings->local->watch_secret ?? '';

if ($secret !== $configSecret) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden', 'secret_provided' => $secret]);
    exit;
}

try {
    $res = gmailStartWatch();

    echo json_encode([
        'ok' => true,
        'response' => $res,
        'time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
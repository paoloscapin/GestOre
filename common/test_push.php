<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/checkSession.php';
    require_once __DIR__ . '/push_lib.php';

    ruoloRichiesto('admin');

    $username = $_GET['username'] ?? ($_SESSION['username'] ?? '');

    if ($username === '') {
        echo json_encode(['ok' => false, 'error' => 'username mancante'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = sendPushToUser(
        $username,
        'Notifica GestOre',
        'Questa è una notifica di prova.',
        '/GestOre/index.php'
    );

    echo json_encode([
        'ok' => true,
        'username' => $username,
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

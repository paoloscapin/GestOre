<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/connect.php';
    require_once __DIR__ . '/checkSession.php';

    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'SESSIONE_NON_VALIDA'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['endpoint'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'DATI_INVALIDI'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $p256dh = trim((string)($input['keys']['p256dh'] ?? ''));
    $auth = trim((string)($input['keys']['auth'] ?? ''));

    if ($p256dh === '' || $auth === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'CHIAVI_SUBSCRIPTION_MANCANTI'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $username = (string)$_SESSION['username'];
    $endpoint = (string)$input['endpoint'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $endpointEsc = addslashes($endpoint);
    $p256dhEsc = addslashes($p256dh);
    $authEsc = addslashes($auth);
    $userAgentEsc = addslashes($userAgent);
    $usernameEsc = addslashes($username);

    $existing = dbGetFirst("
        SELECT id
        FROM push_subscriptions
        WHERE endpoint = '$endpointEsc'
        LIMIT 1
    ");

    if ($existing && isset($existing['id'])) {
        dbExec("
            UPDATE push_subscriptions SET
                username = '$usernameEsc',
                p256dh = '$p256dhEsc',
                auth = '$authEsc',
                user_agent = '$userAgentEsc',
                attivo = 1,
                updated_at = NOW()
            WHERE id = " . intval($existing['id']) . "
        ");
    } else {
        dbExec("
            INSERT INTO push_subscriptions
                (username, endpoint, p256dh, auth, user_agent, attivo, created_at, updated_at)
            VALUES
                ('$usernameEsc', '$endpointEsc', '$p256dhEsc', '$authEsc', '$userAgentEsc', 1, NOW(), NOW())
        ");
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

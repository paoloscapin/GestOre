<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/googleCalendarDocentiLib.php';

setLogChannel('google_calendar');

header('Content-Type: application/json; charset=utf-8');

function googleCalendarDocentiReadPayload()
{
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) {
        $payload = [];
    }
    foreach ($_GET as $k => $v) {
        if (!isset($payload[$k])) {
            $payload[$k] = $v;
        }
    }
    foreach ($_POST as $k => $v) {
        if (!isset($payload[$k])) {
            $payload[$k] = $v;
        }
    }
    return $payload;
}

function googleCalendarDocentiIsIsoDate($value)
{
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value);
}

try {
    $cfg = googleCalendarDocentiConfig();
    $payload = googleCalendarDocentiReadPayload();

    $token = trim((string)($payload['token'] ?? ''));
    $expected = trim((string)($cfg->syncSecret ?? ''));
    if ($expected === '' || !hash_equals($expected, $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token non valido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $username = trim((string)($payload['username'] ?? ''));
    $from = trim((string)($payload['from'] ?? ''));
    $to = trim((string)($payload['to'] ?? ''));

    if ($from === '') {
        $from = date('Y-m-d');
    }
    if ($to === '') {
        $days = intval($payload['days'] ?? 120);
        if ($days < 1) $days = 120;
        if ($days > 370) $days = 370;
        $to = date('Y-m-d', strtotime($from . ' +' . $days . ' days'));
    }

    if (!googleCalendarDocentiIsIsoDate($from) || !googleCalendarDocentiIsIsoDate($to)) {
        throw new Exception('Date non valide: usare YYYY-MM-DD');
    }
    if ($to < $from) {
        throw new Exception('Intervallo date non valido');
    }

    infoGoogleCalendar('Avvio sync calendari docenti: ' . json_encode([
        'username' => $username,
        'from' => $from,
        'to' => $to
    ], JSON_UNESCAPED_UNICODE));

    $results = googleCalendarDocentiSync($username, $from, $to);

    echo json_encode([
        'ok' => true,
        'username' => $username,
        'from' => $from,
        'to' => $to,
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    errorGoogleCalendar('Errore googleCalendarDocentiSync: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}


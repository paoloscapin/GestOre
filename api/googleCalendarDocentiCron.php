<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/googleCalendarDocentiLib.php';

setLogChannel('google_calendar');

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

function googleCalendarDocentiCronParam($name, $default = '')
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        foreach (($argv ?? []) as $arg) {
            if (strpos($arg, '--' . $name . '=') === 0) {
                return substr($arg, strlen($name) + 3);
            }
        }
        return $default;
    }

    return isset($_REQUEST[$name]) ? trim((string)$_REQUEST[$name]) : $default;
}

try {
    $cfg = googleCalendarDocentiConfig();

    if (!$isCli) {
        $token = googleCalendarDocentiCronParam('token');
        $expected = trim((string)($cfg->syncSecret ?? ''));
        if ($expected === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token non valido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $username = googleCalendarDocentiCronParam('username', '');
    $days = intval(googleCalendarDocentiCronParam('days', '15'));
    if ($days < 1) $days = 15;
    if ($days > 60) $days = 60;

    $from = date('Y-m-d');
    $to = date('Y-m-d', strtotime($from . ' +' . $days . ' days'));

    infoGoogleCalendar('Avvio cron sync calendari docenti: ' . json_encode([
        'username' => $username,
        'from' => $from,
        'to' => $to,
        'days' => $days
    ], JSON_UNESCAPED_UNICODE));

    $results = googleCalendarDocentiSync($username, $from, $to);

    echo json_encode([
        'ok' => true,
        'username' => $username,
        'from' => $from,
        'to' => $to,
        'days' => $days,
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($isCli) echo PHP_EOL;
} catch (Throwable $e) {
    errorGoogleCalendar('Errore cron googleCalendarDocentiCron: ' . $e->getMessage());
    if (!$isCli) http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($isCli) echo PHP_EOL;
}

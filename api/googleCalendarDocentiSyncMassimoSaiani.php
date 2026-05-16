<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/googleCalendarDocentiLib.php';

setLogChannel('google_calendar');

$username = 'massimo.saiani';
$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

function googleCalendarDocentiSaianiParam($name, $default = '')
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

function googleCalendarDocentiSaianiIsoDate($value)
{
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value);
}

function googleCalendarDocentiSaianiResolveRange()
{
    $range = strtolower(trim((string)googleCalendarDocentiSaianiParam('range', '')));
    $today = date('Y-m-d');

    if ($range === '' || $range === 'custom') {
        return [
            googleCalendarDocentiSaianiParam('from', date('Y-m-d', strtotime('-4 months'))),
            googleCalendarDocentiSaianiParam('to', date('Y-m-d', strtotime('+4 months')))
        ];
    }

    if (in_array($range, ['oggi', 'today'], true)) {
        return [$today, $today];
    }

    if (preg_match('/^(\d+)\s*(g|gg|giorni|d|days)$/', $range, $m)) {
        $days = max(0, intval($m[1]));
        return [$today, date('Y-m-d', strtotime($today . ' +' . $days . ' days'))];
    }

    if (preg_match('/^(\d+)\s*(m|mesi|months)$/', $range, $m)) {
        $months = max(0, intval($m[1]));
        return [date('Y-m-d', strtotime($today . ' -' . $months . ' months')), date('Y-m-d', strtotime($today . ' +' . $months . ' months'))];
    }

    throw new Exception('Range non valido. Usa oggi, 7gg, 30gg, 4mesi oppure from/to.');
}

try {
    $cfg = googleCalendarDocentiConfig();

    if (!$isCli) {
        $token = googleCalendarDocentiSaianiParam('token');
        $expected = trim((string)($cfg->syncSecret ?? ''));
        if ($expected === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token non valido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    [$from, $to] = googleCalendarDocentiSaianiResolveRange();
    $days = intval(googleCalendarDocentiSaianiParam('days', '240'));
    if ($days < 1) $days = 240;
    if ($days > 370) $days = 370;

    if ($to === '') {
        $to = date('Y-m-d', strtotime($from . ' +' . $days . ' days'));
    }

    if (!googleCalendarDocentiSaianiIsoDate($from) || !googleCalendarDocentiSaianiIsoDate($to)) {
        throw new Exception('Date non valide: usare YYYY-MM-DD');
    }
    if ($to < $from) {
        throw new Exception('Intervallo date non valido');
    }

    $results = googleCalendarDocentiSync($username, $from, $to);

    $out = [
        'ok' => true,
        'username' => $username,
        'from' => $from,
        'to' => $to,
        'results' => $results
    ];

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($isCli) {
        echo PHP_EOL;
    }
} catch (Throwable $e) {
    errorGoogleCalendar('Errore sync massimo.saiani: ' . $e->getMessage());
    if (!$isCli) {
        http_response_code(500);
    }
    echo json_encode([
        'ok' => false,
        'username' => $username,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($isCli) {
        echo PHP_EOL;
    }
}

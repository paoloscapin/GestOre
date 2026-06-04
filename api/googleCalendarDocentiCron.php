<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/__Log.php';

initCronLog('googleCalendarDocentiCron');

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
        $token = googleCalendarDocentiCronParam('token', googleCalendarDocentiCronParam('secret'));
        $expected = trim((string)($cfg->syncSecret ?? ''));
        if ($expected === '' || !hash_equals($expected, $token)) {
            warningGoogleCalendar('Cron sync calendari docenti rifiutato: token non valido o mancante');
            warningcron('googleCalendarDocentiCron rifiutato: token non valido o mancante');
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Token non valido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $username = googleCalendarDocentiCronParam('username', '');
    $defaultDays = googleCalendarDocentiIntConfig('cronFutureDays', 15, 1, 60);
    $days = intval(googleCalendarDocentiCronParam('days', (string)$defaultDays));
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

    if ($username === '' && googleCalendarDocentiBoolConfig('cronOnlyEnabledTeachers', true)) {
        $results = googleCalendarDocentiSyncEnabledTeachers($from, $to, 'cron');
    } else {
        $results = googleCalendarDocentiSync($username, $from, $to);
    }

    $okCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    $totalCreated = 0;
    $totalUpdated = 0;
    $totalUnchanged = 0;
    $totalDeleted = 0;
    foreach (($results ?: []) as $resultRow) {
        if (!empty($resultRow['skipped'])) {
            $skippedCount++;
        } elseif (!empty($resultRow['error']) || (isset($resultRow['ok']) && empty($resultRow['ok']))) {
            $errorCount++;
        } else {
            $okCount++;
        }
        $stats = is_array($resultRow['stats'] ?? null) ? $resultRow['stats'] : [];
        $totalCreated += intval($stats['created'] ?? 0);
        $totalUpdated += intval($stats['updated'] ?? 0);
        $totalUnchanged += intval($stats['unchanged'] ?? 0);
        $totalDeleted += intval($stats['deleted'] ?? 0);
    }
    $summary = 'googleCalendarDocentiCron risultati=' . count($results ?: []) .
        ' ok=' . $okCount .
        ' errori=' . $errorCount .
        ' saltati=' . $skippedCount .
        ' created=' . $totalCreated .
        ' updated=' . $totalUpdated .
        ' unchanged=' . $totalUnchanged .
        ' deleted=' . $totalDeleted .
        ' periodo=' . $from . '/' . $to;
    infoGoogleCalendar($summary);
    infocron($summary);

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

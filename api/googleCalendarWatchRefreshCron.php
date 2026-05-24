<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/googleCalendarLib.php';
require_once __DIR__ . '/../common/__Log.php';

if (function_exists('initCronLog')) {
    initCronLog('googleCalendarWatchRefreshCron');
}

header('Content-Type: application/json; charset=utf-8');

global $__settings;

function googleCalendarWatchRefreshRequestValue(string $key, string $default = ''): string
{
    if (isset($_GET[$key])) {
        return trim((string)$_GET[$key]);
    }
    if (isset($_POST[$key])) {
        return trim((string)$_POST[$key]);
    }
    if (php_sapi_name() === 'cli') {
        global $argv;
        foreach (($argv ?? []) as $arg) {
            if (strpos($arg, $key . '=') === 0) {
                return trim(substr($arg, strlen($key) + 1));
            }
            if (strpos($arg, '--' . $key . '=') === 0) {
                return trim(substr($arg, strlen($key) + 3));
            }
        }
    }
    return $default;
}

$secret = googleCalendarWatchRefreshRequestValue('secret');
$configSecret = $__settings->local->watch_secret ?? '';

if ($secret !== $configSecret) {
    http_response_code(403);

    echo json_encode([
        'ok' => false,
        'error' => 'Forbidden'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $nowMs = time() * 1000;
    $renewBeforeMs = 24 * 60 * 60 * 1000;

    $rows = dbGetAll("
        SELECT *
        FROM google_calendar_config
        WHERE attivo = 1
          AND watch_enabled = 1
          AND (
                watch_expiration IS NULL
                OR watch_expiration = 0
                OR watch_expiration < " . intval($nowMs + $renewBeforeMs) . "
          )
    ");

    $results = [];

    foreach (($rows ?: []) as $row) {
        if (!empty($row['watch_channel_id']) && !empty($row['watch_resource_id'])) {
            try {
                googleCalendarStopWatch($row['watch_channel_id'], $row['watch_resource_id']);
            } catch (Throwable $stopError) {
                // Non blocco il rinnovo se lo stop fallisce.
            }
        }

        $results[] = googleCalendarStartWatchForConfig($row);
    }

    echo json_encode([
        'ok' => true,
        'renewed' => count($results),
        'response' => $results,
        'time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

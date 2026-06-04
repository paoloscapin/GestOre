<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/../common/mastercom/grades_cache_lib.php';

if (function_exists('initCronLog')) {
    initCronLog('mastercomGradesSyncCron');
}

@ignore_user_abort(true);
@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');

$isCli = (PHP_SAPI === 'cli');
header('Content-Type: application/json; charset=utf-8');

function mastercomGradesSyncCronParam(string $name, string $default = ''): string
{
    if (isset($_REQUEST[$name])) {
        return trim((string)$_REQUEST[$name]);
    }

    global $argv;
    if (is_array($argv)) {
        foreach ($argv as $arg) {
            $arg = (string)$arg;
            $query = parse_url($arg, PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
                if (isset($params[$name])) {
                    return trim((string)$params[$name]);
                }
            }
            if (strpos($arg, '--' . $name . '=') === 0) {
                return trim(substr($arg, strlen($name) + 3));
            }
            if (strpos($arg, $name . '=') === 0) {
                return trim(substr($arg, strlen($name) + 1));
            }
        }
    }

    return $default;
}

function mastercomGradesSyncCronSecret(): string
{
    global $__settings;
    if (isset($__settings->local->mastercomGradesSync->syncSecret)) {
        return trim((string)$__settings->local->mastercomGradesSync->syncSecret);
    }
    if (isset($__settings->local->googleCalendarDocenti->syncSecret)) {
        return trim((string)$__settings->local->googleCalendarDocenti->syncSecret);
    }
    if (isset($__settings->local->watch_secret)) {
        return trim((string)$__settings->local->watch_secret);
    }
    return '';
}

try {
    if (!$isCli) {
        $expectedSecret = mastercomGradesSyncCronSecret();
        $providedSecret = mastercomGradesSyncCronParam('secret', mastercomGradesSyncCronParam('token', ''));
        if ($expectedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
            warningcron('mastercomGradesSyncCron rifiutato: secret non valido o mancante');
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Secret non valido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $scope = mastercomGradesSyncCronParam('range', '30gg');
    $range = mastercomGradesCacheResolveRange($scope);
    $startDate = mastercomGradesSyncCronParam('start_date', mastercomGradesSyncCronParam('from', $range['start']));
    $endDate = mastercomGradesSyncCronParam('end_date', mastercomGradesSyncCronParam('to', $range['end']));
    $options = [
        'class_id' => intval(mastercomGradesSyncCronParam('class_id', '0')),
        'subject_id' => intval(mastercomGradesSyncCronParam('subject_id', '0')),
        'start_date' => $startDate,
        'end_date' => $endDate,
    ];

    infocron(
        'Avvio mastercomGradesSyncCron range=' . $scope .
        ' class_id=' . intval($options['class_id']) .
        ' subject_id=' . intval($options['subject_id']) .
        ' periodo=' . $options['start_date'] . '/' . $options['end_date']
    );

    $lastProgressKey = '';
    $progress = function (string $stage, int $current, int $total, string $message) use (&$lastProgressKey): void {
        $key = $stage . ':' . $current . ':' . $total;
        if ($key === $lastProgressKey) {
            return;
        }
        $lastProgressKey = $key;
        infocron('mastercomGradesSyncCron ' . $stage . ' ' . $current . '/' . $total . ' ' . $message);
    };

    $result = mastercomGradesCacheSync($options, $progress);
    $stats = is_array($result['stats'] ?? null) ? $result['stats'] : [];
    $summary = 'mastercomGradesSyncCron completato ok=' . (!empty($result['ok']) ? '1' : '0') .
        ' range=' . $scope .
        ' class_id=' . intval($options['class_id']) .
        ' subject_id=' . intval($options['subject_id']) .
        ' periodo=' . $options['start_date'] . '/' . $options['end_date'] .
        ' classes=' . intval($stats['classes'] ?? 0) .
        ' subjects=' . intval($stats['subjects'] ?? 0) .
        ' averages=' . intval($stats['averages'] ?? 0) .
        ' grades=' . intval($stats['grades'] ?? 0) .
        ' errors=' . intval($stats['errors'] ?? 0);
    infocron($summary);

    echo json_encode([
        'ok' => !empty($result['ok']),
        'range' => $scope,
        'class_id' => intval($options['class_id']),
        'subject_id' => intval($options['subject_id']),
        'from' => $options['start_date'],
        'to' => $options['end_date'],
        'message' => $result['message'] ?? '',
        'stats' => $stats,
        'errors' => $result['errors'] ?? [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($isCli) {
        echo PHP_EOL;
    }
} catch (Throwable $e) {
    errorcron('mastercomGradesSyncCron errore: ' . $e->getMessage());
    if (!$isCli) {
        http_response_code(500);
    }
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($isCli) {
        echo PHP_EOL;
    }
}

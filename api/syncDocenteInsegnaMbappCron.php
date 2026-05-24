<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/../common/docente_insegna_mbapp_sync_lib.php';

if (function_exists('initCronLog')) {
    initCronLog('syncDocenteInsegnaMbappCron');
}

function docenteInsegnaCronArg(string $name, string $default = ''): string
{
    if (isset($_GET[$name])) {
        return trim((string)$_GET[$name]);
    }

    global $argv;
    if (is_array($argv)) {
        foreach ($argv as $arg) {
            $query = parse_url((string)$arg, PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
                if (isset($params[$name])) {
                    return trim((string)$params[$name]);
                }
            }
            if (strpos((string)$arg, $name . '=') === 0) {
                return trim(substr((string)$arg, strlen($name) + 1));
            }
        }
    }

    return $default;
}

function docenteInsegnaCronSecret(): string
{
    global $__settings;
    if (isset($__settings->local->docenteInsegnaMbappSync->syncSecret)) {
        return trim((string)$__settings->local->docenteInsegnaMbappSync->syncSecret);
    }
    if (isset($__settings->local->googleCalendarDocenti->syncSecret)) {
        return trim((string)$__settings->local->googleCalendarDocenti->syncSecret);
    }
    if (isset($__settings->local->watch_secret)) {
        return trim((string)$__settings->local->watch_secret);
    }
    return '';
}

header('Content-Type: application/json; charset=utf-8');

try {
    $expectedSecret = docenteInsegnaCronSecret();
    $providedSecret = docenteInsegnaCronArg('secret', '');
    if (php_sapi_name() !== 'cli' && $expectedSecret !== '' && !hash_equals($expectedSecret, $providedSecret)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Secret non valido'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    [$from, $to] = docenteInsegnaMbappLastWeekRange();
    $from = docenteInsegnaCronArg('from', $from);
    $to = docenteInsegnaCronArg('to', $to);

    $result = docenteInsegnaMbappSync([
        'from' => $from,
        'to' => $to,
        'apply' => true,
        'rimuovi_obsoleti' => true,
        'ignora_incongruenze' => true,
        'preserva_se_vuoto' => true,
    ]);

    $payload = [
        'ok' => true,
        'from' => $result['from'] ?? $from,
        'to' => $result['to'] ?? $to,
        'skipped' => !empty($result['skipped']),
        'skip_reason' => $result['skip_reason'] ?? '',
        'stats' => [
            'mbapp_rows' => count($result['mbapp_rows'] ?? []),
            'created' => count($result['to_insert'] ?? []),
            'unchanged' => count($result['already_present'] ?? []),
            'deleted' => count($result['to_remove'] ?? []),
            'ignored_anomalies' => count($result['errors'] ?? []),
        ],
    ];

    if (function_exists('infocron')) {
        infocron('sync docente_insegna MBApp ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (function_exists('errorcron')) {
        errorcron('sync docente_insegna MBApp errore: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/../common/studentiAttributiRiservatiLib.php';

if (function_exists('initCronLog')) {
    initCronLog('studentiAttributiRiservatiMbappCron');
}

function studentiAttrCronParam(string $name, string $default = ''): string
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

function studentiAttrCronSecret(): string
{
    global $__settings;
    if (isset($__settings->local->studentiAttributiRiservatiMbappSync->syncSecret)) {
        return trim((string)$__settings->local->studentiAttributiRiservatiMbappSync->syncSecret);
    }
    if (isset($__settings->local->watch_secret)) {
        return trim((string)$__settings->local->watch_secret);
    }
    return '';
}

header('Content-Type: application/json; charset=utf-8');

try {
    $expectedSecret = studentiAttrCronSecret();
    $providedSecret = studentiAttrCronParam('secret', studentiAttrCronParam('token', ''));
    if (php_sapi_name() !== 'cli' && $expectedSecret !== '' && !hash_equals($expectedSecret, $providedSecret)) {
        if (function_exists('warningcron')) {
            warningcron('sync attributi riservati studenti MBAPP rifiutato: secret non valido');
        }
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Secret non valido'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = studentiAttrSyncFromMbapp();
    $payload = [
        'ok' => true,
        'stats' => $result,
    ];

    if (function_exists('infocron')) {
        infocron('sync attributi riservati studenti MBAPP ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (function_exists('errorcron')) {
        errorcron('sync attributi riservati studenti MBAPP errore: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}


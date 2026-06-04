<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/../common/mastercom/debts_lib.php';

if (function_exists('initCronLog')) {
    initCronLog('mastercomDebtsSyncCron');
}

@ignore_user_abort(true);
@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');

$isCli = (PHP_SAPI === 'cli');
header('Content-Type: application/json; charset=utf-8');

function mastercomDebtsSyncCronParam(string $name, string $default = ''): string
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

function mastercomDebtsSyncCronSecret(): string
{
    global $__settings;
    if (isset($__settings->local->mastercomDebtsSync->syncSecret)) {
        return trim((string)$__settings->local->mastercomDebtsSync->syncSecret);
    }
    if (isset($__settings->local->googleCalendarDocenti->syncSecret)) {
        return trim((string)$__settings->local->googleCalendarDocenti->syncSecret);
    }
    if (isset($__settings->local->watch_secret)) {
        return trim((string)$__settings->local->watch_secret);
    }
    return '';
}

function mastercomDebtsSyncCronInAllowedWindow(DateTime $now): bool
{
    return intval($now->format('n')) === 6 && intval($now->format('j')) >= 1 && intval($now->format('j')) <= 15;
}

try {
    if (!$isCli) {
        $expectedSecret = mastercomDebtsSyncCronSecret();
        $providedSecret = mastercomDebtsSyncCronParam('secret', mastercomDebtsSyncCronParam('token', ''));
        if ($expectedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
            warningcron('mastercomDebtsSyncCron rifiutato: secret non valido o mancante');
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Secret non valido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $force = in_array(strtolower(mastercomDebtsSyncCronParam('force', '0')), ['1', 'true', 'yes', 'si'], true);
    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
    if (!$force && !mastercomDebtsSyncCronInAllowedWindow($now)) {
        $message = 'Sync carenze MasterCom saltato: fuori periodo 1-15 giugno';
        infocron('mastercomDebtsSyncCron ' . $message . ' data=' . $now->format('Y-m-d'));
        echo json_encode([
            'ok' => true,
            'skipped' => true,
            'message' => $message,
            'date' => $now->format('Y-m-d'),
            'allowed_from' => $now->format('Y') . '-06-01',
            'allowed_to' => $now->format('Y') . '-06-15',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($isCli) {
            echo PHP_EOL;
        }
        exit;
    }

    infocron('Avvio mastercomDebtsSyncCron data=' . $now->format('Y-m-d') . ' force=' . ($force ? '1' : '0'));
    $result = mastercomDebtsFetchAndStoreAllClasses();
    $stats = is_array($result['stats'] ?? null) ? $result['stats'] : [];
    $summary = 'mastercomDebtsSyncCron completato ok=' . (!empty($result['ok']) ? '1' : '0') .
        ' classes=' . intval($stats['classes'] ?? 0) .
        ' saved=' . intval($stats['saved'] ?? 0) .
        ' deleted_stale=' . intval($stats['deleted_stale'] ?? 0) .
        ' without_student=' . intval($stats['without_student'] ?? 0) .
        ' without_subject=' . intval($stats['without_subject'] ?? 0) .
        ' without_year=' . intval($stats['without_year'] ?? 0) .
        ' errors=' . intval($stats['errors'] ?? 0);
    infocron($summary);

    echo json_encode([
        'ok' => !empty($result['ok']),
        'skipped' => false,
        'message' => $result['message'] ?? '',
        'date' => $now->format('Y-m-d'),
        'stats' => $stats,
        'errors' => $result['errors'] ?? [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($isCli) {
        echo PHP_EOL;
    }
} catch (Throwable $e) {
    errorcron('mastercomDebtsSyncCron errore: ' . $e->getMessage());
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

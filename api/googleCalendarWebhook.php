<?php

require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/googleCalendarLib.php';
require_once __DIR__ . '/../common/__Log.php';

header('Content-Type: application/json; charset=utf-8');

$logDir = __DIR__ . '/../log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$headers = function_exists('getallheaders') ? getallheaders() : [];

$channelId = $_SERVER['HTTP_X_GOOG_CHANNEL_ID'] ?? ($headers['X-Goog-Channel-ID'] ?? '');
$resourceId = $_SERVER['HTTP_X_GOOG_RESOURCE_ID'] ?? ($headers['X-Goog-Resource-ID'] ?? '');
$resourceState = $_SERVER['HTTP_X_GOOG_RESOURCE_STATE'] ?? ($headers['X-Goog-Resource-State'] ?? '');
$messageNumber = $_SERVER['HTTP_X_GOOG_MESSAGE_NUMBER'] ?? ($headers['X-Goog-Message-Number'] ?? '');
$channelToken = $_SERVER['HTTP_X_GOOG_CHANNEL_TOKEN'] ?? ($headers['X-Goog-Channel-Token'] ?? '');

infoGoogleCalendar(
    'Webhook ricevuto: ' .
    json_encode([
        'channelId' => $channelId,
        'resourceId' => $resourceId,
        'resourceState' => $resourceState,
        'messageNumber' => $messageNumber
    ], JSON_UNESCAPED_UNICODE)
);

if ($channelId === '' || $resourceId === '') {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => 'Header Google mancanti'
    ]);

    exit;
}

$expectedSecret = googleCalendarGetSyncSecret();

if ($expectedSecret !== '' && $channelToken !== $expectedSecret) {
    http_response_code(403);

    echo json_encode([
        'ok' => false,
        'error' => 'Token canale non valido'
    ]);

    exit;
}

$config = dbGetFirst("
    SELECT *
    FROM google_calendar_config
    WHERE watch_channel_id = '" . dbEscape($channelId) . "'
      AND watch_resource_id = '" . dbEscape($resourceId) . "'
      AND attivo = 1
    LIMIT 1
");
file_put_contents(
    __DIR__ . '/../log/debug_google_calendar_config_lookup.log',
    date('Y-m-d H:i:s') . "\n" .
    "channelId=" . $channelId . "\n" .
    "resourceId=" . $resourceId . "\n" .
    "config trovata=" . ($config == null ? 'NO' : 'SI ID ' . intval($config['id'])) . "\n\n",
    FILE_APPEND
);
if ($config == null) {
    file_put_contents(
        __DIR__ . '/../log/debug_google_calendar_config_not_found.log',
        date('Y-m-d H:i:s') . "\n" .
        "channelId=" . $channelId . "\n" .
        "resourceId=" . $resourceId . "\n\n",
        FILE_APPEND
    );

    http_response_code(200);

    echo json_encode([
        'ok' => false,
        'error' => 'Calendario non riconosciuto',
        'channelId' => $channelId,
        'resourceId' => $resourceId
    ]);

    exit;
}

try {
    if ($resourceState === 'sync') {
        echo json_encode([
            'ok' => true,
            'message' => 'Notifica sync iniziale ricevuta',
            'config_id' => intval($config['id'])
        ]);

        exit;
    }

file_put_contents(
    __DIR__ . '/../log/debug_google_calendar_process_start.log',
    date('Y-m-d H:i:s') . " START PROCESS CONFIG ID " . intval($config['id']) . "\n" .
    "resourceState=" . $resourceState . "\n\n",
    FILE_APPEND
);

$res = googleCalendarProcessWebhookForConfig($config);

file_put_contents(
    __DIR__ . '/../log/debug_google_calendar_process_result.log',
    date('Y-m-d H:i:s') . "\n" .
    json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
    "\n\n",
    FILE_APPEND
);

    echo json_encode([
        'ok' => true,
        'config_id' => intval($config['id']),
        'calendar' => $config['nome'] ?? '',
        'resourceState' => $resourceState,
        'sync' => $res
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    file_put_contents(
        $logDir . '/debug_google_calendar_webhook_error.log',
        date('Y-m-d H:i:s') . " ERROR:\n" .
        $e->getMessage() .
        "\n\n",
        FILE_APPEND
    );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
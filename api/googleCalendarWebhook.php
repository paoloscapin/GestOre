<?php

require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/googleCalendarLib.php';

header('Content-Type: application/json; charset=utf-8');

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
    warningGoogleCalendar('Webhook rifiutato: header Google mancanti');

    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Header Google mancanti'
    ]);
    exit;
}

$expectedSecret = googleCalendarGetSyncSecret();

if ($expectedSecret !== '' && $channelToken !== $expectedSecret) {
    warningGoogleCalendar(
        'Webhook rifiutato: token canale non valido channelId=' . $channelId
    );

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

if ($config == null) {
    warningGoogleCalendar(
        'Calendario non riconosciuto: ' .
        json_encode([
            'channelId' => $channelId,
            'resourceId' => $resourceId,
            'resourceState' => $resourceState
        ], JSON_UNESCAPED_UNICODE)
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

infoGoogleCalendar(
    'Config calendario trovata: ' .
    json_encode([
        'config_id' => intval($config['id']),
        'nome' => $config['nome'] ?? '',
        'calendar_id' => $config['calendar_id'] ?? '',
        'resourceState' => $resourceState
    ], JSON_UNESCAPED_UNICODE)
);

try {
    if ($resourceState === 'sync') {
        infoGoogleCalendar(
            'Notifica sync iniziale ricevuta config_id=' . intval($config['id'])
        );

        echo json_encode([
            'ok' => true,
            'message' => 'Notifica sync iniziale ricevuta',
            'config_id' => intval($config['id'])
        ]);
        exit;
    }

    infoGoogleCalendar(
        'Avvio processo sync config_id=' . intval($config['id']) .
        ' resourceState=' . $resourceState
    );

    $res = googleCalendarProcessWebhookForConfig($config);

    infoGoogleCalendar(
        'Processo sync completato: ' .
        json_encode([
            'config_id' => intval($config['id']),
            'nome' => $config['nome'] ?? '',
            'resourceState' => $resourceState,
            'result' => $res
        ], JSON_UNESCAPED_UNICODE)
    );

    echo json_encode([
        'ok' => true,
        'config_id' => intval($config['id']),
        'calendar' => $config['nome'] ?? '',
        'resourceState' => $resourceState,
        'sync' => $res
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    errorGoogleCalendar(
        'Errore webhook Calendar: ' .
        json_encode([
            'config_id' => intval($config['id'] ?? 0),
            'channelId' => $channelId,
            'resourceId' => $resourceId,
            'resourceState' => $resourceState,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE)
    );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
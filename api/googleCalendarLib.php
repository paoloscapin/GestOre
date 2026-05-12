<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/google-client-library/src/Google_Client.php';
require_once __DIR__ . '/mbappCalendarSyncLib.php';
require_once __DIR__ . '/../common/__Log.php';

function googleCalendarGetConfig()
{
    global $__settings;

    if (!isset($__settings->local->googleCalendar)) {
        throw new Exception('Configurazione local.googleCalendar mancante in GestOre.json');
    }

    return $__settings->local->googleCalendar;
}

function googleCalendarGetClient()
{
    $gc = googleCalendarGetConfig();

    $client = new Google_Client();

    $client->setApplicationName(
        $gc->applicationName ?? 'GestOre Google Calendar'
    );

    $client->setClientId($gc->clientId ?? '');

    $client->setClientSecret($gc->clientSecret ?? '');

    $client->setAccessType('offline');

    $client->setScopes([
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events'
    ]);

    $token = [
        'access_token' => $gc->accessToken ?? '',
        'refresh_token' => $gc->refreshToken ?? '',
        'expires_in' => intval($gc->expiresIn ?? 3600),
        'created' => intval($gc->created ?? 0)
    ];

    $client->setAccessToken(json_encode($token));

    if ($client->isAccessTokenExpired()) {

        if (empty($token['refresh_token'])) {
            throw new Exception(
                'Refresh token Google Calendar mancante'
            );
        }

        $client->refreshToken($token['refresh_token']);

        $newTokenJson = $client->getAccessToken();

        $newToken = json_decode($newTokenJson, true);

        // qui poi aggiorneremo automaticamente il json
    }

    return $client;
}

function googleCalendarGetAccessToken()
{
    $client = googleCalendarGetClient();

    $tokenJson = $client->getAccessToken();

    $token = json_decode($tokenJson, true);

    if (!isset($token['access_token'])) {
        throw new Exception(
            'Access token Google Calendar non disponibile'
        );
    }

    return $token['access_token'];
}

function googleCalendarApiRequest(
    $method,
    $url,
    $data = null
) {

    $accessToken = googleCalendarGetAccessToken();

    $ch = curl_init();

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    curl_setopt($ch, CURLOPT_URL, $url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data !== null) {
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($data)
        );
    }

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError) {
        throw new Exception(
            'Errore CURL Google Calendar: ' . $curlError
        );
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {

        throw new Exception(
            'Errore Google Calendar HTTP ' .
            $httpCode .
            ': ' .
            $response
        );
    }

    return $decoded;
}

function googleCalendarListCalendars()
{
    return googleCalendarApiRequest(
        'GET',
        'https://www.googleapis.com/calendar/v3/users/me/calendarList'
    );
}

function googleCalendarGetSyncSecret()
{
    $gc = googleCalendarGetConfig();
    return $gc->syncSecret ?? '';
}

function googleCalendarGetWebhookUrl()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https'
        : 'https';

    return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/GestOre/api/googleCalendarWebhook.php';
}

function googleCalendarEncodeCalendarId($calendarId)
{
    return rawurlencode($calendarId);
}

function googleCalendarBuildEventsListUrl($calendarId, $params = [])
{
    $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
        googleCalendarEncodeCalendarId($calendarId) .
        '/events';

    if (count($params) > 0) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

function googleCalendarInitialSyncToken($calendarId)
{
    $timeMin = date('c', strtotime('-30 days'));
    $timeMax = date('c', strtotime('+365 days'));

    $params = [
        'singleEvents' => 'true',
        'showDeleted' => 'true',
        'timeMin' => $timeMin,
        'timeMax' => $timeMax,
        'maxResults' => 2500
    ];

    $nextSyncToken = null;
    $pageToken = null;

    do {
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $res = googleCalendarApiRequest(
            'GET',
            googleCalendarBuildEventsListUrl($calendarId, $params)
        );

        $pageToken = $res['nextPageToken'] ?? null;

        if (isset($res['nextSyncToken'])) {
            $nextSyncToken = $res['nextSyncToken'];
        }

    } while ($pageToken);

    return $nextSyncToken;
}

function googleCalendarStartWatchForConfig($config)
{
    $calendarId = $config['calendar_id'];
    $channelId = 'gestore-calendar-' . intval($config['id']) . '-' . time() . '-' . bin2hex(random_bytes(6));
    $watchToken = googleCalendarGetSyncSecret();

    $body = [
        'id' => $channelId,
        'type' => 'web_hook',
        'address' => googleCalendarGetWebhookUrl(),
        'token' => $watchToken
    ];

    $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
        googleCalendarEncodeCalendarId($calendarId) .
        '/events/watch';

    $res = googleCalendarApiRequest('POST', $url, $body);

    $resourceId = $res['resourceId'] ?? '';
    $expiration = isset($res['expiration']) ? intval($res['expiration']) : 0;

    $syncToken = googleCalendarInitialSyncToken($calendarId);

    dbExec("
        UPDATE google_calendar_config
        SET watch_channel_id = '" . dbEscape($channelId) . "',
            watch_resource_id = '" . dbEscape($resourceId) . "',
            watch_token = '" . dbEscape($watchToken) . "',
            watch_expiration = " . intval($expiration) . ",
            sync_token = " . ($syncToken ? "'" . dbEscape($syncToken) . "'" : "NULL") . ",
            last_full_sync = NOW(),
            last_watch_start = NOW(),
            updated_at = NOW()
        WHERE id = " . intval($config['id'])
    );

    return [
        'config_id' => intval($config['id']),
        'nome' => $config['nome'] ?? '',
        'calendar_id' => $calendarId,
        'watch_response' => $res,
        'sync_token_set' => $syncToken ? true : false
    ];
}

function googleCalendarStartWatchAll()
{
    $rows = dbGetAll("
        SELECT *
        FROM google_calendar_config
        WHERE attivo = 1
          AND watch_enabled = 1
    ");

    $out = [];

    foreach (($rows ?: []) as $row) {
        $out[] = googleCalendarStartWatchForConfig($row);
    }

    return $out;
}

function googleCalendarStopWatch($channelId, $resourceId)
{
    if ($channelId == '' || $resourceId == '') {
        return ['ok' => false, 'message' => 'channelId/resourceId mancanti'];
    }

    return googleCalendarApiRequest(
        'POST',
        'https://www.googleapis.com/calendar/v3/channels/stop',
        [
            'id' => $channelId,
            'resourceId' => $resourceId
        ]
    );
}

function googleCalendarProcessWebhookForConfig($config)
{
    $calendarId = $config['calendar_id'];
    $syncToken = $config['sync_token'] ?? '';

    if ($syncToken == '') {
        $syncToken = googleCalendarInitialSyncToken($calendarId);

        dbExec("
            UPDATE google_calendar_config
            SET sync_token = '" . dbEscape($syncToken) . "',
                last_full_sync = NOW(),
                updated_at = NOW()
            WHERE id = " . intval($config['id'])
        );

        return [
            'ok' => true,
            'message' => 'Sync token iniziale creato',
            'events' => 0
        ];
    }

    $params = [
        'syncToken' => $syncToken,
        'showDeleted' => 'true',
        'singleEvents' => 'true',
        'maxResults' => 2500
    ];

    $changedEvents = [];
    $pageToken = null;
    $nextSyncToken = null;

    do {
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        try {
            $res = googleCalendarApiRequest(
                'GET',
                googleCalendarBuildEventsListUrl($calendarId, $params)
            );
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'HTTP 410') !== false) {
                $newSyncToken = googleCalendarInitialSyncToken($calendarId);

                dbExec("
                    UPDATE google_calendar_config
                    SET sync_token = '" . dbEscape($newSyncToken) . "',
                        last_full_sync = NOW(),
                        updated_at = NOW()
                    WHERE id = " . intval($config['id'])
                );

                return [
                    'ok' => true,
                    'message' => 'Sync token scaduto: full sync rigenerato',
                    'events' => 0
                ];
            }

            throw $e;
        }

        foreach (($res['items'] ?? []) as $event) {
            $changedEvents[] = $event;
        }

        $pageToken = $res['nextPageToken'] ?? null;

        if (isset($res['nextSyncToken'])) {
            $nextSyncToken = $res['nextSyncToken'];
        }

    } while ($pageToken);

    if ($nextSyncToken) {
        dbExec("
            UPDATE google_calendar_config
            SET sync_token = '" . dbEscape($nextSyncToken) . "',
                updated_at = NOW()
            WHERE id = " . intval($config['id'])
        );
    }

    foreach ($changedEvents as $event) {
        googleCalendarHandleChangedEvent($config, $event);
    }

    return [
        'ok' => true,
        'events' => count($changedEvents)
    ];
}

function googleCalendarHandleChangedEvent($config, $event)
{
    debugGoogleCalendar(
        'Evento Google ricevuto: ' .
        json_encode([
            'config_id' => intval($config['id']),
            'calendar' => ($config['calendar_id'] ?? ''),
            'event_id' => ($event['id'] ?? ''),
            'status' => ($event['status'] ?? ''),
            'summary' => ($event['summary'] ?? '')
        ], JSON_UNESCAPED_UNICODE)
    );

    $result = mbappCalendarSyncFromGoogleEvent($config, $event);

    infoGoogleCalendarMBApp(
        'Risultato sync MBApp: ' .
        json_encode($result, JSON_UNESCAPED_UNICODE)
    );
}
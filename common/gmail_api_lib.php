<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/google-client-library/src/Google_Client.php';

define('GMAIL_REDIRECT_URI', 'https://www.buonarroti.tn.it/GestOre/api/google_gmail_callback.php');

define('GMAIL_PROJECT_ID', 'gestorembgest');
define('GMAIL_TOPIC_NAME', 'projects/gestorembgest/topics/gmail.notify');

define('GMAIL_LOG_DIR', __DIR__ . '/../log');

define('GMAIL_TOKEN_FILE', GMAIL_LOG_DIR . '/gmail_token.json');
define('GMAIL_STATE_FILE', GMAIL_LOG_DIR . '/gmail_state.json');

function gmailCreateClient()
{
    global $__settings;

    $client = new Google_Client();
    $client->setApplicationName($__settings->GoogleAuth->applicationName);
    $client->setClientId($__settings->GoogleAuth->clientId);
    $client->setClientSecret($__settings->GoogleAuth->clientSecret);
    $client->setRedirectUri(GMAIL_REDIRECT_URI);
    $client->setAccessType('offline');
    $client->setApprovalPrompt('force');

    $client->setScopes([
        'https://www.googleapis.com/auth/gmail.modify'
    ]);

    if (file_exists(GMAIL_TOKEN_FILE)) {
        $token = file_get_contents(GMAIL_TOKEN_FILE);
        if ($token) {
            $client->setAccessToken($token);
        }
    }

    if ($client->isAccessTokenExpired()) {
        $tokenArr = json_decode(file_get_contents(GMAIL_TOKEN_FILE), true);

        if (!empty($tokenArr['refresh_token'])) {
            $client->refreshToken($tokenArr['refresh_token']);
            $newToken = $client->getAccessToken();

            if ($newToken) {
                $newArr = json_decode($newToken, true);

                if (empty($newArr['refresh_token'])) {
                    $newArr['refresh_token'] = $tokenArr['refresh_token'];
                }

                file_put_contents(GMAIL_TOKEN_FILE, json_encode($newArr, JSON_PRETTY_PRINT));
                $client->setAccessToken(json_encode($newArr));
            }
        }
    }

    return $client;
}

function gmailGetAccessToken()
{
    $client = gmailCreateClient();
    $token = json_decode($client->getAccessToken(), true);

    if (empty($token['access_token'])) {
        throw new Exception('Access token non disponibile');
    }

    return $token['access_token'];
}

function gmailApiRequest($method, $url, $body = null)
{
    $accessToken = gmailGetAccessToken();

    $ch = curl_init();

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20
    ]);

    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception('CURL error: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($httpCode >= 400) {
        throw new Exception('Gmail API error: ' . $response);
    }

    return $decoded;
}

function gmailSaveState(array $state)
{
    file_put_contents(GMAIL_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function gmailLoadState()
{
    if (!file_exists(GMAIL_STATE_FILE)) {
        return [];
    }

    return json_decode(file_get_contents(GMAIL_STATE_FILE), true) ?: [];
}

function gmailStartWatch()
{
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/watch';

    $body = [
        'topicName' => GMAIL_TOPIC_NAME,
        'labelIds' => ['INBOX']
    ];

    $res = gmailApiRequest('POST', $url, $body);

    gmailSaveState([
        'historyId' => $res['historyId'] ?? null,
        'expiration' => $res['expiration'] ?? null,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    return $res;
}

function gmailListHistory($startHistoryId)
{
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/history?startHistoryId=' . urlencode($startHistoryId);

    $res = gmailApiRequest('GET', $url);

    $messageIds = [];

    if (!empty($res['history'])) {
        foreach ($res['history'] as $h) {

            // 🔹 nuovi messaggi
            if (!empty($h['messagesAdded'])) {
                foreach ($h['messagesAdded'] as $msg) {
                    if (!empty($msg['message']['id'])) {
                        $messageIds[] = $msg['message']['id'];
                    }
                }
            }

            // 🔥 AGGIUNGI QUESTO
            if (!empty($h['messages'])) {
                foreach ($h['messages'] as $msg) {
                    if (!empty($msg['id'])) {
                        $messageIds[] = $msg['id'];
                    }
                }
            }
        }
    }

    return array_values(array_unique($messageIds));
}

function gmailGetMessage($messageId)
{
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $messageId . '?format=full';

    return gmailApiRequest('GET', $url);
}

function gmailGetHeader($message, $name)
{
    if (empty($message['payload']['headers'])) {
        return '';
    }

    foreach ($message['payload']['headers'] as $h) {
        if (strcasecmp($h['name'], $name) === 0) {
            return $h['value'];
        }
    }

    return '';
}

function gmailBase64UrlDecode($data)
{
    $data = strtr($data, '-_', '+/');
    return base64_decode($data);
}

function gmailExtractPlainText($payload)
{
    if (isset($payload['body']['data'])) {
        return gmailBase64UrlDecode($payload['body']['data']);
    }

    if (!empty($payload['parts'])) {
        foreach ($payload['parts'] as $part) {
            if ($part['mimeType'] === 'text/plain') {
                return gmailBase64UrlDecode($part['body']['data']);
            }
        }
    }

    return '';
}
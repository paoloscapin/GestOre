<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';
require_once __DIR__ . '/google-client-library/src/Google_Client.php';

setLogChannel('gmail');

define('GMAIL_REDIRECT_URI', 'https://www.buonarroti.tn.it/GestOre/api/google_gmail_callback.php');

define('GMAIL_PROJECT_ID', 'gestorembgest');
define('GMAIL_TOPIC_NAME', 'projects/gestorembgest/topics/gmail.notify');

define('GMAIL_LOG_DIR', __DIR__ . '/../log');

define('GMAIL_TOKEN_FILE', GMAIL_LOG_DIR . '/gmail_token.json');
define('GMAIL_STATE_FILE', GMAIL_LOG_DIR . '/gmail_state.json');
define('GMAIL_CRON_LOCK_FILE', GMAIL_LOG_DIR . '/gmail_refresh_watch_cron.lock');
define('GMAIL_WEBHOOK_LOCK_FILE', GMAIL_LOG_DIR . '/gmail_webhook.lock');

function gmailAcquireLock(string $lockFile, int $maxAgeSeconds = 900)
{
    if (!is_dir(dirname($lockFile))) {
        mkdir(dirname($lockFile), 0775, true);
    }

    if (is_file($lockFile)) {
        $age = time() - intval(filemtime($lockFile));
        if ($age > $maxAgeSeconds) {
            @unlink($lockFile);
            warningGmail('lock scaduto rimosso: ' . basename($lockFile) . ' age=' . $age . 's');
        }
    }

    $fp = @fopen($lockFile, 'x');
    if (!$fp) {
        return false;
    }

    fwrite($fp, json_encode([
        'pid' => getmypid(),
        'started_at' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE));
    fflush($fp);

    return $fp;
}

function gmailReleaseLock($lockHandle, string $lockFile): void
{
    if (is_resource($lockHandle)) {
        fclose($lockHandle);
    }
    @unlink($lockFile);
}

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
                infoGmail('access token Gmail aggiornato tramite refresh token');
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
        errorGmail('curl error Gmail API: ' . curl_error($ch));
        throw new Exception('CURL error: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($httpCode >= 400) {
        errorGmail('Gmail API error http=' . $httpCode . ' response=' . mb_substr((string)$response, 0, 800));
        throw new Exception('Gmail API error: ' . $response);
    }

    return $decoded;
}

function gmailSaveState(array $state)
{
    file_put_contents(GMAIL_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function gmailLoadState()
{
    if (!file_exists(GMAIL_STATE_FILE)) {
        return [];
    }

    return json_decode(file_get_contents(GMAIL_STATE_FILE), true) ?: [];
}

function gmailCompareHistoryId($a, $b): int
{
    $a = trim((string)$a);
    $b = trim((string)$b);
    if ($a === $b) {
        return 0;
    }
    if ($a === '') {
        return -1;
    }
    if ($b === '') {
        return 1;
    }
    if (function_exists('bccomp') && ctype_digit($a) && ctype_digit($b)) {
        return bccomp($a, $b);
    }
    if (strlen($a) !== strlen($b) && ctype_digit($a) && ctype_digit($b)) {
        return strlen($a) < strlen($b) ? -1 : 1;
    }
    return strcmp($a, $b);
}

function gmailMaxHistoryId($a, $b): string
{
    return gmailCompareHistoryId($a, $b) >= 0 ? (string)$a : (string)$b;
}

function gmailExtractLabelNameFromImapMailbox(string $mailbox): string
{
    $mailbox = trim(preg_replace('/^\{[^}]+\}/', '', $mailbox));
    if ($mailbox === '' || strcasecmp($mailbox, 'INBOX') === 0) {
        return '';
    }
    if (stripos($mailbox, 'All Mail') !== false || stripos($mailbox, 'Tutti i messaggi') !== false) {
        return '';
    }
    if (stripos($mailbox, '[Gmail]/') === 0 || stripos($mailbox, '[Google Mail]/') === 0) {
        $mailbox = preg_replace('#^\[(Gmail|Google Mail)\]/#i', '', $mailbox);
    }
    return trim($mailbox);
}

function gmailResolveLabelIdByName(string $labelName): string
{
    $labelName = trim($labelName);
    if ($labelName === '') {
        return '';
    }

    $res = gmailApiRequest('GET', 'https://gmail.googleapis.com/gmail/v1/users/me/labels');
    foreach (($res['labels'] ?? []) as $label) {
        $name = trim((string)($label['name'] ?? ''));
        $id = trim((string)($label['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        if (strcasecmp($name, $labelName) === 0) {
            return $id;
        }
        $lastNamePart = trim((string)preg_replace('#^.*[/\\\\]#', '', $name));
        if (strcasecmp($lastNamePart, $labelName) === 0) {
            return $id;
        }
    }

    return '';
}

function gmailMonitoredLabelIds(): array
{
    global $__settings;

    $labelIds = ['INBOX'];
    $ticketMailbox = trim((string)($__settings->ticketMail->imap_mailbox ?? ''));
    $ticketLabelName = gmailExtractLabelNameFromImapMailbox($ticketMailbox);

    if ($ticketLabelName !== '') {
        $ticketLabelId = gmailResolveLabelIdByName($ticketLabelName);
        if ($ticketLabelId !== '') {
            $labelIds[] = $ticketLabelId;
        } else {
            warningGmail('label Gmail non trovata per mailbox ticket: ' . $ticketLabelName);
        }
    }

    return array_values(array_unique($labelIds));
}

function gmailStartWatch()
{
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/watch';
    $labelIds = gmailMonitoredLabelIds();

    $body = [
        'topicName' => GMAIL_TOPIC_NAME,
        'labelIds' => $labelIds,
        'labelFilterAction' => 'include'
    ];

    $res = gmailApiRequest('POST', $url, $body);
    infoGmail('watch Gmail avviato/aggiornato historyId=' . trim((string)($res['historyId'] ?? '')) . ' expiration=' . trim((string)($res['expiration'] ?? '')) . ' labels=' . implode(',', $labelIds));

    gmailSaveState([
        'historyId' => $res['historyId'] ?? null,
        'expiration' => $res['expiration'] ?? null,
        'monitoredLabelIds' => $labelIds,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    return $res;
}

function gmailListHistory($startHistoryId)
{
    $state = gmailLoadState();
    $labelIds = $state['monitoredLabelIds'] ?? ['INBOX'];
    if (!is_array($labelIds) || empty($labelIds)) {
        $labelIds = ['INBOX'];
    }
    $messageIds = [];

    foreach ($labelIds as $labelId) {
        $labelId = trim((string)$labelId);
        if ($labelId === '') {
            continue;
        }
        $url = 'https://gmail.googleapis.com/gmail/v1/users/me/history?startHistoryId=' . urlencode($startHistoryId)
            . '&historyTypes=messageAdded&labelId=' . urlencode($labelId);

        $res = gmailApiRequest('GET', $url);

        if (!empty($res['history'])) {
            foreach ($res['history'] as $h) {
                if (!empty($h['messagesAdded'])) {
                    foreach ($h['messagesAdded'] as $msg) {
                        $message = $msg['message'] ?? [];
                        $labels = $message['labelIds'] ?? [];
                        if (!empty($message['id']) && (empty($labels) || in_array($labelId, $labels, true))) {
                            $messageIds[] = $message['id'];
                        }
                    }
                }
            }
        }
    }

    $messageIds = array_values(array_unique($messageIds));
    debugGmail('history Gmail letta startHistoryId=' . $startHistoryId . ' message_added=' . count($messageIds) . ' labels=' . implode(',', $labelIds));
    return $messageIds;
}

function gmailGetMessage($messageId)
{
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $messageId . '?format=full';

    return gmailApiRequest('GET', $url);
}

function gmailGetMessageMetadata($messageId)
{
    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $messageId
        . '?format=metadata&metadataHeaders=Message-ID';

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

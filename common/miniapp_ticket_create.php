<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';
require_once __DIR__ . '/telegram_webhook_utils.php';
require_once __DIR__ . '/telegram_webhook_api.php';
require_once __DIR__ . '/telegram_webhook_relay.php';

header('Content-Type: application/json; charset=utf-8');

$TELEGRAM_BOT_TOKEN = trim((string)($__settings->telegram->bot_token ?? ''));
$TELEGRAM_SERVICE_CHAT_ID = trim((string)($__settings->telegram->chat_id ?? ''));

function miniappRespond(array $arr)
{
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function miniappParseInitData(string $initData): array
{
    parse_str($initData, $data);
    return is_array($data) ? $data : [];
}

function miniappValidateInitData(string $initData, string $botToken): bool
{
    $data = miniappParseInitData($initData);

    $hash = $data['hash'] ?? '';
    if ($hash === '') return false;

    unset($data['hash']);
    ksort($data);

    $checkArr = [];
    foreach ($data as $k => $v) {
        $checkArr[] = $k . '=' . $v;
    }
    $dataCheckString = implode("\n", $checkArr);

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $calcHash = hash_hmac('sha256', $dataCheckString, $secretKey);

    return hash_equals($calcHash, $hash);
}

function miniappGetTelegramUserFromInitData(string $initData): ?array
{
    $data = miniappParseInitData($initData);
    $userJson = $data['user'] ?? '';
    if ($userJson === '') return null;

    $user = json_decode($userJson, true);
    return is_array($user) ? $user : null;
}

function miniappFindDocenteByTelegramUserId(string $telegramUserId): ?array
{
    $telegramUserId = tgNorm($telegramUserId);
    if ($telegramUserId === '') return null;

    $q = "
        SELECT 
            d.id,
            d.cognome,
            d.nome,
            d.email,
            t.telegram_chat_id,
            t.attivo,
            t.consenso_notifiche
        FROM docente_telegram t
        INNER JOIN docente d ON d.id = t.idDocente
        WHERE t.telegram_chat_id = " . dbQ($telegramUserId) . "
          AND t.attivo = 1
          AND t.consenso_notifiche = 1
        LIMIT 1
    ";

    return dbGetFirst($q);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

$initData = trim((string)($payload['initData'] ?? ''));
$text = trim((string)($payload['text'] ?? ''));

if ($TELEGRAM_BOT_TOKEN === '' || $TELEGRAM_SERVICE_CHAT_ID === '') {
    miniappRespond(['ok' => false, 'error' => 'Configurazione Telegram mancante']);
}

if ($initData === '') {
    miniappRespond(['ok' => false, 'error' => 'initData mancante']);
}

if ($text === '') {
    miniappRespond(['ok' => false, 'error' => 'Testo ticket mancante']);
}

if (!miniappValidateInitData($initData, $TELEGRAM_BOT_TOKEN)) {
    miniappRespond(['ok' => false, 'error' => 'initData Telegram non valido']);
}

$user = miniappGetTelegramUserFromInitData($initData);
if (!$user) {
    miniappRespond(['ok' => false, 'error' => 'Utente Telegram non trovato']);
}

$telegramUserId = tgNorm($user['id'] ?? '');
if ($telegramUserId === '') {
    miniappRespond(['ok' => false, 'error' => 'ID Telegram non valido']);
}

$doc = miniappFindDocenteByTelegramUserId($telegramUserId);
if (!$doc) {
    miniappRespond(['ok' => false, 'error' => 'Docente non collegato a Telegram']);
}

$res = tgCreateOrAppendTicketFromDocente($doc, $text, $TELEGRAM_SERVICE_CHAT_ID, $TELEGRAM_BOT_TOKEN);

if (empty($res['ok'])) {
    miniappRespond(['ok' => false, 'error' => $res['error'] ?? 'Errore creazione ticket']);
}

miniappRespond([
    'ok' => true,
    'mode' => $res['mode'] ?? '',
    'ticket_code' => $res['ticket_code'] ?? ''
]);
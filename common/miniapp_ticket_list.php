<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

header('Content-Type: application/json; charset=utf-8');

$TELEGRAM_BOT_TOKEN = trim((string)($__settings->telegram->bot_token ?? ''));

if ($TELEGRAM_BOT_TOKEN === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Bot token mancante'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ---------------------------------------------------------
 * Utility
 * ---------------------------------------------------------
 */

function tgMiniJson($arr, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tgMiniNorm($v): string
{
    if ($v === null) return '';
    return trim((string)$v);
}

function tgMiniBuildTelegramMessageLink(string $serviceChatId, int $messageId): string
{
    $serviceChatId = trim($serviceChatId);
    $messageId = (int)$messageId;

    if ($serviceChatId === '' || $messageId <= 0) {
        return '';
    }

    // Da -1003815058764 -> 3815058764
    $internalChatId = preg_replace('/^-100/', '', $serviceChatId);

    if ($internalChatId === '' || !ctype_digit($internalChatId)) {
        return '';
    }

    return 'https://t.me/c/' . $internalChatId . '/' . $messageId;
}

function tgMiniFormatDateTime(?string $dt): string
{
    $dt = tgMiniNorm($dt);
    if ($dt === '') return '';

    $ts = strtotime($dt);
    if (!$ts) return $dt;

    return date('d/m/Y H:i', $ts);
}

/**
 * Valida initData della Telegram Mini App
 */
function tgMiniValidateInitData(string $initData, string $botToken): array
{
    $initData = trim($initData);
    if ($initData === '') {
        return ['ok' => false, 'error' => 'initData mancante'];
    }

    parse_str($initData, $data);

    $hash = $data['hash'] ?? '';
    unset($data['hash']);

    if ($hash === '') {
        return ['ok' => false, 'error' => 'hash mancante'];
    }

    ksort($data);

    $checkArr = [];
    foreach ($data as $k => $v) {
        $checkArr[] = $k . '=' . $v;
    }
    $dataCheckString = implode("\n", $checkArr);

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

    if (!hash_equals($calculatedHash, $hash)) {
        return ['ok' => false, 'error' => 'initData non valida'];
    }

    return ['ok' => true, 'data' => $data];
}

/**
 * ---------------------------------------------------------
 * Lettura input
 * ---------------------------------------------------------
 */

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    tgMiniJson([
        'ok' => false,
        'error' => 'JSON non valido'
    ], 400);
}

$initData = tgMiniNorm($input['initData'] ?? '');

$check = tgMiniValidateInitData($initData, $TELEGRAM_BOT_TOKEN);
if (empty($check['ok'])) {
    warningimportsost("miniapp_ticket_list: initData non valida");
    tgMiniJson([
        'ok' => false,
        'error' => $check['error'] ?? 'initData non valida'
    ], 401);
}

$data = $check['data'] ?? [];
$userJson = $data['user'] ?? '';

if ($userJson === '') {
    tgMiniJson([
        'ok' => false,
        'error' => 'Utente Telegram non presente in initData'
    ], 400);
}

$user = json_decode($userJson, true);
if (!is_array($user)) {
    tgMiniJson([
        'ok' => false,
        'error' => 'Dati utente Telegram non validi'
    ], 400);
}

$telegramUserId = tgMiniNorm($user['id'] ?? '');
if ($telegramUserId === '') {
    tgMiniJson([
        'ok' => false,
        'error' => 'Telegram user id mancante'
    ], 400);
}

infoimportsost("miniapp_ticket_list: richiesta valida telegramUserId=[$telegramUserId]");

/**
 * ---------------------------------------------------------
 * Ricerca docente collegato
 * ---------------------------------------------------------
 */

$qDoc = "
    SELECT 
        d.id,
        d.cognome,
        d.nome,
        d.email,
        t.telegram_chat_id
    FROM docente_telegram t
    INNER JOIN docente d ON d.id = t.idDocente
    WHERE t.telegram_chat_id = " . dbQ($telegramUserId) . "
      AND t.attivo = 1
      AND t.consenso_notifiche = 1
    LIMIT 1
";

$doc = dbGetFirst($qDoc);

if (!$doc) {
    tgMiniJson([
        'ok' => false,
        'error' => 'Nessun docente collegato a questo account Telegram'
    ], 404);
}

$idDocente = (int)($doc['id'] ?? 0);

if ($idDocente <= 0) {
    tgMiniJson([
        'ok' => false,
        'error' => 'Docente non valido'
    ], 500);
}

/**
 * ---------------------------------------------------------
 * Ticket
 * ---------------------------------------------------------
 */

$qTickets = "
    SELECT
        r.id,
        r.ticket_code,
        r.stato,
        r.chiusa,
        r.ultimo_testo_docente,
        r.ultimo_testo_admin,
        r.data_creazione,
        r.data_aggiornamento,
        r.data_chiusura,
        r.preso_in_carico_nome,
        r.service_chat_id,
        r.service_message_id,
        r.service_thread_root_message_id,
        r.service_thread_id,
        r.thread_topic_name
    FROM docente_telegram_relay r
    WHERE r.idDocente = " . dbI($idDocente) . "
    ORDER BY
        CASE
            WHEN r.stato IN ('APERTA', 'IN_GESTIONE') THEN 0
            ELSE 1
        END,
        r.id DESC
";

$rows = dbGetAll($qTickets);
if (!is_array($rows)) {
    $rows = [];
}

$aperti = [];
$chiusi = [];

foreach ($rows as $r) {
    $serviceChatId = tgMiniNorm($r['service_chat_id'] ?? '');
    $rootMessageId = (int)($r['service_thread_root_message_id'] ?? 0);
    $serviceMessageId = (int)($r['service_message_id'] ?? 0);

    // Preferisco il root message del topic; fallback sul service_message_id
    $messageIdForLink = $rootMessageId > 0 ? $rootMessageId : $serviceMessageId;
    $telegramLink = tgMiniBuildTelegramMessageLink($serviceChatId, $messageIdForLink);

    $item = [
        'id' => (int)($r['id'] ?? 0),
        'ticket_code' => tgMiniNorm($r['ticket_code'] ?? ''),
        'stato' => tgMiniNorm($r['stato'] ?? ''),
        'chiusa' => (int)($r['chiusa'] ?? 0),
        'ultimo_testo_docente' => tgMiniNorm($r['ultimo_testo_docente'] ?? ''),
        'ultimo_testo_admin' => tgMiniNorm($r['ultimo_testo_admin'] ?? ''),
        'data_creazione' => tgMiniNorm($r['data_creazione'] ?? ''),
        'data_creazione_fmt' => tgMiniFormatDateTime($r['data_creazione'] ?? ''),
        'data_aggiornamento' => tgMiniNorm($r['data_aggiornamento'] ?? ''),
        'data_aggiornamento_fmt' => tgMiniFormatDateTime($r['data_aggiornamento'] ?? ''),
        'data_chiusura' => tgMiniNorm($r['data_chiusura'] ?? ''),
        'data_chiusura_fmt' => tgMiniFormatDateTime($r['data_chiusura'] ?? ''),
        'preso_in_carico_nome' => tgMiniNorm($r['preso_in_carico_nome'] ?? ''),
        'thread_topic_name' => tgMiniNorm($r['thread_topic_name'] ?? ''),
        'service_thread_id' => (int)($r['service_thread_id'] ?? 0),
        'telegram_link' => $telegramLink
    ];

    if (strtoupper($item['stato']) === 'CHIUSA') {
        $chiusi[] = $item;
    } else {
        $aperti[] = $item;
    }
}

tgMiniJson([
    'ok' => true,
    'docente' => [
        'id' => $idDocente,
        'nome' => trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? '')),
        'email' => tgMiniNorm($doc['email'] ?? '')
    ],
    'counts' => [
        'aperti' => count($aperti),
        'chiusi' => count($chiusi)
    ],
    'tickets' => [
        'aperti' => $aperti,
        'chiusi' => $chiusi
    ]
]);
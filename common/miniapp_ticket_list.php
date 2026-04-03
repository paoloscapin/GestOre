<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * ---------------------------------------------------------
 * LOG SAFE
 * ---------------------------------------------------------
 */

function tgMiniLogInfo(string $msg): void
{
    if (function_exists('infoTelegram')) {
        infoTelegram($msg);
    } elseif (function_exists('info')) {
        info($msg);
    } error_log($msg);
}

function tgMiniLogWarn(string $msg): void
{
    if (function_exists('warningTelegram')) {
        warningTelegram($msg);
    } elseif (function_exists('warning')) {
        warning($msg);
    } else {
        error_log($msg);
    }
}

function tgMiniLogError(string $msg): void
{
    if (function_exists('errorTelegram')) {
        errorTelegram($msg);
    } elseif (function_exists('error')) {
        error($msg);
    } else {
        error_log($msg);
    }
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
 * Fatal error handler
 * ---------------------------------------------------------
 */

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = "miniapp_ticket_list FATAL: type={$e['type']} file={$e['file']} line={$e['line']} message={$e['message']}";
        tgMiniLogError($msg);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Errore fatale server',
                'debug' => $msg
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
});

try {
    tgMiniLogInfo("miniapp_ticket_list: START");

    $TELEGRAM_BOT_TOKEN = trim((string)($__settings->telegram->bot_token ?? ''));
    tgMiniLogInfo("miniapp_ticket_list: bot_token_len=" . strlen($TELEGRAM_BOT_TOKEN));

    if ($TELEGRAM_BOT_TOKEN === '') {
        tgMiniLogError("miniapp_ticket_list: bot token mancante");
        tgMiniJson([
            'ok' => false,
            'error' => 'Bot token mancante'
        ], 500);
    }

    /**
     * ---------------------------------------------------------
     * Lettura input
     * ---------------------------------------------------------
     */

    $raw = file_get_contents('php://input');
    tgMiniLogInfo("miniapp_ticket_list: raw_len=" . strlen((string)$raw));
    tgMiniLogInfo("miniapp_ticket_list: raw=" . substr((string)$raw, 0, 3000));

    $input = json_decode($raw, true);
    tgMiniLogInfo("miniapp_ticket_list: json_last_error=" . json_last_error() . " msg=" . json_last_error_msg());

    if (!is_array($input)) {
        tgMiniLogWarn("miniapp_ticket_list: input non array");
        tgMiniJson([
            'ok' => false,
            'error' => 'JSON non valido'
        ], 400);
    }

    $initData = tgMiniNorm($input['initData'] ?? '');
    tgMiniLogInfo("miniapp_ticket_list: initData_len=" . strlen($initData));
    tgMiniLogInfo("miniapp_ticket_list: initData_preview=" . substr($initData, 0, 1000));

    $check = tgMiniValidateInitData($initData, $TELEGRAM_BOT_TOKEN);
    tgMiniLogInfo("miniapp_ticket_list: validateInitData=" . json_encode($check, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    if (empty($check['ok'])) {
        tgMiniLogWarn("miniapp_ticket_list: initData non valida");
        tgMiniJson([
            'ok' => false,
            'error' => $check['error'] ?? 'initData non valida'
        ], 401);
    }

    $data = $check['data'] ?? [];
    $userJson = $data['user'] ?? '';
    tgMiniLogInfo("miniapp_ticket_list: userJson=" . substr((string)$userJson, 0, 1000));

    if ($userJson === '') {
        tgMiniLogWarn("miniapp_ticket_list: user mancante in initData");
        tgMiniJson([
            'ok' => false,
            'error' => 'Utente Telegram non presente in initData'
        ], 400);
    }

    $user = json_decode($userJson, true);
    tgMiniLogInfo("miniapp_ticket_list: user json_last_error=" . json_last_error() . " msg=" . json_last_error_msg());

    if (!is_array($user)) {
        tgMiniLogWarn("miniapp_ticket_list: dati utente Telegram non validi");
        tgMiniJson([
            'ok' => false,
            'error' => 'Dati utente Telegram non validi'
        ], 400);
    }

    $telegramUserId = tgMiniNorm($user['id'] ?? '');
    tgMiniLogInfo("miniapp_ticket_list: telegramUserId=[$telegramUserId]");

    if ($telegramUserId === '') {
        tgMiniLogWarn("miniapp_ticket_list: telegram user id mancante");
        tgMiniJson([
            'ok' => false,
            'error' => 'Telegram user id mancante'
        ], 400);
    }

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

    tgMiniLogInfo("miniapp_ticket_list: qDoc=" . preg_replace('/\s+/', ' ', trim($qDoc)));

    $doc = dbGetFirst($qDoc);
    tgMiniLogInfo("miniapp_ticket_list: doc=" . json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    if (!$doc) {
        tgMiniLogWarn("miniapp_ticket_list: nessun docente collegato per telegramUserId=[$telegramUserId]");
        tgMiniJson([
            'ok' => false,
            'error' => 'Nessun docente collegato a questo account Telegram'
        ], 404);
    }

    $idDocente = (int)($doc['id'] ?? 0);
    tgMiniLogInfo("miniapp_ticket_list: idDocente=[$idDocente]");

    if ($idDocente <= 0) {
        tgMiniLogError("miniapp_ticket_list: docente non valido, doc=" . json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

    tgMiniLogInfo("miniapp_ticket_list: qTickets=" . preg_replace('/\s+/', ' ', trim($qTickets)));

    $rows = dbGetAll($qTickets);
    tgMiniLogInfo("miniapp_ticket_list: rows_type=" . gettype($rows));

    if (is_array($rows)) {
        tgMiniLogInfo("miniapp_ticket_list: rows_count=" . count($rows));
        if (!empty($rows[0])) {
            tgMiniLogInfo("miniapp_ticket_list: first_row=" . json_encode($rows[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    } else {
        tgMiniLogWarn("miniapp_ticket_list: dbGetAll non ha restituito array");
        $rows = [];
    }

    $aperti = [];
    $chiusi = [];

    foreach ($rows as $idx => $r) {
        tgMiniLogInfo("miniapp_ticket_list: processing_row_idx=$idx id=" . (int)($r['id'] ?? 0));

        $serviceChatId = tgMiniNorm($r['service_chat_id'] ?? '');
        $rootMessageId = (int)($r['service_thread_root_message_id'] ?? 0);
        $serviceMessageId = (int)($r['service_message_id'] ?? 0);

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

    tgMiniLogInfo("miniapp_ticket_list: aperti=" . count($aperti) . " chiusi=" . count($chiusi));

    $response = [
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
    ];

    tgMiniLogInfo("miniapp_ticket_list: response_ready");
    tgMiniJson($response);

} catch (Throwable $e) {
    $msg = "miniapp_ticket_list EXCEPTION: " . $e->getMessage() .
        " file=" . $e->getFile() .
        " line=" . $e->getLine();
    tgMiniLogError($msg);
    tgMiniLogError($e->getTraceAsString());

    tgMiniJson([
        'ok' => false,
        'error' => 'Eccezione server',
        'debug' => $msg
    ], 500);
}
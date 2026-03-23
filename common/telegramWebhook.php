<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

header('Content-Type: application/json; charset=utf-8');

$TELEGRAM_BOT_TOKEN = trim((string)($__settings->telegram->bot_token ?? ''));

function tgRespond($payload, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function tgNorm($v)
{
    return trim((string)$v);
}

function tgSendMessage($botToken, $chatId, $text)
{
    $botToken = trim((string)$botToken);
    $chatId   = trim((string)$chatId);

    if ($botToken === '' || $chatId === '') {
        return ['ok' => false, 'error' => 'botToken o chatId mancanti'];
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $payload = [
        'chat_id' => $chatId,
        'text'    => $text
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'error' => $error];
    }

    $json = json_decode($response, true);
    if (is_array($json) && !empty($json['ok'])) {
        return ['ok' => true, 'response' => $response];
    }

    return ['ok' => false, 'error' => $response ?: 'Risposta Telegram vuota'];
}

function tgHandleStartToken($token, $chatId)
{
    global $TELEGRAM_BOT_TOKEN;

    $token = tgNorm($token);
    $chatId = tgNorm($chatId);

    if ($token === '') {
        return "👋 Benvenuto in GestOre.\n\nPer collegare il tuo account Telegram devi usare il link personale ricevuto via mail.";
    }

    infoimportsost("telegramWebhook: start token ricevuto token=[$token] chatId=[$chatId]");

    $qTok = "
        SELECT *
        FROM docente_telegram_token
        WHERE token = " . dbQ($token) . "
        LIMIT 1
    ";
    $tok = dbGetFirst($qTok);

    if (!$tok) {
        warningimportsost("telegramWebhook: token non trovato [$token]");
        return "❌ Link non valido.\n\nIl token di collegamento non è stato trovato.";
    }

    if ((int)($tok['usato'] ?? 0) === 1) {
        warningimportsost("telegramWebhook: token già usato idToken=" . (int)$tok['idToken']);
        return "⚠️ Questo link è già stato utilizzato.\n\nSe devi collegare di nuovo Telegram, richiedi una nuova mail da GestOre.";
    }

    $dataScadenza = tgNorm($tok['dataScadenza'] ?? '');
    if ($dataScadenza !== '' && strtotime($dataScadenza) < time()) {
        warningimportsost("telegramWebhook: token scaduto idToken=" . (int)$tok['idToken']);
        return "⏰ Questo link è scaduto.\n\nRichiedi una nuova mail di collegamento da GestOre.";
    }

    $idDocente = (int)($tok['idDocente'] ?? 0);
    if ($idDocente <= 0) {
        errorimportsost("telegramWebhook: idDocente non valido nel token idToken=" . (int)$tok['idToken']);
        return "❌ Errore di collegamento.\n\nContatta la segreteria o l'amministratore di GestOre.";
    }

    $qDoc = "
        SELECT id, cognome, nome, email
        FROM docente
        WHERE id = " . dbI($idDocente) . "
        LIMIT 1
    ";
    $doc = dbGetFirst($qDoc);

    if (!$doc) {
        errorimportsost("telegramWebhook: docente non trovato idDocente=$idDocente");
        return "❌ Docente non trovato.\n\nContatta la segreteria o l'amministratore di GestOre.";
    }

    $docenteNome = trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? ''));

    dbExec("START TRANSACTION");

    try {
        $qTg = "
            SELECT *
            FROM docente_telegram
            WHERE idDocente = " . dbI($idDocente) . "
            LIMIT 1
        ";
        $tg = dbGetFirst($qTg);

        if ($tg) {
            $qUpd = "
                UPDATE docente_telegram
                SET telegram_chat_id = " . dbQ($chatId) . ",
                    attivo = 1,
                    consenso_notifiche = 1,
                    ultimo_errore = NULL,
                    ultimo_errore_data = NULL
                WHERE idDocente = " . dbI($idDocente);
            dbExec($qUpd);

            infoimportsost("telegramWebhook: aggiornato docente_telegram idDocente=$idDocente chatId=[$chatId]");
        } else {
            $qIns = "
                INSERT INTO docente_telegram (
                    idDocente,
                    telegram_chat_id,
                    attivo,
                    consenso_notifiche
                ) VALUES (
                    " . dbI($idDocente) . ",
                    " . dbQ($chatId) . ",
                    1,
                    1
                )
            ";
            dbExec($qIns);

            infoimportsost("telegramWebhook: inserito docente_telegram idDocente=$idDocente chatId=[$chatId]");
        }

        $qTokUpd = "
            UPDATE docente_telegram_token
            SET usato = 1,
                dataUso = NOW()
            WHERE idToken = " . dbI((int)$tok['idToken']);
        dbExec($qTokUpd);

        infoimportsost("telegramWebhook: token marcato come usato idToken=" . (int)$tok['idToken']);

        dbExec("COMMIT");

        return "✅ Collegamento completato con successo.\n\nDocente associato: {$docenteNome}\nDa ora puoi ricevere le notifiche Telegram di GestOre per le sostituzioni.";
    } catch (Throwable $e) {
        dbExec("ROLLBACK");
        errorimportsost("telegramWebhook: eccezione " . $e->getMessage());
        return "❌ Errore durante il collegamento Telegram.\n\nContatta la segreteria o l'amministratore di GestOre.";
    }
}

if ($TELEGRAM_BOT_TOKEN === '') {
    errorimportsost("telegramWebhook: bot token mancante");
    tgRespond(['ok' => false, 'error' => 'Bot token mancante'], 500);
}

$raw = file_get_contents('php://input');
if (!$raw) {
    warningimportsost("telegramWebhook: body vuoto");
    tgRespond(['ok' => true, 'ignored' => 'body vuoto']);
}

$update = json_decode($raw, true);
if (!is_array($update)) {
    warningimportsost("telegramWebhook: JSON non valido");
    tgRespond(['ok' => true, 'ignored' => 'json non valido']);
}

$message = $update['message'] ?? null;
if (!$message || !is_array($message)) {
    tgRespond(['ok' => true, 'ignored' => 'nessun message']);
}

$chat = $message['chat'] ?? [];
$chatId = tgNorm($chat['id'] ?? '');
$chatType = tgNorm($chat['type'] ?? '');
$text = tgNorm($message['text'] ?? '');

if ($chatId === '') {
    tgRespond(['ok' => true, 'ignored' => 'chatId mancante']);
}

if ($chatType !== 'private') {
    tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, "⚠️ Usa questo comando in una chat privata con il bot.");
    tgRespond(['ok' => true, 'ignored' => 'chat non privata']);
}

if ($text === '') {
    tgRespond(['ok' => true, 'ignored' => 'testo vuoto']);
}

if (preg_match('/^\/start(?:\s+(.+))?$/u', $text, $m)) {
    $token = tgNorm($m[1] ?? '');
    $reply = tgHandleStartToken($token, $chatId);
    $sendRes = tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, $reply);

    if (!$sendRes['ok']) {
        errorimportsost("telegramWebhook: errore invio risposta Telegram chatId=[$chatId] err=[" . ($sendRes['error'] ?? '') . "]");
    }

    tgRespond(['ok' => true, 'handled' => 'start']);
}

tgSendMessage(
    $TELEGRAM_BOT_TOKEN,
    $chatId,
    "👋 Ciao. Per collegare il tuo account a GestOre usa il link personale ricevuto via mail."
);

tgRespond(['ok' => true, 'handled' => 'default']);
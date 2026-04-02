<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

header('Content-Type: text/plain; charset=utf-8');

$TELEGRAM_BOT_TOKEN = trim((string)($__settings->telegram->bot_token ?? ''));
$DELETE_OLD_TOPICS = (bool)($__settings->telegram->delete_old_topics ?? false);
$DELETE_OLD_TOPICS_AFTER_DAYS = (int)($__settings->telegram->delete_old_topics_after_days ?? 30);

$days = max(1, $DELETE_OLD_TOPICS_AFTER_DAYS);

function tgDeleteTopic($botToken, $chatId, $threadId)
{
    $url = "https://api.telegram.org/bot{$botToken}/deleteForumTopic";

    $payload = [
        'chat_id' => $chatId,
        'message_thread_id' => (int)$threadId
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
        infoimportsost("cleanupTicket: tgDeleteTopic curl error=$error");
        return ['ok' => false, 'error' => $error];
    }

    $json = json_decode($response, true);
    infoimportsost("cleanupTicket: tgDeleteTopic response=" . json_encode($json));

    return is_array($json) ? $json : ['ok' => false, 'error' => $response];
}

infoimportsost(
    "cleanupTicket: avvio delete_old_topics=" . ($DELETE_OLD_TOPICS ? '1' : '0') .
    " delete_old_topics_after_days=$days"
);

$q = "
    SELECT id, ticket_code, service_chat_id, service_thread_id, data_chiusura
    FROM docente_telegram_relay
    WHERE stato = 'CHIUSA'
      AND data_chiusura IS NOT NULL
      AND data_chiusura < DATE_SUB(NOW(), INTERVAL $days DAY)
    ORDER BY data_chiusura ASC
";

$rows = dbGetAll($q);

if (!is_array($rows) || empty($rows)) {
    infoimportsost("cleanupTicket: nessun ticket da eliminare");
    echo "Nessun ticket da eliminare\n";
    exit;
}

$deleted = 0;
$skipped = 0;
$errors = 0;

foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    $ticketCode = trim((string)($r['ticket_code'] ?? ''));
    $chatId = trim((string)($r['service_chat_id'] ?? ''));
    $threadId = (int)($r['service_thread_id'] ?? 0);

    infoimportsost("cleanupTicket: processing id=$id ticketCode=[$ticketCode] chatId=[$chatId] threadId=$threadId");

    if ($id <= 0) {
        $skipped++;
        continue;
    }

    if ($DELETE_OLD_TOPICS) {
        if ($TELEGRAM_BOT_TOKEN === '') {
            infoimportsost("cleanupTicket: bot token mancante, skip id=$id");
            $errors++;
            continue;
        }

        if ($chatId !== '' && $threadId > 0) {
            $delRes = tgDeleteTopic($TELEGRAM_BOT_TOKEN, $chatId, $threadId);

            if (empty($delRes['ok'])) {
                infoimportsost("cleanupTicket: deleteForumTopic fallita id=$id ticketCode=[$ticketCode] result=" . json_encode($delRes));
                $errors++;
                continue;
            }
        } else {
            infoimportsost("cleanupTicket: dati topic mancanti id=$id ticketCode=[$ticketCode], cancello solo DB");
        }
    }

    dbExec("DELETE FROM docente_telegram_relay WHERE id = " . dbI($id));
    infoimportsost("cleanupTicket: eliminato id=$id ticketCode=[$ticketCode]");
    $deleted++;
}

infoimportsost("cleanupTicket: fine deleted=$deleted skipped=$skipped errors=$errors");

echo "Eliminati: $deleted\n";
echo "Saltati: $skipped\n";
echo "Errori: $errors\n";
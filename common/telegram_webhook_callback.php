<?php 

/**
 * ===========================
 * CALLBACK HANDLER
 * ===========================
 */
function tgHandleCallback(array $update, string $TELEGRAM_BOT_TOKEN)
{
    $callback = $update['callback_query'] ?? null;
    infoimportsost("tgHandleCallback: raw callback=" . json_encode($callback, JSON_UNESCAPED_UNICODE));

    if (!$callback) return;

    $callbackId = $callback['id'] ?? '';
    $data = tgNorm($callback['data'] ?? '');
    $from = $callback['from'] ?? [];
    $adminName = tgUserDisplayName($from);
    $adminUserId = tgNorm($from['id'] ?? '');
    $callbackMessage = $callback['message'] ?? [];
    $chatId = tgNorm($callbackMessage['chat']['id'] ?? '');
    $messageId = (int)($callbackMessage['message_id'] ?? 0);
    $threadId = (int)($callbackMessage['message_thread_id'] ?? 0);

    infoimportsost("tgHandleCallback: ENTER data=[$data] chatId=[$chatId] messageId=[$messageId] threadId=$threadId admin=$adminName idUser=$adminUserId callbackId=$callbackId");

    if (preg_match('/^(presa|chiudi|riapri)_relay_(\d+)$/', $data, $m)) {
        $action = $m[1];
        $idRelay = (int)$m[2];

        infoimportsost("tgHandleCallback: azione=$action idRelay=$idRelay");

        // Prima prova a recuperare da ID
        $relay = tgFindRelayById($idRelay);
        infoimportsost("tgHandleCallback: relay da ID: " . json_encode($relay));

        // Fallback su service_message_id
        if (!$relay && $messageId > 0) {
            $relay = tgFindRelayByServiceMessage($chatId, $messageId);
            infoimportsost("tgHandleCallback: relay da service_message_id: " . json_encode($relay));
        }

        // Fallback su thread_id
        if (!$relay && $threadId > 0) {
            $q = "SELECT * FROM docente_telegram_relay WHERE service_thread_id = " . dbI($threadId) . " ORDER BY id DESC LIMIT 1";
            $relay = dbGetFirst($q);
            infoimportsost("tgHandleCallback: relay da thread_id: " . json_encode($relay));
        }

        if (!$relay) {
            infoimportsost("tgHandleCallback: RELAY NON TROVATO, rispondo callbackQuery");
            tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callbackId, 'Ticket non trovato');
            tgRespond(['ok'=>true,'ignored'=>'relay id non trovato']);
        }

        // Aggiorna ticket code se vuoto
        $ticketCode = tgNorm($relay['ticket_code'] ?? '');
        if ($ticketCode === '') {
            $ticketCode = tgUpdateTicketCode($idRelay);
            infoimportsost("tgHandleCallback: ticket_code aggiornato a [$ticketCode]");
        }

        // Aggiorna stato
        $newStatus = strtoupper($action==='chiudi'?'CHIUSA':($action==='presa'?'IN_GESTIONE':'APERTA'));
        tgUpdateRelayStatus($idRelay, $newStatus, $adminUserId, $adminName);
        infoimportsost("tgHandleCallback: stato aggiornato a $newStatus");

        // Log relay post update
        $relay = tgFindRelayById($idRelay);
        infoimportsost("tgHandleCallback: relay post-update=" . json_encode($relay));

        // Notifiche Telegram
        $res1 = tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, "Operazione $action eseguita su {$ticketCode}", ['reply_to_message_id'=>$messageId]);
        $res2 = tgSendMessage($TELEGRAM_BOT_TOKEN, $relay['docente_chat_id'], "La tua richiesta {$ticketCode} è stata {$action} da {$adminName}");
        infoimportsost("tgHandleCallback: sendMessage gruppo=" . json_encode($res1) . " docente=" . json_encode($res2));

        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callbackId, 'Operazione eseguita');
        tgRespond(['ok'=>true,'handled'=>'callback']);
    }
}

function tgSendGeneralDashboard($botToken, $chatId)
{
    return tgSendMessage(
        $botToken,
        $chatId,
        "📌 Dashboard GestOre\n\nSeleziona un'azione:",
        [
            'reply_markup' => json_encode(tgGetDashboardKeyboard(), JSON_UNESCAPED_UNICODE)
        ]
    );
}
?>
<?php

/**
 * ===========================
 * CALLBACK HANDLER
 * ===========================
 */
function tgHandleCallback(array $update, string $TELEGRAM_BOT_TOKEN)
{
    $callback = $update['callback_query'] ?? null;
    infoTelegram("tgHandleCallback: raw callback=" . json_encode($callback, JSON_UNESCAPED_UNICODE));

    if (!$callback) {
        return;
    }

    $callbackId = $callback['id'] ?? '';
    $data = tgNorm($callback['data'] ?? '');
    $from = $callback['from'] ?? [];
    $adminName = tgUserDisplayName($from);
    $adminUserId = tgNorm($from['id'] ?? '');
    $callbackMessage = $callback['message'] ?? [];
    $chatId = tgNorm($callbackMessage['chat']['id'] ?? '');
    $messageId = (int)($callbackMessage['message_id'] ?? 0);
    $threadId = (int)($callbackMessage['message_thread_id'] ?? 0);

    infoTelegram("tgHandleCallback: ENTER data=[$data] chatId=[$chatId] messageId=[$messageId] threadId=$threadId admin=$adminName idUser=$adminUserId callbackId=$callbackId");

    if (preg_match('/^(presa|chiudi|riapri)_relay_(\d+)$/', $data, $m)) {
        $action = $m[1];
        $idRelay = (int)$m[2];

        infoTelegram("tgHandleCallback: azione=$action idRelay=$idRelay");

        $relay = tgFindRelayById($idRelay);
        infoTelegram("tgHandleCallback: relay da ID: " . json_encode($relay));

        if (!$relay && $messageId > 0) {
            $relay = tgFindRelayByServiceMessage($chatId, $messageId);
            infoTelegram("tgHandleCallback: relay da service_message_id: " . json_encode($relay));
        }

        if (!$relay && $threadId > 0) {
            $q = "SELECT * FROM docente_telegram_relay WHERE service_thread_id = " . dbI($threadId) . " ORDER BY id DESC LIMIT 1";
            $relay = dbGetFirst($q);
            infoTelegram("tgHandleCallback: relay da thread_id: " . json_encode($relay));
        }

        if (!$relay) {
            infoTelegram("tgHandleCallback: RELAY NON TROVATO, rispondo callbackQuery");
            tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callbackId, 'Ticket non trovato');
            tgRespond(['ok' => true, 'ignored' => 'relay id non trovato']);
        }

        $ticketCode = tgNorm($relay['ticket_code'] ?? '');
        if ($ticketCode === '') {
            $ticketCode = tgUpdateTicketCode($idRelay);
            infoTelegram("tgHandleCallback: ticket_code aggiornato a [$ticketCode]");
        }

        $newStatus = strtoupper($action === 'chiudi' ? 'CHIUSA' : ($action === 'presa' ? 'IN_GESTIONE' : 'APERTA'));
        tgUpdateRelayStatus($idRelay, $newStatus, $adminUserId, $adminName);
        infoTelegram("tgHandleCallback: stato aggiornato a $newStatus");

        $relay = tgFindRelayById($idRelay);
        infoTelegram("tgHandleCallback: relay post-update=" . json_encode($relay));

        $resGroup = tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, "Operazione $action eseguita su {$ticketCode}", ['reply_to_message_id' => $messageId]);

        $resTelegram = ['ok' => false, 'skipped' => true];
        if (trim((string)($relay['docente_chat_id'] ?? '')) !== '') {
            $resTelegram = tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $relay['docente_chat_id'],
                "La tua richiesta {$ticketCode} è stata {$action} da {$adminName}"
            );
        }

        $resMail = ['ok' => false, 'skipped' => true];
        $relayMail = function_exists('ticketMailResolveRelayRecipientEmail')
            ? ticketMailResolveRelayRecipientEmail($relay)
            : trim((string)($relay['email_riferimento'] ?? ''));
        if (function_exists('ticketMailRelayIsMailOrigin') && ticketMailRelayIsMailOrigin($relay) && $relayMail !== '') {
            $subject = match ($newStatus) {
                'IN_GESTIONE' => "Presa in carico ticket {$ticketCode}",
                'CHIUSA' => "Chiusura ticket {$ticketCode}",
                default => "Riapertura ticket {$ticketCode}",
            };
            $body = match ($newStatus) {
                'IN_GESTIONE' => "La tua richiesta {$ticketCode} è stata presa in carico da {$adminName}.",
                'CHIUSA' => "La tua richiesta {$ticketCode} è stata chiusa da {$adminName}.",
                default => "La tua richiesta {$ticketCode} è stata riaperta da {$adminName}.",
            };
            $resMail = ticketMailSendRelayNotification($relay, $subject, $body);
        }

        infoTelegram(
            "tgHandleCallback: send gruppo=" . json_encode($resGroup) .
            " utenteTelegram=" . json_encode($resTelegram) .
            " utenteMail=" . json_encode($resMail)
        );

        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callbackId, 'Operazione eseguita');
        tgRespond(['ok' => true, 'handled' => 'callback']);
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

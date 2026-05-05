<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

header('Content-Type: application/json; charset=utf-8');

$TELEGRAM_BOT_TOKEN = trim((string)($__settings->telegram->bot_token ?? ''));
$TELEGRAM_SERVICE_CHAT_ID = trim((string)($__settings->telegram->chat_id ?? ''));

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
file_put_contents(__DIR__ . '../log/telegram_chat.log', print_r($update, true), FILE_APPEND);

/**
 * ===========================
 * FUNZIONI UTILI
 * ===========================
 */
require_once __DIR__ . '/telegram_webhook_utils.php';

/**
 * =============================
 * TELEGRAM API
 * =============================
 */
require_once __DIR__ . '/telegram_webhook_api.php';

/**
 * =============================
 * RELAY / TICKET
 * =============================
 */
require_once __DIR__ . '/telegram_webhook_relay.php';
require_once __DIR__ . '/ticket_mail_lib.php';

/**
 * ===========================
 * GESTIONE TICKET
 * ===========================
 */

/**
 * ===========================
 * CALLBACK HANDLER
 * ===========================
 */
require_once __DIR__ . '/telegram_webhook_callback.php';

/**
 * Costruisce la keyboard minima per un ticket specifico
 * @param array $relay Record del ticket (docente_telegram_relay)
 * @return array
 */

function tgGetTicketKeyboard()
{
    // Restituisce la struttura della tastiera inline Telegram
    return [
        // Chiave principale richiesta da Telegram per una inline keyboard
        'inline_keyboard' => [
            // Prima riga di pulsanti: presa in carico e chiusura ticket
            [['text' => '🟡 Prendi in carico', 'callback_data' => 'presa'], ['text' => '✅ Chiudi', 'callback_data' => 'chiudi']],

            // Seconda riga di pulsanti: riapertura ticket e visualizzazione stato
            [['text' => '🔵 Riapri', 'callback_data' => 'riapri'], ['text' => '📌 Stato', 'callback_data' => 'stato']],

            // Terza riga: elenco ticket aperti
            [['text' => '📋 Ticket aperti', 'callback_data' => 'lista_aperte']],

            // Quarta riga: elenco ticket in lavorazione
            [['text' => '🟡 Ticket in lavorazione', 'callback_data' => 'lista_lavorazione']],

            // Quinta riga: elenco ticket chiusi oggi
            [['text' => '✅ Ticket chiusi oggi', 'callback_data' => 'lista_chiusi_oggi']],

            // Sesta riga: elenco dei ticket presi in carico dall'admin corrente
            [['text' => '👤 I miei ticket', 'callback_data' => 'lista_miei_ticket']]
        ]
    ];
}

function tgRespond($arr)
{
    // Imposta l'header HTTP come risposta JSON
    header('Content-Type: application/json');

    // Stampa il contenuto dell'array convertito in JSON
    echo json_encode($arr);

    // Interrompe subito l'esecuzione dello script
    exit;
}

function tgFindDocenteByChatId($chatId)
{
    // Normalizza il chatId ricevuto
    $chatId = tgNorm($chatId);

    // Scrive nel log che è stata chiamata la ricerca docente per chatId
    infoTelegram("telegramWebhook: tgFindDocenteByChatId chiamata chatId=[$chatId]");

    // Se il chatId è vuoto
    if ($chatId === '') {
        // Scrive nel log un warning
        warningTelegram("telegramWebhook: tgFindDocenteByChatId - chatId vuoto");

        // Restituisce null perché non è possibile cercare il docente
        return null;
    }

    // Costruisce la query per cercare il docente associato alla chat Telegram
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
        WHERE t.telegram_chat_id = " . dbQ($chatId) . "
          AND t.attivo = 1
          AND t.consenso_notifiche = 1
        LIMIT 1
    ";

    // Scrive nel log la query SQL costruita
    infoTelegram("telegramWebhook: tgFindDocenteByChatId query=[$q]");

    // Esegue la query e recupera la prima riga trovata
    $row = dbGetFirst($q);

    // Se non è stato trovato alcun docente
    if (!$row) {
        // Scrive nel log un warning specificando il chatId
        warningTelegram("telegramWebhook: tgFindDocenteByChatId - nessun docente trovato per chatId=[$chatId]");
    } else {
        // Altrimenti scrive nel log l'id del docente trovato
        infoTelegram("telegramWebhook: tgFindDocenteByChatId trovato docente id=" . ($row['id'] ?? ''));
    }

    // Restituisce il record docente trovato oppure null
    return $row;
}

function tgFindGenitoreByChatId($chatId)
{
    $chatId = tgNorm($chatId);
    infoTelegram("telegramWebhook: tgFindGenitoreByChatId chiamata chatId=[$chatId]");

    if ($chatId === '') {
        warningTelegram("telegramWebhook: tgFindGenitoreByChatId - chatId vuoto");
        return null;
    }

    if (!tgTableExists('genitore_telegram')) {
        infoTelegram("telegramWebhook: tgFindGenitoreByChatId - tabella genitore_telegram assente");
        return null;
    }

    $q = "
        SELECT
            g.id,
            g.cognome,
            g.nome,
            g.email,
            t.telegram_chat_id,
            t.attivo,
            t.consenso_notifiche
        FROM genitore_telegram t
        INNER JOIN genitori g ON g.id = t.idGenitore
        WHERE t.telegram_chat_id = " . dbQ($chatId) . "
          AND t.attivo = 1
          AND t.consenso_notifiche = 1
          AND g.attivo = 1
        LIMIT 1
    ";

    infoTelegram("telegramWebhook: tgFindGenitoreByChatId query=[$q]");
    $row = dbGetFirst($q);

    if (!$row) {
        warningTelegram("telegramWebhook: tgFindGenitoreByChatId - nessun genitore trovato per chatId=[$chatId]");
    } else {
        infoTelegram("telegramWebhook: tgFindGenitoreByChatId trovato genitore id=" . ($row['id'] ?? ''));
    }

    return $row;
}

function tgPrivateActorLabel($actorType)
{
    $actorType = strtolower(trim((string)$actorType));
    if ($actorType === 'genitore') {
        return 'Genitore';
    }
    if ($actorType === 'studente') {
        return 'Studente';
    }
    return 'Docente';
}

function tgHandlePrivateActorMessage(array $actor, string $actorType, array $message, string $serviceChatId, string $botToken)
{
    $actorType = strtolower(trim((string)$actorType));
    $actorLabel = tgPrivateActorLabel($actorType);
    $actorMessageId = (int)($message['message_id'] ?? 0);
    $actorChatId = tgNorm($message['chat']['id'] ?? '');
    $text = tgNorm($message['text'] ?? '');
    $actorId = (int)($actor['id'] ?? 0);
    $actorName = trim(($actor['cognome'] ?? '') . ' ' . ($actor['nome'] ?? ''));

    infoTelegram("ENTER tgHandlePrivateActorMessage: actorType=$actorType actorId=$actorId message=" . json_encode($message));

    if ($actorMessageId <= 0 || $text === '' || $actorId <= 0) {
        warningTelegram("tgHandlePrivateActorMessage: dati non validi actorType=$actorType actorMessageId=$actorMessageId text=[" . tgCut($text, 50) . "] actorId=$actorId");
        return;
    }

    $openRelay = function_exists('tgFindOpenRelayByActor') ? tgFindOpenRelayByActor($actorType, $actorId) : null;

    if ($openRelay) {
        $idRelay = (int)($openRelay['id'] ?? 0);
        $ticketCode = tgNorm($openRelay['ticket_code'] ?? '');
        if ($ticketCode === '') {
            $ticketCode = tgUpdateTicketCode($idRelay);
        }

        $statoLabel = tgBuildStatoLabel($openRelay['stato'] ?? 'APERTA');
        $threadId = (int)($openRelay['service_thread_id'] ?? 0);

        dbExec("
            UPDATE docente_telegram_relay
            SET docente_message_id = " . dbI($actorMessageId) . ",
                ultimo_testo_docente = " . dbQ($text) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        $openRelay = tgFindRelayById($idRelay);
        $actorChatId = tgNorm($openRelay['docente_chat_id'] ?? '');
        if ($actorChatId === '') {
            errorTelegram("tgHandlePrivateActorMessage: actorChatId vuoto per idRelay=$idRelay");
            return;
        }

        $serviceText = "➕ Aggiornamento ticket {$ticketCode}\n\n👤 {$actorLabel}: {$actorName}\n📌 Stato attuale: {$statoLabel}\n\n✉️ Nuovo messaggio:\n" . tgCut($text, 3000);

        tgSendMessage(
            $botToken,
            $serviceChatId,
            $serviceText,
            ['message_thread_id' => $threadId, 'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($openRelay), JSON_UNESCAPED_UNICODE)]
        );

        tgSendMessage($botToken, $actorChatId, "✅ Il tuo messaggio è stato aggiunto al ticket {$ticketCode}.\nStato corrente: {$statoLabel}.");
        infoTelegram("EXIT tgHandlePrivateActorMessage: ticket aggiornato idRelay=$idRelay ticket=$ticketCode actorType=$actorType");
        return;
    }

    $closedRelay = function_exists('tgFindLatestClosedRelayByActor') ? tgFindLatestClosedRelayByActor($actorType, $actorId) : null;
    if ($closedRelay) {
        $idRelay = (int)($closedRelay['id'] ?? 0);
        $ticketCode = tgNorm($closedRelay['ticket_code'] ?? '');
        if ($ticketCode === '') {
            $ticketCode = tgUpdateTicketCode($idRelay);
        }

        $threadId = (int)($closedRelay['service_thread_id'] ?? 0);
        dbExec("
            UPDATE docente_telegram_relay
            SET docente_message_id = " . dbI($actorMessageId) . ",
                ultimo_testo_docente = " . dbQ($text) . ",
                data_aggiornamento = NOW()
            WHERE id = " . dbI($idRelay) . "
        ");

        $closedRelay = tgFindRelayById($idRelay);
        if (!$closedRelay) {
            warningTelegram("tgHandlePrivateActorMessage: relay chiuso non ricaricato idRelay=$idRelay");
            return;
        }
        $serviceText =
            "🔁 Nuovo messaggio su ticket chiuso {$ticketCode}\n\n" .
            "👤 {$actorLabel}: {$actorName}\n\n" .
            "⚠️ Il ticket precedente risulta chiuso.\nScegli se riaprirlo oppure aprire un nuovo ticket.\n\n" .
            "✉️ Messaggio:\n" . tgCut($text, 3000);

        $sendOptions = [
            'reply_markup' => json_encode(tgGetClosedTicketFollowupKeyboard($closedRelay), JSON_UNESCAPED_UNICODE)
        ];
        if ($threadId > 0) {
            $sendOptions['message_thread_id'] = $threadId;
        }

        tgSendMessage($botToken, $serviceChatId, $serviceText, $sendOptions);
        tgSendMessage(
            $botToken,
            $actorChatId,
            "✅ Il tuo messaggio è stato ricevuto.\nIl ticket precedente {$ticketCode} era chiuso: il servizio GestOre deciderà se riaprirlo o aprire una nuova richiesta."
        );
        infoTelegram("EXIT tgHandlePrivateActorMessage: messaggio su ticket chiuso idRelay=$idRelay ticket=$ticketCode actorType=$actorType");
        return;
    }

    dbExec("START TRANSACTION");

    try {
        $insertColumns = [
            'docente_chat_id',
            'docente_message_id',
            'service_chat_id',
            'service_message_id',
            'service_thread_root_message_id',
            'stato',
            'chiusa',
            'ultimo_testo_docente'
        ];
        $insertValues = [
            dbQ($actorChatId),
            dbI($actorMessageId),
            dbQ($serviceChatId),
            '0',
            '0',
            "'APERTA'",
            '0',
            dbQ($text)
        ];

        if ($actorType === 'genitore') {
            if (function_exists('ticketMailColumnExists') && !ticketMailColumnExists('docente_telegram_relay', 'idGenitore')) {
                throw new Exception("Colonna idGenitore mancante in docente_telegram_relay");
            }
            $insertColumns[] = 'idGenitore';
            $insertValues[] = dbI($actorId);
        } else {
            $insertColumns[] = 'idDocente';
            $insertValues[] = dbI($actorId);
        }

        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'tipo_utente')) {
            $insertColumns[] = 'tipo_utente';
            $insertValues[] = dbQ($actorType);
        }
        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'utente_nome')) {
            $insertColumns[] = 'utente_nome';
            $insertValues[] = dbQ((string)($actor['nome'] ?? ''));
        }
        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'utente_cognome')) {
            $insertColumns[] = 'utente_cognome';
            $insertValues[] = dbQ((string)($actor['cognome'] ?? ''));
        }
        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'utente_email')) {
            $insertColumns[] = 'utente_email';
            $insertValues[] = dbQ((string)($actor['email'] ?? ''));
        }

        $q = "
            INSERT INTO docente_telegram_relay (
                " . implode(",\n                ", $insertColumns) . "
            ) VALUES (
                " . implode(",\n                ", $insertValues) . "
            )
        ";

        infoTelegram("DB INSERT nuovo relay actorType=$actorType query=" . trim($q));
        dbExec($q);

        $idRelay = (int)dblastId();
        $ticketCode = tgUpdateTicketCode($idRelay);
        $serviceThreadId = tgCreateTopic($botToken, $serviceChatId, "Ticket " . $ticketCode);

        dbExec("
            UPDATE docente_telegram_relay
            SET service_thread_id = " . dbI($serviceThreadId) . ",
                thread_topic_name = " . dbQ("Ticket $ticketCode") . "
            WHERE id = " . dbI($idRelay) . "
        ");

        $openRelay = tgFindRelayById($idRelay);
        $actorChatId = tgNorm($openRelay['docente_chat_id'] ?? '');
        if ($actorChatId === '') {
            throw new Exception("actorChatId vuoto dopo inserimento nuovo relay idRelay=$idRelay");
        }

        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            "📩 Nuovo messaggio da " . strtolower($actorLabel) . "\n\n🏷 Ticket: $ticketCode\n👤 {$actorLabel}: $actorName\n✉️ Messaggio:\n" . tgCut($text, 3000),
            [
                'message_thread_id' => $serviceThreadId,
                'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($openRelay), JSON_UNESCAPED_UNICODE)
            ]
        );

        $serviceMessageId = (int)($sendRes['message_id'] ?? 0);
        dbExec("
            UPDATE docente_telegram_relay
            SET service_message_id = " . dbI($serviceMessageId) . ",
                service_thread_root_message_id = " . dbI($serviceMessageId) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        dbExec("COMMIT");

        tgSendMessage(
            $botToken,
            $actorChatId,
            "✅ Il tuo messaggio è stato ricevuto.\nTicket: {$ticketCode}\nIl tuo caso sarà preso in carico appena possibile."
        );

        infoTelegram("EXIT tgHandlePrivateActorMessage: nuovo ticket creato idRelay=$idRelay ticket=$ticketCode actorType=$actorType");
        return;
    } catch (Throwable $e) {
        dbExec("ROLLBACK");
        errorTelegram("tgHandlePrivateActorMessage: eccezione " . $e->getMessage());
        tgSendMessage($botToken, $actorChatId, "❌ Errore durante l'apertura del ticket. Riprova più tardi.");
        return;
    }
}

function tgIsUserInGroup($botToken, $groupChatId, $userId)
{
    // Costruisce l'URL dell'endpoint Telegram getChatMember
    $url = "https://api.telegram.org/bot{$botToken}/getChatMember";

    // Prepara i parametri da inviare a Telegram
    $params = [
        'chat_id' => $groupChatId,
        'user_id' => $userId
    ];

    // Inizializza cURL aggiungendo i parametri all'URL come query string
    $ch = curl_init($url . '?' . http_build_query($params));

    // Fa restituire la risposta come stringa invece di stamparla
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Imposta timeout massimo di 10 secondi
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Esegue la chiamata HTTP verso Telegram
    $response = curl_exec($ch);

    // Chiude la sessione cURL
    curl_close($ch);

    // Decodifica la risposta JSON in array associativo
    $json = json_decode($response, true);

    // Se la risposta non è valida oppure Telegram non ha restituito ok=true
    if (!is_array($json) || empty($json['ok'])) {
        // Restituisce false
        return false;
    }

    // Estrae lo status del membro nel gruppo
    $status = $json['result']['status'] ?? '';

    // Restituisce true solo se l'utente è creator o administrator
    return in_array($status, ['creator', 'administrator'], true);
}

function tgFindAdminTelegramByUserId($telegramUserId)
{
    // Normalizza l'id Telegram dell'admin
    $telegramUserId = tgNorm($telegramUserId);

    // Se l'id è vuoto restituisce null
    if ($telegramUserId === '') return null;

    // Costruisce la query per cercare l'admin nella tabella admin_telegram
    $q = "
        SELECT *
        FROM admin_telegram
        WHERE telegram_user_id = " . dbQ($telegramUserId) . "
        LIMIT 1
    ";

    // Esegue la query e restituisce il primo record trovato
    return dbGetFirst($q);
}

function tgUpsertAdminTelegram($telegramUserId, $telegramChatId, $nome, $username = '')
{
    // Normalizza l'id Telegram utente
    $telegramUserId = tgNorm($telegramUserId);

    // Normalizza il chatId privato dell'admin
    $telegramChatId = tgNorm($telegramChatId);

    // Se il nome è null lo trasforma in stringa vuota, poi lo normalizza
    $nome = ($nome === null) ? '' : tgNorm($nome);

    // Se lo username è null lo trasforma in stringa vuota, poi lo normalizza
    $username = ($username === null) ? '' : tgNorm($username);

    // Se il nome è vuoto usa un valore di fallback
    if ($nome === '') {
        $nome = 'Admin Telegram';
    }

    // Se manca userId o chatId restituisce false
    if ($telegramUserId === '' || $telegramChatId === '') return false;

    // Cerca se esiste già un record admin per questo telegram_user_id
    $row = tgFindAdminTelegramByUserId($telegramUserId);

    // Se il record esiste già
    if ($row) {
        // Costruisce la query di aggiornamento
        $q = "
            UPDATE admin_telegram
            SET telegram_chat_id = " . dbI($telegramChatId) . ",
                nome = " . dbQNotNull($nome, 'Admin Telegram') . ",
                username = " . dbQNotNull($username, '') . ",
                attivo = 1
            WHERE telegram_user_id = " . dbI($telegramUserId) . "
        ";

        // Scrive nel log la query di update
        errorTelegram("tgUpsertAdminTelegram QUERY: " . $q);

        // Esegue l'update
        dbExec($q);

        // Restituisce true
        return true;
    }

    // Se il record non esiste costruisce la query di inserimento
    $q = "
        INSERT INTO admin_telegram (
            telegram_user_id,
            telegram_chat_id,
            nome,
            username,
            notifiche_sostituzioni,
            attivo
        ) VALUES (
            " . dbI($telegramUserId) . ",
            " . dbI($telegramChatId) . ",
            " . dbQNotNull($nome, 'Admin Telegram') . ",
            " . dbQNotNull($username, '') . ",
            0,
            1
        )
    ";

    // Scrive nel log la query di insert
    errorTelegram("tgUpsertAdminTelegram QUERY: " . $q);

    // Esegue l'inserimento
    dbExec($q);

    // Restituisce true
    return true;
}

function tgSetAdminSostituzioniNotify($telegramUserId, $enabled)
{
    // Normalizza l'id Telegram dell'admin
    $telegramUserId = tgNorm($telegramUserId);

    // Se l'id è vuoto restituisce false
    if ($telegramUserId === '') return false;

    // Costruisce la query per attivare/disattivare notifiche sostituzioni
    $q = "
        UPDATE admin_telegram
        SET notifiche_sostituzioni = " . dbI($enabled ? 1 : 0) . ",
            attivo = 1
        WHERE telegram_user_id = " . dbQ($telegramUserId) . "
    ";

    // Esegue la query
    dbExec($q);

    // Restituisce true
    return true;
}

function tgGetAdminSostituzioniNotifyStatus($telegramUserId)
{
    // Normalizza l'id Telegram dell'admin
    $telegramUserId = tgNorm($telegramUserId);

    // Se l'id è vuoto restituisce null
    if ($telegramUserId === '') return null;

    // Costruisce la query per leggere il flag notifiche_sostituzioni
    $q = "
        SELECT notifiche_sostituzioni
        FROM admin_telegram
        WHERE telegram_user_id = " . dbQ($telegramUserId) . "
        LIMIT 1
    ";

    // Esegue la query
    $row = dbGetFirst($q);

    // Se non trova nessun record restituisce null
    if (!$row) return null;

    // Restituisce il valore del flag convertito in intero
    return (int)($row['notifiche_sostituzioni'] ?? 0);
}

function tgCopyMessage($botToken, $toChatId, $fromChatId, $messageId, array $extra = [])
{
    // Normalizza il bot token eliminando eventuali spazi
    $botToken   = trim((string)$botToken);

    // Normalizza la chat destinataria
    $toChatId   = trim((string)$toChatId);

    // Normalizza la chat sorgente
    $fromChatId = trim((string)$fromChatId);

    // Converte l'id del messaggio in intero
    $messageId  = (int)$messageId;

    // Se mancano parametri obbligatori restituisce errore
    if ($botToken === '' || $toChatId === '' || $fromChatId === '' || $messageId <= 0) {
        return ['ok' => false, 'error' => 'Parametri copyMessage mancanti'];
    }

    // Costruisce l'URL dell'endpoint Telegram copyMessage
    $url = "https://api.telegram.org/bot{$botToken}/copyMessage";

    // Costruisce il payload base unendo eventuali parametri extra
    $payload = array_merge([
        'chat_id'      => $toChatId,
        'from_chat_id' => $fromChatId,
        'message_id'   => $messageId
    ], $extra);

    // Inizializza cURL
    $ch = curl_init($url);

    // Imposta la richiesta come POST
    curl_setopt($ch, CURLOPT_POST, true);

    // Fa restituire la risposta come stringa
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Invia il payload come form-urlencoded
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

    // Imposta timeout massimo di 20 secondi
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    // Esegue la chiamata Telegram
    $response = curl_exec($ch);

    // Legge eventuale codice errore cURL
    $errno = curl_errno($ch);

    // Legge eventuale messaggio errore cURL
    $error = curl_error($ch);

    // Chiude la sessione cURL
    curl_close($ch);

    // Se cURL ha prodotto errore restituisce errore
    if ($errno) {
        return ['ok' => false, 'error' => $error];
    }

    // Decodifica la risposta JSON di Telegram
    $json = json_decode($response, true);

    // Se Telegram ha risposto correttamente restituisce ok=true con dettagli
    if (is_array($json) && !empty($json['ok'])) {
        return ['ok' => true, 'response' => $response, 'json' => $json];
    }

    // Altrimenti restituisce errore con testo risposta o fallback
    return ['ok' => false, 'error' => $response ?: 'Risposta Telegram vuota'];
}

function tgGetMyWorkingTickets($adminUserId)
{
    // Normalizza l'id admin
    $adminUserId = tgNorm($adminUserId);

    // Se l'id admin è vuoto restituisce array vuoto
    if ($adminUserId === '') return [];

    // Costruisce la query per prendere i ticket in gestione dall'admin corrente
    $q = "
        SELECT r.*, d.cognome, d.nome
        FROM docente_telegram_relay r
        LEFT JOIN docente d ON d.id = r.idDocente
        WHERE r.stato = 'IN_GESTIONE'
          AND (r.chiusa = 0 OR r.chiusa IS NULL)
          AND r.preso_in_carico_da = " . dbQ($adminUserId) . "
        ORDER BY r.id DESC
        LIMIT 20
    ";

    // Esegue la query e recupera tutte le righe
    $rows = dbGetAll($q);

    // Se il risultato è un array lo restituisce, altrimenti restituisce array vuoto
    return is_array($rows) ? $rows : [];
}

function tgGetWorkingTickets()
{
    // Costruisce la query per prendere tutti i ticket in lavorazione
    $q = "
        SELECT r.*, d.cognome, d.nome
        FROM docente_telegram_relay r
        LEFT JOIN docente d ON d.id = r.idDocente
        WHERE r.stato = 'IN_GESTIONE'
          AND (r.chiusa = 0 OR r.chiusa IS NULL)
        ORDER BY r.id DESC
        LIMIT 20
    ";

    // Esegue la query
    $rows = dbGetAll($q);

    // Restituisce l'array dei risultati oppure array vuoto
    return is_array($rows) ? $rows : [];
}

function tgGetClosedTicketsToday()
{
    // Costruisce la query per prendere i ticket chiusi nella data odierna
    $q = "
        SELECT r.*, d.cognome, d.nome
        FROM docente_telegram_relay r
        LEFT JOIN docente d ON d.id = r.idDocente
        WHERE r.stato = 'CHIUSA'
          AND r.chiusa = 1
          AND DATE(r.data_chiusura) = CURDATE()
        ORDER BY r.data_chiusura DESC, r.id DESC
        LIMIT 50
    ";

    // Esegue la query
    $rows = dbGetAll($q);

    // Restituisce l'array dei risultati oppure array vuoto
    return is_array($rows) ? $rows : [];
}

function tgGetOpenUnassignedTickets()
{
    // Costruisce la query per prendere i ticket aperti non ancora presi in carico
    $q = "
        SELECT r.*, d.cognome, d.nome
        FROM docente_telegram_relay r
        LEFT JOIN docente d ON d.id = r.idDocente
        WHERE r.stato = 'APERTA'
          AND (r.chiusa = 0 OR r.chiusa IS NULL)
        ORDER BY r.id DESC
        LIMIT 20
    ";

    // Esegue la query
    $rows = dbGetAll($q);

    // Restituisce l'array dei risultati oppure array vuoto
    return is_array($rows) ? $rows : [];
}

function tgBuildMyWorkingTicketsSummary($adminUserId, $adminName = '')
{
    // Recupera l'elenco dei ticket in gestione dell'admin
    $rows = tgGetMyWorkingTickets($adminUserId);

    // Se non ci sono ticket restituisce un messaggio dedicato
    if (empty($rows)) {
        return "👤 Nessun ticket attualmente in gestione da parte tua.";
    }

    // Inizializza le righe del riepilogo
    $lines = ["👤 I miei ticket in lavorazione:\n"];

    // Se il nome admin non è vuoto lo aggiunge all'intestazione
    if (tgNorm($adminName) !== '') {
        $lines[] = "Admin: " . $adminName . "\n";
    }

    // Scorre tutti i ticket trovati
    foreach ($rows as $r) {
        // Estrae e normalizza il ticket code
        $ticketCode = tgNorm($r['ticket_code'] ?? '');

        // Costruisce il nome del docente richiedente
        $docente = trim(tgNorm($r['cognome'] ?? '') . ' ' . tgNorm($r['nome'] ?? ''));

        // Estrae e normalizza l'ultimo testo inviato dal docente
        $msg = tgNorm($r['ultimo_testo_docente'] ?? '');

        // Tronca il testo a 80 caratteri
        $msg = tgCut($msg, 80);

        // Aggiunge una riga con ticket e docente
        $lines[] = "• {$ticketCode} - Richiedente: {$docente}";

        // Se esiste un testo messaggio lo aggiunge sotto
        if ($msg !== '') {
            $lines[] = "  {$msg}";
        }
    }

    // Restituisce il riepilogo finale come stringa con righe unite da newline
    return implode("\n", $lines);
}

function tgGetAdminsToNotifySostituzioni()
{
    // Costruisce la query per prendere tutti gli admin attivi con notifiche sostituzioni abilitate
    $q = "
        SELECT *
        FROM admin_telegram
        WHERE attivo = 1
          AND notifiche_sostituzioni = 1
          AND telegram_chat_id IS NOT NULL
          AND telegram_chat_id <> ''
        ORDER BY nome
    ";

    // Esegue la query
    $rows = dbGetAll($q);

    // Restituisce l'array risultati oppure array vuoto
    return is_array($rows) ? $rows : [];
}

function tgBuildOpenTicketsSummary()
{
    // Recupera l'elenco dei ticket aperti non ancora assegnati
    $rows = tgGetOpenUnassignedTickets();

    // Se non ci sono ticket aperti restituisce testo e keyboard nulla
    if (empty($rows)) {
        return [
            'text' => "📋 Nessun ticket aperto non ancora preso in carico.",
            'keyboard' => null
        ];
    }

    // Inizializza le righe del riepilogo
    $lines = ["📋 Ticket aperti non ancora presi in carico:\n"];

    // Inizializza le righe della tastiera
    $keyboardRows = [];

    // Scorre ogni ticket aperto
    foreach ($rows as $r) {
        // Estrae l'id relay
        $idRelay = (int)($r['id'] ?? 0);

        // Estrae e normalizza il ticket code
        $ticketCode = tgNorm($r['ticket_code'] ?? '');

        // Costruisce il nome del docente
        $docente = trim(tgNorm($r['cognome'] ?? '') . ' ' . tgNorm($r['nome'] ?? ''));

        // Estrae e normalizza il testo dell'ultimo messaggio docente
        $msg = tgNorm($r['ultimo_testo_docente'] ?? '');

        // Tronca il messaggio a 80 caratteri
        $msg = tgCut($msg, 80);

        // Aggiunge una riga con ticket e docente
        $lines[] = "• {$ticketCode} - {$docente}";

        // Se il messaggio esiste lo aggiunge sotto
        if ($msg !== '') {
            $lines[] = "  {$msg}";
        }

        // Se l'id relay è valido aggiunge un bottone per la presa in carico
        if ($idRelay > 0) {
            $keyboardRows[] = [
                [
                    'text' => "🟡 Prendi {$ticketCode}",
                    'callback_data' => "presa_relay_{$idRelay}"
                ]
            ];
        }
    }

    // Restituisce testo riepilogo e inline keyboard costruita
    return [
        'text' => implode("\n", $lines),
        'keyboard' => [
            'inline_keyboard' => $keyboardRows
        ]
    ];
}

function tgBuildWorkingTicketsSummary()
{
    // Recupera l'elenco dei ticket attualmente in lavorazione
    $rows = tgGetWorkingTickets();

    // Se non ci sono ticket in lavorazione restituisce messaggio dedicato
    if (empty($rows)) {
        return "🟡 Nessun ticket attualmente in lavorazione.";
    }

    // Inizializza le righe del riepilogo
    $lines = ["🟡 Ticket in lavorazione:\n"];

    // Scorre i ticket in lavorazione
    foreach ($rows as $r) {
        // Estrae e normalizza il ticket code
        $ticketCode = tgNorm($r['ticket_code'] ?? '');

        // Costruisce il nome del docente
        $docente = trim(tgNorm($r['cognome'] ?? '') . ' ' . tgNorm($r['nome'] ?? ''));

        // Estrae e normalizza il nome dell'admin che ha preso in carico
        $owner = tgNorm($r['preso_in_carico_nome'] ?? '');

        // Estrae e normalizza l'ultimo messaggio del docente
        $msg = tgNorm($r['ultimo_testo_docente'] ?? '');

        // Tronca il messaggio a 80 caratteri
        $msg = tgCut($msg, 80);

        // Aggiunge la riga principale del ticket
        $lines[] = "• {$ticketCode} - {$docente}";

        // Se è noto l'owner lo aggiunge
        if ($owner !== '') {
            $lines[] = "  In gestione da: {$owner}";
        }

        // Se esiste un messaggio lo aggiunge
        if ($msg !== '') {
            $lines[] = "  {$msg}";
        }
    }

    // Restituisce il riepilogo finale
    return implode("\n", $lines);
}

function tgBuildClosedTodayTicketsSummary()
{
    // Recupera l'elenco dei ticket chiusi oggi
    $rows = tgGetClosedTicketsToday();

    // Se non ci sono ticket chiusi oggi restituisce messaggio dedicato
    if (empty($rows)) {
        return "✅ Nessun ticket chiuso oggi.";
    }

    // Inizializza le righe del riepilogo
    $lines = ["✅ Ticket chiusi oggi:\n"];

    // Scorre i ticket chiusi
    foreach ($rows as $r) {
        // Estrae e normalizza il ticket code
        $ticketCode = tgNorm($r['ticket_code'] ?? '');

        // Costruisce il nome del docente
        $docente = trim(tgNorm($r['cognome'] ?? '') . ' ' . tgNorm($r['nome'] ?? ''));

        // Estrae e normalizza il nome di chi ha chiuso/preso in carico
        $owner = tgNorm($r['preso_in_carico_nome'] ?? '');

        // Estrae e normalizza la data/ora di chiusura
        $when = tgNorm($r['data_chiusura'] ?? '');

        // Inizializza stringa ora vuota
        $ora = '';

        // Se esiste una data/ora di chiusura
        if ($when !== '') {
            // La converte in timestamp Unix
            $ts = strtotime($when);

            // Se la conversione è andata a buon fine
            if ($ts) {
                // Estrae l'ora nel formato HH:MM
                $ora = date('H:i', $ts);
            }
        }

        // Costruisce la riga base del ticket chiuso
        $line = "• {$ticketCode} - {$docente}";

        // Se è disponibile l'ora la aggiunge alla riga
        if ($ora !== '') {
            $line .= " - chiuso alle {$ora}";
        }

        // Aggiunge la riga principale al riepilogo
        $lines[] = $line;

        // Se esiste il nome dell'owner lo aggiunge sotto
        if ($owner !== '') {
            $lines[] = "  Chiuso da: {$owner}";
        }
    }

    // Restituisce il riepilogo finale dei ticket chiusi oggi
    return implode("\n", $lines);
}

function tgTableExists($tableName)
{
    $tableName = trim((string)$tableName);
    if ($tableName === '') {
        return false;
    }

    return dbGetValue("SHOW TABLES LIKE " . dbQ($tableName)) !== null;
}

function tgFindTelegramLinkToken($token)
{
    $token = tgNorm($token);
    if ($token === '') {
        return null;
    }

    $tok = dbGetFirst("
        SELECT *
        FROM docente_telegram_token
        WHERE token = " . dbQ($token) . "
        LIMIT 1
    ");
    if ($tok) {
        return [
            'tipo_utente' => 'docente',
            'token_row' => $tok,
            'actor_id' => (int)($tok['idDocente'] ?? 0),
        ];
    }

    if (tgTableExists('genitore_telegram_token')) {
        $tok = dbGetFirst("
            SELECT *
            FROM genitore_telegram_token
            WHERE token = " . dbQ($token) . "
            LIMIT 1
        ");
        if ($tok) {
            return [
                'tipo_utente' => 'genitore',
                'token_row' => $tok,
                'actor_id' => (int)($tok['idGenitore'] ?? 0),
            ];
        }
    }

    return null;
}

function tgLoadTelegramLinkActor($actorType, $actorId)
{
    $actorId = (int)$actorId;
    if ($actorId <= 0) {
        return null;
    }

    if ($actorType === 'genitore') {
        return dbGetFirst("
            SELECT id, cognome, nome, email
            FROM genitori
            WHERE id = " . dbI($actorId) . "
              AND attivo = 1
            LIMIT 1
        ");
    }

    return dbGetFirst("
        SELECT id, cognome, nome, email
        FROM docente
        WHERE id = " . dbI($actorId) . "
          AND attivo = 1
        LIMIT 1
    ");
}

function tgUpsertTelegramLinkActor($actorType, $actorId, $chatId)
{
    $actorId = (int)$actorId;
    $chatId = tgNorm($chatId);
    if ($actorId <= 0 || $chatId === '') {
        return false;
    }

    if ($actorType === 'genitore') {
        $existing = dbGetFirst("
            SELECT *
            FROM genitore_telegram
            WHERE idGenitore = " . dbI($actorId) . "
            LIMIT 1
        ");

        if ($existing) {
            dbExec("
                UPDATE genitore_telegram
                SET telegram_chat_id = " . dbQ($chatId) . ",
                    attivo = 1,
                    consenso_notifiche = 1,
                    ultimo_errore = NULL,
                    ultimo_errore_data = NULL
                WHERE idGenitore = " . dbI($actorId)
            );
        } else {
            dbExec("
                INSERT INTO genitore_telegram (
                    idGenitore,
                    telegram_chat_id,
                    attivo,
                    consenso_notifiche
                ) VALUES (
                    " . dbI($actorId) . ",
                    " . dbQ($chatId) . ",
                    1,
                    1
                )
            ");
        }

        return true;
    }

    $existing = dbGetFirst("
        SELECT *
        FROM docente_telegram
        WHERE idDocente = " . dbI($actorId) . "
        LIMIT 1
    ");

    if ($existing) {
        dbExec("
            UPDATE docente_telegram
            SET telegram_chat_id = " . dbQ($chatId) . ",
                attivo = 1,
                consenso_notifiche = 1,
                ultimo_errore = NULL,
                ultimo_errore_data = NULL
            WHERE idDocente = " . dbI($actorId)
        );
    } else {
        dbExec("
            INSERT INTO docente_telegram (
                idDocente,
                telegram_chat_id,
                attivo,
                consenso_notifiche
            ) VALUES (
                " . dbI($actorId) . ",
                " . dbQ($chatId) . ",
                1,
                1
            )
        ");
    }

    return true;
}

function tgMarkTelegramLinkTokenUsed($actorType, $idToken)
{
    $idToken = (int)$idToken;
    if ($idToken <= 0) {
        return false;
    }

    $tableName = ($actorType === 'genitore') ? 'genitore_telegram_token' : 'docente_telegram_token';
    dbExec("
        UPDATE " . $tableName . "
        SET usato = 1,
            dataUso = NOW()
        WHERE idToken = " . dbI($idToken)
    );

    return true;
}

function tgHandleStartToken($token, $chatId)
{
    // Normalizza il token ricevuto dal comando /start
    $token = tgNorm($token);

    // Normalizza il chatId della chat Telegram corrente
    $chatId = tgNorm($chatId);

    // Se il token è vuoto, restituisce il messaggio di benvenuto con istruzioni
    if ($token === '') {
        return "👋 Benvenuto in GestOre.\n\nPer collegare il tuo account Telegram devi usare il link personale ricevuto via mail.";
    }

    // Scrive nel log che è stato ricevuto un token di start con relativo chatId
    infoTelegram("telegramWebhook: start token ricevuto token=[$token] chatId=[$chatId]");

    $tokenInfo = tgFindTelegramLinkToken($token);
    $tok = $tokenInfo['token_row'] ?? null;
    $actorType = (string)($tokenInfo['tipo_utente'] ?? 'docente');

    // Se il token non esiste nel database
    if (!$tok) {
        // Scrive nel log un warning che segnala token non trovato
        warningTelegram("telegramWebhook: token non trovato [$token]");

        // Restituisce messaggio di link non valido
        return "❌ Link non valido.\n\nIl token di collegamento non è stato trovato.";
    }

    // Se il token risulta già usato
    if ((int)($tok['usato'] ?? 0) === 1) {
        // Scrive nel log un warning che segnala token già usato
        warningTelegram("telegramWebhook: token già usato idToken=" . (int)$tok['idToken']);

        // Restituisce messaggio che informa che il link è già stato utilizzato
        return "⚠️ Questo link è già stato utilizzato.\n\nSe devi collegare di nuovo Telegram, richiedi una nuova mail da GestOre.";
    }

    // Estrae e normalizza la data di scadenza del token
    $dataScadenza = tgNorm($tok['dataScadenza'] ?? '');

    // Se il token ha una data di scadenza e la scadenza è passata
    if ($dataScadenza !== '' && strtotime($dataScadenza) < time()) {
        // Scrive nel log un warning che segnala token scaduto
        warningTelegram("telegramWebhook: token scaduto idToken=" . (int)$tok['idToken']);

        // Restituisce messaggio che informa che il link è scaduto
        return "⏰ Questo link è scaduto.\n\nRichiedi una nuova mail di collegamento da GestOre.";
    }

    $actorId = (int)($tokenInfo['actor_id'] ?? 0);

    if ($actorId <= 0) {
        errorTelegram("telegramWebhook: actor id non valido nel token idToken=" . (int)$tok['idToken'] . " type=[$actorType]");

        // Restituisce messaggio di errore generico di collegamento
        return "❌ Errore di collegamento.\n\nContatta la segreteria o l'amministratore di GestOre.";
    }

    $doc = tgLoadTelegramLinkActor($actorType, $actorId);

    // Se il profilo non esiste nel database
    if (!$doc) {
        errorTelegram("telegramWebhook: profilo non trovato actorType=[$actorType] actorId=$actorId");

        // Restituisce messaggio di errore
        return "❌ Profilo non trovato.\n\nContatta la segreteria o l'amministratore di GestOre.";
    }

    $displayName = trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? ''));
    $displayLabel = ($actorType === 'genitore') ? 'Genitore associato' : 'Docente associato';
    $finalHint = ($actorType === 'genitore')
        ? "Da ora puoi ricevere le notifiche Telegram di GestOre per il profilo genitore."
        : "Da ora puoi ricevere le notifiche Telegram di GestOre per le sostituzioni e scrivere al servizio assistenza.";

    // Avvia una transazione database
    dbExec("START TRANSACTION");

    try {
        if ($actorType === 'genitore' && !tgTableExists('genitore_telegram')) {
            throw new RuntimeException('Tabella genitore_telegram non presente');
        }

        tgUpsertTelegramLinkActor($actorType, $actorId, $chatId);
        tgMarkTelegramLinkTokenUsed($actorType, (int)$tok['idToken']);

        // Conferma la transazione
        dbExec("COMMIT");

        return "✅ Collegamento completato con successo.\n\n{$displayLabel}: {$displayName}\n{$finalHint}";
    } catch (Throwable $e) {
        // In caso di errore annulla la transazione
        dbExec("ROLLBACK");

        // Scrive nel log il messaggio dell'eccezione
        errorTelegram("telegramWebhook: eccezione " . $e->getMessage());

        // Restituisce messaggio di errore generico
        return "❌ Errore durante il collegamento Telegram.\n\nContatta la segreteria o l'amministratore di GestOre.";
    }
}

function tgHandlePrivateTeacherMessage(array $doc, array $message, string $serviceChatId, string $botToken)
{
    // Estrae l'id del messaggio inviato dal docente e lo converte in intero
    $teacherMessageId = (int)($message['message_id'] ?? 0);

    // Estrae e normalizza la chat Telegram del docente dal messaggio
    $teacherChatId   = tgNorm($message['chat']['id'] ?? '');

    // Estrae e normalizza il testo del messaggio del docente
    $text            = tgNorm($message['text'] ?? '');

    // Estrae l'id del docente dal record e lo converte in intero
    $idDocente       = (int)($doc['id'] ?? 0);

    // Costruisce il nome completo del docente unendo cognome e nome
    $docenteNome     = trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? ''));

    // Scrive nel log l'ingresso nella funzione con i dati principali, senza esporre il token completo
    infoTelegram("ENTER tgHandlePrivateTeacherMessage: doc=" . json_encode($doc) .
        " message=" . json_encode($message) .
        " serviceChatId=$serviceChatId botToken=TRUNCATED");

    // Se mancano dati essenziali (id messaggio, testo o id docente), interrompe la funzione
    if ($teacherMessageId <= 0 || $text === '' || $idDocente <= 0) {
        // Scrive nel log un warning con i dati non validi
        warningTelegram("tgHandlePrivateTeacherMessage: dati non validi teacherMessageId=$teacherMessageId text=[" . tgCut($text, 50) . "] idDocente=$idDocente");
        // Esce dalla funzione senza fare altro
        return;
    }

    // Recupera relay aperto

    // Scrive nel log che sta cercando un eventuale ticket aperto del docente
    infoTelegram("CALL tgFindOpenRelayByDocente idDocente=$idDocente");

    // Cerca nel database un relay aperto o in gestione associato al docente
    $openRelay = tgFindOpenRelayByDocente($idDocente);

    // Scrive nel log il risultato della ricerca relay
    infoTelegram("RETURN tgFindOpenRelayByDocente: " . json_encode($openRelay));

    // Se esiste già un relay aperto
    if ($openRelay) {
        // Ticket esistente

        // Estrae l'id del relay esistente
        $idRelay    = (int)$openRelay['id'];

        // Estrae e normalizza il ticket code del relay
        $ticketCode = tgNorm($openRelay['ticket_code']);

        // Se il relay non ha ancora un ticket code
        if ($ticketCode === '') {
            // Scrive nel log che sta per generare il ticket code
            infoTelegram("CALL tgUpdateTicketCode idRelay=$idRelay");

            // Genera e salva il ticket code nel database
            $ticketCode = tgUpdateTicketCode($idRelay);

            // Scrive nel log il ticket code generato
            infoTelegram("RETURN tgUpdateTicketCode ticketCode=$ticketCode");
        }

        // Costruisce la label leggibile dello stato corrente del ticket
        $statoLabel = tgBuildStatoLabel($openRelay['stato'] ?? 'APERTA');

        // Estrae l'id del thread Telegram del gruppo di servizio
        $threadId   = (int)($openRelay['service_thread_id'] ?? 0);

        // Aggiorna DB con nuovo messaggio docente

        // Scrive nel log che sta aggiornando il relay con l'ultimo messaggio docente
        infoTelegram("CALL DB UPDATE docente_telegram_relay idRelay=$idRelay ultimo_testo_docente");

        // Aggiorna il record relay salvando l'id del messaggio docente e il testo più recente
        dbExec("
            UPDATE docente_telegram_relay
            SET docente_message_id = " . dbI($teacherMessageId) . ",
                ultimo_testo_docente = " . dbQ($text) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        // Scrive nel log che l'update DB è terminato
        infoTelegram("RETURN DB UPDATE docente_telegram_relay idRelay=$idRelay completato");

        // Ricarica relay aggiornato da DB

        // Ricarica il relay dal database per avere i dati aggiornati
        $openRelay = tgFindRelayById($idRelay);

        // Estrae e normalizza la chat Telegram del docente dal relay aggiornato
        $teacherChatId = tgNorm($openRelay['docente_chat_id'] ?? '');

        // Scrive nel log i dati ricaricati del relay
        infoTelegram("tgHandlePrivateTeacherMessage: relay aggiornato ricaricato idRelay=$idRelay teacherChatId=$teacherChatId");

        // Se la chat Telegram del docente è vuota
        if ($teacherChatId === '') {
            // Scrive nel log un errore perché non è possibile rispondere al docente
            errorTelegram("tgHandlePrivateTeacherMessage: teacherChatId vuoto per idRelay=$idRelay, abort invio docente");

            // Esce dalla funzione
            return;
        }

        // Costruisce il testo del messaggio da inviare nel gruppo di servizio
        $serviceText = "➕ Aggiornamento ticket {$ticketCode}\n\n👤 Docente: {$docenteNome}\n📌 Stato attuale: {$statoLabel}\n\n✉️ Nuovo messaggio:\n" . tgCut($text, 3000);

        // Scrive nel log che sta per inviare l'aggiornamento al gruppo di servizio
        infoTelegram("CALL tgSendMessage to serviceChatId=$serviceChatId threadId=$threadId text=[" . tgCut($serviceText, 200) . "]");

        // Invia nel thread del gruppo admin l'aggiornamento del ticket con tastiera aggiornata
        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            $serviceText,
            ['message_thread_id' => $threadId, 'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($openRelay), JSON_UNESCAPED_UNICODE)]
        );

        // Scrive nel log l'esito dell'invio al gruppo di servizio
        infoTelegram("RETURN tgSendMessage service result=" . json_encode($sendRes));

        // Invia conferma al docente

        // Scrive nel log che sta per inviare la conferma al docente
        infoTelegram("CALL tgSendMessage to teacherChatId=$teacherChatId text=[" . tgCut("✅ Il tuo messaggio è stato aggiunto al ticket {$ticketCode}", 200) . "]");

        // Invia al docente la conferma che il messaggio è stato aggiunto al ticket
        tgSendMessage($botToken, $teacherChatId, "✅ Il tuo messaggio è stato aggiunto al ticket {$ticketCode}.\nStato corrente: {$statoLabel}.");

        // Scrive nel log che l'invio al docente è terminato
        infoTelegram("RETURN tgSendMessage teacher completato idRelay=$idRelay");

        // Scrive nel log l'uscita dalla funzione con ticket aggiornato
        infoTelegram("EXIT tgHandlePrivateTeacherMessage: ticket aggiornato idRelay=$idRelay ticket=$ticketCode");

        // Esce dalla funzione perché il ticket esistente è stato aggiornato
        return;
    }

    // Creazione nuovo ticket

    // Scrive nel log che non esiste un ticket aperto e ne verrà creato uno nuovo
    infoTelegram("tgHandlePrivateTeacherMessage: nessun ticket aperto, creo nuovo relay idDocente=$idDocente");

    // Avvia una transazione database
    dbExec("START TRANSACTION");

    try {
        // Costruisce la query di inserimento del nuovo relay
        $q = "
            INSERT INTO docente_telegram_relay (
                idDocente,
                docente_chat_id,
                docente_message_id,
                service_chat_id,
                service_message_id,
                service_thread_root_message_id,
                stato,
                chiusa,
                ultimo_testo_docente
            ) VALUES (
                " . dbI($idDocente) . ",
                " . dbQ($teacherChatId) . ",
                " . dbI($teacherMessageId) . ",
                " . dbQ($serviceChatId) . ",
                0,
                0,
                'APERTA',
                0,
                " . dbQ($text) . "
            )
        ";

        // Scrive nel log la query di inserimento del nuovo relay
        infoTelegram("DB INSERT nuovo relay query=" . trim($q));

        // Esegue l'inserimento del nuovo relay
        dbExec($q);

        // Recupera l'id appena creato del relay
        $idRelay = (int)dblastId();

        // Scrive nel log l'id del relay appena inserito
        infoTelegram("DB INSERT completato idRelay=$idRelay");

        // Genera e salva il ticket code del nuovo relay
        $ticketCode = tgUpdateTicketCode($idRelay);

        // Scrive nel log il ticket code generato
        infoTelegram("RETURN tgUpdateTicketCode ticketCode=$ticketCode");

        // Crea un topic/thread nel gruppo di servizio con nome "Ticket CODICE"
        $serviceThreadId = tgCreateTopic($botToken, $serviceChatId, "Ticket " . $ticketCode);

        // Scrive nel log il thread id creato
        infoTelegram("RETURN tgCreateTopic serviceThreadId=$serviceThreadId");

        // Aggiorna il relay salvando l'id del thread e il nome del topic
        dbExec("
            UPDATE docente_telegram_relay
            SET service_thread_id = " . dbI($serviceThreadId) . ",
                thread_topic_name = " . dbQ("Ticket $ticketCode") . "
            WHERE id = " . dbI($idRelay) . "
        ");

        // Scrive nel log che l'update con i dati del thread è terminato
        infoTelegram("DB UPDATE relay con thread info completato idRelay=$idRelay");

        // Ricarica relay aggiornato per keyboard e chat docente

        // Ricarica dal database il relay aggiornato
        $openRelay = tgFindRelayById($idRelay);

        // Estrae e normalizza la chat Telegram del docente
        $teacherChatId = tgNorm($openRelay['docente_chat_id'] ?? '');

        // Se la chat docente è vuota anche dopo l'inserimento, solleva un'eccezione
        if ($teacherChatId === '') {
            throw new Exception("teacherChatId vuoto dopo inserimento nuovo relay idRelay=$idRelay");
        }

        // Prepara i parametri extra da passare al messaggio nel gruppo admin
        $payloadExtra = [
            'message_thread_id' => $serviceThreadId,
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($openRelay), JSON_UNESCAPED_UNICODE)
        ];

        // Invia nel gruppo admin il primo messaggio del nuovo ticket
        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            "📩 Nuovo messaggio da docente\n\n🏷 Ticket: $ticketCode\n👤 Docente: $docenteNome\n✉️ Messaggio:\n" . tgCut($text, 3000),
            $payloadExtra
        );

        // Scrive nel log l'esito dell'invio del nuovo relay
        infoTelegram("RETURN tgSendMessage nuovo relay: " . json_encode($sendRes));

        // Estrae l'id del messaggio di servizio appena inviato
        $serviceMessageId = (int)($sendRes['message_id'] ?? 0);

        // Aggiorna il relay salvando il service_message_id
        dbExec("
            UPDATE docente_telegram_relay
            SET service_message_id = " . dbI($serviceMessageId) . ", service_thread_root_message_id = " . dbI($serviceMessageId) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        // Scrive nel log che l'update service_message_id è terminato
        infoTelegram("DB UPDATE relay service_message_id completato idRelay=$idRelay");

        // Conferma la transazione DB
        dbExec("COMMIT");

        // Scrive nel log che il commit è stato completato
        infoTelegram("DB COMMIT completato idRelay=$idRelay");

        // Invia al docente la conferma di apertura del nuovo ticket
        tgSendMessage($botToken, $teacherChatId, "✅ Messaggio inviato al gruppo di servizio GestOre.\nTicket: {$ticketCode}\nStato richiesta: APERTA.");

        // Scrive nel log l'uscita dalla funzione con ticket nuovo creato
        infoTelegram("EXIT tgHandlePrivateTeacherMessage: nuovo ticket creato idRelay=$idRelay ticket=$ticketCode");
    } catch (Throwable $e) {
        // In caso di errore annulla la transazione DB
        dbExec("ROLLBACK");

        // Scrive nel log il messaggio e lo stack trace dell'eccezione
        errorTelegram("ECCEZIONE tgHandlePrivateTeacherMessage: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());

        // Invia al docente un messaggio di errore generico
        tgSendMessage($botToken, $teacherChatId, "❌ Si è verificato un errore nella registrazione del ticket. Riprova più tardi.");
    }
}

function tgHandleAdminReply($relay, $message, $botToken)
{
    // Scrive nel log l'ingresso nella funzione, salvando relay e messaggio ricevuti
    infoTelegram("tgHandleAdminReply: ENTER relay=" . json_encode($relay) . " message=" . json_encode($message));

    // Estrae il testo del messaggio admin e lo normalizza
    $adminText = tgNorm($message['text'] ?? '');
    if ($adminText === '') {
        $adminText = tgNorm($message['caption'] ?? '');
    }
    $hasMedia = !empty($message['photo']) || !empty($message['document']);

    // Estrae il blocco dati dell'utente che ha scritto
    $adminFrom = $message['from'] ?? [];

    // Estrae e normalizza l'ID Telegram dell'admin
    $adminUserId = tgNorm($adminFrom['id'] ?? '');

    // Costruisce il nome visualizzato dell'admin
    $adminName = tgUserDisplayName($adminFrom);

    // Estrae e normalizza l'ID della chat di gruppo
    $groupChatId = tgNorm($message['chat']['id'] ?? '');

    // Estrae l'ID del messaggio a cui l'admin ha risposto
    $replyToMessageId = (int)($message['reply_to_message']['message_id'] ?? 0);

    // Estrae l'ID del thread del forum Telegram, se presente
    $threadIdFromMessage = (int)($message['message_thread_id'] ?? 0);

    // Logga i dati principali già interpretati dal messaggio admin
    infoTelegram("tgHandleAdminReply: parsed adminText=[" . tgCut($adminText, 300) . "] adminUserId=[$adminUserId] adminName=[$adminName] groupChatId=[$groupChatId] replyToMessageId=[$replyToMessageId] threadIdFromMessage=[$threadIdFromMessage]");

    // Se mancano contenuto, chat o reply al messaggio ticket, interrompe la funzione
    if (($adminText === '' && !$hasMedia) || $groupChatId === '' || $replyToMessageId <= 0) {
        // Logga il motivo dell'uscita anticipata
        warningTelegram("tgHandleAdminReply: dati insufficienti, uscita. adminTextVuoto=" . ($adminText === '' ? '1' : '0') . " hasMedia=" . ($hasMedia ? '1' : '0') . " groupChatIdVuoto=" . ($groupChatId === '' ? '1' : '0') . " replyToMessageId=$replyToMessageId");
        // Esce senza fare altro
        return;
    }

    // Estrae l'ID interno del relay/ticket
    $idRelay = (int)($relay['id'] ?? 0);

    // Estrae e normalizza la chat Telegram del docente
    $teacherChatId = tgNorm($relay['docente_chat_id'] ?? '');
    $emailRiferimento = function_exists('ticketMailResolveRelayRecipientEmail')
        ? ticketMailResolveRelayRecipientEmail($relay)
        : trim((string)($relay['email_riferimento'] ?? ''));
    $mailOrigin = function_exists('ticketMailRelayIsMailOrigin') && ticketMailRelayIsMailOrigin($relay);
    $telegramChannelActive = ($teacherChatId !== '');
    $mailChannelActive = $mailOrigin && $emailRiferimento !== '';
    $relayUserLabel = function_exists('ticketMailRelayUserLabel') ? ticketMailRelayUserLabel($relay) : 'docente';
    $relayUserTargetLabel = 'al docente';
    if ($relayUserLabel === 'studente') {
        $relayUserTargetLabel = 'allo studente';
    } elseif ($relayUserLabel === 'genitore') {
        $relayUserTargetLabel = 'al genitore';
    }

    // Estrae e normalizza lo stato corrente del ticket
    $currentStatus = strtoupper(tgNorm($relay['stato'] ?? 'APERTA'));

    // Estrae e normalizza il codice ticket
    $ticketCode = tgNorm($relay['ticket_code'] ?? '');

    // Estrae l'ID del thread di servizio
    $serviceThreadId = (int)($relay['service_thread_id'] ?? 0);

    // Estrae e normalizza la chat del gruppo di servizio
    $serviceChatId = tgNorm($relay['service_chat_id'] ?? '');

    // Estrae l'ID del messaggio principale di servizio
    $serviceMessageId = (int)($relay['service_message_id'] ?? 0);

    // Logga tutti i campi importanti letti dal relay
    infoTelegram("tgHandleAdminReply: relay fields idRelay=$idRelay teacherChatId=[$teacherChatId] currentStatus=[$currentStatus] ticketCode=[$ticketCode] serviceThreadId=$serviceThreadId serviceChatId=[$serviceChatId] serviceMessageId=$serviceMessageId");

    // Se il ticket non ha ancora un codice, prova a generarlo
    if ($ticketCode === '') {
        // Logga che sta per rigenerare il ticket code
        infoTelegram("tgHandleAdminReply: ticketCode mancante, rigenerazione per idRelay=$idRelay");
        // Genera e salva il codice ticket
        $ticketCode = tgUpdateTicketCode($idRelay);
        // Logga il ticket code ottenuto
        infoTelegram("tgHandleAdminReply: ticketCode dopo update=[$ticketCode]");
    }

    // Se il relay non è valido o non esiste alcun canale di risposta, interrompe
    if ($idRelay <= 0 || (!$telegramChannelActive && !$mailChannelActive)) {
        // Logga il problema
        warningTelegram("tgHandleAdminReply: relay non valido, uscita. idRelay=$idRelay teacherChatId=[$teacherChatId] emailRiferimento=[$emailRiferimento] mailOrigin=" . ($mailOrigin ? '1' : '0'));
        // Esce
        return;
    }

    // Converte il testo admin in minuscolo per riconoscere i comandi
    $lower = mb_strtolower($adminText, 'UTF-8');

    $ownerUserId = tgNorm($relay['preso_in_carico_da'] ?? '');
    $ownerName = tgNorm($relay['preso_in_carico_nome'] ?? '');
    $ownedByOtherAdmin = (
        $currentStatus === 'IN_GESTIONE' &&
        $ownerUserId !== '' &&
        $adminUserId !== '' &&
        $ownerUserId !== $adminUserId
    );

    // Logga il comando normalizzato
    infoTelegram("tgHandleAdminReply: comando normalizzato lower=[$lower]");

    if ($ownedByOtherAdmin && !in_array($lower, ['/stato', '/override'], true)) {
        $ownerSuffix = $ownerName !== '' ? " da {$ownerName}" : '';
        $warningText = "⚠️ {$ticketCode} è già in gestione{$ownerSuffix}. Usa Override solo se devi subentrare davvero.";
        $warningRes = tgSendMessage($botToken, $groupChatId, $warningText, [
            'reply_to_message_id' => $replyToMessageId,
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relay), JSON_UNESCAPED_UNICODE)
        ]);
        infoTelegram("tgHandleAdminReply: blocco ticket in gestione da altro admin result=" . json_encode($warningRes));
        return;
    }

    // -------------------------------------------------
    // COMANDI RAPIDI
    // -------------------------------------------------

    // Se l'admin ha scritto /presa oppure /in_gestione
    if (in_array($lower, ['/presa', '/in_gestione'], true)) {
        // Logga il comando
        infoTelegram("tgHandleAdminReply: comando presa/in_gestione su idRelay=$idRelay");

        // Aggiorna lo stato del ticket a IN_GESTIONE
        $okUpdate = tgUpdateRelayStatus($idRelay, 'IN_GESTIONE', $adminUserId, $adminName);

        // Logga l'esito dell'update
        infoTelegram("tgHandleAdminReply: risultato tgUpdateRelayStatus(IN_GESTIONE)=" . json_encode($okUpdate));

        // Rilegge il relay aggiornato dal database
        $relayAggiornato = tgFindRelayById($idRelay);

        // Logga il relay aggiornato
        infoTelegram("tgHandleAdminReply: relay aggiornato dopo presa=" . json_encode($relayAggiornato));

        // Invia messaggio nel gruppo per confermare la presa in carico
        $resGroup = tgSendMessage($botToken, $groupChatId, "🟡 {$ticketCode} presa in carico da {$adminName}.", [
            'reply_to_message_id' => $replyToMessageId,
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)
        ]);

        tgEditMessage(
            $botToken,
            $relayAggiornato['service_chat_id'],
            (int)$resGroup['message_id'], // Usa il messageId del messaggio appena inviato nel gruppo per rispondere al thread corretto
            "🟡 {$ticketCode} presa in carico da {$adminName}.",
            [
                'reply_markup' => json_encode(
                    tgGetTicketKeyboardMinimal($relayAggiornato),
                    JSON_UNESCAPED_UNICODE
                )
            ]
        );

        // Logga l'esito del messaggio nel gruppo
        infoTelegram("tgHandleAdminReply: send gruppo presa result=" . json_encode($resGroup));

        $resTeacher = ['ok' => false, 'skipped' => true];
        if ($telegramChannelActive) {
            $resTeacher = tgSendMessage($botToken, $teacherChatId, "🟡 La tua richiesta {$ticketCode} è stata presa in carico da {$adminName}.");
            infoTelegram("tgHandleAdminReply: send docente presa result=" . json_encode($resTeacher));
        }

        if ($mailChannelActive) {
            $mailRes = ticketMailSendRelayNotification(
                $relayAggiornato ?: $relay,
                "Presa in carico ticket {$ticketCode}",
                "La tua richiesta {$ticketCode} è stata presa in carico da {$adminName}."
            );
            infoTelegram("tgHandleAdminReply: send mail presa result=" . json_encode($mailRes));
        }

        // Se ci sono dati sufficienti per aggiornare il messaggio principale del ticket
        if ($serviceChatId !== '' && $serviceMessageId > 0 && $relayAggiornato) {
            // Aggiorna la tastiera del messaggio principale
            $editRes = tgEditMessageReplyMarkup($botToken, $serviceChatId, $serviceMessageId, ['reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)]);
            // Logga l'esito dell'edit
            infoTelegram("tgHandleAdminReply: editMessage dopo presa result=" . json_encode($editRes));
        } else {
            // Logga che l'edit è stato saltato per dati mancanti
            warningTelegram("tgHandleAdminReply: salto editMessage dopo presa per dati mancanti serviceChatId=[$serviceChatId] serviceMessageId=$serviceMessageId");
        }

        // Log finale della variazione di stato
        infoTelegram("tgHandleAdminReply: relay $idRelay -> IN_GESTIONE da {$adminName}");

        // Esce dalla funzione
        return;
    }

    // Se l'admin ha scritto /chiudi
    if ($lower === '/chiudi') {
        // Logga il comando
        infoTelegram("tgHandleAdminReply: comando chiudi su idRelay=$idRelay");

        // Aggiorna lo stato a CHIUSA
        $okUpdate = tgUpdateRelayStatus($idRelay, 'CHIUSA', $adminUserId, $adminName);

        // Logga l'esito dell'update
        infoTelegram("tgHandleAdminReply: risultato tgUpdateRelayStatus(CHIUSA)=" . json_encode($okUpdate));

        // Ricarica il relay aggiornato
        $relayAggiornato = tgFindRelayById($idRelay);

        // Logga il relay aggiornato
        infoTelegram("tgHandleAdminReply: relay aggiornato dopo chiusura=" . json_encode($relayAggiornato));

        // Invia messaggio nel gruppo per confermare la chiusura
        $resGroup = tgSendMessage($botToken, $groupChatId, "✅ {$ticketCode} chiusa da {$adminName}.", [
            'reply_to_message_id' => $replyToMessageId,
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)
        ]);

        tgEditMessage(
            $botToken,
            $relayAggiornato['service_chat_id'],
            (int)$resGroup['message_id'], // Usa il messageId del messaggio appena inviato nel gruppo per rispondere al thread corretto
            "✅ {$ticketCode} chiusa da {$adminName}.",
            [
                'reply_markup' => json_encode(
                    tgGetTicketKeyboardMinimal($relayAggiornato),
                    JSON_UNESCAPED_UNICODE
                )
            ]
        );
        // Logga l'esito del messaggio nel gruppo
        infoTelegram("tgHandleAdminReply: send gruppo chiudi result=" . json_encode($resGroup));

        $resTeacher = ['ok' => false, 'skipped' => true];
        if ($telegramChannelActive) {
            $resTeacher = tgSendMessage($botToken, $teacherChatId, "✅ La tua richiesta {$ticketCode} al servizio GestOre è stata chiusa.");
            infoTelegram("tgHandleAdminReply: send docente chiudi result=" . json_encode($resTeacher));
        }

        if ($mailChannelActive) {
            $mailRes = ticketMailSendRelayNotification(
                $relayAggiornato ?: $relay,
                "Chiusura ticket {$ticketCode}",
                "La tua richiesta {$ticketCode} al servizio GestOre è stata chiusa."
            );
            infoTelegram("tgHandleAdminReply: send mail chiudi result=" . json_encode($mailRes));
        }

        // Se possibile, aggiorna il messaggio principale del ticket
        if ($serviceChatId !== '' && $serviceMessageId > 0 && $relayAggiornato) {
            // Esegue l'edit del messaggio principale
            $editRes = tgEditMessageReplyMarkup($botToken, $serviceChatId, $serviceMessageId, ['reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)]);
            // Logga l'edit
            infoTelegram("tgHandleAdminReply: editMessage dopo chiusura result=" . json_encode($editRes));
        } else {
            // Logga che l'edit non è stato possibile
            warningTelegram("tgHandleAdminReply: salto editMessage dopo chiusura per dati mancanti serviceChatId=[$serviceChatId] serviceMessageId=$serviceMessageId");
        }

        // Log finale
        infoTelegram("tgHandleAdminReply: relay $idRelay -> CHIUSA da {$adminName}");

        // Esce
        return;
    }

    // Se l'admin ha scritto /riapri
    if ($lower === '/riapri') {
        // Logga il comando
        infoTelegram("tgHandleAdminReply: comando riapri su idRelay=$idRelay");

        // Aggiorna lo stato a APERTA
        $okUpdate = tgUpdateRelayStatus($idRelay, 'APERTA', $adminUserId, $adminName);

        // Logga l'esito dell'update
        infoTelegram("tgHandleAdminReply: risultato tgUpdateRelayStatus(APERTA)=" . json_encode($okUpdate));

        // Rilegge il relay aggiornato
        $relayAggiornato = tgFindRelayById($idRelay);

        // Logga il relay aggiornato
        infoTelegram("tgHandleAdminReply: relay aggiornato dopo riapertura=" . json_encode($relayAggiornato));

        // Invia conferma nel gruppo
        $resGroup = tgSendMessage($botToken, $groupChatId, "🔵 {$ticketCode} riaperta da {$adminName}.", [
            'reply_to_message_id' => $replyToMessageId,
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)
        ]);

        tgEditMessage(
            $botToken,
            $relayAggiornato['service_chat_id'],
            (int)$resGroup['message_id'], // Usa il messageId del messaggio appena inviato nel gruppo per rispondere al thread corretto
            "🔵 {$ticketCode} riaperta da {$adminName}.",
            [
                'reply_markup' => json_encode(
                    tgGetTicketKeyboardMinimal($relayAggiornato),
                    JSON_UNESCAPED_UNICODE
                )
            ]
        );
        // Logga l'invio nel gruppo
        infoTelegram("tgHandleAdminReply: send gruppo riapri result=" . json_encode($resGroup));

        $resTeacher = ['ok' => false, 'skipped' => true];
        if ($telegramChannelActive) {
            $resTeacher = tgSendMessage($botToken, $teacherChatId, "🔵 La tua richiesta {$ticketCode} è stata riaperta.");
            infoTelegram("tgHandleAdminReply: send docente riapri result=" . json_encode($resTeacher));
        }

        if ($mailChannelActive) {
            $mailRes = ticketMailSendRelayNotification(
                $relayAggiornato ?: $relay,
                "Riapertura ticket {$ticketCode}",
                "La tua richiesta {$ticketCode} è stata riaperta."
            );
            infoTelegram("tgHandleAdminReply: send mail riapri result=" . json_encode($mailRes));
        }

        // Se possibile aggiorna il messaggio principale
        if ($serviceChatId !== '' && $serviceMessageId > 0 && $relayAggiornato) {
            // Edit del messaggio principale
            $editRes = tgEditMessageReplyMarkup($botToken, $serviceChatId, $serviceMessageId, ['reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)]);
            // Log dell'edit
            infoTelegram("tgHandleAdminReply: editMessage dopo riapertura result=" . json_encode($editRes));
        } else {
            // Logga il salto edit
            warningTelegram("tgHandleAdminReply: salto editMessage dopo riapertura per dati mancanti serviceChatId=[$serviceChatId] serviceMessageId=$serviceMessageId");
        }

        // Log finale
        infoTelegram("tgHandleAdminReply: relay $idRelay -> APERTA da {$adminName}");

        // Esce
        return;
    }

    // Se l'admin ha scritto /stato
    if ($lower === '/stato') {
        // Logga il comando
        infoTelegram("tgHandleAdminReply: comando stato su idRelay=$idRelay");

        // Costruisce l'etichetta leggibile dello stato
        $statusLabel = tgBuildStatoLabel($currentStatus);

        // Recupera il nome di chi ha preso in carico il ticket
        $owner = tgNorm($relay['preso_in_carico_nome'] ?? '');

        // Costruisce la riga opzionale con il nome dell'owner
        $ownerText = $owner !== '' ? "\n👤 In gestione da: {$owner}" : '';

        // Invia nel gruppo il riepilogo dello stato del ticket
        $resGroup = tgSendMessage($botToken, $groupChatId, "📌 {$ticketCode}\nStato richiesta: {$statusLabel}{$ownerText}", [
            'reply_to_message_id' => $replyToMessageId,
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relay), JSON_UNESCAPED_UNICODE)
        ]);

        tgEditMessage(
            $botToken,
            $relay['service_chat_id'],
            (int)$resGroup['message_id'], // Usa il messageId del messaggio appena inviato nel gruppo per rispondere al thread corretto
            "📌 {$ticketCode}\nStato richiesta: {$statusLabel}{$ownerText}",
            [
                'reply_markup' => json_encode(
                    tgGetTicketKeyboardMinimal($relay),
                    JSON_UNESCAPED_UNICODE
                )
            ]
        );
        // Logga l'esito del messaggio
        infoTelegram("tgHandleAdminReply: send gruppo stato result=" . json_encode($resGroup));

        // Esce
        return;
    }

    if ($lower === '/override') {
        infoTelegram("tgHandleAdminReply: comando override su idRelay=$idRelay");

        $okUpdate = tgUpdateRelayStatus($idRelay, 'IN_GESTIONE', $adminUserId, $adminName);
        infoTelegram("tgHandleAdminReply: risultato tgUpdateRelayStatus(OVERRIDE->IN_GESTIONE)=" . json_encode($okUpdate));

        $relayAggiornato = tgFindRelayById($idRelay);
        infoTelegram("tgHandleAdminReply: relay aggiornato dopo override=" . json_encode($relayAggiornato));

        $ownerSuffix = $ownerName !== '' ? " (prima: {$ownerName})" : '';
        $resGroup = tgSendMessage($botToken, $groupChatId, "🔁 {$ticketCode} presa in carico da {$adminName}{$ownerSuffix}.", [
            'reply_to_message_id' => $replyToMessageId,
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)
        ]);
        infoTelegram("tgHandleAdminReply: send gruppo override result=" . json_encode($resGroup));

        if ($serviceChatId !== '' && $serviceMessageId > 0 && $relayAggiornato) {
            $editRes = tgEditMessageReplyMarkup($botToken, $serviceChatId, $serviceMessageId, ['reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)]);
            infoTelegram("tgHandleAdminReply: editMessage dopo override result=" . json_encode($editRes));
        }

        return;
    }

    // -------------------------------------------------
    // RISPOSTA TESTUALE NORMALE
    // -------------------------------------------------

    if ($hasMedia) {
        infoTelegram("tgHandleAdminReply: gestione allegato admin relay=$idRelay");

        if ($currentStatus === 'CHIUSA') {
            $resGroup = tgSendMessage($botToken, $groupChatId, "⚠️ {$ticketCode} risulta chiusa. Usa /riapri in reply per riaprirla prima di inviare allegati.", ['reply_to_message_id' => $replyToMessageId]);
            infoTelegram("tgHandleAdminReply: send gruppo ticket chiuso con media result=" . json_encode($resGroup));
            return;
        }

        $attachmentsForMail = function_exists('ticketMailExtractTelegramAttachmentFromMessage')
            ? ticketMailExtractTelegramAttachmentFromMessage($message, $botToken)
            : [];

        $copyRes = ['ok' => false, 'skipped' => true];
        if ($telegramChannelActive) {
            $copyRes = tgCopyMessage(
                $botToken,
                $teacherChatId,
                $groupChatId,
                (int)($message['message_id'] ?? 0)
            );
            infoTelegram("tgHandleAdminReply: copy media to teacher result=" . json_encode($copyRes));
        }

        $mailRes = ['ok' => false, 'skipped' => true];
        if ($mailChannelActive) {
            $mailBody = $adminText !== '' ? $adminText : 'E stato inviato un allegato relativo al ticket.';
            $mailRes = ticketMailSendRelayNotification(
                $relay,
                "Allegato ticket {$ticketCode}",
                $mailBody,
                array_map(function ($item) {
                    return $item['path'] ?? '';
                }, $attachmentsForMail)
            );
            infoTelegram("tgHandleAdminReply: send mail media result=" . json_encode($mailRes));
        }

        foreach ($attachmentsForMail as $attachment) {
            @unlink((string)($attachment['path'] ?? ''));
        }

        $hardFail = (!$telegramChannelActive && !$mailChannelActive)
            || ($mailChannelActive && empty($mailRes['ok']))
            || (!$mailChannelActive && $telegramChannelActive && empty($copyRes['ok']));

        if ($hardFail) {
            $resGroup = tgSendMessage($botToken, $groupChatId, "❌ Errore nell'invio dell'allegato al docente.", ['reply_to_message_id' => $replyToMessageId]);
            infoTelegram("tgHandleAdminReply: send gruppo errore allegato result=" . json_encode($resGroup));
            return;
        }

        dbExec("START TRANSACTION");
        infoTelegram("tgHandleAdminReply: START TRANSACTION per update allegato admin");

        try {
            $fields = [
                "ultima_risposta_admin = NOW()"
            ];

            if ($adminText !== '') {
                $fields[] = "ultimo_testo_admin = " . dbQ($adminText);
            }

            if ($currentStatus === 'APERTA') {
                $fields[] = "stato = 'IN_GESTIONE'";
                $fields[] = "chiusa = 0";
                $fields[] = "preso_in_carico_da = " . dbQ($adminUserId);
                $fields[] = "preso_in_carico_nome = " . dbQ($adminName);
                $fields[] = "data_presa_in_carico = NOW()";
            }

            $q = "UPDATE docente_telegram_relay SET " . implode(", ", $fields) . " WHERE id = " . dbI($idRelay);
            infoTelegram("tgHandleAdminReply: query update allegato admin=$q");
            dbExec($q);
            dbExec("COMMIT");

            $relayAggiornato = tgFindRelayById($idRelay);
            if ($serviceChatId !== '' && $serviceMessageId > 0 && $relayAggiornato) {
                $editRes = tgEditMessageReplyMarkup($botToken, $serviceChatId, $serviceMessageId, ['reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)]);
                infoTelegram("tgHandleAdminReply: editMessage dopo allegato admin result=" . json_encode($editRes));
            }

            $resGroup = tgSendMessage($botToken, $groupChatId, "✅ Allegato inviato per {$ticketCode} da {$adminName}.", [
                'reply_to_message_id' => $replyToMessageId,
                'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)
            ]);
            infoTelegram("tgHandleAdminReply: send gruppo conferma allegato result=" . json_encode($resGroup));
        } catch (Throwable $e) {
            dbExec("ROLLBACK");
            errorTelegram("tgHandleAdminReply: errore update relay admin media: " . $e->getMessage());
            $resGroup = tgSendMessage($botToken, $groupChatId, "❌ Errore durante l'aggiornamento del ticket {$ticketCode}.", ['reply_to_message_id' => $replyToMessageId]);
            infoTelegram("tgHandleAdminReply: send gruppo errore update media result=" . json_encode($resGroup));
        }

        return;
    }

    // Se il ticket è chiuso, impedisce la risposta normale
    if ($currentStatus === 'CHIUSA') {
        // Logga il blocco
        infoTelegram("tgHandleAdminReply: ticket chiuso, blocco risposta normale idRelay=$idRelay");

        // Invia messaggio nel gruppo per avvisare che va riaperto prima
        $resGroup = tgSendMessage($botToken, $groupChatId, "⚠️ {$ticketCode} risulta chiusa. Usa /riapri in reply per riaprirla prima di rispondere.", ['reply_to_message_id' => $replyToMessageId]);

        // Logga l'esito
        infoTelegram("tgHandleAdminReply: send gruppo ticket chiuso result=" . json_encode($resGroup));

        // Esce
        return;
    }

    // Logga che sta per inviare una risposta normale al docente
    infoTelegram("tgHandleAdminReply: invio risposta normale al docente idRelay=$idRelay");

    $sendRes = ['ok' => false, 'skipped' => true];
    if ($telegramChannelActive) {
        $sendRes = tgSendMessage($botToken, $teacherChatId, "📬 Risposta GestOre - {$ticketCode}\n\n" . $adminText);
        infoTelegram("tgHandleAdminReply: send docente risposta normale result=" . json_encode($sendRes));
    }

    $mailRes = ['ok' => false, 'skipped' => true];
    if ($mailChannelActive) {
        $mailRes = ticketMailSendRelayNotification(
            $relay,
            "Risposta ticket {$ticketCode}",
            "Risposta GestOre - {$ticketCode}\n\n" . $adminText
        );
        infoTelegram("tgHandleAdminReply: send mail risposta normale result=" . json_encode($mailRes));
    }

    $hardFail = (!$telegramChannelActive && !$mailChannelActive)
        || ($mailChannelActive && empty($mailRes['ok']))
        || (!$mailChannelActive && $telegramChannelActive && empty($sendRes['ok']));

    // Se l'invio al docente fallisce
    if ($hardFail) {
        // Logga l'errore
        errorTelegram("tgHandleAdminReply: errore invio reply admin->utente relay=$idRelay errTg=[" . ($sendRes['error'] ?? '') . "] errMail=[" . ($mailRes['error'] ?? '') . "]");

        // Invia un messaggio nel gruppo per avvisare dell'errore
        $resGroup = tgSendMessage($botToken, $groupChatId, "❌ Errore nell'invio della risposta {$relayUserTargetLabel}.", ['reply_to_message_id' => $replyToMessageId]);

        // Logga l'esito del messaggio nel gruppo
        infoTelegram("tgHandleAdminReply: send gruppo errore invio result=" . json_encode($resGroup));

        // Esce
        return;
    }

    // Apre una transazione DB per aggiornare il ticket
    dbExec("START TRANSACTION");

    // Logga l'avvio della transazione
    infoTelegram("tgHandleAdminReply: START TRANSACTION per update risposta admin");

    try {
        // Prepara i campi base da aggiornare nel relay
        $fields = [
            "ultimo_testo_admin = " . dbQ($adminText),
            "ultima_risposta_admin = NOW()"
        ];

        // Se il ticket era ancora APERTA, lo porta in gestione
        if ($currentStatus === 'APERTA') {
            // Aggiorna lo stato a IN_GESTIONE
            $fields[] = "stato = 'IN_GESTIONE'";
            // Segna chiusa=0
            $fields[] = "chiusa = 0";
            // Salva l'ID admin che ha preso in carico
            $fields[] = "preso_in_carico_da = " . dbQ($adminUserId);
            // Salva il nome admin
            $fields[] = "preso_in_carico_nome = " . dbQ($adminName);
            // Salva il timestamp della presa in carico
            $fields[] = "data_presa_in_carico = NOW()";
        }

        // Costruisce la query di update del relay
        $q = "UPDATE docente_telegram_relay SET " . implode(",\n                ", $fields) . " WHERE id = " . dbI($idRelay);

        // Logga la query
        infoTelegram("tgHandleAdminReply: query update risposta admin=$q");

        // Esegue la query
        dbExec($q);

        // Conferma la transazione
        dbExec("COMMIT");

        // Logga il commit
        infoTelegram("tgHandleAdminReply: COMMIT eseguito");

        // Ricarica il relay aggiornato
        $relayAggiornato = tgFindRelayById($idRelay);

        // Logga il relay aggiornato
        infoTelegram("tgHandleAdminReply: relay aggiornato dopo risposta admin=" . json_encode($relayAggiornato));

        // Se possibile aggiorna anche il messaggio principale del ticket
        if ($serviceChatId !== '' && $serviceMessageId > 0 && $relayAggiornato) {
            // Esegue l'edit con tastiera aggiornata
            $editRes = tgEditMessageReplyMarkup($botToken, $serviceChatId, $serviceMessageId, ['reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)]);
            // Logga l'esito dell'edit
            infoTelegram("tgHandleAdminReply: editMessage dopo risposta admin result=" . json_encode($editRes));
        } else {
            // Logga il motivo per cui l'edit non è stato eseguito
            warningTelegram("tgHandleAdminReply: salto editMessage dopo risposta admin per dati mancanti serviceChatId=[$serviceChatId] serviceMessageId=$serviceMessageId");
        }

        // Invia nel gruppo conferma che la risposta è stata inoltrata
        $resGroup = tgSendMessage($botToken, $groupChatId, "✅ Risposta inviata {$relayUserTargetLabel} per {$ticketCode} da {$adminName}.", [
            'reply_to_message_id' => $replyToMessageId,
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relayAggiornato), JSON_UNESCAPED_UNICODE)
        ]);

        // Logga l'esito della conferma nel gruppo
        infoTelegram("tgHandleAdminReply: send gruppo conferma risposta result=" . json_encode($resGroup));

        // Log finale di successo
        infoTelegram("tgHandleAdminReply: risposta admin inoltrata relay=$idRelay admin={$adminName}");
    } catch (Throwable $e) {
        // In caso di errore, annulla la transazione
        dbExec("ROLLBACK");

        // Logga l'errore
        errorTelegram("tgHandleAdminReply: ROLLBACK per errore update relay admin reply: " . $e->getMessage());

        // Invia messaggio nel gruppo per segnalare l'errore
        $resGroup = tgSendMessage($botToken, $groupChatId, "❌ Errore durante l'aggiornamento del ticket {$ticketCode}.", ['reply_to_message_id' => $replyToMessageId]);

        // Logga l'esito del messaggio di errore nel gruppo
        infoTelegram("tgHandleAdminReply: send gruppo errore update result=" . json_encode($resGroup));
    }
}

// Se il token del bot Telegram è vuoto
if ($TELEGRAM_BOT_TOKEN === '') {
    // Scrive nel log un errore che segnala l'assenza del bot token
    errorTelegram("telegramWebhook: bot token mancante");

    // Restituisce subito una risposta JSON di errore HTTP 500
    tgRespond(['ok' => false, 'error' => 'Bot token mancante'], 500);
}

// Se manca l'ID della chat di servizio/gruppo admin
if ($TELEGRAM_SERVICE_CHAT_ID === '') {
    // Scrive nel log l'errore relativo al service chat id mancante
    errorTelegram("telegramWebhook: service chat id mancante");

    // Restituisce subito una risposta JSON di errore HTTP 500
    tgRespond(['ok' => false, 'error' => 'Service chat id mancante'], 500);
}

// Decodifica il JSON grezzo ricevuto da Telegram in array associativo
$update = json_decode($raw, true);

// Se il JSON non è valido o non produce un array
if (!is_array($update)) {
    // Scrive nel log che il payload JSON non è valido
    warningTelegram("telegramWebhook: JSON non valido");

    // Restituisce risposta JSON dicendo che l'update viene ignorato
    tgRespond(['ok' => true, 'ignored' => 'json non valido']);
}

/**
 * CALLBACK BUTTONS
 */

// Estrae l'eventuale callback_query dall'update Telegram
$callback = $update['callback_query'] ?? null;

infoTelegram("telegramWebhook: callback_query=" . json_encode($callback));

// Se l'update contiene un callback di un pulsante inline
if ($callback) {
    $data = tgNorm($callback['data'] ?? '');
    $from = $callback['from'] ?? [];
    $callbackMessage = $callback['message'] ?? [];

    $chatId = tgNorm($callbackMessage['chat']['id'] ?? '');
    $messageId = (int)($callbackMessage['message_id'] ?? 0);
    $threadId = (int)($callbackMessage['message_thread_id'] ?? 0);

    infoTelegram("telegramWebhook: callback data=[$data] chatId=[$chatId] messageId=$messageId threadId=$threadId");

    if ($chatId === '' || $messageId <= 0) {
        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Messaggio non valido');
        tgRespond(['ok' => true]);
    }

    // Pulsanti lista: lasciali come sono
    if ($data === 'lista_aperte') {
        $summary = tgBuildOpenTicketsSummary();
        $extra = [];
        if (!empty($summary['keyboard'])) {
            $extra['reply_markup'] = json_encode($summary['keyboard']);
        }

        tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, $summary['text'], $extra);
        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Elenco aggiornato');
        tgRespond(['ok' => true, 'handled' => 'callback_lista_aperte']);
    }

    if ($data === 'lista_miei_ticket') {
        $adminName = tgUserDisplayName($from);
        $adminUserId = tgNorm($from['id'] ?? '');
        $summary = tgBuildMyWorkingTicketsSummary($adminUserId, $adminName);

        tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, $summary);
        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Elenco aggiornato');
        tgRespond(['ok' => true, 'handled' => 'callback_lista_miei_ticket']);
    }

    if ($data === 'lista_lavorazione') {
        $summary = tgBuildWorkingTicketsSummary();

        tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, $summary);
        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Elenco aggiornato');
        tgRespond(['ok' => true, 'handled' => 'callback_lista_lavorazione']);
    }

    if ($data === 'lista_chiusi_oggi') {
        $summary = tgBuildClosedTodayTicketsSummary();

        tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, $summary);
        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Elenco aggiornato');
        tgRespond(['ok' => true, 'handled' => 'callback_lista_chiusi_oggi']);
    }

    // Gestione pulsanti ticket: presa_relay_27 / chiudi_relay_27 / riapri_relay_27 / stato_relay_27 / override_relay_27 / nuovo_relay_27
    if (preg_match('/^(presa|chiudi|riapri|stato|override|nuovo)_relay_(\d+)$/', $data, $m)) {
        $action = $m[1];
        $idRelay = (int)$m[2];

        infoTelegram("telegramWebhook: callback action=$action idRelay=$idRelay");

        $relay = tgFindRelayById($idRelay);

        // fallback sul thread se serve
        if (!$relay && $threadId > 0) {
            $relay = tgFindRelayByServiceThread($chatId, $threadId);
        }

        if (!$relay) {
            tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Ticket non trovato');
            tgRespond(['ok' => true, 'ignored' => 'relay non trovato']);
        }

        if ($action === 'nuovo') {
            $serviceChatId = tgNorm($relay['service_chat_id'] ?? $chatId);
            $resNew = tgCreateNewTicketFromClosedRelay($relay, $serviceChatId, $TELEGRAM_BOT_TOKEN);
            if (empty($resNew['ok'])) {
                tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Errore apertura nuovo ticket');
                tgSendMessage(
                    $TELEGRAM_BOT_TOKEN,
                    $chatId,
                    "❌ Non sono riuscito ad aprire un nuovo ticket: " . tgCut((string)($resNew['error'] ?? 'errore sconosciuto'), 500),
                    ['reply_to_message_id' => $messageId]
                );
                tgRespond(['ok' => true, 'handled' => 'callback_nuovo_ticket_error']);
            }

            $newTicketCode = tgNorm($resNew['ticket_code'] ?? '');
            $oldTicketCode = tgNorm($relay['ticket_code'] ?? '');
            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "🆕 Creato nuovo ticket {$newTicketCode}" . ($oldTicketCode !== '' ? " dal messaggio arrivato su {$oldTicketCode}." : "."),
                ['reply_to_message_id' => $messageId]
            );
            tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Nuovo ticket creato');
            tgRespond(['ok' => true, 'handled' => 'callback_nuovo_ticket']);
        }

        // Mappa azione callback -> comando testuale che già gestisce tgHandleAdminReply
        $commandMap = [
            'presa'  => '/presa',
            'chiudi' => '/chiudi',
            'riapri' => '/riapri',
            'stato'  => '/stato',
            'override' => '/override'
        ];

        $commandText = $commandMap[$action] ?? '';

        if ($commandText === '') {
            tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Azione non valida');
            tgRespond(['ok' => true, 'ignored' => 'azione non valida']);
        }

        $serviceMessageId = (int)($relay['service_message_id'] ?? 0);
        $serviceThreadId  = (int)($relay['service_thread_id'] ?? 0);
        $serviceChatId    = tgNorm($relay['service_chat_id'] ?? '');

        if ($serviceChatId === '' || $serviceMessageId <= 0 || $serviceThreadId <= 0) {
            tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Dati ticket incompleti');
            tgRespond(['ok' => true, 'ignored' => 'dati ticket incompleti']);
        }
        // Costruisce un messaggio "finto" compatibile con tgHandleAdminReply()
        $fakeMessage = [
            'text' => $commandText,
            'from' => $from,
            'chat' => [
                'id' => $serviceChatId,
                'type' => 'supergroup'
            ],
            'message_id' => $serviceMessageId,
            'message_thread_id' => $serviceThreadId,
            'reply_to_message' => [
                'message_id' => $serviceMessageId
            ]
        ];

        infoTelegram("telegramWebhook: inoltro callback a tgHandleAdminReply command=[$commandText] idRelay=" . (int)($relay['id'] ?? 0));

        tgHandleAdminReply($relay, $fakeMessage, $TELEGRAM_BOT_TOKEN);

        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Operazione eseguita');
        tgRespond(['ok' => true, 'handled' => 'callback_relay_action']);
    }

    tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Azione non gestita');
    tgRespond(['ok' => true, 'ignored' => 'callback non gestito']);
}

// Estrae il nodo message dall'update
$message = $update['message'] ?? null;

// Se non esiste un messaggio valido
if (!$message || !is_array($message)) {
    // Termina ignorando l'update
    tgRespond(['ok' => true, 'ignored' => 'nessun message']);
}

// Estrae i dati chat dal messaggio
$chat = $message['chat'] ?? [];

// Estrae e normalizza l'id chat
$chatId = tgNorm($chat['id'] ?? '');

// Estrae e normalizza il tipo chat
$chatType = tgNorm($chat['type'] ?? '');

// Estrae e normalizza il testo del messaggio
$text = tgNorm($message['text'] ?? '');

// Converte il testo in minuscolo UTF-8 per confronto comandi
$lowerText = mb_strtolower($text, 'UTF-8');

// Se manca il chatId
if ($chatId === '') {
    // Ignora l'update
    tgRespond(['ok' => true, 'ignored' => 'chatId mancante']);
}

// Estrae i dati dell'utente che ha inviato il messaggio
$from = $message['from'] ?? [];

// Costruisce nome leggibile mittente
$fromName = tgUserDisplayName($from);

// Estrae e normalizza l'id Telegram del mittente
$fromUserId = tgNorm($from['id'] ?? '');

// Estrae e normalizza lo username Telegram del mittente
$fromUsername = tgNorm($from['username'] ?? '');

// Scrive nel log un riepilogo dell'update ricevuto
infoTelegram("telegramWebhook: update ricevuto chatId=[$chatId] chatType=[$chatType] from=[$fromName] text=[" . tgCut($text, 200) . "]");

/**
 * GRUPPO ADMIN
 */

// Se il messaggio arriva dalla chat gruppo admin
if ($chatId === $TELEGRAM_SERVICE_CHAT_ID) {

    $groupThreadId = (int)($message['message_thread_id'] ?? 0);
    $isTopicMessage = !empty($message['is_topic_message']);
    $replyTo = $message['reply_to_message'] ?? null;
    $replyToMessageId = (int)($replyTo['message_id'] ?? 0);

    // /dashboard consentito SOLO nel General, non nei topic ticket
    if ($lowerText === '/dashboard') {

        // Se è dentro un topic/forum ticket, blocca
        if ($isTopicMessage || $replyToMessageId > 0) {
            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "⚠️ Il comando /dashboard può essere usato solo nel tab General.",
                [
                    'message_thread_id' => $groupThreadId,
                    'reply_to_message_id' => (int)($message['message_id'] ?? 0)
                ]
            );
            tgRespond(['ok' => true, 'handled' => 'dashboard_denied_not_general']);
        }

        $res = tgSendGeneralDashboard($TELEGRAM_BOT_TOKEN, $TELEGRAM_SERVICE_CHAT_ID);

        infoTelegram("telegramWebhook: /dashboard result=" . json_encode($res));

        if (!empty($res['ok'])) {
            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "✅ Dashboard pubblicata nel topic General."
            );
        } else {
            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "❌ Errore nella pubblicazione della dashboard."
            );
        }

        tgRespond(['ok' => true, 'handled' => 'dashboard_create']);
    }

    // Estrae i dati del mittente nel gruppo
    $groupFrom = $message['from'] ?? [];

    // Estrae e normalizza l'id admin
    $groupAdminUserId = tgNorm($groupFrom['id'] ?? '');

    // Costruisce il nome visualizzato dell'admin
    $groupAdminName = tgUserDisplayName($groupFrom);

    // Estrae e normalizza lo username admin
    $groupAdminUsername = tgNorm($groupFrom['username'] ?? '');

    // Se esiste un id admin valido
    if ($groupAdminUserId !== '') {
        // Cerca l'admin già registrato
        $existingAdmin = tgFindAdminTelegramByUserId($groupAdminUserId);

        // Se l'admin è già noto
        if ($existingAdmin) {
            // Aggiorna i dati anagrafici/chat privata dell'admin
            tgUpsertAdminTelegram(
                $groupAdminUserId,
                tgNorm($existingAdmin['telegram_chat_id'] ?? ''),
                $groupAdminName,
                $groupAdminUsername
            );
        }
    }

    // Se il comando è /notify_sost_on
    if ($lowerText === '/notify_sost_on') {
        // Cerca l'admin
        $existingAdmin = tgFindAdminTelegramByUserId($groupAdminUserId);

        // Se non esiste o non ha ancora registrato la chat privata
        if (!$existingAdmin || tgNorm($existingAdmin['telegram_chat_id'] ?? '') === '') {
            // Invia istruzioni nel gruppo
            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "⚠️ {$groupAdminName}, prima scrivi /admin al bot in chat privata e poi ripeti /notify_sost_on qui nel gruppo."
            );
            // Termina segnando il comando come gestito
            tgRespond(['ok' => true, 'handled' => 'notify_sost_on_missing_private_chat']);
        }

        // Abilita le notifiche sostituzioni per questo admin
        tgSetAdminSostituzioniNotify($groupAdminUserId, 1);

        // Invia conferma nel gruppo
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            "✅ {$groupAdminName} ha abilitato le notifiche Telegram delle sostituzioni in chat privata."
        );
        // Termina
        tgRespond(['ok' => true, 'handled' => 'notify_sost_on']);
    }

    // Se il comando è /notify_sost_off
    if ($lowerText === '/notify_sost_off') {
        // Cerca l'admin
        $existingAdmin = tgFindAdminTelegramByUserId($groupAdminUserId);

        // Se esiste
        if ($existingAdmin) {
            // Disabilita le notifiche sostituzioni
            tgSetAdminSostituzioniNotify($groupAdminUserId, 0);
        }

        // Invia conferma nel gruppo
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            "✅ {$groupAdminName} ha disabilitato le notifiche Telegram delle sostituzioni."
        );
        // Termina
        tgRespond(['ok' => true, 'handled' => 'notify_sost_off']);
    }

    // Se il comando è /notify_sost_stato
    if ($lowerText === '/notify_sost_stato') {
        // Legge lo stato notifiche dell'admin
        $status = tgGetAdminSostituzioniNotifyStatus($groupAdminUserId);

        // Se l'admin non risulta registrato
        if ($status === null) {
            // Invia messaggio informativo
            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "ℹ️ {$groupAdminName}, non risulti ancora registrato. Scrivi prima /admin in chat privata al bot."
            );
            // Termina
            tgRespond(['ok' => true, 'handled' => 'notify_sost_stato_unknown']);
        }

        // Invia nel gruppo stato attivo/disattivo delle notifiche
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            $status ? "🟢 {$groupAdminName}: notifiche sostituzioni ATTIVE." : "🔴 {$groupAdminName}: notifiche sostituzioni DISATTIVE."
        );
        // Termina
        tgRespond(['ok' => true, 'handled' => 'notify_sost_stato']);
    }

    // Estrae il messaggio a cui l'admin ha eventualmente risposto
    $replyTo = $message['reply_to_message'] ?? null;

    // Estrae l'id del messaggio reply
    $replyToMessageId = (int)($replyTo['message_id'] ?? 0);

    // Inizializza relay a null
    $relay = null;

    // Se il messaggio admin è una reply a un messaggio valido
    if ($replyToMessageId > 0) {
        // Cerca il relay partendo dal message id del gruppo
        $relay = tgFindRelayByServiceMessage($TELEGRAM_SERVICE_CHAT_ID, $replyToMessageId);
    }

    // Estrae l'eventuale thread id del messaggio gruppo
    $threadId = (int)($message['message_thread_id'] ?? 0);

    // Se il relay non è stato trovato e c'è un thread id
    if (!$relay && $threadId > 0) {
        // Costruisce query SQL di fallback sul thread
        $q = "
        SELECT *
        FROM docente_telegram_relay
        WHERE service_thread_id = " . dbI($threadId) . "
        ORDER BY id DESC
        LIMIT 1
    ";
        // Esegue la query di fallback
        $relay = dbGetFirst($q);

        // Logga l'esito del fallback
        infoTelegram("telegramWebhook: fallback relay dal threadId=$threadId trovato=" . json_encode($relay));
    }

    // Se non è stato trovato alcun relay
    if (!$relay) {
        // Ignora il messaggio
        tgRespond(['ok' => true, 'ignored' => 'nessun relay trovato']);
    }

    // Passa la gestione completa del messaggio admin alla funzione dedicata
    tgHandleAdminReply($relay, $message, $TELEGRAM_BOT_TOKEN);

    // Termina segnando il messaggio di gruppo come gestito
    tgRespond(['ok' => true, 'handled' => 'group_reply']);
}

/**
 * CHAT PRIVATE
 */

// Se non è una chat privata
if ($chatType !== 'private') {
    // Ignora la chat non gestita
    tgRespond(['ok' => true, 'ignored' => 'chat non gestita']);
}

/**
 * /start TOKEN
 */

// Se il messaggio corrisponde al comando /start con eventuale token
if (preg_match('/^\/start(?:\s+(.+))?$/u', $text, $m)) {
    // Estrae e normalizza il token
    $token = tgNorm($m[1] ?? '');

    // Gestisce il token e costruisce la risposta
    $reply = tgHandleStartToken($token, $chatId);

    // Invia la risposta all'utente
    $sendRes = tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, $reply);

    // Se l'invio Telegram non è andato a buon fine
    if (!$sendRes['ok']) {
        // Logga l'errore di invio
        errorTelegram("telegramWebhook: errore invio risposta Telegram chatId=[$chatId] err=[" . ($sendRes['error'] ?? '') . "]");
    }

    // Termina segnando /start come gestito
    tgRespond(['ok' => true, 'handled' => 'start']);
}

/**
 * /help
 */

// Se il comando è /help
if ($lowerText === '/help') {
    // Invia il messaggio guida all'utente
    tgSendMessage(
        $TELEGRAM_BOT_TOKEN,
        $chatId,
        "👋 Benvenuto in GestOre Telegram.\n\n" .
            "Comandi disponibili:\n" .
            "/start TOKEN - collega Telegram a GestOre\n" .
            "/help - mostra questa guida\n" .
            "/ticket MESSAGGIO - apre un ticket al servizio GestOre\n" .
            "/admin - registra questa chat privata per le funzioni admin (solo se sei admin del gruppo)\n\n" .
            "Se hai già un ticket aperto, ogni tuo messaggio normale, foto o documento verrà aggiunto automaticamente a quel ticket."
    );
    // Termina segnando /help come gestito
    tgRespond(['ok' => true, 'handled' => 'help']);
}

/**
 * /admin
 * diventa admin SOLO se è admin del gruppo
 */

// Se il comando è /admin e ci sono i dati base necessari
if ($fromUserId !== '' && $chatId !== '' && $lowerText === '/admin') {

    // Controlla tramite API Telegram se l'utente è admin del gruppo
    $isMember = tgIsUserInGroup(
        $TELEGRAM_BOT_TOKEN,
        $TELEGRAM_SERVICE_CHAT_ID,
        $fromUserId
    );

    // Se non è admin del gruppo
    if (!$isMember) {
        // Invia messaggio di diniego
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            "❌ Non sei autorizzato.\nDevi essere amministratore del gruppo admin GestOre."
        );
        // Termina segnando il comando come negato
        tgRespond(['ok' => true, 'handled' => 'admin_denied']);
    }

    // Registra o aggiorna l'admin nella tabella admin_telegram
    tgUpsertAdminTelegram($fromUserId, $chatId, $fromName, $fromUsername);

    // Invia conferma di registrazione admin
    tgSendMessage(
        $TELEGRAM_BOT_TOKEN,
        $chatId,
        "✅ Chat privata registrata per funzioni admin.\n\n" .
            "Ora puoi usare nel gruppo:\n" .
            "/notify_sost_on\n" .
            "/notify_sost_off\n" .
            "/notify_sost_stato"
    );

    // Termina segnando il comando come gestito
    tgRespond(['ok' => true, 'handled' => 'admin_register']);
}

// Cerca il docente associato alla chat privata corrente
$doc = tgFindDocenteByChatId($chatId);

// Cerca il genitore associato alla chat privata corrente
$genitoreTg = tgFindGenitoreByChatId($chatId);

// Cerca l'eventuale admin associato al Telegram user id corrente
$admin = tgFindAdminTelegramByUserId($fromUserId);

$privateActor = null;
$privateActorType = '';
if ($doc) {
    $privateActor = $doc;
    $privateActorType = 'docente';
} elseif ($genitoreTg) {
    $privateActor = $genitoreTg;
    $privateActorType = 'genitore';
}

$openRelay = ($privateActor && function_exists('tgFindOpenRelayByActor'))
    ? tgFindOpenRelayByActor($privateActorType, (int)($privateActor['id'] ?? 0))
    : null;

/**
 * /ticket testo
 * apre ticket anche se il docente è anche admin
 */

// Se il messaggio corrisponde al comando /ticket con eventuale testo
if (preg_match('/^\/ticket(?:\s+(.+))?$/uis', $text, $m)) {
    // Estrae e normalizza il testo del ticket
    $ticketText = tgNorm($m[1] ?? '');

    // Se il testo ticket è vuoto
    if ($ticketText === '') {
        // Invia messaggio di errore con esempio
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            "⚠️ Per aprire un ticket devi scrivere un messaggio dopo il comando.\n\n" .
                "Esempio:\n" .
                "/ticket Non vedo le sostituzioni di oggi"
        );
        // Termina segnando il comando come gestito ma vuoto
        tgRespond(['ok' => true, 'handled' => 'ticket_empty']);
    }

    // Se l'utente non è collegato a un profilo valido
    if (!$privateActor) {
        // Invia messaggio che richiede il collegamento tramite link mail
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            "❌ Il tuo account Telegram non risulta collegato a un profilo GestOre.\nUsa prima il link personale ricevuto via mail."
        );
        // Termina
        tgRespond(['ok' => true, 'handled' => 'ticket_no_actor']);
    }

    infoTelegram("telegramWebhook: chiamata tgHandlePrivateActorMessage actorType=[$privateActorType] chatId=[$chatId] testoTicket=[" . tgCut($ticketText, 200) . "]");

    $openRelay = function_exists('tgFindOpenRelayByActor')
        ? tgFindOpenRelayByActor($privateActorType, (int)($privateActor['id'] ?? 0))
        : null;

    tgHandlePrivateActorMessage(
        $privateActor,
        $privateActorType,
        array_merge($message, ['text' => $ticketText]),
        $TELEGRAM_SERVICE_CHAT_ID,
        $TELEGRAM_BOT_TOKEN
    );

    // Termina segnando la creazione ticket come gestita
    tgRespond(['ok' => true, 'handled' => 'ticket_created']);
}

/**
 * Se il docente ha già un ticket aperto:
 * - testo normale => accoda
 * - foto/documento => accoda allegato
 * Tutti i messaggi aggiornano sempre la keyboard del thread admin.
 */

// Se l'utente ha un profilo collegato e ha un relay aperto
if ($privateActor && $openRelay) {
    // Estrae l'id del relay aperto
    $idRelay = (int)($openRelay['id'] ?? 0);

    // Estrae e normalizza il ticket code
    $ticketCode = tgNorm($openRelay['ticket_code'] ?? '');

    // Se il ticket code manca
    if ($ticketCode === '') {
        // Lo genera e salva
        $ticketCode = tgUpdateTicketCode($idRelay);
    }

    // Estrae o ricava la chat di servizio
    $serviceChatId = tgNorm($openRelay['service_chat_id'] ?? $TELEGRAM_SERVICE_CHAT_ID);

    // Estrae il thread id del relay
    $serviceThreadId = (int)($openRelay['service_thread_id'] ?? 0);

    // Estrae l'id del messaggio del docente
    $teacherMessageId = (int)($message['message_id'] ?? 0);

    // Estrae e normalizza la chat Telegram del docente dal relay aggiornato
    $teacherChatId = tgNorm($openRelay['docente_chat_id'] ?? '');

    // ----- Testo normale -----

    // Se il messaggio contiene testo e non è un comando
    if ($text !== '' && !preg_match('/^\//', $text)) {

        // Aggiorna il relay con ultimo message id e ultimo testo docente
        dbExec("
            UPDATE docente_telegram_relay
            SET docente_message_id = " . dbI($teacherMessageId) . ",
                ultimo_testo_docente = " . dbQ($text) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        // Invia nel thread admin l'aggiornamento testuale del ticket
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $serviceChatId,
            "➕ Aggiornamento ticket {$ticketCode}\n\n👤 " . tgPrivateActorLabel($privateActorType) . ": " . trim(($privateActor['cognome'] ?? '') . ' ' . ($privateActor['nome'] ?? '')) . "\n\n✉️ Nuovo messaggio:\n" . tgCut($text, 3000),
            [
                'message_thread_id' => $serviceThreadId,
                'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($openRelay), JSON_UNESCAPED_UNICODE)
            ]
        );

        // Invia conferma al docente
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $teacherChatId,
            "✅ Il tuo messaggio è stato aggiunto al ticket {$ticketCode}."
        );

        // Termina segnando l'accodamento testo come gestito
        tgRespond(['ok' => true, 'handled' => 'append_text_ticket']);
    }

    // ----- Foto o documento -----

    // Se il messaggio contiene foto o documento
    if (!empty($message['photo']) || !empty($message['document'])) {

        // Se il messaggio ha anche testo
        if ($text !== '') {
            // Aggiorna sia message id che ultimo testo docente
            dbExec("
                UPDATE docente_telegram_relay
                SET docente_message_id = " . dbI($teacherMessageId) . ",
                    ultimo_testo_docente = " . dbQ($text) . "
                WHERE id = " . dbI($idRelay) . "
            ");
        } else {
            // Altrimenti aggiorna solo il message id docente
            dbExec("
                UPDATE docente_telegram_relay
                SET docente_message_id = " . dbI($teacherMessageId) . "
                WHERE id = " . dbI($idRelay) . "
            ");
        }

        // Invia nel thread admin un messaggio testuale che segnala il nuovo allegato
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $serviceChatId,
            "📎 Nuovo allegato sul ticket {$ticketCode}" .
                ($text !== '' ? "\n\nMessaggio:\n" . tgCut($text, 3000) : ''),
            [
                'message_thread_id' => $serviceThreadId,
                'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($openRelay), JSON_UNESCAPED_UNICODE)
            ]
        );

        // Copia il messaggio originale del docente nel thread admin
        tgCopyMessage(
            $TELEGRAM_BOT_TOKEN,
            $serviceChatId,
            $teacherChatId,
            $teacherMessageId,
            ['message_thread_id' => $serviceThreadId]
        );

        // Invia conferma al docente
        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $teacherChatId,
            "✅ Il tuo allegato è stato aggiunto al ticket {$ticketCode}."
        );

        // Termina segnando l'accodamento allegato come gestito
        tgRespond(['ok' => true, 'handled' => 'append_media_ticket']);
    }
}

/**
 * Se il profilo collegato NON ha ticket aperto:
 * qualsiasi testo libero mostra la guida
 */

if ($privateActor && $text !== '' && !preg_match('/^\//', $text)) {
    $closedRelay = function_exists('tgFindLatestClosedRelayByActor')
        ? tgFindLatestClosedRelayByActor($privateActorType, (int)($privateActor['id'] ?? 0))
        : null;
    if ($closedRelay) {
        tgHandlePrivateActorMessage(
            $privateActor,
            $privateActorType,
            $message,
            $TELEGRAM_SERVICE_CHAT_ID,
            $TELEGRAM_BOT_TOKEN
        );
        tgRespond(['ok' => true, 'handled' => 'closed_ticket_followup_choice']);
    }
}

if ($privateActor) {
    // Invia il messaggio guida su come aprire un ticket
    tgSendMessage(
        $TELEGRAM_BOT_TOKEN,
        $chatId,
        "👋 Benvenuto in GestOre Telegram.\n\n" .
            "Per aprire un ticket scrivi:\n" .
            "/ticket il tuo messaggio\n\n" .
            "Esempio:\n" .
            "/ticket Non vedo le sostituzioni di oggi\n\n" .
            "Se poi il ticket resta aperto, i messaggi successivi, le foto e i documenti verranno aggiunti automaticamente allo stesso ticket."
    );
    tgRespond(['ok' => true, 'handled' => 'actor_guide']);
}

/**
 * Utente non collegato a docente
 */

// Invia il messaggio finale di default per utente non collegato
tgSendMessage(
    $TELEGRAM_BOT_TOKEN,
    $chatId,
    "👋 Ciao. Per collegare il tuo account a GestOre usa il link personale ricevuto via mail.\n\n" .
        "Dopo il collegamento potrai usare:\n" .
        "/ticket il tuo messaggio"
);

// Termina segnando il caso default come gestito
tgRespond(['ok' => true, 'handled' => 'default']);

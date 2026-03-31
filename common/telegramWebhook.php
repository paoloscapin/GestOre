<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

header('Content-Type: application/json; charset=utf-8');

$TELEGRAM_BOT_TOKEN = trim((string)($__settings->telegram->bot_token ?? ''));
$TELEGRAM_SERVICE_CHAT_ID = trim((string)($__settings->telegram->chat_id ?? ''));

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

function tgCut($text, $max = 3500)
{
    $text = trim((string)$text);
    if ($text === '') return '';
    if (mb_strlen($text, 'UTF-8') <= $max) return $text;
    return mb_substr($text, 0, $max - 3, 'UTF-8') . '...';
}

function tgUserDisplayName($from)
{
    if (!is_array($from)) return '';
    $first = tgNorm($from['first_name'] ?? '');
    $last = tgNorm($from['last_name'] ?? '');
    $username = tgNorm($from['username'] ?? '');

    $name = trim($first . ' ' . $last);
    if ($name !== '') return $name;
    if ($username !== '') return '@' . $username;
    return 'Utente Telegram';
}

function tgSendMessage($botToken, $chatId, $text, array $extra = [])
{
    $botToken = trim((string)$botToken);
    $chatId   = trim((string)$chatId);
    $text     = (string)$text;

    if ($botToken === '' || $chatId === '') {
        return ['ok' => false, 'error' => 'botToken o chatId mancanti'];
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $payload = array_merge([
        'chat_id' => $chatId,
        'text'    => $text
    ], $extra);

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
        return [
            'ok' => true,
            'response' => $response,
            'json' => $json,
            'message_id' => (int)($json['result']['message_id'] ?? 0)
        ];
    }

    return ['ok' => false, 'error' => $response ?: 'Risposta Telegram vuota'];
}

function tgEditMessage($botToken, $chatId, $messageId, $text, array $extra = [])
{
    $botToken = trim((string)$botToken);
    $chatId   = trim((string)$chatId);
    $messageId = (int)$messageId;
    $text     = (string)$text;

    if ($botToken === '' || $chatId === '' || $messageId <= 0) {
        return ['ok' => false, 'error' => 'botToken, chatId o messageId mancanti'];
    }

    $url = "https://api.telegram.org/bot{$botToken}/editMessageText";

    $payload = array_merge([
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'text'       => $text
    ], $extra);

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
        return ['ok' => true, 'response' => $response, 'json' => $json];
    }

    return ['ok' => false, 'error' => $response ?: 'Risposta Telegram vuota'];
}

function tgAnswerCallbackQuery($botToken, $callbackQueryId, $text = '')
{
    $botToken = trim((string)$botToken);
    $callbackQueryId = trim((string)$callbackQueryId);

    if ($botToken === '' || $callbackQueryId === '') {
        return;
    }

    $url = "https://api.telegram.org/bot{$botToken}/answerCallbackQuery";
    $payload = ['callback_query_id' => $callbackQueryId];
    if ($text !== '') {
        $payload['text'] = $text;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_exec($ch);
    curl_close($ch);
}

function tgFindDocenteByChatId($chatId)
{
    $chatId = tgNorm($chatId);
    if ($chatId === '') return null;

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
    return dbGetFirst($q);
}

function tgFindRelayByServiceMessage($serviceChatId, $serviceMessageId)
{
    $serviceChatId = tgNorm($serviceChatId);
    $serviceMessageId = (int)$serviceMessageId;

    if ($serviceChatId === '' || $serviceMessageId <= 0) return null;

    $q = "
        SELECT *
        FROM docente_telegram_relay
        WHERE service_chat_id = " . dbQ($serviceChatId) . "
          AND service_message_id = " . dbI($serviceMessageId) . "
        LIMIT 1
    ";
    return dbGetFirst($q);
}

function tgIsUserInGroup($botToken, $groupChatId, $userId)
{
    $url = "https://api.telegram.org/bot{$botToken}/getChatMember";

    $params = [
        'chat_id' => $groupChatId,
        'user_id' => $userId
    ];

    $ch = curl_init($url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);

    if (!is_array($json) || empty($json['ok'])) {
        return false;
    }

    $status = $json['result']['status'] ?? '';

    return in_array($status, ['creator', 'administrator'], true);
}

function tgFindAdminTelegramByUserId($telegramUserId)
{
    $telegramUserId = tgNorm($telegramUserId);
    if ($telegramUserId === '') return null;

    $q = "
        SELECT *
        FROM admin_telegram
        WHERE telegram_user_id = " . dbQ($telegramUserId) . "
        LIMIT 1
    ";
    return dbGetFirst($q);
}

function tgUpsertAdminTelegram($telegramUserId, $telegramChatId, $nome, $username = '')
{
    $telegramUserId = tgNorm($telegramUserId);
    $telegramChatId = tgNorm($telegramChatId);
    $nome = ($nome === null) ? '' : tgNorm($nome);
    $username = ($username === null) ? '' : tgNorm($username);

    if ($nome === '') {
        $nome = 'Admin Telegram';
    }

    if ($telegramUserId === '' || $telegramChatId === '') return false;

    $row = tgFindAdminTelegramByUserId($telegramUserId);

    if ($row) {
        $q = "
            UPDATE admin_telegram
            SET telegram_chat_id = " . dbQ($telegramChatId) . ",
                nome = " . dbQNotNull($nome, 'Admin Telegram') . ",
                username = " . dbQNotNull($username, '') . ",
                attivo = 1
            WHERE telegram_user_id = " . dbQ($telegramUserId) . "
        ";
        errorimportsost("tgUpsertAdminTelegram QUERY: " . $q);
        dbExec($q);
        return true;
    }

    $q = "
        INSERT INTO admin_telegram (
            telegram_user_id,
            telegram_chat_id,
            nome,
            username,
            notifiche_sostituzioni,
            attivo
        ) VALUES (
            " . dbQ($telegramUserId) . ",
            " . dbQ($telegramChatId) . ",
            " . dbQNotNull($nome, 'Admin Telegram') . ",
            " . dbQNotNull($username, '') . ",
            0,
            1
        )
    ";
    errorimportsost("tgUpsertAdminTelegram QUERY: " . $q);
    dbExec($q);
    return true;
}

function tgSetAdminSostituzioniNotify($telegramUserId, $enabled)
{
    $telegramUserId = tgNorm($telegramUserId);
    if ($telegramUserId === '') return false;

    $q = "
        UPDATE admin_telegram
        SET notifiche_sostituzioni = " . dbI($enabled ? 1 : 0) . ",
            attivo = 1
        WHERE telegram_user_id = " . dbQ($telegramUserId) . "
    ";
    dbExec($q);
    return true;
}

function tgGetAdminSostituzioniNotifyStatus($telegramUserId)
{
    $telegramUserId = tgNorm($telegramUserId);
    if ($telegramUserId === '') return null;

    $q = "
        SELECT notifiche_sostituzioni
        FROM admin_telegram
        WHERE telegram_user_id = " . dbQ($telegramUserId) . "
        LIMIT 1
    ";
    $row = dbGetFirst($q);
    if (!$row) return null;

    return (int)($row['notifiche_sostituzioni'] ?? 0);
}

function tgFindOpenRelayByDocente($idDocente)
{
    $idDocente = (int)$idDocente;
    if ($idDocente <= 0) return null;

    $q = "
        SELECT *
        FROM docente_telegram_relay
        WHERE idDocente = " . dbI($idDocente) . "
          AND stato IN ('APERTA', 'IN_GESTIONE')
          AND (chiusa = 0 OR chiusa IS NULL)
        ORDER BY id DESC
        LIMIT 1
    ";
    return dbGetFirst($q);
}

function tgBuildStatoLabel($stato)
{
    $stato = strtoupper(tgNorm($stato));
    if ($stato === 'IN_GESTIONE') return '🟡 IN GESTIONE';
    if ($stato === 'CHIUSA') return '✅ CHIUSA';
    return '🔵 APERTA';
}

function tgBuildTicketCode($idRelay)
{
    $idRelay = (int)$idRelay;
    return 'TCK-' . date('Ymd') . '-' . $idRelay;
}

function tgUpdateTicketCode($idRelay)
{
    $idRelay = (int)$idRelay;
    if ($idRelay <= 0) return '';

    $ticketCode = tgBuildTicketCode($idRelay);

    $q = "
        UPDATE docente_telegram_relay
        SET ticket_code = " . dbQ($ticketCode) . "
        WHERE id = " . dbI($idRelay) . "
    ";
    dbExec($q);

    return $ticketCode;
}

function tgUpdateRelayStatus($idRelay, $newStatus, $adminUserId = null, $adminName = '')
{
    $idRelay = (int)$idRelay;
    $newStatus = strtoupper(tgNorm($newStatus));
    $adminName = tgNorm($adminName);

    if ($idRelay <= 0) return false;
    if (!in_array($newStatus, ['APERTA', 'IN_GESTIONE', 'CHIUSA'], true)) return false;

    $fields = [
        "stato = " . dbQ($newStatus)
    ];

    if ($newStatus === 'IN_GESTIONE') {
        $fields[] = "chiusa = 0";
        if ($adminUserId !== null) {
            $fields[] = "preso_in_carico_da = " . dbQ((string)$adminUserId);
        }
        if ($adminName !== '') {
            $fields[] = "preso_in_carico_nome = " . dbQ($adminName);
        }
        $fields[] = "data_presa_in_carico = NOW()";
        $fields[] = "data_chiusura = NULL";
    }

    if ($newStatus === 'CHIUSA') {
        $fields[] = "chiusa = 1";
        if ($adminUserId !== null) {
            $fields[] = "preso_in_carico_da = " . dbQ((string)$adminUserId);
        }
        if ($adminName !== '') {
            $fields[] = "preso_in_carico_nome = " . dbQ($adminName);
        }
        $fields[] = "data_chiusura = NOW()";
    }

    if ($newStatus === 'APERTA') {
        $fields[] = "chiusa = 0";
        $fields[] = "data_chiusura = NULL";
    }

    $q = "
        UPDATE docente_telegram_relay
        SET " . implode(",\n            ", $fields) . "
        WHERE id = " . dbI($idRelay) . "
    ";
    dbExec($q);
    return true;
}

function tgGetMyWorkingTickets($adminUserId)
{
    $adminUserId = tgNorm($adminUserId);
    if ($adminUserId === '') return [];

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
    $rows = dbGetAll($q);
    return is_array($rows) ? $rows : [];
}

function tgGetWorkingTickets()
{
    $q = "
        SELECT r.*, d.cognome, d.nome
        FROM docente_telegram_relay r
        LEFT JOIN docente d ON d.id = r.idDocente
        WHERE r.stato = 'IN_GESTIONE'
          AND (r.chiusa = 0 OR r.chiusa IS NULL)
        ORDER BY r.id DESC
        LIMIT 20
    ";
    $rows = dbGetAll($q);
    return is_array($rows) ? $rows : [];
}

function tgGetClosedTicketsToday()
{
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
    $rows = dbGetAll($q);
    return is_array($rows) ? $rows : [];
}

function tgGetOpenUnassignedTickets()
{
    $q = "
        SELECT r.*, d.cognome, d.nome
        FROM docente_telegram_relay r
        LEFT JOIN docente d ON d.id = r.idDocente
        WHERE r.stato = 'APERTA'
          AND (r.chiusa = 0 OR r.chiusa IS NULL)
        ORDER BY r.id DESC
        LIMIT 20
    ";
    $rows = dbGetAll($q);
    return is_array($rows) ? $rows : [];
}

function tgBuildMyWorkingTicketsSummary($adminUserId, $adminName = '')
{
    $rows = tgGetMyWorkingTickets($adminUserId);

    if (empty($rows)) {
        return "👤 Nessun ticket attualmente in gestione da parte tua.";
    }

    $lines = ["👤 I miei ticket in lavorazione:\n"];

    if (tgNorm($adminName) !== '') {
        $lines[] = "Admin: " . $adminName . "\n";
    }

    foreach ($rows as $r) {
        $ticketCode = tgNorm($r['ticket_code'] ?? '');
        $docente = trim(tgNorm($r['cognome'] ?? '') . ' ' . tgNorm($r['nome'] ?? ''));
        $msg = tgNorm($r['ultimo_testo_docente'] ?? '');
        $msg = tgCut($msg, 80);

        $lines[] = "• {$ticketCode} - Richiedente: {$docente}";
        if ($msg !== '') {
            $lines[] = "  {$msg}";
        }
    }

    return implode("\n", $lines);
}

function tgGetAdminsToNotifySostituzioni()
{
    $q = "
        SELECT *
        FROM admin_telegram
        WHERE attivo = 1
          AND notifiche_sostituzioni = 1
          AND telegram_chat_id IS NOT NULL
          AND telegram_chat_id <> ''
        ORDER BY nome
    ";
    $rows = dbGetAll($q);
    return is_array($rows) ? $rows : [];
}

function tgBuildOpenTicketsSummary()
{
    $rows = tgGetOpenUnassignedTickets();

    if (empty($rows)) {
        return [
            'text' => "📋 Nessun ticket aperto non ancora preso in carico.",
            'keyboard' => null
        ];
    }

    $lines = ["📋 Ticket aperti non ancora presi in carico:\n"];
    $keyboardRows = [];

    foreach ($rows as $r) {
        $idRelay = (int)($r['id'] ?? 0);
        $ticketCode = tgNorm($r['ticket_code'] ?? '');
        $docente = trim(tgNorm($r['cognome'] ?? '') . ' ' . tgNorm($r['nome'] ?? ''));
        $msg = tgNorm($r['ultimo_testo_docente'] ?? '');
        $msg = tgCut($msg, 80);

        $lines[] = "• {$ticketCode} - {$docente}";
        if ($msg !== '') {
            $lines[] = "  {$msg}";
        }

        if ($idRelay > 0) {
            $keyboardRows[] = [
                [
                    'text' => "🟡 Prendi {$ticketCode}",
                    'callback_data' => "presa_relay_{$idRelay}"
                ]
            ];
        }
    }

    return [
        'text' => implode("\n", $lines),
        'keyboard' => [
            'inline_keyboard' => $keyboardRows
        ]
    ];
}

function tgBuildWorkingTicketsSummary()
{
    $rows = tgGetWorkingTickets();

    if (empty($rows)) {
        return "🟡 Nessun ticket attualmente in lavorazione.";
    }

    $lines = ["🟡 Ticket in lavorazione:\n"];

    foreach ($rows as $r) {
        $ticketCode = tgNorm($r['ticket_code'] ?? '');
        $docente = trim(tgNorm($r['cognome'] ?? '') . ' ' . tgNorm($r['nome'] ?? ''));
        $owner = tgNorm($r['preso_in_carico_nome'] ?? '');
        $msg = tgNorm($r['ultimo_testo_docente'] ?? '');
        $msg = tgCut($msg, 80);

        $lines[] = "• {$ticketCode} - {$docente}";
        if ($owner !== '') {
            $lines[] = "  In gestione da: {$owner}";
        }
        if ($msg !== '') {
            $lines[] = "  {$msg}";
        }
    }

    return implode("\n", $lines);
}

function tgBuildClosedTodayTicketsSummary()
{
    $rows = tgGetClosedTicketsToday();

    if (empty($rows)) {
        return "✅ Nessun ticket chiuso oggi.";
    }

    $lines = ["✅ Ticket chiusi oggi:\n"];

    foreach ($rows as $r) {
        $ticketCode = tgNorm($r['ticket_code'] ?? '');
        $docente = trim(tgNorm($r['cognome'] ?? '') . ' ' . tgNorm($r['nome'] ?? ''));
        $owner = tgNorm($r['preso_in_carico_nome'] ?? '');
        $when = tgNorm($r['data_chiusura'] ?? '');
        $ora = '';
        if ($when !== '') {
            $ts = strtotime($when);
            if ($ts) {
                $ora = date('H:i', $ts);
            }
        }

        $line = "• {$ticketCode} - {$docente}";
        if ($ora !== '') {
            $line .= " - chiuso alle {$ora}";
        }
        $lines[] = $line;

        if ($owner !== '') {
            $lines[] = "  Chiuso da: {$owner}";
        }
    }

    return implode("\n", $lines);
}

function tgHandleStartToken($token, $chatId)
{
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
        }

        $qTokUpd = "
            UPDATE docente_telegram_token
            SET usato = 1,
                dataUso = NOW()
            WHERE idToken = " . dbI((int)$tok['idToken']);
        dbExec($qTokUpd);

        dbExec("COMMIT");

        return "✅ Collegamento completato con successo.\n\nDocente associato: {$docenteNome}\nDa ora puoi ricevere le notifiche Telegram di GestOre per le sostituzioni e scrivere al servizio assistenza.";
    } catch (Throwable $e) {
        dbExec("ROLLBACK");
        errorimportsost("telegramWebhook: eccezione " . $e->getMessage());
        return "❌ Errore durante il collegamento Telegram.\n\nContatta la segreteria o l'amministratore di GestOre.";
    }
}

function tgHandlePrivateTeacherMessage($doc, $message, $serviceChatId, $botToken)
{
    $teacherChatId = tgNorm($message['chat']['id'] ?? '');
    $teacherMessageId = (int)($message['message_id'] ?? 0);
    $text = tgNorm($message['text'] ?? '');

    if ($teacherChatId === '' || $teacherMessageId <= 0 || $text === '') {
        return;
    }

    $docenteNome = trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? ''));
    $idDocente = (int)($doc['id'] ?? 0);

    $openRelay = tgFindOpenRelayByDocente($idDocente);

    if ($openRelay) {
        $idRelay = (int)$openRelay['id'];
        $ticketCode = tgNorm($openRelay['ticket_code']);
        if ($ticketCode === '') {
            $ticketCode = tgUpdateTicketCode($idRelay);
        }

        $statoLabel = tgBuildStatoLabel($openRelay['stato'] ?? 'APERTA');

        $serviceText =
            "➕ Aggiornamento ticket {$ticketCode}\n\n" .
            "👤 Docente: " . $docenteNome . "\n" .
            "📌 Stato attuale: " . $statoLabel . "\n\n" .
            "✉️ Nuovo messaggio:\n" . tgCut($text, 3000);

        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            $serviceText,
            ['reply_to_message_id' => (int)($openRelay['service_message_id'] ?? 0)]
        );

        if (!$sendRes['ok']) {
            errorimportsost("telegramWebhook: errore invio aggiornamento ticket esistente idRelay=$idRelay err=[" . ($sendRes['error'] ?? '') . "]");
            tgSendMessage(
                $botToken,
                $teacherChatId,
                "❌ Non è stato possibile aggiornare il ticket {$ticketCode}. Riprova più tardi."
            );
            return;
        }

        dbExec("
            UPDATE docente_telegram_relay
            SET docente_message_id = " . dbI($teacherMessageId) . ",
                ultimo_testo_docente = " . dbQ($text) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        tgSendMessage(
            $botToken,
            $teacherChatId,
            "✅ Il tuo messaggio è stato aggiunto al ticket {$ticketCode}.\nStato corrente: {$statoLabel}."
        );

        infoimportsost("telegramWebhook: aggiornamento ticket esistente idRelay=$idRelay ticket=$ticketCode");
        return;
    }

    $serviceText =
        "📩 Nuova richiesta docente\n\n" .
        "👤 Docente: " . $docenteNome . "\n" .
        "🆔 ID docente: " . $idDocente . "\n" .
        "💬 Chat docente: " . $teacherChatId . "\n" .
        "📌 Stato: " . tgBuildStatoLabel('APERTA') . "\n\n" .
        "✉️ Messaggio:\n" . tgCut($text, 3000) . "\n\n" .
        "Rispondere in reply a questo messaggio.\n" .
        "Comandi admin in reply: /presa  /in_gestione  /chiudi  /riapri  /stato";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🟡 Prendi in carico', 'callback_data' => 'presa'],
                ['text' => '✅ Chiudi', 'callback_data' => 'chiudi']
            ],
            [
                ['text' => '🔵 Riapri', 'callback_data' => 'riapri'],
                ['text' => '📌 Stato', 'callback_data' => 'stato']
            ],
            [
                ['text' => '📋 Ticket aperti', 'callback_data' => 'lista_aperte']
            ],
            [
                ['text' => '🟡 Ticket in lavorazione', 'callback_data' => 'lista_lavorazione']
            ],
            [
                ['text' => '✅ Ticket chiusi oggi', 'callback_data' => 'lista_chiusi_oggi']
            ],
            [
                ['text' => '👤 I miei ticket', 'callback_data' => 'lista_miei_ticket']
            ]
        ]
    ];

    $sendRes = tgSendMessage(
        $botToken,
        $serviceChatId,
        $serviceText,
        [
            'reply_markup' => json_encode($keyboard)
        ]
    );

    if (!$sendRes['ok']) {
        errorimportsost("telegramWebhook: errore inoltro gruppo serviceChatId=[$serviceChatId] err=[" . ($sendRes['error'] ?? '') . "]");
        tgSendMessage($botToken, $teacherChatId, "❌ In questo momento non è stato possibile inoltrare il tuo messaggio al gruppo di servizio. Riprova più tardi.");
        return;
    }

    $serviceMessageId = (int)($sendRes['message_id'] ?? 0);

    dbExec("START TRANSACTION");
    try {
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
                " . dbI($serviceMessageId) . ",
                " . dbI($serviceMessageId) . ",
                'APERTA',
                0,
                " . dbQ($text) . "
            )
        ";
        dbExec($q);
        $idRelay = (int)dblastId();

        $ticketCode = tgUpdateTicketCode($idRelay);

        $finalServiceText =
            "📩 Nuova richiesta docente\n\n" .
            "🏷 Ticket: " . $ticketCode . "\n" .
            "👤 Docente: " . $docenteNome . "\n" .
            "🆔 ID docente: " . $idDocente . "\n" .
            "💬 Chat docente: " . $teacherChatId . "\n" .
            "📌 Stato: " . tgBuildStatoLabel('APERTA') . "\n\n" .
            "✉️ Messaggio:\n" . tgCut($text, 3000) . "\n\n" .
            "Rispondere in reply a questo messaggio.\n" .
            "Comandi admin in reply: /presa  /in_gestione  /chiudi  /riapri  /stato";

        $editRes = tgEditMessage(
            $botToken,
            $serviceChatId,
            $serviceMessageId,
            $finalServiceText,
            [
                'reply_markup' => json_encode($keyboard)
            ]
        );

        if (!$editRes['ok']) {
            warningimportsost("telegramWebhook: edit message fallita serviceMessageId=$serviceMessageId err=[" . ($editRes['error'] ?? '') . "]");
        }

        dbExec("COMMIT");

        infoimportsost("telegramWebhook: creata relay idRelay=$idRelay docente=$idDocente teacherChatId=[$teacherChatId] serviceMessageId=$serviceMessageId ticket=$ticketCode");

        tgSendMessage(
            $botToken,
            $teacherChatId,
            "✅ Messaggio inviato al gruppo di servizio GestOre.\nTicket: {$ticketCode}\nStato richiesta: APERTA."
        );
    } catch (Throwable $e) {
        dbExec("ROLLBACK");
        errorimportsost("telegramWebhook: errore insert relay " . $e->getMessage());

        tgSendMessage(
            $botToken,
            $teacherChatId,
            "❌ Si è verificato un errore nella registrazione della richiesta. Riprova più tardi."
        );
    }
}

function tgFindRelayById($idRelay)
{
    $idRelay = (int)$idRelay;
    if ($idRelay <= 0) return null;

    $q = "
        SELECT *
        FROM docente_telegram_relay
        WHERE id = " . dbI($idRelay) . "
        LIMIT 1
    ";
    return dbGetFirst($q);
}

function tgHandleAdminReply($relay, $message, $botToken)
{
    $adminText = tgNorm($message['text'] ?? '');
    $adminFrom = $message['from'] ?? [];
    $adminUserId = tgNorm($adminFrom['id'] ?? '');
    $adminName = tgUserDisplayName($adminFrom);
    $groupChatId = tgNorm($message['chat']['id'] ?? '');
    $replyToMessageId = (int)($message['reply_to_message']['message_id'] ?? 0);

    if ($adminText === '' || $groupChatId === '' || $replyToMessageId <= 0) {
        return;
    }

    $idRelay = (int)($relay['id'] ?? 0);
    $teacherChatId = tgNorm($relay['docente_chat_id'] ?? '');
    $currentStatus = strtoupper(tgNorm($relay['stato'] ?? 'APERTA'));
    $ticketCode = tgNorm($relay['ticket_code'] ?? '');
    if ($ticketCode === '') {
        $ticketCode = tgUpdateTicketCode($idRelay);
    }

    if ($idRelay <= 0 || $teacherChatId === '') {
        return;
    }

    $lower = mb_strtolower($adminText, 'UTF-8');

    if (in_array($lower, ['/presa', '/in_gestione'], true)) {
        tgUpdateRelayStatus($idRelay, 'IN_GESTIONE', $adminUserId, $adminName);

        tgSendMessage(
            $botToken,
            $groupChatId,
            "🟡 {$ticketCode} presa in carico da {$adminName}.",
            ['reply_to_message_id' => $replyToMessageId]
        );

        tgSendMessage(
            $botToken,
            $teacherChatId,
            "🟡 La tua richiesta {$ticketCode} è stata presa in carico da {$adminName}."
        );

        infoimportsost("telegramWebhook: relay $idRelay -> IN_GESTIONE da {$adminName}");
        return;
    }

    if ($lower === '/chiudi') {
        tgUpdateRelayStatus($idRelay, 'CHIUSA', $adminUserId, $adminName);

        tgSendMessage(
            $botToken,
            $groupChatId,
            "✅ {$ticketCode} chiusa da {$adminName}.",
            ['reply_to_message_id' => $replyToMessageId]
        );

        tgSendMessage(
            $botToken,
            $teacherChatId,
            "✅ La tua richiesta {$ticketCode} al servizio GestOre è stata chiusa."
        );

        infoimportsost("telegramWebhook: relay $idRelay -> CHIUSA da {$adminName}");
        return;
    }

    if ($lower === '/riapri') {
        tgUpdateRelayStatus($idRelay, 'APERTA', $adminUserId, $adminName);

        tgSendMessage(
            $botToken,
            $groupChatId,
            "🔵 {$ticketCode} riaperta da {$adminName}.",
            ['reply_to_message_id' => $replyToMessageId]
        );

        tgSendMessage(
            $botToken,
            $teacherChatId,
            "🔵 La tua richiesta {$ticketCode} è stata riaperta."
        );

        infoimportsost("telegramWebhook: relay $idRelay -> APERTA da {$adminName}");
        return;
    }

    if ($lower === '/stato') {
        $statusLabel = tgBuildStatoLabel($currentStatus);
        $owner = tgNorm($relay['preso_in_carico_nome'] ?? '');
        $ownerText = $owner !== '' ? "\n👤 In gestione da: {$owner}" : '';

        tgSendMessage(
            $botToken,
            $groupChatId,
            "📌 {$ticketCode}\nStato richiesta: {$statusLabel}{$ownerText}",
            ['reply_to_message_id' => $replyToMessageId]
        );
        return;
    }

    if ($currentStatus === 'CHIUSA') {
        tgSendMessage(
            $botToken,
            $groupChatId,
            "⚠️ {$ticketCode} risulta chiusa. Usa /riapri in reply per riaprirla prima di rispondere.",
            ['reply_to_message_id' => $replyToMessageId]
        );
        return;
    }

    $sendRes = tgSendMessage(
        $botToken,
        $teacherChatId,
        "📬 Risposta GestOre - {$ticketCode}\n\n" . $adminText
    );

    if (!$sendRes['ok']) {
        errorimportsost("telegramWebhook: errore invio reply admin->docente relay=$idRelay err=[" . ($sendRes['error'] ?? '') . "]");
        tgSendMessage(
            $botToken,
            $groupChatId,
            "❌ Errore nell'invio della risposta al docente.",
            ['reply_to_message_id' => $replyToMessageId]
        );
        return;
    }

    dbExec("START TRANSACTION");
    try {
        $fields = [
            "ultimo_testo_admin = " . dbQ($adminText),
            "ultima_risposta_admin = NOW()"
        ];

        if ($currentStatus === 'APERTA') {
            $fields[] = "stato = 'IN_GESTIONE'";
            $fields[] = "chiusa = 0";
            $fields[] = "preso_in_carico_da = " . dbQ($adminUserId);
            $fields[] = "preso_in_carico_nome = " . dbQ($adminName);
            $fields[] = "data_presa_in_carico = NOW()";
        }

        $q = "
            UPDATE docente_telegram_relay
            SET " . implode(",\n                ", $fields) . "
            WHERE id = " . dbI($idRelay) . "
        ";
        dbExec($q);

        dbExec("COMMIT");

        tgSendMessage(
            $botToken,
            $groupChatId,
            "✅ Risposta inviata al docente per {$ticketCode} da {$adminName}.",
            ['reply_to_message_id' => $replyToMessageId]
        );

        infoimportsost("telegramWebhook: risposta admin inoltrata relay=$idRelay admin={$adminName}");
    } catch (Throwable $e) {
        dbExec("ROLLBACK");
        errorimportsost("telegramWebhook: errore update relay admin reply " . $e->getMessage());
    }
}

if ($TELEGRAM_BOT_TOKEN === '') {
    errorimportsost("telegramWebhook: bot token mancante");
    tgRespond(['ok' => false, 'error' => 'Bot token mancante'], 500);
}

if ($TELEGRAM_SERVICE_CHAT_ID === '') {
    errorimportsost("telegramWebhook: service chat id mancante");
    tgRespond(['ok' => false, 'error' => 'Service chat id mancante'], 500);
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

/**
 * CALLBACK BUTTONS
 */
$callback = $update['callback_query'] ?? null;

if ($callback) {
    $data = tgNorm($callback['data'] ?? '');
    $from = $callback['from'] ?? [];
    $adminName = tgUserDisplayName($from);
    $adminUserId = tgNorm($from['id'] ?? '');

    $callbackMessage = $callback['message'] ?? [];
    $chatId = tgNorm($callbackMessage['chat']['id'] ?? '');
    $messageId = (int)($callbackMessage['message_id'] ?? 0);

    if (preg_match('/^presa_relay_(\d+)$/', $data, $m)) {
        $idRelay = (int)$m[1];
        $relay = tgFindRelayById($idRelay);

        if (!$relay) {
            tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Ticket non trovato');
            tgRespond(['ok' => true, 'ignored' => 'relay id non trovato']);
        }

        $ticketCode = tgNorm($relay['ticket_code'] ?? '');
        if ($ticketCode === '') {
            $ticketCode = tgUpdateTicketCode($idRelay);
        }

        tgUpdateRelayStatus($idRelay, 'IN_GESTIONE', $adminUserId, $adminName);

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            "🟡 {$ticketCode} preso in carico da {$adminName}",
            ['reply_to_message_id' => $messageId]
        );

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $relay['docente_chat_id'],
            "🟡 La tua richiesta {$ticketCode} è stata presa in carico da {$adminName}."
        );

        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Ticket preso in carico');
        tgRespond(['ok' => true, 'handled' => 'callback_presa_relay']);
    }

    if ($chatId === '' || $messageId <= 0) {
        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Messaggio non valido');
        tgRespond(['ok' => true]);
    }

    $relay = tgFindRelayByServiceMessage($chatId, $messageId);

    if (
        !$relay &&
        $data !== 'lista_aperte' &&
        $data !== 'lista_lavorazione' &&
        $data !== 'lista_chiusi_oggi' &&
        $data !== 'lista_miei_ticket'
    ) {
        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Ticket non trovato');
        tgRespond(['ok' => true]);
    }

    if ($data === 'lista_aperte') {
        $summary = tgBuildOpenTicketsSummary();

        $extra = [];
        if (!empty($summary['keyboard'])) {
            $extra['reply_markup'] = json_encode($summary['keyboard']);
        }

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            $summary['text'],
            $extra
        );

        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Elenco aggiornato');
        tgRespond(['ok' => true, 'handled' => 'callback_lista_aperte']);
    }

    if ($data === 'lista_miei_ticket') {
        $summary = tgBuildMyWorkingTicketsSummary($adminUserId, $adminName);

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            $summary
        );

        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Elenco aggiornato');
        tgRespond(['ok' => true, 'handled' => 'callback_lista_miei_ticket']);
    }

    if ($data === 'lista_lavorazione') {
        $summary = tgBuildWorkingTicketsSummary();

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            $summary
        );

        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Elenco aggiornato');
        tgRespond(['ok' => true, 'handled' => 'callback_lista_lavorazione']);
    }

    if ($data === 'lista_chiusi_oggi') {
        $summary = tgBuildClosedTodayTicketsSummary();

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            $summary
        );

        tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Elenco aggiornato');
        tgRespond(['ok' => true, 'handled' => 'callback_lista_chiusi_oggi']);
    }

    $idRelay = (int)$relay['id'];
    $ticketCode = tgNorm($relay['ticket_code'] ?? '');
    if ($ticketCode === '') {
        $ticketCode = tgUpdateTicketCode($idRelay);
    }

    switch ($data) {
        case 'presa':
            tgUpdateRelayStatus($idRelay, 'IN_GESTIONE', $adminUserId, $adminName);

            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "🟡 {$ticketCode} preso in carico da {$adminName}",
                ['reply_to_message_id' => $messageId]
            );

            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $relay['docente_chat_id'],
                "🟡 La tua richiesta {$ticketCode} è stata presa in carico da {$adminName}."
            );
            break;

        case 'chiudi':
            tgUpdateRelayStatus($idRelay, 'CHIUSA', $adminUserId, $adminName);

            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "✅ {$ticketCode} chiuso da {$adminName}",
                ['reply_to_message_id' => $messageId]
            );

            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $relay['docente_chat_id'],
                "✅ La tua richiesta {$ticketCode} è stata chiusa."
            );
            break;

        case 'riapri':
            tgUpdateRelayStatus($idRelay, 'APERTA', $adminUserId, $adminName);

            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "🔵 {$ticketCode} riaperto da {$adminName}",
                ['reply_to_message_id' => $messageId]
            );

            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $relay['docente_chat_id'],
                "🔵 La tua richiesta {$ticketCode} è stata riaperta."
            );
            break;

        case 'stato':
            $status = tgBuildStatoLabel($relay['stato'] ?? 'APERTA');

            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "📌 {$ticketCode}\nStato: {$status}",
                ['reply_to_message_id' => $messageId]
            );
            break;
    }

    tgAnswerCallbackQuery($TELEGRAM_BOT_TOKEN, $callback['id'] ?? '', 'Operazione eseguita');
    tgRespond(['ok' => true, 'handled' => 'callback']);
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

$from = $message['from'] ?? [];
$fromName = tgUserDisplayName($from);
$fromUserId = tgNorm($from['id'] ?? '');
$fromUsername = tgNorm($from['username'] ?? '');

if ($chatType === 'private' && $fromUserId !== '' && $chatId !== '') {
    try {
        tgUpsertAdminTelegram($fromUserId, $chatId, $fromName, $fromUsername);
        infoimportsost("telegramWebhook: upsert admin ok userId=[$fromUserId] chatId=[$chatId] from=[$fromName]");
    } catch (Throwable $e) {
        errorimportsost("telegramWebhook: upsert admin fallito userId=[$fromUserId] chatId=[$chatId] err=[" . $e->getMessage() . "]");
    }
}

infoimportsost("telegramWebhook: update ricevuto chatId=[$chatId] chatType=[$chatType] from=[$fromName] text=[" . tgCut($text, 200) . "]");

/**
 * GRUPPO ADMIN
 */
if ($chatId === $TELEGRAM_SERVICE_CHAT_ID) {

    $groupFrom = $message['from'] ?? [];
    $groupAdminUserId = tgNorm($groupFrom['id'] ?? '');
    $groupAdminName = tgUserDisplayName($groupFrom);
    $groupAdminUsername = tgNorm($groupFrom['username'] ?? '');

    if ($groupAdminUserId !== '') {
        $existingAdmin = tgFindAdminTelegramByUserId($groupAdminUserId);
        if ($existingAdmin) {
            tgUpsertAdminTelegram(
                $groupAdminUserId,
                tgNorm($existingAdmin['telegram_chat_id'] ?? ''),
                $groupAdminName,
                $groupAdminUsername
            );
        }
    }

    $lowerText = mb_strtolower($text, 'UTF-8');

    if ($lowerText === '/notify_sost_on') {
        $existingAdmin = tgFindAdminTelegramByUserId($groupAdminUserId);

        if (!$existingAdmin || tgNorm($existingAdmin['telegram_chat_id'] ?? '') === '') {
            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "⚠️ {$groupAdminName}, prima scrivi almeno una volta al bot in chat privata, poi ripeti /notify_sost_on qui nel gruppo."
            );
            tgRespond(['ok' => true, 'handled' => 'notify_sost_on_missing_private_chat']);
        }

        tgSetAdminSostituzioniNotify($groupAdminUserId, 1);

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            "✅ {$groupAdminName} ha abilitato le notifiche Telegram delle sostituzioni in chat privata."
        );
        tgRespond(['ok' => true, 'handled' => 'notify_sost_on']);
    }

    if ($lowerText === '/notify_sost_off') {
        $existingAdmin = tgFindAdminTelegramByUserId($groupAdminUserId);

        if ($existingAdmin) {
            tgSetAdminSostituzioniNotify($groupAdminUserId, 0);
        }

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            "✅ {$groupAdminName} ha disabilitato le notifiche Telegram delle sostituzioni."
        );
        tgRespond(['ok' => true, 'handled' => 'notify_sost_off']);
    }

    if ($lowerText === '/notify_sost_stato') {
        $status = tgGetAdminSostituzioniNotifyStatus($groupAdminUserId);

        if ($status === null) {
            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "ℹ️ {$groupAdminName}, non risulti ancora registrato. Scrivi prima al bot in chat privata."
            );
            tgRespond(['ok' => true, 'handled' => 'notify_sost_stato_unknown']);
        }

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            $status ? "🟢 {$groupAdminName}: notifiche sostituzioni ATTIVE." : "🔴 {$groupAdminName}: notifiche sostituzioni DISATTIVE."
        );
        tgRespond(['ok' => true, 'handled' => 'notify_sost_stato']);
    }

    $replyTo = $message['reply_to_message'] ?? null;

    if (!is_array($replyTo)) {
        tgRespond(['ok' => true, 'ignored' => 'messaggio gruppo non in reply']);
    }

    $replyToMessageId = (int)($replyTo['message_id'] ?? 0);
    if ($replyToMessageId <= 0) {
        tgRespond(['ok' => true, 'ignored' => 'reply_to_message_id mancante']);
    }

    $relay = tgFindRelayByServiceMessage($TELEGRAM_SERVICE_CHAT_ID, $replyToMessageId);
    if (!$relay) {
        tgRespond(['ok' => true, 'ignored' => 'nessun relay trovato']);
    }

    if ($text === '') {
        tgRespond(['ok' => true, 'ignored' => 'testo gruppo vuoto']);
    }

    tgHandleAdminReply($relay, $message, $TELEGRAM_BOT_TOKEN);
    tgRespond(['ok' => true, 'handled' => 'group_reply']);
}

/**
 * CHAT PRIVATE
 */
if ($chatType !== 'private') {
    tgRespond(['ok' => true, 'ignored' => 'chat non gestita']);
}

if ($text === '') {
    tgRespond(['ok' => true, 'ignored' => 'testo vuoto']);
}

/**
 * /start TOKEN
 */
if (preg_match('/^\/start(?:\s+(.+))?$/u', $text, $m)) {
    $token = tgNorm($m[1] ?? '');
    $reply = tgHandleStartToken($token, $chatId);
    $sendRes = tgSendMessage($TELEGRAM_BOT_TOKEN, $chatId, $reply);

    if (!$sendRes['ok']) {
        errorimportsost("telegramWebhook: errore invio risposta Telegram chatId=[$chatId] err=[" . ($sendRes['error'] ?? '') . "]");
    }

    tgRespond(['ok' => true, 'handled' => 'start']);
}

if (mb_strtolower($text, 'UTF-8') === '/help') {
    tgSendMessage(
        $TELEGRAM_BOT_TOKEN,
        $chatId,
        "Comandi disponibili:\n" .
            "/start TOKEN - collega Telegram a GestOre\n" .
            "/help - mostra questo messaggio\n\n" .
            "Se il tuo account è già collegato, puoi scrivere qui le tue richieste e saranno inoltrate al gruppo di servizio GestOre."
    );
    tgRespond(['ok' => true, 'handled' => 'help']);
}

if ($chatType === 'private' && $fromUserId !== '' && $chatId !== '') {
    if (mb_strtolower($text, 'UTF-8') === '/admin') {

        // verifica che sia nel gruppo admin
        $isMember = tgIsUserInGroup(
            $TELEGRAM_BOT_TOKEN,
            $TELEGRAM_SERVICE_CHAT_ID,
            $fromUserId
        );

        if (!$isMember) {
            tgSendMessage(
                $TELEGRAM_BOT_TOKEN,
                $chatId,
                "❌ Non sei autorizzato.\n" .
                    "Devi essere membro del gruppo admin GestOre."
            );
            tgRespond(['ok' => true, 'handled' => 'admin_denied']);
        }

        // registrazione admin
        tgUpsertAdminTelegram($fromUserId, $chatId, $fromName, $fromUsername);

        tgSendMessage(
            $TELEGRAM_BOT_TOKEN,
            $chatId,
            "✅ Chat privata registrata per funzioni admin.\n\n" .
                "Ora puoi usare nel gruppo:\n" .
                "/notify_sost_on\n" .
                "/notify_sost_off\n" .
                "/notify_sost_stato"
        );

        tgRespond(['ok' => true, 'handled' => 'admin_register']);
    }
}

$doc = tgFindDocenteByChatId($chatId);
$admin = tgFindAdminTelegramByUserId($fromUserId);

if ($doc && !$admin) {
    tgHandlePrivateTeacherMessage($doc, $message, $TELEGRAM_SERVICE_CHAT_ID, $TELEGRAM_BOT_TOKEN);
    tgRespond(['ok' => true, 'handled' => 'private_teacher_message']);
}

tgSendMessage(
    $TELEGRAM_BOT_TOKEN,
    $chatId,
    "👋 Ciao. Per collegare il tuo account a GestOre usa il link personale ricevuto via mail."
);

tgRespond(['ok' => true, 'handled' => 'default']);

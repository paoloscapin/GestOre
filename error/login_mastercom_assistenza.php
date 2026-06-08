<?php

require_once '../common/__Util.php';
require_once '../common/path.php';
require_once '../common/connect.php';
require_once '../common/__Settings.php';
require_once '../common/__Log.php';
require_once '../common/send-mail.php';
require_once '../common/telegram_webhook_utils.php';
require_once '../common/telegram_webhook_api.php';
require_once '../common/telegram_webhook_relay.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_name('GESTORESESSID');
    @session_start();
}

function lmca_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function lmca_column_exists(string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = $tableName . '.' . $columnName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $cache[$key] = dbGetValue("SHOW COLUMNS FROM `" . dbEscape($tableName) . "` LIKE " . dbQ($columnName)) !== null;
    return $cache[$key];
}

function lmca_create_ticket(array $genitore, array $ctx): array
{
    global $__settings;

    $botToken = trim((string)($__settings->telegram->bot_token ?? ''));
    $serviceChatId = trim((string)($__settings->telegram->chat_id ?? ''));
    if ($botToken === '' || $serviceChatId === '') {
        return ['ok' => false, 'error' => 'Configurazione Telegram mancante'];
    }

    $genitoreId = intval($genitore['id'] ?? 0);
    $nome = trim((string)($genitore['nome'] ?? ''));
    $cognome = trim((string)($genitore['cognome'] ?? ''));
    $email = trim((string)($genitore['email'] ?? ''));
    $codiceFiscale = trim((string)($genitore['codice_fiscale'] ?? ''));
    $username = trim((string)($ctx['username'] ?? ''));
    $studentiAttivi = intval($ctx['studenti_attivi'] ?? 0);
    $mastercomHttp = intval($ctx['mastercom_http'] ?? 0);
    $mastercomError = trim((string)($ctx['mastercom_error'] ?? ''));

    $ticketText =
        "LOGIN_MASTERCOM\n" .
        "Richiesta assistenza aperta dal genitore dalla pagina di errore login.\n\n" .
        "Genitore: " . trim($cognome . ' ' . $nome) . "\n" .
        "ID GestOre: " . $genitoreId . "\n" .
        "Username MasterCom: " . ($username !== '' ? $username : '-') . "\n" .
        "Email: " . ($email !== '' ? $email : '-') . "\n" .
        "Codice fiscale: " . ($codiceFiscale !== '' ? $codiceFiscale : '-') . "\n" .
        "Studenti attivi collegati: " . $studentiAttivi . "\n" .
        "MasterCom HTTP: " . $mastercomHttp . "\n" .
        "MasterCom errore: " . ($mastercomError !== '' ? $mastercomError : '-') . "\n" .
        "IP richiesta assistenza: " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');

    $existing = null;
    if (lmca_column_exists('docente_telegram_relay', 'idGenitore')) {
        $existing = dbGetFirst("
            SELECT *
            FROM docente_telegram_relay
            WHERE idGenitore = " . dbI($genitoreId) . "
              AND stato IN ('APERTA','IN_GESTIONE')
              AND (chiusa = 0 OR chiusa IS NULL)
              AND ultimo_testo_docente LIKE '%LOGIN_MASTERCOM%'
            ORDER BY id DESC
            LIMIT 1
        ");
    }

    if ($existing) {
        $idRelay = intval($existing['id'] ?? 0);
        $ticketCode = tgNorm($existing['ticket_code'] ?? '');
        if ($ticketCode === '') {
            $ticketCode = tgUpdateTicketCode($idRelay);
        }

        dbExec("
            UPDATE docente_telegram_relay
            SET ultimo_testo_docente = " . dbQ(tgAppendTicketUserText($existing['ultimo_testo_docente'] ?? '', $ticketText)) . ",
                data_aggiornamento = NOW()
            WHERE id = " . dbI($idRelay) . "
        ");

        $relay = tgFindRelayById($idRelay) ?: $existing;
        $sendOptions = [
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relay), JSON_UNESCAPED_UNICODE)
        ];
        $threadId = intval($existing['service_thread_id'] ?? 0);
        if ($threadId > 0) {
            $sendOptions['message_thread_id'] = $threadId;
        }
        tgSendMessage($botToken, $serviceChatId, "Aggiornamento ticket {$ticketCode}\n\n" . tgCut($ticketText, 3000), $sendOptions);
        return ['ok' => true, 'ticket_code' => $ticketCode, 'idRelay' => $idRelay];
    }

    dbExec('START TRANSACTION');
    try {
        $insertColumns = [
            'docente_chat_id',
            'docente_message_id',
            'service_chat_id',
            'service_message_id',
            'service_thread_root_message_id',
            'stato',
            'chiusa',
            'ultimo_testo_docente',
            'data_creazione',
            'data_aggiornamento'
        ];
        $insertValues = [
            "''",
            '0',
            dbQ($serviceChatId),
            '0',
            '0',
            "'APERTA'",
            '0',
            dbQ($ticketText),
            'NOW()',
            'NOW()'
        ];

        $optionalColumns = [
            'idGenitore' => dbI($genitoreId),
            'tipo_utente' => dbQ('genitore'),
            'canale_apertura' => dbQ('login_mastercom'),
            'email_riferimento' => dbQ($email),
            'utente_nome' => dbQ($nome),
            'utente_cognome' => dbQ($cognome),
            'utente_email' => dbQ($email),
        ];
        foreach ($optionalColumns as $column => $value) {
            if (lmca_column_exists('docente_telegram_relay', $column)) {
                $insertColumns[] = $column;
                $insertValues[] = $value;
            }
        }

        dbExec("
            INSERT INTO docente_telegram_relay (
                " . implode(",\n                ", $insertColumns) . "
            ) VALUES (
                " . implode(",\n                ", $insertValues) . "
            )
        ");

        $idRelay = (int)dblastId();
        if ($idRelay <= 0) {
            throw new Exception('Inserimento ticket fallito');
        }

        $ticketCode = tgUpdateTicketCode($idRelay);
        $threadId = tgCreateTopic($botToken, $serviceChatId, "Ticket " . $ticketCode);
        dbExec("
            UPDATE docente_telegram_relay
            SET service_thread_id = " . dbI($threadId) . ",
                thread_topic_name = " . dbQ("Ticket $ticketCode") . "
            WHERE id = " . dbI($idRelay) . "
        ");

        $relay = tgFindRelayById($idRelay) ?: ['id' => $idRelay, 'ticket_code' => $ticketCode, 'stato' => 'APERTA', 'chiusa' => 0];
        $sendOptions = [
            'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relay), JSON_UNESCAPED_UNICODE)
        ];
        if ((int)$threadId > 0) {
            $sendOptions['message_thread_id'] = (int)$threadId;
        }
        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            "Nuovo ticket login MasterCom\n\nTicket: {$ticketCode}\nGenitore: " . trim($cognome . ' ' . $nome) . "\n\n" . tgCut($ticketText, 3000),
            $sendOptions
        );
        if (empty($sendRes['ok'])) {
            throw new Exception('Invio Telegram fallito: ' . ($sendRes['error'] ?? 'errore sconosciuto'));
        }

        dbExec("
            UPDATE docente_telegram_relay
            SET service_message_id = " . dbI((int)($sendRes['message_id'] ?? 0)) . ",
                service_thread_root_message_id = " . dbI((int)($sendRes['message_id'] ?? 0)) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        dbExec('COMMIT');
        return ['ok' => true, 'ticket_code' => $ticketCode, 'idRelay' => $idRelay];
    } catch (Throwable $e) {
        dbExec('ROLLBACK');
        errorTelegram('Creazione ticket assistenza login MasterCom fallita: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$token = trim((string)($_GET['token'] ?? ''));
$ctx = $_SESSION['login_mastercom_assistenza'] ?? null;
$message = '';
$ok = false;
$ticketCode = '';

if (!is_array($ctx) || $token === '' || !hash_equals((string)($ctx['token'] ?? ''), $token) || intval($ctx['expires_at'] ?? 0) < time()) {
    $message = 'Link di assistenza non valido o scaduto. Riprova il login e usa il nuovo link nella pagina di errore.';
} else {
    $genitoreId = intval($ctx['genitore_id'] ?? 0);
    $genitore = dbGetFirst("SELECT * FROM genitori WHERE id = " . dbI($genitoreId) . " AND attivo = 1 LIMIT 1");
    if (!$genitore) {
        $message = 'Profilo genitore non trovato o non attivo.';
    } else {
        $result = lmca_create_ticket($genitore, $ctx);
        if (!empty($result['ok'])) {
            $ok = true;
            $ticketCode = trim((string)($result['ticket_code'] ?? ''));
            unset($_SESSION['login_mastercom_assistenza']);

            $email = trim((string)($genitore['email'] ?? ''));
            if ($email !== '') {
                $fullName = trim((string)($genitore['nome'] ?? '') . ' ' . (string)($genitore['cognome'] ?? ''));
                $html = '<p>Gentile ' . lmca_h($fullName !== '' ? $fullName : 'genitore') . ',</p>'
                    . '<p>abbiamo aperto una richiesta di assistenza per il problema di accesso a MasterCom/GestOre.</p>'
                    . '<p><strong>Ticket:</strong> ' . lmca_h($ticketCode !== '' ? $ticketCode : 'in lavorazione') . '</p>'
                    . '<p>La segreteria prenderà in carico la segnalazione appena possibile.</p>'
                    . '<p>Questo messaggio è stato generato automaticamente da GestOre.</p>';
                sendMail($email, $fullName !== '' ? $fullName : $email, 'GestOre - richiesta assistenza login MasterCom', $html);
            }
            $message = 'Richiesta di assistenza aperta correttamente.';
        } else {
            $message = 'Non è stato possibile aprire la richiesta di assistenza: ' . trim((string)($result['error'] ?? 'errore sconosciuto'));
        }
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assistenza login MasterCom</title>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-error-min.php'; ?>
<div class="container" style="margin-top:80px;max-width:760px;">
    <div class="panel <?php echo $ok ? 'panel-success' : 'panel-warning'; ?>">
        <div class="panel-heading">Assistenza login MasterCom</div>
        <div class="panel-body">
            <h4><?php echo lmca_h($message); ?></h4>
            <?php if ($ok && $ticketCode !== ''): ?>
                <p>Codice ticket: <strong><?php echo lmca_h($ticketCode); ?></strong></p>
            <?php endif; ?>
            <p><a class="btn btn-primary" href="../index.php">Torna al login</a></p>
        </div>
    </div>
</div>
</body>
</html>

<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/__Log.php';
require_once '../common/__Settings.php';

ruoloRichiesto('genitore');
header('Content-Type: application/json; charset=utf-8');

function genitoreTelegramRespond(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function genitoreTelegramNorm($v): string
{
    return trim((string)$v);
}

function genitoreTelegramEh($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function genitoreTelegramToken(int $len = 48): string
{
    return bin2hex(random_bytes((int)($len / 2)));
}

function genitoreTelegramTableExists(string $tableName): bool
{
    return dbGetValue("SHOW TABLES LIKE " . dbQ($tableName)) !== null;
}

function genitoreTelegramBuildMailHtml(string $fullName, string $telegramLink): string
{
    return '
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Collegamento account Telegram</title>
</head>
<body style="margin:0;padding:0;background:#f2f2f2;font-family:Arial,Helvetica,sans-serif;color:#2f3542;">
    <div style="max-width:920px;margin:0 auto;padding:20px 18px;">
        <div style="background:#0f766e;color:#fff;border-radius:18px 18px 0 0;padding:22px 26px;font-size:22px;font-weight:700;letter-spacing:.3px;">
            Collegamento account Telegram genitore
        </div>

        <div style="background:#f7f7f7;border:1px solid #e3e3e3;border-top:none;border-radius:0 0 18px 18px;padding:22px 26px 18px 26px;">
            <div style="font-size:18px;font-weight:700;color:#2d3340;margin-bottom:8px;">
                ' . genitoreTelegramEh($fullName) . '
            </div>

            <div style="font-size:15px;color:#4b5563;line-height:1.6;margin-bottom:18px;">
                Hai richiesto il collegamento del tuo account Telegram a <strong>GestOre</strong> per ricevere notifiche e aggiornamenti.
            </div>

            <div style="font-size:15px;color:#4b5563;line-height:1.6;margin-bottom:18px;">
                Per completare l\'associazione, apri il bot Telegram usando il pulsante qui sotto.
            </div>

            <div style="margin:24px 0;">
                <a href="' . genitoreTelegramEh($telegramLink) . '" style="
                    display:inline-block;
                    background:#0f766e;
                    color:#ffffff;
                    text-decoration:none;
                    padding:12px 20px;
                    border-radius:10px;
                    font-weight:700;
                    font-size:15px;
                ">Apri il bot Telegram</a>
            </div>

            <div style="font-size:14px;color:#6b7280;line-height:1.6;margin-bottom:14px;">
                Se il pulsante non funziona, copia e incolla questo link nel browser:
            </div>

            <div style="
                background:#f1f2f4;
                border:1px solid #d9dde3;
                border-radius:12px;
                padding:12px 14px;
                font-size:13px;
                color:#2d3340;
                word-break:break-all;
            ">' . genitoreTelegramEh($telegramLink) . '</div>

            <div style="margin-top:18px;font-size:13px;color:#6b7280;line-height:1.5;">
                Il link è personale e ha validità limitata.<br>
                Messaggio automatico GestOre.
            </div>
        </div>
    </div>
</body>
</html>';
}

try {
    if (!genitoreTelegramTableExists('genitore_telegram') || !genitoreTelegramTableExists('genitore_telegram_token')) {
        genitoreTelegramRespond([
            'ok' => false,
            'error' => 'Tabelle Telegram genitori non ancora presenti nel database. Applica prima lo script SQL dedicato.'
        ], 500);
    }

    $idGenitore = (int)($__genitore_id ?? 0);
    if ($idGenitore <= 0) {
        genitoreTelegramRespond(['ok' => false, 'error' => 'Profilo genitore non autenticato'], 401);
    }

    $genitore = dbGetFirst("
        SELECT id, nome, cognome, email
        FROM genitori
        WHERE id = " . dbI($idGenitore) . "
          AND attivo = 1
        LIMIT 1
    ");

    if (!$genitore) {
        genitoreTelegramRespond(['ok' => false, 'error' => 'Profilo genitore non trovato'], 404);
    }

    $email = genitoreTelegramNorm($genitore['email'] ?? '');
    if ($email === '') {
        genitoreTelegramRespond(['ok' => false, 'error' => 'Prima aggiorna la mail nel profilo genitore'], 400);
    }

    global $__settings;
    $botUsername = genitoreTelegramNorm($__settings->telegram->bot_username ?? '');
    if ($botUsername === '') {
        genitoreTelegramRespond(['ok' => false, 'error' => 'Configurazione bot Telegram mancante'], 500);
    }

    $fullName = trim((string)($genitore['cognome'] ?? '') . ' ' . (string)($genitore['nome'] ?? ''));
    $token = genitoreTelegramToken(48);
    $dataScadenza = date('Y-m-d H:i:s', strtotime('+2 days'));

    dbExec("
        INSERT INTO genitore_telegram_token (
            idGenitore,
            token,
            dataCreazione,
            dataScadenza,
            usato
        ) VALUES (
            " . dbI($idGenitore) . ",
            " . dbQ($token) . ",
            NOW(),
            " . dbQ($dataScadenza) . ",
            0
        )
    ");

    $telegramLink = 'https://t.me/' . rawurlencode($botUsername) . '?start=' . rawurlencode($token);
    $subject = 'GestOre - Collegamento account Telegram genitore';
    $html = genitoreTelegramBuildMailHtml($fullName, $telegramLink);

    $ok = sendMailCustom($email, $fullName, $subject, $html, [
        'from_email' => trim((string)($__settings->ticketMail->reply_visible_from ?? $__settings->local->emailNoReplyFrom)),
        'from_name' => 'GestOre ' . trim((string)$__settings->local->nomeIstituto),
        'reply_to_email' => trim((string)($__settings->ticketMail->alias_address ?? $__settings->local->emailNoReplyFrom)),
        'reply_to_name' => 'GestOre Ticket',
        'sender_email' => trim((string)$__settings->local->smtpMail),
        'sender_name' => 'GestOre ' . trim((string)$__settings->local->nomeIstituto),
    ]);

    if (!$ok) {
        genitoreTelegramRespond(['ok' => false, 'error' => 'Invio mail non riuscito'], 500);
    }

    genitoreTelegramRespond([
        'ok' => true,
        'message' => 'Ti abbiamo inviato una mail con il link per collegare Telegram.'
    ]);
} catch (Throwable $e) {
    errorTelegram('GenitoreTelegramSendLink: eccezione ' . $e->getMessage());
    genitoreTelegramRespond([
        'ok' => false,
        'error' => 'Errore durante l\'invio del link Telegram: ' . $e->getMessage()
    ], 500);
}

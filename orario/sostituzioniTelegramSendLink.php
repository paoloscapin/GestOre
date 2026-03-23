<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/__Log.php';

ruoloRichiesto('docente');
header('Content-Type: application/json; charset=utf-8');

global $__username;

function respond($payload, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function norm($v)
{
    return trim((string)$v);
}

function eh($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function generateTelegramLinkToken($len = 48)
{
    return bin2hex(random_bytes((int)($len / 2)));
}

function buildTelegramLinkMailHtml($docenteNome, $telegramLink)
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
            📲 COLLEGAMENTO ACCOUNT TELEGRAM
        </div>

        <div style="background:#f7f7f7;border:1px solid #e3e3e3;border-top:none;border-radius:0 0 18px 18px;padding:22px 26px 18px 26px;">
            <div style="font-size:18px;font-weight:700;color:#2d3340;margin-bottom:8px;">
                ' . eh($docenteNome) . '
            </div>

            <div style="font-size:15px;color:#4b5563;line-height:1.6;margin-bottom:18px;">
                Hai richiesto il collegamento del tuo account Telegram a <strong>GestOre</strong> per ricevere le notifiche delle sostituzioni.
            </div>

            <div style="font-size:15px;color:#4b5563;line-height:1.6;margin-bottom:18px;">
                Per completare l\'associazione, fai clic sul pulsante seguente e avvia il bot Telegram.
            </div>

            <div style="margin:24px 0;">
                <a href="' . eh($telegramLink) . '" style="
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
            ">' . eh($telegramLink) . '</div>

            <div style="margin-top:18px;font-size:13px;color:#6b7280;line-height:1.5;">
                Il link è personale e ha una validità limitata.<br>
                Messaggio automatico <strong>GestOre</strong>.
            </div>
        </div>
    </div>
</body>
</html>';
}

try {
    $username = norm($__username ?? '');
    if ($username === '') {
        errorimportsost("TelegramSendLink: utente non autenticato");
        respond(['ok' => false, 'error' => 'Utente non autenticato'], 401);
    }

    infoimportsost("TelegramSendLink: richiesta link da username=[$username]");

    $qDoc = "
        SELECT id, cognome, nome, email, username
        FROM docente
        WHERE username = " . dbQ($username) . "
        LIMIT 1
    ";
    $doc = dbGetFirst($qDoc);

    if (!$doc) {
        errorimportsost("TelegramSendLink: docente non trovato per username=[$username]");
        respond(['ok' => false, 'error' => 'Docente non trovato'], 404);
    }

    $idDocente = (int)($doc['id'] ?? 0);
    $docenteNome = trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? ''));
    $email = norm($doc['email'] ?? '');

    if ($idDocente <= 0) {
        errorimportsost("TelegramSendLink: id docente non valido per username=[$username]");
        respond(['ok' => false, 'error' => 'Profilo docente non valido'], 500);
    }

    if ($email === '') {
        warningimportsost("TelegramSendLink: email mancante per docente id=$idDocente");
        respond(['ok' => false, 'error' => 'Email istituzionale non presente nel profilo docente'], 400);
    }

    global $__settings;
    $botUsername = norm($__settings->telegram->bot_username ?? '');

    if ($botUsername === '') {
        errorimportsost("TelegramSendLink: bot_username mancante in GestOre.json");
        respond(['ok' => false, 'error' => 'Configurazione bot Telegram mancante'], 500);
    }

    $token = generateTelegramLinkToken(48);
    $dataScadenza = date('Y-m-d H:i:s', strtotime('+2 days'));

    infoimportsost("TelegramSendLink: genero token per docente id=$idDocente scadenza=[$dataScadenza]");

    $qIns = "
        INSERT INTO docente_telegram_token (
            idDocente,
            token,
            dataCreazione,
            dataScadenza,
            usato
        ) VALUES (
            " . dbI($idDocente) . ",
            " . dbQ($token) . ",
            NOW(),
            " . dbQ($dataScadenza) . ",
            0
        )
    ";
    dbExec($qIns);

    $telegramLink = 'https://t.me/' . rawurlencode($botUsername) . '?start=' . rawurlencode($token);
    $subject = 'GestOre - Collegamento account Telegram';
    $html = buildTelegramLinkMailHtml($docenteNome, $telegramLink);

    infoimportsost("TelegramSendLink: invio mail a docente id=$idDocente email=[$email]");

    $mailOk = sendMail($email, $docenteNome, $subject, $html);

    if (!$mailOk) {
        errorimportsost("TelegramSendLink: invio mail fallito docente id=$idDocente email=[$email]");
        respond(['ok' => false, 'error' => 'Invio mail non riuscito'], 500);
    }

    infoimportsost("TelegramSendLink: mail inviata OK docente id=$idDocente email=[$email]");

    respond([
        'ok' => true,
        'message' => 'Ti abbiamo inviato una mail con il link per collegare Telegram.'
    ]);
} catch (Throwable $e) {
    errorimportsost("TelegramSendLink: eccezione " . $e->getMessage());
    respond([
        'ok' => false,
        'error' => 'Errore durante l\'invio del link Telegram: ' . $e->getMessage()
    ], 500);
}
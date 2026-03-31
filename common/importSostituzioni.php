<?php

/**
 * Import sostituzioni da JSON inviato via POST dal client Python
 * Nessuna sessione richiesta: endpoint pensato per uso locale/automatizzato
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/send-mail.php';
require_once __DIR__ . '/__Log.php';

header('Content-Type: application/json; charset=utf-8');

infoimportsost("==== AVVIO importSostituzioni.php ====");

/* =========================================================
   CONFIG
   ========================================================= */

$TELEGRAM_BOT_TOKEN      = trim((string)($__settings->telegram->bot_token ?? ''));
$IS_TELEGRAM_ENABLED     = (bool)($__settings->telegram->enabled ?? false);
$IS_MAIL_DOCENTE_ENABLED = (bool)($__settings->sostituzioni->inviaMailDocente ?? false);

infoimportsost(
    "CONFIG telegram_enabled=" . ($IS_TELEGRAM_ENABLED ? '1' : '0') .
    " mail_enabled=" . ($IS_MAIL_DOCENTE_ENABLED ? '1' : '0')
);

/* =========================================================
   HELPERS
   ========================================================= */

function formatDateItLong($ymd)
{
    $mesi = array(
        '01' => 'gennaio',
        '02' => 'febbraio',
        '03' => 'marzo',
        '04' => 'aprile',
        '05' => 'maggio',
        '06' => 'giugno',
        '07' => 'luglio',
        '08' => 'agosto',
        '09' => 'settembre',
        '10' => 'ottobre',
        '11' => 'novembre',
        '12' => 'dicembre'
    );

    $ymd = trim((string)$ymd);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return $ymd;
    }

    $parts = explode('-', $ymd);
    $y = $parts[0];
    $m = $parts[1];
    $d = $parts[2];

    return ltrim($d, '0') . ' ' . (isset($mesi[$m]) ? $mesi[$m] : $m) . ' ' . $y;
}

function formatDateIt($ymd)
{
    $ymd = trim((string)$ymd);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return $ymd;
    }

    $parts = explode('-', $ymd);
    return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
}

function buildMailSubjectSostituzione($evento, $data, $oraInizio)
{
    $dataIt = formatDateItLong($data);
    $ora = substr((string)$oraInizio, 0, 5);

    if ($evento === 'ANNULLAMENTO') {
        return "GestOre - Sostituzione annullata delle ore $ora del giorno $dataIt";
    }

    if ($evento === 'MODIFICA') {
        return "GestOre - Sostituzione modificata delle ore $ora del giorno $dataIt";
    }

    return "GestOre - Nuova sostituzione assegnata alle ore $ora del giorno $dataIt";
}

function eh($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function stripEmojiForLog($text)
{
    $text = (string)$text;

    $text = preg_replace('/[\x{1F000}-\x{1FAFF}]/u', '', $text);
    $text = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $text);

    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);

    return trim($text);
}

function respond($payload, $httpCode)
{
    infoimportsost("Risposta HTTP $httpCode - ok=" . (!empty($payload['ok']) ? '1' : '0'));
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function norm($v)
{
    return trim((string)$v);
}

function normalizeSpaces($s)
{
    $s = norm($s);
    return preg_replace('/\s+/u', ' ', $s);
}

function normalizeLatinChars($s)
{
    $s = (string)$s;

    $map = array(
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'à' => 'A', 'á' => 'A', 'â' => 'A', 'ã' => 'A', 'ä' => 'A', 'å' => 'A',

        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'è' => 'E', 'é' => 'E', 'ê' => 'E', 'ë' => 'E',

        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ì' => 'I', 'í' => 'I', 'î' => 'I', 'ï' => 'I',

        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'ò' => 'O', 'ó' => 'O', 'ô' => 'O', 'õ' => 'O', 'ö' => 'O',

        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ù' => 'U', 'ú' => 'U', 'û' => 'U', 'ü' => 'U',

        'Ç' => 'C', 'ç' => 'C',
        'Ñ' => 'N', 'ñ' => 'N'
    );

    return strtr($s, $map);
}

function normalizeTeacherKey($s)
{
    $s = normalizeSpaces($s);
    $s = normalizeLatinChars($s);
    $s = mb_strtoupper($s, 'UTF-8');

    $s = str_replace(array("’", "`", "´", "ʻ", "ʼ"), "'", $s);
    $s = str_replace(array("'", ".", "-"), " ", $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = trim($s);

    return $s;
}

function isValidDateYmd($s)
{
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s);
}

function normalizeTimeToHms($s)
{
    $s = norm($s);
    if ($s === '') return '';

    if (preg_match('/^\d{2}:\d{2}$/', $s)) {
        return $s . ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $s)) {
        return $s;
    }

    return '';
}

function normOra($o)
{
    $o = trim((string)$o);
    if ($o === '') return '';
    return substr($o, 0, 5);
}

function tableHasColumn($tableName, $columnName)
{
    $tableName = dbEscape($tableName);
    $columnName = dbEscape($columnName);

    $q = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
    $rows = dbGetAll($q);
    return is_array($rows) && count($rows) > 0;
}

function valueEq($a, $b)
{
    return norm((string)$a) === norm((string)$b);
}

function buildMailHtmlSostituzione($tipoEvento, $docenteDestinatario, $data, $oraInizio, $oraFine, $docenteSostituito, $classe, $aula, $materia)
{
    $headerBg = '#0f766e';
    $headerText = '#ffffff';
    $badgeBg = '#dff7f4';
    $badgeText = '#0f766e';
    $dataIt = formatDateIt($data);

    if ($tipoEvento === 'ANNULLAMENTO') {
        $titoloTop = 'SOSTITUZIONE ANNULLATA';
        $badge = 'NON PIU DA SVOLGERE';
        $intro = 'La sostituzione che ti era stata assegnata è stata annullata. Non è più richiesta la tua presenza per questa attività.';
        $headerBg = '#b45309';
        $badgeBg = '#fef3c7';
        $badgeText = '#9a3412';
    } elseif ($tipoEvento === 'MODIFICA') {
        $titoloTop = 'SOSTITUZIONE MODIFICATA';
        $badge = 'VERIFICA I NUOVI DETTAGLI';
        $intro = 'La sostituzione assegnata è stata modificata. Verifica attentamente i nuovi dettagli riportati qui sotto.';
        $headerBg = '#1d4ed8';
        $badgeBg = '#dbeafe';
        $badgeText = '#1e40af';
    } else {
        $titoloTop = 'NUOVA SOSTITUZIONE';
        $badge = 'SOSTITUZIONE ASSEGNATA';
        $intro = 'Ti è stata assegnata una nuova sostituzione.';
    }

    return '
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>' . eh($titoloTop) . '</title>
</head>
<body style="margin:0;padding:0;background:#f2f2f2;font-family:Arial,Helvetica,sans-serif;color:#2f3542;">
    <div style="max-width:920px;margin:0 auto;padding:20px 18px;">
        <div style="background:' . $headerBg . ';color:' . $headerText . ';border-radius:18px 18px 0 0;padding:22px 26px;font-size:22px;font-weight:700;letter-spacing:.3px;">
            ' . eh($titoloTop) . '
        </div>

        <div style="background:#f7f7f7;border:1px solid #e3e3e3;border-top:none;border-radius:0 0 18px 18px;padding:22px 26px 18px 26px;">
            <div style="font-size:18px;font-weight:700;color:#2d3340;margin-bottom:6px;">
                ' . eh($docenteDestinatario) . '
            </div>

            <div style="font-size:16px;color:#6b7280;margin-bottom:20px;">
                ' . eh($intro) . '
            </div>

            <hr style="border:none;border-top:1px solid #d9d9d9;margin:0 0 18px 0;">

            <div style="
                display:inline-block;
                background:' . $badgeBg . ';
                color:' . $badgeText . ';
                font-weight:700;
                border-radius:20px;
                padding:10px 18px;
                font-size:14px;
                letter-spacing:.4px;
                margin-bottom:16px;
            ">
                ' . eh($badge) . '
            </div>

            <div style="
                background:#f1f2f4;
                border:1px solid #d9dde3;
                border-radius:18px;
                padding:18px 18px 10px 18px;
            ">
                <table role="presentation" style="width:100%;border-collapse:collapse;font-size:16px;">
                    <tr>
                        <td style="width:34%;padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">Data</td>
                        <td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($dataIt) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">Ora</td>
                        <td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($oraInizio . ' - ' . $oraFine) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">Docente sostituito</td>
                        <td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($docenteSostituito) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">Classe</td>
                        <td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($classe !== '' ? $classe : '-') . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 12px;color:#6b7280;border-bottom:1px solid #d9dde3;">Aula</td>
                        <td style="padding:12px 12px;font-weight:700;color:#2d3340;border-bottom:1px solid #d9dde3;">' . eh($aula !== '' ? $aula : '-') . '</td>
                    </tr>
                    <tr>
                        <td style="padding:12px 12px;color:#6b7280;">Materia</td>
                        <td style="padding:12px 12px;font-weight:700;color:#2d3340;">' . eh($materia !== '' ? $materia : '-') . '</td>
                    </tr>
                </table>
            </div>

            <div style="margin-top:18px;font-size:15px;color:#4b5563;line-height:1.5;">
                Messaggio automatico <strong>GestOre</strong>.
            </div>
        </div>
    </div>
</body>
</html>';
}

/* =========================================================
   DOCENTI
   ========================================================= */

function buildDocentiMap()
{
    $map = array();

    $q = "
        SELECT id, cognome, nome, attivo, email
        FROM docente
    ";
    $rows = dbGetAll($q);

    if (!is_array($rows)) return array();

    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;

        if (isset($row['attivo']) && (string)$row['attivo'] !== '' && (int)$row['attivo'] === 0) {
            continue;
        }

        $cognome = norm($row['cognome'] ?? '');
        $nome = norm($row['nome'] ?? '');
        if ($cognome === '' || $nome === '') continue;

        $key = normalizeTeacherKey($cognome . ' ' . $nome);

        if (!isset($map[$key])) $map[$key] = array();
        $map[$key][] = $id;
    }

    return $map;
}

function findDocenteId($fullNamePdf, $docentiMap)
{
    $key = normalizeTeacherKey($fullNamePdf);

    if ($key === '') {
        return array('ok' => false, 'reason' => 'Nome docente vuoto', 'id' => null);
    }

    if (!isset($docentiMap[$key])) {
        return array('ok' => false, 'reason' => 'Docente non trovato', 'id' => null);
    }

    $ids = $docentiMap[$key];
    if (count($ids) > 1) {
        return array('ok' => false, 'reason' => 'Docente ambiguo', 'id' => null);
    }

    return array('ok' => true, 'reason' => '', 'id' => (int)$ids[0]);
}

function getDocenteById($idDocente)
{
    $idDocente = (int)$idDocente;
    if ($idDocente <= 0) return null;

    $q = "
        SELECT id, cognome, nome, email
        FROM docente
        WHERE id = " . dbI($idDocente) . "
        LIMIT 1
    ";

    return dbGetFirst($q);
}

function docenteFullName($doc)
{
    if (!$doc) return '';
    return trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? ''));
}

/* =========================================================
   TELEGRAM DOCENTI
   ========================================================= */

function getTelegramProfileByDocenteId($idDocente)
{
    $idDocente = (int)$idDocente;
    if ($idDocente <= 0) return null;

    $q = "
        SELECT *
        FROM docente_telegram
        WHERE idDocente = " . dbI($idDocente) . "
          AND attivo = 1
          AND consenso_notifiche = 1
        LIMIT 1
    ";

    return dbGetFirst($q);
}

function updateTelegramLastOk($idDocente)
{
    $q = "
        UPDATE docente_telegram
        SET ultimo_invio_ok = NOW(),
            ultimo_errore = NULL,
            ultimo_errore_data = NULL
        WHERE idDocente = " . dbI($idDocente) . "
    ";
    dbExec($q);
}

function updateTelegramLastError($idDocente, $msg)
{
    $q = "
        UPDATE docente_telegram
        SET ultimo_errore = " . dbQ(mb_substr((string)$msg, 0, 1000, 'UTF-8')) . ",
            ultimo_errore_data = NOW()
        WHERE idDocente = " . dbI($idDocente) . "
    ";
    dbExec($q);
}

function logTelegramEsito($idDocente, $idSostituzione, $tipoEvento, $messaggio, $esito, $rispostaApi)
{
    $messaggioLog = stripEmojiForLog($messaggio);
    $rispostaApiLog = stripEmojiForLog($rispostaApi);

    $idSostituzioneSql = ($idSostituzione > 0) ? dbI($idSostituzione) : 'NULL';

    $q = "
        INSERT INTO docente_telegram_log (
            idDocente,
            idSostituzione,
            tipo_evento,
            messaggio,
            esito,
            risposta_api,
            data_invio
        ) VALUES (
            " . dbI($idDocente) . ",
            $idSostituzioneSql,
            " . dbQ($tipoEvento) . ",
            " . dbQ($messaggioLog) . ",
            " . dbQ($esito) . ",
            " . dbQ($rispostaApiLog) . ",
            NOW()
        )
    ";
    dbExec($q);
}

function inviaTelegramDocente($botToken, $chatId, $text)
{
    $chatId = trim((string)$chatId);
    $botToken = trim((string)$botToken);

    if ($botToken === '') {
        return array('ok' => false, 'response' => 'bot token mancante');
    }

    if ($chatId === '') {
        return array('ok' => false, 'response' => 'chat_id mancante');
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $payload = array(
        'chat_id' => $chatId,
        'text' => $text
    );

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
        return array('ok' => false, 'response' => "cURL error: $error");
    }

    $data = json_decode($response, true);
    if (is_array($data) && !empty($data['ok'])) {
        return array('ok' => true, 'response' => $response);
    }

    return array('ok' => false, 'response' => ($response ? $response : 'Risposta Telegram vuota'));
}

/* =========================================================
   TELEGRAM ADMIN
   ========================================================= */

function getAdminTelegramDestinatariSostituzioni()
{
    $q = "
        SELECT DISTINCT
            telegram_user_id,
            telegram_chat_id,
            nome,
            username
        FROM admin_telegram
        WHERE attivo = 1
          AND notifiche_sostituzioni = 1
          AND telegram_chat_id IS NOT NULL
          AND telegram_chat_id <> ''
        ORDER BY nome
    ";

    $rows = dbGetAll($q);
    return is_array($rows) ? $rows : array();
}

function inviaTelegramChat($botToken, $chatId, $text)
{
    $botToken = trim((string)$botToken);
    $chatId   = trim((string)$chatId);
    $text     = trim((string)$text);

    if ($botToken === '') {
        return array('ok' => false, 'response' => 'bot token mancante');
    }
    if ($chatId === '') {
        return array('ok' => false, 'response' => 'chat_id mancante');
    }
    if ($text === '') {
        return array('ok' => false, 'response' => 'testo vuoto');
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $payload = array(
        'chat_id' => $chatId,
        'text'    => $text
    );

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
        return array('ok' => false, 'response' => "cURL error: $error");
    }

    $data = json_decode($response, true);
    if (is_array($data) && !empty($data['ok'])) {
        return array('ok' => true, 'response' => $response);
    }

    return array('ok' => false, 'response' => ($response ? $response : 'Risposta Telegram vuota'));
}

function buildAdminImportSummaryMessage($totaleRicevuti, $inseriti, $aggiornati, $annullati, $scartati, $notificheInviate)
{
    $lines = array();
    $lines[] = "IMPORT SOSTITUZIONI";
    $lines[] = "";
    $lines[] = "Totale righe ricevute: " . (int)$totaleRicevuti;
    $lines[] = "Inserite: " . (int)$inseriti;
    $lines[] = "Aggiornate: " . (int)$aggiornati;
    $lines[] = "Annullate: " . (int)$annullati;
    $lines[] = "Scartate: " . count($scartati);
    $lines[] = "Notifiche inviate a docenti: " . count($notificheInviate);

    if (!empty($scartati)) {
        $lines[] = "";
        $lines[] = "Prime righe scartate:";
        $max = min(5, count($scartati));
        for ($i = 0; $i < $max; $i++) {
            $r = $scartati[$i];
            $riga = (int)($r['riga'] ?? 0);
            $motivo = trim((string)($r['motivo'] ?? 'Motivo non specificato'));
            $lines[] = "- Riga {$riga}: {$motivo}";
        }
    }

    return implode("\n", $lines);
}

function buildAdminImportErrorMessage($msg)
{
    $msg = trim((string)$msg);
    if ($msg === '') $msg = 'Errore non specificato';

    return
        "IMPORT SOSTITUZIONI - ERRORE\n\n" .
        "Il processo di import si è interrotto.\n" .
        "Dettaglio:\n" .
        $msg;
}

function notificaAdminSostituzioni($botToken, $text)
{
    global $IS_TELEGRAM_ENABLED;

    if (!$IS_TELEGRAM_ENABLED) {
        infoimportsost("Notifiche admin Telegram disabilitate da config telegram.enabled");
        return array();
    }

    $text = trim((string)$text);
    if ($text === '') {
        return array();
    }

    $admins = getAdminTelegramDestinatariSostituzioni();

    if (empty($admins)) {
        infoimportsost("Nessun admin abilitato alle notifiche sostituzioni");
        return array();
    }

    $esiti = array();

    foreach ($admins as $admin) {
        $chatId = trim((string)($admin['telegram_chat_id'] ?? ''));
        $nome   = trim((string)($admin['nome'] ?? 'Admin'));

        if ($chatId === '') {
            continue;
        }

        $res = inviaTelegramChat($botToken, $chatId, $text);

        $esiti[] = array(
            'destinatario' => $nome,
            'chat_id' => $chatId,
            'ok' => !empty($res['ok']),
            'response' => $res['response'] ?? ''
        );
    }

    return $esiti;
}

/* =========================================================
   NOTIFICHE
   ========================================================= */

function notificaGiaInviata($idSostituzione, $idDocenteDestinatario, $evento, $canale)
{
    $q = "
        SELECT idNotifica
        FROM sostituzioni_notifiche
        WHERE idSostituzione = " . dbI($idSostituzione) . "
          AND idDocenteDestinatario = " . dbI($idDocenteDestinatario) . "
          AND evento = " . dbQ($evento) . "
          AND canale = " . dbQ($canale) . "
        LIMIT 1
    ";
    $row = dbGetFirst($q);
    return is_array($row) && !empty($row['idNotifica']);
}

function registraNotificaInviata($idSostituzione, $idDocenteDestinatario, $evento, $canale)
{
    $q = "
        INSERT IGNORE INTO sostituzioni_notifiche (
            idSostituzione,
            idDocenteDestinatario,
            evento,
            canale,
            dataInvio
        ) VALUES (
            " . dbI($idSostituzione) . ",
            " . dbI($idDocenteDestinatario) . ",
            " . dbQ($evento) . ",
            " . dbQ($canale) . ",
            NOW()
        )
    ";
    dbExec($q);
}

function inviaMailDocente($to, $toName, $subject, $htmlBody)
{
    global $IS_MAIL_DOCENTE_ENABLED;

    if (!$IS_MAIL_DOCENTE_ENABLED) {
        warningimportsost("MAIL disabilitata da config");
        return false;
    }

    $to = trim((string)$to);
    $toName = trim((string)$toName);

    if ($to === '') {
        warningimportsost("MAIL non inviata: destinatario vuoto");
        return false;
    }

    infoimportsost("Tentativo invio MAIL to=[$to] subj=[$subject]");

    return sendMail($to, $toName, $subject, $htmlBody);
}

function buildMessaggioAssegnazione($data, $oraInizio, $oraFine, $docenteSostituito, $classe, $aula, $materia)
{
    $dataIt = formatDateItLong($data);

    return
        "NUOVA SOSTITUZIONE ASSEGNATA\n\n" .
        "Data: $dataIt\n" .
        "Orario: $oraInizio - $oraFine\n" .
        "Docente sostituito: $docenteSostituito\n" .
        "Classe: " . ($classe !== '' ? $classe : '-') . "\n" .
        "Aula: " . ($aula !== '' ? $aula : '-') . "\n" .
        "Materia: " . ($materia !== '' ? $materia : '-') . "\n\n" .
        "Messaggio automatico GestOre.";
}

function buildMessaggioAnnullamento($data, $oraInizio, $oraFine, $docenteSostituito, $classe, $aula, $materia)
{
    $dataIt = formatDateItLong($data);

    return
        "SOSTITUZIONE ANNULLATA\n\n" .
        "La sostituzione non è più da svolgere.\n\n" .
        "Data: $dataIt\n" .
        "Orario: $oraInizio - $oraFine\n" .
        "Docente sostituito: $docenteSostituito\n" .
        "Classe: " . ($classe !== '' ? $classe : '-') . "\n" .
        "Aula: " . ($aula !== '' ? $aula : '-') . "\n" .
        "Materia: " . ($materia !== '' ? $materia : '-') . "\n\n" .
        "Non è richiesta la tua presenza.\n\n" .
        "GestOre";
}

function buildMessaggioModifica($data, $oraInizio, $oraFine, $docenteSostituito, $classe, $aula, $materia)
{
    $dataIt = formatDateItLong($data);

    return
        "SOSTITUZIONE MODIFICATA\n\n" .
        "La sostituzione è ancora valida ma sono cambiati alcuni dettagli.\n\n" .
        "Data: $dataIt\n" .
        "Orario: $oraInizio - $oraFine\n" .
        "Docente sostituito: $docenteSostituito\n" .
        "Classe: " . ($classe !== '' ? $classe : '-') . "\n" .
        "Aula: " . ($aula !== '' ? $aula : '-') . "\n" .
        "Materia: " . ($materia !== '' ? $materia : '-') . "\n\n" .
        "Verifica attentamente le modifiche.\n\n" .
        "GestOre";
}

function buildNotificationBody($evento, $data, $oraInizio, $oraFine, $docenteSostituito, $classe, $aula, $materia)
{
    $oraInizioShort = substr((string)$oraInizio, 0, 5);
    $oraFineShort = substr((string)$oraFine, 0, 5);

    if ($evento === 'ANNULLAMENTO') {
        return buildMessaggioAnnullamento($data, $oraInizioShort, $oraFineShort, $docenteSostituito, $classe, $aula, $materia);
    }

    if ($evento === 'MODIFICA') {
        return buildMessaggioModifica($data, $oraInizioShort, $oraFineShort, $docenteSostituito, $classe, $aula, $materia);
    }

    return buildMessaggioAssegnazione($data, $oraInizioShort, $oraFineShort, $docenteSostituito, $classe, $aula, $materia);
}

/* =========================================================
   INPUT JSON
   ========================================================= */

$raw = file_get_contents('php://input');
if (!$raw) {
    errorimportsost("Body JSON vuoto");
    respond(array(
        'ok' => false,
        'error' => 'Body JSON vuoto'
    ), 400);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    errorimportsost("JSON non valido");
    respond(array(
        'ok' => false,
        'error' => 'JSON non valido'
    ), 400);
}

$items = isset($data['items']) ? $data['items'] : null;
if (!is_array($items)) {
    errorimportsost("Campo items mancante o non valido");
    respond(array(
        'ok' => false,
        'error' => 'Campo items mancante o non valido'
    ), 400);
}

infoimportsost("Ricevuti " . count($items) . " item da importare");

/* =========================================================
   CHECK STRUTTURA TABELLA
   ========================================================= */

$hasDocSostPdf = tableHasColumn('sostituzioni', 'docenteSostitutoPdf');
$hasDocSostituitoPdf = tableHasColumn('sostituzioni', 'docenteSostituitoPdf');
$hasDataImport = tableHasColumn('sostituzioni', 'dataImport');
$hasStato = tableHasColumn('sostituzioni', 'stato');

infoimportsost(
    "Check tabella sostituzioni: hasDocSostPdf=" . ($hasDocSostPdf ? '1' : '0') .
    " hasDocSostituitoPdf=" . ($hasDocSostituitoPdf ? '1' : '0') .
    " hasDataImport=" . ($hasDataImport ? '1' : '0') .
    " hasStato=" . ($hasStato ? '1' : '0')
);

/* =========================================================
   PRECARICO DOCENTI
   ========================================================= */

$docentiMap = buildDocentiMap();

if (empty($docentiMap)) {
    errorimportsost("Nessun docente disponibile o tabella docente non leggibile");
    respond(array(
        'ok' => false,
        'error' => 'Nessun docente disponibile o tabella docente non leggibile'
    ), 500);
}

infoimportsost("Docenti caricati in mappa: " . count($docentiMap));

/* =========================================================
   PRECARICO SOSTITUZIONI ATTIVE ESISTENTI
   ========================================================= */

function todayYmd()
{
    $dt = new DateTime('now', new DateTimeZone('Europe/Rome'));
    return $dt->format('Y-m-d');
}

/* =========================================================
   PRECARICO SOSTITUZIONI ATTIVE ESISTENTI (SOLO OGGI)
   ========================================================= */

$dataImportOggi = todayYmd();

$whereStatoAttive = $hasStato ? " AND (stato IS NULL OR stato <> 'ANNULLATA') " : "";

$qSostAttive = "
    SELECT *
    FROM sostituzioni
    WHERE data = " . dbQ($dataImportOggi) . "
    $whereStatoAttive
";

$sostituzioniAttiveRows = dbGetAll($qSostAttive);
if (!is_array($sostituzioniAttiveRows)) $sostituzioniAttiveRows = array();

infoimportsost("Precaricate sostituzioni attive del giorno [$dataImportOggi]: " . count($sostituzioniAttiveRows));

$existingByNaturalKey = array();
$existingBySlotDocente = array();

foreach ($sostituzioniAttiveRows as $row) {
    $idS = (int)($row['idSostituzione'] ?? 0);
    if ($idS <= 0) continue;

    $dataS = norm($row['data'] ?? '');
    $oraInS = normalizeTimeToHms($row['oraInizio'] ?? '');
    $oraFineS = normalizeTimeToHms($row['oraFine'] ?? '');
    $idDocSostituitoS = (int)($row['idDocenteSostituito'] ?? 0);
    $classeS = norm($row['classe'] ?? '');
    $aulaS = norm($row['aula'] ?? '');

    $naturalKey = implode('|', array($dataS, $oraInS, $oraFineS, $idDocSostituitoS, $classeS, $aulaS));
    $existingByNaturalKey[$naturalKey] = $row;

    $slotKey = implode('|', array($dataS, $oraInS, $oraFineS, $idDocSostituitoS));
    if (!isset($existingBySlotDocente[$slotKey])) {
        $existingBySlotDocente[$slotKey] = array();
    }
    $existingBySlotDocente[$slotKey][] = $row;
}

/* =========================================================
   IMPORT
   ========================================================= */

$totaleRicevuti = count($items);
$inseriti = 0;
$aggiornati = 0;
$annullati = 0;
$scartati = array();
$preview = array();
$notificheDaInviare = array();
$notificheInviate = array();
$debugNotifiche = array();

$seenActiveIds = array();

infoimportsost("START TRANSACTION");
dbExec("START TRANSACTION");

try {
    foreach ($items as $idx => $item) {
        $riga = $idx + 1;

        $dataVal = norm($item['data'] ?? '');
        $oraInizio = normalizeTimeToHms($item['oraInizio'] ?? '');
        $oraFine = normalizeTimeToHms($item['oraFine'] ?? '');
        $docenteSostitutoPdf = normalizeSpaces($item['docenteSostituto'] ?? '');
        $docenteSostituitoPdf = normalizeSpaces($item['docenteSostituito'] ?? '');
        $materia = normalizeSpaces($item['materia'] ?? '');
        $classe = normalizeSpaces($item['classe'] ?? '');
        $aula = normalizeSpaces($item['aula'] ?? '');

        infoimportsost("Riga $riga letta: data=[$dataVal] ora=[$oraInizio-$oraFine] sostituto=[$docenteSostitutoPdf] sostituito=[$docenteSostituitoPdf] classe=[$classe] aula=[$aula] materia=[$materia]");

        if ($dataVal === '' || $oraInizio === '' || $oraFine === '' || $docenteSostitutoPdf === '' || $docenteSostituitoPdf === '') {
            warningimportsost("Riga $riga scartata: campi obbligatori mancanti o orari non validi");
            $scartati[] = array(
                'riga' => $riga,
                'motivo' => 'Campi obbligatori mancanti o orari non validi',
                'item' => $item
            );
            continue;
        }

        if (!isValidDateYmd($dataVal)) {
            warningimportsost("Riga $riga scartata: data non valida [$dataVal]");
            $scartati[] = array(
                'riga' => $riga,
                'motivo' => 'Data non valida, atteso formato YYYY-MM-DD',
                'item' => $item
            );
            continue;
        }

        $matchSostituto = findDocenteId($docenteSostitutoPdf, $docentiMap);
        if (!$matchSostituto['ok']) {
            warningimportsost("Riga $riga scartata: docente sostituto non trovato/ambiguo [$docenteSostitutoPdf] motivo=[" . $matchSostituto['reason'] . "]");
            $scartati[] = array(
                'riga' => $riga,
                'motivo' => 'Docente sostituto: ' . $matchSostituto['reason'],
                'docente' => $docenteSostitutoPdf,
                'item' => $item
            );
            continue;
        }

        $matchSostituito = findDocenteId($docenteSostituitoPdf, $docentiMap);
        if (!$matchSostituito['ok']) {
            warningimportsost("Riga $riga scartata: docente sostituito non trovato/ambiguo [$docenteSostituitoPdf] motivo=[" . $matchSostituito['reason'] . "]");
            $scartati[] = array(
                'riga' => $riga,
                'motivo' => 'Docente sostituito: ' . $matchSostituito['reason'],
                'docente' => $docenteSostituitoPdf,
                'item' => $item
            );
            continue;
        }

        $idDocenteSostituto = (int)$matchSostituto['id'];
        $idDocenteSostituito = (int)$matchSostituito['id'];

        $naturalKey = implode('|', array($dataVal, $oraInizio, $oraFine, $idDocenteSostituito, $classe, $aula));
        $slotKey = implode('|', array($dataVal, $oraInizio, $oraFine, $idDocenteSostituito));

        $exists = isset($existingByNaturalKey[$naturalKey]) ? $existingByNaturalKey[$naturalKey] : null;

        if ($exists) {
            $idSostituzione = (int)$exists['idSostituzione'];
            $seenActiveIds[$idSostituzione] = true;

            $oldIdDocenteSostituto = (int)($exists['idDocenteSostituto'] ?? 0);

            $stessaAssegnazione =
                $oldIdDocenteSostituto === $idDocenteSostituto &&
                valueEq($exists['materia'] ?? '', $materia) &&
                valueEq($exists['classe'] ?? '', $classe) &&
                valueEq($exists['aula'] ?? '', $aula) &&
                normOra($exists['oraInizio'] ?? '') === substr($oraInizio, 0, 5) &&
                normOra($exists['oraFine'] ?? '') === substr($oraFine, 0, 5);

            if (!$stessaAssegnazione) {
                $fields = array(
                    "idDocenteSostituto = " . dbI($idDocenteSostituto),
                    "materia = " . dbQ($materia),
                    "classe = " . dbQ($classe),
                    "aula = " . dbQ($aula)
                );

                if ($hasDocSostPdf) {
                    $fields[] = "docenteSostitutoPdf = " . dbQ($docenteSostitutoPdf);
                }
                if ($hasDocSostituitoPdf) {
                    $fields[] = "docenteSostituitoPdf = " . dbQ($docenteSostituitoPdf);
                }
                if ($hasDataImport) {
                    $fields[] = "dataImport = NOW()";
                }
                if ($hasStato) {
                    $fields[] = "stato = 'ATTIVA'";
                }

                $q = "
                    UPDATE sostituzioni
                    SET " . implode(",\n", $fields) . "
                    WHERE idSostituzione = " . dbI($idSostituzione);

                dbExec($q);
                $aggiornati++;

                if ($oldIdDocenteSostituto > 0 && $oldIdDocenteSostituto !== $idDocenteSostituto) {
                    $notificheDaInviare[] = array(
                        'idSostituzione' => $idSostituzione,
                        'idDocente' => $oldIdDocenteSostituto,
                        'evento' => 'ANNULLAMENTO',
                        'data' => $dataVal,
                        'oraInizio' => $oraInizio,
                        'oraFine' => $oraFine,
                        'docenteSostituito' => $docenteSostituitoPdf,
                        'classe' => $classe,
                        'aula' => $aula,
                        'materia' => $materia
                    );

                    $notificheDaInviare[] = array(
                        'idSostituzione' => $idSostituzione,
                        'idDocente' => $idDocenteSostituto,
                        'evento' => 'ASSEGNAZIONE',
                        'data' => $dataVal,
                        'oraInizio' => $oraInizio,
                        'oraFine' => $oraFine,
                        'docenteSostituito' => $docenteSostituitoPdf,
                        'classe' => $classe,
                        'aula' => $aula,
                        'materia' => $materia
                    );
                } else {
                    $notificheDaInviare[] = array(
                        'idSostituzione' => $idSostituzione,
                        'idDocente' => $idDocenteSostituto,
                        'evento' => 'MODIFICA',
                        'data' => $dataVal,
                        'oraInizio' => $oraInizio,
                        'oraFine' => $oraFine,
                        'docenteSostituito' => $docenteSostituitoPdf,
                        'classe' => $classe,
                        'aula' => $aula,
                        'materia' => $materia
                    );
                }
            }
        } else {
            $matchedBySlot = null;
            $slotRows = isset($existingBySlotDocente[$slotKey]) ? $existingBySlotDocente[$slotKey] : array();

            if (!empty($slotRows)) {
                foreach ($slotRows as $candidate) {
                    $candidateId = (int)($candidate['idSostituzione'] ?? 0);
                    if ($candidateId > 0 && !isset($seenActiveIds[$candidateId])) {
                        $matchedBySlot = $candidate;
                        break;
                    }
                }
            }

            if ($matchedBySlot) {
                $idSostituzione = (int)$matchedBySlot['idSostituzione'];
                $seenActiveIds[$idSostituzione] = true;
                $oldIdDocenteSostituto = (int)($matchedBySlot['idDocenteSostituto'] ?? 0);

                $fields = array(
                    "idDocenteSostituto = " . dbI($idDocenteSostituto),
                    "materia = " . dbQ($materia),
                    "classe = " . dbQ($classe),
                    "aula = " . dbQ($aula)
                );

                if ($hasDocSostPdf) {
                    $fields[] = "docenteSostitutoPdf = " . dbQ($docenteSostitutoPdf);
                }
                if ($hasDocSostituitoPdf) {
                    $fields[] = "docenteSostituitoPdf = " . dbQ($docenteSostituitoPdf);
                }
                if ($hasDataImport) {
                    $fields[] = "dataImport = NOW()";
                }
                if ($hasStato) {
                    $fields[] = "stato = 'ATTIVA'";
                }

                $q = "
                    UPDATE sostituzioni
                    SET " . implode(",\n", $fields) . "
                    WHERE idSostituzione = " . dbI($idSostituzione);

                dbExec($q);
                $aggiornati++;

                if ($oldIdDocenteSostituto > 0 && $oldIdDocenteSostituto !== $idDocenteSostituto) {
                    $notificheDaInviare[] = array(
                        'idSostituzione' => $idSostituzione,
                        'idDocente' => $oldIdDocenteSostituto,
                        'evento' => 'ANNULLAMENTO',
                        'data' => $dataVal,
                        'oraInizio' => $oraInizio,
                        'oraFine' => $oraFine,
                        'docenteSostituito' => $docenteSostituitoPdf,
                        'classe' => $classe,
                        'aula' => $aula,
                        'materia' => $materia
                    );

                    $notificheDaInviare[] = array(
                        'idSostituzione' => $idSostituzione,
                        'idDocente' => $idDocenteSostituto,
                        'evento' => 'ASSEGNAZIONE',
                        'data' => $dataVal,
                        'oraInizio' => $oraInizio,
                        'oraFine' => $oraFine,
                        'docenteSostituito' => $docenteSostituitoPdf,
                        'classe' => $classe,
                        'aula' => $aula,
                        'materia' => $materia
                    );
                } else {
                    $notificheDaInviare[] = array(
                        'idSostituzione' => $idSostituzione,
                        'idDocente' => $idDocenteSostituto,
                        'evento' => 'MODIFICA',
                        'data' => $dataVal,
                        'oraInizio' => $oraInizio,
                        'oraFine' => $oraFine,
                        'docenteSostituito' => $docenteSostituitoPdf,
                        'classe' => $classe,
                        'aula' => $aula,
                        'materia' => $materia
                    );
                }
            } else {
                $insertCols = array(
                    'data',
                    'oraInizio',
                    'oraFine',
                    'idDocenteSostituto',
                    'idDocenteSostituito',
                    'materia',
                    'classe',
                    'aula'
                );

                $insertVals = array(
                    dbQ($dataVal),
                    dbQ($oraInizio),
                    dbQ($oraFine),
                    dbI($idDocenteSostituto),
                    dbI($idDocenteSostituito),
                    dbQ($materia),
                    dbQ($classe),
                    dbQ($aula)
                );

                if ($hasDocSostPdf) {
                    $insertCols[] = 'docenteSostitutoPdf';
                    $insertVals[] = dbQ($docenteSostitutoPdf);
                }
                if ($hasDocSostituitoPdf) {
                    $insertCols[] = 'docenteSostituitoPdf';
                    $insertVals[] = dbQ($docenteSostituitoPdf);
                }
                if ($hasDataImport) {
                    $insertCols[] = 'dataImport';
                    $insertVals[] = 'NOW()';
                }
                if ($hasStato) {
                    $insertCols[] = 'stato';
                    $insertVals[] = dbQ('ATTIVA');
                }

                $q = "
                    INSERT INTO sostituzioni (" . implode(', ', $insertCols) . ")
                    VALUES (" . implode(', ', $insertVals) . ")
                ";

                dbExec($q);
                $idSostituzione = dblastId();
                $seenActiveIds[$idSostituzione] = true;
                $inseriti++;

                $notificheDaInviare[] = array(
                    'idSostituzione' => $idSostituzione,
                    'idDocente' => $idDocenteSostituto,
                    'evento' => 'ASSEGNAZIONE',
                    'data' => $dataVal,
                    'oraInizio' => $oraInizio,
                    'oraFine' => $oraFine,
                    'docenteSostituito' => $docenteSostituitoPdf,
                    'classe' => $classe,
                    'aula' => $aula,
                    'materia' => $materia
                );
            }
        }

        if (count($preview) < 15) {
            $preview[] = array(
                'data' => $dataVal,
                'oraInizio' => $oraInizio,
                'oraFine' => $oraFine,
                'idDocenteSostituto' => $idDocenteSostituto,
                'idDocenteSostituito' => $idDocenteSostituito,
                'docenteSostituto' => $docenteSostitutoPdf,
                'docenteSostituito' => $docenteSostituitoPdf,
                'materia' => $materia,
                'classe' => $classe,
                'aula' => $aula
            );
        }
    }

    foreach ($sostituzioniAttiveRows as $oldRow) {
        $idSostituzioneOld = (int)($oldRow['idSostituzione'] ?? 0);
        if ($idSostituzioneOld <= 0) continue;
        if (isset($seenActiveIds[$idSostituzioneOld])) continue;

        $fieldsCancel = array();

        if ($hasStato) {
            $fieldsCancel[] = "stato = 'ANNULLATA'";
        }
        if ($hasDataImport) {
            $fieldsCancel[] = "dataImport = NOW()";
        }
        if (empty($fieldsCancel)) {
            $fieldsCancel[] = "materia = materia";
        }

        $q = "
            UPDATE sostituzioni
            SET " . implode(", ", $fieldsCancel) . "
            WHERE idSostituzione = " . dbI($idSostituzioneOld);

        dbExec($q);
        $annullati++;

        $idDocenteOld = (int)($oldRow['idDocenteSostituto'] ?? 0);
        if ($idDocenteOld > 0) {
            $notificheDaInviare[] = array(
                'idSostituzione' => $idSostituzioneOld,
                'idDocente' => $idDocenteOld,
                'evento' => 'ANNULLAMENTO',
                'data' => norm($oldRow['data'] ?? ''),
                'oraInizio' => normalizeTimeToHms($oldRow['oraInizio'] ?? ''),
                'oraFine' => normalizeTimeToHms($oldRow['oraFine'] ?? ''),
                'docenteSostituito' => norm($oldRow['docenteSostituitoPdf'] ?? ''),
                'classe' => norm($oldRow['classe'] ?? ''),
                'aula' => norm($oldRow['aula'] ?? ''),
                'materia' => norm($oldRow['materia'] ?? '')
            );
        }
    }

    dbExec("COMMIT");

    foreach ($notificheDaInviare as $n) {
        $idSostituzione = (int)$n['idSostituzione'];
        $idDocenteDest = (int)$n['idDocente'];
        $evento = $n['evento'];

        $doc = getDocenteById($idDocenteDest);
        if (!$doc) continue;

        $nomeDest = docenteFullName($doc);
        $email = trim((string)($doc['email'] ?? ''));

        $mailGiaInviata = ($idSostituzione > 0) ? notificaGiaInviata($idSostituzione, $idDocenteDest, $evento, 'MAIL') : false;
        $tgGiaInviata = ($idSostituzione > 0) ? notificaGiaInviata($idSostituzione, $idDocenteDest, $evento, 'TELEGRAM') : false;

        $subject = buildMailSubjectSostituzione($evento, $n['data'], $n['oraInizio']);
        $body = buildNotificationBody(
            $evento,
            $n['data'],
            $n['oraInizio'],
            $n['oraFine'],
            $n['docenteSostituito'],
            $n['classe'],
            $n['aula'],
            $n['materia']
        );

        $mailHtml = buildMailHtmlSostituzione(
            $evento,
            $nomeDest,
            $n['data'],
            substr((string)$n['oraInizio'], 0, 5),
            substr((string)$n['oraFine'], 0, 5),
            $n['docenteSostituito'],
            $n['classe'],
            $n['aula'],
            $n['materia']
        );

        if ($email !== '' && !$mailGiaInviata) {
            $mailOk = inviaMailDocente($email, $nomeDest, $subject, $mailHtml);

            if ($mailOk) {
                if ($idSostituzione > 0) {
                    registraNotificaInviata($idSostituzione, $idDocenteDest, $evento, 'MAIL');
                }
                $notificheInviate[] = array(
                    'docente' => $nomeDest,
                    'canale' => 'MAIL',
                    'evento' => $evento
                );
            }
        }

        if ($IS_TELEGRAM_ENABLED && !$tgGiaInviata) {
            $tg = getTelegramProfileByDocenteId($idDocenteDest);

            $chatIdToUse = '';
            $canSendTelegram = false;

            if ($tg && !empty($tg['telegram_chat_id'])) {
                $chatIdToUse = trim((string)$tg['telegram_chat_id']);
                $canSendTelegram = true;
            }

            if ($canSendTelegram) {
                $resTg = inviaTelegramDocente(
                    $TELEGRAM_BOT_TOKEN,
                    $chatIdToUse,
                    $body
                );

                logTelegramEsito(
                    $idDocenteDest,
                    $idSostituzione,
                    $evento,
                    $body,
                    ($resTg['ok'] ? 'OK' : 'ERRORE'),
                    $resTg['response']
                );

                if ($resTg['ok']) {
                    if ($idSostituzione > 0) {
                        registraNotificaInviata($idSostituzione, $idDocenteDest, $evento, 'TELEGRAM');
                    }

                    if ($tg) {
                        updateTelegramLastOk($idDocenteDest);
                    }

                    $notificheInviate[] = array(
                        'docente' => $nomeDest,
                        'canale' => 'TELEGRAM',
                        'evento' => $evento
                    );
                } else {
                    if ($tg) {
                        updateTelegramLastError($idDocenteDest, $resTg['response']);
                    }
                }
            }
        }
    }

    $msgAdmin = buildAdminImportSummaryMessage(
        $totaleRicevuti,
        $inseriti,
        $aggiornati,
        $annullati,
        $scartati,
        $notificheInviate
    );

    $esitiAdmin = notificaAdminSostituzioni($TELEGRAM_BOT_TOKEN, $msgAdmin);

    if (!empty($esitiAdmin)) {
        infoimportsost("Notifiche admin inviate: " . json_encode($esitiAdmin, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    infoimportsost("Import completato: totaleRicevuti=$totaleRicevuti inseriti=$inseriti aggiornati=$aggiornati annullati=$annullati scartati=" . count($scartati) . " notificheInviate=" . count($notificheInviate));

    respond(array(
        'ok' => true,
        'totaleRicevuti' => $totaleRicevuti,
        'inseriti' => $inseriti,
        'aggiornati' => $aggiornati,
        'annullati' => $annullati,
        'scartati' => count($scartati),
        'dettaglioScartati' => $scartati,
        'preview' => $preview,
        'notificheInviate' => $notificheInviate,
        'debugNotifiche' => $debugNotifiche
    ), 200);
} catch (Throwable $e) {
    dbExec("ROLLBACK");
    errorimportsost("Eccezione durante import: " . $e->getMessage());
    errorimportsost("ROLLBACK eseguito");

    $msgAdminErrore = buildAdminImportErrorMessage($e->getMessage());
    $esitiAdminErrore = notificaAdminSostituzioni($TELEGRAM_BOT_TOKEN, $msgAdminErrore);

    if (!empty($esitiAdminErrore)) {
        infoimportsost("Notifiche admin errore inviate: " . json_encode($esitiAdminErrore, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    respond(array(
        'ok' => false,
        'error' => 'Eccezione durante import: ' . $e->getMessage()
    ), 500);
}
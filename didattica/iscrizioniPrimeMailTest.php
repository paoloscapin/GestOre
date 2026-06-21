<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
require_once '../common/send-mail.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

$id = intval($_POST['id'] ?? 0);
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_POST['tipo_iscrizione'] ?? 'prime');

try {
    $cfg = iscrizioniPrimeMailConfig();
    if (empty($cfg['enabled']) || empty($cfg['accounts'])) {
        throw new Exception('Invio mail iscrizioni non configurato.');
    }

    $where = $id > 0
        ? 'id = ' . dbI($id)
        : "tipo_iscrizione = " . dbQ($tipoIscrizione) . " AND studente_interno = 0 AND (email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL)";

    $pratica = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE $where
          AND tipo_iscrizione = " . dbQ($tipoIscrizione) . "
        ORDER BY cognome ASC, nome ASC
        LIMIT 1
    ");
    if (!$pratica) {
        throw new Exception('Nessuna pratica con email disponibile per il test.');
    }

    $recipients = iscrizioniPrimeMailRecipientsForPratica($pratica);
    if (!$recipients) {
        throw new Exception('La pratica selezionata non ha destinatari validi.');
    }

    $account = $cfg['accounts'][0];
    $token = iscrizioniPrimeSetToken((int)$pratica['id']);
    $link = ($GLOBALS['__http_base_link'] ?? '') . '/iscrizioni/conferma.php?t=' . rawurlencode($token);
    $body = iscrizioniPrimeMailBody($pratica, $link, $recipients[0]);
    $template = iscrizioniPrimeMailTemplate($tipoIscrizione);
    $subject = trim((string)($template['subject'] ?? ''));
    if ($subject === '') {
        $subject = iscrizioniPrimeMailSubject($pratica);
    }

    $ok = sendMailCustom($account['email'], 'Test iscrizioni', $subject, $body, [
        'from_email' => $account['email'],
        'from_name' => $cfg['fromName'],
        'reply_to_email' => $cfg['replyToEmail'] !== '' ? $cfg['replyToEmail'] : $account['email'],
        'reply_to_name' => $cfg['replyToName'],
        'sender_email' => $account['email'],
        'sender_name' => $cfg['fromName'],
        'smtp_host' => $cfg['smtpHost'],
        'smtp_username' => $account['email'],
        'smtp_password' => $account['password'],
        'smtp_secure' => $cfg['SMTPSecure'],
        'smtp_port' => $cfg['Port'],
        'attachments' => iscrizioniPrimeMailAttachmentPaths($tipoIscrizione),
    ]);

    echo json_encode([
        'ok' => (bool)$ok,
        'message' => $ok ? 'Mail di test inviata.' : 'Invio mail di test non riuscito.',
        'to' => $account['email'],
        'original_recipient' => $recipients[0],
        'student' => trim((string)(($pratica['cognome'] ?? '') . ' ' . ($pratica['nome'] ?? ''))),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

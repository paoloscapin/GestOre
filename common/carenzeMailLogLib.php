<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/send-mail.php';

function carenzeMailLogEnsureTable(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS carenze_mail_log (
            id int(11) NOT NULL AUTO_INCREMENT,
            carenza_id int(11) NOT NULL,
            student_id int(11) NOT NULL,
            account_email varchar(190) NOT NULL,
            from_email varchar(190) NULL,
            to_email varchar(190) NOT NULL,
            cc_emails text NULL,
            subject varchar(255) NULL,
            log_token varchar(64) NOT NULL,
            transport varchar(50) NULL,
            gmail_message_id varchar(190) NULL,
            status varchar(30) NOT NULL DEFAULT 'pending',
            error_message text NULL,
            bounce_type varchar(40) NULL,
            bounce_reason text NULL,
            bounce_message_id varchar(190) NULL,
            bounce_snippet text NULL,
            sent_at datetime NULL,
            checked_at datetime NULL,
            bounced_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_carenze_mail_log_token (log_token),
            KEY idx_carenze_mail_log_carenza (carenza_id),
            KEY idx_carenze_mail_log_account_status (account_email, status),
            KEY idx_carenze_mail_log_sent (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function carenzeMailLogCreate(int $carenzaId, int $studentId, string $accountEmail, string $fromEmail, string $toEmail, array $ccEmails, string $subject): array
{
    carenzeMailLogEnsureTable();

    $token = bin2hex(random_bytes(16));
    dbExec("
        INSERT INTO carenze_mail_log
            (carenza_id, student_id, account_email, from_email, to_email, cc_emails, subject, log_token, status)
        VALUES
            (" . dbI($carenzaId) . ",
             " . dbI($studentId) . ",
             " . dbQ(strtolower(trim($accountEmail))) . ",
             " . dbQ($fromEmail) . ",
             " . dbQ(strtolower(trim($toEmail))) . ",
             " . dbQ(json_encode(array_values($ccEmails), JSON_UNESCAPED_UNICODE)) . ",
             " . dbQ($subject) . ",
             " . dbQ($token) . ",
             'pending')
    ");

    return [
        'id' => dblastId(),
        'token' => $token,
    ];
}

function carenzeMailLogUpdateSent(int $logId, bool $ok, array $dispatchResult, string $errorMessage = ''): void
{
    carenzeMailLogEnsureTable();

    $status = $ok ? 'sent' : 'send_error';
    $sentAt = $ok ? date('Y-m-d H:i:s') : null;
    if ($errorMessage === '') {
        $errorMessage = (string)($dispatchResult['error'] ?? '');
    }

    dbExec("
        UPDATE carenze_mail_log
        SET status = " . dbQ($status) . ",
            transport = " . dbQ((string)($dispatchResult['transport'] ?? '')) . ",
            gmail_message_id = " . dbQ((string)($dispatchResult['gmail_message_id'] ?? '')) . ",
            error_message = " . dbQ($errorMessage) . ",
            sent_at = " . dbQ($sentAt) . "
        WHERE id = " . dbI($logId) . "
        LIMIT 1
    ");
}

function carenzeMailLogClassifyBounce(string $subject, string $snippet, string $body): array
{
    $text = strtolower($subject . "\n" . $snippet . "\n" . $body);

    $limitPatterns = [
        'daily user sending limit exceeded',
        'user-rate limit exceeded',
        'rate limit exceeded',
        'too many messages',
        'you have reached a limit for sending mail',
        '550 5.4.5',
        '550-5.4.5',
        'mail sending limit',
        'limite di invio',
        'hai raggiunto il limite',
    ];
    foreach ($limitPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return ['type' => 'quota_limit', 'reason' => 'Possibile superamento del limite giornaliero di invio'];
        }
    }

    $mailboxPatterns = [
        'mailbox full',
        'over quota',
        'quota exceeded',
        'mailbox is full',
        'casella piena',
        'spazio esaurito',
    ];
    foreach ($mailboxPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return ['type' => 'mailbox_full', 'reason' => 'Casella destinatario piena'];
        }
    }

    $invalidPatterns = [
        'address not found',
        'user unknown',
        'no such user',
        'recipient address rejected',
        'invalid recipient',
        'does not exist',
        'indirizzo non trovato',
        'utente sconosciuto',
    ];
    foreach ($invalidPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return ['type' => 'invalid_recipient', 'reason' => 'Indirizzo destinatario errato o inesistente'];
        }
    }

    return ['type' => 'other_bounce', 'reason' => 'Mancata consegna non classificata automaticamente'];
}

function carenzeMailLogFindByBounceText(string $text, string $accountEmail): ?array
{
    carenzeMailLogEnsureTable();

    if (preg_match('/X-GestOre-Carenza-Log-Token:\s*([a-f0-9]{32})/i', $text, $m)) {
        $row = dbGetFirst("
            SELECT *
            FROM carenze_mail_log
            WHERE log_token = " . dbQ(strtolower($m[1])) . "
            LIMIT 1
        ");
        if ($row) {
            return $row;
        }
    }

    $accountEmail = strtolower(trim($accountEmail));
    foreach (dbGetAll("
        SELECT *
        FROM carenze_mail_log
        WHERE account_email = " . dbQ($accountEmail) . "
          AND status IN ('sent', 'bounce')
          AND sent_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        ORDER BY sent_at DESC
        LIMIT 500
    ") as $row) {
        $emails = [strtolower((string)$row['to_email'])];
        $cc = json_decode((string)($row['cc_emails'] ?? '[]'), true);
        if (is_array($cc)) {
            foreach ($cc as $email) {
                $emails[] = strtolower(trim((string)$email));
            }
        }
        foreach ($emails as $email) {
            if ($email !== '' && stripos($text, $email) !== false) {
                return $row;
            }
        }
    }

    return null;
}

function carenzeMailLogMarkBounce(int $logId, string $type, string $reason, string $gmailMessageId, string $snippet): void
{
    carenzeMailLogEnsureTable();

    dbExec("
        UPDATE carenze_mail_log
        SET status = 'bounce',
            bounce_type = " . dbQ($type) . ",
            bounce_reason = " . dbQ($reason) . ",
            bounce_message_id = " . dbQ($gmailMessageId) . ",
            bounce_snippet = " . dbQ($snippet) . ",
            bounced_at = NOW(),
            checked_at = NOW()
        WHERE id = " . dbI($logId) . "
        LIMIT 1
    ");
}

function carenzeMailLogStudentLabel(array $logRow): string
{
    $studentId = intval($logRow['student_id'] ?? 0);
    if ($studentId <= 0) {
        return '';
    }
    $row = dbGetFirst("
        SELECT cognome, nome
        FROM studente
        WHERE id = " . dbI($studentId) . "
        LIMIT 1
    ");
    if (!$row) {
        return '';
    }
    return trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
}

function carenzeMailLogMarkChecked(array $logIds): void
{
    carenzeMailLogEnsureTable();
    $ids = array_values(array_filter(array_map('intval', $logIds), static fn($id) => $id > 0));
    if (!$ids) {
        return;
    }
    dbExec("
        UPDATE carenze_mail_log
        SET checked_at = NOW()
        WHERE id IN (" . implode(',', $ids) . ")
    ");
}

function carenzeMailGmailApiRequestAs(string $accountEmail, string $method, string $url, $body = null): array
{
    $accessToken = sendMailOAuthAccessToken($accountEmail, 'https://www.googleapis.com/auth/gmail.readonly');

    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Errore CURL Gmail bounce check: ' . $curlError);
    }
    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
        throw new Exception('Errore Gmail bounce check HTTP ' . $httpCode . ': ' . $response);
    }
    return $decoded;
}

function carenzeMailGmailHeader(array $message, string $name): string
{
    foreach (($message['payload']['headers'] ?? []) as $header) {
        if (strcasecmp((string)($header['name'] ?? ''), $name) === 0) {
            return (string)($header['value'] ?? '');
        }
    }
    return '';
}

function carenzeMailGmailDecode($data): string
{
    $data = strtr((string)$data, '-_', '+/');
    return (string)base64_decode($data);
}

function carenzeMailGmailExtractText(array $payload): string
{
    $chunks = [];
    if (!empty($payload['body']['data'])) {
        $chunks[] = carenzeMailGmailDecode($payload['body']['data']);
    }
    foreach (($payload['parts'] ?? []) as $part) {
        $chunks[] = carenzeMailGmailExtractText($part);
    }
    return trim(implode("\n", array_filter($chunks)));
}

function carenzeMailBounceCheckAccount(string $accountEmail, int $maxResults = 30): array
{
    carenzeMailLogEnsureTable();

    $accountEmail = strtolower(trim($accountEmail));
    $query = 'newer_than:14d (from:(mailer-daemon OR mailer-daemon@googlemail.com OR postmaster OR "Mail Delivery Subsystem") OR subject:("Delivery Status Notification" OR "Undelivered Mail Returned" OR "Mail delivery failed" OR "Message not delivered"))';
    $list = carenzeMailGmailApiRequestAs(
        $accountEmail,
        'GET',
        'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/messages?q=' . rawurlencode($query) . '&maxResults=' . max(1, min(100, $maxResults))
    );

    $checked = 0;
    $matched = 0;
    $unmatched = 0;
    $bounces = [];
    $checkedLogIds = [];

    foreach (($list['messages'] ?? []) as $messageRef) {
        $gmailMessageId = (string)($messageRef['id'] ?? '');
        if ($gmailMessageId === '') {
            continue;
        }
        $message = carenzeMailGmailApiRequestAs(
            $accountEmail,
            'GET',
            'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/messages/' . rawurlencode($gmailMessageId) . '?format=full'
        );
        $checked++;
        $subject = carenzeMailGmailHeader($message, 'Subject');
        $snippet = (string)($message['snippet'] ?? '');
        $body = carenzeMailGmailExtractText($message['payload'] ?? []);
        $searchText = $subject . "\n" . $snippet . "\n" . $body;
        $classification = carenzeMailLogClassifyBounce($subject, $snippet, $body);
        $logRow = carenzeMailLogFindByBounceText($searchText, $accountEmail);

        if ($logRow) {
            $matched++;
            $checkedLogIds[] = (int)$logRow['id'];
            carenzeMailLogMarkBounce((int)$logRow['id'], $classification['type'], $classification['reason'], $gmailMessageId, $snippet);
            $bounces[] = [
                'log_id' => (int)$logRow['id'],
                'carenza_id' => (int)$logRow['carenza_id'],
                'student_id' => (int)$logRow['student_id'],
                'student' => carenzeMailLogStudentLabel($logRow),
                'to_email' => (string)($logRow['to_email'] ?? ''),
                'type' => $classification['type'],
                'reason' => $classification['reason'],
                'snippet' => $snippet,
            ];
        } else {
            $unmatched++;
            $bounces[] = [
                'log_id' => 0,
                'carenza_id' => 0,
                'student_id' => 0,
                'type' => $classification['type'],
                'reason' => $classification['reason'],
                'snippet' => $snippet,
            ];
        }
    }

    carenzeMailLogMarkChecked($checkedLogIds);

    return [
        'account' => $accountEmail,
        'checked' => $checked,
        'matched' => $matched,
        'unmatched' => $unmatched,
        'bounces' => $bounces,
    ];
}

?>

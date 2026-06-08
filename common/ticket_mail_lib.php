<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';
require_once __DIR__ . '/send-mail.php';
require_once __DIR__ . '/telegram_webhook_utils.php';
require_once __DIR__ . '/telegram_webhook_api.php';
require_once __DIR__ . '/telegram_webhook_relay.php';
require_once __DIR__ . '/notifichePreferenzeLib.php';

function ticketMailConfig(): array
{
    global $__settings;

    return [
        'enabled' => (bool)($__settings->ticketMail->enabled ?? true),
        'alias_address' => trim((string)($__settings->ticketMail->alias_address ?? 'gestore@buonarroti.tn.it')),
        'imap_mailbox' => trim((string)($__settings->ticketMail->imap_mailbox ?? '{imap.gmail.com:993/imap/ssl}INBOX')),
        'imap_fallback_mailboxes' => $__settings->ticketMail->imap_fallback_mailboxes ?? [],
        'mark_seen_after_import' => (bool)($__settings->ticketMail->mark_seen_after_import ?? true),
        'quiet_hours_enabled' => (bool)($__settings->ticketMail->quiet_hours_enabled ?? false),
        'quiet_hours_start' => trim((string)($__settings->ticketMail->quiet_hours_start ?? '20:00')),
        'quiet_hours_end' => trim((string)($__settings->ticketMail->quiet_hours_end ?? '07:00')),
        'mailbox_user' => trim((string)($__settings->local->smtpMail ?? '')),
        'mailbox_pass' => trim((string)($__settings->local->AppPassword ?? '')),
        'reply_visible_from' => trim((string)($__settings->ticketMail->reply_visible_from ?? ($__settings->ticketMail->alias_address ?? 'gestore@buonarroti.tn.it'))),
        'bot_token' => trim((string)($__settings->telegram->bot_token ?? '')),
        'service_chat_id' => trim((string)($__settings->telegram->chat_id ?? '')),
    ];
}

function ticketMailTableExists(string $tableName): bool
{
    return dbGetValue("SHOW TABLES LIKE " . dbQ($tableName)) !== null;
}

function ticketMailMissingTables(): array
{
    $missing = [];
    if (!ticketMailTableExists('ticket_mail_import_log')) {
        $missing[] = 'ticket_mail_import_log';
    }
    return $missing;
}

function ticketMailDecodeHeaderValue(?string $value): string
{
    $value = (string)$value;
    if ($value === '') {
        return '';
    }

    $decoded = '';
    $elements = imap_mime_header_decode($value);
    if (is_array($elements) && !empty($elements)) {
        foreach ($elements as $element) {
            $charset = strtoupper((string)($element->charset ?? 'UTF-8'));
            $text = (string)($element->text ?? '');
            if ($charset !== 'DEFAULT' && $charset !== 'UTF-8') {
                $text = @mb_convert_encoding($text, 'UTF-8', $charset);
            }
            $decoded .= $text;
        }
        return trim($decoded);
    }

    return trim($value);
}

function ticketMailNormalizeAddress(string $address): string
{
    return strtolower(trim($address));
}

function ticketMailExtractAddressesFromHeaderInfo($headerInfo, string $fieldName): array
{
    $items = [];
    if (!$headerInfo || empty($headerInfo->{$fieldName}) || !is_array($headerInfo->{$fieldName})) {
        return $items;
    }

    foreach ($headerInfo->{$fieldName} as $part) {
        $mailbox = trim((string)($part->mailbox ?? ''));
        $host = trim((string)($part->host ?? ''));
        if ($mailbox === '' || $host === '') {
            continue;
        }
        $items[] = ticketMailNormalizeAddress($mailbox . '@' . $host);
    }

    return array_values(array_unique($items));
}

function ticketMailExtractDeliveredToAddresses(string $rawHeaders): array
{
    $addresses = [];
    if (preg_match_all('/^(Delivered-To|X-Original-To|Envelope-To|To):\s*(.+)$/im', $rawHeaders, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            if (!preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string)$match[2], $emailMatches)) {
                continue;
            }
            foreach ($emailMatches[0] as $email) {
                $addresses[] = ticketMailNormalizeAddress($email);
            }
        }
    }

    return array_values(array_unique($addresses));
}

function ticketMailIsAddressedToAlias($headerInfo, string $rawHeaders, string $aliasAddress): bool
{
    $aliasAddress = ticketMailNormalizeAddress($aliasAddress);
    $addresses = array_merge(
        ticketMailExtractAddressesFromHeaderInfo($headerInfo, 'to'),
        ticketMailExtractAddressesFromHeaderInfo($headerInfo, 'cc'),
        ticketMailExtractDeliveredToAddresses($rawHeaders)
    );

    foreach (array_unique($addresses) as $address) {
        if ($address === $aliasAddress) {
            return true;
        }
    }

    return false;
}

function ticketMailFindDocenteByEmail(string $email): ?array
{
    $email = ticketMailNormalizeAddress($email);
    if ($email === '') {
        return null;
    }

    $query = "
        SELECT d.*,
               dt.telegram_chat_id
        FROM docente d
        LEFT JOIN docente_telegram dt
            ON dt.idDocente = d.id
           AND dt.attivo = 1
           AND dt.consenso_notifiche = 1
        WHERE LOWER(TRIM(d.email)) = " . dbQ($email) . "
          AND d.attivo = 1
        ORDER BY d.attivo DESC, d.id DESC
        LIMIT 1
    ";

    return dbGetFirst($query);
}

function ticketMailFindStudenteByEmail(string $email): ?array
{
    $email = ticketMailNormalizeAddress($email);
    if ($email === '') {
        return null;
    }

    $query = "
        SELECT s.*
        FROM studente s
        WHERE LOWER(TRIM(s.email)) = " . dbQ($email) . "
          AND s.attivo = 1
        ORDER BY s.attivo DESC, s.id DESC
        LIMIT 1
    ";

    return dbGetFirst($query);
}

function ticketMailFindGenitoreByEmail(string $email): ?array
{
    $email = ticketMailNormalizeAddress($email);
    if ($email === '') {
        return null;
    }

    $joinTelegram = '';
    $selectTelegram = '';
    if (ticketMailTableExists('genitore_telegram')) {
        $joinTelegram = "
            LEFT JOIN genitore_telegram gt
                ON gt.idGenitore = g.id
               AND gt.attivo = 1
               AND gt.consenso_notifiche = 1
        ";
        $selectTelegram = ",
               gt.telegram_chat_id";
    }

    $query = "
        SELECT g.*" . $selectTelegram . "
        FROM genitori g
        " . $joinTelegram . "
        WHERE LOWER(TRIM(g.email)) = " . dbQ($email) . "
          AND g.attivo = 1
        ORDER BY g.attivo DESC, g.id DESC
        LIMIT 1
    ";

    return dbGetFirst($query);
}

function ticketMailResolveActorByEmail(string $email): array
{
    $email = ticketMailNormalizeAddress($email);
    if ($email === '') {
        return ['ok' => false, 'error' => 'indirizzo email mancante'];
    }

    $matches = [];

    $doc = ticketMailFindDocenteByEmail($email);
    if ($doc != null) {
        $matches[] = [
            'tipo_utente' => 'docente',
            'id' => (int)($doc['id'] ?? 0),
            'nome' => trim((string)($doc['nome'] ?? '')),
            'cognome' => trim((string)($doc['cognome'] ?? '')),
            'email' => trim((string)($doc['email'] ?? $email)),
            'attivo' => (int)($doc['attivo'] ?? 0),
            'telegram_chat_id' => trim((string)($doc['telegram_chat_id'] ?? '')),
            'raw' => $doc,
        ];
    }

    $gen = ticketMailFindGenitoreByEmail($email);
    if ($gen != null) {
        $matches[] = [
            'tipo_utente' => 'genitore',
            'id' => (int)($gen['id'] ?? 0),
            'nome' => trim((string)($gen['nome'] ?? '')),
            'cognome' => trim((string)($gen['cognome'] ?? '')),
            'email' => trim((string)($gen['email'] ?? $email)),
            'attivo' => (int)($gen['attivo'] ?? 0),
            'telegram_chat_id' => trim((string)($gen['telegram_chat_id'] ?? '')),
            'raw' => $gen,
        ];
    }

    $stu = ticketMailFindStudenteByEmail($email);
    if ($stu != null) {
        $matches[] = [
            'tipo_utente' => 'studente',
            'id' => (int)($stu['id'] ?? 0),
            'nome' => trim((string)($stu['nome'] ?? '')),
            'cognome' => trim((string)($stu['cognome'] ?? '')),
            'email' => trim((string)($stu['email'] ?? $email)),
            'attivo' => (int)($stu['attivo'] ?? 0),
            'telegram_chat_id' => '',
            'raw' => $stu,
        ];
    }

    if (empty($matches)) {
        return ['ok' => false, 'error' => 'nessun utente GestOre trovato per il mittente'];
    }

    if (count($matches) > 1) {
        $labels = array_map(function ($row) {
            return strtoupper((string)$row['tipo_utente']) . ' #' . (int)$row['id'];
        }, $matches);
        return [
            'ok' => false,
            'error' => 'email associata a piu utenti GestOre: ' . implode(', ', $labels),
            'matches' => $matches,
        ];
    }

    $actor = $matches[0];
    $actor['display_name'] = trim($actor['cognome'] . ' ' . $actor['nome']);
    $actor['display_label'] = match ($actor['tipo_utente']) {
        'studente' => 'Studente',
        'genitore' => 'Genitore',
        default => 'Docente',
    };

    return ['ok' => true, 'actor' => $actor];
}

function ticketMailFindRelayByTicketCode(string $ticketCode): ?array
{
    $ticketCode = trim($ticketCode);
    if ($ticketCode === '') {
        return null;
    }

    return dbGetFirst("SELECT * FROM docente_telegram_relay WHERE ticket_code = " . dbQ($ticketCode) . " LIMIT 1");
}

function ticketMailExtractTicketCode(string $subject): string
{
    if (preg_match('/\b([A-Z]{2,}-\d+)\b/u', strtoupper($subject), $matches)) {
        return trim((string)$matches[1]);
    }
    if (preg_match('/\b(T\d+)\b/u', strtoupper($subject), $matches)) {
        return trim((string)$matches[1]);
    }
    return '';
}

function ticketMailGetImportedRowByUid(string $uidKey): ?array
{
    if ($uidKey === '' || !ticketMailTableExists('ticket_mail_import_log')) {
        return null;
    }
    return dbGetFirst("SELECT * FROM ticket_mail_import_log WHERE message_uid = " . dbQ($uidKey) . " LIMIT 1");
}

function ticketMailColumnExists(string $tableName, string $columnName): bool
{
    static $cache = [];
    $cacheKey = $tableName . '.' . $columnName;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $row = dbGetFirst("SHOW COLUMNS FROM `" . addslashes($tableName) . "` LIKE " . dbQ($columnName));
    $cache[$cacheKey] = $row != null;
    return $cache[$cacheKey];
}

function ticketMailFindOpenMailRelayByDocente(int $idDocente): ?array
{
    if ($idDocente <= 0 || !ticketMailColumnExists('docente_telegram_relay', 'canale_apertura')) {
        return null;
    }

    return dbGetFirst("
        SELECT *
        FROM docente_telegram_relay
        WHERE idDocente = " . dbI($idDocente) . "
          AND canale_apertura = 'mail'
          AND stato IN ('APERTA', 'IN_GESTIONE')
          AND (chiusa = 0 OR chiusa IS NULL)
        ORDER BY id DESC
        LIMIT 1
    ");
}

function ticketMailFindOpenMailRelayByActor(array $actor): ?array
{
    $type = strtolower(trim((string)($actor['tipo_utente'] ?? 'docente')));
    $id = (int)($actor['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    if (!ticketMailColumnExists('docente_telegram_relay', 'canale_apertura')) {
        return $type === 'docente' ? tgFindOpenRelayByDocente($id) : null;
    }

    if ($type === 'docente') {
        return ticketMailFindOpenMailRelayByDocente($id);
    }

    $column = $type === 'studente' ? 'idStudente' : ($type === 'genitore' ? 'idGenitore' : '');
    if ($column === '' || !ticketMailColumnExists('docente_telegram_relay', $column)) {
        return null;
    }

    return dbGetFirst("
        SELECT *
        FROM docente_telegram_relay
        WHERE {$column} = " . dbI($id) . "
          AND canale_apertura = 'mail'
          AND stato IN ('APERTA', 'IN_GESTIONE')
          AND (chiusa = 0 OR chiusa IS NULL)
        ORDER BY id DESC
        LIMIT 1
    ");
}

function ticketMailRelayIsMailOrigin(?array $relay): bool
{
    if (!is_array($relay) || empty($relay)) {
        return false;
    }

    $origin = strtolower(trim((string)($relay['canale_apertura'] ?? '')));
    return in_array($origin, ['mail', 'login_mastercom'], true);
}

function ticketMailRelayUserLabel(array $relay): string
{
    $type = strtolower(trim((string)($relay['tipo_utente'] ?? 'docente')));
    return match ($type) {
        'studente' => 'studente',
        'genitore' => 'genitore',
        default => 'docente',
    };
}

function ticketMailRelayUserDisplayName(array $relay): string
{
    $name = trim((string)(($relay['utente_cognome'] ?? '') . ' ' . ($relay['utente_nome'] ?? '')));
    if ($name !== '') {
        return $name;
    }

    if (!empty($relay['idDocente'])) {
        $doc = dbGetFirst("SELECT cognome, nome FROM docente WHERE id = " . dbI((int)$relay['idDocente']) . " LIMIT 1");
        if ($doc != null) {
            return trim((string)(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? '')));
        }
    }

    return trim((string)($relay['utente_email'] ?? $relay['email_riferimento'] ?? 'utente'));
}

function ticketMailUpdateRelayMetadata(int $idRelay, string $fromEmail, array $doc, string $origin = 'mail'): void
{
    if ($idRelay <= 0) {
        return;
    }

    $fields = [];

    if (ticketMailColumnExists('docente_telegram_relay', 'canale_apertura')) {
        $fields[] = "canale_apertura = " . dbQ($origin);
    }

    if (ticketMailColumnExists('docente_telegram_relay', 'email_riferimento')) {
        $fields[] = "email_riferimento = " . dbQ($fromEmail);
    }

    if (ticketMailColumnExists('docente_telegram_relay', 'data_aggiornamento')) {
        $fields[] = "data_aggiornamento = NOW()";
    }

    $actorType = strtolower(trim((string)($doc['tipo_utente'] ?? 'docente')));
    if ($actorType === 'docente' && ticketMailColumnExists('docente_telegram_relay', 'idDocente')) {
        $fields[] = "idDocente = " . dbI((int)($doc['id'] ?? 0));
    }
    if ($actorType === 'studente' && ticketMailColumnExists('docente_telegram_relay', 'idStudente')) {
        $fields[] = "idStudente = " . dbI((int)($doc['id'] ?? 0));
    }
    if ($actorType === 'genitore' && ticketMailColumnExists('docente_telegram_relay', 'idGenitore')) {
        $fields[] = "idGenitore = " . dbI((int)($doc['id'] ?? 0));
    }
    if (ticketMailColumnExists('docente_telegram_relay', 'tipo_utente')) {
        $fields[] = "tipo_utente = " . dbQ($actorType);
    }
    if (ticketMailColumnExists('docente_telegram_relay', 'utente_nome')) {
        $fields[] = "utente_nome = " . dbQ((string)($doc['nome'] ?? ''));
    }
    if (ticketMailColumnExists('docente_telegram_relay', 'utente_cognome')) {
        $fields[] = "utente_cognome = " . dbQ((string)($doc['cognome'] ?? ''));
    }
    if (ticketMailColumnExists('docente_telegram_relay', 'utente_email')) {
        $fields[] = "utente_email = " . dbQ((string)($doc['email'] ?? $fromEmail));
    }

    $teacherChatId = trim((string)($doc['telegram_chat_id'] ?? ''));
    if ($teacherChatId !== '' && ticketMailColumnExists('docente_telegram_relay', 'docente_chat_id')) {
        $fields[] = "docente_chat_id = " . dbQ($teacherChatId);
    }

    if (empty($fields)) {
        return;
    }

    dbExec("
        UPDATE docente_telegram_relay
        SET " . implode(", ", $fields) . "
        WHERE id = " . dbI($idRelay) . "
    ");
}

function ticketMailLogImported(array $data): void
{
    $columns = [
        'message_uid',
        'message_id',
        'from_email',
        'to_addresses',
        'subject',
        'idDocente',
    ];
    $values = [
        dbQ($data['message_uid'] ?? ''),
        dbQ($data['message_id'] ?? ''),
        dbQ($data['from_email'] ?? ''),
        dbQ($data['to_addresses'] ?? ''),
        dbQ($data['subject'] ?? ''),
        isset($data['idDocente']) && $data['idDocente'] !== null ? dbI((int)$data['idDocente']) : 'NULL',
    ];

    if (ticketMailColumnExists('ticket_mail_import_log', 'tipo_utente')) {
        $columns[] = 'tipo_utente';
        $values[] = dbQ($data['tipo_utente'] ?? '');
    }
    if (ticketMailColumnExists('ticket_mail_import_log', 'idStudente')) {
        $columns[] = 'idStudente';
        $values[] = isset($data['idStudente']) && $data['idStudente'] !== null ? dbI((int)$data['idStudente']) : 'NULL';
    }
    if (ticketMailColumnExists('ticket_mail_import_log', 'idGenitore')) {
        $columns[] = 'idGenitore';
        $values[] = isset($data['idGenitore']) && $data['idGenitore'] !== null ? dbI((int)$data['idGenitore']) : 'NULL';
    }

    $columns = array_merge($columns, ['idRelay', 'ticket_code', 'esito', 'nota', 'imported_at']);
    $values = array_merge($values, [
        isset($data['idRelay']) && $data['idRelay'] !== null ? dbI((int)$data['idRelay']) : 'NULL',
        dbQ($data['ticket_code'] ?? ''),
        dbQ($data['esito'] ?? ''),
        dbQ($data['nota'] ?? ''),
        'NOW()',
    ]);

    $updates = [];
    foreach ($columns as $column) {
        if ($column === 'message_uid') {
            continue;
        }
        $updates[] = "{$column}=VALUES({$column})";
    }

    dbExec("
        INSERT INTO ticket_mail_import_log (
            " . implode(",\n            ", $columns) . "
        ) VALUES (
            " . implode(",\n            ", $values) . "
        )
        ON DUPLICATE KEY UPDATE
            " . implode(",\n            ", $updates) . "
    ");
}

function ticketMailFetchDecodedBody($imap, int $msgNo): string
{
    $structure = imap_fetchstructure($imap, $msgNo);
    if (!$structure) {
        return ticketMailSanitizeBody(trim((string)imap_body($imap, $msgNo)));
    }

    $plainText = ticketMailFetchDecodedPart($imap, $msgNo, $structure, '', 'PLAIN');
    if ($plainText !== '') {
        return ticketMailSanitizeBody($plainText);
    }

    $htmlText = ticketMailFetchDecodedPart($imap, $msgNo, $structure, '', 'HTML');
    if ($htmlText !== '') {
        return ticketMailSanitizeBody(trim(preg_replace('/\s+/u', ' ', strip_tags($htmlText))));
    }

    return ticketMailSanitizeBody(trim(preg_replace('/\s+/u', ' ', strip_tags((string)imap_body($imap, $msgNo)))));
}

function ticketMailFetchDecodedPart($imap, int $msgNo, $structure, string $partNumber, string $preferredSubtype): string
{
    $subtype = strtoupper((string)($structure->subtype ?? ''));
    $type = intval($structure->type ?? 0);

    if ($type === 0 && $subtype === $preferredSubtype) {
        $body = $partNumber === '' ? imap_body($imap, $msgNo) : imap_fetchbody($imap, $msgNo, $partNumber);
        $encoding = intval($structure->encoding ?? 0);
        if ($encoding === 3) {
            $body = base64_decode((string)$body);
        } elseif ($encoding === 4) {
            $body = quoted_printable_decode((string)$body);
        }

        $charset = 'UTF-8';
        if (!empty($structure->parameters) && is_array($structure->parameters)) {
            foreach ($structure->parameters as $param) {
                if (strtolower((string)($param->attribute ?? '')) === 'charset') {
                    $charset = (string)($param->value ?? 'UTF-8');
                    break;
                }
            }
        }

        $body = (string)$body;
        if ($charset !== '' && strtoupper($charset) !== 'UTF-8') {
            $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
            if ($converted !== false) {
                $body = $converted;
            }
        }

        return trim($body);
    }

    if (!empty($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $childPartNumber = $partNumber === '' ? (string)($index + 1) : $partNumber . '.' . ($index + 1);
            $result = ticketMailFetchDecodedPart($imap, $msgNo, $part, $childPartNumber, $preferredSubtype);
            if ($result !== '') {
                return $result;
            }
        }
    }

    return '';
}

function ticketMailEnsureAttachmentDir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'ticket_mail_attachments';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

function ticketMailDecodeBinaryBody($imap, int $msgNo, string $partNumber, int $encoding): string
{
    $body = $partNumber === '' ? (string)imap_body($imap, $msgNo) : (string)imap_fetchbody($imap, $msgNo, $partNumber);
    if ($encoding === 3) {
        return (string)base64_decode($body);
    }
    if ($encoding === 4) {
        return (string)quoted_printable_decode($body);
    }
    return $body;
}

function ticketMailExtractPartFilename($part): string
{
    $candidates = [];
    if (!empty($part->dparameters) && is_array($part->dparameters)) {
        $candidates = array_merge($candidates, $part->dparameters);
    }
    if (!empty($part->parameters) && is_array($part->parameters)) {
        $candidates = array_merge($candidates, $part->parameters);
    }

    foreach ($candidates as $param) {
        $attribute = strtolower((string)($param->attribute ?? ''));
        if (in_array($attribute, ['filename', 'name'], true)) {
            return trim((string)($param->value ?? ''));
        }
    }

    return '';
}

function ticketMailCollectAttachments($imap, int $msgNo, $structure, string $partNumber = ''): array
{
    $attachments = [];

    if (!empty($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $childPartNumber = $partNumber === '' ? (string)($index + 1) : $partNumber . '.' . ($index + 1);
            $attachments = array_merge($attachments, ticketMailCollectAttachments($imap, $msgNo, $part, $childPartNumber));
        }
    }

    $filename = ticketMailExtractPartFilename($structure);
    $disposition = strtolower((string)($structure->disposition ?? ''));
    $subtype = strtoupper((string)($structure->subtype ?? ''));
    $type = intval($structure->type ?? 0);
    $isAttachment = $filename !== '' || in_array($disposition, ['attachment', 'inline'], true);
    $isSupported = in_array($type, [3, 5], true) || ($type === 0 && in_array($subtype, ['JPEG', 'JPG', 'PNG', 'GIF', 'PDF'], true));

    if (!$isAttachment || !$isSupported) {
        return $attachments;
    }

    $data = ticketMailDecodeBinaryBody($imap, $msgNo, $partNumber, intval($structure->encoding ?? 0));
    if ($data === '') {
        return $attachments;
    }

    if ($filename === '') {
        $extensionMap = [
            'JPEG' => 'jpg',
            'JPG' => 'jpg',
            'PNG' => 'png',
            'GIF' => 'gif',
            'PDF' => 'pdf',
        ];
        $ext = $extensionMap[$subtype] ?? 'bin';
        $filename = 'allegato_' . $msgNo . '_' . str_replace('.', '_', $partNumber ?: 'body') . '.' . $ext;
    }

    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    $dir = ticketMailEnsureAttachmentDir();
    $path = $dir . DIRECTORY_SEPARATOR . uniqid('mail_', true) . '_' . $filename;
    file_put_contents($path, $data);

    $attachments[] = [
        'filename' => $filename,
        'path' => $path,
        'mime' => strtolower((string)($structure->subtype ?? '')),
    ];

    return $attachments;
}

function ticketMailSanitizeBody(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $body = preg_replace("/\x{00A0}/u", ' ', $body);
    $body = preg_replace("/[ \t]+\n/u", "\n", $body);
    $body = preg_replace("/\n[ \t]+/u", "\n", $body);
    $body = preg_replace("/=\n/u", '', $body);

    $forwardedBody = ticketMailExtractForwardedBody($body);

    // taglia risposte normali, ma NON le mail inoltrate
    $inlineReplyMarkers = [
        '/\n\s*Il giorno\b[\s\S]{0,800}?ha scritto:\s*/iu',
        '/\n\s*On\b[\s\S]{0,800}?wrote:\s*/iu',
    ];

    foreach ($inlineReplyMarkers as $markerPattern) {
        if (preg_match($markerPattern, $body, $matches, PREG_OFFSET_CAPTURE)) {
            $body = substr($body, 0, $matches[0][1]);
            break;
        }
    }

    $body = ticketMailStripSignatureAndFooter($body);

    if ($forwardedBody !== '') {
        $body = trim($body);

        if ($body !== '') {
            $body .= "\n\n--- Messaggio inoltrato ---\n" . $forwardedBody;
        } else {
            $body = $forwardedBody;
        }
    }

    $body = preg_replace('/^--[A-Za-z0-9_\-]+=*.*$/m', '', $body);
    $body = preg_replace('/^Content-Type:.*$/mi', '', $body);
    $body = preg_replace('/^Content-Transfer-Encoding:.*$/mi', '', $body);
    $body = preg_replace('/^Content-Disposition:.*$/mi', '', $body);
    $body = preg_replace('/^charset=.*$/mi', '', $body);
    $body = preg_replace('/^\s*>.*$/m', '', $body);
    $body = preg_replace("/\n{3,}/", "\n\n", $body);

    return trim($body);
}

function ticketMailExtractForwardedBody(string $body): string
{
    $patterns = [
        '/^-{2,}\s*Messaggio inoltrato\s*-{2,}\s*$/mi',
        '/^-{2,}\s*Forwarded message\s*-{2,}\s*$/mi',
        '/^\s*Da:\s.*$/mi',
        '/^\s*From:\s.*$/mi',
    ];

    $offset = null;

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $body, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            if ($offset === null || $pos < $offset) {
                $offset = $pos;
            }
        }
    }

    if ($offset === null) {
        return '';
    }

    $forwarded = substr($body, $offset);

    // rimuove intestazioni della mail inoltrata
    $forwarded = preg_replace('/^-{2,}\s*(Messaggio inoltrato|Forwarded message)\s*-{2,}\s*$/mi', '', $forwarded);
    $forwarded = preg_replace('/^\s*(Da|From|Inviato|Sent|A|To|Cc|Oggetto|Subject):\s.*$/mi', '', $forwarded);

    $forwarded = ticketMailStripSignatureAndFooter($forwarded);
    $forwarded = preg_replace('/^\s*>.*$/m', '', $forwarded);
    $forwarded = preg_replace("/\n{3,}/", "\n\n", $forwarded);

    return trim($forwarded);
}

function ticketMailStripSignatureAndFooter(string $body): string
{
    $cutPatterns = [
        '/^\s*--\s*$/m',
        '/^\s*Avvertenze ai sensi del Regolamento Europeo 2016\/679.*$/mi',
        '/^\s*La informiamo inoltre che in caso di assenza del destinatario.*$/mi',
        '/^\s*P\s+Rispetta l\'ambiente:.*$/mi',
        '/^\s*\*?\s*Segreteria\s+Didattica\s*\*?\s*$/mi',
        '/^\s*Istituto\s+Tecnico\s+Tecnologico\s*$/mi',
        '/^\s*\*?\s*M\.\s*BUONARROTI\s*\*?\s*$/mi',
        '/^\s*Via\s+Brigata\s+Acqui\b.*$/mi',
        '/^\s*38122\s+Trento\s*$/mi',
        '/^\s*tel\.\s*\+39\s+0461\s+216811\s*$/mi',
        '/^\s*www\.buonarroti\.tn\.it\s*$/mi',
        '/^\s*This e-mail \(including attachments\) is intended only for the recipient\(s\).*$/mi',
        '/^\s*It may contain confidential or privileged information.*$/mi',
        '/^\s*If you are not the named recipient.*$/mi',
        '/^\s*D\.L\.\s*196\/2003\.\s*Print only if necessary\..*$/mi',
        '/^\s*Print only if necessary\..*$/mi',
    ];

    foreach ($cutPatterns as $pattern) {
        if (preg_match($pattern, $body, $matches, PREG_OFFSET_CAPTURE)) {
            $body = substr($body, 0, $matches[0][1]);
            break;
        }
    }

    return trim($body);
}

function ticketMailBuildTelegramText(string $fromEmail, string $subject, string $body): string
{
    return trim($body);
}

function ticketMailBuildHtmlMessage(array $relay, string $subject, string $body): string
{
    global $__settings;

    $ticketCode = trim((string)($relay['ticket_code'] ?? ''));
    $istituto = trim((string)($__settings->local->nomeIstituto ?? 'ITT Buonarroti - Trento'));
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $safeBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    $ticketBadge = $ticketCode !== ''
        ? '<div style="display:inline-block;background:#0f766e;color:#ffffff;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:0.3px;">Ticket ' . htmlspecialchars($ticketCode, ENT_QUOTES, 'UTF-8') . '</div>'
        : '';

    return '
    <div style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
        <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #dbe4ee;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
            <div style="background:linear-gradient(135deg,#0f766e 0%,#0b5d56 100%);padding:24px 28px;color:#ffffff;">
                <div style="font-size:13px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;opacity:0.92;">GestOre</div>
                <div style="font-size:28px;font-weight:700;line-height:1.2;margin-top:6px;">Assistenza ' . htmlspecialchars($istituto, ENT_QUOTES, 'UTF-8') . '</div>
                <div style="font-size:14px;line-height:1.5;margin-top:8px;opacity:0.92;">Aggiornamento del tuo ticket di assistenza</div>
            </div>

            <div style="padding:28px;">
                <div style="margin-bottom:18px;">' . $ticketBadge . '</div>

                <div style="font-size:22px;font-weight:700;line-height:1.3;color:#111827;margin-bottom:10px;">' . $safeSubject . '</div>

                <div style="background:#f8fafc;border:1px solid #e5edf5;border-radius:14px;padding:18px 20px;font-size:15px;line-height:1.7;color:#1f2937;">
                    ' . $safeBody . '
                </div>

                <div style="margin-top:24px;padding-top:18px;border-top:1px solid #e5edf5;font-size:13px;line-height:1.6;color:#64748b;">
                    Questa comunicazione è stata inviata da GestOre.<br>
                    Puoi rispondere direttamente a questa email per continuare la conversazione sullo stesso ticket.
                </div>
            </div>
        </div>
    </div>';
}

function ticketMailForwardAttachmentsToTelegram(array $attachments, string $botToken, string $serviceChatId, int $threadId, string $ticketCode): array
{
    $sent = 0;
    $errors = [];

    foreach ($attachments as $attachment) {
        $path = (string)($attachment['path'] ?? '');
        $fileName = (string)($attachment['filename'] ?? 'allegato');
        if ($path === '' || !is_file($path)) {
            continue;
        }

        $caption = "📎 Allegato email su ticket {$ticketCode}: {$fileName}";
        $res = tgSendDocument($botToken, $serviceChatId, $path, $fileName, [
            'message_thread_id' => $threadId,
            'caption' => $caption
        ]);

        if (!empty($res['ok'])) {
            $sent++;
        } else {
            $errors[] = $fileName . ': ' . trim((string)($res['error'] ?? 'errore invio allegato'));
        }

        @unlink($path);
    }

    return ['sent' => $sent, 'errors' => $errors];
}

function ticketMailSendRelayNotification(array $relay, string $subject, string $body, array $attachments = []): array
{
    global $__settings;

    $toEmail = ticketMailResolveRelayRecipientEmail($relay);
    $config = ticketMailConfig();
    $ticketCode = trim((string)($relay['ticket_code'] ?? ''));
    $mailSubject = trim($subject);
    if ($ticketCode !== '' && stripos($mailSubject, $ticketCode) === false) {
        $mailSubject = '[' . $ticketCode . '] ' . $mailSubject;
    }

    $html = ticketMailBuildHtmlMessage($relay, $subject, $body);

    $channels = [];
    $errors = [];
    if ($toEmail !== '') {
        $okMail = sendMailCustom(
            $toEmail,
            trim((string)(
                (($relay['utente_nome'] ?? $relay['nome'] ?? '') . ' ' . ($relay['utente_cognome'] ?? $relay['cognome'] ?? ''))
            )),
            $mailSubject,
            $html,
            [
                'from_email' => trim((string)($config['reply_visible_from'] ?? $config['alias_address'] ?? $__settings->local->emailNoReplyFrom ?? '')),
                'from_name' => 'GestOre ' . trim((string)($__settings->local->nomeIstituto ?? '')),
                'sender_email' => trim((string)($__settings->local->smtpMail ?? '')),
                'sender_name' => 'GestOre ' . trim((string)($__settings->local->nomeIstituto ?? '')),
                'reply_to_email' => trim((string)($config['reply_visible_from'] ?? $config['alias_address'] ?? '')),
                'reply_to_name' => 'GestOre Ticket',
                'attachments' => $attachments,
                'add_bcc_default' => false,
            ]
        );
        $channels['mail'] = $okMail;
        if (!$okMail) {
            $errors[] = 'invio mail fallito';
        }
    } else {
        $errors[] = 'email_riferimento mancante';
    }

    $idGenitore = (int)($relay['idGenitore'] ?? 0);
    if ($idGenitore > 0 && notifichePreferenzeChannelAllowed('genitore', $idGenitore, 'comunicazioni', 'telegram')) {
        $chatId = notifichePreferenzeGenitoreTelegramChatId($idGenitore);
        $botToken = trim((string)($__settings->telegram->bot_token ?? ''));
        if ($chatId !== '' && $botToken !== '') {
            $telegramText = trim($subject) . "\n\n" . trim($body);
            if ($ticketCode !== '') {
                $telegramText = "Ticket {$ticketCode}\n" . $telegramText;
            }
            $tgRes = tgSendMessage($botToken, $chatId, tgCut($telegramText, 3500));
            $channels['telegram'] = !empty($tgRes['ok']);
            if (empty($tgRes['ok'])) {
                $errors[] = 'invio telegram fallito';
            }
        } else {
            $channels['telegram'] = false;
            $errors[] = 'telegram genitore non collegato';
        }
    }

    $ok = false;
    foreach ($channels as $sent) {
        $ok = $ok || !empty($sent);
    }

    return $ok ? ['ok' => true, 'channels' => $channels] : ['ok' => false, 'error' => implode('; ', $errors), 'channels' => $channels];
}

function ticketMailResolveRelayRecipientEmail(array $relay): string
{
    $toEmail = trim((string)($relay['email_riferimento'] ?? ''));
    if ($toEmail !== '') {
        return $toEmail;
    }

    if (!empty($relay['utente_email'])) {
        return trim((string)$relay['utente_email']);
    }
    if (!empty($relay['idDocente'])) {
        return trim((string)dbGetValue("SELECT email FROM docente WHERE id = " . dbI((int)$relay['idDocente']) . " LIMIT 1"));
    }
    if (!empty($relay['idStudente'])) {
        return trim((string)dbGetValue("SELECT email FROM studente WHERE id = " . dbI((int)$relay['idStudente']) . " LIMIT 1"));
    }
    if (!empty($relay['idGenitore'])) {
        return trim((string)dbGetValue("SELECT email FROM genitori WHERE id = " . dbI((int)$relay['idGenitore']) . " LIMIT 1"));
    }

    return '';
}

function ticketMailExtractTelegramAttachmentFromMessage(array $message, string $botToken): array
{
    $attachments = [];

    if (!empty($message['photo']) && is_array($message['photo'])) {
        $photo = end($message['photo']);
        $fileId = trim((string)($photo['file_id'] ?? ''));
        if ($fileId !== '') {
            $download = tgDownloadFileToTemp($botToken, $fileId, 'foto_ticket.jpg');
            if (!empty($download['ok'])) {
                $attachments[] = [
                    'path' => $download['path'],
                    'filename' => $download['filename'],
                ];
            }
        }
    }

    if (!empty($message['document']) && is_array($message['document'])) {
        $document = $message['document'];
        $mime = strtolower(trim((string)($document['mime_type'] ?? '')));
        $fileName = trim((string)($document['file_name'] ?? 'allegato'));
        $allowed = ($mime === 'application/pdf') || (strpos($mime, 'image/') === 0);
        if ($allowed) {
            $fileId = trim((string)($document['file_id'] ?? ''));
            if ($fileId !== '') {
                $download = tgDownloadFileToTemp($botToken, $fileId, $fileName);
                if (!empty($download['ok'])) {
                    $attachments[] = [
                        'path' => $download['path'],
                        'filename' => $download['filename'],
                    ];
                }
            }
        }
    }

    return $attachments;
}

function ticketMailMailboxCandidates(array $config): array
{
    $primary = trim((string)($config['imap_mailbox'] ?? ''));
    $candidates = [];
    if ($primary !== '' && stripos($primary, 'All Mail') === false && stripos($primary, 'Tutti i messaggi') === false) {
        $candidates[] = $primary;
        $labelRootMailbox = ticketMailGmailLabelRootMailbox($primary);
        if ($labelRootMailbox !== '') {
            array_unshift($candidates, $labelRootMailbox);
        }
    } elseif ($primary !== '' && function_exists('warningGmail')) {
        warningGmail('mailbox primaria ignorata per import ticket: ' . $primary);
    }

    $fallbacks = $config['imap_fallback_mailboxes'] ?? [];
    if (is_string($fallbacks) && trim($fallbacks) !== '') {
        $fallbacks = [trim($fallbacks)];
    }
    if (!is_array($fallbacks)) {
        $fallbacks = [];
    }

    foreach ($fallbacks as $mailbox) {
        $mailbox = trim((string)$mailbox);
        if ($mailbox !== '' && stripos($mailbox, 'All Mail') === false && stripos($mailbox, 'Tutti i messaggi') === false) {
            $candidates[] = $mailbox;
        } elseif ($mailbox !== '' && function_exists('warningGmail')) {
            warningGmail('mailbox fallback ignorata per import ticket: ' . $mailbox);
        }
    }

    return array_values(array_unique(array_filter($candidates)));
}

function ticketMailGmailLabelRootMailbox(string $mailbox): string
{
    if (stripos($mailbox, 'imap.gmail.com') === false) {
        return '';
    }

    if (!preg_match('/^(\{[^}]+\})\[(?:Gmail|Google Mail)\]\/(.+)$/i', $mailbox, $matches)) {
        return '';
    }

    $label = trim((string)$matches[2]);
    if ($label === '' || stripos($label, 'All Mail') !== false || stripos($label, 'Tutti i messaggi') !== false) {
        return '';
    }

    return $matches[1] . $label;
}

function ticketMailClearImapRuntimeErrors(): void
{
    if (function_exists('imap_errors')) {
        @imap_errors();
    }
    if (function_exists('imap_alerts')) {
        @imap_alerts();
    }
}

function ticketMailParseClockToMinutes(string $value): int
{
    if (!preg_match('/^\s*(\d{1,2})\:(\d{2})\s*$/', $value, $matches)) {
        return -1;
    }

    $hours = intval($matches[1]);
    $minutes = intval($matches[2]);
    if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
        return -1;
    }

    return ($hours * 60) + $minutes;
}

function ticketMailIsInQuietHours(array $config): bool
{
    if (empty($config['quiet_hours_enabled'])) {
        return false;
    }

    $start = ticketMailParseClockToMinutes((string)($config['quiet_hours_start'] ?? '20:00'));
    $end = ticketMailParseClockToMinutes((string)($config['quiet_hours_end'] ?? '07:00'));
    if ($start < 0 || $end < 0) {
        return false;
    }

    $now = intval(date('G')) * 60 + intval(date('i'));
    if ($start === $end) {
        return true;
    }

    if ($start < $end) {
        return $now >= $start && $now < $end;
    }

    return $now >= $start || $now < $end;
}

function ticketMailNormalizeMessageId(string $messageId): string
{
    return strtolower(trim($messageId, " \t\n\r\0\x0B<>"));
}

function ticketMailImportInbox(int $limit = 10, ?bool $markSeen = null, bool $respectQuietHours = true, bool $includeRecent = false, array $allowedMessageIds = []): array
{
    $config = ticketMailConfig();
    $limit = max(1, min(30, $limit));
    $markSeen = $markSeen === null ? $config['mark_seen_after_import'] : $markSeen;
    $allowedMessageIds = array_values(array_filter(array_map('ticketMailNormalizeMessageId', $allowedMessageIds)));
    $allowedMessageIdMap = array_fill_keys($allowedMessageIds, true);

    if (!$config['enabled']) {
        return ['ok' => false, 'message' => 'Ticket mail disabilitato in configurazione', 'results' => []];
    }

    if ($respectQuietHours && ticketMailIsInQuietHours($config)) {
        return [
            'ok' => true,
            'message' => 'Import sospeso per fascia oraria silenziosa',
            'counts' => [
                'imported' => 0,
                'errors' => 0,
                'skipped' => 0,
                'processed' => 0,
            ],
            'quiet_hours_active' => true,
            'results' => [],
        ];
    }

    if (!function_exists('imap_open')) {
        return ['ok' => false, 'message' => 'Estensione IMAP non disponibile in PHP', 'results' => []];
    }

    $missingTables = ticketMailMissingTables();
    if (!empty($missingTables)) {
        return ['ok' => false, 'message' => 'Mancano tabelle: ' . implode(', ', $missingTables), 'results' => []];
    }

    $mailboxCandidates = ticketMailMailboxCandidates($config);
    $inbox = false;
    $messageNumbers = [];
    $openedMailbox = '';
    $errors = [];

    foreach ($mailboxCandidates as $mailboxCandidate) {
        ticketMailClearImapRuntimeErrors();
        $candidateInbox = @imap_open($mailboxCandidate, $config['mailbox_user'], $config['mailbox_pass']);
        if ($candidateInbox === false) {
            $errors[] = $mailboxCandidate . ': ' . imap_last_error();
            ticketMailClearImapRuntimeErrors();
            continue;
        }

        $candidateMessages = imap_search($candidateInbox, 'UNSEEN') ?: [];
        if ($includeRecent) {
            $recentMessages = imap_search($candidateInbox, 'ALL') ?: [];
            if (!empty($recentMessages)) {
                rsort($recentMessages, SORT_NUMERIC);
                $recentMessages = array_slice($recentMessages, 0, max($limit * 3, 10));
                $candidateMessages = array_values(array_unique(array_merge($candidateMessages, $recentMessages)));
            }
        }

        if (!empty($candidateMessages)) {
            if ($inbox !== false && $inbox !== $candidateInbox) {
                @imap_close($inbox);
                ticketMailClearImapRuntimeErrors();
            }
            $inbox = $candidateInbox;
            $messageNumbers = $candidateMessages;
            $openedMailbox = $mailboxCandidate;
            break;
        }

        if ($openedMailbox === '') {
            $inbox = $candidateInbox;
            $openedMailbox = $mailboxCandidate;
        } else {
            @imap_close($candidateInbox);
            ticketMailClearImapRuntimeErrors();
        }
    }

    if ($inbox === false) {
        return ['ok' => false, 'message' => 'Connessione IMAP fallita: ' . implode(' | ', $errors), 'results' => []];
    }

    rsort($messageNumbers, SORT_NUMERIC);
    $messageNumbers = array_slice($messageNumbers, 0, $limit);

    $results = [];
    foreach ($messageNumbers as $msgNo) {
        $overviewRows = imap_fetch_overview($inbox, (string)$msgNo, 0);
        $overview = is_array($overviewRows) && !empty($overviewRows) ? $overviewRows[0] : null;
        $headerInfo = imap_headerinfo($inbox, $msgNo);
        $rawHeaders = (string)imap_fetchheader($inbox, $msgNo);
        $uid = imap_uid($inbox, $msgNo);
        $uidKey = 'imap:' . $uid;

        $subject = ticketMailDecodeHeaderValue((string)($overview->subject ?? ''));
        $messageId = trim((string)($overview->message_id ?? ''));
        $normalizedMessageId = ticketMailNormalizeMessageId($messageId);
        if (!empty($allowedMessageIdMap) && ($normalizedMessageId === '' || empty($allowedMessageIdMap[$normalizedMessageId]))) {
            continue;
        }
        $fromAddresses = ticketMailExtractAddressesFromHeaderInfo($headerInfo, 'from');
        $fromEmail = $fromAddresses[0] ?? '';
        $toAddresses = array_unique(array_merge(
            ticketMailExtractAddressesFromHeaderInfo($headerInfo, 'to'),
            ticketMailExtractAddressesFromHeaderInfo($headerInfo, 'cc'),
            ticketMailExtractDeliveredToAddresses($rawHeaders)
        ));

        $resultRow = [
            'msgno' => $msgNo,
            'uid' => $uid,
            'from_email' => $fromEmail,
            'subject' => $subject,
            'ticket_code' => '',
            'status' => 'skipped',
            'note' => '',
        ];

        $existingImportedRow = ticketMailGetImportedRowByUid($uidKey);
        if ($existingImportedRow != null && strtoupper(trim((string)($existingImportedRow['esito'] ?? ''))) !== 'ERRORE') {
            $resultRow['note'] = 'mail già importata in precedenza';
            $results[] = $resultRow;
            if ($markSeen) {
                imap_setflag_full($inbox, (string)$msgNo, '\\Seen');
            }
            continue;
        }

        if (!ticketMailIsAddressedToAlias($headerInfo, $rawHeaders, $config['alias_address'])) {
            $resultRow['note'] = 'destinatario diverso da ' . $config['alias_address'];
            $results[] = $resultRow;
            if ($markSeen) {
                imap_setflag_full($inbox, (string)$msgNo, '\\Seen');
            }
            continue;
        }

        if ($fromEmail === '' || preg_match('/^(mailer-daemon|postmaster)@/i', $fromEmail)) {
            $resultRow['note'] = 'mittente automatico o non valido';
            $results[] = $resultRow;
            if ($markSeen) {
                imap_setflag_full($inbox, (string)$msgNo, '\\Seen');
            }
            continue;
        }

        $actorResolution = ticketMailResolveActorByEmail($fromEmail);
        if (empty($actorResolution['ok'])) {
            $resultRow['note'] = trim((string)($actorResolution['error'] ?? 'nessun utente GestOre trovato per il mittente'));
            $results[] = $resultRow;
            ticketMailLogImported([
                'message_uid' => $uidKey,
                'message_id' => $messageId,
                'from_email' => $fromEmail,
                'to_addresses' => implode(', ', $toAddresses),
                'subject' => $subject,
                'esito' => 'IGNORATA',
                'nota' => $resultRow['note'],
            ]);
            if ($markSeen) {
                imap_setflag_full($inbox, (string)$msgNo, '\\Seen');
            }
            continue;
        }
        $actor = $actorResolution['actor'];

        $body = ticketMailFetchDecodedBody($inbox, $msgNo);
        $attachments = ticketMailCollectAttachments($inbox, $msgNo, imap_fetchstructure($inbox, $msgNo));
        $telegramText = ticketMailBuildTelegramText($fromEmail, $subject, $body);
        $ticketCodeInSubject = ticketMailExtractTicketCode($subject);
        $relayByCode = $ticketCodeInSubject !== '' ? ticketMailFindRelayByTicketCode($ticketCodeInSubject) : null;

        if ($relayByCode && !ticketMailRelayIsMailOrigin($relayByCode) && ticketMailColumnExists('docente_telegram_relay', 'canale_apertura')) {
            $resultRow['note'] = 'ticket nato su telegram: risposta via mail ignorata';
            $results[] = $resultRow;
            ticketMailLogImported([
                'message_uid' => $uidKey,
                'message_id' => $messageId,
                'from_email' => $fromEmail,
                'to_addresses' => implode(', ', $toAddresses),
                'subject' => $subject,
                'tipo_utente' => (string)($actor['tipo_utente'] ?? ''),
                'idDocente' => (($actor['tipo_utente'] ?? '') === 'docente') ? intval($actor['id'] ?? 0) : null,
                'idStudente' => (($actor['tipo_utente'] ?? '') === 'studente') ? intval($actor['id'] ?? 0) : null,
                'idGenitore' => (($actor['tipo_utente'] ?? '') === 'genitore') ? intval($actor['id'] ?? 0) : null,
                'idRelay' => intval($relayByCode['id'] ?? 0),
                'ticket_code' => $ticketCodeInSubject,
                'esito' => 'IGNORATA',
                'nota' => $resultRow['note'],
            ]);
            if ($markSeen) {
                imap_setflag_full($inbox, (string)$msgNo, '\\Seen');
            }
            foreach ($attachments as $attachment) {
                @unlink((string)($attachment['path'] ?? ''));
            }
            continue;
        }

        $res = tgCreateOrAppendTicketFromDocenteMail(
            $actor,
            $subject,
            $telegramText,
            $fromEmail,
            $config['service_chat_id'],
            $config['bot_token'],
            $relayByCode
        );

        if (!empty($res['ok']) && intval($res['idRelay'] ?? 0) > 0) {
            ticketMailUpdateRelayMetadata(intval($res['idRelay']), $fromEmail, $actor, 'mail');
            $relayUpdated = tgFindRelayById(intval($res['idRelay']));
            $forwardRes = ticketMailForwardAttachmentsToTelegram(
                $attachments,
                $config['bot_token'],
                trim((string)($relayUpdated['service_chat_id'] ?? $config['service_chat_id'])),
                intval($relayUpdated['service_thread_id'] ?? 0),
                trim((string)($relayUpdated['ticket_code'] ?? $ticketCodeInSubject))
            );
            if (!empty($forwardRes['errors'])) {
                $resultRow['note'] .= ' | allegati con errori: ' . implode('; ', $forwardRes['errors']);
            } elseif (($forwardRes['sent'] ?? 0) > 0) {
                $resultRow['note'] .= ' | allegati inoltrati: ' . intval($forwardRes['sent']);
            }

            if (($res['mode'] ?? '') === 'create' && is_array($relayUpdated) && !empty($relayUpdated)) {
                $ackRes = ticketMailSendRelayNotification(
                    $relayUpdated,
                    "Ricezione ticket " . trim((string)($relayUpdated['ticket_code'] ?? '')),
                    "Il tuo messaggio è stato ricevuto.\n\nTicket: " . trim((string)($relayUpdated['ticket_code'] ?? '')) . "\nIl tuo caso sarà preso in carico appena possibile."
                );
                if (empty($ackRes['ok'])) {
                    $resultRow['note'] .= ' | conferma mail non inviata';
                }
            } elseif (($res['mode'] ?? '') === 'closed_followup' && is_array($relayUpdated) && !empty($relayUpdated)) {
                $ackRes = ticketMailSendRelayNotification(
                    $relayUpdated,
                    "Messaggio ricevuto su ticket " . trim((string)($relayUpdated['ticket_code'] ?? '')),
                    "Il tuo messaggio è stato ricevuto.\n\nIl ticket " . trim((string)($relayUpdated['ticket_code'] ?? '')) . " risulta chiuso: il servizio GestOre deciderà se riaprirlo o aprire una nuova richiesta."
                );
                if (empty($ackRes['ok'])) {
                    $resultRow['note'] .= ' | conferma mail ticket chiuso non inviata';
                }
            }
        } else {
            foreach ($attachments as $attachment) {
                @unlink((string)($attachment['path'] ?? ''));
            }
        }

        $resultRow['ticket_code'] = trim((string)($res['ticket_code'] ?? $ticketCodeInSubject));
        $resultRow['status'] = !empty($res['ok']) ? 'imported' : 'error';
        $mode = (string)($res['mode'] ?? '');
        $resultRow['note'] = !empty($res['ok'])
            ? ($mode === 'create'
                ? 'nuovo ticket creato'
                : ($mode === 'closed_followup'
                    ? 'messaggio su ticket chiuso, scelta admin richiesta'
                    : ($mode === 'open_followup'
                        ? 'messaggio su ticket aperto, scelta admin richiesta'
                        : 'ticket aggiornato')))
            : trim((string)($res['error'] ?? 'errore ticket'));

        ticketMailLogImported([
            'message_uid' => $uidKey,
            'message_id' => $messageId,
            'from_email' => $fromEmail,
            'to_addresses' => implode(', ', $toAddresses),
            'subject' => $subject,
            'tipo_utente' => (string)($actor['tipo_utente'] ?? ''),
            'idDocente' => (($actor['tipo_utente'] ?? '') === 'docente') ? intval($actor['id'] ?? 0) : null,
            'idStudente' => (($actor['tipo_utente'] ?? '') === 'studente') ? intval($actor['id'] ?? 0) : null,
            'idGenitore' => (($actor['tipo_utente'] ?? '') === 'genitore') ? intval($actor['id'] ?? 0) : null,
            'idRelay' => intval($res['idRelay'] ?? 0) > 0 ? intval($res['idRelay']) : null,
            'ticket_code' => $resultRow['ticket_code'],
            'esito' => !empty($res['ok']) ? 'IMPORTATA' : 'ERRORE',
            'nota' => $resultRow['note'],
        ]);

        if (!empty($res['ok']) && $markSeen) {
            imap_setflag_full($inbox, (string)$msgNo, '\\Seen');
        }

        $results[] = $resultRow;
    }

    @imap_close($inbox);
    ticketMailClearImapRuntimeErrors();

    $imported = count(array_filter($results, function ($row) {
        return ($row['status'] ?? '') === 'imported';
    }));
    $errors = count(array_filter($results, function ($row) {
        return ($row['status'] ?? '') === 'error';
    }));
    $skipped = count(array_filter($results, function ($row) {
        return ($row['status'] ?? '') === 'skipped';
    }));

    return [
        'ok' => true,
        'message' => 'Import mail completato',
        'counts' => [
            'imported' => $imported,
            'errors' => $errors,
            'skipped' => $skipped,
            'processed' => count($results),
        ],
        'mailbox_used' => $openedMailbox,
        'results' => $results,
    ];
}

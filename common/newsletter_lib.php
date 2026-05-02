<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/send-mail.php';
require_once __DIR__ . '/telegram_webhook_api.php';

function newsletterTableExists(string $tableName): bool
{
    return dbGetValue("SHOW TABLES LIKE " . dbQ($tableName)) !== null;
}

function newsletterMissingTables(): array
{
    $missing = [];
    foreach (['gestore_news_item', 'gestore_newsletter'] as $table) {
        if (!newsletterTableExists($table)) {
            $missing[] = $table;
        }
    }
    return $missing;
}

function newsletterPostValue(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function newsletterPostBool(string $key, bool $default = false): bool
{
    if (!isset($_POST[$key])) {
        return $default;
    }
    return in_array((string)$_POST[$key], ['1', 'true', 'on', 'yes'], true);
}

function newsletterNormalizeAudience(string $audience): string
{
    $audience = strtolower(trim($audience));
    return in_array($audience, ['tutti', 'docenti', 'ata'], true) ? $audience : 'tutti';
}

function newsletterNormalizeChannels(array $channels): array
{
    $valid = [];
    foreach ($channels as $channel) {
        $channel = strtolower(trim((string)$channel));
        if (in_array($channel, ['mail', 'telegram'], true)) {
            $valid[] = $channel;
        }
    }
    return array_values(array_unique($valid));
}

function newsletterSaveNewsItem(array $data): int
{
    $title = trim((string)($data['title'] ?? ''));
    $body = trim((string)($data['body'] ?? ''));
    $audience = newsletterNormalizeAudience((string)($data['audience'] ?? 'tutti'));
    $channels = newsletterNormalizeChannels((array)($data['channels'] ?? []));
    $newsDate = trim((string)($data['news_date'] ?? date('Y-m-d')));
    $createdBy = (int)($data['created_by_user_id'] ?? 0);

    if ($title === '' || $body === '') {
        return 0;
    }

    dbExec("
        INSERT INTO gestore_news_item (
            titolo,
            contenuto,
            audience,
            channels,
            data_novita,
            created_by_user_id,
            created_at
        ) VALUES (
            " . dbQ($title) . ",
            " . dbQ($body) . ",
            " . dbQ($audience) . ",
            " . dbQ(implode(',', $channels)) . ",
            " . dbQ($newsDate) . ",
            " . ($createdBy > 0 ? dbI($createdBy) : 'NULL') . ",
            NOW()
        )
    ");

    return (int)dblastId();
}

function newsletterGetRecentItems(int $days = 15): array
{
    $days = max(1, min(90, $days));
    $rows = dbGetAll("
        SELECT *
        FROM gestore_news_item
        WHERE data_novita >= DATE_SUB(CURDATE(), INTERVAL " . dbI($days) . " DAY)
        ORDER BY data_novita DESC, id DESC
    ");
    return is_array($rows) ? $rows : [];
}

function newsletterGetLatestSent(int $limit = 20): array
{
    $rows = dbGetAll("
        SELECT *
        FROM gestore_newsletter
        ORDER BY id DESC
        LIMIT " . dbI(max(1, min(50, $limit))) . "
    ");
    return is_array($rows) ? $rows : [];
}

function newsletterArchiveDir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'newsletter';
}

function newsletterSlug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    $text = trim((string)$text, '-');
    return $text !== '' ? $text : 'newsletter';
}

function newsletterArchiveDraft(array $draft, string $status = 'bozza'): ?array
{
    $dir = newsletterArchiveDir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }
    if (!is_writable($dir)) {
        return null;
    }

    $title = trim((string)($draft['title'] ?? 'Newsletter GestOre'));
    $intro = trim((string)($draft['intro'] ?? ''));
    $body = trim((string)($draft['body'] ?? ''));
    $version = trim((string)($draft['version'] ?? ''));
    $date = date('Y-m-d_H-i-s');
    $nameParts = array_filter([
        $date,
        $version !== '' ? 'v' . newsletterSlug($version) : '',
        newsletterSlug($status),
    ]);
    $fileName = implode('_', $nameParts) . '.md';
    $path = $dir . DIRECTORY_SEPARATOR . $fileName;

    $content = [
        '# ' . $title,
        '',
        '> Stato: ' . $status,
        '> Generata: ' . date('d/m/Y H:i:s'),
    ];
    if ($version !== '') {
        $content[] = '> Versione: ' . $version;
    }
    $content[] = '';
    if ($intro !== '') {
        $content[] = $intro;
        $content[] = '';
    }
    if ($body !== '') {
        $content[] = $body;
        $content[] = '';
    }

    if (@file_put_contents($path, implode("\n", $content)) === false) {
        return null;
    }

    return [
        'path' => $path,
        'file' => $fileName,
        'url' => '../newsletter/' . rawurlencode($fileName),
    ];
}

function newsletterArchiveList(int $limit = 30): array
{
    $dir = newsletterArchiveDir();
    if (!is_dir($dir) || !is_readable($dir)) {
        return [];
    }

    $files = @glob($dir . DIRECTORY_SEPARATOR . '*.md');
    if (!is_array($files)) {
        return [];
    }

    usort($files, function (string $a, string $b): int {
        return ((int)@filemtime($b)) <=> ((int)@filemtime($a));
    });

    $items = [];
    foreach (array_slice($files, 0, max(1, min(100, $limit))) as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $file = basename($path);
        $items[] = [
            'file' => $file,
            'url' => '../newsletter/' . rawurlencode($file),
            'date' => date('d/m/Y H:i', (int)@filemtime($path)),
            'size' => (int)@filesize($path),
        ];
    }

    return $items;
}

function newsletterNormalizeChangelogLine(string $line): string
{
    $line = trim($line);
    $line = preg_replace('/<!--.*?-->/u', '', $line);
    $line = preg_replace('/^#+\s*/u', '', $line);
    $line = preg_replace('/^\*\*(.*?)\*\*$/u', '$1', $line);
    return trim((string)$line);
}

function newsletterLatestChangelogSection(): ?array
{
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'changelog.md';
    if (!is_file($path)) {
        return null;
    }

    $content = (string)file_get_contents($path);
    if (!preg_match('/^##\s+Version\s+([^\r\n]+)\R([\s\S]*?)(?=^##\s+Version\s+|\z)/mu', $content, $match)) {
        return null;
    }

    $header = trim($match[1]);
    $version = $header;
    $date = '';
    if (preg_match('/^([^\s]+)\s+-\s+(.+)$/u', $header, $hm)) {
        $version = trim($hm[1]);
        $date = trim($hm[2]);
    }

    return [
        'version' => $version,
        'date' => $date,
        'body' => trim($match[2]),
    ];
}

function newsletterParseChangelogSection(array $section): array
{
    $lines = preg_split('/\R/u', (string)($section['body'] ?? ''));
    $summary = [];
    $details = [];
    $mode = '';
    $currentDate = '';
    $currentArea = '';

    foreach ($lines as $line) {
        $trimmed = trim((string)$line);
        if ($trimmed === '' || strpos($trimmed, '<!--') === 0) {
            continue;
        }

        if (preg_match('/^#####\s+Sintesi/u', $trimmed)) {
            $mode = 'summary';
            continue;
        }
        if (preg_match('/^#####\s+Dettaglio/u', $trimmed)) {
            $mode = 'details';
            continue;
        }
        if (preg_match('/^#####\s+(.+)$/u', $trimmed, $m)) {
            $mode = 'details';
            $currentDate = newsletterNormalizeChangelogLine($m[1]);
            $details[$currentDate] = $details[$currentDate] ?? [];
            $currentArea = '';
            continue;
        }
        if (preg_match('/^\*\*(.*?)\*\*$/u', $trimmed, $m)) {
            $currentArea = newsletterNormalizeChangelogLine($m[1]);
            if ($currentDate !== '') {
                $details[$currentDate][$currentArea] = $details[$currentDate][$currentArea] ?? [];
            }
            continue;
        }

        if (strpos($trimmed, '- ') === 0) {
            $item = newsletterNormalizeChangelogLine(substr($trimmed, 2));
            if (
                $item === ''
                || stripos($item, 'Aggiornamento generato automaticamente') === 0
                || stripos($item, 'Commit analizzati') === 0
                || stripos($item, 'Periodo commit') === 0
            ) {
                continue;
            }
            if ($mode === 'summary') {
                $summary[] = $item;
            } elseif ($mode === 'details' && $currentDate !== '' && $currentArea !== '') {
                if (!preg_match('/^Altre\s+\d+\s+modifiche\s+minori/u', $item)) {
                    $details[$currentDate][$currentArea][] = $item;
                }
            }
        }
    }

    return [
        'summary' => $summary,
        'details' => $details,
    ];
}

function newsletterBuildDraftFromChangelog(int $maxDates = 4, int $maxItemsPerArea = 3): array
{
    $maxDates = max(1, min(20, $maxDates));
    $maxItemsPerArea = max(1, min(20, $maxItemsPerArea));
    $section = newsletterLatestChangelogSection();
    $fallback = [
        'title' => 'Novita GestOre - ' . date('d/m/Y'),
        'intro' => 'Non ho trovato un aggiornamento valido nel file changelog.md.',
        'body' => 'Aggiorna prima il changelog con lo script manuale, poi rigenera la bozza da questa pagina.',
        'items' => [],
        'period_start' => date('Y-m-d'),
        'period_end' => date('Y-m-d'),
        'source' => 'changelog',
    ];

    if ($section === null) {
        return $fallback;
    }

    $parsed = newsletterParseChangelogSection($section);
    $title = 'Novita GestOre - versione ' . (string)$section['version'];
    $intro = 'Ecco le principali novita introdotte in GestOre'
        . ((string)$section['date'] !== '' ? ' con l\'aggiornamento del ' . (string)$section['date'] : '')
        . '.';

    $body = [];
    if (!empty($parsed['summary'])) {
        $body[] = 'In evidenza';
        foreach ($parsed['summary'] as $item) {
            $body[] = '- ' . $item;
        }
        $body[] = '';
    }

    if (!empty($parsed['details'])) {
        $body[] = 'Le principali novita';
        $dateCount = 0;
        foreach ($parsed['details'] as $dateLabel => $areas) {
            if ($dateCount >= $maxDates) {
                break;
            }
            $dateLines = [];
            foreach ($areas as $area => $items) {
                $items = array_slice(array_values(array_filter($items)), 0, $maxItemsPerArea);
                if (empty($items)) {
                    continue;
                }
                $dateLines[] = '';
                $dateLines[] = $area;
                foreach ($items as $item) {
                    $dateLines[] = '- ' . $item;
                }
            }
            if (!empty($dateLines)) {
                $body[] = '';
                $body[] = $dateLabel;
                $body = array_merge($body, $dateLines);
                $dateCount++;
            }
        }
    }

    return [
        'title' => $title,
        'intro' => $intro,
        'body' => trim(implode("\n", $body)),
        'items' => [],
        'period_start' => date('Y-m-d'),
        'period_end' => date('Y-m-d'),
        'source' => 'changelog',
        'version' => (string)$section['version'],
    ];
}

function newsletterBuildDraft(int $days = 15): array
{
    $items = newsletterGetRecentItems($days);
    $periodStart = date('Y-m-d', strtotime('-' . max(1, $days) . ' days'));
    $periodEnd = date('Y-m-d');
    $title = 'Novità GestOre - ' . date('d/m/Y');

    if (empty($items)) {
        return [
            'title' => $title,
            'intro' => "Negli ultimi {$days} giorni non risultano novità registrate nel diario GestOre.",
            'body' => "Nessuna novità registrata nel periodo {$periodStart} - {$periodEnd}.",
            'items' => [],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    $lines = [];
    foreach ($items as $item) {
        $line = '- ' . trim((string)($item['titolo'] ?? ''));
        $content = trim((string)($item['contenuto'] ?? ''));
        if ($content !== '') {
            $line .= ': ' . preg_replace('/\s+/u', ' ', $content);
        }
        $lines[] = $line;
    }

    return [
        'title' => $title,
        'intro' => "Ecco un riepilogo delle ultime novità di GestOre degli ultimi {$days} giorni.",
        'body' => implode("\n", $lines),
        'items' => $items,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ];
}

function newsletterBuildHtml(string $title, string $intro, string $body, bool $useEmbeddedImageCid = false): string
{
    $titleHtml = nl2br(htmlspecialchars($title));
    $introHtml = nl2br(htmlspecialchars($intro));
    $bodyHtml = newsletterBuildBodyHtml($body);
    $headerImage = newsletterHeaderImageHtml($useEmbeddedImageCid);

    return '
    <div style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
        <div style="max-width:760px;margin:0 auto;background:#ffffff;border:1px solid #dbe4ee;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
            <div style="background:linear-gradient(135deg,#0f766e 0%,#0b5d56 100%);padding:24px 28px;color:#ffffff;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                    <tr>
                        <td style="vertical-align:middle;width:92px;padding-right:18px;">' . $headerImage . '</td>
                        <td style="vertical-align:middle;">
                <div style="font-size:13px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;opacity:0.92;">GestOre</div>
                <div style="font-size:28px;font-weight:700;line-height:1.2;margin-top:6px;">ITT Buonarroti - Trento</div>
                <div style="font-size:14px;line-height:1.5;margin-top:8px;opacity:0.92;">Aggiornamenti funzionalità e novità recenti</div>
                        </td>
                    </tr>
                </table>
            </div>
            <div style="padding:28px;">
                <div style="font-size:22px;font-weight:700;line-height:1.3;color:#111827;margin-bottom:10px;">' . $titleHtml . '</div>
                <div style="font-size:15px;line-height:1.7;color:#1f2937;margin-bottom:20px;">' . $introHtml . '</div>
                <div style="background:#f8fafc;border:1px solid #e5edf5;border-radius:14px;padding:18px 20px;font-size:15px;line-height:1.45;color:#1f2937;">' . $bodyHtml . '</div>
                <div style="font-size:12px;line-height:1.5;color:#64748b;margin-top:18px;text-align:center;">Generato e inviato da GestOre.</div>
            </div>
        </div>
    </div>';
}

function newsletterHeaderImageHtml(bool $useEmbeddedImageCid = false): string
{
    $src = $useEmbeddedImageCid && newsletterHeaderImagePath() !== '' ? 'cid:gestore_newsletter_logo' : newsletterHeaderImageDataUri();
    if ($src === '') {
        return '<div style="width:74px;height:74px;border-radius:20px;background:rgba(255,255,255,0.18);text-align:center;line-height:74px;font-size:34px;">&#128227;</div>';
    }

    return '<img src="' . htmlspecialchars($src) . '" alt="GestOre" width="74" height="74" style="display:block;width:74px;height:74px;object-fit:contain;border-radius:18px;background:#ffffff;padding:8px;">';
}

function newsletterHeaderImageDataUri(): string
{
    static $dataUri = null;
    if ($dataUri !== null) {
        return $dataUri;
    }

    $path = newsletterHeaderImagePath();
    if ($path !== '') {
        $bytes = @file_get_contents($path);
        if ($bytes !== false && $bytes !== '') {
            $dataUri = 'data:image/png;base64,' . base64_encode($bytes);
            return $dataUri;
        }
    }

    $dataUri = '';
    return $dataUri;
}

function newsletterHeaderImagePath(): string
{
    $candidates = [
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logoB_google.png',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo_Buonarroti.png',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'intestazione.png',
    ];

    foreach ($candidates as $path) {
        if (is_file($path) && is_readable($path)) {
            return $path;
        }
    }

    return '';
}

function newsletterBuildBodyHtml(string $body): string
{
    $lines = preg_split('/\R/u', trim($body));
    $html = [];
    $inList = false;

    $closeList = function () use (&$html, &$inList): void {
        if ($inList) {
            $html[] = '</ul>';
            $inList = false;
        }
    };

    foreach ($lines as $rawLine) {
        $line = trim((string)$rawLine);
        if ($line === '') {
            continue;
        }

        if (strpos($line, '- ') === 0) {
            if (!$inList) {
                $html[] = '<ul style="margin:6px 0 12px 20px;padding:0;">';
                $inList = true;
            }
            $html[] = '<li style="margin:0 0 5px 0;">' . htmlspecialchars(trim(substr($line, 2))) . '</li>';
            continue;
        }

        $closeList();
        $safe = htmlspecialchars($line);
        $lower = strtolower($line);
        if (in_array($lower, ['in evidenza', 'le principali novita', 'le principali novità'], true)) {
            $emoji = stripos($line, 'evidenza') !== false ? '✨ ' : '🧭 ';
            $html[] = '<h3 style="font-size:17px;margin:12px 0 8px 0;color:#0f766e;">' . $emoji . $safe . '</h3>';
        } elseif (preg_match('/^\d{1,2}\s+[a-zàèéìòù]+\s+\d{4}/iu', $line)) {
            $html[] = '<h4 style="font-size:15px;margin:14px 0 6px 0;color:#111827;">📅 ' . $safe . '</h4>';
        } else {
            $html[] = '<div style="font-weight:700;margin:10px 0 5px 0;color:#334155;">🔹 ' . $safe . '</div>';
        }
    }

    $closeList();
    return implode("\n", $html);
}

function newsletterTelegramRecipients(string $audience = 'tutti'): array
{
    $audience = newsletterNormalizeAudience($audience);
    if ($audience === 'ata') {
        return [];
    }

    $query = "
        SELECT d.id, d.nome, d.cognome, d.email, t.telegram_chat_id
        FROM docente_telegram t
        INNER JOIN docente d ON d.id = t.idDocente
        WHERE d.attivo = 1
          AND t.attivo = 1
          AND t.consenso_notifiche = 1
          AND t.telegram_chat_id IS NOT NULL
          AND t.telegram_chat_id <> ''
        ORDER BY d.cognome, d.nome
    ";

    $rows = dbGetAll($query);
    return is_array($rows) ? $rows : [];
}

function newsletterMailRecipients(string $audience = 'tutti'): array
{
    $audience = newsletterNormalizeAudience($audience);
    $rows = [];

    if (in_array($audience, ['tutti', 'docenti'], true)) {
        $docRows = dbGetAll("
            SELECT 'docente' AS tipo_utente, id, nome, cognome, email
            FROM docente
            WHERE attivo = 1
              AND email IS NOT NULL
              AND email <> ''
        ");
        if (is_array($docRows)) {
            $rows = array_merge($rows, $docRows);
        }
    }

    if (in_array($audience, ['tutti', 'ata'], true)) {
        $ataRows = dbGetAll("
            SELECT 'ata' AS tipo_utente, id, nome, cognome, email
            FROM personale_ata
            WHERE attivo = 1
              AND email IS NOT NULL
              AND email <> ''
        ");
        if (is_array($ataRows)) {
            $rows = array_merge($rows, $ataRows);
        }
    }

    $unique = [];
    foreach ($rows as $row) {
        $email = strtolower(trim((string)($row['email'] ?? '')));
        if ($email === '' || isset($unique[$email])) {
            continue;
        }
        $unique[$email] = $row;
    }

    return array_values($unique);
}

function newsletterSendTelegramBroadcast(string $message, string $audience = 'tutti'): array
{
    global $__settings;

    $botToken = trim((string)($__settings->telegram->bot_token ?? ''));
    if ($botToken === '') {
        return ['ok' => false, 'error' => 'bot token telegram mancante', 'sent' => 0, 'errors' => []];
    }

    $sent = 0;
    $errors = [];
    foreach (newsletterTelegramRecipients($audience) as $recipient) {
        $chatId = trim((string)($recipient['telegram_chat_id'] ?? ''));
        if ($chatId === '') {
            continue;
        }
        $res = tgSendMessage($botToken, $chatId, $message);
        if (!empty($res['ok'])) {
            $sent++;
        } else {
            $errors[] = trim((string)(($recipient['cognome'] ?? '') . ' ' . ($recipient['nome'] ?? '') . ' - ' . ($res['error'] ?? 'errore sconosciuto')));
        }
    }

    return ['ok' => empty($errors), 'sent' => $sent, 'errors' => $errors];
}

function newsletterBuildTelegramText(string $title, string $intro, string $body): string
{
    return newsletterFormatTelegramNewsletter($title, $intro, $body, true);
}

function newsletterFormatTelegramNewsletter(string $title, string $intro, string $body, bool $preview = false): string
{
    $lines = preg_split('/\R/u', trim($body));
    $out = [];

    if ($preview) {
        $out[] = "🧪 ANTEPRIMA NEWSLETTER";
        $out[] = "";
    }

    $out[] = "📣 " . trim($title);
    if (trim($intro) !== '') {
        $out[] = "";
        $out[] = "📝 " . trim($intro);
    }

    foreach ($lines as $rawLine) {
        $line = trim((string)$rawLine);
        if ($line === '') {
            continue;
        }

        $lower = strtolower($line);
        if (in_array($lower, ['in evidenza', 'le principali novita', 'le principali novitÃ '], true)) {
            $out[] = "";
            $out[] = (stripos($line, 'evidenza') !== false ? "✨ " : "🧭 ") . $line;
        } elseif (preg_match('/^\d{1,2}\s+[a-zÃ Ã¨Ã©Ã¬Ã²Ã¹]+\s+\d{4}/iu', $line)) {
            $out[] = "";
            $out[] = "📅 " . $line;
        } elseif (strpos($line, '- ') === 0) {
            $out[] = "• " . trim(substr($line, 2));
        } else {
            $out[] = "";
            $out[] = "🔹 " . $line;
        }
    }

    $out[] = "";
    $out[] = "—";
    $out[] = "Generato e inviato da GestOre.";

    return trim(implode("\n", $out));
}

function newsletterFindPreviewTelegramChatId(string $email, string $username = ''): string
{
    $target = newsletterFindPreviewTelegramTarget($email, $username);
    return trim((string)($target['chat_id'] ?? ''));
}

function newsletterFindPreviewTelegramTarget(string $email, string $username = ''): ?array
{
    $email = trim($email);
    $username = trim($username);

    if ($email !== '') {
        $row = dbGetFirst("
            SELECT t.telegram_chat_id, d.nome, d.cognome
            FROM docente_telegram t
            INNER JOIN docente d ON d.id = t.idDocente
            WHERE d.email = " . dbQ($email) . "
              AND t.attivo = 1
              AND t.consenso_notifiche = 1
              AND t.telegram_chat_id IS NOT NULL
              AND t.telegram_chat_id <> ''
            LIMIT 1
        ");
        if (!empty($row['telegram_chat_id'])) {
            return [
                'chat_id' => trim((string)$row['telegram_chat_id']),
                'label' => trim((string)(($row['nome'] ?? '') . ' ' . ($row['cognome'] ?? ''))),
                'source' => 'profilo docente Telegram',
            ];
        }
    }

    if ($username !== '' && newsletterTableExists('admin_telegram')) {
        $row = dbGetFirst("
            SELECT telegram_chat_id, nome, username
            FROM admin_telegram
            WHERE username = " . dbQ($username) . "
              AND attivo = 1
              AND telegram_chat_id IS NOT NULL
              AND telegram_chat_id <> ''
            LIMIT 1
        ");
        if (!empty($row['telegram_chat_id'])) {
            return [
                'chat_id' => trim((string)$row['telegram_chat_id']),
                'label' => trim((string)($row['nome'] ?? $row['username'] ?? 'Admin Telegram')),
                'source' => 'profilo admin Telegram',
            ];
        }
    }

    return null;
}

function newsletterSendTelegramPreview(string $message, string $email, string $username = ''): array
{
    global $__settings;

    $botToken = trim((string)($__settings->telegram->bot_token ?? ''));
    if ($botToken === '') {
        return ['ok' => false, 'sent' => 0, 'error' => 'bot token telegram mancante'];
    }

    $chatId = newsletterFindPreviewTelegramChatId($email, $username);
    if ($chatId === '') {
        return ['ok' => false, 'sent' => 0, 'error' => 'chat Telegram personale non trovato'];
    }

    $res = tgSendMessage($botToken, $chatId, $message);
    if (!empty($res['ok'])) {
        return ['ok' => true, 'sent' => 1, 'error' => ''];
    }

    return ['ok' => false, 'sent' => 0, 'error' => (string)($res['error'] ?? 'errore sconosciuto')];
}

function newsletterSendMailBroadcast(string $subject, string $intro, string $body, string $audience = 'tutti'): array
{
    global $__settings;

    $visibleFrom = trim((string)($__settings->ticketMail->reply_visible_from ?? $__settings->ticketMail->alias_address ?? $__settings->local->smtpMail ?? ''));
    $senderEmail = trim((string)($__settings->local->smtpMail ?? ''));
    $logoPath = newsletterHeaderImagePath();
    $html = newsletterBuildHtml($subject, $intro, $body, $logoPath !== '');

    $sent = 0;
    $errors = [];
    foreach (newsletterMailRecipients($audience) as $recipient) {
        $email = trim((string)($recipient['email'] ?? ''));
        if ($email === '') {
            continue;
        }
        $name = trim((string)(($recipient['cognome'] ?? '') . ' ' . ($recipient['nome'] ?? '')));
        $ok = sendMailCustom($email, $name, $subject, $html, [
            'from_email' => $visibleFrom,
            'from_name' => 'GestOre ITT Buonarroti - Trento',
            'reply_to_email' => $visibleFrom,
            'reply_to_name' => 'GestOre',
            'sender_email' => $senderEmail !== '' ? $senderEmail : $visibleFrom,
            'sender_name' => 'GestOre',
            'embedded_images' => $logoPath !== '' ? ['gestore_newsletter_logo' => $logoPath] : [],
        ]);
        if ($ok) {
            $sent++;
        } else {
            $errors[] = ($name !== '' ? $name : $email);
        }
    }

    return ['ok' => empty($errors), 'sent' => $sent, 'errors' => $errors];
}

function newsletterSendMailPreview(string $email, string $name, string $subject, string $intro, string $body): array
{
    global $__settings;

    $email = trim($email);
    if ($email === '') {
        return ['ok' => false, 'sent' => 0, 'error' => 'email utente non disponibile'];
    }

    $visibleFrom = trim((string)($__settings->ticketMail->reply_visible_from ?? $__settings->ticketMail->alias_address ?? $__settings->local->smtpMail ?? ''));
    $senderEmail = trim((string)($__settings->local->smtpMail ?? ''));
    $logoPath = newsletterHeaderImagePath();
    $html = newsletterBuildHtml('[ANTEPRIMA] ' . $subject, $intro, $body, $logoPath !== '');
    $ok = sendMailCustom($email, $name, '[ANTEPRIMA] ' . $subject, $html, [
        'from_email' => $visibleFrom,
        'from_name' => 'GestOre ITT Buonarroti - Trento',
        'reply_to_email' => $visibleFrom,
        'reply_to_name' => 'GestOre',
        'sender_email' => $senderEmail !== '' ? $senderEmail : $visibleFrom,
        'sender_name' => 'GestOre',
        'embedded_images' => $logoPath !== '' ? ['gestore_newsletter_logo' => $logoPath] : [],
    ]);

    return ['ok' => (bool)$ok, 'sent' => $ok ? 1 : 0, 'error' => $ok ? '' : 'invio mail non riuscito'];
}

function newsletterSaveDispatch(array $data): int
{
    dbExec("
        INSERT INTO gestore_newsletter (
            titolo,
            intro_text,
            body_text,
            period_start,
            period_end,
            channels,
            audience,
            telegram_sent_count,
            mail_sent_count,
            stato,
            created_by_user_id,
            created_at,
            sent_at
        ) VALUES (
            " . dbQ($data['title'] ?? '') . ",
            " . dbQ($data['intro'] ?? '') . ",
            " . dbQ($data['body'] ?? '') . ",
            " . dbQ($data['period_start'] ?? '') . ",
            " . dbQ($data['period_end'] ?? '') . ",
            " . dbQ($data['channels'] ?? '') . ",
            " . dbQ($data['audience'] ?? 'tutti') . ",
            " . dbI((int)($data['telegram_sent_count'] ?? 0)) . ",
            " . dbI((int)($data['mail_sent_count'] ?? 0)) . ",
            " . dbQ($data['status'] ?? 'INVIATA') . ",
            " . ((int)($data['created_by_user_id'] ?? 0) > 0 ? dbI((int)$data['created_by_user_id']) : 'NULL') . ",
            NOW(),
            " . (!empty($data['sent_at']) ? 'NOW()' : 'NULL') . "
        )
    ");

    return (int)dblastId();
}

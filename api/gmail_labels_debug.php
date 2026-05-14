<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/gmail_api_lib.php';
require_once __DIR__ . '/../common/ticket_mail_lib.php';

header('Content-Type: application/json');
@set_time_limit(60);

ruoloRichiesto('admin');

function gmailDebugNormalizeImapMailbox($mailbox): string
{
    return mb_convert_encoding((string)$mailbox, 'UTF-8', 'UTF7-IMAP');
}

try {
    $config = ticketMailConfig();
    $labelsRes = gmailApiRequest('GET', 'https://gmail.googleapis.com/gmail/v1/users/me/labels');
    $labels = [];
    foreach (($labelsRes['labels'] ?? []) as $label) {
        $labels[] = [
            'id' => trim((string)($label['id'] ?? '')),
            'name' => trim((string)($label['name'] ?? '')),
            'type' => trim((string)($label['type'] ?? '')),
            'messageListVisibility' => trim((string)($label['messageListVisibility'] ?? '')),
            'labelListVisibility' => trim((string)($label['labelListVisibility'] ?? '')),
        ];
    }

    $imapMailboxes = [];
    $imapErrors = [];
    if (function_exists('imap_open')) {
        $baseMailbox = '{imap.gmail.com:993/imap/ssl}';
        $inbox = @imap_open($baseMailbox . 'INBOX', $config['mailbox_user'], $config['mailbox_pass']);
        if ($inbox === false) {
            $imapErrors[] = imap_last_error();
            ticketMailClearImapRuntimeErrors();
        } else {
            $list = @imap_list($inbox, $baseMailbox, '*') ?: [];
            foreach ($list as $mailbox) {
                $decoded = gmailDebugNormalizeImapMailbox($mailbox);
                $imapMailboxes[] = [
                    'raw' => (string)$mailbox,
                    'decoded' => $decoded,
                    'without_prefix' => preg_replace('/^\{[^}]+\}/', '', $decoded),
                ];
            }
            @imap_close($inbox);
            ticketMailClearImapRuntimeErrors();
        }
    } else {
        $imapErrors[] = 'Estensione IMAP non disponibile';
    }

    $out = [
        'ok' => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'ticket_mail_config' => [
            'alias_address' => $config['alias_address'] ?? '',
            'imap_mailbox' => $config['imap_mailbox'] ?? '',
            'imap_fallback_mailboxes' => $config['imap_fallback_mailboxes'] ?? [],
        ],
        'gmail_api_labels' => $labels,
        'imap_mailboxes' => $imapMailboxes,
        'imap_errors' => $imapErrors,
    ];

    $logFile = __DIR__ . '/../log/gmail_labels_debug.json';
    file_put_contents($logFile, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    infoGmail('diagnostica label/cartelle generata: log/gmail_labels_debug.json labels=' . count($labels) . ' imap_mailboxes=' . count($imapMailboxes));

    $out['debug_file'] = 'log/gmail_labels_debug.json';
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    errorGmail('diagnostica label/cartelle errore: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

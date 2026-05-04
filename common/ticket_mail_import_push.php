<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';
require_once __DIR__ . '/ticket_mail_lib.php';

date_default_timezone_set('Europe/Rome');

function ticketMailImportFromPush($limit = 20)
{
    $logDir = __DIR__ . '/../log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $lockFile = $logDir . '/ticket_mail_import_push.lock';
    $lockFp = fopen($lockFile, 'c');

    if (!$lockFp) {
        throw new Exception('Impossibile creare lock file: ' . $lockFile);
    }

    if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
        infocron('[ticket_mail_import_push] import già in corso, evento push ignorato');
        fclose($lockFp);

        return [
            'ok' => true,
            'locked' => true,
            'message' => 'import già in corso'
        ];
    }

    try {
        $result = ticketMailImportInbox((int)$limit, null, true);

        if (!empty($result['quiet_hours_active'])) {
            infocron('[ticket_mail_import_push] fascia silenziosa attiva, import rimandato');

            return [
                'ok' => true,
                'quiet_hours_active' => true,
                'message' => 'quiet-hours'
            ];
        }

        if (empty($result['ok'])) {
            errorcron('[ticket_mail_import_push] errore: ' . trim((string)($result['message'] ?? 'errore sconosciuto')));

            return [
                'ok' => false,
                'message' => trim((string)($result['message'] ?? 'errore sconosciuto')),
                'raw' => $result
            ];
        }

        $counts = $result['counts'] ?? [];

        infocron(
            '[ticket_mail_import_push] processed=' . intval($counts['processed'] ?? 0) .
            ' imported=' . intval($counts['imported'] ?? 0) .
            ' skipped=' . intval($counts['skipped'] ?? 0) .
            ' errors=' . intval($counts['errors'] ?? 0) .
            ' mailbox=' . trim((string)($result['mailbox_used'] ?? ''))
        );

        return [
            'ok' => true,
            'counts' => $counts,
            'mailbox_used' => trim((string)($result['mailbox_used'] ?? '')),
            'raw' => $result
        ];

    } finally {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}
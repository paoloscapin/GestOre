<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';
require_once __DIR__ . '/ticket_mail_lib.php';

date_default_timezone_set('Europe/Rome');

$result = ticketMailImportInbox(20, null, true);

if (!empty($result['quiet_hours_active'])) {
    infocron('[ticket_mail_import_cron] fascia silenziosa attiva, import rimandato');
    echo "quiet-hours\n";
    exit;
}

if (empty($result['ok'])) {
    errorcron('[ticket_mail_import_cron] errore: ' . trim((string)($result['message'] ?? 'errore sconosciuto')));
    echo "error\n";
    exit(1);
}

$counts = $result['counts'] ?? [];
infocron(
    '[ticket_mail_import_cron] processed=' . intval($counts['processed'] ?? 0) .
    ' imported=' . intval($counts['imported'] ?? 0) .
    ' skipped=' . intval($counts['skipped'] ?? 0) .
    ' errors=' . intval($counts['errors'] ?? 0) .
    ' mailbox=' . trim((string)($result['mailbox_used'] ?? ''))
);

echo "ok\n";

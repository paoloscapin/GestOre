<?php
require_once __DIR__ . '/../common/gmail_api_lib.php';
require_once __DIR__ . '/../common/ticket_mail_import_push.php';

header('Content-Type: application/json');
@set_time_limit(90);

$raw = file_get_contents('php://input');

$data = json_decode($raw, true);

if (!$data || empty($data['message']['data'])) {
    warningGmail('webhook Pub/Sub non valido: payload senza message.data');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Payload Pub/Sub non valido']);
    exit;
}

$decoded = json_decode(base64_decode($data['message']['data']), true);

if (!$decoded || empty($decoded['historyId'])) {
    warningGmail('webhook Pub/Sub non valido: historyId mancante');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'historyId mancante']);
    exit;
}

$newHistoryId = (string)$decoded['historyId'];
$emailAddress = $decoded['emailAddress'] ?? '';

$lock = gmailAcquireLock(GMAIL_WEBHOOK_LOCK_FILE, 300);
if (!$lock) {
    echo json_encode([
        'ok' => true,
        'skipped' => true,
        'message' => 'Webhook Gmail gia in elaborazione',
        'historyId' => $newHistoryId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$state = gmailLoadState();
$lastHistoryId = $state['historyId'] ?? null;

if (!$lastHistoryId) {
    $state['historyId'] = gmailMaxHistoryId($state['historyId'] ?? $lastHistoryId, $newHistoryId);
    $state['emailAddress'] = $emailAddress;
    $state['updated_at'] = date('Y-m-d H:i:s');
    gmailSaveState($state);
    infoGmail('webhook inizializzato: primo historyId salvato historyId=' . $newHistoryId . ' email=' . $emailAddress);

    echo json_encode(['ok' => true, 'message' => 'Primo historyId salvato']);
    gmailReleaseLock($lock, GMAIL_WEBHOOK_LOCK_FILE);
    exit;
}

if (gmailCompareHistoryId($newHistoryId, $lastHistoryId) <= 0) {
    echo json_encode([
        'ok' => true,
        'skipped' => true,
        'message' => 'Notifica Gmail gia superata',
        'lastHistoryId' => $lastHistoryId,
        'newHistoryId' => $newHistoryId,
    ], JSON_UNESCAPED_UNICODE);
    gmailReleaseLock($lock, GMAIL_WEBHOOK_LOCK_FILE);
    exit;
}

try {
    $messageIds = gmailListHistory($lastHistoryId);
    $importResult = null;

    if (count($messageIds) > 0) {
        // Gmail Pub/Sub serve solo da trigger: il filtro reale avviene su INBOX/UNSEEN via IMAP.
        $allowedMessageIds = [];
        foreach ($messageIds as $gmailMessageId) {
            $metadata = gmailGetMessageMetadata($gmailMessageId);
            $messageIdHeader = gmailGetHeader($metadata, 'Message-ID');
            if (trim($messageIdHeader) !== '') {
                $allowedMessageIds[] = $messageIdHeader;
            }
        }
        $importResult = ticketMailImportFromPush(20, !empty($allowedMessageIds), $allowedMessageIds);
        $counts = $importResult['counts'] ?? [];
        $imported = intval($counts['imported'] ?? 0);
        $skipped = intval($counts['skipped'] ?? 0);
        $errors = intval($counts['errors'] ?? 0);
        if ($imported > 0 || $errors > 0) {
            infoGmail(
                'webhook elaborato history_messages=' . count($messageIds) .
                ' imported=' . $imported .
                ' skipped=' . $skipped .
                ' errors=' . $errors
            );
        }
    } else {
        debugGmail('webhook senza nuovi messaggi INBOX lastHistoryId=' . $lastHistoryId . ' newHistoryId=' . $newHistoryId);
    }

    $state['historyId'] = $newHistoryId;
    $state['emailAddress'] = $emailAddress;
    $state['updated_at'] = date('Y-m-d H:i:s');
    gmailSaveState($state);

    echo json_encode([
        'ok' => true,
        'email' => $emailAddress,
        'lastHistoryId' => $lastHistoryId,
        'newHistoryId' => $newHistoryId,
        'messages' => count($messageIds),
        'importResult' => $importResult
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    errorGmail('webhook errore: ' . $e->getMessage());

    $state['historyId'] = gmailMaxHistoryId($state['historyId'] ?? $lastHistoryId, $newHistoryId);
    $state['emailAddress'] = $emailAddress;
    $state['updated_at'] = date('Y-m-d H:i:s');
    $state['last_error'] = $e->getMessage();
    gmailSaveState($state);

    // Rispondo 200 per evitare retry infiniti Pub/Sub che saturano il server.
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'acknowledged' => true
    ], JSON_UNESCAPED_UNICODE);
} finally {
    gmailReleaseLock($lock, GMAIL_WEBHOOK_LOCK_FILE);
}

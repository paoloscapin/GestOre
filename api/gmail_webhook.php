<?php
require_once __DIR__ . '/../common/gmail_api_lib.php';
require_once __DIR__ . '/../common/ticket_mail_import_push.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');

file_put_contents(
    __DIR__ . '/../log/debug_gmail_webhook.log',
    date('Y-m-d H:i:s') . " RAW:\n" . $raw . "\n\n",
    FILE_APPEND
);

$data = json_decode($raw, true);

if (!$data || empty($data['message']['data'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Payload Pub/Sub non valido']);
    exit;
}

$decoded = json_decode(base64_decode($data['message']['data']), true);

if (!$decoded || empty($decoded['historyId'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'historyId mancante']);
    exit;
}

$newHistoryId = (string)$decoded['historyId'];
$emailAddress = $decoded['emailAddress'] ?? '';

$state = gmailLoadState();
$lastHistoryId = $state['historyId'] ?? null;

if (!$lastHistoryId) {
    $state['historyId'] = $newHistoryId;
    $state['emailAddress'] = $emailAddress;
    $state['updated_at'] = date('Y-m-d H:i:s');
    gmailSaveState($state);

    echo json_encode(['ok' => true, 'message' => 'Primo historyId salvato']);
    exit;
}

try {
    $messageIds = gmailListHistory($lastHistoryId);
$importResult = null;

if (count($messageIds) > 0) {
    $importResult = ticketMailImportFromPush(20);

    file_put_contents(
        __DIR__ . '/../log/debug_ticket_mail_import_push.log',
        date('Y-m-d H:i:s') . "\n" .
        json_encode($importResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
        "\n\n",
        FILE_APPEND
    );
}
    foreach ($messageIds as $messageId) {
        $message = gmailGetMessage($messageId);

        $subject = gmailGetHeader($message, 'Subject');
        $from = gmailGetHeader($message, 'From');
        $date = gmailGetHeader($message, 'Date');
        $body = gmailExtractPlainText($message['payload'] ?? []);

        file_put_contents(
            __DIR__ . '/../log/debug_gmail_messages.log',
            "=============================\n" .
            "DATA LETTURA: " . date('Y-m-d H:i:s') . "\n" .
            "MESSAGE ID: " . $messageId . "\n" .
            "FROM: " . $from . "\n" .
            "DATE: " . $date . "\n" .
            "SUBJECT: " . $subject . "\n\n" .
            mb_substr($body, 0, 4000) . "\n\n",
            FILE_APPEND
        );

        /*
          Qui poi agganciamo il tuo parser ticket attuale.
          Esempio futuro:
          gestoreProcessTicketEmail($messageId, $from, $subject, $body, $message);
        */
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
    file_put_contents(
        __DIR__ . '/../log/debug_gmail_webhook_error.log',
        date('Y-m-d H:i:s') . " ERROR:\n" . $e->getMessage() . "\n\n",
        FILE_APPEND
    );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
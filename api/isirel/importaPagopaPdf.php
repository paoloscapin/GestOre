<?php
declare(strict_types=1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'https://istruzione.cloud.provincia.tn.it',
];

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://istruzione.cloud.provincia.tn.it');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 600');
header('Vary: Origin, Access-Control-Request-Headers');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../common/connect.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function outJson($ok, $extra = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sqlExecOrThrowPdf($query) {
    global $__con;

    if (!mysqli_query($__con, $query)) {
        throw new Exception(mysqli_error($__con) . "\n\nQUERY:\n" . $query);
    }
}

function sqlGetFirstOrThrowPdf($query) {
    global $__con;

    $result = mysqli_query($__con, $query);

    if (!$result) {
        throw new Exception(mysqli_error($__con) . "\n\nQUERY:\n" . $query);
    }

    return mysqli_fetch_assoc($result) ?: null;
}

function readJsonBodyPdf() {
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        outJson(false, ['error' => 'Body JSON mancante'], 400);
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        outJson(false, ['error' => 'JSON non valido'], 400);
    }

    return $data;
}

function ensurePagopaPdfColumns() {
    static $done = false;

    if ($done) {
        return;
    }

    $columns = [];
    $rows = dbGetAll("SHOW COLUMNS FROM pagopa_avvisi_studenti") ?: [];

    foreach ($rows as $row) {
        $columns[$row['Field']] = true;
    }

    $defs = [
        'pdf_file' => "ALTER TABLE pagopa_avvisi_studenti ADD COLUMN pdf_file varchar(255) NULL AFTER payment_link",
        'pdf_original_name' => "ALTER TABLE pagopa_avvisi_studenti ADD COLUMN pdf_original_name varchar(255) NULL AFTER pdf_file",
        'pdf_content_type' => "ALTER TABLE pagopa_avvisi_studenti ADD COLUMN pdf_content_type varchar(100) NULL AFTER pdf_original_name",
        'pdf_size' => "ALTER TABLE pagopa_avvisi_studenti ADD COLUMN pdf_size int(11) NULL AFTER pdf_content_type",
        'pdf_saved_at' => "ALTER TABLE pagopa_avvisi_studenti ADD COLUMN pdf_saved_at datetime NULL AFTER pdf_size",
        'pdf_source_url' => "ALTER TABLE pagopa_avvisi_studenti ADD COLUMN pdf_source_url text NULL AFTER pdf_saved_at",
    ];

    foreach ($defs as $field => $sql) {
        if (empty($columns[$field])) {
            sqlExecOrThrowPdf($sql);
        }
    }

    $done = true;
}

function sanitizePdfName($name) {
    $name = trim((string)$name);
    $name = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $name);
    $name = trim((string)$name, '._-');

    if ($name === '' || stripos($name, '.pdf') === false) {
        $name = 'avviso_pagopa.pdf';
    }

    return substr($name, 0, 180);
}

try {
    $data = readJsonBodyPdf();

    if (($data['source'] ?? '') !== 'ISIREL') {
        outJson(false, ['error' => 'Sorgente non valida'], 400);
    }

    $idRecipient = intval($data['idRecipientIsirel'] ?? 0);
    $idActivity = intval($data['idIsirelActivity'] ?? 0);
    $base64 = (string)($data['base64'] ?? '');
    $checkOnly = !empty($data['checkOnly']);

    if ($idRecipient <= 0 || $idActivity <= 0) {
        outJson(false, ['error' => 'Identificativi avviso mancanti'], 400);
    }

    if ($base64 === '' && !$checkOnly) {
        outJson(false, ['error' => 'PDF mancante'], 400);
    }

    ensurePagopaPdfColumns();

    $row = sqlGetFirstOrThrowPdf("
        SELECT id, pdf_file
        FROM pagopa_avvisi_studenti
        WHERE id_recipient_isirel = " . dbI($idRecipient) . "
          AND id_isirel_attivita = " . dbI($idActivity) . "
        LIMIT 1
    ");

    if (!$row) {
        outJson(false, ['error' => 'Avviso non trovato in GestOre'], 404);
    }

    $storageDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'viaggi' . DIRECTORY_SEPARATOR . 'pagopa_pdf';

    if (!is_dir($storageDir) && !mkdir($storageDir, 0750, true)) {
        throw new Exception('Impossibile creare la cartella archivio PDF pagoPA');
    }

    $denyFile = $storageDir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($denyFile)) {
        @file_put_contents($denyFile, "Require all denied\nDeny from all\n", LOCK_EX);
    }

    if (!empty($row['pdf_file'])) {
        $existing = $storageDir . DIRECTORY_SEPARATOR . basename((string)$row['pdf_file']);

        if (is_file($existing) && filesize($existing) > 0) {
            outJson(true, ['skipped' => true, 'message' => 'PDF gia archiviato']);
        }
    }

    if ($checkOnly) {
        outJson(true, ['skipped' => false, 'message' => 'PDF non ancora archiviato']);
    }

    $bytes = base64_decode($base64, true);

    if ($bytes === false || strlen($bytes) < 20) {
        outJson(false, ['error' => 'PDF non valido'], 400);
    }

    $contentType = trim((string)($data['contentType'] ?? 'application/pdf'));

    if (stripos($contentType, 'pdf') === false && substr($bytes, 0, 4) !== '%PDF') {
        outJson(false, ['error' => 'Il file scaricato non sembra un PDF'], 400);
    }

    $originalName = sanitizePdfName($data['fileName'] ?? '');
    $hash = substr(hash('sha256', $idActivity . '-' . $idRecipient . '-' . $bytes), 0, 16);
    $storedName = 'pagopa_' . $idActivity . '_' . $idRecipient . '_' . $hash . '.pdf';
    $target = $storageDir . DIRECTORY_SEPARATOR . $storedName;

    if (file_put_contents($target, $bytes, LOCK_EX) === false) {
        throw new Exception('Impossibile salvare il PDF pagoPA');
    }

    sqlExecOrThrowPdf("
        UPDATE pagopa_avvisi_studenti
        SET
            pdf_file = " . dbQ($storedName) . ",
            pdf_original_name = " . dbQ($originalName) . ",
            pdf_content_type = " . dbQ($contentType) . ",
            pdf_size = " . dbI(strlen($bytes)) . ",
            pdf_saved_at = CURRENT_TIMESTAMP,
            pdf_source_url = " . dbQ($data['sourceUrl'] ?? null) . "
        WHERE id = " . dbI($row['id']) . "
    ");

    outJson(true, [
        'saved' => true,
        'file' => $storedName,
        'size' => strlen($bytes)
    ]);

} catch (Throwable $e) {
    outJson(false, ['error' => $e->getMessage()], 500);
}

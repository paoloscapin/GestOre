<?php
require_once '../common/connect.php';
require_once '../common/genitoriColloquiLib.php';

function colloquiAllegatoFail(string $message, int $code): void
{
    http_response_code($code);
    exit($message);
}

function colloquiAllegatoStream(array $row, string $pathKey, string $nameKey, string $storageKey, string $driveKey, string $mimeType = ''): void
{
    $fileName = basename((string)($row[$nameKey] ?? 'allegato'));
    if ($fileName === '') {
        $fileName = 'allegato';
    }
    if ($mimeType === '') {
        $mimeType = trim((string)($row['mime_type'] ?? '')) ?: 'application/octet-stream';
    }

    if (strtoupper((string)($row[$storageKey] ?? 'LOCAL')) === 'DRIVE' && trim((string)($row[$driveKey] ?? '')) !== '') {
        require_once '../api/googleDriveLib.php';
        try {
            $download = googleDriveDownloadFileContent((string)$row[$driveKey]);
            if (!empty($download['mimeType'])) {
                $mimeType = (string)$download['mimeType'];
            }
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: inline; filename="' . addslashes($fileName) . '"');
            echo (string)($download['content'] ?? '');
            exit;
        } catch (Throwable $e) {
            colloquiAllegatoFail('Errore download Google Drive', 500);
        }
    }

    $path = (string)($row[$pathKey] ?? '');
    $absolute = realpath(__DIR__ . '/../' . ltrim($path, '/\\'));
    $base = realpath(__DIR__ . '/../data');
    if (!$absolute || !$base || strpos(str_replace('\\', '/', $absolute), str_replace('\\', '/', $base) . '/') !== 0 || !is_file($absolute)) {
        colloquiAllegatoFail('File locale non trovato', 404);
    }
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($absolute));
    header('Content-Disposition: inline; filename="' . addslashes($fileName) . '"');
    readfile($absolute);
    exit;
}

genitoriColloquiEnsureTables();
$type = trim((string)($_GET['type'] ?? 'allegato'));
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    colloquiAllegatoFail('Allegato non valido', 400);
}

if ($type === 'ricevuta') {
    $row = dbGetFirst("SELECT * FROM genitori_colloqui WHERE id = " . dbI($id) . " LIMIT 1") ?: [];
    if (!$row || trim((string)($row['ricevuta_libri_path'] ?? '')) === '' && trim((string)($row['ricevuta_libri_drive_file_id'] ?? '')) === '') {
        colloquiAllegatoFail('Ricevuta non trovata', 404);
    }
    colloquiAllegatoStream($row, 'ricevuta_libri_path', 'ricevuta_libri_original_name', 'ricevuta_libri_storage_type', 'ricevuta_libri_drive_file_id');
}

if ($type === 'incontro') {
    $row = dbGetFirst("SELECT * FROM genitori_colloqui_incontri_allegati WHERE id = " . dbI($id) . " LIMIT 1") ?: [];
    if (!$row) {
        colloquiAllegatoFail('Allegato incontro non trovato', 404);
    }
    colloquiAllegatoStream($row, 'path_file', 'nome_file', 'storage_type', 'drive_file_id');
}

if ($type === 'evento') {
    $row = dbGetFirst("SELECT * FROM genitori_colloqui_eventi WHERE id = " . dbI($id) . " LIMIT 1") ?: [];
    if (!$row || trim((string)($row['allegato_path'] ?? '')) === '' && trim((string)($row['allegato_drive_file_id'] ?? '')) === '') {
        colloquiAllegatoFail('Allegato evento non trovato', 404);
    }
    colloquiAllegatoStream($row, 'allegato_path', 'allegato_original_name', 'allegato_storage_type', 'allegato_drive_file_id');
}

$row = dbGetFirst("SELECT * FROM genitori_colloqui WHERE id = " . dbI($id) . " LIMIT 1") ?: [];
if (!$row || trim((string)($row['allegato_path'] ?? '')) === '' && trim((string)($row['allegato_drive_file_id'] ?? '')) === '') {
    colloquiAllegatoFail('Allegato non trovato', 404);
}
colloquiAllegatoStream($row, 'allegato_path', 'allegato_original_name', 'allegato_storage_type', 'allegato_drive_file_id');

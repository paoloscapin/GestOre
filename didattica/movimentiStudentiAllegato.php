<?php
require_once '../common/connect.php';
require_once '../common/studentiMovimentiLib.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Allegato non valido');
}

studentiMovimentiEnsureTables();
$attachment = dbGetFirst("SELECT * FROM studenti_movimenti_allegati WHERE id = " . dbI($id) . " LIMIT 1") ?: [];
if (!$attachment) {
    http_response_code(404);
    exit('Allegato non trovato');
}

$fileName = basename((string)($attachment['nome_file'] ?? 'allegato'));
$mimeType = trim((string)($attachment['mime_type'] ?? ''));
if ($mimeType === '') {
    $mimeType = 'application/octet-stream';
}

if (strtoupper((string)($attachment['storage_type'] ?? 'LOCAL')) === 'DRIVE' && trim((string)($attachment['drive_file_id'] ?? '')) !== '') {
    require_once '../api/googleDriveLib.php';
    try {
        $download = googleDriveDownloadFileContent((string)$attachment['drive_file_id']);
        if (!empty($download['mimeType'])) {
            $mimeType = (string)$download['mimeType'];
        }
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . addslashes($fileName) . '"');
        echo (string)($download['content'] ?? '');
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Errore download Google Drive');
    }
}

$path = (string)($attachment['path_file'] ?? '');
$absolute = realpath($path);
if (!$absolute && $path !== '') {
    $absolute = realpath(__DIR__ . '/../' . ltrim($path, '/\\'));
}
$base = realpath(__DIR__ . '/../data');
if (!$absolute || !$base || strpos(str_replace('\\', '/', $absolute), str_replace('\\', '/', $base) . '/') !== 0 || !is_file($absolute)) {
    http_response_code(404);
    exit('File locale non trovato');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($absolute));
header('Content-Disposition: inline; filename="' . addslashes($fileName) . '"');
readfile($absolute);
exit;

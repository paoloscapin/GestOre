<?php

require_once '../common/path.php';
require_once '../common/iscrizioniPrimeLib.php';

$token = trim((string)($_GET['t'] ?? ''));
$tipo = trim((string)($_GET['tipo'] ?? ''));
$document = iscrizioniPrimeDocumentFileByToken($token, $tipo);

if (!$document) {
    http_response_code(404);
    echo 'Documento non disponibile.';
    exit;
}

$downloadName = trim((string)($document['original_name'] ?? ''));
if ($downloadName === '') {
    $downloadName = (string)($document['label'] ?? 'documento') . '.pdf';
}
$name = preg_replace('/[^A-Za-z0-9_. -]+/', '_', $downloadName);
if (!preg_match('/\.pdf$/i', $name)) {
    $name .= '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');

if (!empty($document['absolute_path'])) {
    $file = (string)$document['absolute_path'];
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

if (!empty($document['drive_file_id'])) {
    require_once '../api/googleDriveLib.php';
    try {
        $download = googleDriveDownloadFileContent((string)$document['drive_file_id']);
        header('Content-Length: ' . intval($download['size'] ?? strlen((string)($download['content'] ?? ''))));
        echo (string)($download['content'] ?? '');
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Documento non recuperabile da Google Drive.';
        exit;
    }
}

http_response_code(404);
echo 'Documento non disponibile.';

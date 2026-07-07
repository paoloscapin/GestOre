<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();
$id = intval($_GET['id'] ?? 0);
$eventoId = intval($_GET['evento_id'] ?? 0);

$record = $eventoId > 0
    ? (dbGetFirst("SELECT * FROM iscrizioni_prime_cambio_scuola_eventi WHERE id = " . dbI($eventoId) . " AND pratica_id = " . dbI($id) . " LIMIT 1") ?: [])
    : (iscrizioniPrimeGetCambioScuola($id) ?: []);
if (!$record) {
    http_response_code(404);
    echo 'Allegato non trovato.';
    exit;
}
$filename = trim((string)($record['allegato_original_name'] ?? 'richiesta-cambio-scuola.pdf'));
if ($filename === '') {
    $filename = 'richiesta-cambio-scuola.pdf';
}

if (strtoupper((string)($record['allegato_storage_type'] ?? 'LOCAL')) === 'DRIVE' && trim((string)($record['allegato_drive_file_id'] ?? '')) !== '') {
    require_once '../api/googleDriveLib.php';
    try {
        $download = googleDriveDownloadFileContent((string)$record['allegato_drive_file_id']);
        $mime = trim((string)($download['mimeType'] ?? '')) ?: 'application/pdf';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
        echo (string)($download['content'] ?? '');
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Errore download Google Drive.';
        exit;
    }
}

$path = $id > 0 ? iscrizioniPrimeCambioScuolaAllegatoPath($id, $eventoId) : null;
if (!$path) {
    http_response_code(404);
    echo 'Allegato non trovato.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
readfile($path);

<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

$praticaId = intval($_GET['pratica_id'] ?? 0);
$tipo = trim((string)($_GET['tipo'] ?? ''));
$pratica = $praticaId > 0
    ? (dbGetFirst("SELECT tipo_iscrizione FROM iscrizioni_prime_pratiche WHERE id = " . dbI($praticaId) . " LIMIT 1") ?: [])
    : [];
$types = iscrizioniPrimeSecretaryAllowedDocumentTypes($pratica);

if ($praticaId <= 0 || !isset($types[$tipo])) {
    http_response_code(400);
    echo 'Richiesta non valida.';
    exit;
}

$document = dbGetFirst("
    SELECT *
    FROM iscrizioni_prime_documenti
    WHERE pratica_id = " . dbI($praticaId) . "
      AND tipo_documento = " . dbQ($tipo) . "
      AND stato <> 'mancante'
      AND (file_path IS NOT NULL OR drive_file_id IS NOT NULL)
    LIMIT 1
");

if (!$document) {
    http_response_code(404);
    echo 'Documento non disponibile.';
    exit;
}

$downloadName = trim((string)($document['original_name'] ?? ''));
if ($downloadName === '') {
    $downloadName = (string)$types[$tipo] . '.pdf';
}
$name = preg_replace('/[^A-Za-z0-9_. -]+/', '_', $downloadName);
if (!preg_match('/\.pdf$/i', $name)) {
    $name .= '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');

if (!empty($document['file_path'])) {
    $absolute = realpath(__DIR__ . '/../' . $document['file_path']);
    $base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_prime_uploads');
    if ($absolute && $base && strpos($absolute, $base) === 0 && is_file($absolute)) {
        header('Content-Length: ' . filesize($absolute));
        readfile($absolute);
        exit;
    }
}

$storageType = strtoupper((string)($document['storage_type'] ?? 'LOCAL'));
if ($storageType === 'DRIVE' && trim((string)($document['drive_file_id'] ?? '')) !== '') {
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

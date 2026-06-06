<?php
declare(strict_types=1);

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'segreteria-docenti', 'dirigente', 'genitore', 'studente');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    exit('PDF non valido');
}

$row = dbGetFirst("
    SELECT
        p.id,
        p.pdf_file,
        p.pdf_original_name,
        p.pdf_content_type,
        p.id_studente_gestore,
        p.cognome,
        p.nome,
        a.causal
    FROM pagopa_avvisi_studenti p
    LEFT JOIN pagopa_attivita a ON a.id = p.id_attivita
    WHERE p.id = " . dbI($id) . "
    LIMIT 1
");

if (!$row || empty($row['pdf_file'])) {
    http_response_code(404);
    exit('PDF non archiviato');
}

$role = (string)($__utente_ruolo ?? '');
$canDownload = in_array($role, ['admin', 'segreteria-didattica', 'segreteria-docenti', 'dirigente'], true);

if (!$canDownload && $role === 'studente') {
    $canDownload = intval($row['id_studente_gestore'] ?? 0) > 0
        && intval($row['id_studente_gestore']) === intval($__studente_id ?? 0);
}

if (!$canDownload && $role === 'genitore') {
    $linked = dbGetValue("
        SELECT COUNT(*)
        FROM genitori_studenti
        WHERE id_genitore = " . dbI((int)($__genitore_id ?? 0)) . "
          AND id_studente = " . dbI((int)($row['id_studente_gestore'] ?? 0)) . "
    ");
    $canDownload = intval($linked) > 0;
}

if (!$canDownload) {
    http_response_code(403);
    exit('Accesso non consentito');
}

$storageDir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'pagopa_pdf';
$fileName = basename((string)$row['pdf_file']);
$path = $storageDir . DIRECTORY_SEPARATOR . $fileName;

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('File PDF non trovato');
}

$downloadName = trim((string)($row['pdf_original_name'] ?? ''));

if ($downloadName === '') {
    $student = trim((string)($row['cognome'] ?? '') . '_' . (string)($row['nome'] ?? ''));
    $downloadName = 'avviso_pagopa_' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', $student) . '.pdf';
}

if (stripos($downloadName, '.pdf') === false) {
    $downloadName .= '.pdf';
}

header('Content-Type: ' . (!empty($row['pdf_content_type']) ? $row['pdf_content_type'] : 'application/pdf'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;

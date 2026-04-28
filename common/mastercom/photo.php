<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/../checkSession.php';
require_once __DIR__ . '/../__MasterCom.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

$fileName = trim((string)($_GET['file'] ?? $_POST['file'] ?? ''));
$proxy = intval($_GET['proxy'] ?? $_POST['proxy'] ?? 0) === 1;

if ($fileName === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => 'Parametro file mancante o non valido',
    ]);
    exit;
}

$url = mastercomStudentPhotoUrl($fileName);

if (!$proxy) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => $url !== '',
        'file' => $fileName,
        'url' => $url,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$download = mastercomDownloadStudentPhoto($fileName, [
    'timeout' => 60,
]);

if (!$download['ok']) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => 'Download foto MasterCom fallito',
        'error' => $download['error'] ?? 'DOWNLOAD_FAILED',
        'http_code' => $download['http_code'] ?? 0,
        'file' => $fileName,
        'url' => $url,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$contentType = $download['content_type'] ?: 'image/jpeg';
header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
echo $download['body'];


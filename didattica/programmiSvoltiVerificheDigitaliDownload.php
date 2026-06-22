<?php

require_once '../common/checkSession.php';
require_once '../common/programmiSvoltiVerificheDigitaliLib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

$id = intval($_GET['id'] ?? 0);
$forceDownload = isset($_GET['download']) && $_GET['download'] == '1';
$disposition = $forceDownload ? 'attachment' : 'inline';

if ($id <= 0) {
    http_response_code(400);
    exit('File non indicato');
}

$item = programmiSvoltiVerificheDigitaliLoadItem($id);
if (!$item) {
    http_response_code(404);
    exit('File non trovato');
}

$programma = programmiSvoltiCopertinaLoadProgramma(intval($item['id_programma_svolto']));
if (!$programma || !programmiSvoltiVerificheDigitaliCanManage($programma)) {
    http_response_code(403);
    exit('Non autorizzato');
}

$fileId = trim((string)($item['drive_file_id'] ?? ''));
if ($fileId === '') {
    http_response_code(404);
    exit('ID file Drive mancante');
}

try {
    $download = googleDriveDownloadFileContent($fileId);
} catch (Throwable $e) {
    http_response_code(502);
    exit('Errore download Drive: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

$downloadName = str_replace('"', '', (string)($item['original_name'] ?? 'verifiche_digitali.zip'));
if ($downloadName === '') {
    $downloadName = 'verifiche_digitali.zip';
}

header('Content-Type: ' . ($download['contentType'] ?: 'application/zip'));
header('Content-Length: ' . intval($download['size']));
header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $download['content'];
exit;

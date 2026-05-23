<?php

require_once '../common/checkSession.php';
require_once '../common/programmiSvoltiVerificheDigitaliLib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');
header('Content-Type: application/json; charset=utf-8');

function programmiSvoltiVerificheDigitaliStartJson(bool $ok, array $data = []): void
{
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $programmaId = intval($_POST['programma_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $mime = trim((string)($_POST['mime'] ?? 'application/zip'));
    $size = intval($_POST['size'] ?? 0);

    if ($programmaId <= 0 || $name === '' || $size <= 0) {
        programmiSvoltiVerificheDigitaliStartJson(false, ['message' => 'Parametri mancanti']);
    }
    if (!programmiSvoltiVerificheDigitaliIsZipName($name)) {
        programmiSvoltiVerificheDigitaliStartJson(false, ['message' => 'Sono ammessi solo file ZIP']);
    }

    $programma = programmiSvoltiCopertinaLoadProgramma($programmaId);
    if (!$programma) {
        programmiSvoltiVerificheDigitaliStartJson(false, ['message' => 'Programma svolto non trovato']);
    }
    if (!programmiSvoltiVerificheDigitaliCanManage($programma)) {
        programmiSvoltiVerificheDigitaliStartJson(false, ['message' => 'Non autorizzato']);
    }

    programmiSvoltiVerificheDigitaliEnsureSchema();

    $folderId = programmiSvoltiVerificheDigitaliProgramFolderId($programma);
    $driveName = programmiSvoltiVerificheDigitaliFileName($programma, $name);
    if (googleDriveFindFileByNameInParent($driveName, $folderId) !== '') {
        programmiSvoltiVerificheDigitaliStartJson(false, ['message' => 'Esiste gia un file ZIP con questo nome nella cartella Drive del programma']);
    }

    $session = googleDriveStartResumableUpload($driveName, $mime !== '' ? $mime : 'application/zip', $size, $folderId);

    programmiSvoltiVerificheDigitaliStartJson(true, [
        'uploadUrl' => $session['uploadUrl'],
        'driveName' => $driveName,
        'folderId' => $folderId,
    ]);
} catch (Throwable $e) {
    programmiSvoltiVerificheDigitaliStartJson(false, ['message' => $e->getMessage()]);
}

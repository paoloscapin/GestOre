<?php

require_once '../common/checkSession.php';
require_once '../common/programmiSvoltiVerificheDigitaliLib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');
header('Content-Type: application/json; charset=utf-8');

function programmiSvoltiVerificheDigitaliCompleteJson(bool $ok, array $data = []): void
{
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $programmaId = intval($_POST['programma_id'] ?? 0);
    $fileId = trim((string)($_POST['file_id'] ?? ''));
    $folderId = trim((string)($_POST['folder_id'] ?? ''));
    $originalName = trim((string)($_POST['name'] ?? ''));
    $storedName = trim((string)($_POST['drive_name'] ?? $originalName));
    $mime = trim((string)($_POST['mime'] ?? 'application/zip'));
    $size = intval($_POST['size'] ?? 0);
    $webViewLink = trim((string)($_POST['web_view_link'] ?? ''));

    if ($programmaId <= 0 || $fileId === '' || $originalName === '') {
        programmiSvoltiVerificheDigitaliCompleteJson(false, ['message' => 'Parametri mancanti']);
    }
    if (!programmiSvoltiVerificheDigitaliIsZipName($originalName)) {
        programmiSvoltiVerificheDigitaliCompleteJson(false, ['message' => 'Sono ammessi solo file ZIP']);
    }

    $programma = programmiSvoltiCopertinaLoadProgramma($programmaId);
    if (!$programma) {
        programmiSvoltiVerificheDigitaliCompleteJson(false, ['message' => 'Programma svolto non trovato']);
    }
    if (!programmiSvoltiVerificheDigitaliCanManage($programma)) {
        programmiSvoltiVerificheDigitaliCompleteJson(false, ['message' => 'Non autorizzato']);
    }

    programmiSvoltiVerificheDigitaliEnsureSchema();

    $shareWarning = '';
    $docenteEmail = programmiSvoltiVerificheDigitaliDocenteEmail($programma);
    if ($docenteEmail !== '') {
        try {
            googleDriveShareFileWithUser($fileId, $docenteEmail, 'reader');
        } catch (Throwable $shareError) {
            $shareWarning = 'File caricato, ma condivisione Drive non riuscita per ' . $docenteEmail . ': ' . $shareError->getMessage();
        }
    }

    $now = date('Y-m-d H:i:s');
    dbExec("
        INSERT INTO programmi_svolti_verifiche_digitali
        (
            id_programma_svolto, id_anno_scolastico, id_docente,
            drive_folder_id, drive_file_id, drive_web_view_link,
            original_name, stored_name, mime_type, file_size,
            uploaded_by_user_id, uploaded_at, created_at, updated_at
        )
        VALUES
        (
            " . intval($programmaId) . ",
            " . intval($programma['id_anno_scolastico']) . ",
            " . intval($programma['id_docente']) . ",
            " . dbQ($folderId) . ",
            " . dbQ($fileId) . ",
            " . dbQ($webViewLink) . ",
            " . dbQ($originalName) . ",
            " . dbQ($storedName) . ",
            " . dbQ($mime) . ",
            " . intval($size) . ",
            " . intval($GLOBALS['__utente_id'] ?? 0) . ",
            " . dbQ($now) . ",
            " . dbQ($now) . ",
            " . dbQ($now) . "
        )
    ");

    $response = ['message' => 'Verifica digitale caricata su Drive'];
    if ($shareWarning !== '') {
        $response['warning'] = $shareWarning;
        $response['message'] .= '. ' . $shareWarning;
    }

    programmiSvoltiVerificheDigitaliCompleteJson(true, $response);
} catch (Throwable $e) {
    programmiSvoltiVerificheDigitaliCompleteJson(false, ['message' => $e->getMessage()]);
}

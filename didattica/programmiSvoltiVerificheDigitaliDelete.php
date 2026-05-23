<?php

require_once '../common/checkSession.php';
require_once '../common/programmiSvoltiVerificheDigitaliLib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');
header('Content-Type: application/json; charset=utf-8');

function programmiSvoltiVerificheDigitaliDeleteJson(bool $ok, array $data = []): void
{
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        programmiSvoltiVerificheDigitaliDeleteJson(false, ['message' => 'File non indicato']);
    }

    $item = programmiSvoltiVerificheDigitaliLoadItem($id);
    if (!$item) {
        programmiSvoltiVerificheDigitaliDeleteJson(false, ['message' => 'File non trovato']);
    }

    $programma = programmiSvoltiCopertinaLoadProgramma(intval($item['id_programma_svolto']));
    if (!$programma || !programmiSvoltiVerificheDigitaliCanManage($programma)) {
        programmiSvoltiVerificheDigitaliDeleteJson(false, ['message' => 'Non autorizzato']);
    }

    googleDriveDeleteFile((string)($item['drive_file_id'] ?? ''));

    $now = date('Y-m-d H:i:s');
    dbExec("
        UPDATE programmi_svolti_verifiche_digitali
        SET deleted_at=" . dbQ($now) . ",
            deleted_by_user_id=" . intval($GLOBALS['__utente_id'] ?? 0) . ",
            updated_at=" . dbQ($now) . "
        WHERE id=" . intval($id) . "
    ");

    programmiSvoltiVerificheDigitaliDeleteJson(true, ['message' => 'File eliminato']);
} catch (Throwable $e) {
    programmiSvoltiVerificheDigitaliDeleteJson(false, ['message' => $e->getMessage()]);
}

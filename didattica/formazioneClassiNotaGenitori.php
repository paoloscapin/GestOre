<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

header('Content-Type: application/json; charset=UTF-8');

try {
    $rowId = intval($_POST['row_id'] ?? 0);
    if ($rowId <= 0) {
        throw new RuntimeException('Studente non valido.');
    }

    $note = (string)($_POST['note'] ?? '');
    echo json_encode(formazioneClassiSaveParentNote($rowId, $note), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

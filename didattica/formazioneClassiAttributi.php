<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'formazione-classi');

header('Content-Type: application/json; charset=UTF-8');

try {
    $rowId = intval($_POST['row_id'] ?? 0);
    if ($rowId <= 0) {
        throw new RuntimeException('Studente non valido.');
    }

    $attrs = [
        STUD_ATTR_R7A2 => intval($_POST['dsa'] ?? 0) === 1,
        STUD_ATTR_Q4M9 => intval($_POST['legge_104'] ?? 0) === 1,
        STUD_ATTR_Z8C3 => intval($_POST['fascia_c'] ?? 0) === 1,
    ];

    echo json_encode(formazioneClassiSaveStudentAttrs($rowId, $attrs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

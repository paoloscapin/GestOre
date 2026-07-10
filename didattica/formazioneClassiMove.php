<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'formazione-classi');

header('Content-Type: application/json; charset=UTF-8');

try {
    $sessionId = intval($_POST['session_id'] ?? 0);
    $rowId = intval($_POST['row_id'] ?? 0);
    $targetLabel = trim((string)($_POST['target_label'] ?? ''));

    if ($sessionId <= 0 || $rowId <= 0) {
        throw new RuntimeException('Dati spostamento non validi.');
    }

    echo json_encode(formazioneClassiMoveStudent($sessionId, $rowId, $targetLabel), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

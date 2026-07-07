<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

header('Content-Type: application/json; charset=UTF-8');

try {
    $sessionId = intval($_POST['session_id'] ?? 0);
    $rowIds = $_POST['row_ids'] ?? [];
    $targetLabels = $_POST['target_labels'] ?? [];
    $weights = $_POST['weights'] ?? [];

    if (!is_array($rowIds)) {
        $rowIds = [$rowIds];
    }
    if (!is_array($targetLabels)) {
        $targetLabels = [$targetLabels];
    }
    if (!is_array($weights)) {
        $weights = [];
    }

    echo json_encode(
        formazioneClassiAutoAssign($sessionId, $rowIds, $targetLabels, $weights),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

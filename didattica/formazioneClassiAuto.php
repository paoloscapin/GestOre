<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

header('Content-Type: application/json; charset=UTF-8');

try {
    $sessionId = intval($_POST['session_id'] ?? 0);
    $rowIds = $_POST['row_ids'] ?? [];
    $targetLabels = $_POST['target_labels'] ?? [];
    $targetCounts = $_POST['target_counts'] ?? [];
    $weights = $_POST['weights'] ?? [];
    $tabletFilter = formazioneClassiNormalizeTabletFilter((string)($_POST['tablet_filter'] ?? 'all'));

    if (!is_array($rowIds)) {
        $rowIds = [$rowIds];
    }
    if (!is_array($targetLabels)) {
        $targetLabels = [$targetLabels];
    }
    if (!is_array($weights)) {
        $weights = [];
    }
    if (!is_array($targetCounts)) {
        $targetCounts = [];
    }

    echo json_encode(
        formazioneClassiAutoAssign($sessionId, $rowIds, $targetLabels, $weights, $tabletFilter, $targetCounts),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

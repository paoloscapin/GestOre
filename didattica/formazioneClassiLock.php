<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

header('Content-Type: application/json; charset=UTF-8');

try {
    $sessionId = intval($_POST['session_id'] ?? 0);
    $scope = trim((string)($_POST['scope'] ?? ''));
    $locked = intval($_POST['locked'] ?? 0) === 1;

    if ($scope === 'student') {
        $rowId = intval($_POST['row_id'] ?? 0);
        echo json_encode(formazioneClassiSetStudentLock($sessionId, $rowId, $locked), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($scope === 'class') {
        $classLabel = trim((string)($_POST['class_label'] ?? ''));
        echo json_encode(formazioneClassiSetClassLock($sessionId, $classLabel, $locked), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Tipo blocco non valido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

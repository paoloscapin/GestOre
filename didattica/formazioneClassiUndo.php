<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'formazione-classi');

header('Content-Type: application/json; charset=UTF-8');

try {
    $sessionId = intval($_POST['session_id'] ?? 0);
    if ($sessionId <= 0) {
        throw new RuntimeException('Sessione formazione non valida.');
    }

    $action = trim((string)($_POST['action'] ?? 'undo_last'));
    if ($action === 'list') {
        echo json_encode([
            'ok' => true,
            'items' => formazioneClassiUndoList($sessionId, 50),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($action === 'undo_to') {
        $undoId = intval($_POST['undo_id'] ?? 0);
        echo json_encode(formazioneClassiUndoTo($sessionId, $undoId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode(formazioneClassiUndoLast($sessionId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

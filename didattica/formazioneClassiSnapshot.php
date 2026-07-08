<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

header('Content-Type: application/json; charset=UTF-8');

try {
    formazioneClassiEnsureTables();
    $action = trim((string)($_POST['action'] ?? ''));
    $sessionId = intval($_POST['session_id'] ?? 0);
    if ($action === 'save') {
        $name = trim((string)($_POST['name'] ?? ''));
        echo json_encode(
            formazioneClassiSaveSnapshot($sessionId, $name, (string)($__utente_nome ?? $__utente_username ?? '')),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
    if ($action === 'apply') {
        echo json_encode(
            formazioneClassiApplySnapshot($sessionId, intval($_POST['snapshot_id'] ?? 0)),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
    echo json_encode(['ok' => false, 'message' => 'Azione non valida.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

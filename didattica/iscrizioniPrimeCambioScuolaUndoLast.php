<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

try {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Pratica non valida.');
    }

    echo json_encode(iscrizioniPrimeUndoLastCambioScuola($id), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

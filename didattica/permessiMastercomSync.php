<?php

require_once '../common/checkSession.php';
require_once '../common/permessi_uscita_lib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente', 'personale-ata');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
$date = trim((string)($_POST['data'] ?? ''));

try {
    if ($id > 0) {
        $result = permessiUscitaSyncOne($id);
    } else {
        $result = permessiUscitaSyncPending($date);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error('permessiMastercomSync.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

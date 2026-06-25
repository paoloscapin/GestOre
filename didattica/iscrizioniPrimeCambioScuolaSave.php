<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Pratica non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

global $__useremail, $__utente_nome, $__utente_cognome;
$updatedBy = trim((string)($__utente_nome ?? '') . ' ' . (string)($__utente_cognome ?? ''));
if ($updatedBy === '') {
    $updatedBy = (string)($__useremail ?? '');
}

try {
    $file = $_FILES['allegato'] ?? null;
    $result = iscrizioniPrimeSaveCambioScuola($id, $_POST, is_array($file) ? $file : null, $updatedBy);
    if (empty($result['ok'])) {
        http_response_code(400);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

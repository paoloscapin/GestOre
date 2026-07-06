<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$praticaId = intval($_POST['pratica_id'] ?? 0);
$tipo = trim((string)($_POST['tipo'] ?? ''));

if ($praticaId <= 0 || $tipo === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Richiesta non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    iscrizioniPrimeEnsureSchema();
    if (function_exists('iscrizioniPrimeDeleteSecretaryDocument')) {
        $result = iscrizioniPrimeDeleteSecretaryDocument($praticaId, $tipo);
    } elseif (function_exists('iscrizioniPrimeDeleteSecreteraryDocument')) {
        $result = iscrizioniPrimeDeleteSecreteraryDocument($praticaId, $tipo);
    } else {
        throw new RuntimeException('Funzione cancellazione documento non disponibile.');
    }
    if (empty($result['ok'])) {
        http_response_code(400);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

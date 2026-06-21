<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
$stato = trim((string)($_POST['stato'] ?? ''));
$allowed = ['inviata', 'verificata', 'da_integrare'];

if ($id <= 0 || !in_array($stato, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Richiesta non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = " . dbQ($stato) . ",
            updated_at = NOW()
        WHERE id = " . dbI($id) . "
          AND stato IN ('inviata', 'verificata', 'da_integrare')
        LIMIT 1
    ");

    echo json_encode(['ok' => true, 'message' => 'Stato pratica aggiornato.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

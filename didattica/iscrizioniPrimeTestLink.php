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

$pratica = dbGetFirst("
    SELECT id, tipo_iscrizione
    FROM iscrizioni_prime_pratiche
    WHERE id = $id
    LIMIT 1
");

if (!$pratica) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Pratica non trovata.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$link = '../iscrizioni/conferma.php?preview_id=' . rawurlencode((string)$id);

echo json_encode([
    'ok' => true,
    'link' => $link,
    'preview' => true,
], JSON_UNESCAPED_UNICODE);

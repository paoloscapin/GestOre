<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

$id = intval($_POST['id'] ?? 0);
$action = trim((string)($_POST['action'] ?? 'save'));
if ($action === 'ocr_status') {
    echo json_encode([
        'ok' => true,
        'message' => iscrizioniPrimeOcrStatusMessage(),
        'ocr_status' => iscrizioniPrimeOcrStatus(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Pratica non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pratica = dbGetFirst("
        SELECT id, tipo_iscrizione, cognome, nome
        FROM iscrizioni_prime_pratiche
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");
    if (!$pratica) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pratica non trovata.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'recognize') {
        $result = iscrizioniPrimeRecognizePagellaValues($id);
        if (!$result['ok']) {
            http_response_code(422);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $italiano = iscrizioniPrimeNormalizeGradeValue($_POST['voto_italiano'] ?? '');
    $matematica = iscrizioniPrimeNormalizeGradeValue($_POST['voto_matematica'] ?? '');
    $consiglio = trim((string)($_POST['consiglio_orientativo'] ?? ''));
    $fonte = trim((string)($_POST['fonte'] ?? 'manuale'));
    if (!in_array($fonte, ['manuale', 'pagella'], true)) {
        $fonte = 'manuale';
    }
    $documentoId = intval($_POST['documento_id'] ?? 0);

    $result = iscrizioniPrimeSavePagellaValues($id, $italiano, $matematica, $consiglio, $fonte, $documentoId > 0 ? $documentoId : null);
    echo json_encode([
        'ok' => true,
        'message' => $result['changes'] > 0 ? 'Valori pagella salvati.' : 'Nessuna modifica da salvare.',
        'values' => $result['values'],
        'changes' => $result['changes'],
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

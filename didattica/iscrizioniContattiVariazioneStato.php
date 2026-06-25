<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

try {
    iscrizioniPrimeEnsureSchema();

    $id = intval($_POST['id'] ?? 0);
    $stato = trim((string)($_POST['stato'] ?? ''));
    if ($id <= 0) {
        throw new RuntimeException('Variazione non valida.');
    }
    if (!in_array($stato, ['da_lavorare', 'lavorata'], true)) {
        throw new RuntimeException('Stato non valido.');
    }

    $processedBy = trim((string)($GLOBALS['__useremail'] ?? $GLOBALS['__username'] ?? ''));
    if ($stato === 'lavorata') {
        dbExec("
            UPDATE iscrizioni_contatti_variazioni SET
                stato = 'lavorata',
                processed_at = NOW(),
                processed_by = " . dbQ($processedBy) . "
            WHERE id = " . dbI($id) . "
            LIMIT 1
        ");
    } else {
        dbExec("
            UPDATE iscrizioni_contatti_variazioni SET
                stato = 'da_lavorare',
                processed_at = NULL,
                processed_by = NULL
            WHERE id = " . dbI($id) . "
            LIMIT 1
        ");
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

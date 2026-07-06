<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

try {
    iscrizioniPrimeEnsureSchema();

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Pratica non valida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $note = trim((string)($_POST['note_genitori_iscrizione'] ?? ''));
    $noteValue = $note !== '' ? $note : null;

    $before = dbGetFirst("
        SELECT id, tipo_iscrizione, note_genitori_iscrizione
        FROM iscrizioni_prime_pratiche
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");
    if (!$before) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pratica non trovata.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET note_genitori_iscrizione = " . dbQ($noteValue) . ",
            updated_at = NOW()
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");

    if ((string)($before['note_genitori_iscrizione'] ?? '') !== (string)($noteValue ?? '')) {
        iscrizioniPrimeRecordEvent($id, 'note_formazione_classi', 'Note genitori per formazione classi aggiornate', [
            'tipo_iscrizione' => $before['tipo_iscrizione'] ?? 'prime',
            'dettagli' => [
                'prima' => $before['note_genitori_iscrizione'] ?? null,
                'dopo' => $noteValue,
            ],
        ]);
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Note genitori aggiornate.',
        'note_genitori_iscrizione' => $noteValue ?? '',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

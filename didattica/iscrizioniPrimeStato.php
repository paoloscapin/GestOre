<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
$stato = trim((string)($_POST['stato'] ?? ''));
$note = trim((string)($_POST['note'] ?? ''));
$allowed = ['bozza', 'inviata', 'verifica_iniziale_ok', 'verificata', 'da_integrare', 'annullata'];

if ($id <= 0 || !in_array($stato, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Richiesta non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($stato === 'da_integrare' && strlen($note) < 8) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Scrivi una nota per spiegare al genitore cosa deve correggere o integrare.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    iscrizioniPrimeEnsureSchema();
    $pratica = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE id = " . dbI($id) . "
          AND stato IN ('inviata', 'verifica_iniziale_ok', 'verificata', 'da_integrare', 'annullata')
        LIMIT 1
    ");

    if (!$pratica) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pratica non trovata o non modificabile.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $statoPrecedente = (string)($pratica['stato'] ?? '');

    $clearsSecretaryNews = in_array($stato, ['verifica_iniziale_ok', 'verificata'], true);

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = " . dbQ($stato) . ",
            novita_segreteria_at = " . ($clearsSecretaryNews ? 'NULL' : 'novita_segreteria_at') . ",
            novita_segreteria_messaggio = " . ($clearsSecretaryNews ? 'NULL' : 'novita_segreteria_messaggio') . ",
            updated_at = NOW()
        WHERE id = " . dbI($id) . "
          AND stato IN ('inviata', 'verifica_iniziale_ok', 'verificata', 'da_integrare', 'annullata')
        LIMIT 1
    ");

    try {
        $praticaSync = $pratica;
        $praticaSync['stato'] = $stato;
        iscrizioniPrimeSyncGestoreStudentFromPractice($praticaSync);
    } catch (Throwable $syncError) {
        warning('[iscrizioni] errore aggiornamento studente GestOre pratica id=' . $id . ': ' . $syncError->getMessage());
    }

    if ($stato === 'da_integrare') {
        $pratica['stato'] = 'da_integrare';
        $mail = iscrizioniPrimeSendIntegrationRequest($pratica, $note);
        iscrizioniPrimeRecordEvent($id, 'richiesta_integrazione', !empty($mail['ok']) ? 'Richiesta integrazione inviata alla famiglia' : 'Richiesta integrazione registrata, mail non inviata', [
            'stato_precedente' => $statoPrecedente,
            'stato_nuovo' => 'da_integrare',
            'messaggio' => $note,
            'dettagli' => $mail,
        ]);
        if (!empty($mail['ok'])) {
            echo json_encode(['ok' => true, 'message' => 'Pratica riaperta e mail di richiesta integrazione inviata ai genitori.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'warning' => true,
            'message' => 'Pratica riaperta, ma non e\' stato possibile inviare la mail: ' . ($mail['message'] ?? 'errore non specificato'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    iscrizioniPrimeRecordEvent($id, 'cambio_stato', 'Stato pratica aggiornato', [
        'stato_precedente' => $statoPrecedente,
        'stato_nuovo' => $stato,
        'messaggio' => $note !== '' ? $note : null,
    ]);

    echo json_encode(['ok' => true, 'message' => 'Stato pratica aggiornato.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

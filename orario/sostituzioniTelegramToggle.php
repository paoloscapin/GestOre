<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('docente');
header('Content-Type: application/json; charset=utf-8');

global $__username;

try {
    $username = trim((string)$__username);
    if ($username === '') {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Utente non autenticato'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : null;
    if (!in_array($enabled, [0, 1], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Parametro enabled non valido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $qDoc = "
        SELECT id, cognome, nome
        FROM docente
        WHERE username = " . dbQ($username) . "
        LIMIT 1
    ";
    $doc = dbGetFirst($qDoc);

    if (!$doc) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Docente non trovato'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idDocente = (int)$doc['id'];

    $qTg = "
        SELECT idDocente, telegram_chat_id
        FROM docente_telegram
        WHERE idDocente = " . dbI($idDocente) . "
        LIMIT 1
    ";
    $tg = dbGetFirst($qTg);

    if (!$tg) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Il tuo profilo Telegram non è ancora configurato in GestOre. Prima devi associare il tuo account Telegram al sistema.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (trim((string)($tg['telegram_chat_id'] ?? '')) === '') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Il tuo account Telegram non è ancora associato al tuo profilo docente in GestOre.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $qUpd = "
        UPDATE docente_telegram
        SET attivo = 1,
            consenso_notifiche = " . dbI($enabled) . "
        WHERE idDocente = " . dbI($idDocente) . "
    ";
    dbExec($qUpd);

    echo json_encode([
        'ok' => true,
        'enabled' => ((int)$enabled === 1),
        'message' => ((int)$enabled === 1)
            ? 'Notifiche Telegram abilitate'
            : 'Notifiche Telegram disabilitate'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
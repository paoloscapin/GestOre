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
        SELECT attivo, consenso_notifiche, telegram_chat_id
        FROM docente_telegram
        WHERE idDocente = " . dbI($idDocente) . "
        LIMIT 1
    ";
    $tg = dbGetFirst($qTg);

    $enabled = false;
    $hasTelegramProfile = false;

    if ($tg) {
        $hasTelegramProfile = !empty($tg['telegram_chat_id']);
        $enabled = ((int)($tg['attivo'] ?? 0) === 1) && ((int)($tg['consenso_notifiche'] ?? 0) === 1);
    }

    echo json_encode([
        'ok' => true,
        'idDocente' => $idDocente,
        'docente' => trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? '')),
        'enabled' => $enabled,
        'hasTelegramProfile' => $hasTelegramProfile
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$signature = trim((string)($_POST['signature'] ?? ''));

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Pratica non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($subject === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Inserire oggetto e testo della comunicazione.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pratica = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");

    if (!$pratica) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pratica non trovata.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = iscrizioniPrimeSendCustomPracticeMail($pratica, $subject, $message, $signature);
    if (empty($result['ok'])) {
        http_response_code(500);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

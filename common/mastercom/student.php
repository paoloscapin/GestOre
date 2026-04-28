<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/../checkSession.php';
require_once __DIR__ . '/../__MasterCom.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$studentId = intval($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
if ($studentId <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Parametro student_id mancante o non valido',
    ]);
    exit;
}

$authResult = mastercomAuthenticateService([
    'method' => 'POST',
    'timeout' => 60,
]);

if (!$authResult['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Autenticazione MasterCom fallita',
        'error' => $authResult['error'] ?? 'AUTH_FAILED',
        'http_code' => $authResult['http_code'] ?? 0,
    ]);
    exit;
}

$studentResult = mastercomLoadStudentDetails($authResult, $studentId, [
    'method' => 'GET',
    'timeout' => 120,
]);

if (!$studentResult['ok'] || !is_array($studentResult['response'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento studente MasterCom fallito',
        'error' => $studentResult['error'] ?? 'LOAD_FAILED',
        'http_code' => $studentResult['http_code'] ?? 0,
        'raw' => $studentResult['raw'] ?? null,
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'student_id' => $studentId,
    'count' => count($studentResult['response']),
    'records' => $studentResult['response'],
], JSON_UNESCAPED_UNICODE);


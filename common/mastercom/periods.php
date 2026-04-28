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

$classId = intval($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
if ($classId <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Parametro class_id mancante o non valido',
    ]);
    exit;
}

$authResult = mastercomAuthenticateService([
    'profile' => 'MasterComDocenteAuth',
    'method' => 'POST',
    'timeout' => 60,
]);

if (!$authResult['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Autenticazione MasterCom docente fallita',
        'error' => $authResult['error'] ?? 'AUTH_FAILED',
        'http_code' => $authResult['http_code'] ?? 0,
    ]);
    exit;
}

$periodsResult = mastercomLoadPeriodsData($authResult, $classId, [
    'method' => 'POST',
    'timeout' => 120,
]);

if (!$periodsResult['ok'] || !is_array($periodsResult['response'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento periodi MasterCom fallito',
        'error' => $periodsResult['error'] ?? 'LOAD_FAILED',
        'http_code' => $periodsResult['http_code'] ?? 0,
        'raw' => $periodsResult['raw'] ?? null,
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'class_id' => $classId,
    'result' => $periodsResult['response']['result'] ?? null,
    'error_code' => $periodsResult['response']['error_code'] ?? null,
], JSON_UNESCAPED_UNICODE);


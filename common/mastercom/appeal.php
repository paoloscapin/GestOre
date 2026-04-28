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

$appealResult = mastercomLoadAppealData($authResult, $classId, [
    'method' => 'POST',
    'timeout' => 120,
]);

if (!$appealResult['ok'] || !is_array($appealResult['response'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento appello MasterCom fallito',
        'error' => $appealResult['error'] ?? 'LOAD_FAILED',
        'http_code' => $appealResult['http_code'] ?? 0,
        'raw' => $appealResult['raw'] ?? null,
    ]);
    exit;
}

$records = $appealResult['response']['result'] ?? [];
$studentIds = is_array($records) ? array_map('intval', array_keys($records)) : [];

echo json_encode([
    'ok' => true,
    'class_id' => $classId,
    'count' => is_array($records) ? count($records) : 0,
    'ids' => $studentIds,
    'records' => $records,
    'debug' => $appealResult['response']['debug_code'] ?? null,
    'error_code' => $appealResult['response']['error_code'] ?? null,
], JSON_UNESCAPED_UNICODE);


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
$startTs = intval($_GET['start_ts'] ?? $_POST['start_ts'] ?? 0);
$endTs = intval($_GET['end_ts'] ?? $_POST['end_ts'] ?? 0);

if ($classId <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Parametro class_id mancante o non valido',
    ]);
    exit;
}

if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
    echo json_encode([
        'ok' => false,
        'message' => 'Parametri start_ts / end_ts mancanti o non validi',
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

$calendarResult = mastercomLoadCalendarNotes($authResult, $classId, $startTs, $endTs, [
    'method' => 'POST',
    'timeout' => 120,
]);

if (!$calendarResult['ok'] || !is_array($calendarResult['response'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento note agenda MasterCom fallito',
        'error' => $calendarResult['error'] ?? 'LOAD_FAILED',
        'http_code' => $calendarResult['http_code'] ?? 0,
        'raw' => $calendarResult['raw'] ?? null,
    ]);
    exit;
}

$records = $calendarResult['response']['result'] ?? [];

echo json_encode([
    'ok' => true,
    'class_id' => $classId,
    'start_ts' => $startTs,
    'end_ts' => $endTs,
    'count' => is_array($records) ? count($records) : 0,
    'records' => $records,
    'debug' => $calendarResult['response']['debug_code'] ?? null,
    'error_code' => $calendarResult['response']['error_code'] ?? null,
], JSON_UNESCAPED_UNICODE);


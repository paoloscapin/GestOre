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

$startTs = intval($_GET['start_ts'] ?? $_POST['start_ts'] ?? 0);
$endTs = intval($_GET['end_ts'] ?? $_POST['end_ts'] ?? 0);

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

$festivitiesResult = mastercomLoadFestivitiesData($authResult, $startTs, $endTs, [
    'method' => 'POST',
    'timeout' => 120,
]);

if (!$festivitiesResult['ok'] || !is_array($festivitiesResult['response'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento festivita MasterCom fallito',
        'error' => $festivitiesResult['error'] ?? 'LOAD_FAILED',
        'http_code' => $festivitiesResult['http_code'] ?? 0,
        'raw' => $festivitiesResult['raw'] ?? null,
    ]);
    exit;
}

$records = $festivitiesResult['response']['result'] ?? [];

echo json_encode([
    'ok' => true,
    'start_ts' => $startTs,
    'end_ts' => $endTs,
    'count' => is_array($records) ? count($records) : 0,
    'records' => $records,
    'error_code' => $festivitiesResult['response']['error_code'] ?? null,
], JSON_UNESCAPED_UNICODE);


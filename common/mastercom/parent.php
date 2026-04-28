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

$parentId = intval($_GET['parent_id'] ?? $_POST['parent_id'] ?? 0);
if ($parentId <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Parametro parent_id mancante o non valido',
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

$parentResult = mastercomLoadParentDetails($authResult, $parentId, [
    'method' => 'GET',
    'timeout' => 120,
]);

if (!$parentResult['ok'] || !is_array($parentResult['response'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento genitore MasterCom fallito',
        'error' => $parentResult['error'] ?? 'LOAD_FAILED',
        'http_code' => $parentResult['http_code'] ?? 0,
        'raw' => $parentResult['raw'] ?? null,
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'parent_id' => $parentId,
    'count' => count($parentResult['response']),
    'records' => $parentResult['response'],
], JSON_UNESCAPED_UNICODE);


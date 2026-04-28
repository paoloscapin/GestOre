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

$usersResult = mastercomLoadUsersList($authResult, [
    'method' => 'POST',
    'timeout' => 120,
]);

if (!$usersResult['ok'] || !is_array($usersResult['response'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento utenti MasterCom fallito',
        'error' => $usersResult['error'] ?? 'LOAD_FAILED',
        'http_code' => $usersResult['http_code'] ?? 0,
        'raw' => $usersResult['raw'] ?? null,
    ]);
    exit;
}

$teachers = mastercomExtractTeacherUsers($usersResult);
$teacherIds = [];
foreach ($teachers as $teacher) {
    if (isset($teacher['id_user'])) {
        $teacherIds[] = intval($teacher['id_user']);
    }
}

echo json_encode([
    'ok' => true,
    'count' => count($teachers),
    'ids' => $teacherIds,
    'records' => $teachers,
    'error_code' => $usersResult['response']['error_code'] ?? null,
], JSON_UNESCAPED_UNICODE);


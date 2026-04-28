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

$studentsResult = mastercomLoadStudentsList($authResult, $classId, [
    'method' => 'POST',
    'timeout' => 120,
]);

if (!$studentsResult['ok'] || !is_array($studentsResult['response'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento studenti MasterCom fallito',
        'error' => $studentsResult['error'] ?? 'LOAD_FAILED',
        'http_code' => $studentsResult['http_code'] ?? 0,
        'raw' => $studentsResult['raw'] ?? null,
    ]);
    exit;
}

$records = $studentsResult['response']['result'] ?? [];
$studentIds = [];
foreach ($records as $record) {
    if (is_array($record) && isset($record['id_studente'])) {
        $studentIds[] = intval($record['id_studente']);
    }
}

echo json_encode([
    'ok' => true,
    'class_id' => $classId,
    'count' => is_array($records) ? count($records) : 0,
    'ids' => $studentIds,
    'records' => $records,
    'error_code' => $studentsResult['response']['error_code'] ?? null,
], JSON_UNESCAPED_UNICODE);


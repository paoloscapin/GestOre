<?php

require_once __DIR__ . '/../checkSession.php';
require_once __DIR__ . '/admin_lib.php';

ruoloRichiesto('admin');

header('Content-Type: application/json; charset=utf-8');

$classId = intval($_GET['class_id'] ?? 0);
if ($classId <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'class_id non valido',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = mastercomAdminBuildStudentSupplementalMapForClass($classId);

echo json_encode([
    'ok' => $result['ok'] ?? false,
    'message' => $result['message'] ?? '',
    'class_id' => $classId,
    'rows_count' => intval($result['rows_count'] ?? 0),
    'map_count' => is_array($result['map'] ?? null) ? count($result['map']) : 0,
    'elapsed_seconds' => $result['elapsed_seconds'] ?? 0,
    'http_code' => intval($result['http_code'] ?? 0),
    'content_type' => (string)($result['content_type'] ?? ''),
    'preview' => (string)($result['preview'] ?? ''),
    'sample' => array_slice(array_values(is_array($result['map'] ?? null) ? $result['map'] : []), 0, 3),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);


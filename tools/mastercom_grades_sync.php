<?php

require_once __DIR__ . '/../common/mastercom/grades_cache_lib.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Script eseguibile solo da CLI/cron.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$scope = trim((string)($argv[1] ?? ''));
$argOffset = 1;
if ($scope !== '' && !preg_match('/^\d+$/', $scope)) {
    $range = mastercomGradesCacheResolveRange($scope);
    $argOffset = 2;
} else {
    $range = mastercomGradesCacheSchoolYearRange();
}

$options = [
    'class_id' => intval($argv[$argOffset] ?? 0),
    'subject_id' => intval($argv[$argOffset + 1] ?? 0),
    'start_date' => trim((string)($argv[$argOffset + 2] ?? $range['start'])),
    'end_date' => trim((string)($argv[$argOffset + 3] ?? min($range['end'], mastercomGradesCacheRomeToday('Y-m-d')))),
];

$progress = function (string $stage, int $current, int $total, string $message): void {
    infocron('mastercom_grades_sync ' . $stage . ' ' . $current . '/' . $total . ' ' . $message);
};

header('Content-Type: application/json; charset=utf-8');

try {
    $result = mastercomGradesCacheSync($options, $progress);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    errorcron('tools/mastercom_grades_sync.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

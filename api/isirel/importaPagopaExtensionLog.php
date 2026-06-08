<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 600');
header('Vary: Access-Control-Request-Headers');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function outJson($ok, $extra = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outJson(false, ['error' => 'Metodo non consentito'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);

if (!is_array($data)) {
    outJson(false, ['error' => 'JSON non valido'], 400);
}

$message = trim((string)($data['message'] ?? ''));

if ($message === '') {
    outJson(true, ['skipped' => true]);
}

$logDir = realpath(__DIR__ . '/../../log');

if ($logDir === false) {
    $logDir = __DIR__ . '/../../log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
}

$runId = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', (string)($data['runId'] ?? ''));
$runId = substr((string)$runId, 0, 80);
$source = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', (string)($data['source'] ?? 'EXTENSION'));
$source = substr((string)$source, 0, 40);
$at = trim((string)($data['at'] ?? ''));
$clientTime = $at !== '' ? ' client_at=' . $at : '';

$line = date('Y-m-d H:i:s')
    . ' [' . ($source !== '' ? $source : 'EXTENSION') . ']'
    . ($runId !== '' ? ' run=' . $runId : '')
    . $clientTime
    . ' ' . str_replace(["\r", "\n"], [' ', ' '], $message)
    . PHP_EOL;

$written = @file_put_contents($logDir . '/isirel_pagopa_extension.log', $line, FILE_APPEND | LOCK_EX);

if ($written === false) {
    outJson(false, ['error' => 'Impossibile scrivere il log'], 500);
}

outJson(true);

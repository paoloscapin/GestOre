<?php

require_once '../common/checkSession.php';
require_once '../api/googleDriveLib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

$uploadUrl = $_SERVER['HTTP_X_DRIVE_UPLOAD_URL'] ?? '';
$contentRange = $_SERVER['HTTP_CONTENT_RANGE'] ?? '';
$contentType = $_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream';

if ($uploadUrl === '' || $contentRange === '') {
    http_response_code(400);
    echo 'Parametri upload mancanti';
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || strlen($body) === 0) {
    http_response_code(400);
    echo 'Chunk vuoto';
    exit;
}

$accessToken = googleDriveAccessToken();
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $uploadUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: ' . $contentType,
        'Content-Length: ' . strlen($body),
        'Content-Range: ' . $contentRange,
    ],
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_TIMEOUT => 600,
]);

$response = curl_exec($ch);
$httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo 'Errore CURL Drive: ' . $error;
    exit;
}

http_response_code($httpCode);
header('Content-Type: application/json; charset=utf-8');
echo $response;

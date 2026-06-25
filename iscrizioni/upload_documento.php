<?php

require_once '../common/path.php';
require_once '../common/iscrizioniPrimeLib.php';

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo non consentito.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = trim((string)($_POST['token'] ?? ($_GET['t'] ?? '')));
$tipo = trim((string)($_POST['tipo_documento'] ?? ''));
$uploadMode = trim((string)($_POST['upload_mode'] ?? 'replace'));

try {
    if (preg_match('/^admin_preview:\d+$/', $token)) {
        require_once '../common/checkSession.php';
        ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');
    }
    $result = iscrizioniPrimeUploadDocumentByToken($token, $tipo, $_FILES['documento'] ?? [], $uploadMode);
    if (!$result['ok']) {
        http_response_code(400);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

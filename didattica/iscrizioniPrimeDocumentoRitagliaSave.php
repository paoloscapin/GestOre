<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$praticaId = intval($_POST['pratica_id'] ?? 0);
$tipo = trim((string)($_POST['tipo'] ?? ''));
$page = max(1, intval($_POST['page'] ?? 1));
$dpi = min(450, max(180, intval($_POST['dpi'] ?? 300)));
$crop = [
    'x' => $_POST['x'] ?? 0,
    'y' => $_POST['y'] ?? 0,
    'width' => $_POST['width'] ?? 0,
    'height' => $_POST['height'] ?? 0,
];

if ($praticaId <= 0 || $tipo === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Richiesta non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    iscrizioniPrimeEnsureSchema();
    $result = iscrizioniPrimeSaveSecretaryCroppedDocument($praticaId, $tipo, $page, $crop, $dpi);
    if (empty($result['ok'])) {
        http_response_code(400);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

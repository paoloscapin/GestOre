<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_POST['tipo_iscrizione'] ?? 'prime');
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$signature = trim((string)($_POST['signature'] ?? ''));
$dryRun = !empty($_POST['dry_run']);
$audience = iscrizioniPrimeCustomMailAudience($_POST['audience'] ?? 'esterni');

if ($subject === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Inserire oggetto e testo della comunicazione.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = iscrizioniPrimeSendCustomBulkMail($tipoIscrizione, $subject, $message, $signature, $dryRun, $audience);
    if (empty($result['ok'])) {
        http_response_code(500);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

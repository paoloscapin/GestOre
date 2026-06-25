<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

$dryRun = intval($_POST['dry_run'] ?? 0) === 1;
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_POST['tipo_iscrizione'] ?? 'prime');

try {
    $result = iscrizioniPrimeResendCorrectLinkFromSentMailBatch($dryRun, $tipoIscrizione);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

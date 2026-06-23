<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

try {
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_POST['tipo_iscrizione'] ?? 'prime');
    $subject = trim((string)($_POST['draft_subject'] ?? ''));
    iscrizioniPrimeDraftSubjectSave($tipoIscrizione, $subject, $_SESSION['username'] ?? '');
    echo json_encode([
        'ok' => true,
        'message' => 'Oggetto bozza salvato.',
        'draft_subject' => iscrizioniPrimeDraftSubject($tipoIscrizione),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/../checkSession.php';
require_once __DIR__ . '/../__MasterCom.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$requestData = array_merge($_GET, $_POST);

if (empty($requestData['form_stato']) || empty($requestData['stato_principale']) || empty($requestData['stato_secondario'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'Parametri form_stato, stato_principale e stato_secondario sono obbligatori',
    ]);
    exit;
}

$authResult = mastercomAuthenticateService([
    'profile' => 'MasterComAuth',
    'method' => 'POST',
    'timeout' => 60,
]);

if (!$authResult['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Autenticazione MasterCom amministratore fallita',
        'error' => $authResult['error'] ?? 'AUTH_FAILED',
        'http_code' => $authResult['http_code'] ?? 0,
    ]);
    exit;
}

$submitResult = mastercomSubmitAdminAbsenceAction($authResult, $requestData, [
    'method' => 'POST',
    'timeout' => 120,
    'send_in_body' => false,
]);

if (!$submitResult['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Invio azione assenza MasterCom fallito',
        'error' => $submitResult['error'] ?? 'SUBMIT_FAILED',
        'http_code' => $submitResult['http_code'] ?? 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Azione inviata a MasterCom',
    'http_code' => $submitResult['http_code'] ?? 0,
    'content_type' => $submitResult['content_type'] ?? null,
    'stato_principale' => $requestData['stato_principale'] ?? null,
    'stato_secondario' => $requestData['stato_secondario'] ?? null,
], JSON_UNESCAPED_UNICODE);

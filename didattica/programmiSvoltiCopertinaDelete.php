<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/programmiSvoltiCopertineLib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function programmiSvoltiCopertinaDeleteOut(bool $ok, string $message): void
{
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $copertinaId = intval($_POST['id'] ?? 0);
    if ($copertinaId <= 0) {
        programmiSvoltiCopertinaDeleteOut(false, 'Copertina non valida.');
    }

    $result = programmiSvoltiCopertinaDeleteRequest($copertinaId);
    programmiSvoltiCopertinaDeleteOut((bool)$result['ok'], (string)$result['message']);
} catch (Throwable $e) {
    programmiSvoltiCopertinaDeleteOut(false, $e->getMessage());
}


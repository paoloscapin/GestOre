<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/programmiSvoltiCopertineLib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function programmiSvoltiCopertinaConsegnaOut(bool $ok, string $message): void
{
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $copertinaId = intval($_POST['id'] ?? 0);
    $consegnata = intval($_POST['consegnata'] ?? 0) === 1;

    if ($copertinaId <= 0) {
        programmiSvoltiCopertinaConsegnaOut(false, 'Copertina non valida.');
    }

    $result = programmiSvoltiCopertinaSetVerificheConsegnate($copertinaId, $consegnata, intval($__utente_id ?? 0));
    programmiSvoltiCopertinaConsegnaOut((bool)$result['ok'], (string)$result['message']);
} catch (Throwable $e) {
    programmiSvoltiCopertinaConsegnaOut(false, $e->getMessage());
}


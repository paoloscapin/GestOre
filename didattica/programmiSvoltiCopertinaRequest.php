<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/programmiSvoltiCopertineLib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function programmiSvoltiCopertinaRequestOut(bool $ok, string $message): void
{
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $programmaId = intval($_POST['programma_id'] ?? 0);
    if ($programmaId <= 0) {
        programmiSvoltiCopertinaRequestOut(false, 'Programma non valido.');
    }

    $programma = programmiSvoltiCopertinaLoadProgramma($programmaId);
    if (!$programma) {
        programmiSvoltiCopertinaRequestOut(false, 'Programma svolto non trovato.');
    }

    if (!programmiSvoltiCopertinaUserCanRequest($programma, intval($__docente_id ?? 0))) {
        programmiSvoltiCopertinaRequestOut(false, 'Non sei autorizzato a richiedere la copertina per questo programma.');
    }

    $result = programmiSvoltiCopertinaRequest($programmaId, intval($__utente_id ?? 0));
    programmiSvoltiCopertinaRequestOut((bool)$result['ok'], (string)$result['message']);
} catch (Throwable $e) {
    programmiSvoltiCopertinaRequestOut(false, $e->getMessage());
}


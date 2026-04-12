<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente','segreteria-ata'. 'ras');

header('Content-Type: application/json; charset=utf-8');

function paJsonError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'ok' => false,
        'message' => $msg
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$connRef = $GLOBALS['__conn'] ?? ($GLOBALS['conn'] ?? null);
if (!$connRef) {
    paJsonError('Connessione database non disponibile.', 500);
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    paJsonError('ID non valido.');
}

$personale = dbGetFirst("
    SELECT id, username, cognome, nome
    FROM personale_ata
    WHERE id = $id
    LIMIT 1
");

if (!$personale) {
    paJsonError('Dipendente non trovato.', 404);
}

$username = mysqli_real_escape_string($connRef, (string)$personale['username']);

mysqli_begin_transaction($connRef);

try {
    dbExec("
        DELETE FROM personale_ata_assegnazioni
        WHERE username = '$username'
    ");

    dbExec("
        DELETE FROM personale_ata
        WHERE id = $id
        LIMIT 1
    ");

    mysqli_commit($connRef);

    echo json_encode([
        'ok' => true,
        'message' => 'Dipendente eliminato correttamente.'
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    mysqli_rollback($connRef);
    paJsonError('Errore in eliminazione: ' . $e->getMessage(), 500);
}
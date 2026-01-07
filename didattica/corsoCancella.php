<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani
 *  @copyright  (C) 2025
 *  @license    GPL-3.0+
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

function json_fail($msg, $http = 500) {
    http_response_code($http);
    echo json_encode(['success' => false, 'status' => 'ko', 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_POST['id']) || $_POST['id'] === "") {
    json_fail("Parametro mancante: id", 400);
}

$corso_id = intval($_POST['id']);
if ($corso_id <= 0) {
    json_fail("Parametro non valido: id", 400);
}

try {
    mysqli_begin_transaction($__con);

    // Verifica esistenza corso (utile per messaggi chiari)
    $corso = dbGetFirst("SELECT id, carenza, carenza_sessione FROM corso WHERE id = $corso_id LIMIT 1");
    if (!$corso) {
        mysqli_rollback($__con);
        echo json_encode(['success' => true, 'status' => 'ok', 'msg' => 'Corso già inesistente'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ============================
    // 1) Mapping carenze 1->2
    // Se sto cancellando un corso di 2ª sessione, devo togliere i mapping che puntano a lui.
    // (Se cancello un corso di 1ª sessione, tolgo i mapping dove è corso_primo)
    // ============================
    dbExec("DELETE FROM corso_carenze_seconda WHERE id_corso_secondo = $corso_id");
    dbExec("DELETE FROM corso_carenze_seconda WHERE id_corso_primo = $corso_id");

    // ============================
    // 2) Esami/esiti legati al corso
    // Prima esiti, poi date esami (per evitare FK)
    // ============================
    dbExec("DELETE FROM corso_esiti WHERE id_corso = $corso_id");
    dbExec("DELETE FROM corso_esami_date WHERE id_corso = $corso_id");

    // ============================
    // 3) Iscritti e date corso
    // ============================
    dbExec("DELETE FROM corso_iscritti WHERE id_corso = $corso_id");
    dbExec("DELETE FROM corso_date WHERE id_corso = $corso_id");

    // ============================
    // 4) Docenti associati (se usi corso_docenti)
    // ============================
    // Se la tabella non esiste, dbExec potrebbe dare errore.
    // In GestOre di solito esiste: se non esiste, dimmelo e lo rendiamo "safe".
    dbExec("DELETE FROM corso_docenti WHERE id_corso = $corso_id");

    // ============================
    // 5) Infine: corso
    // ============================
    dbExec("DELETE FROM corso WHERE id = $corso_id");

    mysqli_commit($__con);

    echo json_encode([
        'success' => true,
        'status'  => 'ok',
        'corso_id' => $corso_id
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    @mysqli_rollback($__con);
    // Messaggio completo (utile per capire subito quale tabella/vincolo rompe)
    json_fail("Errore cancellazione corso $corso_id: " . $e->getMessage());
} catch (Exception $e) {
    @mysqli_rollback($__con);
    json_fail("Errore cancellazione corso $corso_id: " . $e->getMessage());
}

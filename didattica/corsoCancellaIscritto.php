<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

// check request
if (isset($_POST['id']) && $_POST['id'] !== "" && isset($_POST['corso_id']) && $_POST['corso_id'] !== "") {

    $id = intval($_POST['id']);
    $corso_id = intval($_POST['corso_id']);

    if ($id <= 0 || $corso_id <= 0) {
        echo json_encode(["status" => "error", "error" => "Parametri non validi"]);
        exit;
    }

    // ricavo id_studente dalla iscrizione (così non mi fido del client)
    $id_studente = dbGetValue("SELECT id_studente FROM corso_iscritti WHERE id = $id AND id_corso = $corso_id LIMIT 1");
    $id_studente = intval($id_studente);

    if ($id_studente <= 0) {
        echo json_encode(["status" => "error", "error" => "Iscrizione non trovata"]);
        exit;
    }

    // 1) cancello eventuali esiti dello studente su questo corso
    dbExec("DELETE FROM corso_esiti WHERE id_corso = $corso_id AND id_studente = $id_studente");

    // 2) cancello iscrizione
    dbExec("DELETE FROM corso_iscritti WHERE id = $id LIMIT 1");

    // 3) ✅ se questo corso è un corso "secondo tentativo", sgancio il mapping
    //    (così nel corso di primo tentativo non risulta più "iscritto al secondo tentativo")
    dbExec("DELETE FROM corso_carenze_seconda WHERE id_corso_secondo = $corso_id AND id_studente = $id_studente");

    $response = [
        "status" => "ok"
    ];

    info("Studente id $id_studente iscritto al corso id $corso_id cancellato (e mapping eventuale sganciato)");

    echo json_encode($response);
    exit;
}

// fallback
echo json_encode(["status" => "error", "error" => "Parametri mancanti"]);
exit;

?>

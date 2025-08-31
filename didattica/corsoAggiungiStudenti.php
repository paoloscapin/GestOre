<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('docente','admin','segreteria-didattica');

if (isset($_POST['id_corso'], $_POST['id_studente'])) {

    $id_corso = intval($_POST['id_corso']);
    $id_studenti = $_POST['id_studente']; // può essere array o singolo valore

    if (!is_array($id_studenti)) {
        $id_studenti = [$id_studenti]; // rendi array se singolo studente
    }

    $added = [];
    $already = [];

    foreach ($id_studenti as $id_studente) {
        $id_studente = intval($id_studente);

        // Controllo se lo studente è già iscritto
        $queryCheck = "SELECT COUNT(*) as cnt 
                       FROM corso_iscritti 
                       WHERE id_corso = $id_corso AND id_studente = $id_studente";
        $res = dbGetFirst($queryCheck);

        if ($res['cnt'] == 0) {
            // Inserimento
            $query = "INSERT INTO corso_iscritti (id_corso, id_studente) 
                      VALUES ($id_corso, $id_studente)";
            dbExec($query);
            $added[] = $id_studente;
        } else {
            $already[] = $id_studente;
        }
    }

    $response = [
        'status' => 'ok',
        'added' => $added,
        'already' => $already,
        'message' => count($added) . " studenti aggiunti, " . count($already) . " già presenti"
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} else {
    $response = [
        'status' => 'error',
        'message' => 'Parametri mancanti'
    ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

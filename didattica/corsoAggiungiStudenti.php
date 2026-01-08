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

header('Content-Type: application/json; charset=utf-8');

if (isset($_POST['id_corso'], $_POST['id_studente'])) {

    $id_corso = intval($_POST['id_corso']);
    $id_studenti = $_POST['id_studente']; // può essere array o singolo valore

    if (!is_array($id_studenti)) {
        $id_studenti = [$id_studenti]; // rendi array se singolo studente
    }

    // leggo UNA volta se il corso è carenze
    $rowC = dbGetFirst("SELECT carenza FROM corso WHERE id = $id_corso LIMIT 1");
    $isCarenze = ($rowC && intval($rowC['carenza'] ?? 0) === 1);

    $added = [];
    $already = [];

    foreach ($id_studenti as $id_studente) {
        $id_studente = intval($id_studente);
        if ($id_studente <= 0) continue;

        // Controllo se lo studente è già iscritto
        $res = dbGetFirst("
            SELECT COUNT(*) as cnt
            FROM corso_iscritti
            WHERE id_corso = $id_corso AND id_studente = $id_studente
        ");

        $cnt = intval($res['cnt'] ?? 0);

        if ($cnt == 0) {
            // Inserimento iscrizione
            dbExec("
                INSERT INTO corso_iscritti (id_corso, id_studente)
                VALUES ($id_corso, $id_studente)
            ");

            // Se il corso è di carenze, inserisco anche in corso_esiti (UNA SOLA VOLTA)
            if ($isCarenze) {
                // se corso_esiti ha vincoli particolari, qui inseriamo solo le colonne esistenti nella tua tabella
                dbExec("
                    INSERT INTO corso_esiti (id_corso, id_studente)
                    SELECT $id_corso, $id_studente
                    WHERE NOT EXISTS (
                        SELECT 1 FROM corso_esiti
                        WHERE id_corso = $id_corso AND id_studente = $id_studente
                    )
                ");
            }

            $added[] = $id_studente;
        } else {
            $already[] = $id_studente;
        }
    }

    echo json_encode([
        'status'  => 'ok',
        'added'   => $added,
        'already' => $already,
        'message' => count($added) . " studenti aggiunti, " . count($already) . " già presenti"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} else {

    echo json_encode([
        'status'  => 'error',
        'message' => 'Parametri mancanti'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

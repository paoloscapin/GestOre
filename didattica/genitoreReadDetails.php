<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if (isset($_POST['id']) && $_POST['id'] != "") {
    $studente_id = $_POST['id'];

    // Recupero studente
    $query = "SELECT * FROM studente WHERE id = '$studente_id'";
    $studente = dbGetFirst($query);

    if (!$studente) {
        echo json_encode(['error' => 'Studente non trovato']);
        exit;
    }

    // Recupero frequenze
    $query = "SELECT * FROM studente_frequenta WHERE id_studente = '$studente_id' ORDER BY id_anno_scolastico DESC";
    $frequenze_raw = dbGetAll($query);

    $frequenze = [];
    $first = true; // Flag per il primo ciclo

    if (!empty($frequenze_raw)) {
        foreach ($frequenze_raw as $frequenza) {
            // Recupera nome classe
            $query = "SELECT classe FROM classi WHERE id = " . intval($frequenza['id_classe']);
            $classe = dbGetValue($query);

            // Recupera anno scolastico
            $query = "SELECT anno FROM anno_scolastico WHERE id = " . intval($frequenza['id_anno_scolastico']);
            $anno = dbGetValue($query);

            // Recupera nome classe
            $id_classe = intval($frequenza['id_classe']);
            $id_anno_scolastico = intval($frequenza['id_anno_scolastico']);

            if ($first) {
                // Se l'anno scolastico è quello corrente, aggiungo anche il nome dell'anno
                $studente['id_anno_scolastico'] = $id_anno_scolastico;
                $studente['id_classe'] = $id_classe;
                $first = false; // Dopo il primo ciclo, non entra più
            }

            // Aggiungi i dati
            $frequenza['classe'] = $classe;
            $frequenza['anno'] = $anno;

            $frequenze[] = $frequenza; // <-- salva nel nuovo array
        }
    }

    // Aggiungi array frequenze allo studente
    $studente['frequenze'] = $frequenze;

    // Output
    echo json_encode($studente);
}

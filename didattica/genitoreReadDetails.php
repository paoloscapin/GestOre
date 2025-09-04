<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if (isset($_POST['id']) && $_POST['id'] != "") {
    $genitore_id = $_POST['id'];

    // Recupero studente
    $query = "SELECT * FROM genitori WHERE id = '$genitore_id'";
    $genitore = dbGetFirst($query);

    if (!$genitore) {
        echo json_encode(['error' => 'Genitore non trovato']);
        exit;
    }

    // Recupero studenti associati
    $query = "SELECT * FROM genitori_studenti WHERE id_genitore = '$genitore_id'";
    $genitoriStudenti = dbGetAll($query);
    $genitoriDi = [];
    foreach ($genitoriStudenti as $genitoreStudente) {
        $query2 = "SELECT * FROM studente WHERE id = " . $genitoreStudente['id_studente'] . " AND attivo = '1'";
        $studente = dbGetFirst($query2);
        if ($studente) {
            $query2 = "SELECT id_classe FROM studente_frequenta WHERE id_studente = " . $studente['id'] . " AND id_anno_scolastico = '$__anno_scolastico_corrente_id'";
            $id_classe = dbGetValue($query2);
            $query2 = "SELECT classe FROM classi WHERE id = '$id_classe'";
            $classe = dbGetValue($query2);
            $genitoriDi[] = $studente['cognome'] . ' ' . $studente['nome'] . ' (Classe: ' . $classe . ')';
        }
    }
    $relazioni = [];
    foreach ($genitoriStudenti as $genitoreStudente) {
        $query2 = "SELECT relazione FROM genitori_relazioni WHERE id = " . $genitoreStudente['id_relazione'];
        $relazione = dbGetValue($query2);
        if ($relazione) {
            $relazioni[] = ucfirst($relazione);
        }
    }
    if ($genitoriDi == []) {
        $genitoriDi[] = 'Nessuno';
        $relazioni = ['Nessuna'];
    } 
    if ($relazioni == []) {
        $relazioni[] = 'Nessuna';
    } 
    $genitore['genitori_di'] = $genitoriDi;
    $genitore['relazioni'] = $relazioni;
   
    // Output
    echo json_encode($genitore);
}

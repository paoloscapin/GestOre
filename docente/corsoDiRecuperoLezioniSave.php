<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-didattica','dirigente');

if(isset($_POST)) {
	$id = $_POST['id'];
    $corso_di_recupero_id = $_POST['corso_di_recupero_id'];
    $data = escapePost('data');
    $numero_ore = escapePost('numero_ore');
    $lezioni_inizio = escapePost('inizia_alle');

    // prende l'ora di inizio dall'orario
    $timeStart = strtotime ($lezioni_inizio);
    // ora di inizio
    $inizia_alle = date("H:i:s", $timeStart);
    // durata (in ore da 50 minuti)
    $timeEnd = $timeStart + $numero_ore * (50 * 60);
    // orario in formato stringa
    $orario = date("H:i", $timeStart) . " - " . date("H:i", $timeEnd);

    if ($id > 0) {
        $query = "UPDATE lezione_corso_di_recupero SET data = '$data', orario = '$orario', numero_ore = '$numero_ore', inizia_alle = '$inizia_alle' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato lezione_corso_di_recupero id=$id data=$data orario = $orario, numero_ore = $numero_ore");
    } else {
        $query = "INSERT INTO lezione_corso_di_recupero (data, inizia_alle, numero_ore, orario, corso_di_recupero_id) VALUES ('$data', '$inizia_alle', $numero_ore, '$orario', $corso_di_recupero_id); ";
        dbExec($query);
        $id = dblastId();

        // aggiorna gli studenti per quella lezione
        $sql = "INSERT INTO studente_partecipa_lezione_corso_di_recupero (lezione_corso_di_recupero_id, studente_per_corso_di_recupero_id)
					SELECT lezione_corso_di_recupero.id, studente_per_corso_di_recupero.id FROM lezione_corso_di_recupero, studente_per_corso_di_recupero
                    WHERE lezione_corso_di_recupero.id = $id AND studente_per_corso_di_recupero.corso_di_recupero_id = $corso_di_recupero_id; ";
        dbExec($sql);

        info("aggiunto lezione_corso_di_recupero id=$id data=$data orario=$orario");
    }
}
?>
<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente','segreteria-docenti');

$tableName = "corso_di_recupero";
if(isset($_POST)) {
	$id = $_POST['id'];
    $codice = escapePost('codice');
    $aula = escapePost('aula');
    $docente_id = $_POST['docente_id'];
    $materia_id = $_POST['materia_id'];
    $datiModificati = $_POST['datiModificati'];
    $testoLezioni = $_POST['testoLezioni'];
    $testoLezioniModificato = $_POST['testoLezioniModificato'];
    $testoStudenti = $_POST['testoStudenti'];
    $testoStudentiModificato = $_POST['testoStudentiModificato'];

    if ($datiModificati) {
        if ($id > 0) {
            $query = "UPDATE $tableName SET codice = '$codice', aula = '$aula', docente_id = '$docente_id', materia_id = '$materia_id' WHERE id = '$id';";
            dbExec($query);
            info("aggiornato $tableName id=$id codice=$codice");
        } else {
            $query = "INSERT INTO $tableName(codice,aula,docente_id,materia_id,anno_scolastico_id) VALUES('$codice', '$aula', $docente_id, $materia_id, $__anno_scolastico_corrente_id));";
            dbExec($query);
            $id = dblastId();
            info("aggiunto $tableName id=$id codice=$codice");
        }
    }

    if ($testoLezioniModificato) {
        // per prima cosa cancella tutte le lezioni che esistono (prima rimuove tutte le partecipazioni degli studenti)
        dbExec("DELETE FROM studente_partecipa_lezione_corso_di_recupero WHERE lezione_corso_di_recupero_id IN (SELECT id FROM lezione_corso_di_recupero WHERE corso_di_recupero_id = $id);");
        dbExec("DELETE FROM lezione_corso_di_recupero WHERE corso_di_recupero_id = $id;");

        // separa le linee
        $lines = explode("\n", $testoLezioni);

        // estrae le lezioni dalle linee
        foreach($lines as $line) {
            $wordList = explode(" - ", $line);
            if (count($wordList) >= 4) {
                $data = $wordList[0];
                $inizia_alle = $wordList[1];
                $numero_ore = $wordList[2];
                $orario = $wordList[3];
                if (count($wordList) > 4) {
                    $orario = $orario . ' - ' . $wordList[4];
                }
                dbExec("INSERT INTO lezione_corso_di_recupero(data,inizia_alle,numero_ore,orario,corso_di_recupero_id) VALUES('$data', '$inizia_alle', $numero_ore, '$orario', $id);");
            }
        }
        info("Aggiornate le lezioni per corso di recupero id=$id codice=$codice"); 
    }

    if ($testoStudentiModificato) {
        // per prima cosa cancella tutte le lezioni che esistono (prima rimuove tutte le partecipazioni degli studenti)
        dbExec("DELETE FROM studente_partecipa_lezione_corso_di_recupero WHERE studente_per_corso_di_recupero_id IN (SELECT id FROM studente_per_corso_di_recupero WHERE corso_di_recupero_id = $id);");
        dbExec("DELETE FROM studente_per_corso_di_recupero WHERE corso_di_recupero_id = $id;");

        // separa le linee
        $lines = explode("\n", $testoStudenti);

        // estrae gli studenti dalle linee
        foreach($lines as $line) {
            $wordList = explode(" - ", $line);
            if (count($wordList) >= 3) {
                $classe = escapeString($wordList[0]);
                $cognome = escapeString($wordList[1]);
                $nome = escapeString($wordList[2]);
                $serve_voto = 1;
                if (count($wordList) >= 4) {
                    if (strtolower(trim($wordList[3])) === 'uditore') {
                        // nota il problema sorto dal nome fuorviante: serve_voto significa che e' esente da esame...
                        $serve_voto = 0;
                    }
                }
                dbExec("INSERT INTO studente_per_corso_di_recupero(classe,cognome,nome,serve_voto,corso_di_recupero_id) VALUES('$classe', '$cognome', '$nome', $serve_voto, $id);");
            }
        }
        info("Aggiornati gli studenti per corso di recupero id=$id codice=$codice"); 
    }

    // se ha fatto uno dei due (lezioni o studenti) deve ricostruire le partecipazioni (vuote...)
    if ($testoLezioniModificato || $testoStudentiModificato) {
        dbExec("INSERT INTO studente_partecipa_lezione_corso_di_recupero (lezione_corso_di_recupero_id, studente_per_corso_di_recupero_id)
        SELECT lezione_corso_di_recupero.id, studente_per_corso_di_recupero.id FROM lezione_corso_di_recupero, studente_per_corso_di_recupero
        WHERE lezione_corso_di_recupero.corso_di_recupero_id = $id AND studente_per_corso_di_recupero.corso_di_recupero_id = $id;");
        info("Aggiornate le partecipazioni per corso di recupero id=$id codice=$codice");
    }
}
?>
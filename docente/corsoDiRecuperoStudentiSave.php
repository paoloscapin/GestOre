<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-didattica','segreteria-docenti','dirigente');

if(isset($_POST)) {
	$id = $_POST['id'];
    $corso_di_recupero_id = $_POST['corso_di_recupero_id'];
    $cognome = escapePost('cognome');
    $nome = escapePost('nome');
    $classe = escapePost('classe');
    $email = escapePost('email');
    $commento = escapePost('commento');

    // controlla se e' un uditore e quindi non deve fare la prova finale: deve esserci scritto uditore nel commento
    $serve_voto = 1;
    // controlla se il commento contiene "uditore"
    if (strpos(strtolower($commento), 'uditore') !== false) {
        $serve_voto = 0;
    }

    if ($id > 0) {
        $query = "UPDATE studente_per_corso_di_recupero SET cognome = '$cognome', nome = '$nome', classe = '$classe', email = '$email', commento = '$commento', serve_voto = '$serve_voto' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato studente_per_corso_di_recupero id=$id cognome=$cognome, nome = $nome,  classe = $classe, email = $email, commento = '$commento', serve_voto = '$serve_voto'");
    } else {
        $query = "INSERT INTO studente_per_corso_di_recupero (cognome, nome, classe, email, commento, serve_voto, corso_di_recupero_id) VALUES ('$cognome', '$nome', '$classe', '$email', '$commento', '$serve_voto', $corso_di_recupero_id); ";
        dbExec($query);
        $id = dblastId();

        // deve partecipare a tutte le lezioni di quel corso di recupero
        $sql = "INSERT INTO studente_partecipa_lezione_corso_di_recupero (lezione_corso_di_recupero_id, studente_per_corso_di_recupero_id)
	                        SELECT lezione_corso_di_recupero.id, studente_per_corso_di_recupero.id FROM lezione_corso_di_recupero, studente_per_corso_di_recupero
                        	WHERE lezione_corso_di_recupero.corso_di_recupero_id = $corso_di_recupero_id AND studente_per_corso_di_recupero.id = $id; ";
        dbExec($sql);

        info("aggiunto studente_per_corso_di_recupero id=$id cognome=$cognome, nome = $nome,  classe = $classe, email = $email");
    }
}
?>
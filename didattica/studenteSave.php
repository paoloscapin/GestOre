<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('segreteria-didattica');

 if(isset($_POST)) {
	$id = $_POST['id'];
	$cognome = escapePost('cognome');
	$nome = escapePost('nome');
	$email = escapePost('email');
	$classe = escapePost('classe');
	$anno = escapePost('anno');

	if ($id > 0) {
        $query = "UPDATE studente SET cognome = '$cognome', nome = '$nome', email = '$email', classe = '$classe', anno = '$anno' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato studente id=$id cognome=$cognome nome=$nome email=$email classe=$classe anno=$anno");
    } else {
        $query = "INSERT INTO studente(cognome, nome, email, classe, anno) VALUES('$cognome', '$nome', '$email', '$classe', '$anno')";
        dbExec($query);
        $studenteId = dblastId();
		info("aggiunto studente id=$studenteId cognome=$cognome nome=$nome email=$email classe=$classe anno=$anno");

        // insert dell'utente
        $username = strstr($email, '@', true);
        $username = $nome . '.' . $cognome;
        $query = "INSERT INTO utente(nome, cognome, username, email, ruolo) VALUES('$nome', '$cognome', '$username', '$email', 'studente')";
        dbExec($query);
    }
}
?>

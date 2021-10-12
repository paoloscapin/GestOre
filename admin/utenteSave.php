<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('admin');

$tableName = "utente";
if(isset($_POST)) {
	$id = $_POST['id'];
    $username = escapePost('username');
    $cognome = escapePost('cognome');
    $nome = escapePost('nome');
    $ruolo = escapePost('ruolo');
	$email = escapePost('email');

    if ($id > 0) {
        $query = "UPDATE utente SET username = '$username', cognome = '$cognome', nome = '$nome', ruolo = '$ruolo', email = '$email' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato utente id=$id username=$username cognome=$cognome  nome=$nome  ruolo=$ruolo email=$email");
    } else {
        $query = "INSERT INTO utente(username, cognome, nome, ruolo, email) VALUES('$username', '$cognome', '$nome', '$ruolo', '$email')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto utente id=$id username=$username cognome=$cognome  nome=$nome  ruolo=$ruolo email=$email");    
    }
}
?>
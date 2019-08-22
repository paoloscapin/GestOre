<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('segreteria-docenti','dirigente');

if(isset($_POST['nome']) && isset($_POST['cognome']) && isset($_POST['email'])) {
	$nome = $_POST['nome'];
	$cognome = $_POST['cognome'];
	$email = $_POST['email'];
	$username = $_POST['username'];
	$matricola = $_POST['matricola'];

	$query = "INSERT INTO docente(nome, cognome, email, username, matricola) VALUES('$nome', '$cognome', '$email', '$username', '$matricola')";
	dbExec($query);

	// trova l'id inserito
	$docente_id = dblastId();

	// insert dell'utente
	$query = "INSERT INTO utente(nome, cognome, username, ruolo) VALUES('$nome', '$cognome', '$username', 'docente')";
	dbExec($query);

	// insert del profilo
	$query = "INSERT INTO profilo_docente(anno_scolastico_id, docente_id) VALUES('$__anno_scolastico_corrente_id', '$docente_id')";
	dbExec($query);

	info("aggiunto utente username=$username id=$docente_id cognome=$cognome nome=$nome email=$email");
}
?>
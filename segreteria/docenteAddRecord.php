<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST['nome']) && isset($_POST['cognome']) && isset($_POST['email'])) {
	// include Database connection file
	require_once '../common/checkSession.php';
	require_once '../common/connect.php';

	// get values
	$nome = $_POST['nome'];
	$cognome = $_POST['cognome'];
	$email = $_POST['email'];
	$username = $_POST['username'];
	$matricola = $_POST['matricola'];

	$query = "INSERT INTO docente(nome, cognome, email, username, matricola) VALUES('$nome', '$cognome', '$email', '$username', '$matricola')";
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}

	// trova l'id inserito
	$docente_id = $con->insert_id;

	// insert dell'utente
	$query = "INSERT INTO utente(nome, cognome, username, ruolo) VALUES('$nome', '$cognome', '$username', 'docente')";
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}

	// insert del profilo
	$query = "INSERT INTO profilo_docente(anno_scolastico_id, docente_id) VALUES('$__anno_scolastico_corrente_id', '$docente_id')";
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}
	echo "aggiuto 1 docente!";
}
?>
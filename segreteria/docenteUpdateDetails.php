<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
	// include Database connection file 
	require_once '../common/connect.php';

	// get values
	$docente_id = $_POST['docente_id'];
	$nome = $_POST['nome'];
	$cognome = $_POST['cognome'];
	$email = $_POST['email'];
	$username = $_POST['username'];
	$matricola = $_POST['matricola'];
	$attivo = $_POST['attivo'];
	$era_attivo = $_POST['era_attivo'];
	// posso usare $_POST['era_attivo']; che vale 1 o 0

	// Update docente details
	$query = "UPDATE docente SET nome = '$nome', cognome = '$cognome', email = '$email', username = '$username', matricola = '$matricola', attivo = '$attivo' WHERE id = '$docente_id'";
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}

	if ($era_attivo == 0 && $attivo != 0) {
		require_once '../common/checkSession.php';
				// Get Docente Details
		$query = "	SELECT profilo_docente.id
					FROM profilo_docente
					WHERE profilo_docente.anno_scolastico_id = '$__anno_scolastico_corrente_id'
					AND profilo_docente.docente_id = '$docente_id'";

		if (!$result = mysqli_query($con, $query)) {
			exit(mysqli_error($con));
		}

		if(mysqli_num_rows($result) == 0) {
			// se non ci sono risultati, deve creare un nuovo profilo docente per questo docente per quest'anno
			$query = "INSERT INTO profilo_docente(anno_scolastico_id, docente_id) VALUES('$__anno_scolastico_corrente_id', '$docente_id')";
			if (!$result = mysqli_query($con, $query)) {
				exit(mysqli_error($con));
			}
			echo "aggiuto 1 profilo docente!";
		}
	}
}
?>
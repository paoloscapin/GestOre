<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-docenti','dirigente');

if(isset($_POST)) {
	$docente_id = $_POST['docente_id'];
	$nome = escapePost('nome');
	$cognome = escapePost('cognome');
	$email = escapePost('email');
	$username = escapePost('username');
	$matricola = escapePost('matricola');
	$classe_di_concorso =  escapePost('classe_di_concorso');
	$attivo = $_POST['attivo'];
	$era_attivo = $_POST['era_attivo'];
	// posso usare $_POST['era_attivo']; che vale 1 o 0

	$query = "UPDATE docente SET nome = '$nome', cognome = '$cognome', email = '$email', username = '$username', matricola = '$matricola', attivo = '$attivo' WHERE id = '$docente_id'";
	dbExec($query);

	$query = "UPDATE profilo_docente SET classe_di_concorso = '$classe_di_concorso' WHERE docente_id = '$docente_id' AND anno_scolastico_id = '$__anno_scolastico_corrente_id'";
	dbExec($query);

	info("aggiornato docente docente_id=$docente_id cognome=$cognome nome=$nome email=$email");

	if ($era_attivo == 0 && $attivo != 0) {
		$query = "	SELECT profilo_docente.id
					FROM profilo_docente
					WHERE profilo_docente.anno_scolastico_id = '$__anno_scolastico_corrente_id'
					AND profilo_docente.docente_id = '$docente_id'";

		$profilo_docente = dbGetFirst($query);
		if($profilo_docente == null) {
			// se non ci sono risultati, deve creare un nuovo profilo docente per questo docente per quest'anno
			$query = "INSERT INTO profilo_docente(anno_scolastico_id, docente_id) VALUES('$__anno_scolastico_corrente_id', '$docente_id')";
			dbExec($query);
			$profilo_docente_id = dblastId();
			info("aggiunto profile docente id=$profilo_docente_id username=$username id=$docente_id cognome=$cognome nome=$nome");
		}
	}
}
?>
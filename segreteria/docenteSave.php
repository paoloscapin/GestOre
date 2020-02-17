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
	if(getSettingsValue('config','comprensorio', false)) {
		$codice_istituto = escapePost('codice_istituto');
	}
	$docente_id = $_POST['docente_id'];
	$nome = escapePost('nome');
	$cognome = escapePost('cognome');
	$email = escapePost('email');
	$username = escapePost('username');
	$matricola = escapePost('matricola');
	$attivo = $_POST['attivo'];
	$era_attivo = $_POST['era_attivo'];
	// posso usare $_POST['era_attivo']; che vale 1 o 0

	debug("-------------------------------    ATTIVO = $attivo");
	debug("-------------------------------ERA ATTIVO = $era_attivo");

	if ($docente_id > 0) {
		$codicePart = getSettingsValue('config','comprensorio', false) ? " codice_istituto = '$codice_istituto' , " : "";

		$query = "UPDATE docente SET ".$codicePart." nome = '$nome', cognome = '$cognome', email = '$email', username = '$username', matricola = '$matricola', attivo = '$attivo' WHERE id = '$docente_id'";
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
				info("aggiunto profilo docente id=$profilo_docente_id username=$username id=$docente_id cognome=$cognome nome=$nome");
			}
		}
	} else {
		$codiceIntoPart = getSettingsValue('config','comprensorio', false) ? "codice_istituto," : "";
		$codiceValuePart = getSettingsValue('config','comprensorio', false) ? "'$codice_istituto', " : "";
		$query = "INSERT INTO docente(".$codiceIntoPart."nome, cognome, email, username, matricola) VALUES(".$codiceValuePart."'$nome', '$cognome', '$email', '$username', '$matricola')";
		dbExec($query);
	
		// trova l'id inserito
		$docente_id = dblastId();
	
		// insert dell'utente
		$query = "INSERT INTO utente(nome, cognome, username, email, ruolo) VALUES('$nome', '$cognome', '$username', '$email', 'docente')";
		dbExec($query);
	
		// insert del profilo
		$query = "INSERT INTO profilo_docente(anno_scolastico_id, docente_id) VALUES('$__anno_scolastico_corrente_id', '$docente_id')";
		dbExec($query);
	
		info("aggiunto utente username=$username id=$docente_id cognome=$cognome nome=$nome email=$email");	
	}

}
?>
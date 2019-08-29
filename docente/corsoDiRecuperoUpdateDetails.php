<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$lezione_corso_di_recupero_id = $_POST['lezione_corso_di_recupero_id'];
	$argomento = mysqli_real_escape_string($con, $_POST['argomento']);
	$argomentoChanged = $_POST['argomentoChanged'];
	$note = mysqli_real_escape_string($con, $_POST['note']);
	$noteChanged = $_POST['noteChanged'];
	$studentiDaModificareIdArray = json_decode($_POST['studentiDaModificareIdList']);

	if ($argomentoChanged || $noteChanged) {
		$query = "UPDATE lezione_corso_di_recupero SET ";
		if ($argomentoChanged && $noteChanged) {
			$query .= "argomento = '$argomento', note = '$note' ";
		} else if ($argomentoChanged) {
			$query .= "argomento = '$argomento' ";
		} else {
			$query .= "note = '$note' ";
		}
		$query .= "WHERE id = '$lezione_corso_di_recupero_id'";

		dbExec($query);
		info("aggiornato lezione_corso_di_recupero id=$id argomento=$argomento note=$note");
	}

	// aggiorna i partecipanti
	foreach($studentiDaModificareIdArray as $studente_partecipa_lezione_corso_di_recupero) {
		$query = "UPDATE studente_partecipa_lezione_corso_di_recupero SET ha_partecipato = NOT ha_partecipato WHERE studente_partecipa_lezione_corso_di_recupero.id = $studente_partecipa_lezione_corso_di_recupero";
		dbExec($query);
		info("aggiornato studente_partecipa_lezione_corso_di_recupero studente_partecipa_lezione_corso_di_recupero=$studente_partecipa_lezione_corso_di_recupero");
	}
}
?>
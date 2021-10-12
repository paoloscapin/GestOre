<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('studente','dirigente');

if(isset($_POST)) {
	$sportello_id = $_POST['id'];
	$materia = escapePost('materia');
	$argomento = escapePost('argomento');

	dbExec("INSERT INTO sportello_studente(iscritto, argomento, sportello_id, studente_id) VALUES(true, '$argomento', $sportello_id, $__studente_id)");
	$last_id = dblastId();
	info("iscritto $__studente_cognome $__studente_nome allo sportello di $materia argomento=$argomento sportello_id=$sportello_id");

	// aggiorna l'argomento dello sportello se ne prevediamo uno solo per tutti
	if (getSettingsValue('sportelli','unSoloArgomento', true)) {
		dbExec("UPDATE sportello  SET argomento = '$argomento' WHERE id = '$sportello_id'");
		info("aggiornato sportello con il suo argomento sportello_id=$sportello_id argomento=$argomento");
	}
}
?>
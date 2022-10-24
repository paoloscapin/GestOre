<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
 ruoloRichiesto('studente','segreteria-didattica','dirigente');

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	$sportello_id = $_POST['id'];
	$materia = $_POST['materia'];

	dbExec("DELETE FROM sportello_studente WHERE sportello_id = $sportello_id AND studente_id  = $__studente_id");
	info("cancellata iscrizione di $__studente_cognome $__studente_nome dallo sportello di $materia sportello_id=$sportello_id");

	// se e' settato un solo argomento, nel caso si cancelli l'ultimo studente, non ha senso tenere l'argomento per cui toglie anche quello
	if (getSettingsValue("sportelli", "unSoloArgomento", true)) {
		// controlla quanti studenti sono ancora iscritti
		$iscritti = dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = $sportello_id;");
		if ($iscritti == 0) {
			debug("non ci sono altri iscritti quindi cancello l'argomento");
			dbExec("UPDATE sportello SET argomento='' WHERE id = $sportello_id;");
			info("cancellato argomento per lo sportello id=$sportello_id");
		}
	}
}
?>
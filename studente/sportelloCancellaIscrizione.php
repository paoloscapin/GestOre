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
	$argomento = $_POST['argomento'];
	$categoria = $_POST['categoria'];
	$data = $_POST['data'];
	$ora = $_POST['ora'];
	$numero_ore = $_POST['numero_ore'];
	$luogo = $_POST['luogo'];
	$studente_id = $_POST['studente_id'];
	$docente_id = $_POST['docente_id'];

	$date_time = $data . " " . $ora . ":00";
	$dateT=date_create($date_time , timezone_open("Europe/Rome"));
	// Check Daylight Saving Time
	$isDST = $dateT->format("I");	
	if ($isDST)
	{
		$dateT->sub(new DateInterval('PT2H'));
	}
	else
	{
		$dateT->sub(new DateInterval('PT1H'));
	}

	// inverto format data - giorno con mese
	$data_array = explode("-", $data);
	$data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];
	
	dbExec("DELETE FROM sportello_studente WHERE sportello_id = $sportello_id AND studente_id  = $__studente_id");
	info("cancellata iscrizione di $__studente_cognome $__studente_nome dallo sportello di $materia sportello_id=$sportello_id");
	require 'sportelloMailCancellazioneStudente.php';

	$iscritti = dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = $sportello_id;");
	if ($iscritti == 0) 
	{	// se e' settato un solo argomento, nel caso si cancelli l'ultimo studente, non ha senso tenere l'argomento per cui toglie anche quello
	
		if (getSettingsValue("sportelli", "unSoloArgomento", true)) {
		// controlla quanti studenti sono ancora iscritti

			debug("non ci sono altri iscritti quindi cancello l'argomento");
			dbExec("UPDATE sportello SET argomento='' WHERE id = $sportello_id;");
			info("cancellato argomento per lo sportello id=$sportello_id");
		}
		require 'sportelloInviaMailCancellazioneDocente.php';
	}
}
?>
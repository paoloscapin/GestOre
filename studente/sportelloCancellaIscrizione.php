<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

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

	// recupero tutti i dati necessari di studente, docente e genitori
	$studente = dbGetFirst("SELECT * from studente WHERE id = $__studente_id");
	$studente_nome = $studente['nome'];
	$studente_cognome = $studente['cognome'];
	$studente_email = $studente['email'];
	$docente = dbGetFirst("SELECT * from docente WHERE id = $docente_id");
	$docente_nome = $docente['nome'];
	$docente_cognome = $docente['cognome'];
	$docente_email = $docente['email'];
	$genitori = dbGetAll("SELECT email from genitori g
						  INNER JOIN genitori_studenti gs ON gs.id_studente = $__studente_id
						  WHERE g.attivo=1 AND gs.id_genitore = g.id");
	$email_genitori = "";
	foreach($genitori as $genitore) {
		if ($email_genitori != "") {
			$email_genitori = $email_genitori . ", ";
		}
		$email_genitori = $email_genitori . $genitore['email'];
	}

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
	info("cancellata iscrizione dello studente $studente_id dallo sportello di $materia sportello_id=$sportello_id");
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
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

 
if(isset($_POST)) {
	$sportello_id = $_POST['id'];
	$materia = escapePost('materia');
	$argomento = escapePost('argomento');
	$categoria = $_POST['categoria'];
	$data = $_POST['data'];
	$ora = $_POST['ora'];
	$numero_ore = $_POST['numero_ore'];
	$luogo = $_POST['luogo'];
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
	dbExec("INSERT INTO sportello_studente(iscritto, argomento, sportello_id, studente_id) VALUES(true, '$argomento', $sportello_id, $__studente_id)");
	$last_id = dblastId();
	info("iscritto $__studente_cognome $__studente_nome allo sportello di $materia argomento=$argomento sportello_id=$sportello_id");

	// aggiorna l'argomento dello sportello se ne prevediamo uno solo per tutti
	if (getSettingsValue('sportelli','unSoloArgomento', true)) {
		dbExec("UPDATE sportello  SET argomento = '$argomento' WHERE id = '$sportello_id'");
		info("aggiornato sportello con il suo argomento sportello_id=$sportello_id argomento=$argomento");
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
	$datetime_sportello = date_format($dateT,"Ymd-His");
	$datetime_sportello = str_replace("-","T",$datetime_sportello);
	$durata_minuti = $numero_ore * 50;
	$dateT_fine = $dateT;
	$dateT_fine->modify(' + ' . $durata_minuti . ' minutes');
	$datetime_fine_sportello = date_format($dateT_fine,"Ymd-His");
	$datetime_fine_sportello = str_replace("-","T",$datetime_fine_sportello);
	
	// inverto format data - giorno con mese
	$data_array = explode("-", $data);
	$data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];
	
	// invio la mail di iscrizione allo studente
	require 'sportelloMailIscrizioneStudente.php';

	$query = "	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = " . $sportello_id;
	
	$numero_studenti_iscritti = dbGetValue($query);

	// è il primo studente dello sportello?
	if ($numero_studenti_iscritti == 1)
	{
		// invio una mail al docente per avvisarlo che c'è almeno uno studente iscritto
		require 'sportelloInviaMailIscrizioneDocente.php';
	}
}
?>
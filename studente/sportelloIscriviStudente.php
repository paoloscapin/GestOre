<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('studente','segreteria-didattica','dirigente');

 
if(isset($_POST)) {
	$sportello_id = $_POST['id'];
	$materia = escapePost('materia');
	$argomento = escapePost('argomento');
	$data = $_POST['data'];
	$ora = $_POST['ora'];
	$numero_ore = $_POST['numero_ore'];
	$luogo = $_POST['luogo'];
	$studente_cognome = $_POST['studente_cognome'];
	$studente_nome = $_POST['studente_nome'];
	$studente_email = $_POST['studente_email'];
	$studente_classe = $_POST['studente_classe'];
	$docente_cognome = $_POST['docente_cognome'];
	$docente_nome = $_POST['docente_nome'];
	$docente_email = $_POST['docente_email'];

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
	
	info("PRIMA DI INVIO MAIL STUDENTE");
	// invio la mail di iscrizione allo studente
	require 'sportelloMailStudente.php';
	info("DOPO INVIO MAIL STUDENTE");
	$query = "	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = " . $sportello_id;
	
	$numero_studenti_iscritti = dbGetValue($query);
	info("NUMERO STUDENTI ISCRITTI ALLO SPORTELLO: ".$numero_studenti_iscritti);
	// è il primo studente dello sportello?
	if ($numero_studenti_iscritti == 1)
	{
		// invio una mail al docente per avvisarlo che c'è almeno uno studente iscritto
		require 'sportelloInviaMailDocente.php';
	}
}
?>
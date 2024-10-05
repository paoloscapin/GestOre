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

	dbExec("INSERT INTO sportello_studente(iscritto, argomento, sportello_id, studente_id) VALUES(true, '$argomento', $sportello_id, $__studente_id)");
	$last_id = dblastId();
	info("iscritto $__studente_cognome $__studente_nome allo sportello di $materia argomento=$argomento sportello_id=$sportello_id");

	// aggiorna l'argomento dello sportello se ne prevediamo uno solo per tutti
	if (getSettingsValue('sportelli','unSoloArgomento', true)) {
		dbExec("UPDATE sportello  SET argomento = '$argomento' WHERE id = '$sportello_id'");
		info("aggiornato sportello con il suo argomento sportello_id=$sportello_id argomento=$argomento");
	}
	$date_time = $data . " " . $ora . ":00";
	$dateT=date_create($date_time , timezone_open("Europe/Oslo"));
	$datetime_sportello = date_format($dateT,"Ymd-His");
	$datetime_sportello = str_replace("-","T",$datetime_sportello);
	$durata_minuti = $numero_ore * 50;
	$dateT_fine = $dateT;
	$dateT_fine->modify(' + ' . $durata_minuti . ' minutes');
	$datetime_fine_sportello = date_format($dateT_fine,"Ymd-His");
	$datetime_fine_sportello = str_replace("-","T",$datetime_fine_sportello);
	info("formato Google inizio: " . $datetime_sportello . " fine: " . $datetime_fine_sportello . " luogo: " . $luogo);
	require_once 'sportelloMail.php';

}
?>
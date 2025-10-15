<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'docente');

if (isset($_POST)) {

	$id = $_POST['id'];
	$docente_id = $_POST['docente_id'];
	$classe_id = $_POST['classe_id'];
	$materia_id = $_POST['materia_id'];
	$duplica = $_POST['duplica'];
	$share = $_POST['share'];
	date_default_timezone_set("Europe/Rome");
	$update = date("Y-m-d H-i-s");
	$utente_id = $__utente_id;
	$data = '';
	if (($duplica == 'false') && ($share == 'false')) {
		if ($id > 0) {
			$query = "UPDATE programmi_iniziali SET id_classe = '$classe_id', id_docente = '$docente_id', id_materia = '$materia_id', id_utente = '$utente_id', updated = '$update' WHERE id = '$id'";
			dbExec($query);
			info("aggiornato programma iniziale id=$id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_utente=$utente_id updated=$update");
		} else {
			$query = "INSERT INTO programmi_iniziali(id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated) VALUES('$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update')";
			dbExec($query);
			$new_id = dblastId();
			$data = $new_id;
			info("aggiunto programma iniziale id=$new_id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");
		}
	} else if ($duplica == 'true')
	{

		// verifico se esiste già la classe su cui voglio duplicare il programma
		$query = "SELECT * from programmi_iniziali WHERE id_classe='$classe_id' AND id_docente='$docente_id' AND id_materia='$materia_id'";
		$result = dbGetFirst($query);
		
		if ($result!=null)
		{
		  $data = 'Programma già esistente';	
		}
		else
		{
			// creo il programma vuoto per la nuova classe
			$query = "INSERT INTO programmi_iniziali(id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated) VALUES('$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update')";
			dbExec($query);
			$new_id = dblastId();
			info("aggiunto programma iniziale id=$new_id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");

			// duplico i moduli collegati al programma originale
			$query = "INSERT INTO programmi_iniziali_moduli (id_programma, ordine, nome, conoscenze, abilita, competenze, periodo, id_utente, updated)
			SELECT $new_id AS id_programma, ordine, nome, conoscenze, abilita, competenze, periodo, id_utente, NOW() AS updated FROM programmi_iniziali_moduli WHERE id_programma = $id";
			dbExec($query);
			info("duplicati i moduli del programma iniziale id=$id e li ho collegati al nuovo programma iniziale id=$new_id");
		}
	}
	else if ($share == 'true')
	{
		// verifico se esiste già la classe su cui voglio duplicare il programma
		$query = "SELECT * from programmi_iniziali WHERE id_classe='$classe_id' AND id_docente='$docente_id' AND id_materia='$materia_id'";
		$result = dbGetFirst($query);
		
		if (($result!=null)&&($overwrite!='true'))
		{
		  $data = 'Programma già esistente';	
		}
		else
		{
			// creo il programma vuoto per la nuova classe
			$query = "INSERT INTO programmi_iniziali(id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated) VALUES('$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update')";
			dbExec($query);
			$new_id = dblastId();
			info("aggiunto programma iniziale id=$new_id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");
			// duplico i moduli collegati al programma originale
			$query = "INSERT INTO programmi_iniziali_moduli (id_programma, ordine, nome, conoscenze, abilita, competenze, periodo, id_utente, updated)
			SELECT $new_id AS id_programma, ordine, nome, conoscenze, abilita, competenze, periodo, id_utente, NOW() AS updated FROM programmi_iniziali_moduli WHERE id_programma = $id";
			dbExec($query);
			info("duplicati per il docente id=$docente_id i moduli del programma iniziale id=$id e li ho collegati al nuovo programma iniziale id=$new_id");
		}
	}
	echo $data;
}
?>
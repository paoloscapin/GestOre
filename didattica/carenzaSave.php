<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica');

if (isset($_POST)) {

	$id = $_POST['id'];
	$studente_id = $_POST['studente_id'];
	$anno_id = $_POST['anno_id'];

	$query = "SELECT 
	s.id_classe, 
	s.id_studente,
	s.id_anno_scolastico
	FROM studente_frequenta s
	WHERE s.id_studente='$studente_id' AND s.id_anno_scolastico='$anno_id'";
	$result = dbGetFirst($query);
	$classe_id = $result['id_classe'];

	$materia_id = $_POST['materia_id'];
	date_default_timezone_set("Europe/Rome");
	$update = date("Y-m-d H-i-s");
	$utente_id = $__utente_id;
	if ($id > 0) {
		$query = "UPDATE carenze SET id_studente = '$studente_id', id_classe = '$classe_id', id_materia = '$materia_id', id_docente = '0', stato = '0', data_inserimento = '$update', data_validazione = '', data_invio = '' WHERE id = '$id'";
		dbExec($query);
		info("aggiornata materia id=$id id_materia=$materia_id id_utente=$utente_id updated=$update");
	} else {
		$query = "INSERT INTO carenze(id_studente, id_materia, id_classe, id_docente, id_anno_scolastico, stato, data_inserimento, data_validazione, data_invio) VALUES('$studente_id', '$materia_id', '$classe_id', '0', '$anno_id', '0', '$update','','')";
		dbExec($query);
		$id = dblastId();
		info("aggiunta carenza	 id=$id id_studente)$studente_id id_classe=$classe_id id_materia=$materia_id id_utente=$utente_id updated=$update");
	}
}
?>
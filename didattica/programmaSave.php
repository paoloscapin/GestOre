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
	$anno_id = $_POST['anno_id'];
	$indirizzo_id = $_POST['indirizzo_id'];
	$materia_id = $_POST['materia_id'];
	date_default_timezone_set("Europe/Rome");
	$update = date("Y-m-d H-i-s");
	$utente_id = $__utente_id;
	if ($id > 0) {
		$query = "UPDATE programma_materie SET anno = '$anno_id', id_indirizzo = '$indirizzo_id', id_materia = '$materia_id', id_utente = '$utente_id', updated = '$update' WHERE id = '$id'";
		dbExec($query);
		info("aggiornata materia id=$id  anno=$anno_id id_indirizzo=$indirizzo_id id_materia=$materia_id id_utente=$utente_id updated=$update");
	} else {
		$query = "INSERT INTO programma_materie(anno, id_indirizzo, id_materia, id_utente, updated) VALUES('$anno_id', '$indirizzo_id', '$materia_id', '$utente_id', '$update')";
		dbExec($query);
		$id = dblastId();
		info("aggiunta materia	 id=$id  anno=$anno_id id_indirizzo=$indirizzo_id id_materia=$materia_id id_utente=$utente_id updated=$update");
	}
}
?>
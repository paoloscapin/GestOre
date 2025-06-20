<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica','docente');

if (isset($_POST)) {

	$id = $_POST['id'];
	$docente_id = $_POST['docente_id'];
	$classe_id = $_POST['classe_id'];
	$materia_id = $_POST['materia_id'];
	date_default_timezone_set("Europe/Rome");
	$update = date("Y-m-d H-i-s");
	$utente_id = $__utente_id;
	if ($id > 0) {
		$query = "UPDATE programmi_svolti SET id_classe = '$classe_id', id_docente = '$docente_id', id_materia = '$materia_id', id_utente = '$utente_id', updated = '$update' WHERE id = '$id'";
		dbExec($query);
		info("aggiornato programma svolto id=$id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_utente=$utente_id updated=$update");
	} else {
		$query = "INSERT INTO programmi_svolti(id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated) VALUES('$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update')";
		dbExec($query);
		$id = dblastId();
		info("aggiunto programma svolto id=$id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");
	}
}
?>
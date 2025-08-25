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

	$id = $_POST['corso_id'];
	$data_ora = $_POST['data_ora'];
	$aula = $_POST['aula'];


	$query = "INSERT INTO corso_date(id_corso, data, aula) VALUES('$id', '$data_ora', '$aula')";
	dbExec($query);
	// if ($id > 0) {
	// 	$query = "UPDATE carenze SET id_studente = '$studente_id', id_classe = '$classe_id', id_materia = '$materia_id', id_docente = '0', stato = '0', data_inserimento = '$update', data_validazione = '', data_invio = '' WHERE id = '$id'";
	// 	dbExec($query);
	// 	info("aggiornata materia id=$id id_materia=$materia_id id_utente=$utente_id updated=$update");
	// } else {
	// 	$query = "INSERT INTO carenze(id_studente, id_materia, id_classe, id_docente, id_anno_scolastico, stato, data_inserimento, data_validazione, data_invio) VALUES('$studente_id', '$materia_id', '$classe_id', '0', '$__anno_scolastico_corrente_id', '0', '$update','','')";
	// 	dbExec($query);
	// 	$id = dblastId();
	// 	info("aggiunta carenza	 id=$id id_studente)$studente_id id_classe=$classe_id id_materia=$materia_id id_utente=$utente_id updated=$update");
	// }
	$data = $dblastId();
	echo $data;
}
?>
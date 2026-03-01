<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria');

if(isset($_POST)) {
	$viaggio_id = $_POST['viaggio_id'];
	$protocollo = escapePost('protocollo');
	$tipo_viaggio = $_POST['tipo_viaggio'];
	$data_nomina = $_POST['data_nomina'];
	$data_partenza = $_POST['data_partenza'];
	$data_rientro = $_POST['data_rientro'];
	$docente_incaricato_id = $_POST['docente_incaricato_id'];
	$destinazione = escapePost('destinazione');
	$classe = escapePost('classe');
	$note = escapePost('note');
	$ora_partenza = $_POST['ora_partenza'];
	$ora_rientro = $_POST['ora_rientro'];
	$stato = $_POST['stato'];

	if ($viaggio_id > 0) {
		$query = "UPDATE viaggio SET protocollo = '$protocollo', tipo_viaggio = '$tipo_viaggio', data_nomina = '$data_nomina', data_partenza = '$data_partenza', data_rientro = '$data_rientro', docente_id = '$docente_incaricato_id', classe = '$classe', note = '$note', destinazione = '$destinazione', ora_partenza = '$ora_partenza', ora_rientro = '$ora_rientro', stato = '$stato' WHERE id = '$viaggio_id'";
		dbExec($query);
		info("aggiornato viaggio id=$viaggio_id protocollo=$protocollo tipo_viaggio=$tipo_viaggio data_nomina=$data_nomina data_partenza=$data_partenza data_rientro=$data_rientro docente_id=$docente_incaricato_id classe=$classe note=$note destinazione=$destinazione ora_partenza=$ora_partenza ora_rientro=$ora_rientro stato=$stato");
	} else {
		$query = "INSERT INTO viaggio(protocollo, tipo_viaggio, data_nomina, data_partenza, data_rientro, docente_id, destinazione, classe, note, ora_partenza, ora_rientro, anno_scolastico_id) VALUES('$protocollo', '$tipo_viaggio', '$data_nomina', '$data_partenza', '$data_rientro', '$docente_incaricato_id', '$destinazione', '$classe', '$note', '$ora_partenza', '$ora_rientro', '$__anno_scolastico_corrente_id')";
		dbExec($query);
		$last_id = dblastId();
		info("aggiunto viaggio id=$last_id docente_id=$docente_id dataSostituzione=$dataSostituzione destinazione=$destinazione data_partenza=$data_partenza");
	}
}
?>

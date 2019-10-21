<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
	require_once '../common/checkSession.php';
	require_once '../common/connect.php';

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

	$query = "UPDATE viaggio SET protocollo = '$protocollo', tipo_viaggio = '$tipo_viaggio', data_nomina = '$data_nomina', data_partenza = '$data_partenza', data_rientro = '$data_rientro', docente_id = '$docente_incaricato_id', classe = '$classe', note = '$note', destinazione = '$destinazione', ora_partenza = '$ora_partenza', ora_rientro = '$ora_rientro', stato = '$stato' WHERE id = '$viaggio_id'";
	dbExec($query);
	info("aggiornato viaggio id=$viaggio_id protocollo=$protocollo tipo_viaggio=$tipo_viaggio data_nomina=$data_nomina data_partenza=$data_partenza data_rientro=$data_rientro docente_id=$docente_incaricato_id classe=$classe note=$note destinazione=$destinazione ora_partenza=$ora_partenza ora_rientro=$ora_rientro stato=$stato");
}
?>
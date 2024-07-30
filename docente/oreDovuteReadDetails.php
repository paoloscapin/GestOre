<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

function oreDovuteReadDetails($soloTotale, $docente_id, $table_name) {
	global $__anno_scolastico_corrente_id;

	$query = "SELECT * FROM $table_name WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id;";
	$response = dbGetFirst($query);
	return $response;
}
/*
// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_POST)) {
	if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
		$docente_id = $_POST['docente_id'];
	} else {
		$docente_id = $__docente_id;
	}
	$soloTotale = json_decode($_POST['soloTotale']);

	$table_name = $_POST['table_name'];
	$result = oreDovuteReadDetails($soloTotale, $docente_id, $table_name);
	echo json_encode($result);
}*/
?>

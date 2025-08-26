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

	$data_id = $_POST['data_id'];
	$corso_id = $_POST['corso_id'];
	$jsDate = $_POST['corso_data'];
try {
	$tz = new DateTimeZone('Europe/Rome');
	$date = new DateTime($jsDate, $tz);
    $dbDate = $date->format('Y-m-d H:i:s');

} catch (Exception $e) {
    die("Data non valida: " . $e->getMessage());
}
	$aula = $_POST['corso_aula'];

	if ($data_id > 0) {
		$query = "UPDATE corso_date SET data = '$dbDate', aula = '$aula' WHERE id = '$data_id'";
		dbExec($query);
		info("aggiornato dati della data con id $lastId del corso con id=$corso_id");
	} else {
		$query = "INSERT INTO corso_date(id_corso, data, aula) VALUES('$corso_id', '$dbDate', '$aula')";
		dbExec($query);
		$lastId = dblastId();
		info("aggiunta nuova data con id $lastId al corso con id=$corso_id");
	}
	// Crei un array/oggetto in PHP
	$response = [
		"id" => $corso_id,
		"status" => "ok"
	];

	// Lo trasformi in JSON
	$data = json_encode($response);
	// Se lo devi stampare come risposta AJAX
	header('Content-Type: application/json');
	echo $data;
}

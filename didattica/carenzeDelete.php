<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

// check request
if(isset($_POST['id']) && isset($_POST['id']) != ""){
	$carenza_id = $_POST['id'];

	// se ha un file carenza generato associato devo cancellare il file e toglierlo da carenze_downloads
	$query = "SELECT * FROM carenze_downloads WHERE carenza_id = $carenza_id";
	$row = dbGetFirst($query);

	if ($row) {
		// cancello il file fisico
		$filepath = $row['filepath'];
		if (file_exists($filepath)) {
			unlink($filepath);
			info("cancellato il file fisico della carenza id = $carenza_id");
		}
		$query = "DELETE FROM carenze_downloads WHERE carenza_id = $carenza_id";
		dbExec($query);
		info("cancellati i download della carenza id = $carenza_id");
	}
	// delete record
	$query = "DELETE FROM carenze WHERE id = $carenza_id";
	dbExec($query);
	info("cancellata la carenza id = $carenza_id");

    // Crei un array/oggetto in PHP
	$response = [
		"status" => "ok"
	];
	// Lo trasformi in JSON
	$data = json_encode($response);
	// Se lo devi stampare come risposta AJAX
	header('Content-Type: application/json');
	echo $data;

}
?>
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
	$id = $_POST['id'];
	$corso_id = $_POST['corso_id'];

	// delete record
    $query = "DELETE FROM corso_iscritti WHERE id = '$id'";
    dbExec($query);
    // Crei un array/oggetto in PHP
	$response = [
		"status" => "ok"
	];
    info("Studente iscritto al corso id $corso_id cancellato");
	// Lo trasformi in JSON
	$data = json_encode($response);
	// Se lo devi stampare come risposta AJAX
	header('Content-Type: application/json');
	echo $data;

}
?>
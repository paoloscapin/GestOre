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
	$corso_id = $_POST['id'];

	$query = "DELETE FROM corso_iscritti WHERE id_corso = $corso_id";
	dbExec($query);
	// delete record
	info("cancellati gli studenti iscritti al corso id = $corso_id");
	$query = "DELETE FROM corso_date WHERE id_corso = $corso_id";
	dbExec($query);
	info("cancellate le date del corso id = $corso_id");

	$query = "DELETE FROM corso WHERE id = $corso_id";
	dbExec($query);
	info("cancello il corso id = $corso_id");

    // Crei un array/oggetto in PHP
	$response = [
		"status" => "ok"
	];
    info("Corso con id $corso_id cancellato");
	// Lo trasformi in JSON
	$data = json_encode($response);
	// Se lo devi stampare come risposta AJAX
	header('Content-Type: application/json');
	echo $data;

}
?>
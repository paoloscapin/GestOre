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
ruoloRichiesto('segreteria-didattica');

if (isset($_POST)) {
	$docente_id = $_POST['docente_id'];
	$corso_id = $_POST['id'];
	$materia_id = $_POST['materia_id'];
	$titolo = $_POST['titolo'];
	$carenze = $_POST['carenze'];

	if ($corso_id > 0) {
		$query = "UPDATE corso SET id_docente = '$docente_id', id_materia = '$materia_id', titolo='$titolo', carenze='$carenze' WHERE id = '$corso_id'";
		dbExec($query);
		info("aggiornato dati del corso con id $corso_id");
	} 
	else {
		$query = "INSERT INTO corso (id_materia, id_docente, id_anno_scolastico, titolo, carenze) VALUES('$materia_id', '$docente_id', '$__anno_scolastico_corrente_id','$titolo', '$carenze')";
		dbExec($query);
		$lastId = dblastId();
		info("aggiunto nuovo corso con id $lastId");
	}
	//Crei un array/oggetto in PHP
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

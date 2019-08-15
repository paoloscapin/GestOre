<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// check request
if(isset($_POST['bonus_docente_id']) && isset($_POST['bonus_docente_id']) != "") {
	// include Database connection file
	require_once '../common/connect.php';

	// get docente ID
	$bonus_docente_id = $_POST['bonus_docente_id'];

	// Get Docente Details
	$query = "SELECT * FROM bonus_docente WHERE id = '$bonus_docente_id'";

	$response = dbGetFirst($query);

	echo json_encode($response);
}
else {
	$response['status'] = 200;
	$response['message'] = "Invalid Request!";
}
?>
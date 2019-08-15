<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// check request
if(isset($_POST['id']) && isset($_POST['id']) != "") {
	// include Database connection file 
	require_once '../common/connect.php';

	// get viaggio ID
	$viaggio_id = $_POST['id'];

	// Get Docente Details
	$query = "SELECT * FROM viaggio WHERE id = '$viaggio_id'";

	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}

	$response = array();
	if(mysqli_num_rows($result) > 0) {
		while ($row = mysqli_fetch_assoc($result)) {
			$response = $row;
		}
	}
	else {
		$response['status'] = 200;
		$response['message'] = "Data not found!";
	}
	// display JSON data
	echo json_encode($response);
}
else {
	$response['status'] = 200;
	$response['message'] = "Invalid Request!";
}
?>
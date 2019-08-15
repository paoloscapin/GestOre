<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
	// include Database connection file
	require_once '../common/checkSession.php';
	require_once '../common/connect.php';
	// get values
	$spesa_viaggio_id = $_POST['spesa_viaggio_id'];

	// Update viaggio details
	$query = "UPDATE spesa_viaggio SET validato = true WHERE id = '$spesa_viaggio_id';";
	debug($query);
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}
}
?>
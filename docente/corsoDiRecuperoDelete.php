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

	// get id
	$corso_di_recupero_id = $_POST['id'];

	// delete User
	$query = "DELETE FROM corso_di_recupero WHERE id = '$corso_di_recupero_id'";
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}
}
?>
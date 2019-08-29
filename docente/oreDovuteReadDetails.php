<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$docente_id = $__docente_id;
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
	$docente_id = $_POST['docente_id'];
}
if(isset($_POST['table_name']) && isset($_POST['table_name']) != "") {
	$table_name = $_POST['table_name'];
}

$query = "SELECT * FROM $table_name WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id;";
if (!$result = mysqli_query($con, $query)) {
	exit(mysqli_error($con));
}

$response = array();
if(mysqli_num_rows($result) > 0) {
	if ($row = mysqli_fetch_assoc($result)) {
		$response = $row;
	}
}
else {
	$response['status'] = 200;
	$response['message'] = "Data not found!";
}

echo json_encode($response);
?>

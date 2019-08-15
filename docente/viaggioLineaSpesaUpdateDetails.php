<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
	// include Database connection file
	require_once '../common/connect.php';

	// get values
	$spesa_viaggio_id = $_POST['spesa_viaggio_id'];
	$viaggio_id = $_POST['viaggio_id'];
	$data = $_POST['data'];
	$tipo = $_POST['tipo'];
	$importo = $_POST['importo'];
	$note = $_POST['note'];

	$query = '';
	if ($spesa_viaggio_id > 0) {
		$query = "UPDATE spesa_viaggio SET data = '$data', tipo = '$tipo', importo = '$importo', note = '$note' WHERE id = '$spesa_viaggio_id'";
	} else {
		$query = "INSERT INTO spesa_viaggio(data, tipo, importo, note, viaggio_id) VALUES('$data', '$tipo', '$importo', '$note', $viaggio_id)";
	}
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}
}
?>
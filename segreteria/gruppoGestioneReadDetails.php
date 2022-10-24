<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {

	$gruppo_id = $_POST['id'];

	$query = "SELECT * FROM gruppo WHERE id = '$gruppo_id'";
	$response = dbGetFirst($query);
	echo json_encode($response);
}
?>
<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

require_once __DIR__ . '/checkSession.php';

// check request
if(isset($_POST['id']) && isset($_POST['id']) != "" && isset($_POST['table']) && isset($_POST['table']) != "") {
	$id = $_POST['id'];
	$table = $_POST['table'];

    $query = "SELECT * FROM $table WHERE id = '$id'";
	$result = dbGetFirst($query);
	echo json_encode($result);
}
?>
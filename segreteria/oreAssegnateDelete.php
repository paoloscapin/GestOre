<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// check request
if(isset($_POST['id']) && isset($_POST['id']) != "") {
	require_once '../common/connect.php';

	$id = $_POST['id'];

	$query = "DELETE FROM ore_previste_attivita WHERE id = '$id'";
	dbExec($query);
}
?>
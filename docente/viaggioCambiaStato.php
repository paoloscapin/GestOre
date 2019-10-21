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

	$viaggio_id = $_POST['viaggio_id'];
	$nuovo_stato = $_POST['nuovo_stato'];

	$query = "UPDATE viaggio SET stato = '$nuovo_stato' WHERE id = '$viaggio_id'";
	dbExec($query);
	info("aggiornato viaggio id=$viaggio_id stato=$nuovo_stato");
}
?>
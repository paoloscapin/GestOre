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

	// get viaggio id
	$viaggio_id = $_POST['id'];

	// cancella il viaggio
	dbExec("DELETE FROM viaggio_ore_recuperate WHERE viaggio_id = '$viaggio_id'");
	dbExec("DELETE FROM fuis_viaggio_diaria WHERE id = '$viaggio_id'");
	dbExec("DELETE FROM viaggio WHERE id = '$viaggio_id'");
	info("rimosso viaggio id=$viaggio_id");
}
?>
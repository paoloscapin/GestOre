<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	require_once '../common/checkSession.php';
	require_once '../common/connect.php';

	$id = $_POST['id'];

	$query = "DELETE FROM ore_previste_attivita WHERE id = '$id'";
	debug($query);
	dbExec($query);
	require_once '../docente/oreDovuteAggiornaDocente.php';
	orePrevisteAggiornaDocente($__docente_id);
}
?>
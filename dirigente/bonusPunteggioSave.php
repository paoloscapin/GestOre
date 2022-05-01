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
	$bonus_docente_id = $_POST['bonus_docente_id'];
	$punteggio = $_POST['punteggio'];

	// Update details
	$query = "UPDATE bonus_docente SET approvato = $punteggio, ultimo_controllo = now() WHERE id = '$bonus_docente_id'";
    dbExec($query);
	info("assegnato punteggio $punteggio bonus_docente_id=$bonus_docente_id");
}
?>
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

	// get values
	$lezione_corso_di_recupero_id = $_POST['lezione_corso_di_recupero_id'];
	$aggiungi_ore = $_POST['aggiungi_ore'];
	
	// calcola il segno + o -
	$ore_da_aggiornare = ($aggiungi_ore > 0) ? '+2' : '-2';

	// Update details
	$query = "UPDATE lezione_corso_di_recupero SET firmato = NOT FIRMATO WHERE id = '$lezione_corso_di_recupero_id'";
	dbExec($query);
}
?>
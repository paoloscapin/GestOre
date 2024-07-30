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
	
	// cancella eventuale registro
	$query = "DELETE FROM registro_attivita_clil WHERE ore_fatte_attivita_clil_id = '$id'";
	dbExec($query);
	
	// cancella eventuale commento
	$query = "DELETE FROM ore_fatte_attivita_clil_commento WHERE ore_fatte_attivita_clil_id = '$id'";
	dbExec($query);
	
	// cancella l'attivita'
	$query = "DELETE FROM ore_fatte_attivita_clil WHERE id = '$id'";
	dbExec($query);
	info("rimosso ore_fatte_attivita_clil id=$id");
}
?>
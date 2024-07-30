<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
	require_once '../common/checkSession.php';
	require_once '../common/connect.php';
	
	$attivita_id = $_POST['attivita_id'];
	$docente_id = $_POST['docente_id'];
	$contestata = $_POST['contestata'];
	$commento = mysqli_real_escape_string($con, $_POST['commento']);
	$clilmode = $_POST['clilmode'];
	
	$tabella_ore_fatte_attivita = 'ore_fatte_attivita';
	$tabella_ore_fatte_attivita_commento = 'ore_fatte_attivita_commento';
	$riferimento_id = 'ore_fatte_attivita_id';
	if ($clilmode === 'clil') {
	    $tabella_ore_fatte_attivita = 'ore_fatte_attivita_clil';
	    $tabella_ore_fatte_attivita_commento = 'ore_fatte_attivita_clil_commento';
	    $riferimento_id = 'ore_fatte_attivita_clil_id';
	}
	debug("attivita_id=" . $attivita_id. " contestata=" . $contestata . " commento=" . $commento . " clilmode=" . $clilmode);
	$query = '';
	if ($contestata === "true") {
	    $query = "UPDATE $tabella_ore_fatte_attivita SET contestata = true WHERE id = $attivita_id";
	    dbExec($query);
	    $query = "REPLACE INTO $tabella_ore_fatte_attivita_commento (`commento`, $riferimento_id) VALUES ('$commento', $attivita_id);";
		dbExec($query);
		info("contestata attivita attivita_id=$attivita_id docente_id=$docente_id clil=$clilmode commento=$commento");
	} else {
	    $query = "UPDATE $tabella_ore_fatte_attivita SET contestata = false WHERE id = $attivita_id";
	    dbExec($query);
	    $query = "DELETE FROM $tabella_ore_fatte_attivita_commento WHERE $riferimento_id = $attivita_id;";
	    dbExec($query);
		info("ripristinata attivita attivita_id=$attivita_id docente_id=$docente_id clil=$clilmode");
	}
}
?>
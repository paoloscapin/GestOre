<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-docenti','dirigente');

if(isset($_POST)) {
	$ore_previste_attivita_id = $_POST['ore_previste_attivita_id'];
	$dettaglio = escapePost('dettaglio');
	$ore = $_POST['ore'];

    $query = "UPDATE ore_previste_attivita SET dettaglio = '$dettaglio', ore = '$ore' WHERE id = $ore_previste_attivita_id";
	dbExec($query);

	info("aggiornatà attività ore_previste_attivita_id=$ore_previste_attivita_id dettaglio=$dettaglio ore=$ore");
}
?>
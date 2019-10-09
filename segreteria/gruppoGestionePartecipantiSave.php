<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$gruppo_id = $_POST['gruppo_id'];
	$partecipantiArray = json_decode($_POST['partecipantiArray']);

	// aggiorna i partecipanti
    dbExec("DELETE FROM gruppo_partecipante WHERE gruppo_id=$gruppo_id;");

    foreach($partecipantiArray as $partecipante) {
        $query = "INSERT INTO gruppo_partecipante (gruppo_id, docente_id) VALUES($gruppo_id, $partecipante);";
		dbExec($query);
		info("inserito gruppo_partecipante gruppo_id=$gruppo_id partecipante=$partecipante");
	}
}
?>

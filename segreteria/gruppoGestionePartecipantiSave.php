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
	debug('gruppo_id='.$gruppo_id);

	// legge i partecipanti correnti
	$attualiIdList = array();
	foreach(dbGetAll("SELECT docente_id FROM gruppo_partecipante WHERE gruppo_id=$gruppo_id;") as $attuale) {
		$attualiIdList[] = $attuale['docente_id'];
	}
	$daAggiungere = array_diff($partecipantiArray,$attualiIdList);
	$daTogliere = array_diff($attualiIdList,$partecipantiArray);
	debug('partecipantiArray='.print_r($partecipantiArray, true));
	debug('attualiIdList='.print_r($attualiIdList, true));
	debug('da aggiungere='.print_r($daAggiungere, true));
	debug('da togliere='.print_r($daTogliere, true));

	// toglie tutti quelli che sono da togliere
	foreach($daTogliere as $docente_da_rimuovere_id) {
		dbExec("DELETE FROM gruppo_partecipante WHERE gruppo_id=$gruppo_id AND docente_id=$docente_da_rimuovere_id;");
		info("rimosso gruppo_partecipante gruppo_id=$gruppo_id docente_id=$docente_da_rimuovere_id");
	}

    foreach($daAggiungere as $docente_da_aggiungere_id) {
		// controlla se esiste gia' il docente
		// $id = dbGetValue("SELECT id FROM gruppo_partecipante WHERE gruppo_id=$gruppo_id AND docente_id=$docente_da_aggiungere_id");
		dbExec("INSERT INTO gruppo_partecipante (gruppo_id, docente_id) VALUES($gruppo_id, $docente_da_aggiungere_id);");
		info("inserito gruppo_partecipante gruppo_id=$gruppo_id partecipante=$docente_da_aggiungere_id");

		// inserisce tutte le possibili partecipazioni per gli incontri di questo gruppo, anche passati, se non esistono gia' per questo docente
		// prendi la lista dei gruppi incontro id per questo gruppo id
		// prendi la lista dei gruppi incontro partecipazione per questo gruppo id e docente_id
		// guarda cosa manca ed inseriscilo

		// OPPURE:
		foreach(dbGetAll("SELECT * FROM gruppo_incontro WHERE gruppo_id=$gruppo_id;") as $incontro) {
			// bisogna aggiungere la partecipazione di questo docente se manca, mettendola in quel caso a zero
			if (dbGetValue("SELECT id FROM gruppo_incontro_partecipazione WHERE gruppo_incontro_id=".$incontro['id']." AND docente_id=$docente_da_aggiungere_id;") == null) {
//				debug('devo inserire un partecipazione per incontro del '.$incontro['data'] . " id=" . $incontro['id'] . " docenteid=" . $docente_da_aggiungere_id);
				dbExec("INSERT INTO gruppo_incontro_partecipazione( `ore`, `ha_partecipato`, `gruppo_incontro_id`, `docente_id`) VALUES(0,false," . $incontro['id'] . ", $docente_da_aggiungere_id);");
			}
		}
	}
}
?>

<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

// check request
if(isset($_POST['id']) && isset($_POST['id']) != "" && isset($_POST['table']) && isset($_POST['table']) != "") {
	$id = $_POST['id'];
	$table = $_POST['table'];

    $query = "SELECT * FROM $table WHERE id = '$id'";
	$result = dbGetFirst($query);

	// prepara la lista dei docenti che fanno parte del gruppo se hanno paretcipato
	$docenti_list = array();
	$query = "SELECT
				gruppo_incontro_partecipazione.id AS gruppo_incontro_partecipazione_id,
				gruppo_incontro_partecipazione.ore, gruppo_incontro_partecipazione.ha_partecipato,
				gruppo_incontro.durata AS durata,
				docente.id AS docente_id,
				docente.cognome, docente.nome, docente.email
	
			FROM gruppo_incontro_partecipazione
			INNER JOIN docente ON gruppo_incontro_partecipazione.docente_id = docente.id
			INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
			WHERE gruppo_incontro_partecipazione.gruppo_incontro_id = '$id'
			ORDER BY docente.cognome ASC, docente.nome ASC;";
	foreach(dbGetAll($query) as $docente) {
		$docente_partecipa = array();
		$docente_partecipa['gruppo_incontro_partecipazione_id'] = $docente['gruppo_incontro_partecipazione_id'];
		$docente_partecipa['docente_id'] = $docente['docente_id'];
		$docente_partecipa['cognome_e_nome'] = $docente['cognome'] . ' ' . $docente['nome'];
		$docente_partecipa['ore'] = $docente['ore'];
		$docente_partecipa['ha_partecipato'] = $docente['ha_partecipato'];
		$docenti_list[] = $docente_partecipa;
	}

	$result['partecipanti'] = $docenti_list;
	echo json_encode($result);
}
?>
<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	$lezione_corso_di_recupero_id = $_POST['id'];

	$query = "	SELECT
					studente_partecipa_lezione_corso_di_recupero.id AS studente_partecipa_lezione_corso_di_recupero_id,
					studente_partecipa_lezione_corso_di_recupero.ha_partecipato AS studente_partecipa_lezione_corso_di_recupero_ha_partecipato,
					studente_per_corso_di_recupero.id AS studente_per_corso_di_recupero_id,
					studente_per_corso_di_recupero.cognome AS studente_per_corso_di_recupero_cognome,
					studente_per_corso_di_recupero.nome AS studente_per_corso_di_recupero_nome,
					studente_per_corso_di_recupero.classe AS studente_per_corso_di_recupero_classe,
					lezione_corso_di_recupero.argomento AS lezione_corso_di_recupero_argomento,
					lezione_corso_di_recupero.note AS lezione_corso_di_recupero_note,
					lezione_corso_di_recupero.id AS lezione_corso_di_recupero_uscitoid
				FROM studente_partecipa_lezione_corso_di_recupero
				INNER JOIN studente_per_corso_di_recupero studente_per_corso_di_recupero
				ON studente_partecipa_lezione_corso_di_recupero.studente_per_corso_di_recupero_id = studente_per_corso_di_recupero.id
				INNER JOIN lezione_corso_di_recupero lezione_corso_di_recupero
				ON studente_partecipa_lezione_corso_di_recupero.lezione_corso_di_recupero_id = lezione_corso_di_recupero.id
				WHERE
					studente_partecipa_lezione_corso_di_recupero.lezione_corso_di_recupero_id = ".$lezione_corso_di_recupero_id."
				"
				;
	$partecipaArray = dbGetAll($query);
	echo json_encode($partecipaArray);
}
?>
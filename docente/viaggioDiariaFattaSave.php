<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$id = $_POST['id'];
	$docente_id = $_POST['docente_id'];
	$data_partenza = $_POST['data_partenza'];
	$operatore = $_POST['operatore'];
	$descrizione = escapePost('descrizione');
	$giorni_senza_pernottamento = $_POST['giorni_senza_pernottamento'];
	$giorni_con_pernottamento = $_POST['giorni_con_pernottamento'];
	$ore = $_POST['ore'];
	$commento = escapePost('commento');

	// il dirigente gestisce anche i commenti
	if ($id > 0 && $operatore == 'dirigente') {

		// cerca il commento corrente
		$commento_id = dbGetValue("SELECT id FROM viaggio_diaria_fatta_commento WHERE viaggio_diaria_fatta_id=$id");
		if (!empty(trim($commento, " "))) {
			// se nuovo, registra il commento, altrimenti lo aggiorna
			if ($commento_id == null) {
				// deve ricuperare i valori attuali di giorni senza e con pernottamento
				$attuale = dbGetFirst("SELECT * FROM viaggio_diaria_fatta WHERE id=$id");
				if ($attuale != null) {
					$giorni_senza_pernottamento_originali = $attuale['giorni_senza_pernottamento'];
					$giorni_con_pernottamento_originali = $attuale['giorni_con_pernottamento'];
					$ore_originali = $attuale['ore'];
				}

				dbExec("INSERT INTO viaggio_diaria_fatta_commento (commento, giorni_senza_pernottamento_originali, giorni_con_pernottamento_originali, ore_originali, viaggio_diaria_fatta_id) VALUES('$commento', '$giorni_senza_pernottamento_originali', '$giorni_con_pernottamento_originali', '$ore_originali', $id);");
				info("inserito viaggio_diaria_fatta_commento commento=$commento giorni_senza_pernottamento_originali=$giorni_senza_pernottamento_originali giorni_con_pernottamento_originali=$giorni_con_pernottamento_originali ore_originali=$ore_originali viaggio_diaria_fatta_id=$id");
			} else {
				// aggiorna il commento ma lascia i giorni originali inalterati
				dbExec("UPDATE viaggio_diaria_fatta_commento SET commento = '$commento' WHERE viaggio_diaria_fatta_id=$id;");
				info("aggiornato viaggio_diaria_fatta_commento commento=$commento viaggio_diaria_fatta_id=$id");
			}
		} else {
			// se e' vuoto decide se aggiornarlo o cancellarlo
			if ($commento_id != null) {
				dbExec("UPDATE viaggio_diaria_fatta_commento SET commento = '$commento', giorni_senza_pernottamento_originali = null, giorni_con_pernottamento_originali = null, ore_originali = NULL WHERE viaggio_diaria_fatta_id=$id;");
				info("aggiornato vuoto viaggio_diaria_fatta_commento commento=$commento viaggio_diaria_fatta_id=$id");
			}
		}
	}

	// in ogni caso aggiorna o inserisce il viaggio
	if ($id > 0) {
		dbExec("UPDATE viaggio_diaria_fatta SET data_partenza = '$data_partenza', descrizione = '$descrizione', giorni_senza_pernottamento = '$giorni_senza_pernottamento', giorni_con_pernottamento = '$giorni_con_pernottamento', ore = '$ore' WHERE id = '$id';");
		info("aggiornata viaggio_diaria_fatta id=$id data_partenza=$data_partenza descrizione=$descrizione giorni_senza_pernottamento=$giorni_senza_pernottamento giorni_con_pernottamento=$giorni_con_pernottamento docente_id=$docente_id");
	} else {
		dbExec("INSERT INTO viaggio_diaria_fatta (data_partenza, descrizione, giorni_senza_pernottamento, giorni_con_pernottamento, ore, docente_id, anno_scolastico_id) VALUES('$data_partenza', '$descrizione', '$giorni_senza_pernottamento', '$giorni_con_pernottamento', '$ore', '$docente_id', '$__anno_scolastico_corrente_id');");
		$ore_previste_attivita_id = dblastId();
		info("aggiunto viaggio_diaria_fatta id=$id descrizione=$descrizione data_partenza=$data_partenza giorni_senza_pernottamento=$giorni_senza_pernottamento giorni_con_pernottamento=$giorni_con_pernottamento ore='$ore' docente_id=$docente_id");
	}

	require_once '../docente/oreDovuteAggiornaDocente.php';
	oreFatteAggiornaDocente($__docente_id);
}

?>

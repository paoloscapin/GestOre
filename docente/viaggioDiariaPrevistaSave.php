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
	$operatore = $_POST['operatore'];
	$descrizione = escapePost('descrizione');
	$giorni_senza_pernottamento = $_POST['giorni_senza_pernottamento'];
	$giorni_con_pernottamento = $_POST['giorni_con_pernottamento'];
	$ore = $_POST['ore'];
	$commento = escapePost('commento');

	// il dirigente gestisce anche i commenti
	if ($id > 0 && $operatore == 'dirigente') {

		// cerca il commento corrente
		$commento_id = dbGetValue("SELECT id FROM viaggio_diaria_prevista_commento WHERE viaggio_diaria_prevista_id=$id");
		if (!empty(trim($commento, " "))) {
			// se nuovo, registra il commento, altrimenti lo aggiorna
			if ($commento_id == null) {
				// deve ricuperare i valori attuali di giorni senza e con pernottamento
				$attuale = dbGetFirst("SELECT * FROM viaggio_diaria_prevista WHERE id=$id");
				if ($attuale != null) {
					$giorni_senza_pernottamento_originali = $attuale['giorni_senza_pernottamento'];
					$giorni_con_pernottamento_originali = $attuale['giorni_con_pernottamento'];
				}

				dbExec("INSERT INTO viaggio_diaria_prevista_commento (commento, giorni_senza_pernottamento_originali, giorni_con_pernottamento_originali, viaggio_diaria_prevista_id) VALUES('$commento', '$giorni_senza_pernottamento_originali', '$giorni_con_pernottamento_originali', $id);");
				info("inserito viaggio_diaria_prevista_commento commento=$commento giorni_senza_pernottamento_originali=$giorni_senza_pernottamento_originali giorni_con_pernottamento_originali=$giorni_con_pernottamento_originali viaggio_diaria_prevista_id=$id");
			} else {
				// aggiorna il commento ma lascia i giorni originali inalterati
				dbExec("UPDATE viaggio_diaria_prevista_commento SET commento = '$commento' WHERE viaggio_diaria_prevista_id=$id;");
				info("aggiornato viaggio_diaria_prevista_commento commento=$commento viaggio_diaria_prevista_id=$id");
			}
		} else {
			// se e' vuoto decide se aggiornarlo o cancellarlo
			if ($commento_id != null) {
				dbExec("UPDATE viaggio_diaria_prevista_commento SET commento = '$commento', giorni_senza_pernottamento_originali = null, giorni_con_pernottamento_originali = null WHERE viaggio_diaria_prevista_id=$id;");
				info("aggiornato vuoto viaggio_diaria_prevista_commento commento=$commento viaggio_diaria_prevista_id=$id");
			}
		}
	}

	// in ogni caso aggiorna o inserisce il viaggio
	if ($id > 0) {
		dbExec("UPDATE viaggio_diaria_prevista SET descrizione = '$descrizione', giorni_senza_pernottamento = '$giorni_senza_pernottamento', giorni_con_pernottamento	 = '$giorni_con_pernottamento', ore	 = '$ore' WHERE id = '$id';");
		info("aggiornata viaggio_diaria_prevista id=$id descrizione=$descrizione giorni_senza_pernottamento=$giorni_senza_pernottamento giorni_con_pernottamento=$giorni_con_pernottamento ore=$ore docente_id=$docente_id");
	} else {
		dbExec("INSERT INTO viaggio_diaria_prevista (descrizione, giorni_senza_pernottamento, giorni_con_pernottamento, ore, docente_id, anno_scolastico_id) VALUES('$descrizione', '$giorni_senza_pernottamento', '$giorni_con_pernottamento', '$ore', '$docente_id', '$__anno_scolastico_corrente_id');");
		$ore_previste_attivita_id = dblastId();
		info("aggiunto viaggio_diaria_prevista id=$id descrizione=$descrizione giorni_senza_pernottamento=$giorni_senza_pernottamento giorni_con_pernottamento=$giorni_con_pernottamento ore=$ore docente_id=$docente_id");
	}
}

?>

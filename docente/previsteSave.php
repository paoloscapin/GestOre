<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$docente_id = $_POST['docente_id'];
	$ore_previste_attivita_id = $_POST['ore_previste_attivita_id'];
	$update_tipo_attivita_id = $_POST['update_tipo_attivita_id'];
	$update_ore = $_POST['update_ore'];
	$update_dettaglio = mysqli_real_escape_string($con, $_POST['update_dettaglio']);
	$update_commento = mysqli_real_escape_string($con, $_POST['update_commento']);
	$operatore = $_POST['operatore'];

	$query = '';
	if ($ore_previste_attivita_id > 0) {
		dbExec("UPDATE ore_previste_attivita SET dettaglio = '$update_dettaglio', ore = '$update_ore', ore_previste_tipo_attivita_id	 = '$update_tipo_attivita_id' WHERE id = '$ore_previste_attivita_id';");
		info("aggiornata ore_previste_attivita id=$ore_previste_attivita_id dettaglio=$update_dettaglio ore=$update_ore ore_previste_tipo_attivita_id=$update_tipo_attivita_id docente_id=$docente_id");
	} else {
		dbExec("INSERT INTO ore_previste_attivita (dettaglio, ore, ore_previste_tipo_attivita_id, docente_id, anno_scolastico_id) VALUES('$update_dettaglio', '$update_ore', '$update_tipo_attivita_id', '$docente_id', '$__anno_scolastico_corrente_id');");
		$ore_previste_attivita_id = dblastId();
		info("aggiunto ore_previste_attivita id=$ore_previste_attivita_id dettaglio=$update_dettaglio ore=$update_ore ore_previste_tipo_attivita_id=$update_tipo_attivita_id docente_id=$docente_id");
	}

	// il dirigente gestisce anche i commenti
	if ($operatore == 'dirigente') {

		// cerca il commento corrente
		$commento_id = dbGetValue("SELECT id FROM ore_previste_attivita_commento WHERE ore_previste_attivita_id=$ore_previste_attivita_id");
		if (!empty(trim($update_commento, " "))) {
			// se nuovo, registra il commento, altrimenti lo aggiorna
			if ($commento_id == null) {
				dbExec("INSERT INTO ore_previste_attivita_commento (commento, ore_previste_attivita_id) VALUES('$update_commento', $ore_previste_attivita_id);");
				info("inserito ore_previste_attivita_commento commento=$update_commento ore_previste_attivita_id=$ore_previste_attivita_id");
			} else {
				dbExec("UPDATE ore_previste_attivita_commento SET commento = '$update_commento' WHERE ore_previste_attivita_id=$ore_previste_attivita_id;");
				info("aggiornato ore_previste_attivita_commento commento=$update_commento ore_previste_attivita_id=$ore_previste_attivita_id");
			}
		} else {
			// se e' vuoto decide se aggiornarlo o cancellarlo
			if ($commento_id != null) {
				dbExec("UPDATE ore_previste_attivita_commento SET commento = '$update_commento' WHERE ore_previste_attivita_id=$ore_previste_attivita_id;");
				info("aggiornato vuoto ore_previste_attivita_commento commento=$update_commento ore_previste_attivita_id=$ore_previste_attivita_id");
			}
		}
	}

	require_once '../docente/oreDovuteAggiornaDocente.php';
	orePrevisteAggiornaDocente($__docente_id);
}

?>

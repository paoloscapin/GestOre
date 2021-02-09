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
	$ore_fatte_attivita_clil_id = $_POST['attivita_id'];
	$con_studenti = $_POST['con_studenti'];
	$ore = $_POST['ore'];
	$dettaglio = mysqli_real_escape_string($con, $_POST['dettaglio']);
	$ora_inizio = $_POST['ora_inizio'];
	$data = $_POST['data'];
	$commento = mysqli_real_escape_string($con, $_POST['commento']);
	$operatore = $_POST['operatore'];

	// il dirigente gestisce anche i commenti
	if ($ore_fatte_attivita_clil_id > 0 && $operatore == 'dirigente') {

		// cerca il commento corrente
		$commento_id = dbGetValue("SELECT id FROM ore_fatte_attivita_clil_commento WHERE ore_fatte_attivita_clil_id=$ore_fatte_attivita_clil_id");
		if (!empty(trim($commento, " "))) {
			// se nuovo, registra il commento, altrimenti lo aggiorna
			if ($commento_id == null) {
				// deve ricuperare i valori attuali di ore
				$attuale = dbGetFirst("SELECT * FROM ore_fatte_attivita_clil WHERE id=$ore_fatte_attivita_clil_id");
				if ($attuale != null) {
					$ore_originali = $attuale['ore'];
				} else {
					$ore_originali = 0;
				}

				dbExec("INSERT INTO ore_fatte_attivita_clil_commento (commento, ore_originali, ore_fatte_attivita_clil_id) VALUES('$commento', '$ore_originali', $ore_fatte_attivita_clil_id);");
				info("inserito ore_fatte_attivita_clil_commento commento=$commento ore_originali=$ore_originali ore_fatte_attivita_clil_id=$ore_fatte_attivita_clil_id");
			} else {
				// aggiorna il commento ma lascia le ore originali inalterati
				dbExec("UPDATE ore_fatte_attivita_clil_commento SET commento = '$commento' WHERE ore_fatte_attivita_clil_id=$ore_fatte_attivita_clil_id;");
				info("aggiornato ore_fatte_attivita_clil_commento commento=$commento ore_fatte_attivita_clil_id=$ore_fatte_attivita_clil_id");
			}
		} else {
			// se e' vuoto decide se aggiornarlo o cancellarlo: in realta' mette le ore a null e in quel modo il commento non viene piu' considerato
			if ($commento_id != null) {
				dbExec("UPDATE ore_fatte_attivita_clil_commento SET commento = '$commento', ore_originali = null WHERE ore_fatte_attivita_clil_id=$ore_fatte_attivita_clil_id;");
				info("aggiornato vuoto ore_fatte_attivita_clil_commento commento=$commento ore_fatte_attivita_clil_id=$ore_fatte_attivita_clil_id");
			}
		}
	}

	// in ogni caso aggiorna o inserisce l'attivita'
	if ($ore_fatte_attivita_clil_id > 0) {
		dbExec("UPDATE ore_fatte_attivita_clil SET dettaglio = '$dettaglio', ore = '$ore', con_studenti = $con_studenti, ora_inizio = '$ora_inizio', data = '$data' WHERE id = '$ore_fatte_attivita_clil_id';");
		info("aggiornata ore_fatte_attivita_clil id=$ore_fatte_attivita_clil_id dettaglio=$dettaglio ore=$ore con_studenti=$con_studenti docente_id=$docente_id");
	} else {
		dbExec("INSERT INTO ore_fatte_attivita_clil (dettaglio, ore, ora_inizio, data, con_studenti, docente_id, anno_scolastico_id) VALUES('$dettaglio', '$ore', '$ora_inizio', '$data', $con_studenti, '$__docente_id', '$__anno_scolastico_corrente_id');");
		$ore_fatte_attivita_clil_id = dblastId();
		// se si tratta del dirigente, inaserisce un eventuale commento
		if ($operatore == 'dirigente') {
			// se lo inserisce il dirigente, le ore originali non c'erano
			$ore_originali = 0;
			// se il commento risulta vuoto ne mette uno di default
			if (empty(trim($commento, " "))) {
				$commento = "inserito da dirigente";
			}
			dbExec("INSERT INTO ore_fatte_attivita_clil_commento (commento, ore_originali, ore_fatte_attivita_clil_id) VALUES('$commento', '$ore_originali', $ore_fatte_attivita_clil_id);");
			info("inserito ore_fatte_attivita_clil_commento commento=$commento ore_originali=$ore_originali ore_fatte_attivita_clil_id=$ore_fatte_attivita_clil_id");
		}
		info("aggiunto ore_fatte_attivita_clil id=$ore_fatte_attivita_clil_id dettaglio=$dettaglio ore=$ore con_studenti=$con_studenti docente_id=$docente_id");
	}

	require_once '../docente/oreDovuteAggiornaDocente.php';
	oreFatteAggiornaDocente($__docente_id);
}

?>

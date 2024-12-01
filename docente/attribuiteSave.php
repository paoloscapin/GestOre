<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');

if(isset($_POST)) {
	$attribuite_attivita_id = $_POST['attribuite_attivita_id'];
	$tipo_attivita_id = $_POST['tipo_attivita_id'];
	$ore = $_POST['ore'];
	$dettaglio = escapePost('dettaglio');
	$commento = escapePost('commento');
	$docente_id = $_POST['docente_id'];

	// controlla se deve aggiornare: in quel caso sistema il commento (lo fa subito in modo da poter leggere le ore originali)
    if ($attribuite_attivita_id > 0) {

		// se esisteva gia', devo controllare se esiste un commento associato
		$commento_id = dbGetValue("SELECT id FROM ore_previste_attivita_commento WHERE ore_previste_attivita_id=$attribuite_attivita_id");

		// controlla che il commenti inserito dalla dirigente non sia vuoto
		if (!empty(trim($commento, " "))) {

			// se nuovo, registra il commento, altrimenti lo aggiorna
			if ($commento_id == null) {

				// per inserirlo nuovo, deve ricuperare i valori attuali di ore originali o zero se non c'erano prima
				$attuale = dbGetFirst("SELECT * FROM ore_previste_attivita WHERE id=$attribuite_attivita_id");
				$ore_originali = ($attuale != null)? $attuale['ore'] : 0;

				dbExec("INSERT INTO ore_previste_attivita_commento (commento, ore_originali, ore_previste_attivita_id) VALUES('$commento', '$ore_originali', $attribuite_attivita_id);");
				info("inserito ore_previste_attivita_commento commento=$commento ore_originali=$ore_originali ore_previste_attivita_id=$attribuite_attivita_id");
			} else {

				// se esisteva, aggiorna il commento ma lascia le ore originali inalterate
				dbExec("UPDATE ore_previste_attivita_commento SET commento = '$commento' WHERE ore_previste_attivita_id=$attribuite_attivita_id;");
				info("aggiornato ore_previste_attivita_commento commento=$commento ore_previste_attivita_id=$attribuite_attivita_id");
			}
		} else {
			// se il commento inserito ora dalla dirigente e' vuoto, decide se aggiornarlo o cancellarlo: in realta' mette le ore a null e in quel modo il commento non viene piu' considerato
			if ($commento_id != null) {
				dbExec("UPDATE ore_previste_attivita_commento SET commento = '$commento', ore_originali = null WHERE ore_previste_attivita_id=$attribuite_attivita_id;");
				info("aggiornato vuoto ore_previste_attivita_commento commento=$commento ore_previste_attivita_id=$attribuite_attivita_id");
			}
		}
	}

	// in ogni caso, finito con il commento, aggiorna o inserisce l'attivita' (sopra non ci e' passato se non c'era gia' l'attivita')
	if ($attribuite_attivita_id > 0) {

		// in questo caso il commento e' gia' stato aggiornato per cui basta aggiornare i valori dell'attivita'
		dbExec("UPDATE ore_previste_attivita SET ore_previste_tipo_attivita_id = '$tipo_attivita_id', dettaglio = '$dettaglio', ore = '$ore' WHERE id = '$attribuite_attivita_id'");
		info("aggiornata ore_previste_attivita id=$attribuite_attivita_id dettaglio=$dettaglio ore=$ore ore_previste_tipo_attivita_id=$tipo_attivita_id docente_id=$docente_id");
	} else {

		// se arriva qui, deve inserire tutto, attivita' e valori (ma questo significa che non esiste ancora il commento e deve creare anche quello)
		dbExec("INSERT INTO ore_previste_attivita (dettaglio, ore, ore_previste_tipo_attivita_id, docente_id, anno_scolastico_id) VALUES('$dettaglio', '$ore', '$tipo_attivita_id', '$docente_id', '$__anno_scolastico_corrente_id');");
		$attribuite_attivita_id = dblastId();
		info("inserito ore_previste_attivita id=$attribuite_attivita_id dettaglio=$dettaglio ore=$ore ore_previste_tipo_attivita_id=$tipo_attivita_id docente_id=$docente_id");

		// se lo inserisce adesso il dirigente, le ore originali non c'erano
		$ore_originali = 0;
		// se il commento risulta vuoto ne mette uno di default
		if (empty(trim($commento, " "))) {
			$commento = "inserito da dirigente";
		}
		dbExec("INSERT INTO ore_previste_attivita_commento (commento, ore_originali, ore_previste_attivita_id) VALUES('$commento', '$ore_originali', $attribuite_attivita_id);");
		info("inserito ore_previste_attivita_commento commento=$commento ore_originali=$ore_originali ore_previste_attivita_id=$attribuite_attivita_id");
	}
}
?>
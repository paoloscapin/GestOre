<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';


function oreFatteReadSostituzioni($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

	// valori da restituire come totali
	$sostituzioniOre = 0;
	$dataSostituzioni = '';

	// controlla se deve restituire solo il totale o anche la tabella html
	if($soloTotale) {
		$sostituzioniOre = dbGetValue("SELECT count(id) FROM sostituzione_docente WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id;");

		$result = compact('dataSostituzioni', 'sostituzioniOre');
		return $result;
	}
	
	// Design initial table header
	$dataSostituzioni .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
							<thead><tr>
								<th class="col-md-11 text-left">Data</th>
								<th class="col-md-2 text-center">Ore</th>
							</tr></thead><tbody>';
	
	$query = "SELECT data, ora FROM sostituzione_docente WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id ORDER BY data DESC;";
	foreach(dbGetAll($query) as $row) {
		$dataSostituzioni .= '<tr>
			<td>'.strftime("%d/%m/%Y", strtotime($row['data'])).'</td>
			<td class="text-center">'.$row['ora'].'</td>
			';
		
		// aggiorna il totale da restituire (viene segnata un'ora per volta, quindi sempre 1)
		$sostituzioniOre += 1;
	}
	
	$dataSostituzioni .= '</tbody>
	<tfoot><tr><td class="text-right"><strong>Totale:</strong></td><td class="text-center"><strong>'.$sostituzioniOre.'</strong></td></tr></tfoot>
	</table></div>';
	
	$result = compact('dataSostituzioni', 'sostituzioniOre');
	return $result;
}
/*
// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_GET)) {
	if(isset($_GET['docente_id']) && isset($_GET['docente_id']) != "") {
		$docente_id = $_GET['docente_id'];
	} else {
		$docente_id = $__docente_id;
	}
	$soloTotale = json_decode($_GET['soloTotale']);

	if(isset($_GET['operatore']) && $_GET['operatore'] == 'dirigente') {
		// se vuoi fare il dirigente, devi essere dirigente
		ruoloRichiesto('dirigente');
		// agisci quindi come dirigente
		$operatore = 'dirigente';
		// il dirigente può sempre fare modifiche
		$modificabile = true;
		// devi leggere il timestamp dell'ultimo controllo effettuato
		$ultimo_controllo = $_PO_GETST['ultimo_controllo'];
	} else {
		$operatore = 'docente';
		$ultimo_controllo = '';
		$modificabile = $__config->getOre_fatte_aperto();
	}

	$result = oreFatteReadSostituzioni($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	echo json_encode($result);
}*/
?>
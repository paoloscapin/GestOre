<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

function oreFatteReadViaggi($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

	// valori da restituire come totali
	$viaggiOre = 0;
	$dataViaggi = '';
	
	// controlla se deve restituire solo il totale o anche la tabella html
	if(isset($_POST['solo_totale']) && $_POST['solo_totale'] == "true") {
		$viaggiOre = dbGetValue("SELECT SUM(ore) FROM viaggio_ore_recuperate INNER JOIN viaggio viaggio ON viaggio_ore_recuperate.viaggio_id = viaggio.id WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id AND viaggio.docente_id = $docente_id;");

		$result = compact('dataViaggi', 'viaggiOre');
		return $result;
	}
	
	// Design initial table header
	$dataViaggi .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
							<thead><tr>
								<th class="col-md-10 text-left">Destinazione</th>
								<th class="col-md-1 text-center">Data</th>
								<th class="col-md-1 text-center">Ore</th>
							</tr></thead><tbody>';
	
	$query = "	SELECT
						viaggio_ore_recuperate.id AS viaggio_ore_recuperate_id,
						viaggio_ore_recuperate.ore AS viaggio_ore_recuperate_ore,
						viaggio.destinazione AS viaggio_destinazione,
						viaggio.data_partenza AS viaggio_data_partenza
	
					FROM viaggio_ore_recuperate viaggio_ore_recuperate
					INNER JOIN viaggio viaggio
					ON viaggio_ore_recuperate.viaggio_id = viaggio.id
					WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
					AND viaggio.docente_id = $docente_id
					ORDER BY
						viaggio.data_partenza DESC
					"
					;
	
	foreach(dbGetAll($query) as $viaggio) {
		$ore_con_minuti = oreToDisplay($viaggio['viaggio_ore_recuperate_ore']);
		$dataViaggi .= '<tr>
			<td>'.$viaggio['viaggio_destinazione'].'</td>
			<td class="text-center">'.strftime("%d/%m/%Y", strtotime($viaggio['viaggio_data_partenza'])).'</td>
			<td class="text-center">'.$ore_con_minuti.'</td>
			</tr>';
	
		// aggiorna il totale da restituire
		$viaggiOre += $viaggio['viaggio_ore_recuperate_ore'];
	}
	
	$dataViaggi .= '</tbody></table></div>';

	$result = compact('dataViaggi', 'viaggiOre');
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

	$result = oreFatteReadViaggi($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	echo json_encode($result);
}*/
?>

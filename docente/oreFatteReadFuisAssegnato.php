<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

function formatNoZeroLocal($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function oreFatteReadFuisAssegnato($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

	// valori da restituire come totali
	$importoFuisAssegnato = 0;
	$dataFuisAssegnato = '';

	// controlla se deve restituire solo il totale o anche la tabella html
	if($soloTotale) {
	    $importoFuisAssegnato = dbGetValue("SELECT COALESCE(SUM(importo), 0) FROM fuis_assegnato WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

		$result = compact('dataFuisAssegnato', 'importoFuisAssegnato');
		return $result;
	}

	// Design initial table header
	$dataFuisAssegnato = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
							<thead><tr>
								<th class="col-md-10 text-left">Tipo</th>
								<th class="col-md-2 text-center">Importo</th>
							</tr></thead><tbody>';
	
	$assegnatoList = dbGetAll("SELECT * FROM fuis_assegnato INNER JOIN fuis_assegnato_tipo ON fuis_assegnato.fuis_assegnato_tipo_id=fuis_assegnato_tipo.id WHERE fuis_assegnato.docente_id = $docente_id AND fuis_assegnato.anno_scolastico_id = $__anno_scolastico_corrente_id;");

	if (!empty($assegnatoList)) {
		foreach($assegnatoList as $assegnato) {
			$dataFuisAssegnato .= '<tr><td>'.$assegnato['nome'].'</td><td class="text-right funzionale">'.$assegnato['importo'].' €&ensp;</td></tr>';
			$importoFuisAssegnato = $importoFuisAssegnato + $assegnato['importo'];
		}
	}
	$dataFuisAssegnato .= '</tbody><tfoot>';
	$dataFuisAssegnato .='<tr><td colspan="1" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . formatNoZeroLocal($importoFuisAssegnato) . '</strong></td></tr>';
	$dataFuisAssegnato .='</tfoot></table></div>';

	$result = compact('dataFuisAssegnato', 'importoFuisAssegnato');
	return $result;
}
?>
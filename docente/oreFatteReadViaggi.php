<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

function formatNoZeroDiariaViaggi($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function oreFatteReadViaggi($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

	// valori da restituire come totali
	$viaggiOre = 0;
	$diariaImporto = 0;
	$dataViaggi = '';
	
	// controlla se deve restituire solo il totale o anche la tabella html
	if(isset($_POST['solo_totale']) && $_POST['solo_totale'] == "true") {
		$viaggiOre = dbGetValue("SELECT SUM(ore) FROM viaggio_ore_recuperate INNER JOIN viaggio viaggio ON viaggio_ore_recuperate.viaggio_id = viaggio.id WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id AND viaggio.docente_id = $docente_id;");

		// aggiunge un eventuale importo di diaria proveniente dalla vecchia gestione nella tabella fuis_viaggio_diaria
		$diariaImporto = dbGetValue ("SELECT COALESCE(SUM(importo) , 0) AS importo FROM fuis_viaggio_diaria INNER JOIN viaggio ON viaggio.id = fuis_viaggio_diaria.viaggio_id WHERE viaggio.docente_id = $docente_id AND viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id;") ;

		$result = compact('dataViaggi', 'viaggiOre', 'diariaImporto');
		return $result;
	}
	
	// Design initial table header
	$dataViaggi .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
							<thead><tr>
								<th class="col-md-1 text-left">Data</th>
								<th class="col-md-2 text-left">Tipo</th>
								<th class="col-md-5 text-left">Destinazione</th>
								<th class="col-md-2 text-center">Classe</th>
								<th class="col-md-1 text-center">Ore</th>
								<th class="col-md-1 text-center">Diaria</th>
							</tr></thead><tbody>';

	// scorro tutti i viaggi del docente perche' potrebbero esserci ore oppure diaria
	foreach(dbGetAll("SELECT * FROM viaggio WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id AND viaggio.docente_id = $docente_id ORDER BY viaggio.data_partenza DESC;") as $viaggio) {
		$viaggio_id = $viaggio['id'];

		// cerco le ore e la diaria
		$ore = dbGetValue("SELECT COALESCE(ore, 0) FROM viaggio_ore_recuperate WHERE viaggio_id = $viaggio_id");
		$diaria = dbGetValue("SELECT COALESCE(importo, 0) FROM fuis_viaggio_diaria WHERE viaggio_id = $viaggio_id");

		// se non c'e' niente continuo ai prossimi viaggi
		if ($ore == 0 && $diaria == 0) {
			continue;
		}

		$oreString = oreToDisplay($ore);
		$diariaString =  formatNoZeroDiariaViaggi($diaria);
		$rimborsato = $viaggio['rimborsato'];
		$diariaIcon = ($diaria == 0)? ' ' : ($rimborsato == 0)? '<span class="glyphicon glyphicon-option-horizontal text-warning data-toggle="tooltip" data-placement="left" data-html="true" title="da liquidare""></span>' : '<span class="glyphicon glyphicon-saved text-success data-toggle="tooltip" data-placement="left" data-html="true" title="liquidato""></span>';

		$dataViaggi .= '<tr>
			<td>'.strftime("%d/%m/%Y", strtotime($viaggio['data_partenza'])).'</td>
			<td>'.$viaggio['tipo_viaggio'].'</td>
			<td>'.$viaggio['destinazione'].'</td>
			<td>'.$viaggio['classe'].'</td>
			<td class="text-center">'.$oreString.'</td>
			<td>'.$diariaString.'&nbsp;&nbsp;'.$diariaIcon.'</td>
			</tr>';
	
		// aggiorna il totale da restituire
		$viaggiOre += $ore;
		$diariaImporto += $diaria;
	}

	$dataViaggi .= '</tbody><tfoot>';
	$dataViaggi .='<tr><td colspan="4" class="text-right"><strong>Totale:</strong></td><td class="text-center funzionale"><strong>' . oreToDisplay($viaggiOre) . '</strong></td><td class="text-center funzionale"><strong>' . formatNoZeroDiaria($diariaImporto) . '</strong></td></tr>';
	$dataViaggi .='</tfoot></table></div>';

	$result = compact('dataViaggi', 'viaggiOre', 'diariaImporto');
	return $result;
}
?>

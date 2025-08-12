<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

function oreFatteReadSportelli($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

	// valori da restituire come totali
	$sportelliOre = 0;
	$sportelliOreClil = 0;
	$sportelliOreOrientamento = 0;
	$dataSportelli = '';

	if($soloTotale) {
		$query = "SELECT sportello.*, ( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.presente) AS numero_presenti,
					( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.iscritto) AS numero_iscritti
					FROM sportello WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id AND sportello.docente_id = $docente_id AND sportello.firmato = true AND NOT sportello.cancellato;";
		foreach(dbGetAll($query) as $sportello) {
			if ($sportello['numero_presenti'] > 0) {
				$oreSportello = $sportello['numero_ore'];
			} else if ($sportello['numero_iscritti'] > 0 && $sportello['online'] == 0) {
				$oreSportello = 1;
			} else {
				$oreSportello = 0;
			}
		
			if ($sportello['clil']) {
				$sportelliOreClil += $oreSportello;
			} elseif ($sportello['orientamento']) {
				$sportelliOreOrientamento += $oreSportello;
			} else {
				$sportelliOre += $oreSportello;
			}
		}

		$result = compact('dataSportelli', 'sportelliOre', 'sportelliOreClil', 'sportelliOreOrientamento');
		return $result;
	}

	// nel sommario non vogliamo le ultime due colonne e nome e dettaglio insieme
	$dataSportelli .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><thead><tr>
	<th class="col-md-2 text-left">Categoria</th>
	<th class="col-md-2 text-left">Materia</th>
	<th class="col-md-2 text-left">Note</th>
	<th class="col-md-2 text-center">Studenti</th>
	<th class="col-md-1 text-center">Data</th>
	<th class="col-md-1 text-center">Ore</th>
	<th class="col-md-1 text-center"></th>';

	$dataSportelli .= '</tr></thead><tbody>';

	$query = "	SELECT sportello.id AS sportello_id, sportello.clil AS sportello_clil, sportello.orientamento AS sportello_orientamento, sportello.*, materia.*,
					( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.presente) AS numero_presenti,
					( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.iscritto) AS numero_iscritti
					FROM sportello sportello INNER JOIN materia materia ON sportello.materia_id = materia.id
					WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id AND sportello.docente_id = $docente_id AND sportello.firmato = true AND sportello.cancellato = false
					ORDER BY sportello.orientamento ASC, sportello.clil ASC, sportello.categoria, sportello.data DESC ;" ;

	$lastWasClil = false;
	$lastWasOrientamento = false;
	foreach(dbGetAll($query) as $sportello) {
		// controlla se iniziano qui gli sportelli clil
		if ($sportello['sportello_clil'] && ! $lastWasClil) {
			$dataSportelli .= '<tr><th colspan="7" class="col-md-12 text-center btn-lightblue4" style="padding: 0px">Clil</th></tr>';
			$lastWasClil = true;
		}

		// controlla se iniziano qui gli sportelli orientamento
		if ($sportello['sportello_orientamento'] && ! $lastWasOrientamento) {
			$dataSportelli .= '<tr><th colspan="7" class="col-md-12 text-center btn-beige" style="padding: 0px">Orientamento</th></tr>';
			$lastWasOrientamento = true;
		}

		// marca se online
		$onlineMarker = (empty($sportello['online'])) ? '' : '<span class=\'label label-danger\'>online</span>';

		$ore_con_minuti = oreToDisplay($sportello['numero_ore']);
		$dataSportelli .= '<tr><td>'.$sportello['categoria'].'</td>';
		$dataSportelli .= '<td>'.$sportello['nome'].'</td>';
		$dataSportelli .= '<td>'.$onlineMarker.$sportello['note'].'</td>';
		$dataSportelli .= '<td class="text-center">'.$sportello['numero_presenti'].' di '.$sportello['numero_iscritti'].' iscritti</td>';
		$dataSportelli .= '<td class="text-center">'.strftime("%d/%m/%Y", strtotime($sportello['data'])).'</td>';
		$dataSportelli .= '<td class="text-center">'.$ore_con_minuti.'</td>';
		if ($operatore == 'dirigente') {
			$dataSportelli .='<td class="text-center"><button onclick="sportelloGetDetails('.$sportello['sportello_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></button></td>';
		} else {
			$dataSportelli .='<td></td>';
		}
		$dataSportelli .='</tr>';

		// le ore vengono registrate solo se ci sono studenti, altrimenti viene riconosciuta una sola ora se c'erano iscritti
		if ($sportello['numero_presenti'] > 0) {
			$oreSportello = $sportello['numero_ore'];
		} else if ($sportello['numero_iscritti'] > 0 && $sportello['online'] == 0) {
			$oreSportello = 1;
		} else {
			$oreSportello = 0;
		}

		if ($sportello['sportello_clil']) {
			$sportelliOreClil += $oreSportello;
		} elseif ($sportello['sportello_orientamento']) {
			$sportelliOreOrientamento += $oreSportello;
		} else {
			$sportelliOre += $oreSportello;
		}
	}

	$dataSportelli .= '</tbody></table></div>';

	$result = compact('dataSportelli', 'sportelliOre', 'sportelliOreClil', 'sportelliOreOrientamento');
	return $result;
}
?>
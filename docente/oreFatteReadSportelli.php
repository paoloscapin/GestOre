<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

$operatoreDirigente = false;

$docente_id = $__docente_id;
if (haRuolo('dirigente')) {
	$operatoreDirigente = true;
	debug('ruolo=dirigente, __docente_id='.$__docente_id);
}

// valori da restituire come totali
$sportelliOre = 0;
$sportelliOreClil = 0;
$sportelliOreOrientamento = 0;
$data = '';

// nel sommario non vogliamo le ultime due colonne e nome e dettaglio insieme
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><thead><tr>
<th class="col-md-2 text-left">Categoria</th>
<th class="col-md-2 text-left">Materia</th>
<th class="col-md-3 text-left">Note</th>
<th class="col-md-1 text-center">Studenti</th>
<th class="col-md-1 text-center">Data</th>
<th class="col-md-1 text-center">Ore</th>';
if ($operatoreDirigente) {
	$data .= '<th class="col-md-1 text-center"></th>';
}

$data .= '</tr></thead><tbody>';

$query = "	SELECT sportello.id AS sportello_id, sportello.clil AS sportello_clil, sportello.orientamento AS sportello_orientamento, sportello.*, materia.*,
				( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.presente) AS numero_presenti,
				( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.iscritto) AS numero_iscritti
				FROM sportello sportello INNER JOIN materia materia ON sportello.materia_id = materia.id
				WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id AND sportello.docente_id = $docente_id AND sportello.firmato = true AND sportello.cancellato = false
				ORDER BY sportello.orientamento ASC, sportello.clil ASC, sportello.categoria, sportello.data DESC ;" ;

foreach(dbGetAll($query) as $sportello) {
	
	$onlineMarker = (empty($sportello['online'])) ? '' : '<span class=\'label label-danger\'>online</span>';
	$clilMarker = '';
	if ($sportello['sportello_clil']) {
		$clilMarker = '<span class="label label-danger">clil</span>';
	}
	$orientamentoMarker = '';
	if ($sportello['sportello_orientamento']) {
		$orientamentoMarker = '<span class="label label-warning">orientamento</span>';
	}

	$ore_con_minuti = oreToDisplay($sportello['numero_ore']);
	$data .= '<tr><td>'.$clilMarker.$orientamentoMarker.$sportello['categoria'].'</td>';
	$data .= '<td>'.$sportello['nome'].'</td>';
	$data .= '<td>'.$onlineMarker.$sportello['note'].'</td>';
	$data .= '<td class="text-center">'.$sportello['numero_presenti'].' di '.$sportello['numero_iscritti'].' iscritti</td>';
	$data .= '<td class="text-center">'.strftime("%d/%m/%Y", strtotime($sportello['data'])).'</td>';
	$data .= '<td class="text-center">'.$ore_con_minuti.'</td>';
	if ($operatoreDirigente) {
		$data .='<td class="text-center"><button onclick="sportelloGetDetails('.$sportello['sportello_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></button></td>';
	}
	$data .='</tr>';

	// le ore vengono registrate solo se ci sono studenti, altrimenti viene riconosciuta una sola ora se c'erano iscritti
	if ($sportello['numero_presenti'] > 0) {
		$oreSportello = $sportello['numero_ore'];
	} else if ($sportello['numero_iscritti'] > 0) {
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

$data .= '</tbody></table></div>';

$response = compact('data', 'sportelliOre', 'sportelliOreClil', 'sportelliOreOrientamento');
echo json_encode($response);
?>
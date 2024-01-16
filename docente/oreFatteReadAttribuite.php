<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

function writeOre($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return oreToDisplay($attuali);
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	return '<s style="text-decoration-style: double;"> '.oreToDisplay($originali).' </s>&ensp;<span class="text-danger"><strong> '.oreToDisplay($attuali).' </strong></span>';
}

$operatoreDirigente = false;

$docente_id = $__docente_id;
if (haRuolo('dirigente')) {
	$operatoreDirigente = true;
	debug('si sono dirigente, id='.$__docente_id);
}

// il dirigente lo chiama passando il docente_id (todo: rimuovere)
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
	ruoloRichiesto('dirigente');
	$docente_id = $_POST['docente_id'];
	$operatoreDirigente = true;
}


// valori da restituire come totali
$attribuiteOreFunzionali = 0;
$attribuiteOreConStudenti = 0;
$attribuiteClilOreFunzionali = 0;
$attribuiteClilOreConStudenti = 0;
$attribuiteOrientamentoOreFunzionali = 0;
$attribuiteOrientamentoOreConStudenti = 0;
$data = '';

// nel sommario non vogliamo le ultime due colonne e nome e dettaglio insieme
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><thead><tr>
<th class="col-md-2 text-left">Tipo</th>
<th class="col-md-3 text-left">Nome</th>
<th class="col-md-5 text-left">Dettaglio</th>
<th class="col-md-1 text-center">ore</th>
<th class="col-md-1 text-center"></th>';

$data .= '</tr></thead><tbody>';

$query = "	SELECT
					ore_previste_attivita.id AS ore_previste_attivita_id,
					ore_previste_attivita.ore AS ore_previste_attivita_ore,
					ore_previste_attivita.dettaglio AS ore_previste_attivita_dettaglio,
					ore_previste_tipo_attivita.id AS ore_previste_tipo_attivita_id,
					ore_previste_tipo_attivita.categoria AS ore_previste_tipo_attivita_categoria,
					ore_previste_tipo_attivita.da_rendicontare AS ore_previste_tipo_attivita_da_rendicontare,
					ore_previste_tipo_attivita.nome AS ore_previste_tipo_attivita_nome,
					ore_previste_tipo_attivita.funzionali AS ore_previste_tipo_attivita_funzionali,
					ore_previste_tipo_attivita.con_studenti AS ore_previste_tipo_attivita_con_studenti,
					ore_previste_tipo_attivita.clil AS ore_previste_tipo_attivita_clil,
					ore_previste_tipo_attivita.orientamento AS ore_previste_tipo_attivita_orientamento,
                    ore_previste_attivita_commento.commento AS ore_previste_attivita_commento_commento,
                    ore_previste_attivita_commento.ore_originali AS ore_previste_attivita_commento_ore_originali
					
				FROM ore_previste_attivita ore_previste_attivita
				INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita
				ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
                LEFT JOIN ore_previste_attivita_commento
                on ore_previste_attivita_commento.ore_previste_attivita_id = ore_previste_attivita.id
				WHERE ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND ore_previste_attivita.docente_id = $docente_id
                AND ore_previste_tipo_attivita.inserito_da_docente = false
                AND ore_previste_tipo_attivita.previsto_da_docente = false
				ORDER BY
					ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.nome ASC
				"
				;

foreach(dbGetAll($query) as $row) {
	$ore_con_minuti = oreToDisplay($row['ore_previste_attivita_ore']);
	$clilMarker = '';
	if ($row['ore_previste_tipo_attivita_clil']) {
		$clilMarker = '<span class="label label-danger">clil</span>';
	}
	$orientamentoMarker = '';
	if ($row['ore_previste_tipo_attivita_orientamento']) {
		$orientamentoMarker = '<span class="label label-warning">orientamento</span>';
	}

	$data .= '<tr><td class="col-md-1">'.$clilMarker.$orientamentoMarker.$row['ore_previste_tipo_attivita_categoria'].'</td>';
	$data .= '<td class="col-md-3">'.$row['ore_previste_tipo_attivita_nome'].'</td>';
	$data .= '<td>'.$row['ore_previste_attivita_dettaglio'];
	if ($row['ore_previste_attivita_commento_commento'] != null && !empty(trim($row['ore_previste_attivita_commento_commento'], " "))) {
		$data .='</br><span class="text-danger"><strong>'.$row['ore_previste_attivita_commento_commento'].'</strong></span>';
	}
	$data .='</td>';

	$data .= '<td class="col-md-1 text-center">'.writeOre($row['ore_previste_attivita_ore'], $row['ore_previste_attivita_commento_ore_originali']).'</td>';

	$data .='<td class="col-md-1 text-center">';
	// si possono modificare solo le righe previste da docente: se dirigente lo script non cancella ma propone di mettere le ore a zero
	if ($operatoreDirigente) {
		$data .='<button onclick="attribuiteGetDetails('.$row['ore_previste_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>';
	}

	// aggiorna il totale delle ore: prima le attivita' clil (funzionali o con studenti)
	if ($row['ore_previste_tipo_attivita_clil'] == 1) {
		if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
			$attribuiteClilOreFunzionali += $row['ore_previste_attivita_ore'];
		} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
			$attribuiteClilOreConStudenti += $row['ore_previste_attivita_ore'];
		} else {
			warning('attivita clil non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
		}

	// consideriamo quelle di orientamento
	} elseif ($row['ore_previste_tipo_attivita_orientamento'] == 1) {
		if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
			$attribuiteOrientamentoOreFunzionali += $row['ore_previste_attivita_ore'];
		} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
			$attribuiteOrientamentoOreConStudenti += $row['ore_previste_attivita_ore'];
		} else {
			warning('attivita orientamento non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
		}

	// infine le altre attribuite
	} else {
		if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
			$attribuiteOreFunzionali += $row['ore_previste_attivita_ore'];
		} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
			$attribuiteOreConStudenti += $row['ore_previste_attivita_ore'];
		} else {
			warning('attivita orientamento non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
		}
	}

	$data .='</td></tr>';
}

$data .= '</tbody></table></div>';

$response = compact('data', 'attribuiteOreFunzionali', 'attribuiteOreConStudenti', 'attribuiteClilOreFunzionali', 'attribuiteClilOreConStudenti', 'attribuiteOrientamentoOreFunzionali', 'attribuiteOrientamentoOreConStudenti');
echo json_encode($response);
?>
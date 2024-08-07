<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

function writeOreAttribuite($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return oreToDisplay($attuali);
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	return '<s style="text-decoration-style: double;"> '.oreToDisplay($originali).' </s>&ensp;<span class="text-danger"><strong> '.oreToDisplay($attuali).' </strong></span>';
}

function oreFatteReadAttribuite($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

	// valori da restituire come totali
	$attribuiteOreFunzionali = 0;
	$attribuiteOreConStudenti = 0;
	$attribuiteClilOreFunzionali = 0;
	$attribuiteClilOreConStudenti = 0;
	$attribuiteOrientamentoOreFunzionali = 0;
	$attribuiteOrientamentoOreConStudenti = 0;
	$dataAttribuite = '';

	// controlla se deve restituire solo il totale o anche la tabella html
	if($soloTotale) {
		$query = "SELECT ore_previste_tipo_attivita.con_studenti, ore_previste_tipo_attivita.funzionali, ore_previste_tipo_attivita.clil, ore_previste_tipo_attivita.orientamento, Sum(ore_previste_attivita.ore) AS ore FROM ore_previste_attivita
					INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
					WHERE ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_previste_attivita.docente_id = $docente_id
					AND ore_previste_tipo_attivita.inserito_da_docente = false AND ore_previste_tipo_attivita.previsto_da_docente = false
					group by ore_previste_tipo_attivita.clil, ore_previste_tipo_attivita.orientamento, ore_previste_tipo_attivita.con_studenti, ore_previste_tipo_attivita.funzionali;";
		foreach(dbGetAll($query) as $attivita) {
			if ($attivita['clil'] == 1) {
				if ($attivita['con_studenti'] == 1) {
					$attribuiteClilOreConStudenti += $attivita['ore'];
				} else {
					$attribuiteClilOreFunzionali += $attivita['ore'];
				}
			}
			elseif ($attivita['orientamento'] == 1) {
				if ($attivita['con_studenti'] == 1) {
					$attribuiteOrientamentoOreConStudenti += $attivita['ore'];
				} else {
					$attribuiteOrientamentoOreFunzionali += $attivita['ore'];
				}
			} else {
				if ($attivita['con_studenti'] == 1) {
					$attribuiteOreConStudenti += $attivita['ore'];
				} else {
					$attribuiteOreFunzionali += $attivita['ore'];
				}
			}
		}

		$result = compact('dataAttribuite', 'attribuiteOreFunzionali', 'attribuiteOreConStudenti', 'attribuiteClilOreFunzionali', 'attribuiteClilOreConStudenti', 'attribuiteOrientamentoOreFunzionali', 'attribuiteOrientamentoOreConStudenti');
		return $result;
	}

	// nel sommario non vogliamo le ultime due colonne e nome e dettaglio insieme
	$dataAttribuite .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><thead><tr>
	<th class="col-md-2 text-left">Tipo</th>
	<th class="col-md-3 text-left">Nome</th>
	<th class="col-md-5 text-left">Dettaglio</th>
	<th class="col-md-1 text-center">ore</th>
	<th class="col-md-1 text-center"></th>';

	$dataAttribuite .= '</thead><tbody>';

	$query = "SELECT
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
				ORDER BY ore_previste_tipo_attivita.orientamento ASC, ore_previste_tipo_attivita.clil ASC, ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.nome ASC;";

	$lastWasClil = false;
	$lastWasOrientamento = false;
	foreach(dbGetAll($query) as $row) {
		// controlla se iniziano qui i gruppi clil
		if ($row['ore_previste_tipo_attivita_clil'] && ! $lastWasClil) {
			$dataAttribuite .= '<tr><th  colspan="7" class="col-md-12 text-center btn-lightblue4" style="padding: 0px">Clil</th></tr><tbody>';
			$lastWasClil = true;
		}

		// controlla se iniziano qui i gruppi orientamento
		if ($row['ore_previste_tipo_attivita_orientamento'] && ! $lastWasOrientamento) {
			$dataAttribuite .= '<tr><th colspan="7" class="col-md-12 text-center btn-beige" style="padding: 0px">Orientamento</th></tr><tbody>';
			$lastWasOrientamento = true;
		}

		$ore_con_minuti = oreToDisplay($row['ore_previste_attivita_ore']);

		$dataAttribuite .= '<tr><td class="col-md-1">'.$row['ore_previste_tipo_attivita_categoria'].'</td>';
		$dataAttribuite .= '<td class="col-md-3">'.$row['ore_previste_tipo_attivita_nome'].'</td>';
		$dataAttribuite .= '<td>'.$row['ore_previste_attivita_dettaglio'];
		if ($row['ore_previste_attivita_commento_commento'] != null && !empty(trim($row['ore_previste_attivita_commento_commento'], " "))) {
			$dataAttribuite .='</br><span class="text-danger"><strong>'.$row['ore_previste_attivita_commento_commento'].'</strong></span>';
		}
		$dataAttribuite .='</td>';

		$dataAttribuite .= '<td class="col-md-1 text-center">'.writeOreAttribuite($row['ore_previste_attivita_ore'], $row['ore_previste_attivita_commento_ore_originali']).'</td>';

		$dataAttribuite .='<td class="col-md-1 text-center">';
		// si possono modificare solo le righe previste da docente: se dirigente lo script non cancella ma propone di mettere le ore a zero
		if ($operatore == 'dirigente') {
			$dataAttribuite .='<button onclick="attribuiteGetDetails('.$row['ore_previste_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>';
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

		$dataAttribuite .='</td></tr>';
	}

	$dataAttribuite .= '</tbody></table></div>';

	$result = compact('dataAttribuite', 'attribuiteOreFunzionali', 'attribuiteOreConStudenti', 'attribuiteClilOreFunzionali', 'attribuiteClilOreConStudenti', 'attribuiteOrientamentoOreFunzionali', 'attribuiteOrientamentoOreConStudenti');
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

	$result = oreFatteReadAttribuite($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	echo json_encode($result);
}*/
?>
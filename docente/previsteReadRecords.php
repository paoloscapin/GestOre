<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

function writeOrePreviste($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return oreToDisplay($attuali);
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	return '<s style="text-decoration-style: double;"> '.oreToDisplay($originali).' </s>&ensp;<span class="text-danger"><strong> '.oreToDisplay($attuali).' </strong></span>';
}

function previsteReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

	$attivitaAggiornamento = 0;
	$attivitaOreFunzionali = 0;
	$attivitaOreConStudenti = 0;
	$attivitaClilOreFunzionali = 0;
	$attivitaClilOreConStudenti = 0;
	$attivitaOrientamentoOreFunzionali = 0;
	$attivitaOrientamentoOreConStudenti = 0;

	$dataAttivita = '';

	// controlla se deve restituire solo il totale o anche la tabella html
	if($soloTotale) {
		$query = "SELECT ore_previste_tipo_attivita.funzionali, ore_previste_tipo_attivita.con_studenti, ore_previste_tipo_attivita.clil, ore_previste_tipo_attivita.aggiornamento, ore_previste_tipo_attivita.orientamento, Sum(ore_previste_attivita.ore) AS ore FROM ore_previste_attivita
					INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
					WHERE ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_previste_attivita.docente_id = $docente_id AND ore_previste_tipo_attivita.previsto_da_docente = true
					group by ore_previste_tipo_attivita.clil, ore_previste_tipo_attivita.orientamento, ore_previste_tipo_attivita.aggiornamento, ore_previste_tipo_attivita.funzionali, ore_previste_tipo_attivita.con_studenti;";

		foreach(dbGetAll($query) as $attivita) {

			// debug('devo sommare ore='.$attivita['ore'].' aggiornamento='.$attivita['aggiornamento'].' clil='.$attivita['clil'].' orientamento='.$attivita['orientamento'].' funzionali='.$attivita['funzionali'].' con_studenti='.$attivita['con_studenti']);
			// ore di aggiornamento
			if ($attivita['aggiornamento'] == 1) {
				$attivitaAggiornamento += $attivita['ore'];
			// ora consideriamo le attivita' clil (funzionali o con studenti)
			} elseif ($attivita['clil'] == 1) {
				if ($attivita['funzionali'] == 1) {
					$attivitaClilOreFunzionali += $attivita['ore'];
				} elseif ($attivita['con_studenti'] == 1) {
					$attivitaClilOreConStudenti += $attivita['ore'];
				} else {
					warning('attivita clil non funzionale e non con studenti: id=' . $attivita['ore_previste_tipo_attivita_id']);
				}
		
			// consideriamo quelle di orientamento
			} elseif ($attivita['orientamento'] == 1) {
				if ($attivita['funzionali'] == 1) {
					$attivitaOrientamentoOreFunzionali += $attivita['ore'];
				} elseif ($attivita['con_studenti'] == 1) {
					$attivitaOrientamentoOreConStudenti += $attivita['ore'];
				} else {
					warning('attivita orientamento non funzionale e non con studenti: id=' . $attivita['ore_previste_tipo_attivita_id']);
				}
		
			// infine le altre attivita'
			} else {
				if ($attivita['funzionali'] == 1) {
					$attivitaOreFunzionali += $attivita['ore'];
				} elseif ($attivita['con_studenti'] == 1) {
					$attivitaOreConStudenti += $attivita['ore'];
				}
			}
		}

		$result = compact('dataAttivita', 'attivitaAggiornamento', 'attivitaOreFunzionali', 'attivitaOreConStudenti', 'attivitaClilOreFunzionali', 'attivitaClilOreConStudenti', 'attivitaOrientamentoOreFunzionali', 'attivitaOrientamentoOreConStudenti');
		return $result;
	}

	// se non e' solo il totale, disegna la tabella
	$dataAttivita = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
	<tr><th>Tipo</th><th>Nome</th><th>Dettaglio</th><th class="col-md-1 text-center">ore</th><th></th></tr>';

	$query = "SELECT 		ore_previste_attivita.id AS ore_previste_attivita_id,
		ore_previste_attivita.ore AS ore_previste_attivita_ore,
		ore_previste_attivita.dettaglio AS ore_previste_attivita_dettaglio,
		ore_previste_attivita.ultima_modifica AS ore_previste_attivita_ultima_modifica,
		ore_previste_tipo_attivita.id AS ore_previste_tipo_attivita_id,
		ore_previste_tipo_attivita.categoria AS ore_previste_tipo_attivita_categoria,
		ore_previste_tipo_attivita.inserito_da_docente AS ore_previste_tipo_attivita_inserito_da_docente,
		ore_previste_tipo_attivita.previsto_da_docente AS ore_previste_tipo_attivita_previsto_da_docente,
		ore_previste_tipo_attivita.nome AS ore_previste_tipo_attivita_nome,
		ore_previste_tipo_attivita.clil AS ore_previste_tipo_attivita_clil,
		ore_previste_tipo_attivita.orientamento AS ore_previste_tipo_attivita_orientamento,
		ore_previste_tipo_attivita.aggiornamento AS ore_previste_tipo_attivita_aggiornamento,
		ore_previste_tipo_attivita.funzionali AS ore_previste_tipo_attivita_funzionali,
		ore_previste_tipo_attivita.con_studenti AS ore_previste_tipo_attivita_con_studenti,
		ore_previste_attivita_commento.commento AS ore_previste_attivita_commento_commento,
		ore_previste_attivita_commento.ore_originali AS ore_previste_attivita_commento_ore_originali

		FROM ore_previste_attivita ore_previste_attivita
		INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
		LEFT JOIN ore_previste_attivita_commento on ore_previste_attivita_commento.ore_previste_attivita_id = ore_previste_attivita.id
		WHERE ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_previste_attivita.docente_id = $docente_id
		AND ore_previste_tipo_attivita.previsto_da_docente = true
		ORDER BY ore_previste_tipo_attivita.aggiornamento ASC, ore_previste_tipo_attivita.orientamento ASC, ore_previste_tipo_attivita.clil ASC, ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.nome ASC;";

	$lastWasClil = false;
	$lastWasOrientamento = false;
	$lastWasAggiornamento = false;
	foreach(dbGetAll($query) as $row) {
		// controlla se iniziano qui le attivita clil
		debug('attivita clil ='.$row['ore_previste_tipo_attivita_clil'].' nome='.$row['ore_previste_tipo_attivita_nome']);
		if ($row['ore_previste_tipo_attivita_clil'] == 1 && ! $lastWasClil) {
			debug('entro!attivita clil ='.$row['ore_previste_tipo_attivita_clil'].' nome='.$row['ore_previste_tipo_attivita_nome']);
			$dataAttivita .= '<tr><th  colspan="5" class="col-md-12 text-center btn-lightblue4" style="padding: 0px">Clil</th></tr><tbody>';
			$lastWasClil = true;
		}

		// controlla se iniziano qui le attivita orientamento
		if ($row['ore_previste_tipo_attivita_orientamento'] == 1 && ! $lastWasOrientamento) {
			$dataAttivita .= '<tr><th colspan="5" class="col-md-12 text-center btn-beige" style="padding: 0px">Orientamento</th></tr><tbody>';
			$lastWasOrientamento = true;
		}

		// controlla se iniziano qui le attivita aggiornamento
		if ($row['ore_previste_tipo_attivita_aggiornamento'] == 1 && ! $lastWasAggiornamento) {
			$dataAttivita .= '<tr><th colspan="5" class="col-md-12 text-center btn-purple" style="padding: 0px">Aggiornamento</th></tr><tbody>';
			$lastWasAggiornamento = true;
		}

		// controlla se aggiornata dall'ultima modifica (solo per il dirigente)
		$marker = '';
		if ($operatore == 'dirigente') {
			if ($row['ore_previste_attivita_ultima_modifica'] > $ultimo_controllo) {
				$marker = '&nbsp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>&ensp;';
			}
		}

		$dataAttivita .= '<tr>';
		$dataAttivita .= '<td class="col-md-1">'.$row['ore_previste_tipo_attivita_categoria'].'</td><td class="col-md-3">'.$row['ore_previste_tipo_attivita_nome'].'</td><td>'.$marker.$row['ore_previste_attivita_dettaglio'];
		if ($row['ore_previste_attivita_commento_commento'] != null && !empty(trim($row['ore_previste_attivita_commento_commento'], " "))) {
			$dataAttivita .='</br><span class="text-danger"><strong>'.$row['ore_previste_attivita_commento_commento'].'</strong></span>';
		}
		$dataAttivita .='</td>';
		$dataAttivita .= '<td class="col-md-1 text-center">'.writeOrePreviste($row['ore_previste_attivita_ore'], $row['ore_previste_attivita_commento_ore_originali']).'</td>';
		$dataAttivita .='<td class="col-md-1 text-center">';

		// si possono modificare solo le righe previste da docente: se dirigente lo script non cancella ma propone di mettere le ore a zero
		if ($row['ore_previste_tipo_attivita_previsto_da_docente']) {
			if ($modificabile) {
				$dataAttivita .='<button onclick="previstaModifica('.$row['ore_previste_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
				<button onclick="previstaDelete('.$row['ore_previste_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>';
			}
		}
		$dataAttivita .='</td></tr>';

		// aggiorna il totale delle ore: per prima cosa quelle di aggiornamento
		if ($row['ore_previste_tipo_attivita_categoria'] == 'aggiornamento') {
			$attivitaAggiornamento += $row['ore_previste_attivita_ore'];

			// ora consideriamo le attivita' clil (funzionali o con studenti)
		} elseif ($row['ore_previste_tipo_attivita_clil'] == 1) {
			if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
				$attivitaClilOreFunzionali += $row['ore_previste_attivita_ore'];
			} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
				$attivitaClilOreConStudenti += $row['ore_previste_attivita_ore'];
			} else {
				warning('attivita clil non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
			}

			// consideriamo quelle di orientamento
		} elseif ($row['ore_previste_tipo_attivita_orientamento'] == 1) {
			if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
				$attivitaOrientamentoOreFunzionali += $row['ore_previste_attivita_ore'];
			} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
				$attivitaOrientamentoOreConStudenti += $row['ore_previste_attivita_ore'];
			} else {
				warning('attivita orientamento non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
			}

			// infine le altre attivita'
		} else {
			if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
				$attivitaOreFunzionali += $row['ore_previste_attivita_ore'];
			} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
				$attivitaOreConStudenti += $row['ore_previste_attivita_ore'];
			} else {
				warning('attivita orientamento non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
			}
		}
	}
	$dataAttivita .= '</table></div>';

	$result = compact('dataAttivita', 'attivitaAggiornamento', 'attivitaOreFunzionali', 'attivitaOreConStudenti', 'attivitaClilOreFunzionali', 'attivitaClilOreConStudenti', 'attivitaOrientamentoOreFunzionali', 'attivitaOrientamentoOreConStudenti');
	return $result;
}

// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_POST['richiesta']) && $_POST['richiesta'] == "previsteReadRecords") {
	if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
		$docente_id = $_POST['docente_id'];
	} else {
		$docente_id = $__docente_id;
	}
	$soloTotale = json_decode($_POST['soloTotale']);

	if(isset($_POST['operatore']) && $_POST['operatore'] == 'dirigente') {
		// se vuoi fare il dirigente, devi essere dirigente
		ruoloRichiesto('dirigente');
		// agisci quindi come dirigente
		$operatore = 'dirigente';
		// il dirigente può sempre fare modifiche
		$modificabile = true;
		// devi leggere il timestamp dell'ultimo controllo effettuato
		$ultimo_controllo = $_POST['ultimo_controllo'];
	} else {
		$operatore = 'docente';
		$ultimo_controllo = '';
		$modificabile = $__config->getOre_previsioni_aperto();
	}

	$result = previsteReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	echo json_encode($result);
}
?>

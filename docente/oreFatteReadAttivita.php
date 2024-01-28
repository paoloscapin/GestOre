<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

function writeOreAttivita($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return oreToDisplay($attuali);
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	return '<s style="text-decoration-style: double;"> '.oreToDisplay($originali).' </s>&ensp;<span class="text-danger"><strong> '.oreToDisplay($attuali).' </strong></span>';
}

// valori da restituire come totali
$attivitaAggiornamento = 0;
$attivitaOreFunzionali = 0;
$attivitaOreConStudenti = 0;
$attivitaClilOreFunzionali = 0;
$attivitaClilOreConStudenti = 0;
$attivitaOrientamentoOreFunzionali = 0;
$attivitaOrientamentoOreConStudenti = 0;

function writeLineAttivita($row, $clil, $orientamento, $operatore, $ultimo_controllo, $modificabile) {
	global $accettataMarker;
	global $attivitaAggiornamento;
	global $attivitaOreFunzionali;
	global $attivitaOreConStudenti;
	global $attivitaClilOreFunzionali;
	global $attivitaClilOreConStudenti;
	global $attivitaOrientamentoOreFunzionali;
	global $attivitaOrientamentoOreConStudenti;
    $dataAttivita = '';

	$contestataMarker = '<span class=\'label label-danger\'>contestata</span>';
	$accettataMarker = '';
	$strikeOn = '';
	$strikeOff = '';
	if ($row['ore_fatte_attivita_contestata'] == 1) {
		$strikeOn = '<strike>';
		$strikeOff = '</strike>';
	}
	
	// controlla se aggiornata dall'ultima modifica (solo per il dirigente)
	$marker = '';
	if ($operatore == 'dirigente') {
		if ($row['ore_fatte_attivita_ultima_modifica'] > $ultimo_controllo) {
			$marker = '&nbsp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>&ensp;';
		}
	}

	$dataAttivita .= '<tr>
		<td>'.$strikeOn.$row['ore_previste_tipo_attivita_categoria'].$strikeOff.'</td>
		<td>'.$strikeOn.$row['ore_previste_tipo_attivita_nome'].$strikeOff.'</td>
		<td>'.$marker.$strikeOn.$row['ore_fatte_attivita_dettaglio'].$strikeOff;

	if ($row['ore_fatte_attivita_commento_commento'] != null && !empty(trim($row['ore_fatte_attivita_commento_commento'], " "))) {
		$dataAttivita .='</br><span class="text-danger"><strong>'.$row['ore_fatte_attivita_commento_commento'].'</strong></span>';
	}
	$dataAttivita .='</td>';

	$ore_con_minuti = oreToDisplay($row['ore_fatte_attivita_ore']);

	// data e ora solo per quelle inserite da docente (dovrebbero essere tutte a questo punto)
	if ($row['ore_previste_tipo_attivita_inserito_da_docente']) {
		$dataAttivita .= '<td class="text-center">'.$strikeOn.strftime("%d/%m/%Y", strtotime($row['ore_fatte_attivita_data'])).$strikeOff.'</td>';
		$dataAttivita .= '<td class="text-center">'.writeOreAttivita($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';
	} else {
		$dataAttivita .= '<td class="text-center">'.$strikeOn.strftime("%d/%m/%Y", strtotime($row['ore_fatte_attivita_data'])).$strikeOff.'</td>';
		$dataAttivita .= '<td class="text-center">'.writeOreAttivita($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';
//		$dataAttivita .='<td class="text-center">'.'</td><td class="text-center">'.writeOreAttivita($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';
	}

	$dataAttivita .='<td class="text-center">';
	// registro per quelle inserite da docente
	if ($row['ore_previste_tipo_attivita_inserito_da_docente']) {
		$dataAttivita .='<button onclick="oreFatteGetRegistroAttivita('.$row['ore_fatte_attivita_id'].', '.$row['registro_attivita_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></button>';
	}
	$dataAttivita .='</td>';

	$marker = ($row['ore_fatte_attivita_contestata'] == 1)? $contestataMarker : $accettataMarker;
//	$dataAttivita .= '<td class="col-md-1 text-center">'.$marker.'</td>';

	$dataAttivita .='<td class="text-center">';

	if ($row['ore_previste_tipo_attivita_inserito_da_docente']) {
		if ($modificabile) {
			$dataAttivita .='<button onclick="oreFatteGetAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
				<button onclick="oreFatteDeleteAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>';
		} else {
			if ($row['ore_fatte_attivita_contestata'] == 1) {
				$dataAttivita .='<button onclick="oreFatteRipristrinaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \''.str2js($row['ore_fatte_attivita_commento_commento']).'\', \'\')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-ok"></span> Ripristina</button>';
			} else {
				$dataAttivita .='<button onclick="oreFatteControllaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \'\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-remove"></span> Contesta</button>';
			}
		}
	} else {
		// non dovrebbe accadere mai, a meno che non sia stato tolto quest'anno il flag inserito da docente per questo tipo di attivita: in quel caso posso solo cancellarlo
		$dataAttivita .='<button onclick="oreFatteDeleteAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>';
	}
	$dataAttivita .='</td></tr>';

	// aggiorna il totale delle ore: per prima cosa quelle di aggiornamento
	if ($row['ore_previste_tipo_attivita_categoria'] == 'aggiornamento') {
		$attivitaAggiornamento += $row['ore_fatte_attivita_ore'];

		// ora consideriamo le attivita' clil (funzionali o con studenti)
	} elseif ($row['ore_previste_tipo_attivita_clil'] == 1) {
		if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
			$attivitaClilOreFunzionali += $row['ore_fatte_attivita_ore'];
		} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
			$attivitaClilOreConStudenti += $row['ore_fatte_attivita_ore'];
		} else {
			warning('attivita clil non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
		}

		// consideriamo quelle di orientamento
	} elseif ($row['ore_previste_tipo_attivita_orientamento'] == 1) {
		if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
			$attivitaOrientamentoOreFunzionali += $row['ore_fatte_attivita_ore'];
		} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
			$attivitaOrientamentoOreConStudenti += $row['ore_fatte_attivita_ore'];
		} else {
			warning('attivita orientamento non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
		}

		// infine le altre attivita'
	} else {
		if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
			$attivitaOreFunzionali += $row['ore_fatte_attivita_ore'];
		} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
			$attivitaOreConStudenti += $row['ore_fatte_attivita_ore'];
		} else {
			warning('attivita orientamento non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
		}
	}

	return $dataAttivita;
}

function creaQuery($docente_id, $clil, $orientamento) {
	global $__anno_scolastico_corrente_id;

	global $attivitaAggiornamento;
	global $attivitaOreFunzionali;
	global $attivitaOreConStudenti;
	global $attivitaClilOreFunzionali;
	global $attivitaClilOreConStudenti;
	global $attivitaOrientamentoOreFunzionali;
	global $attivitaOrientamentoOreConStudenti;

	debug('FUNZIONE NON SOLO docente_id:'.$docente_id);

	return "	SELECT
			ore_fatte_attivita.id AS ore_fatte_attivita_id,
			ore_fatte_attivita.ore AS ore_fatte_attivita_ore,
			ore_fatte_attivita.dettaglio AS ore_fatte_attivita_dettaglio,
			ore_fatte_attivita.data AS ore_fatte_attivita_data,
			ore_fatte_attivita.contestata AS ore_fatte_attivita_contestata,
			ore_fatte_attivita.ultima_modifica AS ore_fatte_attivita_ultima_modifica,
			ore_previste_tipo_attivita.id AS ore_previste_tipo_attivita_id,
			ore_previste_tipo_attivita.categoria AS ore_previste_tipo_attivita_categoria,
			ore_previste_tipo_attivita.inserito_da_docente AS ore_previste_tipo_attivita_inserito_da_docente,
			ore_previste_tipo_attivita.nome AS ore_previste_tipo_attivita_nome,
			ore_previste_tipo_attivita.da_rendicontare AS ore_previste_tipo_attivita_da_rendicontare,

			ore_previste_tipo_attivita.funzionali AS ore_previste_tipo_attivita_funzionali,
			ore_previste_tipo_attivita.con_studenti AS ore_previste_tipo_attivita_con_studenti,
			ore_previste_tipo_attivita.clil AS ore_previste_tipo_attivita_clil,
			ore_previste_tipo_attivita.orientamento AS ore_previste_tipo_attivita_orientamento,
			
			registro_attivita.id AS registro_attivita_id,
			ore_fatte_attivita_commento.commento AS ore_fatte_attivita_commento_commento,
			ore_fatte_attivita_commento.ore_originali AS ore_fatte_attivita_ore_originali

		FROM ore_fatte_attivita ore_fatte_attivita
		INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita
		ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
		LEFT JOIN registro_attivita registro_attivita
		ON registro_attivita.ore_fatte_attivita_id = ore_fatte_attivita.id
		LEFT JOIN ore_fatte_attivita_commento
		on ore_fatte_attivita_commento.ore_fatte_attivita_id = ore_fatte_attivita.id
		WHERE ore_fatte_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id
		AND ore_fatte_attivita.docente_id = $docente_id
		AND ore_previste_tipo_attivita.clil = $clil
		AND ore_previste_tipo_attivita.orientamento = $orientamento
		ORDER BY
			ore_fatte_attivita.data DESC,
			ore_fatte_attivita.ora_inizio";
}

function oreFatteReadAttivitaVecchio($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

	global $attivitaAggiornamento;
	global $attivitaOreFunzionali;
	global $attivitaOreConStudenti;
	global $attivitaClilOreFunzionali;
	global $attivitaClilOreConStudenti;
	global $attivitaOrientamentoOreFunzionali;
	global $attivitaOrientamentoOreConStudenti;

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
		$query = "SELECT ore_fatte_attivita.ore, ore_fatte_attivita.dettaglio, ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.funzionali, ore_previste_tipo_attivita.con_studenti, ore_previste_tipo_attivita.clil, ore_previste_tipo_attivita.orientamento
					FROM ore_fatte_attivita ore_fatte_attivita INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
					WHERE ore_fatte_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_fatte_attivita.docente_id = $docente_id AND COALESCE(ore_fatte_attivita.contestata, 0) = 0;";

		foreach(dbGetAll($query) as $attivita) {
			// ore di aggiornamento
			if ($attivita['categoria'] == 'aggiornamento') {
				$attivitaAggiornamento += $attivita['ore'];
				// ora consideriamo le attivita' clil (funzionali o con studenti)
			} elseif ($attivita['clil'] == 1) {
				if ($attivita['funzionali'] == 1) {
					$attivitaClilOreFunzionali += $attivita['ore'];
				} elseif ($roattivitaw['con_studenti'] == 1) {
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
	$dataAttivita .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
							<thead><tr>
								<th class="col-md-1 text-left">Tipo</th>
								<th class="col-md-2 text-left">Nome</th>
								<th class="col-md-5 text-left">Dettaglio</th>
								<th class="col-md-1 text-center">Data</th>
								<th class="col-md-1 text-center">Ore</th>
								<th class="col-md-1 text-center">Registro</th>
								<th class="col-md-1 text-center"></th>
							</tr></thead><tbody>';

	$query = creaQuery($docente_id, 1,0);
	$lista = dbGetAll($query);
	if (!empty($lista)) {
		$dataAttivita .= '<thead><tr><th  colspan="7" class="col-md-12 text-center btn-lightblue4">Clil</th></tr></thead><tbody>';
		foreach($lista as $row) {
			$dataAttivita .= writeLineAttivita($row, 1, 0, $operatore, $ultimo_controllo, $modificabile);
		}
	}

	$query = creaQuery($docente_id, 0,1);
	$lista = dbGetAll($query);
	if (!empty($lista)) {
		$dataAttivita .= '<thead><tr><th  colspan="7" class="col-md-12 text-center btn-beige">Orientamento</th></tr></thead><tbody>';
		foreach($lista as $row) {
			$dataAttivita .= writeLineAttivita($row, 0, 1, $operatore, $ultimo_controllo, $modificabile);
		}
	}

	$dataAttivita .= '</tbody></table></div>';

	$result = compact('dataAttivita', 'attivitaAggiornamento', 'attivitaOreFunzionali', 'attivitaOreConStudenti', 'attivitaClilOreFunzionali', 'attivitaClilOreConStudenti', 'attivitaOrientamentoOreFunzionali', 'attivitaOrientamentoOreConStudenti');
	return $result;
}




function oreFatteReadAttivita($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

	global $attivitaAggiornamento;
	global $attivitaOreFunzionali;
	global $attivitaOreConStudenti;
	global $attivitaClilOreFunzionali;
	global $attivitaClilOreConStudenti;
	global $attivitaOrientamentoOreFunzionali;
	global $attivitaOrientamentoOreConStudenti;

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
		$query = "SELECT ore_fatte_attivita.ore, ore_fatte_attivita.dettaglio, ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.funzionali, ore_previste_tipo_attivita.con_studenti, ore_previste_tipo_attivita.clil, ore_previste_tipo_attivita.orientamento
					FROM ore_fatte_attivita ore_fatte_attivita INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
					WHERE ore_fatte_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_fatte_attivita.docente_id = $docente_id AND COALESCE(ore_fatte_attivita.contestata, 0) = 0;";

		foreach(dbGetAll($query) as $attivita) {
			// ore di aggiornamento
			if ($attivita['categoria'] == 'aggiornamento') {
				$attivitaAggiornamento += $attivita['ore'];
				// ora consideriamo le attivita' clil (funzionali o con studenti)
			} elseif ($attivita['clil'] == 1) {
				if ($attivita['funzionali'] == 1) {
					$attivitaClilOreFunzionali += $attivita['ore'];
				} elseif ($roattivitaw['con_studenti'] == 1) {
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

/*
		$query = "SELECT SUM(ore_fatte_attivita.ore) AS ore, ore_previste_tipo_attivita.funzionali, ore_previste_tipo_attivita.con_studenti, ore_previste_tipo_attivita.clil, ore_previste_tipo_attivita.orientamento, ore_previste_tipo_attivita.aggiornamento
			FROM ore_fatte_attivita ore_fatte_attivita INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
			WHERE ore_fatte_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_fatte_attivita.docente_id = $docente_id AND COALESCE(ore_fatte_attivita.contestata, 0) = 0
			group by ore_previste_tipo_attivita.clil, ore_previste_tipo_attivita.orientamento, ore_previste_tipo_attivita.aggiornamento, ore_previste_tipo_attivita.funzionali, ore_previste_tipo_attivita.con_studenti;";

		foreach(dbGetAll($query) as $attivita) {
			// ore di aggiornamento
			if ($attivita['aggiornamento'] == 1) {
				$attivitaAggiornamento += $attivita['ore'];
				// ora consideriamo le attivita' clil (funzionali o con studenti)
			} elseif ($attivita['clil'] == 1) {
				if ($attivita['funzionali'] == 1) {
					$attivitaClilOreFunzionali += $attivita['ore'];
				} elseif ($roattivitaw['con_studenti'] == 1) {
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
*/
		$result = compact('dataAttivita', 'attivitaAggiornamento', 'attivitaOreFunzionali', 'attivitaOreConStudenti', 'attivitaClilOreFunzionali', 'attivitaClilOreConStudenti', 'attivitaOrientamentoOreFunzionali', 'attivitaOrientamentoOreConStudenti');
		return $result;
	}

	// se non e' solo il totale, disegna la tabella
	$dataAttivita .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
							<thead><tr>
								<th class="col-md-1 text-left">Tipo</th>
								<th class="col-md-2 text-left">Nome</th>
								<th class="col-md-5 text-left">Dettaglio</th>
								<th class="col-md-1 text-center">Data</th>
								<th class="col-md-1 text-center">Ore</th>
								<th class="col-md-1 text-center">Registro</th>
								<th class="col-md-1 text-center"></th>
							</tr></thead><tbody>';

	// crea la query
	debug('FUNZIONE NON SOLO docente_id:'.$docente_id);

	$query = "SELECT
		ore_fatte_attivita.id AS ore_fatte_attivita_id,
		ore_fatte_attivita.ore AS ore_fatte_attivita_ore,
		ore_fatte_attivita.dettaglio AS ore_fatte_attivita_dettaglio,
		ore_fatte_attivita.data AS ore_fatte_attivita_data,
		ore_fatte_attivita.contestata AS ore_fatte_attivita_contestata,
		ore_fatte_attivita.ultima_modifica AS ore_fatte_attivita_ultima_modifica,
		ore_previste_tipo_attivita.id AS ore_previste_tipo_attivita_id,
		ore_previste_tipo_attivita.categoria AS ore_previste_tipo_attivita_categoria,
		ore_previste_tipo_attivita.inserito_da_docente AS ore_previste_tipo_attivita_inserito_da_docente,
		ore_previste_tipo_attivita.nome AS ore_previste_tipo_attivita_nome,
		ore_previste_tipo_attivita.da_rendicontare AS ore_previste_tipo_attivita_da_rendicontare,
		ore_previste_tipo_attivita.funzionali AS ore_previste_tipo_attivita_funzionali,
		ore_previste_tipo_attivita.con_studenti AS ore_previste_tipo_attivita_con_studenti,
		ore_previste_tipo_attivita.clil AS ore_previste_tipo_attivita_clil,
		ore_previste_tipo_attivita.orientamento AS ore_previste_tipo_attivita_orientamento,
		ore_previste_tipo_attivita.aggiornamento AS ore_previste_tipo_attivita_aggiornamento,
		registro_attivita.id AS registro_attivita_id,
		ore_fatte_attivita_commento.commento AS ore_fatte_attivita_commento_commento,
		ore_fatte_attivita_commento.ore_originali AS ore_fatte_attivita_ore_originali

		FROM ore_fatte_attivita ore_fatte_attivita
		INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
		LEFT JOIN registro_attivita registro_attivita ON registro_attivita.ore_fatte_attivita_id = ore_fatte_attivita.id
		LEFT JOIN ore_fatte_attivita_commento ON ore_fatte_attivita_commento.ore_fatte_attivita_id = ore_fatte_attivita.id
		WHERE ore_fatte_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id
		AND ore_fatte_attivita.docente_id = $docente_id
		ORDER BY ore_previste_tipo_attivita.aggiornamento ASC, ore_previste_tipo_attivita.orientamento ASC, ore_previste_tipo_attivita.clil ASC, ore_fatte_attivita.data DESC, ore_fatte_attivita.ora_inizio";
						
	$lastWasClil = false;
	$lastWasOrientamento = false;
	$lastWasAggiornamento = false;
	foreach(dbGetAll($query) as $row) {
		// controlla se iniziano qui gli sportelli clil
		if ($row['ore_previste_tipo_attivita_clil'] && ! $lastWasClil) {
			$dataAttivita .= '<tr><th  colspan="7" class="col-md-12 text-center btn-lightblue4" style="padding: 0px">Clil</th></tr><tbody>';
			$lastWasClil = true;
		}

		// controlla se iniziano qui gli sportelli orientamento
		if ($row['ore_previste_tipo_attivita_orientamento'] && ! $lastWasOrientamento) {
			$dataAttivita .= '<tr><th colspan="7" class="col-md-12 text-center btn-beige" style="padding: 0px">Orientamento</th></tr><tbody>';
			$lastWasOrientamento = true;
		}

		// controlla se iniziano qui le attivita aggiornamento
		if ($row['ore_previste_tipo_attivita_aggiornamento'] == 1 && ! $lastWasAggiornamento) {
			$dataAttivita .= '<tr><th colspan="7" class="col-md-12 text-center btn-purple" style="padding: 0px">Aggiornamento</th></tr><tbody>';
			$lastWasAggiornamento = true;
		}

		// controlla se e' contestata e attiva lo strike
		$contestataMarker = '<span class=\'label label-danger\'>contestata</span>';
		$accettataMarker = '';
		$strikeOn = '';
		$strikeOff = '';
		if ($row['ore_fatte_attivita_contestata'] == 1) {
			$strikeOn = '<strike>';
			$strikeOff = '</strike>';
		}
		
		// controlla se aggiornata dall'ultima modifica (solo per il dirigente)
		$marker = '';
		if ($operatore == 'dirigente') {
			if ($row['ore_fatte_attivita_ultima_modifica'] > $ultimo_controllo) {
				$marker = '&nbsp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>&ensp;';
			}
		}

		// comincia a costruire la riga
		$dataAttivita .= '<tr>
			<td>'.$strikeOn.$row['ore_previste_tipo_attivita_categoria'].$strikeOff.'</td>
			<td>'.$strikeOn.$row['ore_previste_tipo_attivita_nome'].$strikeOff.'</td>
			<td>'.$marker.$strikeOn.$row['ore_fatte_attivita_dettaglio'].$strikeOff;

		if ($row['ore_fatte_attivita_commento_commento'] != null && !empty(trim($row['ore_fatte_attivita_commento_commento'], " "))) {
			$dataAttivita .='</br><span class="text-danger"><strong>'.$row['ore_fatte_attivita_commento_commento'].'</strong></span>';
		}
		$dataAttivita .='</td>';
	
		$ore_con_minuti = oreToDisplay($row['ore_fatte_attivita_ore']);
	
		// data e ora solo per quelle inserite da docente (dovrebbero essere tutte a questo punto)
		if ($row['ore_previste_tipo_attivita_inserito_da_docente']) {
			$dataAttivita .= '<td class="text-center">'.$strikeOn.strftime("%d/%m/%Y", strtotime($row['ore_fatte_attivita_data'])).$strikeOff.'</td>';
			$dataAttivita .= '<td class="text-center">'.writeOreAttivita($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';
		} else {
			$dataAttivita .= '<td class="text-center">'.$strikeOn.strftime("%d/%m/%Y", strtotime($row['ore_fatte_attivita_data'])).$strikeOff.'</td>';
			$dataAttivita .= '<td class="text-center">'.writeOreAttivita($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';
	//		$dataAttivita .='<td class="text-center">'.'</td><td class="text-center">'.writeOreAttivita($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';
		}
	
		$dataAttivita .='<td class="text-center">';
		// registro per quelle inserite da docente
		if ($row['ore_previste_tipo_attivita_inserito_da_docente']) {
			$dataAttivita .='<button onclick="oreFatteGetRegistroAttivita('.$row['ore_fatte_attivita_id'].', '.$row['registro_attivita_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></button>';
		}
		$dataAttivita .='</td>';
	
		$marker = ($row['ore_fatte_attivita_contestata'] == 1)? $contestataMarker : $accettataMarker;
	//	$dataAttivita .= '<td class="col-md-1 text-center">'.$marker.'</td>';
	
		$dataAttivita .='<td class="text-center">';
	
		if ($row['ore_previste_tipo_attivita_inserito_da_docente']) {
			if ($modificabile) {
				$dataAttivita .='<button onclick="oreFatteGetAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
					<button onclick="oreFatteDeleteAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>';
			} else {
				if ($row['ore_fatte_attivita_contestata'] == 1) {
					$dataAttivita .='<button onclick="oreFatteRipristrinaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \''.str2js($row['ore_fatte_attivita_commento_commento']).'\', \'\')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-ok"></span> Ripristina</button>';
				} else {
					$dataAttivita .='<button onclick="oreFatteControllaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \'\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-remove"></span> Contesta</button>';
				}
			}
		} else {
			// non dovrebbe accadere mai, a meno che non sia stato tolto quest'anno il flag inserito da docente per questo tipo di attivita: in quel caso posso solo cancellarlo
			$dataAttivita .='<button onclick="oreFatteDeleteAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>';
		}
		$dataAttivita .='</td></tr>';

		// aggiorna il totale delle ore: per prima cosa quelle di aggiornamento
		if ($row['ore_previste_tipo_attivita_categoria'] == 'aggiornamento') {
			$attivitaAggiornamento += $row['ore_fatte_attivita_ore'];
	
			// ora consideriamo le attivita' clil (funzionali o con studenti)
		} elseif ($row['ore_previste_tipo_attivita_clil'] == 1) {
			if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
				$attivitaClilOreFunzionali += $row['ore_fatte_attivita_ore'];
			} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
				$attivitaClilOreConStudenti += $row['ore_fatte_attivita_ore'];
			} else {
				warning('attivita clil non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
			}
	
			// consideriamo quelle di orientamento
		} elseif ($row['ore_previste_tipo_attivita_orientamento'] == 1) {
			if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
				$attivitaOrientamentoOreFunzionali += $row['ore_fatte_attivita_ore'];
			} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
				$attivitaOrientamentoOreConStudenti += $row['ore_fatte_attivita_ore'];
			} else {
				warning('attivita orientamento non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
			}
	
			// infine le altre attivita'
		} else {
			if ($row['ore_previste_tipo_attivita_funzionali'] == 1) {
				$attivitaOreFunzionali += $row['ore_fatte_attivita_ore'];
			} elseif ($row['ore_previste_tipo_attivita_con_studenti'] == 1) {
				$attivitaOreConStudenti += $row['ore_fatte_attivita_ore'];
			} else {
				warning('attivita orientamento non funzionale e non con studenti: id=' . $row['ore_previste_tipo_attivita_id']);
			}
		}
	}

	$dataAttivita .= '</tbody></table></div>';

	$result = compact('dataAttivita', 'attivitaAggiornamento', 'attivitaOreFunzionali', 'attivitaOreConStudenti', 'attivitaClilOreFunzionali', 'attivitaClilOreConStudenti', 'attivitaOrientamentoOreFunzionali', 'attivitaOrientamentoOreConStudenti');
	return $result;
}
/*
// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_POST['richiesta']) && $_POST['richiesta'] == "oreFatteReadAttivita") {
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
		$modificabile = $__config->getOre_fatte_aperto();
	}

	$result = oreFatteReadAttivita($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	echo json_encode($result);
}*/
?>
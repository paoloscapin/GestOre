<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';
require_once '../common/importi_load.php';

function corsoDiRecuperoPrevisteReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile, $controllaFirmeInItinere) {
	global $__anno_scolastico_corrente_id;

	// valori da restituire come totali
	$corso_di_recupero_ore_recuperate = 0;
	$corso_di_recupero_ore_pagamento_extra = 0;
	$corso_di_recupero_ore_in_itinere = 0;
	$dataCdr = '';

	// controlla se deve restituire solo il totale o anche la tabella html
	if($soloTotale) {
		$ore_corsi_di_recupero = dbGetFirst("SELECT COALESCE(SUM(corso_di_recupero.ore_recuperate), 0) AS ore_recuperate, COALESCE(SUM(corso_di_recupero.ore_pagamento_extra), 0) AS ore_pagamento_extra FROM corso_di_recupero WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id AND NOT in_itinere;");
		$corso_di_recupero_ore_recuperate = $ore_corsi_di_recupero['ore_recuperate'];
		$corso_di_recupero_ore_pagamento_extra = $ore_corsi_di_recupero['ore_pagamento_extra'];
		$corso_di_recupero_ore_in_itinere = dbGetValue("SELECT COALESCE(SUM(corso_di_recupero.ore_recuperate), 0) FROM corso_di_recupero WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id AND in_itinere = true;");

		$result = compact('dataCdr', 'corso_di_recupero_ore_recuperate', 'corso_di_recupero_ore_pagamento_extra', 'corso_di_recupero_ore_in_itinere');
		return $result;
	}

	// NB: si potrebbe usare una cosa di questo tipo?
	// $ore_corsi_di_recupero = dbGetValue("SELECT COALESCE(SUM(corso_di_recupero.ore_recuperate), 0) FROM corso_di_recupero WHERE docente_id = $docenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
	// dbExec("UPDATE corso_di_recupero SET ore_recuperate=$ore_recuperate, ore_pagamento_extra=$ore_pagamento_extra WHERE id = $corsoId;");
	// si usano  AND NOT in_itinere
	// oppure  AND in_itinere = true
	// intestazione della table
	$dataCdr .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><thead><tr><th class="col-md-3 text-left">Corso</th><th class="col-md-5 text-left">Materia</th><th class="text-center col-md-1">Ore Totali</th><th class="text-center col-md-1">Recuperate</th><th class="text-center col-md-1">Pagamento Extra</th><th class="text-center col-md-1"></th></tr></thead><tbody>';

	// contatori delle ore recuperate e pagamento extra
	$ore_recuperate_totale = 0;
	$ore_pagamento_extra_totale = 0;

	// prima i corsi di recupero che sono stati fatti a settembre (non in_itinere)
	foreach(dbGetAll("SELECT DISTINCT corso_di_recupero.*, materia.nome AS materiaNome FROM corso_di_recupero INNER JOIN materia ON corso_di_recupero.materia_id = materia.id INNER JOIN lezione_corso_di_recupero on lezione_corso_di_recupero.corso_di_recupero_id=corso_di_recupero.id WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id AND firmato=true AND NOT in_itinere") AS $corso) {
		$corsoId = $corso['id'];
		$corsoCodice = $corso['codice'];
		$materia = $corso['materiaNome'];
		// per prima cosa calcola quante ore sono state firmate
		$ore_firmate = dbGetValue("SELECT COALESCE(SUM(lezione_corso_di_recupero.numero_ore),0) FROM `lezione_corso_di_recupero` WHERE corso_di_recupero_id = $corsoId AND firmato=true;");

		$ore_recuperate = $corso['ore_recuperate'];
		$ore_pagamento_extra = $corso['ore_pagamento_extra'];

		// calcola quante sono obbligatorie per chiudere le 10 ore, per quelle non ci deve essere scelta su come pagarle
		$mancanti_recuperate_totale = 10 - $ore_recuperate_totale;

		// calcola quantes sono le ore di questo corso che devono per forza essere recuperate (potrebbe anche essere negativo)
		$ore_recuperate_calcolate = min($ore_firmate, $mancanti_recuperate_totale);

		// se le mancanti sono > 0 , allora queste ore le devo per forza recuperare e non posso modificarle
		if ($mancanti_recuperate_totale > 0) {
			// scrivo le ore e NON metto il bottone di modifica
			$dataCdr .= '<tr><td>'.$corsoCodice.'</td><td>'.$materia.'</td><td class="text-center">'.$ore_recuperate_calcolate.'</td><td class="text-center">'.$ore_recuperate_calcolate.'</td><td class="text-center">'.'0'.'</td><td></td></tr>';

			// aggiorno il totale delle recuperate
			$ore_recuperate_totale += $ore_recuperate_calcolate;

			// queste ore sono state usate e quindi le tolgo dalle firmate, che potrebbero andare a 0 opure no
			$ore_firmate -= $ore_recuperate_calcolate;

			// le ore recuperate che rimangono dopo quelle che ho tolto
			$ore_recuperate = max($ore_recuperate - $ore_recuperate_calcolate, 0);

			// se restano altre ore non richieste come recuperate, vanno calcolate come extra
			$ore_pagamento_extra = $ore_firmate - $ore_recuperate;

			// per sicurezza aggiorna la riga sul database per dire quante sono le recuperate
			$ore_recuperate_per_db = $ore_recuperate + $ore_recuperate_calcolate;
			dbExec("UPDATE corso_di_recupero SET ore_recuperate=$ore_recuperate_per_db, ore_pagamento_extra=$ore_pagamento_extra WHERE id = $corsoId;");

			// le calcolate vanno nel totale delle ore recuperate
			$corso_di_recupero_ore_recuperate += $ore_recuperate_calcolate;
		}

		// aggiorna il totale da ritornare
		$corso_di_recupero_ore_recuperate += $ore_recuperate;
		$corso_di_recupero_ore_pagamento_extra += $ore_pagamento_extra;
		
		// se sono rimaste delle ore firmate, ore devono essere inserite
		if ($ore_firmate > 0) {
			$dataCdr .= '<tr><td>'.$corsoCodice.'</td><td>'.$materia.'</td><td class="text-center">'.$ore_firmate.'</td><td class="text-center">'.$ore_recuperate.'</td><td class="text-center">'.$ore_pagamento_extra.'</td><td>';
			$dataCdr .= '<button onclick="corsoDiRecuperoPrevisteEdit('.$corsoId.', \''.$corsoCodice.'\', '.$ore_firmate.', '.$ore_recuperate.', '.$ore_pagamento_extra.')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>';
			$dataCdr .= '</td></tr>';
		}
	}

	// adesso aggiungo tutti i corsi in itinere
	foreach(dbGetAll("SELECT DISTINCT corso_di_recupero.*, materia.nome AS materiaNome FROM corso_di_recupero INNER JOIN materia ON corso_di_recupero.materia_id = materia.id INNER JOIN lezione_corso_di_recupero on lezione_corso_di_recupero.corso_di_recupero_id=corso_di_recupero.id WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id AND in_itinere = true") AS $corso) {
		$corsoId = $corso['id'];
		$corsoCodice = $corso['codice'];
		$materia = $corso['materiaNome'];
		// per prima cosa calcola quante ore sono state firmate
		$soloFirmatePart = $controllaFirmeInItinere ? '  AND firmato=true ' : '';

		$ore_firmate = dbGetValue("SELECT COALESCE(SUM(lezione_corso_di_recupero.numero_ore),0) FROM `lezione_corso_di_recupero` WHERE corso_di_recupero_id = $corsoId " . $soloFirmatePart . ";");
		$ore_recuperate = $corso['ore_recuperate'];
		$ore_pagamento_extra = $corso['ore_pagamento_extra'];

		// controlla che siano tutte nelle ore recuperate altrimenti mette a posto
		if ($ore_recuperate != $ore_firmate || $ore_pagamento_extra != 0) {
			$ore_recuperate = $ore_firmate;
			$ore_pagamento_extra = 0;
			dbExec("UPDATE corso_di_recupero SET ore_recuperate=$ore_recuperate, ore_pagamento_extra=$ore_pagamento_extra WHERE id = $corsoId;");
		}

		// aggiorna il totale da ritornare
		$corso_di_recupero_ore_in_itinere += $ore_firmate;

		$dataCdr .= '<tr><td>'.$corsoCodice.'</td><td>'.$materia.'</td><td class="text-center">'.$ore_firmate.'</td><td class="text-center">'.$ore_recuperate.'</td><td class="text-center">'.$ore_pagamento_extra.'</td><td></td></tr>';
	}

	$dataCdr .= '</tbody></table></div>';
	// debug('dataCdr='.$dataCdr);

	$result = compact('dataCdr', 'corso_di_recupero_ore_recuperate', 'corso_di_recupero_ore_pagamento_extra', 'corso_di_recupero_ore_in_itinere');
	return $result;
}
?>

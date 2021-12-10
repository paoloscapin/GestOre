<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__Minuti.php';
require_once '../common/importi_load.php';

// default opera sul docente connesso e agisce come docente
$docente_id = $__docente_id;
$operatore = 'docente';

$modificabile = $__config->getOre_previsioni_aperto();

if(isset($_POST['operatore']) && $_POST['operatore'] == 'dirigente') {
	// se vuoi fare il dirigente, devi essere dirigente
	ruoloRichiesto('dirigente');
	// agisci quindi come dirigente
	$operatore = 'dirigente';
	// il dirigente può sempre fare modifiche
	$modificabile = true;
	// devi leggere il timestamp dell'ultimo controllo effettuato
	$ultimo_controllo = $_POST['ultimo_controllo'];
}

// le previste considerano come se tutto fosse stato firmato nel caso di corsi in itinere, ma le fatte no
if(isset($_POST['sorgente_richiesta']) && $_POST['sorgente_richiesta'] == 'fatte') {
	$controllaFirmeInItinere = true;
} else {
	$controllaFirmeInItinere = false;
}


$dataCdr = '';
// intestazione della table
$dataCdr .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><tr><th>Corso</th><th>Ore Totali</th><th>Recuperate</th><th>Pagamento Extra</th><th></th></tr>';

// contatori delle ore recuperate e pagamento extra
$ore_recuperate_totale = 0;
$ore_pagamento_extra_totale = 0;

// prima i corsi di recupero che sono stati fatti a settembre (non in_itinere)
foreach(dbGetAll("SELECT DISTINCT corso_di_recupero.* FROM corso_di_recupero INNER JOIN lezione_corso_di_recupero on lezione_corso_di_recupero.corso_di_recupero_id=corso_di_recupero.id WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id AND firmato=true AND NOT in_itinere") AS $corso) {
	$corsoId = $corso['id'];
	$corsoCodice = $corso['codice'];
	// per prima cosa calcola quante ore sono state firmate
	$ore_firmate = dbGetValue("SELECT COALESCE(SUM(lezione_corso_di_recupero.numero_ore),0) FROM `lezione_corso_di_recupero` WHERE corso_di_recupero_id = $corsoId;");

	$ore_recuperate = $corso['ore_recuperate'];
	$ore_pagamento_extra = $corso['ore_pagamento_extra'];

	$obbligatorie_per_chiudere_le_10_ore = false;

	// le recuperate totali devono essere almeno 10: le prime 10 sono usate in questo modo e non si possono modificare
	if ($ore_recuperate_totale < 10) {
		$obbligatorie_per_chiudere_le_10_ore = true;
		$mancanti_recuperate_totale = 10 - $ore_recuperate_totale;
		$ore_recuperate_calcolate = min($ore_firmate, $mancanti_recuperate_totale);

		// ma se le aveva già inserite in altro modo le lascio così (ad esempio 30 tutte in recuperate)
		$ore_recuperate_calcolate = max($ore_recuperate_calcolate, $ore_recuperate);

		$ore_recuperate_totale += $ore_recuperate_calcolate;
		$ore_pagamento_extra_calcolate = $ore_firmate - $ore_recuperate_calcolate;

		// se qualcosa e' cambiato metto a posto i valori sul db
		if ($ore_recuperate_calcolate != $ore_recuperate || $ore_pagamento_extra_calcolate != $ore_pagamento_extra) {
			$ore_recuperate = $ore_recuperate_calcolate;
			$ore_pagamento_extra = $ore_pagamento_extra_calcolate;
			dbExec("UPDATE corso_di_recupero SET ore_recuperate=$ore_recuperate, ore_pagamento_extra=$ore_pagamento_extra WHERE id = $corsoId;");
		}
	} else {
		// se le recuperate totali sono gia' 10, e se devo scegliere perche' sono ancora tutte a 0, le metto nelle extra
		if (($ore_recuperate + $ore_pagamento_extra) <= 0) {
			$ore_pagamento_extra = $ore_firmate;
			$ore_recuperate = 0;
			dbExec("UPDATE corso_di_recupero SET ore_recuperate=$ore_recuperate, ore_pagamento_extra=$ore_pagamento_extra WHERE id = $corsoId;");
		} else {
			// altrimenti le lascio come sono e aggiorno il totale
			$ore_recuperate_totale += $ore_recuperate;
		}
	}

	// adesso posso scrivere il risultato
	$dataCdr .= '<tr><td class="col-md-7 text-left">'.$corsoCodice.'</td><td class="col-md-1 text-center">'.$ore_firmate.'</td><td class="col-md-1 text-center">'.$ore_recuperate.'</td><td class="col-md-1 text-center">'.$ore_pagamento_extra.'</td><td class="col-md-1 text-center">';

	// il bottone per editarlo solo se non sono obbligatorie per chiudere le 10 ore
	if (! $obbligatorie_per_chiudere_le_10_ore) {
		$dataCdr .= '<button onclick="corsoDiRecuperoPrevisteEdit('.$corsoId.', \''.$corsoCodice.'\', '.$ore_firmate.', '.$ore_recuperate.', '.$ore_pagamento_extra.')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>';
	}
	$dataCdr .= '</td></tr>';
}

// adesso aggiungo tutti i corsi in itinere
foreach(dbGetAll("SELECT DISTINCT corso_di_recupero.* FROM corso_di_recupero INNER JOIN lezione_corso_di_recupero on lezione_corso_di_recupero.corso_di_recupero_id=corso_di_recupero.id WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id AND in_itinere = true") AS $corso) {
	$corsoId = $corso['id'];
	$corsoCodice = $corso['codice'];
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
	$dataCdr .= '<tr><td class="col-md-7 text-left">'.$corsoCodice.'</td><td class="col-md-1 text-center">'.$ore_firmate.'</td><td class="col-md-1 text-center">'.$ore_recuperate.'</td><td class="col-md-1 text-center">'.$ore_pagamento_extra.'</td><td class="col-md-1 text-center"></td></tr>';
}

$dataCdr .= '</table></div>';

echo $dataCdr;
?>

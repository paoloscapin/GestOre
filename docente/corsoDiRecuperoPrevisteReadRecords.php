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
	$ore_firmate = dbGetValue("SELECT COALESCE(SUM(lezione_corso_di_recupero.numero_ore),0) FROM `lezione_corso_di_recupero` WHERE corso_di_recupero_id = $corsoId AND firmato=true;");

	$ore_recuperate = $corso['ore_recuperate'];
	$ore_pagamento_extra = $corso['ore_pagamento_extra'];

	$obbligatorie_per_chiudere_le_10_ore = false;

	// calcola quante sono obbligatorie per chiudere le 10 ore, per quelle non ci deve essere scelta su come pagarle
	$mancanti_recuperate_totale = 10 - $ore_recuperate_totale;
	$ore_recuperate_calcolate = min($ore_firmate, $mancanti_recuperate_totale);

	// se le mancanti sono > 0 , allora queste ore le devo per forza recuperare e non posso modificarle
	if ($mancanti_recuperate_totale > 0) {
		// scrivo le ore e NON metto il bottone di modifica
		$dataCdr .= '<tr><td class="col-md-7 text-left">1'.$corsoCodice.'</td><td class="col-md-1 text-center">'.$ore_recuperate_calcolate.'</td><td class="col-md-1 text-center">'.$ore_recuperate_calcolate.'</td><td class="col-md-1 text-center">'.'0'.'</td><td class="col-md-1 text-center"></td></tr>';

		// aggiorno il totale delle recuperate
		$ore_recuperate_totale += $ore_recuperate_calcolate;

		// queste ore sono state usate
		$ore_firmate -= $ore_recuperate_calcolate;

		// le ore recuperate che rimangono dopo quelle che ho tolto
		$ore_recuperate = max($ore_recuperate - $ore_recuperate_calcolate, 0);

		// se restano altre ore non richieste come recuperate, vanno calcolate come extra
		$ore_pagamento_extra = $ore_firmate - $ore_recuperate;

		// per sicurezza aggiorna la riga sul database per dire quante sono le recuperate
		$ore_recuperate_per_db = $ore_recuperate + $ore_recuperate_calcolate;
		dbExec("UPDATE corso_di_recupero SET ore_recuperate=$ore_recuperate_per_db, ore_pagamento_extra=$ore_pagamento_extra WHERE id = $corsoId;");
	}

	// se sono rimaste delle ore firmate, ore devono essere inserite
	if ($ore_firmate > 0) {
		$dataCdr .= '<tr><td class="col-md-7 text-left">2'.$corsoCodice.'</td><td class="col-md-1 text-center">'.$ore_firmate.'</td><td class="col-md-1 text-center">'.$ore_recuperate.'</td><td class="col-md-1 text-center">'.$ore_pagamento_extra.'</td><td class="col-md-1 text-center">';
		$dataCdr .= '<button onclick="corsoDiRecuperoPrevisteEdit('.$corsoId.', \''.$corsoCodice.'\', '.$ore_firmate.', '.$ore_recuperate.', '.$ore_pagamento_extra.')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>';
		$dataCdr .= '</td></tr>';
	}
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

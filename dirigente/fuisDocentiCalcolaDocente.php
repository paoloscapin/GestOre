<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function calcolaFuisDocente($localDocenteId) {
	global $__anno_scolastico_corrente_id;
	global $__settings;

	// cerca le diarie viaggi di quest'anno
	$query = "
        SELECT
        	SUM(fuis_viaggio_diaria.importo)
        	FROM fuis_viaggio_diaria fuis_viaggio_diaria
        	INNER JOIN viaggio viaggio
        	ON fuis_viaggio_diaria.viaggio_id = viaggio.id
        	WHERE
            	viaggio.docente_id = $localDocenteId
        	AND
            	viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
        ";
	$fuis_viaggio_diaria = 0 + dbGetValue($query);

	// cerca i fuis assegnati di quest'anno
	$query = "
        SELECT
        	SUM(fuis_assegnato.importo)
        	FROM fuis_assegnato fuis_assegnato
        	WHERE
            	fuis_assegnato.docente_id = $localDocenteId
        	AND
            	fuis_assegnato.anno_scolastico_id = $__anno_scolastico_corrente_id
        ";
	$fuis_assegnato = 0 + dbGetValue($query);
	
	// somma i fuis funzionali e con studenti di quest'anno
	$query = "
SELECT
	docente.id,
	ore_dovute.ore_40_sostituzioni_di_ufficio AS ore_dovute_ore_40_sostituzioni_di_ufficio,
	ore_dovute.ore_40_con_studenti AS ore_dovute_ore_40_con_studenti,
	ore_dovute.ore_70_funzionali AS ore_dovute_ore_70_funzionali,
	ore_dovute.ore_70_con_studenti AS ore_dovute_ore_70_con_studenti,
	    
	ore_previste.ore_40_sostituzioni_di_ufficio AS ore_previste_ore_40_sostituzioni_di_ufficio,
	ore_previste.ore_40_con_studenti AS ore_previste_ore_40_con_studenti,
	ore_previste.ore_70_funzionali AS ore_previste_ore_70_funzionali,
	ore_previste.ore_70_con_studenti AS ore_previste_ore_70_con_studenti,
	    
	ore_fatte.ore_40_sostituzioni_di_ufficio AS ore_fatte_ore_40_sostituzioni_di_ufficio,
	ore_fatte.ore_40_con_studenti AS ore_fatte_ore_40_con_studenti,
	ore_fatte.ore_70_funzionali AS ore_fatte_ore_70_funzionali,
	ore_fatte.ore_70_con_studenti AS ore_fatte_ore_70_con_studenti
	    
FROM docente
	    
INNER JOIN ore_dovute
ON ore_dovute.docente_id = docente.id
	    
INNER JOIN ore_previste
ON ore_previste.docente_id = docente.id
	    
INNER JOIN ore_fatte
ON ore_fatte.docente_id = docente.id
	    
WHERE
	docente.id = $localDocenteId
AND
	ore_dovute.anno_scolastico_id = $__anno_scolastico_corrente_id
AND
	ore_previste.anno_scolastico_id = $__anno_scolastico_corrente_id
AND
	ore_fatte.anno_scolastico_id = $__anno_scolastico_corrente_id
";
	$ore = dbGetFirst($query);
	$ore_sostituzioni = $ore['ore_fatte_ore_40_sostituzioni_di_ufficio'] - $ore['ore_dovute_ore_40_sostituzioni_di_ufficio'];
	// ma se configurato per non sottrarre le sostituzioni, ignora questa parte se sono dovute dal docente (mette a 0)
	if (! getSettingsValue('fuis','rimuovi_sostituzioni_non_fatte', true)) {
		if ($ore_sostituzioni < 0) {
			$ore_sostituzioni = 0;
		}
	}
	
	$ore_funzionali = $ore['ore_fatte_ore_70_funzionali'] - $ore['ore_dovute_ore_70_funzionali'];
	$ore_con_studenti = $ore['ore_fatte_ore_70_con_studenti'] - $ore['ore_dovute_ore_70_con_studenti'];

	// se si possono compensare in ore quelle mancanti funzionali con quelle fatte in piu' con studenti lo aggiorna ora
	if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {
		if ($ore_funzionali < 0) {
			$daSpostare = -$ore_funzionali;
			// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
			if ($ore_con_studenti < $daSpostare) {
				$daSpostare = $ore_con_studenti;
			}
			$ore_con_studenti = $ore_con_studenti - $daSpostare;
			$ore_funzionali = $ore_funzionali + $daSpostare;
		}
	}

	// NB: non deve accadere che manchino delle ore con studenti: in quel caso il DS assegnerebbe altre attivita' o Disposizioni
	//     In caso siano rimaste in negativo ore con studenti la cosa viene qui ignorata, visto che in ogni caso il fuis non puo' diventare negativo
	$fuis_funzionale_proposto = $ore_funzionali * $__settings->importi->oreFunzionali;
	$fuis_con_studenti_proposto = $ore_con_studenti * $__settings->importi->oreConStudenti;
	$fuis_sostituzioni_proposto = $ore_sostituzioni * $__settings->importi->oreConStudenti;

	// se non configurato per compensare, i valori negativi devono essere azzerati (se ce ne sono...)
	if (!getSettingsValue('fuis','compensa_in_valore', false)) {
		$fuis_funzionale_proposto = max($fuis_funzionale_proposto, 0);
		$fuis_con_studenti_proposto = max($fuis_con_studenti_proposto, 0);
		$fuis_sostituzioni_proposto = max($fuis_sostituzioni_proposto, 0);
	}

	// CLIL
	// somma i fuis funzionali e con studenti di quest'anno
	$query = "
SELECT
    SUM(ore)
FROM `ore_fatte_attivita_clil`
WHERE
	ore_fatte_attivita_clil.docente_id = $localDocenteId
AND
	ore_fatte_attivita_clil.anno_scolastico_id = $__anno_scolastico_corrente_id
AND
    ore_fatte_attivita_clil.contestata is not true
AND
	ore_fatte_attivita_clil.con_studenti = false
";
	$clil_ore_funzionale_parziale = 0 + dbGetValue($query);

	// anche i gruppi di lavoro clil entrano nel clil funzionale (ma solo nelle ore fatte, dove il responsabile ha inserito la partecipazione)
	$query = "SELECT COALESCE(SUM(gruppo_incontro_partecipazione.ore), 0) FROM gruppo_incontro_partecipazione
			INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
			INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
			WHERE gruppo_incontro_partecipazione.docente_id = $localDocenteId
			AND gruppo_incontro_partecipazione.ha_partecipato = true
			AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id
			AND gruppo_incontro.effettuato = true AND gruppo.dipartimento = false AND gruppo.clil = true;";
	$ore_gruppi_clil = dbGetValue($query);
	$clil_ore_funzionale = $clil_ore_funzionale_parziale + $ore_gruppi_clil;
	debug('clil_ore_funzionale_parziale='.$clil_ore_funzionale_parziale);
	debug('ore_gruppi_clil='.$ore_gruppi_clil);
	debug('clil_ore_funzionale='.$clil_ore_funzionale);

	$query = "
SELECT
    SUM(ore)
FROM `ore_fatte_attivita_clil`
WHERE
	ore_fatte_attivita_clil.docente_id = $localDocenteId
AND
	ore_fatte_attivita_clil.anno_scolastico_id = $__anno_scolastico_corrente_id
AND
    ore_fatte_attivita_clil.contestata is not true
AND
	ore_fatte_attivita_clil.con_studenti = true
";
	$clil_ore_con_studenti = 0 + dbGetValue($query);

	$clil_funzionale_proposto = $clil_ore_funzionale * $__settings->importi->oreFunzionali;
	$clil_con_studenti_proposto = $clil_ore_con_studenti * $__settings->importi->oreConStudenti;

	// prende il valore attualmente registrato
	$query = "SELECT * FROM fuis_docente WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;";
	$fuis_corrente = dbGetFirst($query);
	
	$clil_funzionale_approvato = $clil_funzionale_proposto;
	$clil_con_studenti_approvato = $clil_con_studenti_proposto;
	$fuis_funzionale_approvato = $fuis_funzionale_proposto;
	$fuis_con_studenti_approvato = $fuis_con_studenti_proposto;
	$fuis_sostituzioni_approvato = $fuis_sostituzioni_proposto;
	debug('fuis_funzionale_proposto='.$fuis_funzionale_proposto);
	debug('fuis_funzionale_approvato='.$fuis_funzionale_approvato);
	/*
	if ($fuis_corrente != null && $fuis_corrente['funzionale_approvato'] != null) {
	    $clil_funzionale_approvato = $fuis_corrente['clil_funzionale_approvato'];
	    $clil_con_studenti_approvato = $fuis_corrente['clil_con_studenti_approvato'];
	    $fuis_funzionale_approvato = $fuis_corrente['funzionale_approvato'];
	    $fuis_con_studenti_approvato = $fuis_corrente['con_studenti_approvato'];
	    $fuis_sostituzioni_approvato = $fuis_corrente['sostituzioni_approvato'];
	}
*/
	$totale_proposto = $fuis_sostituzioni_proposto + $fuis_funzionale_proposto + $fuis_con_studenti_proposto;
	$totale_approvato = $fuis_sostituzioni_approvato + $fuis_funzionale_approvato + $fuis_con_studenti_approvato;
	$clil_totale_proposto = $clil_funzionale_proposto + $clil_con_studenti_proposto;
	$clil_totale_approvato = $clil_funzionale_approvato + $clil_con_studenti_approvato;

	// nessuno deve dare soldi indietro alla scuola
	if ($totale_proposto < 0) {
	    $totale_proposto = 0;
	}
	if ($totale_approvato < 0) {
	    $totale_approvato = 0;
	}
	
	$totale_da_pagare = $fuis_assegnato + $totale_approvato + $clil_totale_approvato;
	$query = "
        INSERT INTO fuis_docente (
            `viaggi`, `assegnato`, 
            `clil_funzionale_ore`, `clil_con_studenti_ore`, `funzionale_ore`, `con_studenti_ore`, `sostituzioni_ore`,
            `clil_funzionale_proposto`, `clil_con_studenti_proposto`, `funzionale_proposto`, `con_studenti_proposto`, `sostituzioni_proposto`,
            `clil_funzionale_approvato`, `clil_con_studenti_approvato`, `funzionale_approvato`, `con_studenti_approvato`, `sostituzioni_approvato`,
            `totale_proposto`, `clil_totale_proposto`, `totale_approvato`, `clil_totale_approvato`, `totale_da_pagare`,
            `docente_id`, `anno_scolastico_id`
        )
        VALUES (
            $fuis_viaggio_diaria, $fuis_assegnato,
            $clil_ore_funzionale, $clil_ore_con_studenti, $ore_funzionali, $ore_con_studenti, $ore_sostituzioni,
            $clil_funzionale_proposto, $clil_con_studenti_proposto, $fuis_funzionale_proposto, $fuis_con_studenti_proposto, $fuis_sostituzioni_proposto,
            $clil_funzionale_approvato, $clil_con_studenti_approvato, $fuis_funzionale_approvato, $fuis_con_studenti_approvato, $fuis_sostituzioni_approvato,
            $totale_proposto, $clil_totale_proposto, $totale_approvato, $clil_totale_approvato, $totale_da_pagare,
            $localDocenteId, $__anno_scolastico_corrente_id
        )
        ON DUPLICATE KEY UPDATE
            `viaggi`=$fuis_viaggio_diaria,
            `assegnato`=$fuis_assegnato,
            
            `clil_funzionale_ore`=$clil_ore_funzionale,
            `clil_con_studenti_ore`=$clil_ore_con_studenti,
            `funzionale_ore`=$ore_funzionali,
            `con_studenti_ore`=$ore_con_studenti,
            `sostituzioni_ore`=$ore_sostituzioni,
            
            `clil_funzionale_proposto`=$clil_funzionale_proposto,
            `clil_con_studenti_proposto`=$clil_con_studenti_proposto,
            `funzionale_proposto`=$fuis_funzionale_proposto,
            `con_studenti_proposto`=$fuis_con_studenti_proposto,
            `sostituzioni_proposto`=$fuis_sostituzioni_proposto,
            
            `clil_funzionale_approvato`=$clil_funzionale_approvato,
            `clil_con_studenti_approvato`=$clil_con_studenti_approvato,
            `funzionale_approvato`=$fuis_funzionale_approvato,
            `con_studenti_approvato`=$fuis_con_studenti_approvato,
            `sostituzioni_approvato`=$fuis_sostituzioni_approvato,
            
            `totale_proposto`=$totale_proposto,
            `clil_totale_proposto`=$clil_totale_proposto,
            `totale_approvato`=$totale_approvato,
            `clil_totale_approvato`=$clil_totale_approvato,
            `totale_da_pagare`=$totale_da_pagare
            ;";
	dbExec($query);
}
?>

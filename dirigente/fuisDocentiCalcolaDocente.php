<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function calcolaFuisDocente($localDocenteId) {
    global $__anno_scolastico_corrente_id;

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
	$ore_sostituzioni =  $ore['ore_fatte_ore_40_sostituzioni_di_ufficio'] - $ore['ore_dovute_ore_40_sostituzioni_di_ufficio'];
	$ore_funzionali = $ore['ore_fatte_ore_70_funzionali'] - $ore['ore_dovute_ore_70_funzionali'];
	$ore_con_studenti = $ore['ore_fatte_ore_70_con_studenti'] - $ore['ore_dovute_ore_70_con_studenti'];
	$fuis_funzionale_proposto = $ore_funzionali * 17.5;
	$fuis_con_studenti_proposto = $ore_con_studenti * 35;
	$fuis_sostituzioni_proposto = $ore_sostituzioni * 35;

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
	$clil_ore_funzionale = 0 + dbGetValue($query);

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

	$clil_funzionale_proposto = $clil_ore_funzionale * 17.5;
	$clil_con_studenti_proposto = $clil_ore_con_studenti * 35;

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
	    debug('setting');
	    $clil_funzionale_approvato = $fuis_corrente['clil_funzionale_approvato'];
	    $clil_con_studenti_approvato = $fuis_corrente['clil_con_studenti_approvato'];
	    $fuis_funzionale_approvato = $fuis_corrente['funzionale_approvato'];
	    debug('cambio fuis_funzionale_approvato='.$fuis_funzionale_approvato);
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
	debug($query);
	dbExec($query);
}
?>

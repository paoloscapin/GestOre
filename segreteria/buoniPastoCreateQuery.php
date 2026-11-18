<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// crea una query per i buoni pasto: se il docente vale 0 cerca tutti i docenti
function buoniPastoCreateQuery($mese_da, $mese_a, $docente_id) {
    global $__anno_scolastico_corrente_id;
    global $__anno_scolastico_corrente_anno;

    // calcola l'anno di cui deve fare il limite inferiore e superiore (potrebbero essere diversi come da inizio dicembre a fine gennaio)
    $parti = explode('/', $__anno_scolastico_corrente_anno);
    $anno_da = ($mese_da >= 9) ? $parti[0] : $parti[1];
    $anno_a = ($mese_a >= 9) ? $parti[0] : $parti[1];

    $query = '';

    $query .= "
(
    SELECT
        'Attività' AS tipo,
        docente.cognome AS cognomeDocente,
        docente.nome AS nomeDocente,
        DATE_FORMAT(ore_fatte_attivita.data, '%d/%m/%Y') AS data_attivita,
        DATE_FORMAT(ore_fatte_attivita.ora_inizio, '%H:%i') AS ora,
        ore_fatte_attivita.ore AS numero_ore,
        ore_fatte_attivita.dettaglio AS dettaglio,
        ore_fatte_attivita.data AS ord_data,
        ore_fatte_attivita.ora_inizio AS ord_ora
    FROM ore_fatte_attivita
    INNER JOIN docente ON ore_fatte_attivita.docente_id = docente.id
    INNER JOIN ore_previste_tipo_attivita ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
    WHERE ore_fatte_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id";

    if ($docente_id > 0) {
	    $query .= " AND docente.id = ".$docente_id." ";
    }
    $query .= "
      AND ore_fatte_attivita.data >= '".$anno_da."-".$mese_da."-01'
      AND ore_fatte_attivita.data < '".$anno_a."-".$mese_a."-31'
)

UNION ALL
(
    SELECT 
        'Sportello' AS tipo,
        docente.cognome AS cognomeDocente, 
        docente.nome AS nomeDocente, 
        DATE_FORMAT(sportello.data, '%d/%m/%Y') AS data_attivita, 
        DATE_FORMAT(sportello.ora, '%H:%i') AS ora, 
        sportello.numero_ore AS numero_ore, 
        sportello.categoria AS dettaglio, -- Ora usa la colonna categoria dello sportello
        sportello.data AS ord_data,
        sportello.ora AS ord_ora
    FROM sportello
    INNER JOIN docente ON sportello.docente_id = docente.id
    WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id";

    if ($docente_id > 0) {
	    $query .= " AND docente.id = ".$docente_id." ";
    }
    $query .= "
      AND sportello.firmato = TRUE
      AND sportello.online = FALSE
      AND sportello.data >= '".$anno_da."-".$mese_da."-01'
      AND sportello.data < '".$anno_a."-".$mese_a."-31'
)
UNION ALL
(
    SELECT 
        'Gruppo' AS tipo,
        docente.cognome AS cognomeDocente, 
        docente.nome AS nomeDocente, 
        DATE_FORMAT(gruppo_incontro.data, '%d/%m/%Y') AS data_attivita, 
        DATE_FORMAT(gruppo_incontro.ora, '%H:%i') AS ora, 
        gruppo_incontro_partecipazione.ore AS numero_ore, 
        gruppo.nome AS dettaglio,
        gruppo_incontro.data AS ord_data,
        gruppo_incontro.ora AS ord_ora
    FROM gruppo_incontro_partecipazione
    INNER JOIN docente ON gruppo_incontro_partecipazione.docente_id = docente.id
    INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
    INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
    WHERE gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id";

    if ($docente_id > 0) {
	    $query .= " AND docente.id = ".$docente_id." ";
    }
    $query .= "
      AND gruppo_incontro.effettuato = TRUE
      AND gruppo_incontro_partecipazione.ha_partecipato = TRUE
      AND gruppo_incontro.data >= '".$anno_da."-".$mese_da."-01'
      AND gruppo_incontro.data < '".$anno_a."-".$mese_a."-31'
)
UNION ALL
(
    SELECT
        'Corso di Recupero' AS tipo,
        docente.cognome AS cognomeDocente,
        docente.nome AS nomeDocente,
        DATE_FORMAT(lezione_corso_di_recupero.data, '%d/%m/%Y') AS data_attivita,
        DATE_FORMAT(lezione_corso_di_recupero.orario, '%H:%i') AS ora,
        lezione_corso_di_recupero.numero_ore AS numero_ore,
        lezione_corso_di_recupero.argomento AS dettaglio,
        lezione_corso_di_recupero.data AS ord_data,
        lezione_corso_di_recupero.orario AS ord_ora
    FROM lezione_corso_di_recupero
    INNER JOIN corso_di_recupero ON lezione_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
    INNER JOIN docente ON corso_di_recupero.docente_id = docente.id
    WHERE corso_di_recupero.anno_scolastico_id = $__anno_scolastico_corrente_id";
    if ($docente_id > 0) {
   $query .= " AND docente.id = ".$docente_id." ";
    }
    $query .= "
      AND corso_di_recupero.in_itinere = TRUE
      AND lezione_corso_di_recupero.firmato = TRUE
      AND lezione_corso_di_recupero.data >= '".$anno_da."-".$mese_da."-01'
      AND lezione_corso_di_recupero.data < '".$anno_a."-".$mese_a."-31'
)

ORDER BY cognomeDocente ASC, nomeDocente ASC, ord_data ASC, ord_ora ASC;
        ";
    return $query;
}
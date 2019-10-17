<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	// get docente ID
	$ore_previste_attivita_id = $_POST['id'];

	// Get Docente Details
	$query = "SELECT
            ore_previste_attivita.id as ore_previste_attivita_id,
            ore_previste_attivita.dettaglio,
            CONCAT(docente.cognome, ' ', docente.nome) AS docente,
            ore_previste_attivita.ore,
            ore_previste_tipo_attivita.nome
        FROM
            ore_previste_attivita
        INNER JOIN docente
        ON ore_previste_attivita.docente_id = docente.id
        INNER JOIN ore_previste_tipo_attivita
        ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE
            ore_previste_attivita.anno_scolastico_id = '$__anno_scolastico_corrente_id'
            AND
            ore_previste_attivita.id = '$ore_previste_attivita_id';";
	$ore_previste_attivita = dbGetFirst($query);
	echo json_encode($ore_previste_attivita);
}
?>
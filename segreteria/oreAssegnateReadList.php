<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
	// include Database connection file
	require_once '../common/checkSession.php';
	require_once '../common/connect.php';

	// get values
	$ore_previste_tipo_attivita_id = $_POST['ore_previste_tipo_attivita_id'];

	// Get Details
	$query = "	SELECT
						ore_previste_attivita.id AS ore_previste_attivita_id,
						ore_previste_attivita.dettaglio AS ore_previste_attivita_dettaglio,
						ore_previste_attivita.ore AS ore_previste_attivita_ore,
						ore_previste_attivita.ore_previste_tipo_attivita_id AS ore_previste_attivita_ore_previste_tipo_attivita_id,
						docente.nome AS docente_nome,
						docente.cognome AS docente_cognome
					FROM
						ore_previste_attivita
					INNER JOIN docente docente
					ON ore_previste_attivita.docente_id = docente.id
					WHERE
						ore_previste_attivita.anno_scolastico_id = '$__anno_scolastico_corrente_id'
					AND
						ore_previste_attivita.ore_previste_tipo_attivita_id = '$ore_previste_tipo_attivita_id'
					ORDER BY
						docente.cognome ASC,
						docente.nome ASC
					;
						";
	debug($query);
	$oreAssegnateArray = dbGetAll($query);
	echo json_encode($oreAssegnateArray);
}
else {
	$response['status'] = 200;
	$response['message'] = "Invalid Request!";
}
?>
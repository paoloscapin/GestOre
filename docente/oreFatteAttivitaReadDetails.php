<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// check request
if(isset($_POST['attivita_id']) && isset($_POST['attivita_id']) != "") {
	// include Database connection file
	require_once '../common/connect.php';

	// get docente ID
	$attivita_id = $_POST['attivita_id'];

	// Get Docente Details
	$query = "	SELECT
						ore_fatte_attivita.*,
						registro_attivita.descrizione,
						registro_attivita.studenti,
						ore_previste_tipo_attivita.nome
				FROM
					ore_fatte_attivita ore_fatte_attivita
				INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita
				ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
				LEFT JOIN registro_attivita
				ON registro_attivita.ore_fatte_attivita_id = ore_fatte_attivita.id
				WHERE ore_fatte_attivita.id = '$attivita_id'";
	$response = dbGetFirst($query);
	echo json_encode($response);
}
else {
	$response['status'] = 200;
	$response['message'] = "Invalid Request!";
}
?>
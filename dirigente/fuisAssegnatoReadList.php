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
	$fuis_assegnato_tipo_id = $_POST['fuis_assegnato_tipo_id'];

	$query = "	SELECT
					fuis_assegnato.id AS fuis_assegnato_id,
					fuis_assegnato.importo AS fuis_assegnato_importo,
					fuis_assegnato.fuis_assegnato_tipo_id AS fuis_assegnato_fuis_assegnato_tipo_id,
					docente.id AS docente_id,
					docente.nome AS docente_nome,
					docente.cognome AS docente_cognome
				FROM
					fuis_assegnato
				INNER JOIN docente docente
				ON fuis_assegnato.docente_id = docente.id
				WHERE
					fuis_assegnato.anno_scolastico_id = '$__anno_scolastico_corrente_id'
				AND
					fuis_assegnato.fuis_assegnato_tipo_id = '$fuis_assegnato_tipo_id'
				ORDER BY
					docente.cognome ASC,
					docente.nome ASC
				;
		";
	$resultArray = dbGetAll($query);
	echo json_encode($resultArray);
}
else {
	$response['status'] = 200;
	$response['message'] = "Invalid Request!";
}
?>
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

	$attivita_id = $_POST['attivita_id'];

	$query = "	SELECT
						ore_fatte_attivita_clil.*,
						registro_attivita_clil.descrizione,
						registro_attivita_clil.studenti,
						ore_fatte_attivita_clil_commento.*
				FROM
					ore_fatte_attivita_clil ore_fatte_attivita_clil
				LEFT JOIN registro_attivita_clil ON registro_attivita_clil.ore_fatte_attivita_clil_id = ore_fatte_attivita_clil.id
				LEFT JOIN ore_fatte_attivita_clil_commento ON ore_fatte_attivita_clil_commento.ore_fatte_attivita_clil_id = ore_fatte_attivita_clil.id
				WHERE ore_fatte_attivita_clil.id = '$attivita_id';";
				$response = dbGetFirst($query);
	echo json_encode($response);
}
?>
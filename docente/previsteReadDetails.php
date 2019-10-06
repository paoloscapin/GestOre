<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['attivita_id']) && isset($_POST['attivita_id']) != "") {

	$attivita_id = $_POST['attivita_id'];

	$query = "SELECT * FROM ore_previste_attivita LEFT JOIN ore_previste_attivita_commento ON ore_previste_attivita_commento.ore_previste_attivita_id = ore_previste_attivita.id WHERE ore_previste_attivita.id = '$attivita_id'";
	$response = dbGetFirst($query);
	echo json_encode($response);
}
?>
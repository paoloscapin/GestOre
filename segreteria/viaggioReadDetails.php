<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	$viaggio_id = $_POST['id'];

	$query = "SELECT viaggio.*, docente.cognome, docente.nome FROM `viaggio` INNER JOIN docente on viaggio.docente_id = docente.id WHERE viaggio.id='$viaggio_id'";
	$response = dbGetFirst($query);

	// adesso ricaviamo le ore e l'importo di una eventuale diaria
	$ore = dbGetValue("SELECT COALESCE( (SELECT ore FROM viaggio_ore_recuperate WHERE viaggio_id = $viaggio_id), 0);");
	$diaria = dbGetValue("SELECT COALESCE( (SELECT importo FROM fuis_viaggio_diaria WHERE viaggio_id = $viaggio_id), 0);");

	$response['ore'] = $ore;
	$response['diaria'] = $diaria;
	echo json_encode($response);
}
?>
F<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
	require_once '../common/checkSession.php';

	$viaggio_id = $_POST['viaggio_id'];
	$ore = $_POST['ore'];
	$diaria = $_POST['diaria'];

	$query = "UPDATE viaggio SET ore_richieste = '$ore', richiesta_fuis = $diaria, stato = 'effettuato' WHERE id = '$viaggio_id' ;";

	dbExec($query);
	info("inoltrato viaggio id=$viaggio_id ore_richieste=$ore richiesta_fuis=$diaria");
}
?>
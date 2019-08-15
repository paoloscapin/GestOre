<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
	require_once '../common/connect.php';
	require_once '../common/checkSession.php';

	$studente_per_corso_di_recupero_id = $_POST['studente_per_corso_di_recupero_id'];
	$dbFieldName = $_POST['dbFieldName'];
	$voto = $_POST['voto'];
	$passato = 0;
	if ($voto > 5) {
		$passato = true;
	}

	$query = "UPDATE studente_per_corso_di_recupero SET $dbFieldName = $voto, passato = $passato WHERE id = '$studente_per_corso_di_recupero_id';";
	debug($query);
	dbExec($query);
}
?>
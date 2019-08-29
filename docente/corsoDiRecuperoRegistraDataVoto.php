<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$studente_per_corso_di_recupero_id = $_POST['studente_per_corso_di_recupero_id'];
	$dbFieldName = $_POST['dbFieldName'];
	$value = $_POST['value'];

	$query = "UPDATE studente_per_corso_di_recupero SET $dbFieldName = '$value' WHERE id = '$studente_per_corso_di_recupero_id';";
	dbExec($query);
	info("aggiornato studente_per_corso_di_recupero id=$studente_per_corso_di_recupero_id $dbFieldName=$value");
}
?>
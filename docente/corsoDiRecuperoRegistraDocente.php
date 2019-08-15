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
	$docente_id = $_POST['docente_id'];

	$query = "UPDATE studente_per_corso_di_recupero SET $dbFieldName = $docente_id WHERE id = '$studente_per_corso_di_recupero_id';";
	debug($query);
	dbExec($query);
}
?>
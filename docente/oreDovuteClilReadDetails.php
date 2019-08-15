<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$docente_id = $__docente_id;
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
	$docente_id = $_POST['docente_id'];
}
if(isset($_POST['table_name']) && isset($_POST['table_name']) != "") {
	$table_name = $_POST['table_name'];
}

$query = "SELECT SUM(ore) FROM $table_name WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id AND con_studenti = false;";
debug($query);
$funzionali=dbGetValue($query);

$query = "SELECT SUM(ore) FROM $table_name WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id AND con_studenti = true;";
debug($query);
$con_studenti=dbGetValue($query);

$response = compact('funzionali', 'con_studenti');

echo json_encode($response);
?>

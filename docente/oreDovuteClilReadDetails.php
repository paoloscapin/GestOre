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

// ricupera la somma delle ore funzionali e con studenti fatte e previste in clil
$query = "SELECT COALESCE(SUM(ore),0) FROM $table_name WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id AND con_studenti = false;";
$funzionali=dbGetValue($query);

$query = "SELECT COALESCE(SUM(ore),0) FROM $table_name WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id AND con_studenti = true;";
$con_studenti=dbGetValue($query);

$query = "SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id
AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'funzionali' ;";
$funzionali_previste=dbGetValue($query);

$query = "SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id
AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'con studenti' ;";
$con_studenti_previste=dbGetValue($query);

$response = compact('funzionali', 'con_studenti', 'funzionali_previste', 'con_studenti_previste');

echo json_encode($response);
?>

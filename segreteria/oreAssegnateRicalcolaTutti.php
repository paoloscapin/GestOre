<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');

$query = "	SELECT
				docente.id AS local_docente_id,
				docente.*
				FROM docente
			";
$query .= "WHERE docente.attivo = true ";
$query .= "order by cognome,nome";
$resultArray = dbGetAll($query);

foreach($resultArray as $docente) {
	$docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];

	echo $docenteCognomeNome . ': ';
}
?>

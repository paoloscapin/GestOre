<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

function oreDovuteClilReadDetails($soloTotale, $docente_id) {
	global $__anno_scolastico_corrente_id;

	$query = "SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
	WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id
	AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'funzionali' ;";
	$funzionali_previste=dbGetValue($query);

	$query = "SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
	WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id
	AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'con studenti' ;";
	$con_studenti_previste=dbGetValue($query);

	$response = compact('funzionali_previste', 'con_studenti_previste');
	return $response;
}
?>

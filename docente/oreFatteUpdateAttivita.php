<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
	require_once '../common/checkSession.php';
	require_once '../common/connect.php';
	$attivita_id = $_POST['attivita_id'];
	$tipo_attivita_id = $_POST['tipo_attivita_id'];
	$ore = $_POST['ore'];
	$dettaglio = mysqli_real_escape_string($con, $_POST['dettaglio']);
	$ora_inizio = $_POST['ora_inizio'];
	$data = $_POST['data'];

	$query = '';
	if ($attivita_id > 0) {
		$query = "UPDATE ore_fatte_attivita SET dettaglio = '$dettaglio', ore = '$ore', ore_previste_tipo_attivita_id = '$tipo_attivita_id', ora_inizio = '$ora_inizio', data = '$data' WHERE id = '$attivita_id'";
	} else {
		$query = "INSERT INTO ore_fatte_attivita (dettaglio, ore, ora_inizio, data, ore_previste_tipo_attivita_id, docente_id, anno_scolastico_id) VALUES('$dettaglio', '$ore', '$ora_inizio', '$data', '$tipo_attivita_id', '$__docente_id', '$__anno_scolastico_corrente_id')";
	}
	debug($query);

	dbExec($query);

	require_once '../docente/oreDovuteAggiornaDocente.php';
	oreFatteAggiornaDocente($__docente_id);
}

?>

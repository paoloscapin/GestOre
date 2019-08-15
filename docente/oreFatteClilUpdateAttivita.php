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
	$con_studenti = $_POST['con_studenti'];
	$ore = $_POST['ore'];
	$dettaglio = mysqli_real_escape_string($con, $_POST['dettaglio']);
	$ora_inizio = $_POST['ora_inizio'];
	$data = $_POST['data'];
	info('con_studenti='.$con_studenti);
	$query = '';
	if ($attivita_id > 0) {
		$query = "UPDATE ore_fatte_attivita_clil SET dettaglio = '$dettaglio', ore = '$ore', ora_inizio = '$ora_inizio', data = '$data', con_studenti = $con_studenti WHERE id = '$attivita_id'";
	} else {
		$query = "INSERT INTO ore_fatte_attivita_clil (dettaglio, ore, ora_inizio, data, con_studenti, docente_id, anno_scolastico_id) VALUES('$dettaglio', '$ore', '$ora_inizio', '$data', $con_studenti, '$__docente_id', '$__anno_scolastico_corrente_id')";
	}
	debug($query);

	dbExec($query);

	require_once '../docente/oreDovuteAggiornaDocente.php';
	oreFatteAggiornaDocente($__docente_id);
}

?>

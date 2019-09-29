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

	$docente_id = $_POST['docente_id'];
	$ore_previste_attivita_id = $_POST['ore_previste_attivita_id'];
	$update_tipo_attivita_id = $_POST['update_tipo_attivita_id'];
	$update_ore = $_POST['update_ore'];
	$update_dettaglio = mysqli_real_escape_string($con, $_POST['update_dettaglio']);

	$query = '';
	if ($ore_previste_attivita_id > 0) {
		$query = "UPDATE ore_previste_attivita SET dettaglio = '$update_dettaglio', ore = '$update_ore', ore_previste_tipo_attivita_id	 = '$update_tipo_attivita_id' WHERE id = '$ore_previste_attivita_id'";
		info("aggiornata ore_previste_attivita id=$ore_previste_attivita_id dettaglio=$update_dettaglio ore=$update_ore ore_previste_tipo_attivita_id=$update_tipo_attivita_id docente_id=$docente_id");
	} else {
		$query = "INSERT INTO ore_previste_attivita (dettaglio, ore, ore_previste_tipo_attivita_id, docente_id, anno_scolastico_id) VALUES('$update_dettaglio', '$update_ore', '$update_tipo_attivita_id', '$docente_id', '$__anno_scolastico_corrente_id')";
		$id = dblastId();
		info("aggiunto ore_previste_attivita id=$id dettaglio=$update_dettaglio ore=$update_ore ore_previste_tipo_attivita_id=$update_tipo_attivita_id docente_id=$docente_id");
		}
	dbExec($query);

	require_once '../docente/oreDovuteAggiornaDocente.php';
	orePrevisteAggiornaDocente($__docente_id);
}

?>

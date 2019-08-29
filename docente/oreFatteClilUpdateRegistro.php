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
	$registro_id = $_POST['registro_id'];
	$attivita_id = $_POST['attivita_id'];
	$descrizione = mysqli_real_escape_string($con, $_POST['descrizione']);
	$studenti = mysqli_real_escape_string($con, $_POST['studenti']);

	$query = '';
	if ($registro_id > 0) {
		$query = "UPDATE registro_attivita_clil SET descrizione = '$descrizione', studenti = '$studenti', ore_fatte_attivita_clil_id = '$attivita_id' WHERE id = '$registro_id'";
	} else {
		$query = "INSERT INTO registro_attivita_clil (descrizione, studenti, ore_fatte_attivita_clil_id) VALUES('$descrizione', '$studenti', '$attivita_id')";
	}
	dbExec($query);

	dbExec("UPDATE ore_fatte_attivita_clil SET ultima_modifica = CURRENT_TIMESTAMP WHERE id = $attivita_id;");
}

?>

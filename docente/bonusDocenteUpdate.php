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
	$bonus_docente_id = $_POST['bonus_docente_id'];
	$rendiconto = mysqli_real_escape_string($con, $_POST['rendiconto']);

	$query = "UPDATE bonus_docente SET rendiconto_evidenze = '$rendiconto' WHERE id = '$bonus_docente_id'";
	dbExec($query);
}

?>

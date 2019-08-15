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

	$fatte_id = $_POST['fatte_id'];
	$numeroFatte = escapePost('numeroFatte');
	$docente_incaricato_cognomenome = $_POST['docente_incaricato_cognomenome'];
	
	$query = "UPDATE ore_fatte SET ore_40_sostituzioni_di_ufficio = '$numeroFatte' WHERE id = '$fatte_id'";
	debug($query);
	dbExec($query);
	info('docente='.$docente_incaricato_cognomenome.': aggiornate sostituzioni fatte numero='.$numeroFatte);
}
?>
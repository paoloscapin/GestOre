<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {

	$docente_id = $_POST['docente_id'];
	$id = $_POST['id'];

	dbExec("DELETE FROM ore_previste_attivita_commento WHERE ore_previste_attivita_id = '$id'");
	dbExec("DELETE FROM ore_previste_attivita WHERE id = '$id'");
	info("cancellata ore_previste_attivita id=$ore_previste_attivita_id dettaglio=$update_dettaglio ore=$update_ore ore_previste_tipo_attivita_id=$update_tipo_attivita_id docente_id=$docente_id");

	require_once '../docente/oreDovuteAggiornaDocente.php';
	orePrevisteAggiornaDocente($docente_id);
}
?>
<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$docente_id = $_POST['docente_id'];

	require_once '../docente/oreDovuteAggiornaDocente.php';
	oreFatteAggiornaDocente($docente_id);
}
?>

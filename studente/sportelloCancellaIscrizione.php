<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
 ruoloRichiesto('studente','dirigente');

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	$sportello_id = $_POST['id'];
	$materia = $_POST['materia'];

	dbExec("DELETE FROM sportello_studente WHERE sportello_id = $sportello_id AND studente_id  = $__studente_id");
	info("cancellata iscrizione di $__studente_cognome $__studente_nome dallo sportello di $materia sportello_id=$sportello_id");
}
?>
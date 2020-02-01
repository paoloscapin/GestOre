<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('studente','dirigente');

if(isset($_POST)) {
	$sportello_id = $_POST['id'];
	$materia = $_POST['materia'];

	dbExec("INSERT INTO sportello_studente(iscritto, sportello_id, studente_id) VALUES(true, $sportello_id, $__studente_id)");
	$last_id = dblastId();
	info("iscritto $__studente_cognome $__studente_nome allo sportello di $materia sportello_id=$sportello_id");
}
?>
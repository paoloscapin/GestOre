<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('segreteria-docenti','dirigente');

if(isset($_POST)) {
	$docente_id = $_POST['docente_id'];
	$dataSostituzione = $_POST['dataSostituzione'];

	$query = "INSERT INTO sostituzione_docente(docente_id, data, ora, anno_scolastico_id) VALUES($docente_id, '$dataSostituzione', 1, $__anno_scolastico_corrente_id)";
	dbExec($query);

	// trova l'id inserito
	$last_id = dblastId();

	info("aggiunto sostituzione id=$last_id docente_id=$docente_id dataSostituzione=$dataSostituzione");
}
?>
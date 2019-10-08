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
	$nome = $_POST['nome'];
	$commento = $_POST['commento'];
	$max_ore = $_POST['max_ore'];
	$dipartimento = $_POST['dipartimento'];
	$responsabile_docente_id = $_POST['responsabile_docente_id'];
	$ore_responsabile = $_POST['ore_responsabile'];

	$query = "INSERT INTO gruppo(nome, dipartimento, commento, max_ore, anno_scolastico_id, responsabile_docente_id) VALUES('$nome', '$dipartimento', '$commento', '$max_ore', $__anno_scolastico_corrente_id, $responsabile_docente_id)";
	dbExec($query);
	$last_id = dblastId();

	info("aggiunto gruppo id=$last_id nome=$nome commento=$commento max_ore=$max_ore dipartimento=$dipartimento responsabile_docente_id=$responsabile_docente_id");

    // TODO: aggiunge le ore al docente responsabile e poi
    //require_once '../docente/oreDovuteAggiornaDocente.php';
	//oreFatteAggiornaDocente($responsabile_docente_id);
}
?>
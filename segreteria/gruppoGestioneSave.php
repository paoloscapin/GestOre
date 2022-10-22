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
	$id = $_POST['id'];
	$nome = $_POST['nome'];
	$commento = $_POST['commento'];
	$max_ore = $_POST['max_ore'];
	$dipartimento = $_POST['dipartimento'];
	$clil = $_POST['clil'];
	$responsabile_docente_id = $_POST['responsabile_docente_id'];

    if ($id > 0) {
		$query = "UPDATE gruppo SET nome = '$nome', commento = '$commento', max_ore = '$max_ore', dipartimento = '$dipartimento', clil = '$clil', responsabile_docente_id = $responsabile_docente_id WHERE id = '$id'";
		dbExec($query);
		info("aggiornato gruppo id=$id nome=$nome categoria=$categoria ore=$ore ore_max=$ore_max valido=$valido previsto_da_docente=$previsto_da_docente inserito_da_docente=$inserito_da_docente da_rendicontare=$da_rendicontare");
	} else {
		$query = "INSERT INTO gruppo(nome, dipartimento, commento, max_ore, clil, anno_scolastico_id, responsabile_docente_id) VALUES('$nome', '$dipartimento', '$commento', '$max_ore', '$clil', $__anno_scolastico_corrente_id, $responsabile_docente_id)";
		dbExec($query);
		$id = dblastId();
		info("aggiunto gruppo id=$last_id nome=$nome commento=$commento max_ore=$max_ore dipartimento=$dipartimento clil=$clil responsabile_docente_id=$responsabile_docente_id");
	}
}
?>
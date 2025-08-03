<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'docente');

if(isset($_POST)) {
	$id = $_POST['id'];
	$id_programma = $_POST['id_programma'];
	$ordine = $_POST['ordine'];
	$titolo = $_POST['titolo'];
	$conoscenze = $_POST['conoscenze'];
	$abilita = $_POST['abilita'];

	$titolo = str_replace("'","''",$titolo);
	$conoscenze = str_replace("'","''",$conoscenze);
	$abilita = str_replace("'","''",$abilita);
	$titolo = str_replace('"',"''",$titolo);
	$conoscenze = str_replace('"',"''",$conoscenze);
	$abilita = str_replace('"',"''",$abilita);
	date_default_timezone_set("Europe/Rome");
    $update = date("Y-m-d H-i-s");
	$id_utente = $__utente_id;
	if ($id > 0) {
		$query = "UPDATE programma_minimi_moduli SET id_programma = '$id_programma', id_utente = '$id_utente', ordine = '$ordine', nome = '$titolo', conoscenze = '$conoscenze', abilita = '$abilita', updated = '$update' WHERE id = '$id'";
		dbExec($query);
		info("aggiornato programma minimi modulo id=$id id_programma=$id_programma id_utente=$id_utente updated=$update");
	} else {
		$query = "INSERT INTO programma_minimi_moduli(id_programma,ordine,nome,conoscenze,abilita,id_utente,updated) VALUES('$id_programma', '$ordine', '$titolo', '$conoscenze', '$abilita', '$id_utente','$update')";
		dbExec($query);
		$id = dblastId();
		info("aggiunto programma minimi modulo id=$id  id_programma=$id_programma id_utente=$id_utente updated=$update");
	}
}
?>

<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'docente');

function pulisciTestoProgrammaIniziale($testo) {
	$testo = html_entity_decode((string)$testo, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$testo = str_replace("\xc2\xa0", ' ', $testo);
	$testo = preg_replace('/&(nbsp|amp;nbsp);/i', ' ', $testo);
	$testo = str_replace(['__MODULE_TITLE__', '__SECTION_HEADING__'], '', $testo);
	$testo = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $testo);
	return $testo;
}

if(isset($_POST)) {
	$id = $_POST['id'];
	$id_programma = $_POST['id_programma'];
	$ordine = $_POST['ordine'];
	$titolo = $_POST['titolo'];
	$conoscenze = pulisciTestoProgrammaIniziale($_POST['conoscenze']);
	$abilita = pulisciTestoProgrammaIniziale($_POST['abilita']);
	$competenze = pulisciTestoProgrammaIniziale($_POST['competenze']);
	$periodo = pulisciTestoProgrammaIniziale($_POST['periodo']);

	$titolo = str_replace("'","''",$titolo);
	$conoscenze = str_replace("'","''",$conoscenze);
	$abilita = str_replace("'","''",$abilita);
	$competenze = str_replace("'","''",$competenze);
	$periodo = str_replace("'","''",$periodo);
	date_default_timezone_set("Europe/Rome");
    $update = date("Y-m-d H-i-s");
	$id_utente = $__utente_id;
	if ($id > 0) {
		$query = "UPDATE programmi_iniziali_moduli SET id_programma = '$id_programma', id_utente = '$id_utente', ordine = '$ordine', nome = '$titolo', conoscenze = '$conoscenze', abilita = '$abilita', competenze = '$competenze', periodo = '$periodo', updated = '$update' WHERE id = '$id'";
		dbExec($query);
		info("aggiornato programma iniziale modulo id=$id id_programma=$id_programma id_utente=$id_utente updated=$update");
	} else {
		$query = "INSERT INTO programmi_iniziali_moduli(id_programma,ordine,nome,conoscenze,abilita,competenze,periodo,id_utente,updated) VALUES('$id_programma', '$ordine', '$titolo', '$conoscenze', '$abilita', '$competenze', '$periodo','$id_utente','$update')";
		dbExec($query);
		$id = dblastId();
		info("aggiunto programma iniziale modulo id=$id  id_programma=$id_programma id_utente=$id_utente updated=$update");
	}
}
?>

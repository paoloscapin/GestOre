<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica');

if(isset($_POST)) {

	$classe_id = $_POST['classe_id'];
	$classe = $_POST['classe']; // la classe è un testo che mi viene passato da POST
	
	// uso la lista delle classi, quindi uso ID per cercare il nome della classe nel DB
	if ($classe_id != 0) // 
	{
		$classe = dbGetValue("SELECT nome FROM classe WHERE classe.id=" . $classe_id);
	}

	$id = $_POST['id'];
	$data = $_POST['data'];
	$ora = $_POST['ora'];
	$docente_id = $_POST['docente_id'];
	$materia_id = $_POST['materia_id'];
	$numero_ore = $_POST['numero_ore'];
	$argomento = escapePost('argomento');
	$luogo = escapePost('luogo');
	$max_iscrizioni = $_POST['max_iscrizioni'];
	$cancellato = $_POST['cancellato'];
	$firmato = $_POST['firmato'];
	$online = $_POST['online'];
	$clil = $_POST['clil'];
	$orientamento = $_POST['orientamento'];


	if ($id > 0) {
		$query = "UPDATE sportello SET data = '$data', ora = '$ora', docente_id = '$docente_id', materia_id = '$materia_id', numero_ore = '$numero_ore', argomento = '$argomento', luogo = '$luogo', classe = '$classe', classe_id = $classe_id, max_iscrizioni = '$max_iscrizioni', cancellato = $cancellato, firmato = $firmato, online = $online, clil = $clil, orientamento = $orientamento WHERE id = '$id'";
		dbExec($query);
		info("aggiornato sportello id=$id data=$data ora=$ora docente_id=$docente_id materia_id=$materia_id numero_ore=$numero_ore argomento=$argomento luogo=$luogo classe=$classe classe_id=$classe_id max_iscrizioni=$max_iscrizioni online = $online clil = $clil orientamento = $orientamento");
	} else {
		$query = "INSERT INTO sportello(data, ora, docente_id, materia_id, numero_ore, argomento, luogo, classe, classe_id, max_iscrizioni, online, clil, orientamento, anno_scolastico_id) VALUES('$data', '$ora', '$docente_id', '$materia_id', '$numero_ore', '$argomento', '$luogo', '$classe', '$classe_id', '$max_iscrizioni', '$online', '$clil', '$orientamento', $__anno_scolastico_corrente_id)";
		dbExec($query);
		$id = dblastId();
		info("aggiunto sportello id=$id data=$data ora=$ora docente_id=$docente_id materia_id=$materia_id numero_ore=$numero_ore argomento=$argomento luogo=$luogo classe=$classe classe_id=$classe_id max_iscrizioni=$max_iscrizioni online = $online clil = $clil orientamento = $orientamento");
	}
}
?>

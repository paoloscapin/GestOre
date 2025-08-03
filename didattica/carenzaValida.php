<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

ruoloRichiesto('segreteria-didattica','docente');

if (isset($_POST)) {

	$id = $_POST['id'];
	$utente_id = $_POST['id_utente'];
	$stato = $_POST['stato'];
    $nota = $_POST['nota'];
	$nota = str_replace("'","",$nota);
	// recupero il docente_id partendo da utente_id
	$query = "SELECT 
	utente.id AS utente_id,
	utente.cognome AS utente_cognome,
	utente.nome AS utente_nome,
	docente.id AS doc_id,
	docente.nome AS doc_nome,
	docente.cognome AS doc_cognome
    FROM gvgtcyej_gestione_ore.utente
	INNER JOIN docente docente
	ON docente.cognome = utente.cognome AND docente.nome = utente.nome
	WHERE utente.id='$utente_id'";

	if ($stato == 0) {
		$stato = 1;
	} else
		if ($stato == 1) {
			$stato = 0;
		}
	date_default_timezone_set("Europe/Rome");
	$update = date("Y-m-d H-i-s");

	$result = dbGetFirst($query);
	$docente_id = $result['doc_id'];

	if ($stato==0)
	{
		$query = "UPDATE carenze SET id_docente = '0', stato = '$stato', data_validazione = '$update', nota_docente = '' WHERE id = '$id'";
	}
	else
	{
		$query = "UPDATE carenze SET id_docente = '$docente_id', stato = '$stato', data_validazione = '$update', nota_docente = '$nota'WHERE id = '$id'";
	}
	dbExec($query);
	info("aggiornata validazione carenza id=$id  docente_id=$docente_id stato=$stato updated=$update");

}

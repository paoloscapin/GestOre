<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

ruoloRichiesto('segreteria-didattica','docente');

function carenzaResolveDocenteValidatore(int $utenteId): int
{
	global $__docente_id, $__username;

	$docenteId = intval($__docente_id ?? 0);
	if ($docenteId > 0) {
		return $docenteId;
	}

	$username = trim((string)($__username ?? ''));
	if ($username !== '') {
		$docenteId = intval(dbGetValue("SELECT id FROM docente WHERE username = " . dbQ($username) . " LIMIT 1"));
		if ($docenteId > 0) {
			return $docenteId;
		}
	}

	$query = "
		SELECT docente.id
		FROM utente
		INNER JOIN docente
			ON docente.cognome = utente.cognome
		   AND docente.nome = utente.nome
		WHERE utente.id = " . dbI($utenteId) . "
		LIMIT 1
	";

	return intval(dbGetValue($query));
}

if (isset($_POST)) {

	$id = intval($_POST['id'] ?? 0);
	$utente_id = intval($_POST['id_utente'] ?? 0);
	$stato = intval($_POST['stato'] ?? 0);
    $nota = trim((string)($_POST['nota'] ?? ''));

	if ($stato == 0) {
		$stato = 1;
	} else
		if ($stato == 1) {
			$stato = 0;
		}
	date_default_timezone_set("Europe/Rome");
	$update = date("Y-m-d H-i-s");

	$docente_id = carenzaResolveDocenteValidatore($utente_id);

	if ($stato==0)
	{
		$query = "UPDATE carenze SET id_docente = 0, stato = 0, data_validazione = " . dbQ($update) . ", nota_docente = '' WHERE id = " . dbI($id);
	}
	else
	{
		if ($docente_id <= 0) {
			warning("validazione carenza bloccata: impossibile identificare docente validatore carenza id=$id utente_id=$utente_id ruolo=$__utente_ruolo username=$__username");
			http_response_code(400);
			echo "Impossibile validare: docente non identificato.";
			exit;
		}
		$query = "UPDATE carenze SET id_docente = " . dbI($docente_id) . ", stato = 1, data_validazione = " . dbQ($update) . ", nota_docente = " . dbQ($nota) . " WHERE id = " . dbI($id);
	}
	dbExec($query);
	info("aggiornata validazione carenza id=$id  docente_id=$docente_id stato=$stato updated=$update");

}

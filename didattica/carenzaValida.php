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

function carenzaResolveDocenteCarenza(int $carenzaId, int $docenteValidatoreId): int
{
	if ($carenzaId <= 0) {
		return 0;
	}

	$carenza = dbGetFirst("
		SELECT id_classe, id_materia, id_anno_scolastico
		FROM carenze
		WHERE id = " . dbI($carenzaId) . "
		LIMIT 1
	");
	if (!$carenza) {
		return 0;
	}

	$idClasse = intval($carenza['id_classe'] ?? 0);
	$idMateria = intval($carenza['id_materia'] ?? 0);
	$idAnno = intval($carenza['id_anno_scolastico'] ?? 0);
	if ($idClasse <= 0 || $idMateria <= 0 || $idAnno <= 0) {
		return $docenteValidatoreId;
	}

	if ($docenteValidatoreId > 0) {
		$validatoreInsegna = intval(dbGetValue("
			SELECT COUNT(*)
			FROM docente_insegna
			WHERE id_docente = " . dbI($docenteValidatoreId) . "
			  AND id_classe = " . dbI($idClasse) . "
			  AND id_materia = " . dbI($idMateria) . "
			  AND id_anno_scolastico = " . dbI($idAnno) . "
		"));
		if ($validatoreInsegna > 0) {
			return $docenteValidatoreId;
		}
	}

	$docenteMateria = intval(dbGetValue("
		SELECT di.id_docente
		FROM docente_insegna di
		INNER JOIN docente d ON d.id = di.id_docente
		WHERE di.id_classe = " . dbI($idClasse) . "
		  AND di.id_materia = " . dbI($idMateria) . "
		  AND di.id_anno_scolastico = " . dbI($idAnno) . "
		ORDER BY d.cognome, d.nome, d.id
		LIMIT 1
	"));

	return $docenteMateria > 0 ? $docenteMateria : $docenteValidatoreId;
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

	$docente_validatore_id = carenzaResolveDocenteValidatore($utente_id);
	$docente_id = carenzaResolveDocenteCarenza($id, $docente_validatore_id);

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
	info("aggiornata validazione carenza id=$id  docente_id=$docente_id docente_validatore_id=$docente_validatore_id stato=$stato updated=$update");

}

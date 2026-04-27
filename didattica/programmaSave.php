<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'docente', 'dirigente');

function canEditProgrammaMateriaRecord(int $programmaId, int $materiaId = 0): bool
{
	global $__docente_id;
	$is_docente_effettivo = impersonaRuolo('docente') && intval($__docente_id ?? 0) > 0;

	if ($is_docente_effettivo) {
		if (!getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
			return false;
		}

		if (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false)) {
			return true;
		}

		if (!getSettingsValue('programmiMaterie', 'coordinatore_dipartimento_puo_modificare', false)) {
			return false;
		}

		global $__anno_scolastico_corrente_id;

		$coord = dbGetFirst("SELECT id_dipartimento FROM coordinatori_dipartimento WHERE id_anno_scolastico=" . intval($__anno_scolastico_corrente_id) . " AND id_docente=" . intval($__docente_id));
		if ($coord == null) {
			return false;
		}

		if ($materiaId <= 0 && $programmaId > 0) {
			$program = dbGetFirst("SELECT id_materia FROM programma_materie WHERE id=" . intval($programmaId));
			$materiaId = intval($program['id_materia'] ?? 0);
		}
		if ($materiaId <= 0) {
			return false;
		}

		$materia = dbGetFirst("SELECT id_dipartimento FROM materia WHERE id=" . intval($materiaId));
		if ($materia == null) {
			return false;
		}

		return intval($materia['id_dipartimento']) === intval($coord['id_dipartimento']);
	}

	if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
		return true;
	}

	return false;
}

if (isset($_POST)) {
	$id = intval($_POST['id'] ?? 0);
	$anno_id = intval($_POST['anno_id'] ?? 0);
	$indirizzo_id = intval($_POST['indirizzo_id'] ?? 0);
	$materia_id = intval($_POST['materia_id'] ?? 0);

	if (!canEditProgrammaMateriaRecord($id, $materia_id)) {
		http_response_code(403);
		echo 'Non autorizzato';
		exit;
	}

	date_default_timezone_set("Europe/Rome");
	$update = date("Y-m-d H-i-s");
	$utente_id = $__utente_id;
	if ($id > 0) {
		$query = "UPDATE programma_materie SET anno = '$anno_id', id_indirizzo = '$indirizzo_id', id_materia = '$materia_id', id_utente = '$utente_id', updated = '$update' WHERE id = '$id'";
		dbExec($query);
		info("aggiornata materia id=$id  anno=$anno_id id_indirizzo=$indirizzo_id id_materia=$materia_id id_utente=$utente_id updated=$update");
	} else {
		$query = "INSERT INTO programma_materie(anno, id_indirizzo, id_materia, id_utente, updated) VALUES('$anno_id', '$indirizzo_id', '$materia_id', '$utente_id', '$update')";
		dbExec($query);
		$id = dblastId();
		info("aggiunta materia	 id=$id  anno=$anno_id id_indirizzo=$indirizzo_id id_materia=$materia_id id_utente=$utente_id updated=$update");
	}
}
?>

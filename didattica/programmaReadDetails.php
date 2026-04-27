<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

header('Content-Type: application/json; charset=utf-8');

function out($arr)
{
	echo json_encode($arr, JSON_UNESCAPED_UNICODE);
	exit;
}

function canEditProgrammaMateriaRecord(int $programmaId): bool
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

		$program = dbGetFirst("SELECT materia.id_dipartimento
			FROM programma_materie
			INNER JOIN materia ON materia.id = programma_materie.id_materia
			WHERE programma_materie.id=" . intval($programmaId));

		if ($program == null) {
			return false;
		}

		return intval($program['id_dipartimento']) === intval($coord['id_dipartimento']);
	}

	if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
		return true;
	}

	return false;
}

if (!isset($_POST['programma_id']) || $_POST['programma_id'] === '') {
	out(['ok' => false, 'error' => 'programma_id mancante']);
}

$programma_id = (int)$_POST['programma_id'];
if ($programma_id <= 0) {
	out(['ok' => false, 'error' => 'programma_id non valido']);
}

$query = "SELECT
		programma_materie.id as programma_id,
		programma_materie.anno as programma_anno,
		programma_materie.id_materia as programma_idmateria,
		programma_materie.id_indirizzo as programma_idindirizzo,
		programma_materie.updated as programma_updated
	FROM programma_materie
	WHERE programma_materie.id = '$programma_id'";

$programma = dbGetFirst($query);
if ($programma == null) {
	out(['ok' => false, 'error' => 'Programma non trovato']);
}

$programma['ok'] = true;
$programma['can_edit'] = canEditProgrammaMateriaRecord($programma_id) ? 1 : 0;

out($programma);
?>

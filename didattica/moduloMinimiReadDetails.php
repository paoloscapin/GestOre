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

function canEditProgrammaMinimiModulo(int $programmaId): bool
{
	$is_docente_effettivo = impersonaRuolo('docente') && intval($__docente_id ?? 0) > 0;

	if ($is_docente_effettivo) {
		if (!getSettingsValue('programmiMinimi', 'visibile_docenti', false)) {
			return false;
		}

		if (getSettingsValue('programmiMinimi', 'docente_puo_modificare', false)) {
			return true;
		}

		if (!getSettingsValue('programmiMinimi', 'coordinatore_dipartimento_puo_modificare', false)) {
			return false;
		}

		global $__anno_scolastico_corrente_id, $__docente_id;

		$coord = dbGetFirst("SELECT id_dipartimento FROM coordinatori_dipartimento WHERE id_anno_scolastico=" . intval($__anno_scolastico_corrente_id) . " AND id_docente=" . intval($__docente_id));
		if ($coord == null) {
			return false;
		}

		$program = dbGetFirst("SELECT materia.id_dipartimento
		FROM programma_minimi
		INNER JOIN materia ON materia.id = programma_minimi.id_materia
		WHERE programma_minimi.id=" . intval($programmaId));

		if ($program == null) {
			return false;
		}

		return intval($program['id_dipartimento']) === intval($coord['id_dipartimento']);
	}

	if (haRuolo('admin') || haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
		return true;
	}

	return false;
}

if (!isset($_POST['modulo_id']) || $_POST['modulo_id'] === '') {
	out(['ok' => false, 'error' => 'modulo_id mancante']);
}

$modulo_id = (int)$_POST['modulo_id'];
if ($modulo_id <= 0) {
	out(['ok' => false, 'error' => 'modulo_id non valido']);
}

$query = "SELECT
		programma_minimi_moduli.id AS modulo_id,
		programma_minimi_moduli.id_programma AS programma_id,
		programma_minimi_moduli.ordine AS modulo_ordine,
		programma_minimi_moduli.nome AS modulo_nome,
		programma_minimi_moduli.conoscenze AS modulo_conoscenze,
		programma_minimi_moduli.abilita AS modulo_abilita,
		programma_minimi_moduli.updated AS modulo_updated
	FROM programma_minimi_moduli
	WHERE programma_minimi_moduli.id=" . $modulo_id . "
	ORDER BY programma_minimi_moduli.ordine ASC";

$modulo = dbGetFirst($query);
if ($modulo == null) {
	out(['ok' => false, 'error' => 'Modulo non trovato']);
}

$modulo['ok'] = true;
$modulo['can_edit'] = canEditProgrammaMinimiModulo((int)$modulo['programma_id']) ? 1 : 0;

out($modulo);
?>

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

if (!isset($_POST['modulo_id']) || $_POST['modulo_id'] === '') {
	out(['ok' => false, 'error' => 'modulo_id mancante']);
}

$modulo_id = (int)$_POST['modulo_id'];
if ($modulo_id <= 0) {
	out(['ok' => false, 'error' => 'modulo_id non valido']);
}

$query = "	SELECT
					programma_moduli.id AS modulo_id,
					programma_moduli.id_programma AS programma_id,
					programma_moduli.ordine AS modulo_ordine,
					programma_moduli.nome AS modulo_nome,
					programma_moduli.conoscenze AS modulo_conoscenze,
					programma_moduli.abilita AS modulo_abilita,
					programma_moduli.competenze AS modulo_competenze,
					programma_moduli.periodo AS modulo_periodo,
					programma_moduli.updated AS modulo_updated
				FROM programma_moduli
				WHERE programma_moduli.id=$modulo_id ";

$query .= "ORDER BY programma_moduli.ordine ASC";

$modulo = dbGetFirst($query);

$programma_id = $modulo['programma_id'];
/* 2) Calcolo permessi dinamici */
$can_edit = false;

$is_coordinatore = false;
$id_dipartimento = 0;
	
if (haRuolo('docente') && (!haRuolo('admin')) && (!haRuolo('dirigente')) && (!haRuolo('segreteria-didattica'))) 
{
	$query = " SELECT * FROM coordinatori_dipartimento WHERE id_anno_scolastico=" . $__anno_scolastico_corrente_id . " AND id_docente=" . $__docente_id;
	$result = dbGetFirst($query);
	if ($result != null) 
	{
		$is_coordinatore = true;
		$id_dipartimento = $result['id_dipartimento'];
	}
	$query = "SELECT id_materia from programma_materie WHERE id=$programma_id";
	$result = dbGetFirst($query);
	$id_materia = $result['id_materia'];
	if ($is_coordinatore) {
		$query = "SELECT id_dipartimento from materia WHERE id=$id_materia";
		$result = dbGetFirst($query);
		$id_dipartimento_materia = $result['id_dipartimento'];
		if ($id_dipartimento_materia != $id_dipartimento) {
			// non è coordinatore del dipartimento di questa materia
			$is_coordinatore = false;
		}
	}
}
if (haRuolo('admin') || haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
	$can_edit = true;
}
	
if ($__settings->programmiMaterie->visibile_docenti) {
	if (haRuolo('docente')) {
		if ($__settings->programmiMaterie->docente_puo_modificare || $is_coordinatore) {
			$can_edit = true;
		}
	}
}
/* 3) Risposta JSON per JS */
$modulo['ok'] = true;
$modulo['can_edit'] = $can_edit ? 1 : 0;

out($modulo);

<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'docente', 'dirigente');

function canEditProgrammaMateriaModulo(int $programmaId): bool
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

if(isset($_POST)) {
	$id = $_POST['id'];
	$id_programma = $_POST['id_programma'];
	$ordine = $_POST['ordine'];
	$titolo = $_POST['titolo'];
	$conoscenze = $_POST['conoscenze'];
	$abilita = $_POST['abilita'];
	$competenze = $_POST['competenze'];
	$periodo = $_POST['periodo'];

	$titolo = str_replace("'","''",$titolo);
	$conoscenze = str_replace("'","''",$conoscenze);
	$abilita = str_replace("'","''",$abilita);
	$competenze = str_replace("'","''",$competenze);
	$periodo = str_replace("'","''",$periodo);
	$titolo = str_replace('"',"''",$titolo);
	$conoscenze = str_replace('"',"''",$conoscenze);
	$abilita = str_replace('"',"''",$abilita);
	$competenze = str_replace('"',"''",$competenze);
	$periodo = str_replace('"',"''",$periodo);

	if (!canEditProgrammaMateriaModulo((int)$id_programma)) {
		http_response_code(403);
		echo 'Non autorizzato';
		exit;
	}

	date_default_timezone_set("Europe/Rome");
    $update = date("Y-m-d H-i-s");
	$id_utente = $__utente_id;
	if ($id > 0) {
		$query = "UPDATE programma_moduli SET id_programma = '$id_programma', id_utente = '$id_utente', ordine = '$ordine', nome = '$titolo', conoscenze = '$conoscenze', abilita = '$abilita', competenze = '$competenze', periodo = '$periodo', updated = '$update' WHERE id = '$id'";
		dbExec($query);
		info("aggiornato programma modulo id=$id id_programma=$id_programma id_utente=$id_utente updated=$update");
	} else {
		$query = "INSERT INTO programma_moduli(id_programma,ordine,nome,conoscenze,abilita,competenze,periodo,id_utente,updated) VALUES('$id_programma', '$ordine', '$titolo', '$conoscenze', '$abilita', '$competenze', '$periodo','$id_utente','$update')";
		dbExec($query);
		$id = dblastId();
		info("aggiunto programma modulo id=$id  id_programma=$id_programma id_utente=$id_utente updated=$update");
	}
}
?>

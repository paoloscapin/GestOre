<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'docente', 'dirigente');

function pulisciTestoProgrammaMinimi($testo) {
	$testo = html_entity_decode((string)$testo, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$testo = str_replace("\xc2\xa0", ' ', $testo);
	$testo = preg_replace('/&(nbsp|amp;nbsp);/i', ' ', $testo);
	$testo = str_replace(['__MODULE_TITLE__', '__SECTION_HEADING__'], '', $testo);
	$testo = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $testo);
	return $testo;
}

function canEditProgrammaMinimiModulo(int $programmaId): bool
{
	global $__anno_scolastico_corrente_id, $__docente_id;
	$is_admin_effettivo = haRuolo('admin') || haRuolo('dirigente') || haRuolo('segreteria-didattica');
	$is_docente_effettivo = impersonaRuolo('docente') && intval($__docente_id ?? 0) > 0 && !$is_admin_effettivo;

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

	if ($is_admin_effettivo) {
		return true;
	}

	return false;
}

if(isset($_POST)) {
	$id = $_POST['id'];
	$id_programma = $_POST['id_programma'];
	$ordine = $_POST['ordine'];
	$titolo = $_POST['titolo'];
	$conoscenze = pulisciTestoProgrammaMinimi($_POST['conoscenze']);
	$abilita = pulisciTestoProgrammaMinimi($_POST['abilita']);

	$titolo = str_replace("'","''",$titolo);
	$conoscenze = str_replace("'","''",$conoscenze);
	$abilita = str_replace("'","''",$abilita);
	$titolo = str_replace('"',"''",$titolo);
	$conoscenze = str_replace('"',"''",$conoscenze);
	$abilita = str_replace('"',"''",$abilita);

	if (!canEditProgrammaMinimiModulo((int)$id_programma)) {
		http_response_code(403);
		echo 'Non autorizzato';
		exit;
	}

	date_default_timezone_set("Europe/Rome");
    $update = date("Y-m-d H-i-s");
	$id_utente = $__utente_id;
	if ($id > 0) {
		$query = "UPDATE programma_minimi_moduli SET id_programma = '$id_programma', id_utente = '$id_utente', ordine = '$ordine', nome = '$titolo', conoscenze = '$conoscenze', abilita = '$abilita', updated = '$update' WHERE id = '$id'";
		dbExec($query);
		info("aggiornato programma minimi modulo id=$id id_programma=$id_programma id_utente=$id_utente updated=$update");
	} else {
		$query = "INSERT INTO programma_minimi_moduli(id_programma,ordine,nome,conoscenze,abilita,id_utente,updated) VALUES('$id_programma', '$ordine', '$titolo', '$conoscenze', '$abilita', '$id_utente','$update')";
		dbExec($query);
		$id = dblastId();
		info("aggiunto programma minimi modulo id=$id  id_programma=$id_programma id_utente=$id_utente updated=$update");
	}
}
?>

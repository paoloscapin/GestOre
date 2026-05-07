<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'docente');
require_once __DIR__ . '/programmiAutoreUtils.php';

function sanitizeProgrammaSvoltoRichHtml(string $html): string
{
	$html = trim($html);
	if ($html === '') {
		return '';
	}

	$html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
	$html = preg_replace_callback('/<ol\b([^>]*)>/i', function ($matches) {
		$attrs = strtolower($matches[1] ?? '');
		if (strpos($attrs, 'lower-alpha') !== false || preg_match('/type\s*=\s*["\']?a/i', $attrs)) {
			return '<ol type="a">';
		}
		return '<ol type="1">';
	}, $html);
	$html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><u><h4><blockquote>');
	$html = preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', function ($matches) {
		$tag = strtolower($matches[1] ?? '');
		$attrs = $matches[2] ?? '';
		if ($tag === 'ol' && preg_match('/type\s*=\s*["\']?([aA1])["\']?/i', $attrs, $typeMatch)) {
			return '<ol type="' . $typeMatch[1] . '">';
		}
		return '<' . $tag . '>';
	}, $html);
	$html = str_ireplace(['<b>', '</b>', '<i>', '</i>'], ['<strong>', '</strong>', '<em>', '</em>'], $html);

	return trim($html);
}

function programmiSvoltiHasProgramField(string $columnName): bool
{
	static $cache = [];
	if (!array_key_exists($columnName, $cache)) {
		$row = dbGetFirst("SHOW COLUMNS FROM programmi_svolti LIKE '" . dbEscape($columnName) . "'");
		$cache[$columnName] = ($row != null);
	}

	return $cache[$columnName];
}

if (isset($_POST)) {

	$id = $_POST['id'];
	$docente_id = $_POST['docente_id'];
	$classe_id = $_POST['classe_id'];
	$materia_id = $_POST['materia_id'];
	$duplica = $_POST['duplica'];
	$share = $_POST['share'];
	$metodologie_programma = sanitizeProgrammaSvoltoRichHtml((string)($_POST['metodologie_programma'] ?? ''));
	$criteri_valutazione_programma = sanitizeProgrammaSvoltoRichHtml((string)($_POST['criteri_valutazione_programma'] ?? ''));
	$testi_materiali_programma = sanitizeProgrammaSvoltoRichHtml((string)($_POST['testi_materiali_programma'] ?? ''));
	$extraSet = '';
	$extraColumns = [];
	$extraValues = [];

	if (programmiSvoltiHasProgramField('metodologie')) {
		$extraSet .= ", metodologie = '" . dbEscape($metodologie_programma) . "'";
		$extraColumns[] = 'metodologie';
		$extraValues[] = "'" . dbEscape($metodologie_programma) . "'";
	}
	if (programmiSvoltiHasProgramField('criteri_valutazione')) {
		$extraSet .= ", criteri_valutazione = '" . dbEscape($criteri_valutazione_programma) . "'";
		$extraColumns[] = 'criteri_valutazione';
		$extraValues[] = "'" . dbEscape($criteri_valutazione_programma) . "'";
	}
	if (programmiSvoltiHasProgramField('testi_materiali')) {
		$extraSet .= ", testi_materiali = '" . dbEscape($testi_materiali_programma) . "'";
		$extraColumns[] = 'testi_materiali';
		$extraValues[] = "'" . dbEscape($testi_materiali_programma) . "'";
	}
	date_default_timezone_set("Europe/Rome");
	$update = date("Y-m-d H-i-s");
	$utente_id = programmiUtenteAutoreDaDocente(intval($docente_id), intval($__utente_id));
	$data = '';
	if (($duplica == 'false') && ($share == 'false')) {
		if ($id > 0) {
			$query = "UPDATE programmi_svolti SET id_classe = '$classe_id', id_docente = '$docente_id', id_materia = '$materia_id', id_utente = '$utente_id', updated = '$update' $extraSet WHERE id = '$id'";
			dbExec($query);
			info("aggiornato programma svolto id=$id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_utente=$utente_id updated=$update");
		} else {
			$insertColumns = "id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated";
			$insertValues = "'$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update'";
			if (!empty($extraColumns)) {
				$insertColumns .= ", " . implode(', ', $extraColumns);
				$insertValues .= ", " . implode(', ', $extraValues);
			}
			$query = "INSERT INTO programmi_svolti($insertColumns) VALUES($insertValues)";
			dbExec($query);
			$new_id = dblastId();
			$data = $new_id;
			info("aggiunto programma svolto id=$new_id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");
		}
	} else if ($duplica == 'true')
	{

		// verifico se esiste già la classe su cui voglio duplicare il programma
		$query = "SELECT * from programmi_svolti WHERE id_classe='$classe_id' AND id_docente='$docente_id' AND id_materia='$materia_id'";
		$result = dbGetFirst($query);
		
		if ($result!=null)
		{
		  $data = 'Programma già esistente';	
		}
		else
		{
			// creo il programma vuoto per la nuova classe
			$insertColumns = "id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated";
			$insertValues = "'$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update'";
			if (!empty($extraColumns)) {
				$insertColumns .= ", " . implode(', ', $extraColumns);
				$insertValues .= ", " . implode(', ', $extraValues);
			}
			$query = "INSERT INTO programmi_svolti($insertColumns) VALUES($insertValues)";
			dbExec($query);
			$new_id = dblastId();
			info("aggiunto programma svolto id=$new_id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");

			// duplico i moduli collegati al programma originale
			$query = "INSERT INTO programmi_svolti_moduli (ID_PROGRAMMA, ORDINE, NOME, CONTENUTO, ID_UTENTE, UPDATED)
			SELECT $new_id AS ID_PROGRAMMA, ORDINE, NOME, CONTENUTO, ID_UTENTE, NOW() AS UPDATED FROM programmi_svolti_moduli WHERE ID_PROGRAMMA = $id";
			dbExec($query);
			info("duplicati i moduli del programma svolto id=$id e li ho collegati al nuovo programma svolto id=$new_id");
		}
	}
	else if ($share == 'true')
	{
		// verifico se esiste già la classe su cui voglio duplicare il programma
		$query = "SELECT * from programmi_svolti WHERE id_classe='$classe_id' AND id_docente='$docente_id' AND id_materia='$materia_id'";
		$result = dbGetFirst($query);
		
		if (($result!=null)&&($overwrite!='true'))
		{
		  $data = 'Programma già esistente';	
		}
		else
		{
			// creo il programma vuoto per la nuova classe
			$insertColumns = "id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated";
			$insertValues = "'$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update'";
			if (!empty($extraColumns)) {
				$insertColumns .= ", " . implode(', ', $extraColumns);
				$insertValues .= ", " . implode(', ', $extraValues);
			}
			$query = "INSERT INTO programmi_svolti($insertColumns) VALUES($insertValues)";
			dbExec($query);
			$new_id = dblastId();
			info("aggiunto programma svolto id=$new_id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");
			// duplico i moduli collegati al programma originale
			$query = "INSERT INTO programmi_svolti_moduli (ID_PROGRAMMA, ORDINE, NOME, CONTENUTO, ID_UTENTE, UPDATED)
			SELECT $new_id AS ID_PROGRAMMA, ORDINE, NOME, CONTENUTO, ID_UTENTE, NOW() AS UPDATED FROM programmi_svolti_moduli WHERE ID_PROGRAMMA = $id";
			dbExec($query);
			info("duplicati per il docente id=$docente_id i moduli del programma svolto id=$id e li ho collegati al nuovo programma svolto id=$new_id");
		}
	}
	echo $data;
}
?>

<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'docente', 'dirigente');

function programmaMinimiSaveLooksLikeHtml(string $text): bool
{
	return preg_match('/<\/?(p|div|br|ul|ol|li|h[1-6]|strong|b|em|i|u|blockquote|span)\b/i', $text) === 1;
}

function isProgrammaMinimiSaveUppercase(string $text): bool
{
	$text = trim($text);
	return preg_match('/\p{L}/u', $text) === 1 && preg_match('/\p{Ll}/u', $text) !== 1;
}

function sanitizeProgrammaMinimiSaveRichHtml(string $html): string
{
	$html = html_entity_decode((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$html = str_replace("\xc2\xa0", ' ', $html);
	$html = preg_replace('/&(nbsp|amp;nbsp);/i', ' ', $html);
	$html = str_replace(['__MODULE_TITLE__', '__SECTION_HEADING__'], '', $html);
	$html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $html);
	$html = preg_replace('/<\s*(script|style|meta|link|object|iframe)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html);
	$html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/is', '', $html);
	$html = preg_replace('/\s+(class|id|style)\s*=\s*(["\']).*?\2/is', '', $html);
	$html = preg_replace_callback('/<ol\b([^>]*)>/i', function ($matches) {
		$attrs = strtolower($matches[1] ?? '');
		if (strpos($attrs, 'lower-alpha') !== false || preg_match('/type\s*=\s*["\']?a/i', $attrs)) {
			return '<ol type="a">';
		}
		return '<ol type="1">';
	}, $html);
	$html = preg_replace('/<\s*b\b[^>]*>/i', '<strong>', $html);
	$html = preg_replace('/<\s*\/\s*b\s*>/i', '</strong>', $html);
	$html = preg_replace('/<\s*i\b[^>]*>/i', '<em>', $html);
	$html = preg_replace('/<\s*\/\s*i\s*>/i', '</em>', $html);
	$html = preg_replace('/<\s*h[1-6]\b[^>]*>/i', '<h4>', $html);
	$html = preg_replace('/<\s*\/\s*h[1-6]\s*>/i', '</h4>', $html);
	$html = strip_tags($html, '<p><div><br><ul><ol><li><strong><em><u><h4><blockquote><span>');
	return trim($html);
}

function programmaMinimiSaveLegacyTextToRichHtml(string $text): string
{
	$lines = preg_split('/\r\n|\r|\n/u', str_replace("\t", '  ', $text));
	if ($lines === false) {
		return '';
	}

	$html = '';
	$listOpen = false;

	$closeList = function () use (&$html, &$listOpen): void {
		if ($listOpen) {
			$html .= '</ul>';
			$listOpen = false;
		}
	};

	foreach ($lines as $line) {
		$raw = (string)$line;
		$trimmed = trim($raw);
		if ($trimmed === '') {
			$closeList();
			continue;
		}

		if (preg_match('/^>>\s*(.+)$/u', $trimmed, $m)) {
			$closeList();
			$title = preg_replace('/[.;:]\s*$/u', '', trim($m[1]));
			$html .= '<h4>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>';
			continue;
		}

		if (isProgrammaMinimiSaveUppercase($trimmed) && mb_strlen($trimmed, 'UTF-8') <= 90) {
			$closeList();
			$title = preg_replace('/[.;:]\s*$/u', '', $trimmed);
			$html .= '<h4>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>';
			continue;
		}

		if (preg_match('/^(?:[\x{2022}\x{00b7}\x{25cf}\x{25e6}\x{2043}\x{f0b7}\x{f0a7}\x{f076}]\s+|--\s+|>\s+|-\s+|\*\s+|\d+[\.)]\s+|[a-zA-Z][\.)]\s+)(.+)$/u', ltrim($raw), $m)) {
			if (!$listOpen) {
				$html .= '<ul>';
				$listOpen = true;
			}
			$html .= '<li>' . htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') . '</li>';
			continue;
		}

		$closeList();
		$html .= '<p>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</p>';
	}

	$closeList();
	return sanitizeProgrammaMinimiSaveRichHtml($html);
}

function programmaMinimiSaveEnsureRichHtml($testo): string
{
	$testo = trim((string)$testo);
	if ($testo === '') {
		return '';
	}

	return programmaMinimiSaveLooksLikeHtml($testo)
		? sanitizeProgrammaMinimiSaveRichHtml($testo)
		: programmaMinimiSaveLegacyTextToRichHtml($testo);
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
	$conoscenze = programmaMinimiSaveEnsureRichHtml($_POST['conoscenze']);
	$abilita = programmaMinimiSaveEnsureRichHtml($_POST['abilita']);

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

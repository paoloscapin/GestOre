<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');

if(isset($_POST)) {
	$id = $_POST['id'];
	$categoria = escapePost('categoria');
	$nome = escapePost('nome');
	$ore = $_POST['ore'];
	$ore_max = $_POST['ore_max'];
	$funzionali = $_POST['funzionali'];
	$con_studenti = $_POST['con_studenti'];
	$clil = $_POST['clil'];
	$orientamento = $_POST['orientamento'];
	$valido = $_POST['valido'];
	$previsto_da_docente = $_POST['previsto_da_docente'];
	$inserito_da_docente = $_POST['inserito_da_docente'];
	$da_rendicontare = $_POST['da_rendicontare'];

    if ($id > 0) {
		$query = "UPDATE ore_previste_tipo_attivita SET categoria = '$categoria', nome = '$nome', ore = '$ore', ore_max = '$ore_max', funzionali = '$funzionali', con_studenti = '$con_studenti', clil = '$clil', orientamento = '$orientamento', valido = '$valido', previsto_da_docente = '$previsto_da_docente', inserito_da_docente = '$inserito_da_docente', da_rendicontare = '$da_rendicontare' WHERE id = '$id'";
		dbExec($query);
		info("aggiornato ore_previste_tipo_attivita id=$id nome=$nome categoria=$categoria ore=$ore ore_max=$ore_max funzionali=$funzionali con_studenti=$con_studenti clil=$clil orientamento=$orientamento valido=$valido previsto_da_docente=$previsto_da_docente inserito_da_docente=$inserito_da_docente da_rendicontare=$da_rendicontare");
	} else {
		$query = "INSERT INTO ore_previste_tipo_attivita(categoria, nome, ore, ore_max, funzionali, con_studenti, clil, orientamento, valido, previsto_da_docente, inserito_da_docente, da_rendicontare) VALUES('$categoria', '$nome', '$ore', '$ore_max', '$funzionali', '$con_studenti', '$clil', '$orientamento', '$valido', '$previsto_da_docente', '$inserito_da_docente', '$da_rendicontare')";
		dbExec($query);
		$id = dblastId();
		info("aggiunto ore_previste_tipo_attivita id=$id nome=$nome categoria=$categoria ore=$ore ore_max=$ore_max funzionali=$funzionali con_studenti=$con_studenti clil=$clil orientamento=$orientamento valido=$valido previsto_da_docente=$previsto_da_docente inserito_da_docente=$inserito_da_docente da_rendicontare=$da_rendicontare");
	}
}
?>
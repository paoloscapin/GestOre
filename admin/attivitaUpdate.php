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
	$categoria = $_POST['categoria'];
	$nome = $_POST['nome'];
	$ore = $_POST['ore'];
	$ore_max = $_POST['ore_max'];
	$valido = $_POST['valido'];
	$previsto_da_docente = $_POST['previsto_da_docente'];
	$inserito_da_docente = $_POST['inserito_da_docente'];
	$da_rendicontare = $_POST['da_rendicontare'];

    $query = "UPDATE ore_previste_tipo_attivita SET categoria = '$categoria', nome = '$nome', ore = '$ore', ore_max = '$ore_max', valido = '$valido', previsto_da_docente = '$previsto_da_docente', inserito_da_docente = '$inserito_da_docente', da_rendicontare = '$da_rendicontare' WHERE id = '$id'";
	dbExec($query);
	info("aggiornato ore_previste_tipo_attivita id=$id nome=$nome categoria=$categoria ore=$ore ore_max=$ore_max valido=$valido previsto_da_docente=$previsto_da_docente inserito_da_docente=$inserito_da_docente da_rendicontare=$da_rendicontare");
}
?>
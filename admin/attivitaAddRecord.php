<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('dirigente');

if(isset($_POST['nome'])) {
	$categoria = $_POST['categoria'];
	$nome = $_POST['nome'];
	$ore = $_POST['ore'];
	$ore_max = $_POST['ore_max'];
	$valido = $_POST['valido'];
	$inserito_da_docente = $_POST['inserito_da_docente'];
	$da_rendicontare = $_POST['da_rendicontare'];

	$query = "INSERT INTO ore_previste_tipo_attivita(categoria, nome, ore, ore_max, valido, inserito_da_docente, da_rendicontare) VALUES('$categoria', '$nome', '$ore', '$ore_max', '$valido', '$inserito_da_docente', '$da_rendicontare')";
	dbExec($query);
    $id = dblastId();
	info("aggiunto ore_previste_tipo_attivita id=$id nome=$nome categoria=$categoria ore=$ore ore_max=$ore_max valido=$valido inserito_da_docente=$inserito_da_docente da_rendicontare=$da_rendicontare");
}
?>

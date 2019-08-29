<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['numero_ore']) && isset($_POST['data'])) {

	$data = $_POST['data'];
	$numero_ore = $_POST['numero_ore'];
	$studenti = $_POST['studenti'];
	$materia_id = $_POST['materia_id'];

	$query = "INSERT INTO corso_di_recupero(data, numero_ore, studenti, materia_id, docente_id, anno_scolastico_id) VALUES('$data', '$numero_ore', '$studenti', '$materia_id', '$__docente_id', '$__anno_scolastico_corrente_id')";
    dbExec($query);
    $id = dblastId();
	info("aggiunto corso_di_recupero id=$id data=$data numero_ore=$numero_ore studenti=$studenti materia_id=$materia_id");
}
?>
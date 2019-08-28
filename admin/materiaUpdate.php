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
	$nome = $_POST['nome'];
	$codice = $_POST['codice'];

	// Update details
    $query = "UPDATE materia SET nome = '$nome', codice = '$codice' WHERE id = '$id'";
    dbExec($query);
	info("aggiornato materia id=$id nome=$nome codice=$codice");
}
?>
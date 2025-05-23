<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('admin');

$tableName = "materia";
if(isset($_POST)) {
	$id = $_POST['id'];
    $nome = escapePost('nome');
	$codice = escapePost('codice');

    if ($id > 0) {
        $query = "UPDATE $tableName SET nome = '$nome', codice = '$codice' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato $tableName id=$id nome=$nome codice=$codice");
    } else {
        $query = "INSERT INTO $tableName(nome, codice) VALUES('$nome', '$codice')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto $tableName id=$id nome=$nome codice=$codice");    
    }
}
?>
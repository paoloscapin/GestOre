<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('admin');

$tableName = "fuis_assegnato_tipo";
if(isset($_POST)) {
	$id = $_POST['id'];
    $nome = $_POST['nome'];

    if ($id > 0) {
        $query = "UPDATE $tableName SET nome = '$nome' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato $tableName id=$id nome=$nome");
    } else {
        $query = "INSERT INTO $tableName(nome) VALUES('$nome')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto $tableName id=$id nome=$nome");    
    }
}
?>
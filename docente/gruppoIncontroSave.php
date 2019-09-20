<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-docenti','dirigente');

$tableName = "gruppo_incontro";
if(isset($_POST)) {
	$id = $_POST['id'];
	$gruppo_id = $_POST['gruppo_id'];
    $data = $_POST['data'];
	$ora = $_POST['ora'];
	$ordine_del_giorno = escapePost('ordine_del_giorno');

    if ($id > 0) {
        $query = "UPDATE $tableName SET data = '$data', ora = '$ora', ordine_del_giorno = '$ordine_del_giorno' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato $tableName id=$id data=$data ora=$ora");
    } else {
        $query = "INSERT INTO $tableName(gruppo_id, data, ora, ordine_del_giorno) VALUES('$gruppo_id', '$data', '$ora', '$ordine_del_giorno')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto $tableName id=$id data=$data ora=$ora");    
    }
}
?>
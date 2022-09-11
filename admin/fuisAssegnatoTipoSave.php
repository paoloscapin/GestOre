<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('admin');

if(isset($_POST)) {
	$id = $_POST['id'];
    $nome = $_POST['nome'];
    $codice_citrix = $_POST['codice_citrix'];
    $attivo = $_POST['attivo'];

    if ($id > 0) {
        $query = "UPDATE fuis_assegnato_tipo SET nome = '$nome', codice_citrix = '$codice_citrix', attivo = '$attivo' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato fuis_assegnato_tipo id=$id nome=$nome codice_citrix=$codice_citrix attivo=$attivo");
    } else {
        $query = "INSERT INTO fuis_assegnato_tipo(nome, codice_citrix,  attivo) VALUES('$nome', '$codice_citrix', '$attivo')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto fuis_assegnato_tipo id=$id nome=$nome codice_citrix=$codice_citrix attivo=$attivo");    
    }
}
?>
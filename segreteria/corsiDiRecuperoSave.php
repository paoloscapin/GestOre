<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente','segreteria-docenti');

if(isset($_POST)) {
	$id = $_POST['id'];
    $codice = escapePost('codice');
    $aula = escapePost('aula');
    $docente_id = $_POST['docente_id'];
    $materia_id = $_POST['materia_id'];

    if ($id > 0) {
        $query = "UPDATE corso_di_recupero SET codice = '$codice', aula = '$aula', docente_id = '$docente_id', materia_id = '$materia_id' WHERE id = '$id';";
        dbExec($query);
        info("aggiornato corso_di_recupero id=$id codice=$codice");
    } else {
        $query = "INSERT INTO corso_di_recupero (codice,aula,docente_id,materia_id,anno_scolastico_id) VALUES('$codice', '$aula', $docente_id, $materia_id, $__anno_scolastico_corrente_id);";
        dbExec($query);
        $id = dblastId();
        info("aggiunto corso_di_recupero id=$id codice=$codice");
    }
}
?>
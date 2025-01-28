<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');

$tableName = "bonus_assegnato";
if(isset($_POST)) {
	$id = $_POST['id'];
    $commento = escapePost('commento');
	$importo = $_POST['importo'];
	$docente_id = $_POST['docente_id'];

    if ($id > 0) {
        $query = "UPDATE $tableName SET commento = '$commento', importo = '$importo' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato $tableName id=$id commento=$commento importo=$importo");
    } else {
        $query = "INSERT INTO $tableName(commento, importo, docente_id, anno_scolastico_id) VALUES('$commento', '$importo', $docente_id, $__anno_scolastico_corrente_id)";
        dbExec($query);
        $id = dblastId();
        info("aggiunto $tableName id=$id commento=$commento importo=$importo docente_id=$docente_id");    
    }
}
?>
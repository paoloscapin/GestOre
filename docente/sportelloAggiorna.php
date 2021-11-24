<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-docenti','dirigente');

$tableName = "sportello";
if(isset($_POST)) {
	$id = $_POST['id'];
	$firmato = $_POST['firmato'];
    $studentiDaModificareIdList = json_decode($_POST['studentiDaModificareIdList']);
    
    $query = "UPDATE $tableName SET firmato = $firmato WHERE id = $id";
    dbExec($query);
    info("aggiornato $tableName id=$id firmato=$firmato");

    // aggiorna i partecipanti
    foreach($studentiDaModificareIdList as $studente) {
        $query = "UPDATE sportello_studente SET presente = IF (`presente`, 0, 1) WHERE sportello_studente.id = $studente";
        dbExec($query);
        info("aggiornato id=$studente");

    }

	require_once '../docente/oreDovuteAggiornaDocente.php';
	oreFatteAggiornaDocente($__docente_id);
}
?>
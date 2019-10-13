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
    $verbale = escapePost('verbale');
    $effettuato = $_POST['effettuato'];
    $durata = $_POST['durata'];
    $partecipantiDaModificareIdArray = json_decode($_POST['partecipantiDaModificareIdList']);
    $partecipantiDaModificareDocenteIdArray = json_decode($_POST['partecipantiDaModificareDocenteIdList']);
    
    if ($id > 0) {
        $query = "UPDATE $tableName SET data = '$data', ora = '$ora', ordine_del_giorno = '$ordine_del_giorno', verbale = '$verbale', durata = $durata, effettuato = $effettuato WHERE id = '$id'";
        dbExec($query);
        info("aggiornato $tableName id=$id data=$data ora=$ora");
    } else {
        $query = "INSERT INTO $tableName(gruppo_id, data, ora, ordine_del_giorno, verbale, effettuato, durata) VALUES('$gruppo_id', '$data', '$ora', '$ordine_del_giorno', '$verbale', false, $durata)";
        dbExec($query);
        $lastId = dblastId();
        info("aggiunto $tableName id=$lastId data=$data ora=$ora");    
    }

    if ($id > 0) {
        // aggiorna i partecipanti
        foreach($partecipantiDaModificareIdArray as $docente_partecipa_gruppo_incontro) {
            $query = "UPDATE gruppo_incontro_partecipazione SET ha_partecipato = NOT ha_partecipato, ore = $durata WHERE gruppo_incontro_partecipazione.id = $docente_partecipa_gruppo_incontro";
            dbExec($query);
            info("aggiornato gruppo_incontro_partecipazione docente_partecipa_gruppo_incontro=$docente_partecipa_gruppo_incontro");

        }

        // per sicurezza aggiorna le ore fatte di tutti i partecipanti al gruppo
        require_once '../docente/oreDovuteAggiornaDocente.php';
        $partecipantiIdList = dbGetAll("SELECT docente_id FROM `gruppo_partecipante` WHERE gruppo_id = $gruppo_id");
        foreach($partecipantiIdList as $row) {
            $docente_id = $row['docente_id'];
            oreFatteAggiornaDocente($docente_id);
            info("aggiornate ore docente id=$docente_id");
        }
    } else {
        $query = "INSERT INTO gruppo_incontro_partecipazione(gruppo_incontro_id, docente_id) SELECT $lastId, docente_id FROM `gruppo_partecipante` WHERE gruppo_id = $gruppo_id;";
        dbExec($query);
    }
}
?>
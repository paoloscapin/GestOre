<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-docenti','dirigente');

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
        $query = "UPDATE gruppo_incontro SET data = '$data', ora = '$ora', ordine_del_giorno = '$ordine_del_giorno', verbale = '$verbale', durata = '$durata', effettuato = $effettuato WHERE id = '$id'";
        dbExec($query);
        info("aggiornato gruppo_incontro id=$id data=$data ora=$ora");
    } else {
        $query = "INSERT INTO gruppo_incontro(gruppo_id, data, ora, ordine_del_giorno, verbale, effettuato, durata) VALUES('$gruppo_id', '$data', '$ora', '$ordine_del_giorno', '$verbale', false, '$durata')";
        dbExec($query);
        $lastId = dblastId();
        info("aggiunto gruppo_incontro id=$lastId data=$data ora=$ora");    
    }

    if ($id > 0) {
        // aggiorna i partecipanti
        foreach($partecipantiDaModificareIdArray as $docente_partecipa_gruppo_incontro) {
            $query = "UPDATE gruppo_incontro_partecipazione SET ha_partecipato = NOT ha_partecipato, ore = $durata WHERE gruppo_incontro_partecipazione.id = $docente_partecipa_gruppo_incontro";
            dbExec($query);
            info("aggiornato gruppo_incontro_partecipazione docente_partecipa_gruppo_incontro=$docente_partecipa_gruppo_incontro");

        }
    } else {
        $query = "INSERT INTO gruppo_incontro_partecipazione(gruppo_incontro_id, docente_id) SELECT $lastId, docente_id FROM `gruppo_partecipante` WHERE gruppo_id = $gruppo_id;";
        dbExec($query);
    }
}
?>
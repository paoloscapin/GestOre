<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('docente','segreteria-didattica','dirigente');

 if(isset($_POST['id']) && isset($_POST['id']) != "") {
    $id = $_POST['id'];
    $data = $_POST['data'];

    // cancella prima le partecipazioni di tutti gli studenti
    dbExec("DELETE FROM studente_partecipa_lezione_corso_di_recupero WHERE lezione_corso_di_recupero_id = '$id'");

    // poi cancella la lezione
    dbExec("DELETE FROM lezione_corso_di_recupero WHERE id = '$id'");

    info("cancellata lezione_corso_di_recupero id=$id data=$data");
}
?>
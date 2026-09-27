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
    $cognome = escapePost('cognome');
    $nome = escapePost('nome');

    // cancella prima le partecipazioni dello studente da tutte le lezioni
    dbExec("DELETE FROM studente_partecipa_lezione_corso_di_recupero WHERE studente_per_corso_di_recupero_id = '$id'");

    // poi cancella lo studente
    dbExec("DELETE FROM studente_per_corso_di_recupero WHERE id = '$id'");

    info("cancellato studente per corso di recupero id=$id cognome=$cognome nome=$nome");
}
?>
<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica');

if (isset($_POST)) {
    $id = $_POST['id'];
    $cognome = escapePost('cognome');
    $nome = escapePost('nome');
    $email = escapePost('email');
    $codice_fiscale = escapePost('codice_fiscale');
    $userId = escapePost('userId');
    $attivo = escapePost('attivo');
    $era_attivo = escapePost('era_attivo');

    if ($id > 0) {
        // devo aggiornare un genitore
        $query = "UPDATE genitori SET cognome = '$cognome', nome = '$nome', email = '$email', codice_fiscale = '$codice_fiscale', username = '$userId', attivo = '$attivo' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato genitore id=$id cognome=$cognome nome=$nome email=$email codice_fiscale=$codice_fiscale username=$userId attivo=$attivo era_attivo=$era_attivo");
    } else {
        // devo inserire un nuovo genitore
        $query = "INSERT INTO genitori (cognome, nome, email, codice_fiscale, username, attivo) VALUES ('$cognome', '$nome', '$email', '$codice_fiscale', '$userId', '$attivo')";
        dbExec($query);
        $id = dbLastId();
        info("inserito nuovo genitore id=$id cognome=$cognome nome=$nome email=$email codice_fiscale=$codice_fiscale username=$userId attivo=$attivo");
    }
}

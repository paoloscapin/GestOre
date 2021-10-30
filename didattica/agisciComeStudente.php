<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if (isset($_POST)) {
    require_once '../common/checkSession.php';
    ruoloRichiesto('segreteria-didattica');

    // get values
    $studente_id = $_POST['studente_id'];

    // lo cerco tra gli studenti:
    $studente = dbGetFirst("SELECT * FROM studente WHERE studente.id = '$studente_id'");
    if ($studente != null) {
        $session->set ( 'studente_id', $studente ['id'] );
        $session->set ( 'studente_nome', $studente ['nome'] );
        $session->set ( 'studente_cognome', $studente ['cognome'] );
        $session->set ( 'studente_email', $__useremail );
    }

    $__studente_id = $session->get ( 'studente_id' );
    $__studente_nome = $session->get ( 'studente_nome' );
    $__studente_cognome = $session->get ( 'studente_cognome' );
    $__studente_email = $session->get ( 'studente_email' );
}

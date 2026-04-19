<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if (isset($_POST)) {
    require_once '../common/checkSession.php';
    ruoloRichiesto('segreteria-didattica');

    // get values
    $genitore_id = $_POST['genitore_id'];

    // lo cerco tfra i genitori:
    $genitore = dbGetFirst("SELECT * FROM genitori WHERE genitori.id = '$genitore_id'");
    if ($genitore != null) {
        $session->set ( 'docente_id', null );
        $session->set ( 'docente_nome', null );
        $session->set ( 'docente_cognome', null );
        $session->set ( 'docente_email', null );
        $session->set ( 'studente_id', null );
        $session->set ( 'studente_nome', null );
        $session->set ( 'studente_cognome', null );
        $session->set ( 'studente_email', null );
        $session->set ( 'studente_codice_fiscale', null );
        $session->set ( 'impersona_docente_id', null );
        $session->set ( 'impersona_studente_id', null );
        $session->set ( 'genitore_id', $genitore ['id'] );
        $session->set ( 'genitore_nome', $genitore ['nome'] );
        $session->set ( 'genitore_cognome', $genitore ['cognome'] );
        $session->set ( 'genitore_email', $genitore ['email'] );
        $session->set ( 'genitore_codice_fiscale', $genitore ['codice_fiscale'] );
        $session->set ( 'impersona_attiva', 1 );
        $session->set ( 'impersona_ruolo', 'genitore' );
        $session->set ( 'impersona_genitore_id', $genitore ['id'] );
    }

    $__genitore_id = $session->get ( 'genitore_id' );
    $__genitore_nome = $session->get ( 'genitore_nome' );
    $__genitore_cognome = $session->get ( 'genitore_cognome' );
    $__genitore_email = $session->get ( 'genitore_email' );
    $__genitore_codice_fiscale = $session->get ( 'genitore_codice_fiscale' );
    info("Agisco come genitore id=$__genitore_id nome=$__genitore_nome cognome=$__genitore_cognome email=$__genitore_email codice_fiscale=$__genitore_codice_fiscale");
}

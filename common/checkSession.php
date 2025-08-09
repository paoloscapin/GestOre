<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Util.php';
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';

// start session
if (session_status() == PHP_SESSION_NONE) {
	session_set_cookie_params ($__settings->system->durata_sessione);
	session_start();
}

// configurazione globale
require_once __DIR__ . '/Config.php';

// se la session non contiene username, vai alla pagina di login (passando come location la pagina richiesta
if (!isset($__username) && !$session->has('__username')) {
    if (!isset($__gClient)) {
        $__redirectURL = $__http_base_link . '/index.php';

        //Include Google client library
        require_once __DIR__ . '/google-client-library/src/Google_Client.php';
        require_once __DIR__ . '/google-client-library/src/contrib/Google_Oauth2Service.php';

        //Call Google API
        $gClient = new Google_Client();
        $gClient->setApplicationName($__settings->GoogleAuth->applicationName);
        $gClient->setClientId($__settings->GoogleAuth->clientId);
        $gClient->setClientSecret($__settings->GoogleAuth->clientSecret);
        $gClient->setRedirectUri($__redirectURL);

        $google_oauthV2 = new Google_Oauth2Service($gClient);
    }

    if(isset($_GET['code'])){
        $gClient->authenticate($_GET['code']);
        $_SESSION['token'] = $gClient->getAccessToken();

        // log the new access
        $gpUserProfile = $google_oauthV2->userinfo->get();
        $useremail = $gpUserProfile['email'];
        debug("email letta da profilo Google: " . $useremail);
        infoLogin("utente [" . $useremail . "]: logging in with Google");
        header('Location: ' . filter_var($__redirectURL, FILTER_SANITIZE_URL));
    }

    if (isset($_SESSION['token'])) {
        $gClient->setAccessToken($_SESSION['token']);
    }

    if ($gClient->getAccessToken()) {
        //Get user profile data from google
        $gpUserProfile = $google_oauthV2->userinfo->get();
        
        $useremail = $gpUserProfile['email'];

        if(empty($useremail)){
            $output = '<h3 style="color:red">Some problem occurred, please try again.</h3>';
        }
    } else {
        $authUrl = $gClient->createAuthUrl();
        $output = '<a href="'.filter_var($authUrl, FILTER_SANITIZE_URL).'"><img src="'.$__application_base_path.'/img/glogin.png" alt=""/></a>';
        return;
    }
}

// controlla che tutte le variabili richieste siano settate, oppure caricale
if (!isset($__useremail)) {
    $__useremail = $session->get('__useremail');
}

// se non era in sessione controlla se lo ha appena verificato con google
if (!isset($__useremail)) {
    $__useremail = $useremail;
}

// deve esserci un utente collegato, altrimenti non va bene
if (empty ( $__useremail )) {
    warning ( 'nessun utente collegato!' );
    redirect ( '/error/notlogged.php' );
}

// anche gli studenti hanno utente_id in sessione (= -1)
if (! $session->has ( 'utente_id' )) {
    debug ( 'manca in sessione utente_id' );
    $utente = dbGetFirst("SELECT * FROM utente WHERE utente.email = '$__useremail'");
    if ($utente != null) {
        infoLogin("utente [$utente[username]]: logged in - role=[" . $utente['ruolo'] . "]");
        // lo ho trovato tra gli utenti
        $session->set ( 'utente_id', $utente ['id'] );
        $session->set ( 'username', $utente ['username'] );
        $session->set ( 'utente_nome', $utente ['nome'] );
        $session->set ( 'utente_cognome', $utente ['cognome'] );
        $session->set ( 'utente_ruolo', $utente ['ruolo'] );
        $session->set ( '__useremail', $__useremail );
        if(!empty($utente ['username'])){
            $__username = $session->get ( 'username' );
            $session->set ( '__username',  $__username);
            info('utente [' . $utente['username'] . ']: logged in');
        }
    } else 
    {
        // lo cerco tra gli studenti:
        $studente = dbGetFirst("SELECT * FROM studente WHERE studente.email = '$__useremail'");
        if ($studente != null) {
            // lo ho trovato tra gli studenti: utente id = -1
            infoLogin("utente [" . $studente['nome'] . $studente['cognome'] . "]: logged in - role=[studente]");
            $session->set ( 'utente_id', -1 );
            $session->set ( 'studente_id', $studente ['id'] );
            $session->set ( 'studente_nome', $studente ['nome'] );
            $session->set ( 'studente_cognome', $studente ['cognome'] );
            $session->set ( 'studente_email', $__useremail );

            $session->set ( 'username', $studente ['nome'].".".$studente ['cognome'] );
            $session->set ( 'utente_nome', $studente ['nome'] );
            $session->set ( 'utente_cognome', $studente ['cognome'] );
            $session->set ( 'utente_ruolo', 'studente');
            $session->set ( '__useremail', $__useremail );
            $__username = $session->get ( 'username' );
            $session->set ( '__username',  $__username);
            info("utente [" . $studente['nome'] . $studente['cognome'] . "]: logged in");

        } else {
            $__message = 'utente non trovato: [' . $__useremail . ']';
            infoLogin("utente non trovato: " . $__useremail);
            // non lo ho trovato tra gli utenti e neanche tra gli studenti  
            warning ($__message);
            redirect ( '/error/error.php?message=' . $__message);
            exit ();
        }
    }
} else {
//    debug ( 'esiste utente_id=' . $session->get('utente_id'));
}

$__utente_id = $session->get ( 'utente_id' );
$__username = $session->get ( 'username' );
$__utente_nome = $session->get ( 'utente_nome' );
$__utente_cognome = $session->get ( 'utente_cognome' );
$__utente_ruolo = $session->get ( 'utente_ruolo' );

// controlla se e' un docente deve avere i rispettivi termini
if (!$session->has ( 'docente_id' ) && $session->has ( 'utente_ruolo' ) && ($session->get ( 'utente_ruolo' ) === "docente")) {
    debug ( 'manca in sessione docente_id' );
    $docente = dbGetFirst("SELECT * FROM docente WHERE docente.username = '$__username'");
    if ($docente == null) {
        redirect("/error/unauthorized.php");
        exit ();
    }
    $session->set ( 'docente_id', $docente ['id'] );
    $session->set ( 'docente_nome', $docente ['nome'] );
    $session->set ( 'docente_cognome', $docente ['cognome'] );
    $session->set ( 'docente_email', $__useremail );
} else {
//    debug ( 'esiste docente_id=' . $session->get ( 'docente_id' ) );
}

$__docente_id = $session->get ( 'docente_id' );
$__docente_nome = $session->get ( 'docente_nome' );
$__docente_cognome = $session->get ( 'docente_cognome' );
$__docente_email = $session->get ( 'docente_email' );

$__studente_id = $session->get ( 'studente_id' );
$__studente_nome = $session->get ( 'studente_nome' );
$__studente_cognome = $session->get ( 'studente_cognome' );
$__studente_email = $session->get ( 'studente_email' );

// aggiusta l'anno scolastico
if (! $session->has ( 'anno_scolastico_corrente_anno' )) {
    debug ( 'manca in sessione anno_scolastico_corrente_anno' );
    $anno = dbGetFirst("SELECT * FROM anno_scolastico_corrente");
    $session->set ( 'anno_scolastico_corrente_id', $anno ['anno_scolastico_id'] );
    $session->set ( 'anno_scolastico_corrente_anno', $anno ['anno'] );
    $session->set ( 'anno_scolastico_scorso_id', $anno ['anno_scorso_id'] );
} else {
//    debug ( 'esiste anno_scolastico_corrente_anno=' . $session->get ( 'anno_scolastico_corrente_anno' ) );
}

$__anno_scolastico_corrente_id = $session->get ( 'anno_scolastico_corrente_id' );
$__anno_scolastico_corrente_anno = $session->get ( 'anno_scolastico_corrente_anno' );
$__anno_scolastico_scorso_id = $session->get ( 'anno_scolastico_scorso_id' );
/*
debug ( '__username=' . $__username );
debug ( '__anno_scolastico_corrente_id=' . $__anno_scolastico_corrente_id );
debug ( '__anno_scolastico_corrente_anno=' . $__anno_scolastico_corrente_anno );
debug ( '__anno_scolastico_scorso_id=' . $__anno_scolastico_scorso_id );
debug ( '__utente_id=' . $__utente_id );
debug ( '__utente_nome=' . $__utente_nome );
debug ( '__utente_cognome=' . $__utente_cognome );
debug ( '__utente_ruolo=' . $__utente_ruolo );
debug ( '__docente_id=' . $__docente_id );
debug ( '__docente_nome=' . $__docente_nome );
debug ( '__docente_cognome=' . $__docente_cognome );
debug ( '__docente_email=' . $__docente_email );
debug ( '__studente_id=' . $__studente_id );
debug ( '__studente_nome=' . $__studente_nome );
debug ( '__studente_cognome=' . $__studente_cognome );
debug ( '__studente_email=' . $__studente_email );
*/
?>
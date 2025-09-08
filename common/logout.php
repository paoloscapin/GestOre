<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */


require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/__Log.php';
require_once __DIR__ . '/__Util.php';
require_once '../common/checkSession.php';

$base = $_GET['base'] ?? '';

if (haRuolo('admin'))
{
    debug("Ha ruolo Admin");
}
if (impersonaRuolo('docente'))
{
    debug("Impersona docente");
}
if (impersonaRuolo('studente'))
{
    debug("Impersona studente");
}
if (impersonaRuolo('genitore'))
{
    debug("Impersona genitore");
}
debug("Ruolo attuale: $__utente_ruolo");
debug("Username attuale: $__username");
debug("Docente ID attuale: $__docente_id");
debug("Studente ID attuale: $__studente_id");
debug("Genitore ID attuale: $__genitore_id");

if (haRuolo('admin')) {
    // verifico se sto impersonando qualcun altro:
    if (impersonaRuolo(('docente'))) 
        {
        // se stavo impersonando un docente, torno alla mia sessione
        unset($_SESSION['docente_id']);
        unset($_SESSION['docente_nome']);
        unset($_SESSION['docente_cognome']);
        unset($_SESSION['docente_email']);
        $__docente_id = -1;
        $__docente_nome = '';
        $__docente_cognome = '';
        $__docente_email = '';
        info("Torno alla sessione da docente ad admin");
        redirect('/index.php');
    }
    if (impersonaRuolo(('studente'))) 
        {
        // se stavo impersonando un studente, torno alla mia sessione
        unset($_SESSION['studente_id']);
        unset($_SESSION['studente_nome']);
        unset($_SESSION['studente_cognome']);
        unset($_SESSION['studente_email']);
        unset($_SESSION['studente_codice_fiscale']);
        $__studente_id = -1;
        $__studente_nome = '';
        $__studente_cognome = '';
        $__studente_email = '';
        $__studente_codice_fiscale = '';

        info("Torno alla sessione da studente ad admin");
        redirect('/index.php');
    }
    if (impersonaRuolo(('genitore'))) 
        {
        // se stavo impersonando un genitore, torno alla mia sessione
        unset($_SESSION['genitore_id']);
        unset($_SESSION['genitore_nome']);
        unset($_SESSION['genitore_cognome']);
        unset($_SESSION['genitore_email']);
        unset($_SESSION['genitore_codice_fiscale']);
        $__genitore_id = -1;
        $__genitore_nome = '';
        $__genitore_cognome = '';
        $__genitore_email = '';
        $__genitore_codice_fiscale = '';
        info("Torno alla sessione da genitore ad admin");
        redirect('/index.php');
    }
}

// start session
if (session_status() == PHP_SESSION_NONE) {
	session_set_cookie_params ($__settings->system->durata_sessione);
	session_start();
}

//Include Google client library
require_once __DIR__ . '/google-client-library/src/Google_Client.php';
require_once __DIR__ . '/google-client-library/src/contrib/Google_Oauth2Service.php';

$__username = $session->get ( 'username' );
info('utente ' . $__username . ': logged out');
$session->logout();
//Unset token and user data from session

unset($_SESSION['token']);
unset($_SESSION['userData']);
unset($_SESSION['access_token']);
unset($_SESSION['oauth_provider']);
unset($_SESSION['id']);
unset($_SESSION['username']);
unset($_SESSION['utente_nome']);
unset($_SESSION['utente_cognome']);
unset($_SESSION['utente_ruolo']);
unset($_SESSION['__useremail']);

    if ($base=='docente') 
        {
        // se stavo impersonando un docente, torno alla mia sessione
        unset($_SESSION['docente_id']);
        unset($_SESSION['docente_nome']);
        unset($_SESSION['docente_cognome']);
        unset($_SESSION['docente_email']);
        $__docente_id = -1;
        $__docente_nome = '';
        $__docente_cognome = '';
        $__docente_email = '';
        info("Cancellato sessione da docente");
    }
    if ($base=='studente') 
        {
        // se stavo impersonando un studente, torno alla mia sessione
        unset($_SESSION['studente_id']);
        unset($_SESSION['studente_nome']);
        unset($_SESSION['studente_cognome']);
        unset($_SESSION['studente_email']);
        unset($_SESSION['studente_codice_fiscale']);
        $__studente_id = -1;
        $__studente_nome = '';
        $__studente_cognome = '';
        $__studente_email = '';
        $__studente_codice_fiscale = '';
        info("Cancellato sessione da studente");
    }
    if ($base=='genitore') 
        {
        // se stavo impersonando un genitore, torno alla mia sessione
        unset($_SESSION['genitore_id']);
        unset($_SESSION['genitore_nome']);
        unset($_SESSION['genitore_cognome']);
        unset($_SESSION['genitore_email']);
        unset($_SESSION['genitore_codice_fiscale']);
        $__genitore_id = -1;
        $__genitore_nome = '';
        $__genitore_cognome = '';
        $__genitore_email = '';
        $__genitore_codice_fiscale = '';
        info("Cancellato sessione da genitore");
    }

//Call Google API
$gClient = new Google_Client();
$gClient->setApplicationName($__settings->GoogleAuth->applicationName);
$gClient->setClientId($__settings->GoogleAuth->clientId);
$gClient->setClientSecret($__settings->GoogleAuth->clientSecret);
$gClient->setRedirectUri($__redirectURL);

//Reset OAuth access token
//$gClient->revokeToken();

$status = session_status();
if($status == PHP_SESSION_ACTIVE){
    //Destroy current
    session_destroy();
}

//Redirect to homepage
redirect('/' . 'index.php');
?>

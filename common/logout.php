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

// start session
if (session_status() == PHP_SESSION_NONE) {
	session_set_cookie_params ($__settings->system->durata_sessione);
	session_start();
}

$__redirectURL = $__http_base_link . '/index.php';

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

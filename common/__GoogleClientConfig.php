<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// start session
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params(DURATA_SESSIONE);
    session_start();
}

//Include Google client library
require_once __DIR__ . '/google-client-library/src/Google_Client.php';
require_once __DIR__ . '/google-client-library/src/contrib/Google_Oauth2Service.php';

// Configuration and setup Google API
$clientId = '667798242060-aff3o5kiub2h69vau5hm9lla2hbo40pn.apps.googleusercontent.com'; //Google client ID
$clientSecret = 'Xp2leJOul4SZ3tKerPuuwvYs'; //Google client secret
$redirectURL = 'http://localhost/GestOre/test/index.php'; //Callback URL

//Call Google API
$gClient = new Google_Client();
$gClient->setApplicationName('GestOre');
$gClient->setClientId($__clientId);
$gClient->setClientSecret($__clientSecret);
$gClient->setRedirectUri($__redirectURL);

$google_oauthV2 = new Google_Oauth2Service($gClient);

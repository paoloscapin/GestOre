<?php

@session_name('GESTORESESSID');
session_start();

require_once __DIR__ . '/path.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/google-client-library/src/Google_Client.php';
require_once __DIR__ . '/google-client-library/src/contrib/Google_Oauth2Service.php';

foreach ([
    'token',
    'access_token',
    'refresh_token',
    'google_user',
    'username',
    '__username',
    '__useremail',
    'utente_id',
    'utente_nome',
    'utente_cognome',
    'utente_ruolo',
    'docente_id',
    'docente_nome',
    'docente_cognome',
    'docente_email',
    'studente_id',
    'studente_nome',
    'studente_cognome',
    'studente_email',
    'genitore_id',
    'genitore_nome',
    'genitore_cognome',
    'genitore_email',
    'personale_ata_id',
    'personale_ata_nome',
    'personale_ata_cognome',
    'personale_ata_email',
] as $key) {
    unset($_SESSION[$key]);
}

$redirectUrl = $__http_base_link . '/index.php';

$gClient = new Google_Client();
$gClient->setApplicationName($__settings->GoogleAuth->applicationName);
$gClient->setClientId($__settings->GoogleAuth->clientId);
$gClient->setClientSecret($__settings->GoogleAuth->clientSecret);
$gClient->setRedirectUri($redirectUrl);
new Google_Oauth2Service($gClient);

$authUrl = $gClient->createAuthUrl();
$separator = strpos($authUrl, '?') === false ? '?' : '&';
$authUrl .= $separator . http_build_query([
    'prompt' => 'select_account',
]);

header('Location: ' . $authUrl);
exit;

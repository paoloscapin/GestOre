<?php

require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/google-client-library/src/Google_Client.php';
require_once __DIR__ . '/../common/__Log.php';

ruoloRichiesto('admin');

$gc = $__settings->local->googleCalendar ?? null;

if ($gc == null) {
    die('Configurazione local.googleCalendar mancante in GestOre.json');
}

$clientId = $gc->clientId ?? '';
$clientSecret = $gc->clientSecret ?? '';

if ($clientId == '' || $clientSecret == '') {
    die('clientId o clientSecret mancanti in local.googleCalendar');
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

$redirectUri = $protocol . '://' .
    $_SERVER['HTTP_HOST'] .
    '/GestOre/api/googleCalendarAuth.php';

$client = new Google_Client();

$client->setApplicationName($gc->applicationName ?? 'GestOre Google Calendar');
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);

$client->setAccessType('offline');
$client->setApprovalPrompt('force');

$client->setScopes([
    'https://www.googleapis.com/auth/calendar',
    'https://www.googleapis.com/auth/calendar.events'
]);

if (!isset($_GET['code'])) {

    $authUrl = $client->createAuthUrl();

    echo '<h2>Autorizzazione Google Calendar GestOre</h2>';

    echo '<p>Account previsto: <strong>' .
        htmlspecialchars($gc->accountEmail ?? '') .
        '</strong></p>';

    echo '<p><a href="' .
        htmlspecialchars($authUrl) .
        '">Autorizza Google Calendar</a></p>';

    exit;
}

$client->authenticate($_GET['code']);

$tokenJson = $client->getAccessToken();

$token = json_decode($tokenJson, true);

echo '<h2>Token Google Calendar ottenuto</h2>';

echo '<p>Copia questi valori dentro:</p>';
echo '<pre>local -> googleCalendar</pre>';

echo '<pre>';
echo htmlspecialchars(json_encode([
    'accessToken' => $token['access_token'] ?? '',
    'refreshToken' => $token['refresh_token'] ?? '',
    'expiresIn' => $token['expires_in'] ?? '',
    'created' => $token['created'] ?? time()
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo '</pre>';
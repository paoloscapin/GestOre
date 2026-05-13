<?php

require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/googleDriveLib.php';

ruoloRichiesto('admin');

$cfg = googleDriveGetConfig();
$client = googleDriveCreateClient(false);

if (empty($cfg->clientId) || empty($cfg->clientSecret)) {
    die('clientId o clientSecret mancanti per Google Drive');
}

if (!isset($_GET['code'])) {
    $authUrl = $client->createAuthUrl();
    echo '<h2>Autorizzazione Google Drive GestOre</h2>';
    echo '<p>Account previsto: <strong>' . htmlspecialchars($cfg->accountEmail ?? '') . '</strong></p>';
    echo '<p>Questa autorizzazione serve per caricare automaticamente i log ruotati nella cartella Drive dedicata.</p>';
    echo '<p><a href="' . htmlspecialchars($authUrl) . '">Autorizza Google Drive</a></p>';
    exit;
}

$client->authenticate($_GET['code']);
$token = json_decode((string)$client->getAccessToken(), true);
googleDriveSaveToken($token);

echo '<h2>Token Google Drive salvato</h2>';
echo '<p>Il token e stato salvato in <code>log/google_drive_token.json</code>.</p>';
echo '<p>Ora puoi usare il cron di archivio log.</p>';

?>

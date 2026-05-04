<?php
require_once __DIR__ . '/../common/gmail_api_lib.php';

if (!isset($_GET['code'])) {
    echo 'Codice OAuth mancante';
    exit;
}

$client = gmailCreateClient();

$token = $client->authenticate($_GET['code']);

if (!$token) {
    echo 'Token non ricevuto';
    exit;
}

file_put_contents(GMAIL_TOKEN_FILE, $token);

echo '<h3>OAuth Gmail completato</h3>';
echo '<p>Token salvato correttamente.</p>';
echo '<p><a href="/GestOre/api/gmail_start_watch.php">Attiva watch Gmail</a></p>';
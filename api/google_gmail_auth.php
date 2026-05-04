<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/gmail_api_lib.php';

ruoloRichiesto('admin');

$client = gmailCreateClient();
$authUrl = $client->createAuthUrl();

header('Location: ' . $authUrl);
exit;
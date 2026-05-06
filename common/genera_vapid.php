<?php
require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo '<pre>';
echo "PUBLIC KEY:\n" . $keys['publicKey'] . "\n\n";
echo "PRIVATE KEY:\n" . $keys['privateKey'] . "\n";
echo '</pre>';
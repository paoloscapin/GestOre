<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new RuntimeException('Autoload composer non trovato in common/vendor/autoload.php');
}
require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

global $__settings;

function gestorePushGetConfigValue(string $path): string
{
    global $__settings;

    $value = $__settings;
    foreach (explode('.', $path) as $part) {
        if (!is_object($value) || !isset($value->{$part})) {
            return '';
        }
        $value = $value->{$part};
    }

    return trim((string)$value);
}

function gestorePushValidateVapidKeys(string $publicKey, string $privateKey): void
{
    if ($publicKey === '' || $privateKey === '') {
        throw new RuntimeException('Chiavi VAPID mancanti in GestOre.json: notifiche.vapid.publicKey/privateKey');
    }

    if (!preg_match('/^[A-Za-z0-9_-]+$/', $publicKey)) {
        throw new RuntimeException('VAPID publicKey non valida: deve essere base64url senza spazi, + o /');
    }

    if (!preg_match('/^[A-Za-z0-9_-]+$/', $privateKey)) {
        throw new RuntimeException('VAPID privateKey non valida: deve essere base64url senza spazi, + o /');
    }
}

if (!defined('GESTORE_VAPID_PUBLIC_KEY')) {
    define('GESTORE_VAPID_PUBLIC_KEY', gestorePushGetConfigValue('notifiche.vapid.publicKey'));
}
if (!defined('GESTORE_VAPID_PRIVATE_KEY')) {
    define('GESTORE_VAPID_PRIVATE_KEY', gestorePushGetConfigValue('notifiche.vapid.privateKey'));
}

function sendPushToUser(string $username, string $title, string $body, string $url = '/GestOre/index.php'): array
{
    $usernameEsc = addslashes($username);

    $rows = dbGetAll("
        SELECT id, endpoint, p256dh, auth
        FROM push_subscriptions
        WHERE username = '$usernameEsc'
          AND attivo = 1
    ");

    return sendPushToSubscriptions($rows, $title, $body, $url);
}

function sendPushToRole(string $role, string $title, string $body, string $url = '/GestOre/index.php'): array
{
    $roleEsc = addslashes($role);

    $rows = dbGetAll("
        SELECT ps.id, ps.endpoint, ps.p256dh, ps.auth
        FROM push_subscriptions ps
        INNER JOIN utente u ON u.username = ps.username
        WHERE u.ruolo = '$roleEsc'
          AND ps.attivo = 1
    ");

    return sendPushToSubscriptions($rows, $title, $body, $url);
}

function sendPushToSubscriptions(array $rows, string $title, string $body, string $url): array
{
    gestorePushValidateVapidKeys(GESTORE_VAPID_PUBLIC_KEY, GESTORE_VAPID_PRIVATE_KEY);

    if (empty($rows)) {
        return [
            'ok' => 0,
            'ko' => 0,
            'expired' => 0,
            'total' => 0,
            'errors' => ['Nessuna subscription attiva trovata per il destinatario'],
        ];
    }

    $auth = [
        'VAPID' => [
            'subject' => 'mailto:noreplygestore@buonarroti.tn.it',
            'publicKey' => GESTORE_VAPID_PUBLIC_KEY,
            'privateKey' => GESTORE_VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new WebPush($auth);

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url,
    ], JSON_UNESCAPED_UNICODE);

    foreach ($rows as $row) {
        $subscription = Subscription::create([
            'endpoint' => $row['endpoint'],
            'keys' => [
                'p256dh' => $row['p256dh'],
                'auth'   => $row['auth'],
            ],
        ]);

        $webPush->queueNotification($subscription, $payload);
    }

    $result = [
        'ok' => 0,
        'ko' => 0,
        'expired' => 0,
        'total' => count($rows),
        'errors' => [],
    ];

    foreach ($webPush->flush() as $report) {
        if ($report->isSuccess()) {
            $result['ok']++;
        } else {
            $result['ko']++;
            $result['errors'][] = trim((string)$report->getReason());

            if ($report->isSubscriptionExpired()) {
                $endpointEsc = addslashes($report->getEndpoint());
                dbExec("
                    UPDATE push_subscriptions
                    SET attivo = 0, updated_at = NOW()
                    WHERE endpoint = '$endpointEsc'
                ");
                $result['expired']++;
            }
        }
    }

    return $result;
}

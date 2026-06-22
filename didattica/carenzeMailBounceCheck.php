<?php

require_once '../common/checkSession.php';
require_once '../common/carenzeMailLogLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120);

function carenzeBounceCheckAccounts(): array
{
    global $__settings;

    $accounts = [];
    $cfg = $__settings->iscrizioniPrime->mail ?? null;
    if ($cfg != null && !empty($cfg->accounts) && is_array($cfg->accounts)) {
        foreach ($cfg->accounts as $account) {
            $email = strtolower(trim((string)($account->email ?? '')));
            if ($email !== '') {
                $accounts[] = $email;
            }
        }
    }
    return array_values(array_unique($accounts));
}

$maxResults = isset($_POST['max']) ? intval($_POST['max']) : 30;
$maxResults = max(1, min(100, $maxResults));

$summary = [
    'ok' => true,
    'accounts' => [],
    'totals' => [
        'checked' => 0,
        'matched' => 0,
        'unmatched' => 0,
        'quota_limit' => 0,
        'invalid_recipient' => 0,
        'mailbox_full' => 0,
        'other_bounce' => 0,
    ],
];

foreach (carenzeBounceCheckAccounts() as $accountEmail) {
    try {
        $result = carenzeMailBounceCheckAccount($accountEmail, $maxResults);
        $summary['accounts'][] = $result;
        $summary['totals']['checked'] += intval($result['checked'] ?? 0);
        $summary['totals']['matched'] += intval($result['matched'] ?? 0);
        $summary['totals']['unmatched'] += intval($result['unmatched'] ?? 0);
        foreach (($result['bounces'] ?? []) as $bounce) {
            $type = (string)($bounce['type'] ?? 'other_bounce');
            if (!array_key_exists($type, $summary['totals'])) {
                $type = 'other_bounce';
            }
            $summary['totals'][$type]++;
        }
    } catch (Throwable $e) {
        $summary['ok'] = false;
        $summary['accounts'][] = [
            'account' => $accountEmail,
            'error' => $e->getMessage(),
        ];
    }
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE);

?>

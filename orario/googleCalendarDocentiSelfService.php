<?php

require_once '../common/checkSession.php';
require_once '../api/googleCalendarDocentiLib.php';

header('Content-Type: application/json; charset=utf-8');
ruoloRichiesto('docente');

function orarioGoogleCalendarDocentiJson($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function orarioGoogleCalendarDocentiCurrentUsername()
{
    global $__docente_id;

    $docenteId = intval($__docente_id ?? 0);
    if ($docenteId <= 0) {
        throw new Exception('Docente non riconosciuto.');
    }

    $username = trim((string)dbGetValue("SELECT username FROM docente WHERE id = " . dbI($docenteId) . " LIMIT 1"));
    if ($username === '') {
        throw new Exception('Username docente non configurato.');
    }

    return $username;
}

function orarioGoogleCalendarDocentiPayload()
{
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = [];
    }
    foreach ($_POST as $key => $value) {
        if (!isset($payload[$key])) {
            $payload[$key] = $value;
        }
    }
    return $payload;
}

function orarioGoogleCalendarDocentiStatus($username)
{
    $pref = googleCalendarDocentiPreference($username);
    return [
        'ok' => true,
        'enabled' => !empty($pref['enabled']),
        'initialSyncDone' => !empty($pref['initial_sync_at']),
        'initialSyncAt' => $pref['initial_sync_at'] ?? null,
        'lastManualSyncAt' => $pref['last_manual_sync_at'] ?? null,
        'lastCronSyncAt' => $pref['last_cron_sync_at'] ?? null,
        'lastSyncFrom' => $pref['last_sync_from'] ?? null,
        'lastSyncTo' => $pref['last_sync_to'] ?? null,
        'lastError' => $pref['last_error'] ?? null,
        'manualPastDays' => googleCalendarDocentiIntConfig('teacherManualSyncPastDays', 15, 1, 365),
        'manualFutureDays' => googleCalendarDocentiIntConfig('teacherManualSyncFutureDays', 120, 1, 370),
    ];
}

try {
    if (!googleCalendarDocentiTeacherSelfServiceEnabled()) {
        orarioGoogleCalendarDocentiJson([
            'ok' => false,
            'disabled' => true,
            'error' => 'Sync Google Calendar docenti non disponibile.'
        ], 403);
    }

    $username = orarioGoogleCalendarDocentiCurrentUsername();
    $payload = orarioGoogleCalendarDocentiPayload();
    $action = trim((string)($payload['action'] ?? 'status'));

    if ($action === 'status') {
        orarioGoogleCalendarDocentiJson(orarioGoogleCalendarDocentiStatus($username));
    }

    if ($action === 'disable') {
        googleCalendarDocentiSetTeacherEnabled($username, false);
        orarioGoogleCalendarDocentiJson(orarioGoogleCalendarDocentiStatus($username));
    }

    if ($action === 'enable') {
        $pref = googleCalendarDocentiSetTeacherEnabled($username, true);
        $didInitialSync = false;
        $syncResult = null;

        if (empty($pref['initial_sync_at']) && googleCalendarDocentiBoolConfig('teacherInitialSyncOnEnable', true)) {
            $from = googleCalendarDocentiCurrentSchoolYearStart();
            $to = googleCalendarDocentiToday();
            $result = googleCalendarDocentiSyncUsernames([$username], $from, $to);
            $syncResult = $result[0] ?? null;
            $error = trim((string)($syncResult['error'] ?? ''));

            googleCalendarDocentiUpsertPreference($username, [
                'initial_sync_at' => $error === '' ? date('Y-m-d H:i:s') : null,
                'last_manual_sync_at' => date('Y-m-d H:i:s'),
                'last_sync_from' => $from,
                'last_sync_to' => $to,
                'last_error' => $error !== '' ? $error : null,
            ]);
            $didInitialSync = true;
        }

        $status = orarioGoogleCalendarDocentiStatus($username);
        $status['didInitialSync'] = $didInitialSync;
        $status['syncResult'] = $syncResult;
        orarioGoogleCalendarDocentiJson($status);
    }

    if ($action === 'force_sync') {
        $pref = googleCalendarDocentiPreference($username);
        if (empty($pref['enabled'])) {
            throw new Exception('Abilita il sync prima di forzare un aggiornamento.');
        }

        $pastDays = googleCalendarDocentiIntConfig('teacherManualSyncPastDays', 15, 1, 365);
        $futureDays = googleCalendarDocentiIntConfig('teacherManualSyncFutureDays', 120, 1, 370);
        $today = googleCalendarDocentiToday();
        $from = date('Y-m-d', strtotime($today . ' -' . $pastDays . ' days'));
        $to = date('Y-m-d', strtotime($today . ' +' . $futureDays . ' days'));
        $result = googleCalendarDocentiSyncUsernames([$username], $from, $to);
        $syncResult = $result[0] ?? null;
        $error = trim((string)($syncResult['error'] ?? ''));

        googleCalendarDocentiUpsertPreference($username, [
            'last_manual_sync_at' => date('Y-m-d H:i:s'),
            'last_sync_from' => $from,
            'last_sync_to' => $to,
            'last_error' => $error !== '' ? $error : null,
        ]);

        $status = orarioGoogleCalendarDocentiStatus($username);
        $status['syncResult'] = $syncResult;
        orarioGoogleCalendarDocentiJson($status);
    }

    throw new Exception('Azione non valida.');
} catch (Throwable $e) {
    orarioGoogleCalendarDocentiJson([
        'ok' => false,
        'error' => $e->getMessage()
    ], 500);
}

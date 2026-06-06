<?php

function profiloLogClientIp(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value !== '') {
            return trim(explode(',', $value)[0]);
        }
    }
    return '';
}

function profiloLogActor(): array
{
    global $__username, $__utente_id, $__utente_ruolo, $__genitore_id, $__studente_id;
    $session = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];

    return [
        'username' => (string)($__username ?? ($session['username'] ?? $session['__username'] ?? '')),
        'utente_id' => (int)($__utente_id ?? ($session['utente_id'] ?? 0)),
        'ruolo' => (string)($__utente_ruolo ?? ($session['utente_ruolo'] ?? '')),
        'genitore_id' => (int)($__genitore_id ?? ($session['genitore_id'] ?? 0)),
        'studente_id' => (int)($__studente_id ?? ($session['studente_id'] ?? 0)),
    ];
}

function profiloLogWrite(string $action, string $targetRole, int $targetId, array $details = [], string $level = 'info'): void
{
    global $__settings;

    $logDir = __DIR__ . '/../log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile = trim((string)($__settings->log->logProfiliFile ?? 'profili.log'));
    if ($logFile === '') {
        $logFile = 'profili.log';
    }

    $entry = [
        'ts' => date('Y-m-d H:i:s'),
        'level' => $level,
        'page' => basename((string)($_SERVER['PHP_SELF'] ?? 'cli')),
        'action' => $action,
        'actor' => profiloLogActor(),
        'target' => [
            'role' => $targetRole,
            'id' => $targetId,
        ],
        'ip' => profiloLogClientIp(),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        'details' => $details,
    ];

    $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = json_encode([
            'ts' => date('Y-m-d H:i:s'),
            'level' => 'error',
            'action' => 'profilo_log_json_error',
            'target' => ['role' => $targetRole, 'id' => $targetId],
        ]);
    }

    @file_put_contents($logDir . '/' . basename($logFile), $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function profiloLogChangedFields(array $oldValues, array $newValues): array
{
    $changes = [];
    foreach ($newValues as $field => $newValue) {
        $oldValue = $oldValues[$field] ?? null;
        if ((string)$oldValue !== (string)$newValue) {
            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }
    }
    return $changes;
}

function profiloLogNotificationPrefsFromPost(array $post): array
{
    $incoming = is_array($post['notifiche'] ?? null) ? $post['notifiche'] : [];
    $prefs = [];
    foreach ($incoming as $type => $channels) {
        if (!is_array($channels)) {
            continue;
        }
        foreach ($channels as $channel => $enabled) {
            $prefs[(string)$type][(string)$channel] = !empty($enabled);
        }
    }
    return $prefs;
}

function profiloLogIsImpersonatingTarget(string $targetRole): bool
{
    $actor = profiloLogActor();
    $actorRole = trim((string)($actor['ruolo'] ?? ''));
    if ($actorRole === '' || $actorRole === $targetRole) {
        return false;
    }

    if ($targetRole === 'genitore') {
        return (int)($actor['genitore_id'] ?? 0) > 0;
    }
    if ($targetRole === 'studente') {
        return (int)($actor['studente_id'] ?? 0) > 0;
    }

    return false;
}

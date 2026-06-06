<?php

require_once __DIR__ . '/connect.php';

function notifichePreferenzeEnsureTable(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS notifiche_preferenze (
            id INT NOT NULL AUTO_INCREMENT,
            ruolo VARCHAR(32) NOT NULL,
            soggetto_id INT NOT NULL,
            tipo VARCHAR(64) NOT NULL,
            canale VARCHAR(32) NOT NULL,
            abilitato TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_notifiche_preferenze (ruolo, soggetto_id, tipo, canale),
            KEY idx_notifiche_preferenze_soggetto (ruolo, soggetto_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function notifichePreferenzeDefaultCatalog(string $role): array
{
    $common = [
        'sportelli' => [
            'label' => 'Sportelli',
            'descrizione' => 'Promemoria, cancellazioni e aggiornamenti sugli sportelli.',
            'canali' => ['mail' => true, 'telegram' => true, 'push' => false],
            'default' => ['mail' => true, 'telegram' => false, 'push' => false],
            'obbligatoria' => false,
            'almeno_un_canale' => false,
            'canale_obbligatorio' => '',
        ],
        'permessi' => [
            'label' => 'Permessi di uscita',
            'descrizione' => 'Aggiornamenti sulle richieste di permesso e sul loro stato.',
            'canali' => ['mail' => true, 'telegram' => true, 'push' => false],
            'default' => ['mail' => true, 'telegram' => false, 'push' => false],
            'obbligatoria' => true,
            'almeno_un_canale' => true,
            'canale_obbligatorio' => '',
        ],
        'carenze' => [
            'label' => 'Carenze',
            'descrizione' => 'Avvisi quando vengono pubblicate carenze, recuperi o comunicazioni correlate.',
            'canali' => ['mail' => true, 'telegram' => true, 'push' => false],
            'default' => ['mail' => true, 'telegram' => false, 'push' => false],
            'obbligatoria' => true,
            'almeno_un_canale' => true,
            'canale_obbligatorio' => '',
        ],
        'comunicazioni' => [
            'label' => 'Comunicazioni generali',
            'descrizione' => 'Messaggi informativi futuri inviati da GestOre.',
            'canali' => ['mail' => true, 'telegram' => true, 'push' => false],
            'default' => ['mail' => true, 'telegram' => false, 'push' => false],
            'obbligatoria' => false,
            'almeno_un_canale' => false,
            'canale_obbligatorio' => '',
        ],
    ];

    if ($role === 'studente') {
        foreach ($common as $tipo => $cfg) {
            $common[$tipo]['canali']['telegram'] = false;
            $common[$tipo]['default']['telegram'] = false;
        }
    }

    return $common;
}

function notifichePreferenzeObjectToArray($value): array
{
    if ($value instanceof stdClass) {
        $value = (array)$value;
    }
    if (!is_array($value)) {
        return [];
    }
    foreach ($value as $key => $item) {
        if ($item instanceof stdClass || is_array($item)) {
            $value[$key] = notifichePreferenzeObjectToArray($item);
        }
    }
    return $value;
}

function notifichePreferenzeMerge(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
            $base[$key] = notifichePreferenzeMerge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

function notifichePreferenzeConfigKeyForRole(string $role): string
{
    return $role === 'studente' ? 'studenti' : 'genitori';
}

function notifichePreferenzeCatalog(string $role): array
{
    global $__settings;
    $catalog = notifichePreferenzeDefaultCatalog($role);

    // Compatibilita con la prima bozza della configurazione.
    $legacyConfigured = notifichePreferenzeObjectToArray($__settings->notifiche->tipi ?? []);
    foreach ($legacyConfigured as $tipo => $cfg) {
        if (!is_array($cfg)) {
            continue;
        }
        $catalog[$tipo] = notifichePreferenzeMerge($catalog[$tipo] ?? [], $cfg);
    }

    $roleKey = notifichePreferenzeConfigKeyForRole($role);
    $configured = notifichePreferenzeObjectToArray($__settings->notifiche->{$roleKey} ?? []);
    foreach ($configured as $tipo => $cfg) {
        if (!is_array($cfg)) {
            continue;
        }
        $catalog[$tipo] = notifichePreferenzeMerge($catalog[$tipo] ?? [], $cfg);
    }

    return $catalog;
}

function notifichePreferenzeChannelLabel(string $channel): string
{
    $labels = [
        'mail' => 'Email',
        'telegram' => 'Telegram',
        'push' => 'Telefono/PC',
    ];
    return $labels[$channel] ?? ucfirst($channel);
}

function notifichePreferenzeRoleEnabled(array $cfg, string $role): bool
{
    if (!isset($cfg['ruoli'])) {
        return true;
    }
    return !empty($cfg['ruoli'][$role]);
}

function notifichePreferenzeChannelEnabled(array $cfg, string $role, string $channel): bool
{
    if (empty($cfg['canali'][$channel])) {
        return false;
    }
    if ($channel === 'telegram' && $role !== 'genitore') {
        return false;
    }
    if ($channel === 'telegram' && !getSettingsValue('profiloGenitore', 'visibile_telegram', false)) {
        return false;
    }
    if ($channel === 'push') {
        return false;
    }
    return true;
}

function notifichePreferenzeAllowedChannels(array $cfg, string $role): array
{
    $channels = [];
    foreach (['mail', 'telegram', 'push'] as $channel) {
        if (notifichePreferenzeChannelEnabled($cfg, $role, $channel)) {
            $channels[] = $channel;
        }
    }
    return $channels;
}

function notifichePreferenzeLoad(string $role, int $subjectId): array
{
    notifichePreferenzeEnsureTable();
    $savedRows = dbGetAll("
        SELECT tipo, canale, abilitato
        FROM notifiche_preferenze
        WHERE ruolo = " . dbQ($role) . "
          AND soggetto_id = " . dbI($subjectId) . "
    ") ?: [];

    $saved = [];
    foreach ($savedRows as $row) {
        $saved[(string)$row['tipo']][(string)$row['canale']] = ((int)$row['abilitato'] === 1);
    }

    $prefs = [];
    foreach (notifichePreferenzeCatalog($role) as $tipo => $cfg) {
        if (!notifichePreferenzeRoleEnabled($cfg, $role)) {
            continue;
        }
        foreach (notifichePreferenzeAllowedChannels($cfg, $role) as $channel) {
            $prefs[$tipo][$channel] = $saved[$tipo][$channel] ?? !empty($cfg['default'][$channel]);
        }
    }
    return notifichePreferenzeNormalize($role, $prefs);
}

function notifichePreferenzeNormalize(string $role, array $prefs): array
{
    foreach (notifichePreferenzeCatalog($role) as $tipo => $cfg) {
        if (!notifichePreferenzeRoleEnabled($cfg, $role)) {
            continue;
        }
        $channels = notifichePreferenzeAllowedChannels($cfg, $role);
        $forced = trim((string)($cfg['canale_obbligatorio'] ?? ''));
        if ($forced !== '' && in_array($forced, $channels, true)) {
            $prefs[$tipo][$forced] = true;
        }
        $mandatory = !empty($cfg['obbligatoria']) || !empty($cfg['almeno_un_canale']);
        if ($mandatory) {
            $hasActive = false;
            foreach ($channels as $channel) {
                $hasActive = $hasActive || !empty($prefs[$tipo][$channel]);
            }
            if (!$hasActive && $channels) {
                $fallback = in_array('mail', $channels, true) ? 'mail' : $channels[0];
                $prefs[$tipo][$fallback] = true;
            }
        }
    }
    return $prefs;
}

function notifichePreferenzeSaveFromPost(string $role, int $subjectId, array $post): array
{
    notifichePreferenzeEnsureTable();
    $incoming = is_array($post['notifiche'] ?? null) ? $post['notifiche'] : [];
    $prefs = [];

    foreach (notifichePreferenzeCatalog($role) as $tipo => $cfg) {
        if (!notifichePreferenzeRoleEnabled($cfg, $role)) {
            continue;
        }
        foreach (notifichePreferenzeAllowedChannels($cfg, $role) as $channel) {
            $prefs[$tipo][$channel] = !empty($incoming[$tipo][$channel]);
        }
    }
    $prefs = notifichePreferenzeNormalize($role, $prefs);

    foreach (notifichePreferenzeCatalog($role) as $tipo => $cfg) {
        if (!notifichePreferenzeRoleEnabled($cfg, $role)) {
            continue;
        }
        foreach (notifichePreferenzeAllowedChannels($cfg, $role) as $channel) {
            dbExec("
                INSERT INTO notifiche_preferenze (ruolo, soggetto_id, tipo, canale, abilitato, updated_at)
                VALUES (" . dbQ($role) . ", " . dbI($subjectId) . ", " . dbQ($tipo) . ", " . dbQ($channel) . ", " . dbI(!empty($prefs[$tipo][$channel]) ? 1 : 0) . ", NOW())
                ON DUPLICATE KEY UPDATE abilitato = VALUES(abilitato), updated_at = NOW()
            ");
        }
    }

    return ['ok' => true, 'message' => 'Preferenze notifiche salvate.'];
}

function notifichePreferenzeChannelAllowed(string $role, int $subjectId, string $type, string $channel): bool
{
    $prefs = notifichePreferenzeLoad($role, $subjectId);
    return !empty($prefs[$type][$channel]);
}

function notifichePreferenzeTableExists(string $table): bool
{
    $table = trim($table);
    if ($table === '') {
        return false;
    }
    return dbGetValue("SHOW TABLES LIKE " . dbQ($table)) !== null;
}

function notifichePreferenzeGenitoreTelegramChatId(int $genitoreId): string
{
    if ($genitoreId <= 0 || !notifichePreferenzeTableExists('genitore_telegram')) {
        return '';
    }

    return trim((string)dbGetValue("
        SELECT telegram_chat_id
        FROM genitore_telegram
        WHERE idGenitore = " . dbI($genitoreId) . "
          AND attivo = 1
          AND consenso_notifiche = 1
          AND telegram_chat_id IS NOT NULL
          AND telegram_chat_id <> ''
        LIMIT 1
    "));
}

function notifichePreferenzeGenitoreRecord(int $genitoreId): ?array
{
    if ($genitoreId <= 0) {
        return null;
    }

    return dbGetFirst("
        SELECT id, nome, cognome, email
        FROM genitori
        WHERE id = " . dbI($genitoreId) . "
          AND attivo = 1
        LIMIT 1
    ");
}

function notifichePreferenzeInviaGenitore(
    int $genitoreId,
    string $tipo,
    string $subject,
    string $mailHtml,
    string $telegramText = '',
    array $mailOptions = [],
    array $telegramOptions = []
): array {
    global $__settings;

    $genitore = notifichePreferenzeGenitoreRecord($genitoreId);
    if (!$genitore) {
        return ['ok' => false, 'error' => 'genitore non trovato', 'channels' => []];
    }

    $channels = [];
    $errors = [];

    if (notifichePreferenzeChannelAllowed('genitore', $genitoreId, $tipo, 'mail')) {
        $email = trim((string)($genitore['email'] ?? ''));
        if ($email !== '') {
            if (!function_exists('sendMailCustom')) {
                require_once __DIR__ . '/send-mail.php';
            }
            $name = trim((string)(($genitore['nome'] ?? '') . ' ' . ($genitore['cognome'] ?? '')));
            $ok = sendMailCustom($email, $name, $subject, $mailHtml, $mailOptions);
            if ($ok) {
                $channels['mail'] = true;
            } else {
                $channels['mail'] = false;
                $errors[] = 'mail non inviata';
            }
        } else {
            $channels['mail'] = false;
            $errors[] = 'email genitore mancante';
        }
    }

    if (notifichePreferenzeChannelAllowed('genitore', $genitoreId, $tipo, 'telegram')) {
        $chatId = notifichePreferenzeGenitoreTelegramChatId($genitoreId);
        $botToken = trim((string)($__settings->telegram->bot_token ?? ''));
        if ($chatId !== '' && $botToken !== '') {
            require_once __DIR__ . '/telegram_webhook_utils.php';
            require_once __DIR__ . '/telegram_webhook_api.php';
            $text = trim($telegramText) !== '' ? $telegramText : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mailHtml)));
            $res = tgSendMessage($botToken, $chatId, tgCut($text, 3500), $telegramOptions);
            $channels['telegram'] = !empty($res['ok']);
            if (empty($res['ok'])) {
                $errors[] = 'telegram non inviato';
            }
        } else {
            $channels['telegram'] = false;
            $errors[] = 'telegram genitore non collegato';
        }
    }

    $ok = false;
    foreach ($channels as $sent) {
        $ok = $ok || !empty($sent);
    }

    return ['ok' => $ok, 'channels' => $channels, 'errors' => $errors];
}

function notifichePreferenzeRenderSection(string $role, int $subjectId): string
{
    $catalog = notifichePreferenzeCatalog($role);
    $prefs = notifichePreferenzeLoad($role, $subjectId);
    $html = '<section class="genitore-profilo-card notifiche-preferenze-card">';
    $html .= '<div class="genitore-profilo-card-heading"><span class="glyphicon glyphicon-bell"></span><span>Notifiche</span></div>';
    $html .= '<div class="genitore-profilo-card-body">';
    $html .= '<p class="genitore-profilo-help">Scegli quali comunicazioni ricevere e su quali canali. Le comunicazioni obbligatorie definite dalla scuola mantengono almeno un canale attivo.</p>';
    $html .= '<form method="post">';
    $html .= '<input type="hidden" name="profilo_action" value="notifiche">';
    $html .= '<div class="notifiche-preferenze-list">';

    foreach ($catalog as $tipo => $cfg) {
        if (!notifichePreferenzeRoleEnabled($cfg, $role)) {
            continue;
        }
        $channels = notifichePreferenzeAllowedChannels($cfg, $role);
        if (!$channels) {
            continue;
        }
        $forced = trim((string)($cfg['canale_obbligatorio'] ?? ''));
        $mandatory = !empty($cfg['obbligatoria']) || !empty($cfg['almeno_un_canale']);
        $html .= '<div class="notifiche-preferenze-row">';
        $html .= '<div class="notifiche-preferenze-info">';
        $html .= '<div class="notifiche-preferenze-title">' . htmlspecialchars((string)($cfg['label'] ?? $tipo), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        $html .= '<div class="notifiche-preferenze-desc">' . htmlspecialchars((string)($cfg['descrizione'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        if ($mandatory) {
            $html .= '<div class="notifiche-preferenze-rule">Obbligatoria: deve restare attivo almeno un canale.</div>';
        }
        $html .= '</div><div class="notifiche-preferenze-channels">';

        foreach ($channels as $channel) {
            $checked = !empty($prefs[$tipo][$channel]) ? ' checked' : '';
            $onlyMandatoryChannel = ($mandatory && count($channels) === 1 && $channels[0] === $channel);
            $disabled = (($forced !== '' && $forced === $channel) || $onlyMandatoryChannel) ? ' disabled' : '';
            if ($disabled !== '') {
                $html .= '<input type="hidden" name="notifiche[' . htmlspecialchars($tipo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '][' . htmlspecialchars($channel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ']" value="1">';
            }
            $html .= '<label class="notifiche-preferenze-channel">';
            $html .= '<input type="checkbox" name="notifiche[' . htmlspecialchars($tipo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '][' . htmlspecialchars($channel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ']" value="1"' . $checked . $disabled . '>';
            $html .= '<span>' . htmlspecialchars(notifichePreferenzeChannelLabel($channel), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
            $html .= '</label>';
        }
        $html .= '</div></div>';
    }

    $html .= '</div><div class="genitore-profilo-actions">';
    $html .= '<button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk"></span> Salva notifiche</button>';
    $html .= '</div></form></div></section>';
    return $html;
}

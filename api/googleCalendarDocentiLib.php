<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/connectMBApp.php';
require_once __DIR__ . '/../common/__Log.php';

setLogChannel('google_calendar');

function googleCalendarDocentiConfig()
{
    global $__settings;

    if (!isset($__settings->local->googleCalendarDocenti)) {
        throw new Exception('Configurazione local.googleCalendarDocenti mancante in GestOre.json');
    }

    $cfg = $__settings->local->googleCalendarDocenti;
    if (empty($cfg->enabled)) {
        throw new Exception('Sincronizzazione Google Calendar docenti disabilitata');
    }

    return $cfg;
}

function googleCalendarDocentiBase64Url($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function googleCalendarDocentiServiceAccountPath($cfg)
{
    $path = trim((string)($cfg->serviceAccountFile ?? ''));
    if ($path === '') {
        throw new Exception('serviceAccountFile mancante in local.googleCalendarDocenti');
    }

    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || substr($path, 0, 1) === '/' || substr($path, 0, 1) === '\\') {
        return $path;
    }

    return realpath(__DIR__ . '/../' . $path) ?: (__DIR__ . '/../' . $path);
}

function googleCalendarDocentiLoadServiceAccount($cfg)
{
    $path = googleCalendarDocentiServiceAccountPath($cfg);
    if (!is_file($path)) {
        throw new Exception('File service account non trovato: ' . $path);
    }

    $json = json_decode(file_get_contents($path), true);
    if (!is_array($json)) {
        throw new Exception('File service account non valido');
    }

    if (empty($json['client_email']) || empty($json['private_key'])) {
        throw new Exception('client_email/private_key mancanti nel service account');
    }

    return $json;
}

function googleCalendarDocentiUserEmail($username)
{
    $cfg = googleCalendarDocentiConfig();
    $u = trim((string)$username);
    if ($u === '') {
        throw new Exception('Username docente vuoto');
    }
    if (strpos($u, '@') !== false) {
        return strtolower($u);
    }
    $domain = trim((string)($cfg->domain ?? ''));
    if ($domain === '') {
        throw new Exception('Dominio mancante in local.googleCalendarDocenti');
    }
    return strtolower($u . '@' . $domain);
}

function googleCalendarDocentiAccessToken($subjectEmail)
{
    static $cache = [];

    $subjectEmail = strtolower(trim((string)$subjectEmail));
    if (isset($cache[$subjectEmail]) && $cache[$subjectEmail]['expires_at'] > time() + 60) {
        return $cache[$subjectEmail]['access_token'];
    }

    $cfg = googleCalendarDocentiConfig();
    $sa = googleCalendarDocentiLoadServiceAccount($cfg);

    $now = time();
    $scopes = googleCalendarDocentiScopes($cfg);

    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];
    $claim = [
        'iss' => $sa['client_email'],
        'scope' => implode(' ', $scopes),
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now,
        'sub' => $subjectEmail
    ];

    $unsigned = googleCalendarDocentiBase64Url(json_encode($header)) . '.' .
        googleCalendarDocentiBase64Url(json_encode($claim));

    $privateKey = openssl_pkey_get_private($sa['private_key']);
    if (!$privateKey) {
        throw new Exception('Private key service account non caricabile');
    }

    $signature = '';
    if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new Exception('Firma JWT service account non riuscita');
    }

    if (function_exists('openssl_pkey_free')) {
        openssl_pkey_free($privateKey);
    }

    $jwt = $unsigned . '.' . googleCalendarDocentiBase64Url($signature);

    $tokenUri = trim((string)($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token'));
    if ($tokenUri === '') {
        $tokenUri = 'https://oauth2.googleapis.com/token';
    }

    $ch = curl_init($tokenUri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Errore CURL token Google docenti: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
        $clientId = trim((string)($sa['client_id'] ?? ''));
        $hint = ' Verifica in Google Admin Console > Sicurezza > Controlli API > Delega a livello di dominio: ' .
            'client ID ' . ($clientId !== '' ? $clientId : '(client_id non trovato nel JSON)') .
            ' con scopes ' . implode(',', $scopes) .
            ' e subject ' . $subjectEmail . '.';
        throw new Exception('Errore token Google docenti HTTP ' . $httpCode . ': ' . $response . $hint);
    }

    $cache[$subjectEmail] = [
        'access_token' => $decoded['access_token'],
        'expires_at' => time() + intval($decoded['expires_in'] ?? 3600)
    ];

    return $cache[$subjectEmail]['access_token'];
}

function googleCalendarDocentiScopes($cfg)
{
    if (isset($cfg->scopes) && is_array($cfg->scopes) && count($cfg->scopes) > 0) {
        $out = [];
        foreach ($cfg->scopes as $scope) {
            $scope = trim((string)$scope);
            if ($scope !== '') $out[] = $scope;
        }
        if (!empty($out)) return $out;
    }

    return [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events'
    ];
}

function googleCalendarDocentiRequest($subjectEmail, $method, $url, $data = null, $query = [])
{
    if (!empty($query)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
    }

    $token = googleCalendarDocentiAccessToken($subjectEmail);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Errore CURL Google Calendar docenti: ' . $curlError);
    }

    if ($httpCode === 204) {
        return [];
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception(
            'Errore Google Calendar docenti HTTP ' . $httpCode .
                ' [' . strtoupper($method) . ' ' . $url . '] subject=' . $subjectEmail .
                ': ' . $response
        );
    }

    return is_array($decoded) ? $decoded : [];
}

function googleCalendarDocentiEnsureTables()
{
    dbExec("
        CREATE TABLE IF NOT EXISTS google_calendar_docenti_sync (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(190) NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            calendar_id VARCHAR(255) NOT NULL,
            google_event_id VARCHAR(255) NOT NULL,
            source_key VARCHAR(255) NOT NULL,
            source_hash CHAR(64) NOT NULL,
            source_type VARCHAR(40) NOT NULL,
            checksum CHAR(64) NOT NULL,
            data_inizio DATETIME NOT NULL,
            data_fine DATETIME NOT NULL,
            stato VARCHAR(30) NOT NULL DEFAULT 'SYNC',
            last_sync DATETIME NOT NULL,
            UNIQUE KEY uq_docente_source (username, source_hash),
            KEY idx_docente_range (username, data_inizio, data_fine),
            KEY idx_google_event (google_event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS google_calendar_docenti_pref (
            id INT AUTO_INCREMENT PRIMARY KEY,
            docente_id INT NULL,
            username VARCHAR(190) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            enabled_at DATETIME NULL,
            disabled_at DATETIME NULL,
            initial_sync_at DATETIME NULL,
            last_manual_sync_at DATETIME NULL,
            last_cron_sync_at DATETIME NULL,
            last_sync_from DATE NULL,
            last_sync_to DATE NULL,
            last_error TEXT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_docente_pref_username (username),
            KEY idx_docente_pref_enabled (enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function googleCalendarDocentiBoolConfig($name, $default = false)
{
    $cfg = googleCalendarDocentiConfig();
    if (!isset($cfg->{$name})) {
        return (bool)$default;
    }

    return (bool)$cfg->{$name};
}

function googleCalendarDocentiIntConfig($name, $default, $min = null, $max = null)
{
    $cfg = googleCalendarDocentiConfig();
    $value = intval($cfg->{$name} ?? $default);
    if ($min !== null) $value = max(intval($min), $value);
    if ($max !== null) $value = min(intval($max), $value);
    return $value;
}

function googleCalendarDocentiTeacherSelfServiceEnabled()
{
    return googleCalendarDocentiBoolConfig('teacherSelfServiceEnabled', false);
}

function googleCalendarDocentiCurrentSchoolYearStart()
{
    global $__anno_scolastico_corrente_anno;

    $year = trim((string)($__anno_scolastico_corrente_anno ?? ''));
    if ($year === '') {
        $row = dbGetFirst("SELECT anno FROM anno_scolastico_corrente LIMIT 1");
        $year = trim((string)($row['anno'] ?? ''));
    }

    if (preg_match('/(\d{4})/', $year, $matches)) {
        return $matches[1] . '-09-01';
    }

    $currentYear = intval(date('n')) >= 9 ? intval(date('Y')) : intval(date('Y')) - 1;
    return $currentYear . '-09-01';
}

function googleCalendarDocentiCurrentSchoolYearEnd()
{
    global $__anno_scolastico_corrente_anno;

    $year = trim((string)($__anno_scolastico_corrente_anno ?? ''));
    if ($year === '') {
        $row = dbGetFirst("SELECT anno FROM anno_scolastico_corrente LIMIT 1");
        $year = trim((string)($row['anno'] ?? ''));
    }

    if (preg_match('/^\s*(\d{4})\s*\/\s*(\d{4})\s*$/', $year, $matches)) {
        return $matches[2] . '-08-31';
    }

    if (preg_match('/(\d{4})/', $year, $matches)) {
        return (intval($matches[1]) + 1) . '-08-31';
    }

    $currentYear = intval(date('n')) >= 9 ? intval(date('Y')) : intval(date('Y')) - 1;
    return ($currentYear + 1) . '-08-31';
}

function googleCalendarDocentiToday()
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
}

function googleCalendarDocentiPreference($username)
{
    googleCalendarDocentiEnsureTables();

    $username = trim((string)$username);
    if ($username === '') {
        return null;
    }

    return dbGetFirst("
        SELECT *
        FROM google_calendar_docenti_pref
        WHERE username = " . dbQ($username) . "
        LIMIT 1
    ");
}

function googleCalendarDocentiUpsertPreference($username, array $values)
{
    googleCalendarDocentiEnsureTables();

    $username = trim((string)$username);
    if ($username === '') {
        throw new Exception('Username docente vuoto');
    }

    $teacher = googleCalendarDocentiGetTeachers($username)[0] ?? null;
    $docenteId = intval($teacher['id'] ?? 0);
    $existing = googleCalendarDocentiPreference($username);

    $enabled = array_key_exists('enabled', $values)
        ? (intval($values['enabled']) ? 1 : 0)
        : intval($existing['enabled'] ?? 0);
    $enabledAt = array_key_exists('enabled_at', $values) ? $values['enabled_at'] : ($existing['enabled_at'] ?? null);
    $disabledAt = array_key_exists('disabled_at', $values) ? $values['disabled_at'] : ($existing['disabled_at'] ?? null);
    $initialSyncAt = array_key_exists('initial_sync_at', $values) ? $values['initial_sync_at'] : ($existing['initial_sync_at'] ?? null);
    $lastManualSyncAt = array_key_exists('last_manual_sync_at', $values) ? $values['last_manual_sync_at'] : ($existing['last_manual_sync_at'] ?? null);
    $lastCronSyncAt = array_key_exists('last_cron_sync_at', $values) ? $values['last_cron_sync_at'] : ($existing['last_cron_sync_at'] ?? null);
    $lastSyncFrom = array_key_exists('last_sync_from', $values) ? $values['last_sync_from'] : ($existing['last_sync_from'] ?? null);
    $lastSyncTo = array_key_exists('last_sync_to', $values) ? $values['last_sync_to'] : ($existing['last_sync_to'] ?? null);
    $lastError = array_key_exists('last_error', $values) ? $values['last_error'] : ($existing['last_error'] ?? null);

    dbExec("
        INSERT INTO google_calendar_docenti_pref
            (docente_id, username, enabled, enabled_at, disabled_at, initial_sync_at, last_manual_sync_at, last_cron_sync_at, last_sync_from, last_sync_to, last_error, updated_at)
        VALUES
            (" . ($docenteId > 0 ? dbI($docenteId) : "NULL") . ",
             " . dbQ($username) . ",
             " . dbI($enabled) . ",
             " . dbQ($enabledAt) . ",
             " . dbQ($disabledAt) . ",
             " . dbQ($initialSyncAt) . ",
             " . dbQ($lastManualSyncAt) . ",
             " . dbQ($lastCronSyncAt) . ",
             " . dbQ($lastSyncFrom) . ",
             " . dbQ($lastSyncTo) . ",
             " . dbQ($lastError) . ",
             NOW())
        ON DUPLICATE KEY UPDATE
            docente_id = VALUES(docente_id),
            enabled = VALUES(enabled),
            enabled_at = VALUES(enabled_at),
            disabled_at = VALUES(disabled_at),
            initial_sync_at = VALUES(initial_sync_at),
            last_manual_sync_at = VALUES(last_manual_sync_at),
            last_cron_sync_at = VALUES(last_cron_sync_at),
            last_sync_from = VALUES(last_sync_from),
            last_sync_to = VALUES(last_sync_to),
            last_error = VALUES(last_error),
            updated_at = NOW()
    ");

    return googleCalendarDocentiPreference($username);
}

function googleCalendarDocentiSetTeacherEnabled($username, $enabled)
{
    $pref = googleCalendarDocentiPreference($username);
    $values = [
        'enabled' => $enabled ? 1 : 0,
        'last_error' => null,
    ];

    if ($enabled) {
        $values['enabled_at'] = $pref['enabled_at'] ?? date('Y-m-d H:i:s');
        $values['disabled_at'] = null;
    } else {
        $values['disabled_at'] = date('Y-m-d H:i:s');
    }

    return googleCalendarDocentiUpsertPreference($username, $values);
}

function googleCalendarDocentiEnabledTeacherUsernames()
{
    googleCalendarDocentiEnsureTables();

    return dbGetAllValues("
        SELECT p.username
        FROM google_calendar_docenti_pref p
        INNER JOIN docente d ON d.username = p.username
        WHERE p.enabled = 1
          AND d.attivo = true
          AND d.username IS NOT NULL
          AND d.username <> ''
        ORDER BY d.cognome, d.nome
    ") ?: [];
}

function googleCalendarDocentiCalendarId($userEmail)
{
    $cfg = googleCalendarDocentiConfig();
    $createDedicated = !empty($cfg->createDedicatedCalendar);
    if (!$createDedicated) {
        return trim((string)($cfg->calendarId ?? 'primary')) ?: 'primary';
    }

    $name = trim((string)($cfg->calendarName ?? 'GestOre - Attivita'));
    if ($name === '') {
        $name = 'GestOre - Attivita';
    }

    $pageToken = null;
    do {
        $params = ['maxResults' => 250];
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }
        $res = googleCalendarDocentiRequest(
            $userEmail,
            'GET',
            'https://www.googleapis.com/calendar/v3/users/me/calendarList',
            null,
            $params
        );
        foreach (($res['items'] ?? []) as $cal) {
            if (trim((string)($cal['summary'] ?? '')) === $name) {
                return (string)$cal['id'];
            }
        }
        $pageToken = $res['nextPageToken'] ?? null;
    } while ($pageToken);

    $created = googleCalendarDocentiRequest(
        $userEmail,
        'POST',
        'https://www.googleapis.com/calendar/v3/calendars',
        [
            'summary' => $name,
            'timeZone' => trim((string)($cfg->timeZone ?? 'Europe/Rome')) ?: 'Europe/Rome'
        ]
    );

    return (string)($created['id'] ?? '');
}

function googleCalendarDocentiSchoolSlots()
{
    return ["07:50", "08:40", "09:30", "10:30", "11:20", "12:10", "13:00", "13:50", "14:40", "15:30", "16:20", "17:10", "18:00", "18:50", "19:40", "20:30", "21:30", "22:20"];
}

function googleCalendarDocentiNormOra($ora)
{
    $ora = trim((string)$ora);
    if ($ora === '') return '';
    return substr($ora, 0, 5);
}

function googleCalendarDocentiSlotEnd($ora)
{
    $slots = googleCalendarDocentiSchoolSlots();
    $ora = googleCalendarDocentiNormOra($ora);
    $idx = array_search($ora, $slots, true);
    if ($idx !== false && isset($slots[$idx + 1])) {
        return $slots[$idx + 1];
    }
    $dt = DateTime::createFromFormat('H:i', $ora);
    if (!$dt) {
        return $ora;
    }
    $dt->modify('+50 minutes');
    return $dt->format('H:i');
}

function googleCalendarDocentiMinutes($ora)
{
    $ora = googleCalendarDocentiNormOra($ora);
    if (!preg_match('/^\d{2}:\d{2}$/', $ora)) return null;
    [$h, $m] = array_map('intval', explode(':', $ora));
    return $h * 60 + $m;
}

function googleCalendarDocentiAreContiguous($prevEnd, $nextStart)
{
    $a = googleCalendarDocentiMinutes($prevEnd);
    $b = googleCalendarDocentiMinutes($nextStart);
    return $a !== null && $b !== null && $a === $b;
}

function googleCalendarDocentiDateTime($date, $time)
{
    $date = substr(trim((string)$date), 0, 10);
    $time = googleCalendarDocentiNormOra($time);
    return $date . 'T' . $time . ':00';
}

function googleCalendarDocentiCsv($value)
{
    $parts = preg_split('/\s*,\s*/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    $seen = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $k = strtoupper($p);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $p;
    }
    return $out;
}

function googleCalendarDocentiMergeUnique(array $a, array $b)
{
    $out = [];
    $seen = [];
    foreach (array_merge($a, $b) as $value) {
        $value = trim((string)$value);
        if ($value === '') continue;
        $key = strtoupper($value);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $value;
    }
    return $out;
}

function googleCalendarDocentiPrefixTitleWithClasses($title, array $classi)
{
    $title = trim((string)$title);
    if (empty($classi)) return $title;
    $classi = googleCalendarDocentiMergeUnique([], $classi);
    sort($classi, SORT_NATURAL | SORT_FLAG_CASE);
    $prefix = implode(', ', $classi);
    if ($prefix === '' || stripos($title, $prefix . ' - ') === 0) return $title;
    return $prefix . ' - ' . $title;
}

function googleCalendarDocentiIsUdienza($sigla, $nome)
{
    $s = strtoupper(trim((string)$sigla));
    $n = strtoupper(trim((string)$nome));
    return strpos($s, 'UDIENZA') !== false || strpos($n, 'UDIENZA') !== false;
}

function googleCalendarDocentiIsPranzoStudio($sigla, $nome, $attivita)
{
    $s = strtoupper(trim((string)$sigla));
    $n = strtoupper(trim((string)$nome));
    $a = strtoupper(trim((string)$attivita));
    return strpos($s, 'PRANZO') !== false ||
        strpos($n, 'PAUSA PRANZO') !== false ||
        strpos($a, 'PAUSA PRANZO') !== false ||
        strpos($s, 'AULA S') !== false ||
        strpos($n, 'AULA STUDIO') !== false ||
        strpos($a, 'AULA STUDIO') !== false;
}

function googleCalendarDocentiIsUscita($motivo, $dettagli)
{
    $m = strtoupper(trim((string)$motivo));
    $d = strtoupper(trim((string)$dettagli));
    return strpos($m, 'USCITA') !== false || strpos($d, 'USCITA') !== false;
}

function googleCalendarDocentiIsUscitaFuori($motivo, $dettagli)
{
    $m = strtoupper(trim((string)$motivo));
    $d = strtoupper(trim((string)$dettagli));
    return googleCalendarDocentiIsUscita($m, $d) &&
        (strpos($m, 'FUORI') !== false || strpos($d, 'FUORI') !== false);
}

function googleCalendarDocentiIsViaggio($motivo, $dettagli)
{
    $m = strtoupper(trim((string)$motivo));
    $d = strtoupper(trim((string)$dettagli));
    return strpos($m, 'VIAGG') !== false ||
        strpos($m, 'ISTRUZ') !== false ||
        strpos($m, 'GITA') !== false ||
        strpos($d, 'VIAGG') !== false ||
        strpos($d, 'ISTRUZ') !== false ||
        strpos($d, 'GITA') !== false;
}

function googleCalendarDocentiIsInstituteCommitment($motivo, $dettagli)
{
    $m = strtoupper(trim((string)$motivo));
    $d = strtoupper(trim((string)$dettagli));
    return strpos($m, 'IMPEGNO IN ISTITUTO') !== false ||
        strpos($d, 'IMPEGNO IN ISTITUTO') !== false ||
        strpos($m, 'SPORTELLO') !== false ||
        strpos($d, 'SPORTELLO') !== false;
}

function googleCalendarDocentiPickOraInizio(array $row)
{
    $ora = googleCalendarDocentiNormOra($row['oraInizioReale'] ?? '');
    if ($ora !== '') return $ora;
    $ora = googleCalendarDocentiNormOra($row['oraInizio'] ?? '');
    return $ora !== '' ? $ora : '07:50';
}

function googleCalendarDocentiPickOraFine(array $row)
{
    $ora = googleCalendarDocentiNormOra($row['oraFineReale'] ?? '');
    if ($ora !== '') return $ora;
    $ora = googleCalendarDocentiNormOra($row['oraFine'] ?? '');
    return $ora !== '' ? $ora : '13:50';
}

function googleCalendarDocentiMbEsc($value)
{
    global $__conMBApp;
    return mysqli_real_escape_string($__conMBApp, (string)$value);
}

function googleCalendarDocentiLocalEsc($value)
{
    return dbEscape($value);
}

function googleCalendarDocentiGetTeachers($username = '')
{
    $where = "attivo = true AND username IS NOT NULL AND username <> ''";
    if (trim((string)$username) !== '') {
        $where .= " AND username = '" . googleCalendarDocentiLocalEsc($username) . "'";
    }

    $rows = dbGetAll("
        SELECT id, username, cognome, nome, email
        FROM docente
        WHERE $where
        ORDER BY cognome, nome
    ") ?: [];

    $out = [];
    foreach ($rows as $r) {
        $u = trim((string)($r['username'] ?? ''));
        if ($u === '') continue;
        $email = googleCalendarDocentiTeacherEmail($r);
        $out[] = [
            'id' => intval($r['id'] ?? 0),
            'username' => $u,
            'email' => $email,
            'nome' => trim((string)($r['cognome'] ?? '') . ' ' . (string)($r['nome'] ?? ''))
        ];
    }
    return $out;
}

function googleCalendarDocentiTeacherEmail(array $row)
{
    $cfg = googleCalendarDocentiConfig();
    $domain = strtolower(trim((string)($cfg->domain ?? '')));
    $email = strtolower(trim((string)($row['email'] ?? '')));

    if ($email !== '' && strpos($email, '@') !== false) {
        if ($domain === '' || substr($email, -strlen('@' . $domain)) === '@' . $domain) {
            return $email;
        }
    }

    return googleCalendarDocentiUserEmail($row['username'] ?? '');
}

function googleCalendarDocentiClassesForTeacherFromGestore($username)
{
    $u = googleCalendarDocentiLocalEsc($username);

    $rows = dbGetAll("
        SELECT DISTINCT UPPER(TRIM(c.classe)) AS classe
        FROM docente_insegna di
        JOIN docente d ON d.id = di.id_docente
        JOIN classi c ON c.id = di.id_classe
        WHERE d.username = '$u'
          AND c.classe IS NOT NULL
          AND c.classe <> ''
        ORDER BY c.classe
    ") ?: [];

    $out = [];
    foreach ($rows as $r) {
        $classe = strtoupper(trim((string)($r['classe'] ?? '')));
        if ($classe !== '') $out[] = $classe;
    }

    return googleCalendarDocentiMergeUnique([], $out);
}

function googleCalendarDocentiExtractClasseCdc($dettagli)
{
    $det = strtoupper(trim((string)$dettagli));

    if (preg_match('/\bCC\s+([0-9][A-Z]{1,4})\b/u', $det, $m)) {
        return strtoupper(trim($m[1]));
    }

    if (preg_match('/\b([0-9][A-Z]{1,4})\b/u', $det, $m)) {
        return strtoupper(trim($m[1]));
    }

    return '';
}

function googleCalendarDocentiAuleByAssenza($idAssenza)
{
    $id = intval($idAssenza);
    if ($id <= 0) return [];

    $rows = mb_dbGetAll("
        SELECT DISTINCT nroAula
        FROM oralezione
        WHERE idAssenza = $id
          AND nroAula IS NOT NULL
          AND nroAula <> ''
        ORDER BY CAST(nroAula AS UNSIGNED), nroAula
    ") ?: [];

    $out = [];
    foreach ($rows as $r) {
        $aula = trim((string)($r['nroAula'] ?? ''));
        if ($aula !== '') $out[] = $aula;
    }

    return googleCalendarDocentiMergeUnique([], $out);
}

function googleCalendarDocentiFetchCdcAndCollegio($username, $from, $to)
{
    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);

    $out = [];

    // A) CONSIGLI DI CLASSE: visibili solo ai docenti che insegnano nella classe letta da dettagli
    $classiDocente = googleCalendarDocentiClassesForTeacherFromGestore($username);
    $classiMap = [];
    foreach ($classiDocente as $c) {
        $classiMap[strtoupper(trim($c))] = true;
    }

    if (!empty($classiMap)) {
        $qCdc = "
            SELECT a.*
            FROM assenze a
            WHERE UPPER(TRIM(COALESCE(a.motivo, ''))) = 'CONSIGLIO DI CLASSE'
              AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
              AND DATE(a.dataInizio) <= '$toEsc'
              AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
            ORDER BY a.dataInizio, a.oraInizio
        ";

        foreach (mb_dbGetAll($qCdc) ?: [] as $a) {
            $idAssenza = intval($a['idAssenza'] ?? 0);
            if ($idAssenza <= 0) continue;

            $classe = googleCalendarDocentiExtractClasseCdc($a['dettagli'] ?? '');
            if ($classe === '' || !isset($classiMap[$classe])) continue;

            $date = substr((string)($a['dataInizio'] ?? ''), 0, 10);
            if ($date === '' || $date < $from || $date > $to) continue;

            $start = googleCalendarDocentiPickOraInizio($a);
            $end = googleCalendarDocentiPickOraFine($a);
            if ($end <= $start) $end = googleCalendarDocentiSlotEnd($start);

            $aule = googleCalendarDocentiAuleByAssenza($idAssenza);

            $out[] = [
                'source_key' => 'cdc:' . $idAssenza . ':' . $username,
                'source_type' => 'impegno',
                'summary' => 'Consiglio di classe',
                'description' => googleCalendarDocentiDescription('Consiglio di classe', [$classe], $aule, ''),
                'location' => implode(', ', $aule),
                'date' => $date,
                'start' => $start,
                'end' => $end,
                'classi' => [$classe],
                'aule' => $aule
            ];
        }
    }

    // B) COLLEGIO DOCENTI: visibile a TUTTI i docenti
    $qCollegio = "
        SELECT a.*
        FROM assenze a
        WHERE UPPER(TRIM(COALESCE(a.motivo, ''))) = 'IMPEGNO IN ISTITUTO'
          AND UPPER(TRIM(COALESCE(a.dettagli, ''))) LIKE '%COLLEGIO DOCENTI%'
          AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
          AND DATE(a.dataInizio) <= '$toEsc'
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
        ORDER BY a.dataInizio, a.oraInizio
    ";

    foreach (mb_dbGetAll($qCollegio) ?: [] as $a) {
        $idAssenza = intval($a['idAssenza'] ?? 0);
        if ($idAssenza <= 0) continue;

        $date = substr((string)($a['dataInizio'] ?? ''), 0, 10);
        if ($date === '' || $date < $from || $date > $to) continue;

        $start = googleCalendarDocentiPickOraInizio($a);
        $end = googleCalendarDocentiPickOraFine($a);
        if ($end <= $start) $end = googleCalendarDocentiSlotEnd($start);

        $aule = googleCalendarDocentiAuleByAssenza($idAssenza);
        if (empty($aule)) $aule = ['221'];

        $out[] = [
            'source_key' => 'collegio-docenti:' . $idAssenza . ':' . $username,
            'source_type' => 'impegno',
            'summary' => 'Collegio Docenti',
            'description' => googleCalendarDocentiDescription('Collegio Docenti', [], $aule, ''),
            'location' => implode(', ', $aule),
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'classi' => [],
            'aule' => $aule
        ];
    }

    return $out;
}

function googleCalendarDocentiFetchActivities($username, $from, $to)
{
    $u = googleCalendarDocentiMbEsc($username);
    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);
    $activities = [];

    $q = "
        SELECT
            o.idCalendario,
            o.idAssenza,
            o.dataGiorno,
            o.ora,
            o.siglaMateria,
            o.attivitaProgetto,
            m.nomeMateria,
            a.dataInizio AS assenzaDataInizio,
            a.dataFine AS assenzaDataFine,
            a.oraInizio AS assenzaOraInizio,
            a.oraFine AS assenzaOraFine,
            a.oraInizioReale AS assenzaOraInizioReale,
            a.oraFineReale AS assenzaOraFineReale,
            a.dettagli AS assenzaDettagli,
            GROUP_CONCAT(DISTINCT CONCAT(doc.cognome,' ',doc.nome) ORDER BY doc.cognome, doc.nome SEPARATOR ', ') AS docenti_nomi,
            GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi,
            GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule
        FROM oralezione o
        JOIN utilizza ut ON ut.idCalendario = o.idCalendario AND ut.username = '$u'
        LEFT JOIN utilizza utAll ON utAll.idCalendario = o.idCalendario AND utAll.username IS NOT NULL
        LEFT JOIN utente doc ON doc.username = utAll.username
        LEFT JOIN occupa oc ON oc.idCalendario = o.idCalendario
        LEFT JOIN materia m ON m.siglaMateria = o.siglaMateria
        LEFT JOIN assenze a ON a.idAssenza = o.idAssenza
        WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
          AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
        GROUP BY
            o.idCalendario,
            o.idAssenza,
            o.dataGiorno,
            o.ora,
            o.siglaMateria,
            o.attivitaProgetto,
            m.nomeMateria,
            a.dataInizio,
            a.dataFine,
            a.oraInizio,
            a.oraFine,
            a.oraInizioReale,
            a.oraFineReale,
            a.dettagli
        ORDER BY o.dataGiorno, o.ora, o.idCalendario
    ";

    $impegniByAssenza = [];
    $lessonsBySlot = [];
    $personalAbsences = googleCalendarDocentiFetchPersonalAbsences($username, $from, $to);
    $personalAbsencesBySlot = googleCalendarDocentiPersonalAbsencesBySlot($personalAbsences);
    $replacementByLessonSlot = googleCalendarDocentiReplacementMapForOriginalTeacher($username, $from, $to);
    $colleagueNotesBySlot = googleCalendarDocentiColleagueAbsenceNotesBySlot($username, $from, $to);
    $classNotesBySlot = googleCalendarDocentiClassEventNotesBySlot($username, $from, $to);

    foreach (mb_dbGetAll($q) ?: [] as $r) {
        $date = substr((string)($r['dataGiorno'] ?? ''), 0, 10);
        $start = googleCalendarDocentiNormOra($r['ora'] ?? '');
        if ($date === '' || $start === '') continue;

        $sigla = trim((string)($r['siglaMateria'] ?? ''));
        $nomeMateria = trim((string)($r['nomeMateria'] ?? ''));
        $attivita = trim((string)($r['attivitaProgetto'] ?? ''));
        $idAssenza = intval($r['idAssenza'] ?? 0);

        if ($attivita === '' && googleCalendarDocentiIsUdienza($sigla, $nomeMateria)) {
            continue;
        }
        if (googleCalendarDocentiIsPranzoStudio($sigla, $nomeMateria, $attivita)) {
            continue;
        }

        if ($attivita !== '' && $idAssenza > 0) {
            if (!isset($impegniByAssenza[$idAssenza])) {
                $impegniByAssenza[$idAssenza] = [
                    'idAssenza' => $idAssenza,
                    'title' => trim((string)($r['assenzaDettagli'] ?? '')) !== '' ? trim((string)$r['assenzaDettagli']) : $attivita,
                    'date' => substr((string)($r['assenzaDataInizio'] ?? $date), 0, 10),
                    'start' => googleCalendarDocentiPickOraInizio([
                        'oraInizioReale' => $r['assenzaOraInizioReale'] ?? '',
                        'oraInizio' => $r['assenzaOraInizio'] ?? ''
                    ]),
                    'end' => googleCalendarDocentiPickOraFine([
                        'oraFineReale' => $r['assenzaOraFineReale'] ?? '',
                        'oraFine' => $r['assenzaOraFine'] ?? ''
                    ]),
                    'classi' => [],
                    'aule' => [],
                    'docenti' => []
                ];
            }

            $impegniByAssenza[$idAssenza]['classi'] = array_values(array_unique(array_merge(
                $impegniByAssenza[$idAssenza]['classi'],
                googleCalendarDocentiCsv($r['classi'] ?? '')
            )));
            $impegniByAssenza[$idAssenza]['aule'] = array_values(array_unique(array_merge(
                $impegniByAssenza[$idAssenza]['aule'],
                googleCalendarDocentiCsv($r['aule'] ?? '')
            )));
            $impegniByAssenza[$idAssenza]['docenti'] = array_values(array_unique(array_merge(
                $impegniByAssenza[$idAssenza]['docenti'],
                googleCalendarDocentiCsv($r['docenti_nomi'] ?? '')
            )));
            continue;
        }

        $title = $attivita !== ''
            ? $attivita
            : ($sigla !== '' ? ($sigla . ($nomeMateria !== '' ? ' - ' . $nomeMateria : '')) : 'Lezione');

        $classi = googleCalendarDocentiCsv($r['classi'] ?? '');
        $aule = googleCalendarDocentiCsv($r['aule'] ?? '');
        $badge = $attivita !== '' ? 'Impegno in istituto' : 'Lezione curricolare';

        if ($attivita === '') {
            $slotKey = $date . '|' . $start;
            $absence = $personalAbsencesBySlot[$slotKey] ?? null;
            $replacement = googleCalendarDocentiFindReplacementForLesson($replacementByLessonSlot[$slotKey] ?? [], $classi, $aule);
            if ($absence || $replacement) {
                $absenceTitle = $absence ? (string)$absence['summary'] : 'Lezione annullata';
                if ($replacement) {
                    $absenceTitle = 'Lezione sostituita da ' . (string)$replacement['sostituto'];
                }
                $absenceKey = 'lezione-sostituita:' . ($replacement['id'] ?? ($absence['idAssenza'] ?? 'assenza')) . ':' . intval($r['idCalendario'] ?? 0) . ':' . $date . ':' . $start;
                if (!isset($lessonsBySlot[$absenceKey])) {
                    $lessonsBySlot[$absenceKey] = [
                        'source_ids' => [],
                        'summary' => $absenceTitle,
                        'source_type' => 'lezione_sostituita',
                        'subject_key' => 'ASSENZA-' . $absenceKey,
                        'description_badge' => $replacement ? 'Sostituzione della tua lezione' : (string)($absence['badge'] ?? 'Assenza'),
                        'date' => $date,
                        'start' => $start,
                        'end' => googleCalendarDocentiSlotEnd($start),
                        'classi' => [],
                        'aule' => [],
                        'docenti' => []
                    ];
                }
                $lessonsBySlot[$absenceKey]['source_ids'][] = intval($r['idCalendario'] ?? 0);
                $lessonsBySlot[$absenceKey]['classi'] = googleCalendarDocentiMergeUnique($lessonsBySlot[$absenceKey]['classi'], $classi);
                $lessonsBySlot[$absenceKey]['aule'] = googleCalendarDocentiMergeUnique($lessonsBySlot[$absenceKey]['aule'], $aule);
                $docentiInfo = [];
                if ($replacement && trim((string)$replacement['sostituto']) !== '') {
                    $docentiInfo[] = 'Sostituto: ' . trim((string)$replacement['sostituto']);
                }
                $lessonsBySlot[$absenceKey]['docenti'] = googleCalendarDocentiMergeUnique($lessonsBySlot[$absenceKey]['docenti'], $docentiInfo);
                continue;
            }

            sort($classi, SORT_NATURAL | SORT_FLAG_CASE);
            sort($aule, SORT_NATURAL | SORT_FLAG_CASE);
            $subjectKey = strtoupper(trim($sigla !== '' ? $sigla : $title));
            $lessonKeyParts = [
                $date,
                $start,
                $subjectKey,
                implode('|', array_map('strtoupper', $classi))
            ];
            $lessonKey = implode('::', $lessonKeyParts);

            if (!isset($lessonsBySlot[$lessonKey])) {
                $lessonsBySlot[$lessonKey] = [
                    'source_ids' => [],
                    'summary' => $title,
                    'subject_key' => $subjectKey,
                    'description_badge' => $badge,
                    'date' => $date,
                    'start' => $start,
                    'end' => googleCalendarDocentiSlotEnd($start),
                    'classi' => [],
                    'aule' => [],
                    'docenti' => []
                ];
            }

            $lessonsBySlot[$lessonKey]['source_ids'][] = intval($r['idCalendario'] ?? 0);
            $lessonsBySlot[$lessonKey]['classi'] = googleCalendarDocentiMergeUnique($lessonsBySlot[$lessonKey]['classi'], $classi);
            $lessonsBySlot[$lessonKey]['aule'] = googleCalendarDocentiMergeUnique($lessonsBySlot[$lessonKey]['aule'], $aule);
            $lessonsBySlot[$lessonKey]['docenti'] = googleCalendarDocentiMergeUnique($lessonsBySlot[$lessonKey]['docenti'], googleCalendarDocentiCsv($r['docenti_nomi'] ?? ''));
            $colleagueNotes = $colleagueNotesBySlot[$date . '|' . $start] ?? [];
            $lessonsBySlot[$lessonKey]['notes'] = googleCalendarDocentiMergeUnique(
                $lessonsBySlot[$lessonKey]['notes'] ?? [],
                $colleagueNotes
            );
            $classNoteKey = googleCalendarDocentiClassNoteKey($date, $start, $classi);
            $classNotes = $classNotesBySlot[$classNoteKey] ?? [];
            $lessonsBySlot[$lessonKey]['notes'] = googleCalendarDocentiMergeUnique(
                $lessonsBySlot[$lessonKey]['notes'] ?? [],
                $classNotes
            );
            if (!empty($lessonsBySlot[$lessonKey]['notes'])) {
                if (!empty($colleagueNotes)) {
                    $lessonsBySlot[$lessonKey]['source_type'] = 'lezione_codocente_assente';
                    if (strpos($lessonsBySlot[$lessonKey]['summary'], '⚠') !== 0) {
                        $lessonsBySlot[$lessonKey]['summary'] = '⚠ Codocente assente - ' . $lessonsBySlot[$lessonKey]['summary'];
                    }
                } elseif (!empty($classNotes)) {
                    $lessonsBySlot[$lessonKey]['source_type'] = 'lezione_classe_impegnata';
                    $eventRooms = googleCalendarDocentiClassEventRoomsBySlot($classNoteKey);
                    if (!empty($eventRooms)) {
                        $lessonsBySlot[$lessonKey]['aule'] = $eventRooms;
                    }
                    if (strpos($lessonsBySlot[$lessonKey]['summary'], '◆') !== 0) {
                        $lessonsBySlot[$lessonKey]['summary'] = '◆ Classe impegnata - ' . $lessonsBySlot[$lessonKey]['summary'];
                    }
                }
            }
            continue;
        }

        $activities[] = [
            'source_key' => 'oralezione:' . intval($r['idCalendario'] ?? 0) . ':' . $date . ':' . $start,
            'source_type' => 'oralezione',
            'summary' => $title,
            'description' => googleCalendarDocentiDescription($badge, $classi, $aule, trim((string)($r['docenti_nomi'] ?? ''))),
            'location' => implode(', ', $aule),
            'date' => $date,
            'start' => $start,
            'end' => googleCalendarDocentiSlotEnd($start),
            'classi' => $classi,
            'aule' => $aule
        ];
    }

    $lessonBlocks = googleCalendarDocentiBuildLessonBlocks($lessonsBySlot);

    foreach ($lessonBlocks as $lesson) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $lesson['source_ids']))));
        sort($ids, SORT_NUMERIC);
        $sourcePart = !empty($ids) ? implode('-', $ids) : hash('sha256', json_encode($lesson));

        $activities[] = [
            'source_key' => 'lezione-blocco:' . $sourcePart . ':' . $lesson['date'] . ':' . $lesson['start'] . ':' . $lesson['end'],
            'source_type' => (string)($lesson['source_type'] ?? 'lezione'),
            'summary' => googleCalendarDocentiPrefixTitleWithClasses($lesson['summary'], $lesson['classi']),
            'description' => googleCalendarDocentiDescription($lesson['description_badge'], $lesson['classi'], $lesson['aule'], implode(', ', $lesson['docenti']), $lesson['notes'] ?? []),
            'location' => implode(', ', $lesson['aule']),
            'date' => $lesson['date'],
            'start' => $lesson['start'],
            'end' => $lesson['end'],
            'classi' => $lesson['classi'],
            'aule' => $lesson['aule']
        ];
    }

    foreach ($impegniByAssenza as $impegno) {
        if (($impegno['date'] ?? '') === '') continue;
        $activities[] = [
            'source_key' => 'impegno:' . intval($impegno['idAssenza']),
            'source_type' => 'impegno',
            'summary' => $impegno['title'] !== '' ? $impegno['title'] : 'Impegno in istituto',
            'description' => googleCalendarDocentiDescription('Impegno in istituto', $impegno['classi'], $impegno['aule'], implode(', ', $impegno['docenti'])),
            'location' => implode(', ', $impegno['aule']),
            'date' => $impegno['date'],
            'start' => $impegno['start'],
            'end' => $impegno['end'] > $impegno['start'] ? $impegno['end'] : googleCalendarDocentiSlotEnd($impegno['start']),
            'classi' => $impegno['classi'],
            'aule' => $impegno['aule']
        ];
    }

    $activities = array_merge($activities, googleCalendarDocentiFetchSubstitutions($username, $from, $to));
    $activities = array_merge($activities, googleCalendarDocentiPersonalAbsenceActivities($personalAbsences));
    $activities = array_merge($activities, googleCalendarDocentiFetchAssignedInstituteCommitments($username, $from, $to));
    $activities = array_merge($activities, googleCalendarDocentiFetchDidacticOutings($username, $from, $to));
    $activities = array_merge($activities, googleCalendarDocentiFetchClassDidacticOutings($username, $from, $to));
    $activities = array_merge($activities, googleCalendarDocentiFetchClassInstituteCommitments($username, $from, $to));
    $activities = array_merge($activities, googleCalendarDocentiFetchCdcAndCollegio($username, $from, $to));
    $activities = googleCalendarDocentiCoalesceConsecutiveClassCommitments($activities);
    $activities = googleCalendarDocentiCoalesceDuplicateInstituteCommitments($activities);

    return googleCalendarDocentiDedupeActivities($activities);
}

function googleCalendarDocentiNormalizeCommitmentTitle($summary)
{
    $summary = trim((string)$summary);
    $summary = preg_replace('/^\s*(?:[A-Z0-9]+(?:\s*,\s*[A-Z0-9]+)*)\s*-\s*/i', '', $summary);
    $summary = preg_replace('/^\s*classe\s+impegnata\s*-\s*/i', '', $summary);
    $summary = preg_replace('/^\s*impegno\s+in\s+istituto\s*-\s*/i', '', $summary);
    $summary = preg_replace('/\s+/', ' ', (string)$summary);
    return strtoupper(trim((string)$summary));
}

function googleCalendarDocentiCoalesceDuplicateInstituteCommitments(array $activities)
{
    $groups = [];
    $others = [];

    foreach ($activities as $activity) {
        $type = strtolower(trim((string)($activity['source_type'] ?? '')));
        if ($type !== 'impegno') {
            $others[] = $activity;
            continue;
        }

        $titleKey = googleCalendarDocentiNormalizeCommitmentTitle($activity['summary'] ?? '');
        if ($titleKey === '') {
            $others[] = $activity;
            continue;
        }

        $classi = isset($activity['classi']) && is_array($activity['classi']) ? $activity['classi'] : [];
        $aule = isset($activity['aule']) && is_array($activity['aule']) ? $activity['aule'] : [];
        sort($classi, SORT_NATURAL | SORT_FLAG_CASE);
        sort($aule, SORT_NATURAL | SORT_FLAG_CASE);

        $keyParts = [
            (string)($activity['date'] ?? ''),
            (string)($activity['start'] ?? ''),
            (string)($activity['end'] ?? ''),
            $titleKey,
            implode('|', array_map('strtoupper', $classi))
        ];

        if (!empty($classi)) {
            $keyParts[] = implode('|', array_map('strtoupper', $aule));
        }

        $key = implode('::', $keyParts);

        if (!isset($groups[$key])) $groups[$key] = [];
        $groups[$key][] = $activity;
    }

    $out = $others;
    foreach ($groups as $items) {
        if (count($items) === 1) {
            $out[] = $items[0];
            continue;
        }

        usort($items, function ($a, $b) {
            $rankA = googleCalendarDocentiInstituteCommitmentRank($a);
            $rankB = googleCalendarDocentiInstituteCommitmentRank($b);
            if ($rankA === $rankB) return strcmp((string)($a['source_key'] ?? ''), (string)($b['source_key'] ?? ''));
            return $rankB <=> $rankA;
        });

        $merged = $items[0];
        foreach (array_slice($items, 1) as $item) {
            $merged['classi'] = googleCalendarDocentiMergeUnique($merged['classi'] ?? [], $item['classi'] ?? []);
            $merged['aule'] = googleCalendarDocentiMergeUnique($merged['aule'] ?? [], $item['aule'] ?? []);
            if (empty($merged['location']) && !empty($item['location'])) $merged['location'] = $item['location'];
            $merged['source_key'] = 'impegno-dedup:' . hash('sha256', (string)$merged['source_key'] . '|' . (string)$item['source_key']);
        }
        $out[] = $merged;
    }

    return $out;
}

function googleCalendarDocentiInstituteCommitmentRank(array $activity)
{
    $summary = trim((string)($activity['summary'] ?? ''));
    $classi = isset($activity['classi']) && is_array($activity['classi']) ? $activity['classi'] : [];
    $aule = isset($activity['aule']) && is_array($activity['aule']) ? $activity['aule'] : [];
    $hasRoom = !empty($aule) || trim((string)($activity['location'] ?? '')) !== '';

    if (!empty($classi) && stripos($summary, 'Classe impegnata') !== false) return 300 + ($hasRoom ? 10 : 0);
    if (stripos($summary, 'Classe impegnata') !== false) return 50 + ($hasRoom ? 10 : 0);
    if (stripos($summary, 'Impegno in istituto') !== false) return 150 + ($hasRoom ? 10 : 0);
    return 200 + ($hasRoom ? 10 : 0);
}

function googleCalendarDocentiCoalesceConsecutiveClassCommitments(array $activities)
{
    $groups = [];
    $others = [];

    foreach ($activities as $activity) {
        $type = strtolower(trim((string)($activity['source_type'] ?? '')));
        $summary = trim((string)($activity['summary'] ?? ''));
        if ($type !== 'impegno' || stripos($summary, 'Classe impegnata') === false) {
            $others[] = $activity;
            continue;
        }

        $classi = isset($activity['classi']) && is_array($activity['classi']) ? $activity['classi'] : [];
        $aule = isset($activity['aule']) && is_array($activity['aule']) ? $activity['aule'] : [];
        sort($classi, SORT_NATURAL | SORT_FLAG_CASE);
        sort($aule, SORT_NATURAL | SORT_FLAG_CASE);

        $key = implode('::', [
            (string)($activity['date'] ?? ''),
            strtoupper($summary),
            implode('|', array_map('strtoupper', $classi)),
            implode('|', array_map('strtoupper', $aule))
        ]);

        if (!isset($groups[$key])) $groups[$key] = [];
        $groups[$key][] = $activity;
    }

    $out = $others;
    foreach ($groups as $items) {
        usort($items, function ($a, $b) {
            return strcmp((string)($a['start'] ?? ''), (string)($b['start'] ?? ''));
        });

        $current = null;
        foreach ($items as $item) {
            if ($current === null) {
                $current = $item;
                continue;
            }

            $curEnd = (string)($current['end'] ?? '');
            $nextStart = (string)($item['start'] ?? '');
            if (googleCalendarDocentiAreContiguous($curEnd, $nextStart) || $curEnd >= $nextStart) {
                if ((string)($item['end'] ?? '') > (string)($current['end'] ?? '')) {
                    $current['end'] = $item['end'];
                }
                $current['source_key'] = 'class-impegno-merged:' . hash('sha256', (string)$current['source_key'] . '|' . (string)$item['source_key']);
                continue;
            }

            $out[] = $current;
            $current = $item;
        }

        if ($current !== null) $out[] = $current;
    }

    return $out;
}

function googleCalendarDocentiDedupeActivities(array $activities)
{
    $out = [];
    $seen = [];
    foreach ($activities as $activity) {
        $key = (string)($activity['source_key'] ?? '');
        if ($key === '') continue;
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $activity;
    }
    return $out;
}

function googleCalendarDocentiFetchPersonalAbsences($username, $from, $to)
{
    $u = googleCalendarDocentiMbEsc($username);
    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);

    $q = "
        SELECT a.*
        FROM assenze a
        WHERE a.idAssenza IN (
            SELECT DISTINCT ut.IDassenza
            FROM utilizza ut
            WHERE ut.username = '$u'
              AND ut.IDassenza IS NOT NULL
        )
          AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
          AND DATE(a.dataInizio) <= '$toEsc'
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
        ORDER BY a.dataInizio, a.oraInizio
    ";

    $out = [];
    foreach (mb_dbGetAll($q) ?: [] as $r) {
        if (
            googleCalendarDocentiIsUscita($r['motivo'] ?? '', $r['dettagli'] ?? '') ||
            googleCalendarDocentiIsViaggio($r['motivo'] ?? '', $r['dettagli'] ?? '')
        ) {
            continue;
        }
        if (googleCalendarDocentiIsInstituteCommitment($r['motivo'] ?? '', $r['dettagli'] ?? '')) {
            continue;
        }

        $idAssenza = intval($r['idAssenza'] ?? 0);
        if ($idAssenza <= 0) continue;

        $dataFrom = substr((string)($r['dataInizio'] ?? ''), 0, 10);
        $dataTo = substr((string)($r['dataFine'] ?? ''), 0, 10);
        if ($dataFrom === '') continue;
        if ($dataTo === '') $dataTo = $dataFrom;

        $motivo = trim((string)($r['motivo'] ?? ''));
        $dettagli = trim((string)($r['dettagli'] ?? ''));
        $title = $motivo !== '' ? $motivo : 'Assenza';
        if ($dettagli !== '' && strtoupper($dettagli) !== strtoupper($motivo)) {
            $title .= ' - ' . $dettagli;
        }

        $start = googleCalendarDocentiPickOraInizio($r);
        $end = googleCalendarDocentiPickOraFine($r);
        if ($end <= $start) $end = googleCalendarDocentiSlotEnd($start);

        $d1 = new DateTime($dataFrom);
        $d2 = new DateTime($dataTo);
        if ($d2 < $d1) $d2 = clone $d1;

        for ($d = clone $d1; $d <= $d2; $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            if ($date < $from || $date > $to) continue;

            $out[] = [
                'idAssenza' => $idAssenza,
                'source_key' => 'assenza-personale:' . $idAssenza . ':' . $date,
                'source_type' => 'assenza',
                'summary' => $title,
                'badge' => $motivo !== '' ? $motivo : 'Assenza',
                'description' => googleCalendarDocentiDescription($motivo !== '' ? $motivo : 'Assenza', [], [], ''),
                'location' => '',
                'date' => $date,
                'start' => $start,
                'end' => $end,
                'classi' => [],
                'aule' => []
            ];
        }
    }

    return $out;
}

function googleCalendarDocentiPersonalAbsenceActivities(array $absences)
{
    $out = [];
    foreach ($absences as $a) {
        $out[] = [
            'source_key' => $a['source_key'],
            'source_type' => $a['source_type'],
            'summary' => $a['summary'],
            'description' => $a['description'],
            'location' => '',
            'date' => $a['date'],
            'start' => $a['start'],
            'end' => $a['end'],
            'classi' => [],
            'aule' => []
        ];
    }
    return $out;
}

function googleCalendarDocentiPersonalAbsencesBySlot(array $absences)
{
    $map = [];
    foreach ($absences as $a) {
        foreach (googleCalendarDocentiSlotsBetween($a['start'], $a['end']) as $slot) {
            $map[$a['date'] . '|' . $slot] = $a;
        }
    }
    return $map;
}

function googleCalendarDocentiSlotsBetween($start, $end)
{
    $startMin = googleCalendarDocentiMinutes($start);
    $endMin = googleCalendarDocentiMinutes($end);
    if ($startMin === null || $endMin === null || $endMin <= $startMin) return [];

    $out = [];
    foreach (googleCalendarDocentiSchoolSlots() as $slot) {
        $slotStart = googleCalendarDocentiMinutes($slot);
        $slotEnd = googleCalendarDocentiMinutes(googleCalendarDocentiSlotEnd($slot));
        if ($slotStart === null || $slotEnd === null) continue;
        if ($slotStart < $endMin && $slotEnd > $startMin) {
            $out[] = $slot;
        }
    }
    return $out;
}

function googleCalendarDocentiReplacementMapForOriginalTeacher($username, $from, $to)
{
    $u = googleCalendarDocentiLocalEsc($username);
    $fromEsc = googleCalendarDocentiLocalEsc($from);
    $toEsc = googleCalendarDocentiLocalEsc($to);
    $hasStato = googleCalendarDocentiTableHasColumn('sostituzioni', 'stato');
    $whereStato = $hasStato ? "AND (s.stato IS NULL OR UPPER(TRIM(s.stato)) <> 'ANNULLATA')" : "";

    $q = "
        SELECT
            s.idSostituzione,
            s.data,
            s.oraInizio,
            s.oraFine,
            s.classe,
            s.aula,
            ds.cognome AS cognomeSostituto,
            ds.nome AS nomeSostituto
        FROM sostituzioni s
        LEFT JOIN docente ds ON ds.id = s.idDocenteSostituto
        LEFT JOIN docente dd ON dd.id = s.idDocenteSostituito
        WHERE dd.username = '$u'
          AND s.data BETWEEN '$fromEsc' AND '$toEsc'
          $whereStato
        ORDER BY s.data, s.oraInizio, s.classe, s.aula
    ";

    $map = [];
    foreach (dbGetAll($q) ?: [] as $r) {
        $date = substr((string)($r['data'] ?? ''), 0, 10);
        $start = googleCalendarDocentiNormOra($r['oraInizio'] ?? '');
        $end = googleCalendarDocentiNormOra($r['oraFine'] ?? '');
        if ($date === '' || $start === '') continue;
        if ($end === '') $end = googleCalendarDocentiSlotEnd($start);

        $replacement = [
            'id' => intval($r['idSostituzione'] ?? 0),
            'classe' => strtoupper(trim((string)($r['classe'] ?? ''))),
            'aula' => trim((string)($r['aula'] ?? '')),
            'sostituto' => trim((string)($r['cognomeSostituto'] ?? '') . ' ' . (string)($r['nomeSostituto'] ?? ''))
        ];

        foreach (googleCalendarDocentiSlotsBetween($start, $end) as $slot) {
            $key = $date . '|' . $slot;
            if (!isset($map[$key])) $map[$key] = [];
            $map[$key][] = $replacement;
        }
    }
    return $map;
}

function googleCalendarDocentiFindReplacementForLesson(array $replacements, array $classi, array $aule)
{
    $classiUp = array_map('strtoupper', $classi);
    foreach ($replacements as $r) {
        $classe = strtoupper(trim((string)($r['classe'] ?? '')));
        $aula = trim((string)($r['aula'] ?? ''));
        $classOk = $classe === '' || in_array($classe, $classiUp, true);
        $roomOk = $aula === '' || in_array($aula, $aule, true);
        if ($classOk && $roomOk) return $r;
    }
    return null;
}

function googleCalendarDocentiColleagueAbsenceNotesBySlot($username, $from, $to)
{
    $u = googleCalendarDocentiMbEsc($username);
    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);

    $q = "
        SELECT DISTINCT
            oSelf.dataGiorno,
            oSelf.ora,
            utCol.username,
            CONCAT(uCol.cognome,' ',uCol.nome) AS nome,
            a.*
        FROM oralezione oSelf
        JOIN utilizza utSelf ON utSelf.idCalendario = oSelf.idCalendario AND utSelf.username = '$u'
        JOIN occupa ocSelf ON ocSelf.idCalendario = oSelf.idCalendario
        JOIN occupa ocCol ON ocCol.classe = ocSelf.classe
        JOIN oralezione oCol ON oCol.idCalendario = ocCol.idCalendario
                          AND oCol.dataGiorno = oSelf.dataGiorno
                          AND oCol.ora = oSelf.ora
                          AND (oCol.stato IS NULL OR oCol.stato <> 'CANCELLATO')
        JOIN utilizza utCol ON utCol.idCalendario = oCol.idCalendario
                         AND utCol.username IS NOT NULL
                         AND utCol.username <> ''
                         AND utCol.username <> '$u'
        JOIN utente uCol ON uCol.username = utCol.username
        JOIN utilizza utAss ON utAss.username = utCol.username
                          AND utAss.IDassenza IS NOT NULL
        JOIN assenze a ON a.idAssenza = utAss.IDassenza
        WHERE oSelf.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
          AND DATE(COALESCE(NULLIF(NULLIF(a.dataFine,''),'0000-00-00'), a.dataInizio)) >= oSelf.dataGiorno
          AND DATE(a.dataInizio) <= oSelf.dataGiorno
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
          AND (oSelf.stato IS NULL OR oSelf.stato <> 'CANCELLATO')
        ORDER BY oSelf.dataGiorno, oSelf.ora, uCol.cognome, uCol.nome
    ";

    $map = [];
    foreach (mb_dbGetAll($q) ?: [] as $r) {
        $date = substr((string)($r['dataGiorno'] ?? ''), 0, 10);
        $slot = googleCalendarDocentiNormOra($r['ora'] ?? '');
        if ($date === '' || $slot === '') continue;

        $absenceStart = googleCalendarDocentiPickOraInizio($r);
        $absenceEnd = googleCalendarDocentiPickOraFine($r);
        if (!in_array($slot, googleCalendarDocentiSlotsBetween($absenceStart, $absenceEnd), true)) {
            continue;
        }

        $nome = trim((string)($r['nome'] ?? $r['username'] ?? 'Codocente'));
        $motivo = trim((string)($r['motivo'] ?? 'assenza'));
        $dettagli = trim((string)($r['dettagli'] ?? ''));
        $tipo = 'assente';
        if (googleCalendarDocentiIsUscita($motivo, $dettagli) || googleCalendarDocentiIsViaggio($motivo, $dettagli)) {
            $tipo = 'in uscita didattica';
        }

        $key = $date . '|' . $slot;
        if (!isset($map[$key])) $map[$key] = [];
        $map[$key][] = $nome . ' ' . $tipo;
    }

    foreach ($map as $key => $notes) {
        $map[$key] = googleCalendarDocentiMergeUnique([], $notes);
    }

    return $map;
}

function googleCalendarDocentiClassNoteKey($date, $slot, array $classi)
{
    $classi = array_map('strtoupper', $classi);
    sort($classi, SORT_NATURAL | SORT_FLAG_CASE);
    return $date . '|' . $slot . '|' . implode('|', $classi);
}

function googleCalendarDocentiClassEventNotesBySlot($username, $from, $to)
{
    $u = googleCalendarDocentiMbEsc($username);
    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);

    $lessonRows = mb_dbGetAll("
        SELECT DISTINCT
            o.dataGiorno,
            o.ora,
            oc.classe
        FROM oralezione o
        JOIN utilizza ut ON ut.idCalendario = o.idCalendario AND ut.username = '$u'
        JOIN occupa oc ON oc.idCalendario = o.idCalendario
        WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
          AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
          AND oc.classe IS NOT NULL
          AND oc.classe <> ''
    ") ?: [];

    $slotClasses = [];
    foreach ($lessonRows as $r) {
        $date = substr((string)($r['dataGiorno'] ?? ''), 0, 10);
        $slot = googleCalendarDocentiNormOra($r['ora'] ?? '');
        $classe = strtoupper(trim((string)($r['classe'] ?? '')));
        if ($date === '' || $slot === '' || $classe === '') continue;
        $key = $date . '|' . $slot;
        if (!isset($slotClasses[$key])) $slotClasses[$key] = [];
        $slotClasses[$key][] = $classe;
    }

    $map = [];
    foreach ($slotClasses as $slotKey => $classi) {
        [$date, $slot] = explode('|', $slotKey, 2);
        $classi = array_values(array_unique($classi));
        $notes = googleCalendarDocentiClassAbsenceNotes($date, $slot, $classi);
        $notes = googleCalendarDocentiMergeUnique($notes, googleCalendarDocentiClassInstituteCommitmentNotes($date, $slot, $classi));
        if (!empty($notes)) {
            $noteKey = googleCalendarDocentiClassNoteKey($date, $slot, $classi);
            $map[$noteKey] = $notes;
            googleCalendarDocentiClassEventRoomsBySlot($noteKey, googleCalendarDocentiClassInstituteCommitmentRooms($date, $slot, $classi));
        }
    }

    return $map;
}

function googleCalendarDocentiClassEventRoomsBySlot($key, $rooms = null)
{
    static $map = [];
    $key = (string)$key;
    if (is_array($rooms)) {
        $map[$key] = googleCalendarDocentiMergeUnique($map[$key] ?? [], $rooms);
    }
    return $map[$key] ?? [];
}

function googleCalendarDocentiClassAbsenceNotes($date, $slot, array $classi)
{
    if (empty($classi)) return [];
    $dateEsc = googleCalendarDocentiMbEsc($date);
    $inClassi = implode(',', array_map(function ($classe) {
        return "'" . googleCalendarDocentiMbEsc($classe) . "'";
    }, $classi));

    $rows = mb_dbGetAll("
        SELECT DISTINCT
            a.idAssenza,
            a.motivo,
            a.dettagli,
            a.oraInizio,
            a.oraFine,
            a.oraInizioReale,
            a.oraFineReale,
            oc.classe
        FROM assenze a
        JOIN occupa oc ON oc.IDassenza = a.idAssenza
        WHERE oc.classe IN ($inClassi)
          AND DATE(COALESCE(NULLIF(NULLIF(a.dataFine,''),'0000-00-00'), a.dataInizio)) >= '$dateEsc'
          AND DATE(a.dataInizio) <= '$dateEsc'
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
    ") ?: [];

    $notes = [];
    foreach ($rows as $r) {
        $motivo = trim((string)($r['motivo'] ?? ''));
        $dettagli = trim((string)($r['dettagli'] ?? ''));
        if (!googleCalendarDocentiIsUscita($motivo, $dettagli) && !googleCalendarDocentiIsViaggio($motivo, $dettagli)) {
            continue;
        }

        if (!in_array($slot, googleCalendarDocentiSlotsBetween(
            googleCalendarDocentiPickOraInizio($r),
            googleCalendarDocentiPickOraFine($r)
        ), true)) {
            continue;
        }

        $classe = strtoupper(trim((string)($r['classe'] ?? '')));
        $tipo = googleCalendarDocentiIsViaggio($motivo, $dettagli)
            ? 'viaggio di istruzione'
            : (googleCalendarDocentiIsUscitaFuori($motivo, $dettagli) ? 'uscita fuori comune' : 'uscita didattica');
        $notes[] = 'La classe ' . $classe . ' è in ' . $tipo;
    }

    return googleCalendarDocentiMergeUnique([], $notes);
}

function googleCalendarDocentiClassInstituteCommitmentNotes($date, $slot, array $classi)
{
    if (empty($classi)) return [];
    $dateEsc = googleCalendarDocentiMbEsc($date);
    $slotEsc = googleCalendarDocentiMbEsc($slot);
    $inClassi = implode(',', array_map(function ($classe) {
        return "'" . googleCalendarDocentiMbEsc($classe) . "'";
    }, $classi));

    $rows = mb_dbGetAll("
        SELECT DISTINCT
            o.attivitaProgetto,
            o.nroAula,
            o.idAssenza,
            a.dettagli,
            oc.classe
        FROM oralezione o
        JOIN occupa oc ON oc.idCalendario = o.idCalendario
        LEFT JOIN assenze a ON a.idAssenza = o.idAssenza
        WHERE o.dataGiorno = '$dateEsc'
          AND o.ora = '$slotEsc'
          AND oc.classe IN ($inClassi)
          AND o.attivitaProgetto IS NOT NULL
          AND o.attivitaProgetto <> ''
          AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
    ") ?: [];

    $notes = [];
    foreach ($rows as $r) {
        $classe = strtoupper(trim((string)($r['classe'] ?? '')));
        $title = trim((string)($r['dettagli'] ?? ''));
        if ($title === '') $title = trim((string)($r['attivitaProgetto'] ?? 'impegno in istituto'));
        $aula = trim((string)($r['nroAula'] ?? ''));
        $notes[] = 'La classe ' . $classe . ' ha impegno in istituto: ' . $title . ($aula !== '' ? ' (aula ' . $aula . ')' : '');
    }

    return googleCalendarDocentiMergeUnique([], $notes);
}

function googleCalendarDocentiClassInstituteCommitmentRooms($date, $slot, array $classi)
{
    if (empty($classi)) return [];
    $dateEsc = googleCalendarDocentiMbEsc($date);
    $slotEsc = googleCalendarDocentiMbEsc($slot);
    $inClassi = implode(',', array_map(function ($classe) {
        return "'" . googleCalendarDocentiMbEsc($classe) . "'";
    }, $classi));

    $rows = mb_dbGetAll("
        SELECT DISTINCT o.nroAula
        FROM oralezione o
        JOIN occupa oc ON oc.idCalendario = o.idCalendario
        WHERE o.dataGiorno = '$dateEsc'
          AND o.ora = '$slotEsc'
          AND oc.classe IN ($inClassi)
          AND o.attivitaProgetto IS NOT NULL
          AND o.attivitaProgetto <> ''
          AND o.nroAula IS NOT NULL
          AND o.nroAula <> ''
          AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
        ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula
    ") ?: [];

    $out = [];
    foreach ($rows as $r) {
        $aula = trim((string)($r['nroAula'] ?? ''));
        if ($aula !== '') $out[] = $aula;
    }
    return googleCalendarDocentiMergeUnique([], $out);
}

function googleCalendarDocentiBuildLessonBlocks(array $lessonsBySlot)
{
    $groups = [];
    foreach ($lessonsBySlot as $lesson) {
        sort($lesson['classi'], SORT_NATURAL | SORT_FLAG_CASE);
        sort($lesson['aule'], SORT_NATURAL | SORT_FLAG_CASE);
        $groupKey = implode('::', [
            $lesson['date'],
            $lesson['subject_key'],
            implode('|', array_map('strtoupper', $lesson['classi']))
        ]);
        if (!isset($groups[$groupKey])) $groups[$groupKey] = [];
        $groups[$groupKey][] = $lesson;
    }

    $blocks = [];
    foreach ($groups as $items) {
        usort($items, function ($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        $current = null;
        foreach ($items as $lesson) {
            if ($current === null) {
                $current = $lesson;
                continue;
            }

            if (googleCalendarDocentiAreContiguous($current['end'], $lesson['start'])) {
                $current['end'] = $lesson['end'];
                $current['source_ids'] = googleCalendarDocentiMergeUnique($current['source_ids'], $lesson['source_ids']);
                $current['docenti'] = googleCalendarDocentiMergeUnique($current['docenti'], $lesson['docenti']);
                $current['aule'] = googleCalendarDocentiMergeUnique($current['aule'], $lesson['aule']);
                $current['notes'] = googleCalendarDocentiMergeUnique($current['notes'] ?? [], $lesson['notes'] ?? []);
                continue;
            }

            $blocks[] = $current;
            $current = $lesson;
        }

        if ($current !== null) {
            $blocks[] = $current;
        }
    }

    return $blocks;
}

function googleCalendarDocentiFetchDidacticOutings($username, $from, $to)
{
    $u = googleCalendarDocentiMbEsc($username);
    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);

    $q = "
        SELECT
            a.*
        FROM assenze a
        WHERE a.idAssenza IN (
            SELECT DISTINCT ut.IDassenza
            FROM utilizza ut
            WHERE ut.username = '$u'
              AND ut.IDassenza IS NOT NULL
        )
          AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
          AND DATE(a.dataInizio) <= '$toEsc'
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
        ORDER BY a.dataInizio, a.oraInizio
    ";

    $out = [];
    foreach (mb_dbGetAll($q) ?: [] as $r) {
        $motivo = trim((string)($r['motivo'] ?? ''));
        $dettagli = trim((string)($r['dettagli'] ?? ''));
        $isUscita = googleCalendarDocentiIsUscita($motivo, $dettagli);
        $isViaggio = googleCalendarDocentiIsViaggio($motivo, $dettagli);
        if (!$isUscita && !$isViaggio) {
            continue;
        }

        $idAssenza = intval($r['idAssenza'] ?? 0);
        if ($idAssenza <= 0) continue;

        $dataFrom = substr((string)($r['dataInizio'] ?? ''), 0, 10);
        $dataTo = substr((string)($r['dataFine'] ?? ''), 0, 10);
        if ($dataFrom === '') continue;
        if ($dataTo === '') $dataTo = $dataFrom;

        $start = googleCalendarDocentiPickOraInizio($r);
        $end = googleCalendarDocentiPickOraFine($r);
        if ($end <= $start) {
            $end = googleCalendarDocentiSlotEnd($start);
        }

        $classi = googleCalendarDocentiClassiByAssenza($idAssenza);
        $docenti = googleCalendarDocentiDocentiByAssenza($idAssenza);
        $base = $isViaggio ? 'Viaggio di istruzione' : (googleCalendarDocentiIsUscitaFuori($motivo, $dettagli) ? 'Uscita fuori comune' : 'Uscita didattica');
        $summary = $base . ($dettagli !== '' ? ' - ' . $dettagli : '');

        $d1 = new DateTime($dataFrom);
        $d2 = new DateTime($dataTo);
        if ($d2 < $d1) $d2 = clone $d1;

        for ($d = clone $d1; $d <= $d2; $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            if ($date < $from || $date > $to) continue;

            $out[] = [
                'source_key' => 'uscita:' . $idAssenza . ':' . $date,
                'source_type' => 'uscita',
                'summary' => googleCalendarDocentiPrefixTitleWithClasses($summary, $classi),
                'description' => googleCalendarDocentiDescription($base, $classi, [], implode(', ', $docenti)),
                'location' => '',
                'date' => $date,
                'start' => $start,
                'end' => $end,
                'classi' => $classi,
                'aule' => []
            ];
        }
    }

    return $out;
}

function googleCalendarDocentiFetchClassDidacticOutings($username, $from, $to)
{
    $classi = googleCalendarDocentiClassesForTeacher($username, $from, $to);
    if (empty($classi)) return [];

    $classiEsc = array_map(function ($classe) {
        return "'" . googleCalendarDocentiMbEsc($classe) . "'";
    }, $classi);

    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);
    $inClassi = implode(',', $classiEsc);

    $q = "
        SELECT DISTINCT a.*
        FROM assenze a
        JOIN occupa oc ON oc.IDassenza = a.idAssenza
        WHERE oc.classe IN ($inClassi)
          AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
          AND DATE(a.dataInizio) <= '$toEsc'
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
        ORDER BY a.dataInizio, a.oraInizio
    ";

    return googleCalendarDocentiBuildOutingsFromAssenze($q, $from, $to);
}

function googleCalendarDocentiFetchAssignedInstituteCommitments($username, $from, $to)
{
    $u = googleCalendarDocentiMbEsc($username);
    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);

    $q = "
        SELECT
            a.idAssenza,
            a.dataInizio,
            a.dataFine,
            a.oraInizio,
            a.oraFine,
            a.oraInizioReale,
            a.oraFineReale,
            a.motivo,
            a.dettagli,
            o.dataGiorno,
            o.ora,
            o.attivitaProgetto,
            GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi,
            GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule,
            GROUP_CONCAT(DISTINCT CONCAT(doc.cognome,' ',doc.nome) ORDER BY doc.cognome, doc.nome SEPARATOR ', ') AS docenti_nomi
        FROM assenze a
        JOIN utilizza utSelf ON utSelf.IDassenza = a.idAssenza AND utSelf.username = '$u'
        LEFT JOIN oralezione o ON o.idAssenza = a.idAssenza
                            AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
        LEFT JOIN occupa oc ON oc.idCalendario = o.idCalendario OR oc.IDassenza = a.idAssenza
        LEFT JOIN utilizza utAll ON utAll.IDassenza = a.idAssenza
                              AND utAll.username IS NOT NULL
                              AND utAll.username <> ''
        LEFT JOIN utente doc ON doc.username = utAll.username
        WHERE DATE(COALESCE(NULLIF(NULLIF(a.dataFine,''),'0000-00-00'), a.dataInizio)) >= '$fromEsc'
          AND DATE(a.dataInizio) <= '$toEsc'
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
        GROUP BY
            a.idAssenza,
            a.dataInizio,
            a.dataFine,
            a.oraInizio,
            a.oraFine,
            a.oraInizioReale,
            a.oraFineReale,
            a.motivo,
            a.dettagli,
            o.dataGiorno,
            o.ora,
            o.attivitaProgetto
        ORDER BY a.dataInizio, a.oraInizio
    ";

    $byKey = [];
    foreach (mb_dbGetAll($q) ?: [] as $r) {
        $motivo = trim((string)($r['motivo'] ?? ''));
        $dettagli = trim((string)($r['dettagli'] ?? ''));
        if (googleCalendarDocentiIsUscita($motivo, $dettagli) || googleCalendarDocentiIsViaggio($motivo, $dettagli)) {
            continue;
        }

        $idAssenza = intval($r['idAssenza'] ?? 0);
        if ($idAssenza <= 0) continue;

        $date = substr((string)($r['dataGiorno'] ?? ''), 0, 10);
        if ($date === '') $date = substr((string)($r['dataInizio'] ?? ''), 0, 10);
        if ($date === '') continue;

        $title = $dettagli !== '' ? $dettagli : trim((string)($r['attivitaProgetto'] ?? ''));
        if ($title === '') $title = $motivo !== '' ? $motivo : 'Impegno in istituto';
        $slot = googleCalendarDocentiNormOra($r['ora'] ?? '');
        $start = $slot !== '' ? $slot : googleCalendarDocentiPickOraInizio($r);
        $end = $slot !== '' ? googleCalendarDocentiSlotEnd($slot) : googleCalendarDocentiPickOraFine($r);
        if ($end <= $start) $end = googleCalendarDocentiSlotEnd($start);

        $classi = googleCalendarDocentiCsv($r['classi'] ?? '');
        $aule = googleCalendarDocentiCsv($r['aule'] ?? '');
        $docenti = googleCalendarDocentiCsv($r['docenti_nomi'] ?? '');

        sort($classi, SORT_NATURAL | SORT_FLAG_CASE);
        sort($aule, SORT_NATURAL | SORT_FLAG_CASE);
        $key = 'impegno-assegnato:' . hash('sha256', implode('|', [
            $date,
            strtoupper($title),
            implode(',', array_map('strtoupper', $classi)),
            implode(',', array_map('strtoupper', $aule))
        ]));

        if (!isset($byKey[$key])) {
            $isClassCommitment = !empty($classi);
            $byKey[$key] = [
                'source_key' => $key,
                'source_type' => 'impegno',
                'summary' => $isClassCommitment ? ('Classe impegnata - ' . $title) : $title,
                'description_badge' => $isClassCommitment ? 'Impegno in istituto della classe' : 'Impegno in istituto',
                'date' => $date,
                'start' => $start,
                'end' => $end,
                'classi' => [],
                'aule' => [],
                'docenti' => []
            ];
        }

        $byKey[$key]['classi'] = googleCalendarDocentiMergeUnique($byKey[$key]['classi'], $classi);
        $byKey[$key]['aule'] = googleCalendarDocentiMergeUnique($byKey[$key]['aule'], $aule);
        $byKey[$key]['docenti'] = googleCalendarDocentiMergeUnique($byKey[$key]['docenti'], $docenti);
        if ($start < $byKey[$key]['start']) $byKey[$key]['start'] = $start;
        if ($end > $byKey[$key]['end']) $byKey[$key]['end'] = $end;
    }

    $out = [];
    foreach ($byKey as $event) {
        $out[] = [
            'source_key' => $event['source_key'],
            'source_type' => $event['source_type'],
            'summary' => googleCalendarDocentiPrefixTitleWithClasses($event['summary'], $event['classi']),
            'description' => googleCalendarDocentiDescription($event['description_badge'], $event['classi'], $event['aule'], implode(', ', $event['docenti'])),
            'location' => implode(', ', $event['aule']),
            'date' => $event['date'],
            'start' => $event['start'],
            'end' => $event['end'],
            'classi' => $event['classi'],
            'aule' => $event['aule']
        ];
    }

    return $out;
}

function googleCalendarDocentiFetchClassInstituteCommitments($username, $from, $to)
{
    $classi = googleCalendarDocentiClassesForTeacher($username, $from, $to);
    if (empty($classi)) return [];

    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);
    $inClassi = implode(',', array_map(function ($classe) {
        return "'" . googleCalendarDocentiMbEsc($classe) . "'";
    }, $classi));

    $q = "
        SELECT
            o.idAssenza,
            o.dataGiorno,
            o.ora,
            o.attivitaProgetto,
            a.dettagli,
            a.oraInizio,
            a.oraFine,
            a.oraInizioReale,
            a.oraFineReale,
            GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi,
            GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule,
            GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_nomi
        FROM oralezione o
        JOIN occupa oc ON oc.idCalendario = o.idCalendario
        LEFT JOIN utilizza ut ON ut.idCalendario = o.idCalendario AND ut.username IS NOT NULL AND ut.username <> ''
        LEFT JOIN utente u ON u.username = ut.username
        LEFT JOIN assenze a ON a.idAssenza = o.idAssenza
        WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
          AND oc.classe IN ($inClassi)
          AND o.attivitaProgetto IS NOT NULL
          AND o.attivitaProgetto <> ''
          AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
        GROUP BY
            o.idAssenza,
            o.dataGiorno,
            o.ora,
            o.attivitaProgetto,
            a.dettagli,
            a.oraInizio,
            a.oraFine,
            a.oraInizioReale,
            a.oraFineReale
        ORDER BY o.dataGiorno, o.ora
    ";

    $byKey = [];
    foreach (mb_dbGetAll($q) ?: [] as $r) {
        $date = substr((string)($r['dataGiorno'] ?? ''), 0, 10);
        $slot = googleCalendarDocentiNormOra($r['ora'] ?? '');
        if ($date === '' || $slot === '') continue;

        $idAssenza = intval($r['idAssenza'] ?? 0);
        $title = trim((string)($r['dettagli'] ?? ''));
        if ($title === '') $title = trim((string)($r['attivitaProgetto'] ?? 'Impegno in istituto'));

        $start = $idAssenza > 0
            ? googleCalendarDocentiPickOraInizio($r)
            : $slot;
        $end = $idAssenza > 0
            ? googleCalendarDocentiPickOraFine($r)
            : googleCalendarDocentiSlotEnd($slot);
        if ($end <= $start) $end = googleCalendarDocentiSlotEnd($start);

        $classiRow = googleCalendarDocentiCsv($r['classi'] ?? '');
        $auleRow = googleCalendarDocentiCsv($r['aule'] ?? '');
        sort($classiRow, SORT_NATURAL | SORT_FLAG_CASE);
        sort($auleRow, SORT_NATURAL | SORT_FLAG_CASE);

        $groupSeed = $idAssenza > 0
            ? 'assenza:' . $idAssenza
            : implode('|', [$date, strtoupper($title), implode(',', array_map('strtoupper', $classiRow)), implode(',', array_map('strtoupper', $auleRow))]);

        $key = 'class-impegno:' . hash('sha256', $groupSeed);

        if (!isset($byKey[$key])) {
            $byKey[$key] = [
                'source_key' => $key,
                'source_type' => 'impegno',
                'summary' => 'Classe impegnata - ' . $title,
                'description_badge' => 'Impegno in istituto della classe',
                'date' => $date,
                'start' => $start,
                'end' => $end,
                'classi' => [],
                'aule' => [],
                'docenti' => []
            ];
        }

        $byKey[$key]['classi'] = googleCalendarDocentiMergeUnique($byKey[$key]['classi'], $classiRow);
        $byKey[$key]['aule'] = googleCalendarDocentiMergeUnique($byKey[$key]['aule'], $auleRow);
        $byKey[$key]['docenti'] = googleCalendarDocentiMergeUnique($byKey[$key]['docenti'], googleCalendarDocentiCsv($r['docenti_nomi'] ?? ''));
        if ($start < $byKey[$key]['start']) $byKey[$key]['start'] = $start;
        if ($end > $byKey[$key]['end']) $byKey[$key]['end'] = $end;
    }

    $out = [];
    foreach ($byKey as $event) {
        $classPrefix = !empty($event['classi']) ? (implode(', ', $event['classi']) . ' - ') : '';
        $out[] = [
            'source_key' => $event['source_key'],
            'source_type' => $event['source_type'],
            'summary' => $classPrefix . $event['summary'],
            'description' => googleCalendarDocentiDescription($event['description_badge'], $event['classi'], $event['aule'], implode(', ', $event['docenti'])),
            'location' => implode(', ', $event['aule']),
            'date' => $event['date'],
            'start' => $event['start'],
            'end' => $event['end'],
            'classi' => $event['classi'],
            'aule' => $event['aule']
        ];
    }

    return $out;
}

function googleCalendarDocentiClassesForTeacher($username, $from, $to)
{
    $u = googleCalendarDocentiMbEsc($username);
    $fromEsc = googleCalendarDocentiMbEsc($from);
    $toEsc = googleCalendarDocentiMbEsc($to);

    $rows = mb_dbGetAll("
        SELECT DISTINCT oc.classe
        FROM oralezione o
        JOIN utilizza ut ON ut.idCalendario = o.idCalendario AND ut.username = '$u'
        JOIN occupa oc ON oc.idCalendario = o.idCalendario
        WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
          AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
          AND oc.classe IS NOT NULL
          AND oc.classe <> ''
        ORDER BY oc.classe
    ") ?: [];

    $out = [];
    foreach ($rows as $r) {
        $classe = trim((string)($r['classe'] ?? ''));
        if ($classe !== '') $out[] = $classe;
    }
    return array_values(array_unique($out));
}

function googleCalendarDocentiBuildOutingsFromAssenze($query, $from, $to)
{
    $out = [];
    foreach (mb_dbGetAll($query) ?: [] as $r) {
        $motivo = trim((string)($r['motivo'] ?? ''));
        $dettagli = trim((string)($r['dettagli'] ?? ''));
        $isUscita = googleCalendarDocentiIsUscita($motivo, $dettagli);
        $isViaggio = googleCalendarDocentiIsViaggio($motivo, $dettagli);
        if (!$isUscita && !$isViaggio) {
            continue;
        }

        $idAssenza = intval($r['idAssenza'] ?? 0);
        if ($idAssenza <= 0) continue;

        $dataFrom = substr((string)($r['dataInizio'] ?? ''), 0, 10);
        $dataTo = substr((string)($r['dataFine'] ?? ''), 0, 10);
        if ($dataFrom === '') continue;
        if ($dataTo === '') $dataTo = $dataFrom;

        $start = googleCalendarDocentiPickOraInizio($r);
        $end = googleCalendarDocentiPickOraFine($r);
        if ($end <= $start) {
            $end = googleCalendarDocentiSlotEnd($start);
        }

        $classi = googleCalendarDocentiClassiByAssenza($idAssenza);
        $docenti = googleCalendarDocentiDocentiByAssenza($idAssenza);
        $base = $isViaggio ? 'Viaggio di istruzione' : (googleCalendarDocentiIsUscitaFuori($motivo, $dettagli) ? 'Uscita fuori comune' : 'Uscita didattica');
        $summary = $base . ($dettagli !== '' ? ' - ' . $dettagli : '');

        $d1 = new DateTime($dataFrom);
        $d2 = new DateTime($dataTo);
        if ($d2 < $d1) $d2 = clone $d1;

        for ($d = clone $d1; $d <= $d2; $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            if ($date < $from || $date > $to) continue;

            $out[] = [
                'source_key' => 'uscita:' . $idAssenza . ':' . $date,
                'source_type' => 'uscita',
                'summary' => $summary,
                'description' => googleCalendarDocentiDescription($base, $classi, [], implode(', ', $docenti)),
                'location' => '',
                'date' => $date,
                'start' => $start,
                'end' => $end,
                'classi' => $classi,
                'aule' => []
            ];
        }
    }

    return $out;
}

function googleCalendarDocentiClassiByAssenza($idAssenza)
{
    $id = intval($idAssenza);
    if ($id <= 0) return [];
    $out = [];

    $rows = mb_dbGetAll("
        SELECT DISTINCT classe
        FROM occupa
        WHERE IDassenza = $id
          AND classe IS NOT NULL
          AND classe <> ''
        ORDER BY classe
    ") ?: [];
    foreach ($rows as $r) {
        $classe = trim((string)($r['classe'] ?? ''));
        if ($classe !== '') $out[] = $classe;
    }

    $rows = mb_dbGetAll("
        SELECT DISTINCT oc.classe
        FROM oralezione o
        JOIN occupa oc ON oc.idCalendario = o.idCalendario
        WHERE o.idAssenza = $id
          AND oc.classe IS NOT NULL
          AND oc.classe <> ''
        ORDER BY oc.classe
    ") ?: [];
    foreach ($rows as $r) {
        $classe = trim((string)($r['classe'] ?? ''));
        if ($classe !== '') $out[] = $classe;
    }

    return googleCalendarDocentiMergeUnique([], $out);
}

function googleCalendarDocentiDocentiByAssenza($idAssenza)
{
    $id = intval($idAssenza);
    if ($id <= 0) return [];
    $rows = mb_dbGetAll("
        SELECT DISTINCT CONCAT(u.cognome,' ',u.nome) AS nome
        FROM utilizza ut
        JOIN utente u ON u.username = ut.username
        WHERE ut.IDassenza = $id
          AND ut.username IS NOT NULL
          AND ut.username <> ''
        ORDER BY u.cognome, u.nome
    ") ?: [];
    $out = [];
    foreach ($rows as $r) {
        $nome = trim((string)($r['nome'] ?? ''));
        if ($nome !== '') $out[] = $nome;
    }
    return $out;
}

function googleCalendarDocentiFetchSubstitutions($username, $from, $to)
{
    $u = googleCalendarDocentiLocalEsc($username);
    $fromEsc = googleCalendarDocentiLocalEsc($from);
    $toEsc = googleCalendarDocentiLocalEsc($to);
    $hasStato = googleCalendarDocentiTableHasColumn('sostituzioni', 'stato');
    $whereStato = $hasStato ? "AND (s.stato IS NULL OR UPPER(TRIM(s.stato)) <> 'ANNULLATA')" : "";

    $q = "
        SELECT
            s.idSostituzione,
            s.data,
            s.oraInizio,
            s.oraFine,
            s.materia,
            s.classe,
            s.aula,
            ds.cognome AS cognomeSostituto,
            ds.nome AS nomeSostituto,
            dd.cognome AS cognomeSostituito,
            dd.nome AS nomeSostituito
        FROM sostituzioni s
        LEFT JOIN docente ds ON ds.id = s.idDocenteSostituto
        LEFT JOIN docente dd ON dd.id = s.idDocenteSostituito
        WHERE ds.username = '$u'
          AND s.data BETWEEN '$fromEsc' AND '$toEsc'
          $whereStato
        ORDER BY s.data, s.oraInizio, s.classe, s.aula
    ";

    $out = [];
    foreach (dbGetAll($q) ?: [] as $r) {
        $date = substr((string)($r['data'] ?? ''), 0, 10);
        $start = googleCalendarDocentiNormOra($r['oraInizio'] ?? '');
        $end = googleCalendarDocentiNormOra($r['oraFine'] ?? '');
        if ($date === '' || $start === '') continue;
        if ($end === '') $end = googleCalendarDocentiSlotEnd($start);

        $materia = trim((string)($r['materia'] ?? ''));
        $classe = strtoupper(trim((string)($r['classe'] ?? '')));
        $aula = trim((string)($r['aula'] ?? ''));
        $sostituito = trim((string)($r['cognomeSostituito'] ?? '') . ' ' . (string)($r['nomeSostituito'] ?? ''));

        $out[] = [
            'source_key' => 'sostituzione:' . intval($r['idSostituzione'] ?? 0),
            'source_type' => 'sostituzione',
            'summary' => 'Sostituzione' . ($materia !== '' ? ' - ' . $materia : ''),
            'description' => googleCalendarDocentiDescription('Sostituzione' . ($sostituito !== '' ? ' al posto di ' . $sostituito : ''), $classe !== '' ? [$classe] : [], $aula !== '' ? [$aula] : [], ''),
            'location' => $aula,
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'classi' => $classe !== '' ? [$classe] : [],
            'aule' => $aula !== '' ? [$aula] : []
        ];
    }

    return $out;
}

function googleCalendarDocentiTableHasColumn($table, $column)
{
    $tableEsc = googleCalendarDocentiLocalEsc($table);
    $columnEsc = googleCalendarDocentiLocalEsc($column);
    return dbGetValue("SHOW COLUMNS FROM `$tableEsc` LIKE '$columnEsc'") !== null;
}

function googleCalendarDocentiDescription($tipo, array $classi, array $aule, $docenti, array $notes = [])
{
    $lines = [];
    if (trim($tipo) !== '') $lines[] = $tipo;
    foreach ($notes as $note) {
        $note = trim((string)$note);
        if ($note !== '') $lines[] = 'ATTENZIONE: ' . $note;
    }
    if (!empty($classi)) $lines[] = 'Classe/i: ' . implode(', ', $classi);
    if (!empty($aule)) $lines[] = 'Aula/e: ' . implode(', ', $aule);
    if (trim((string)$docenti) !== '') $lines[] = 'Docente/i: ' . trim((string)$docenti);
    $lines[] = '';
    $lines[] = 'Evento sincronizzato automaticamente da GestOre.';
    return implode("\n", $lines);
}

function googleCalendarDocentiEventId($username, $sourceKey)
{
    return 'g' . substr(hash('sha256', strtolower($username) . '|' . $sourceKey), 0, 48);
}

function googleCalendarDocentiBuildGoogleEvent(array $activity)
{
    $cfg = googleCalendarDocentiConfig();
    $tz = trim((string)($cfg->timeZone ?? 'Europe/Rome')) ?: 'Europe/Rome';

    $event = [
        'summary' => googleCalendarDocentiSummaryForGoogleEvent($activity),
        'description' => (string)$activity['description'],
        'location' => (string)($activity['location'] ?? ''),
        'start' => [
            'dateTime' => googleCalendarDocentiDateTime($activity['date'], $activity['start']),
            'timeZone' => $tz
        ],
        'end' => [
            'dateTime' => googleCalendarDocentiDateTime($activity['date'], $activity['end']),
            'timeZone' => $tz
        ],
        'extendedProperties' => [
            'private' => [
                'gestore_source' => 'docenti',
                'gestore_source_key' => (string)$activity['source_key']
            ]
        ],
        'reminders' => [
            'useDefault' => false,
            'overrides' => [
                [
                    'method' => 'popup',
                    'minutes' => 30
                ]
            ]
        ]
    ];

    $colorId = googleCalendarDocentiColorId((string)($activity['source_type'] ?? ''));
    if ($colorId !== '') {
        $event['colorId'] = $colorId;
    }

    return $event;
}

function googleCalendarDocentiColorId($sourceType)
{
    $cfg = googleCalendarDocentiConfig();
    $type = strtolower(trim((string)$sourceType));

    $defaults = [
        'lezione' => '',
        'oralezione' => '',
        'impegno' => '10',
        'uscita' => '11',
        'sostituzione' => '6',
        'assenza' => '4',
        'lezione_sostituita' => '5',
        'lezione_codocente_assente' => '5',
        'lezione_classe_impegnata' => '7'
    ];

    $configured = [];
    if (isset($cfg->eventColors) && is_object($cfg->eventColors)) {
        $configured = (array)$cfg->eventColors;
    }

    $value = trim((string)($configured[$type] ?? $defaults[$type] ?? ''));
    if ($value === '') return '';

    return preg_match('/^\d+$/', $value) ? $value : '';
}

function googleCalendarDocentiSummaryForGoogleEvent(array $activity)
{
    $summary = (string)($activity['summary'] ?? '');
    $type = strtolower(trim((string)($activity['source_type'] ?? '')));
    $classi = isset($activity['classi']) && is_array($activity['classi']) ? $activity['classi'] : [];

    if (in_array($type, ['lezione', 'uscita', 'impegno'], true)) {
        return googleCalendarDocentiPrefixTitleWithClasses($summary, $classi);
    }

    return $summary;
}

function googleCalendarDocentiSyncTeacher(array $teacher, $from, $to)
{
    googleCalendarDocentiEnsureTables();

    $cfg = googleCalendarDocentiConfig();
    $username = (string)$teacher['username'];
    $userEmail = (string)$teacher['email'];
    $calendarId = googleCalendarDocentiCalendarId($userEmail);
    if ($calendarId === '') {
        throw new Exception('Calendar ID vuoto per ' . $userEmail);
    }

    $sendUpdates = trim((string)($cfg->sendUpdates ?? 'none')) ?: 'none';
    $activities = googleCalendarDocentiFetchActivities($username, $from, $to);
    $seen = [];
    $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0, 'errors' => 0];

    foreach ($activities as $activity) {
        $sourceKey = (string)$activity['source_key'];
        $seen[$sourceKey] = true;
        $eventId = googleCalendarDocentiEventId($username, $sourceKey);
        $event = googleCalendarDocentiBuildGoogleEvent($activity);
        $checksum = hash('sha256', json_encode($event, JSON_UNESCAPED_UNICODE));

        $existing = dbGetFirst("
            SELECT *
            FROM google_calendar_docenti_sync
            WHERE username = " . dbQ($username) . "
              AND source_key = " . dbQ($sourceKey) . "
            LIMIT 1
        ");

        if ($existing && (string)$existing['checksum'] === $checksum && (string)$existing['stato'] === 'SYNC') {
            dbExec("
                UPDATE google_calendar_docenti_sync
                SET last_sync = NOW()
                WHERE id = " . intval($existing['id']) . "
            ");
            $stats['unchanged']++;
            continue;
        }

        googleCalendarDocentiUpsertGoogleEvent($userEmail, $calendarId, $eventId, $event, $sendUpdates, $existing ? true : false);

        googleCalendarDocentiUpsertSyncRow($username, $userEmail, $calendarId, $eventId, $activity, $checksum);
        if ($existing) $stats['updated']++;
        else $stats['created']++;
    }

    $existingRows = dbGetAll("
        SELECT *
        FROM google_calendar_docenti_sync
        WHERE username = " . dbQ($username) . "
          AND data_inizio >= " . dbQ($from . ' 00:00:00') . "
          AND data_inizio <= " . dbQ($to . ' 23:59:59') . "
          AND stato = 'SYNC'
    ") ?: [];

    foreach ($existingRows as $row) {
        $sourceKey = (string)($row['source_key'] ?? '');
        if ($sourceKey === '' || isset($seen[$sourceKey])) continue;

        try {
            $url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode((string)$row['calendar_id']) .
                '/events/' . rawurlencode((string)$row['google_event_id']);
            googleCalendarDocentiRequest($userEmail, 'DELETE', $url, null, ['sendUpdates' => $sendUpdates]);
        } catch (Throwable $e) {
            warningGoogleCalendar('Delete evento docente fallito: ' . $e->getMessage());
        }

        dbExec("
            UPDATE google_calendar_docenti_sync
            SET stato = 'ANNULLATO',
                last_sync = NOW()
            WHERE id = " . intval($row['id']) . "
        ");
        $stats['deleted']++;
    }

    $orphanDeleted = googleCalendarDocentiDeleteCalendarOrphans(
        $userEmail,
        $calendarId,
        $from,
        $to,
        $seen,
        $sendUpdates
    );
    $stats['deleted'] += $orphanDeleted;

    return [
        'username' => $username,
        'email' => $userEmail,
        'calendarId' => $calendarId,
        'activities' => count($activities),
        'stats' => $stats
    ];
}

function googleCalendarDocentiDeleteCalendarOrphans($userEmail, $calendarId, $from, $to, array $seen, $sendUpdates)
{
    $deleted = 0;
    $pageToken = null;
    $timeMin = $from . 'T00:00:00+01:00';
    $timeMax = date('Y-m-d', strtotime($to . ' +1 day')) . 'T00:00:00+01:00';

    do {
        $params = [
            'singleEvents' => 'true',
            'showDeleted' => 'false',
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'maxResults' => 2500
        ];
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $res = googleCalendarDocentiRequest(
            $userEmail,
            'GET',
            'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendarId) . '/events',
            null,
            $params
        );

        foreach (($res['items'] ?? []) as $event) {
            $private = $event['extendedProperties']['private'] ?? [];
            if (($private['gestore_source'] ?? '') !== 'docenti') continue;

            $sourceKey = (string)($private['gestore_source_key'] ?? '');
            $eventId = (string)($event['id'] ?? '');
            if ($sourceKey === '' || $eventId === '') continue;
            if (isset($seen[$sourceKey])) continue;

            try {
                googleCalendarDocentiRequest(
                    $userEmail,
                    'DELETE',
                    'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId),
                    null,
                    ['sendUpdates' => $sendUpdates]
                );
                $deleted++;
            } catch (Throwable $e) {
                warningGoogleCalendar('Delete evento orfano docente fallito: ' . $e->getMessage());
            }
        }

        $pageToken = $res['nextPageToken'] ?? null;
    } while ($pageToken);

    return $deleted;
}

function googleCalendarDocentiUpsertSyncRow($username, $userEmail, $calendarId, $eventId, array $activity, $checksum)
{
    $start = googleCalendarDocentiDateTime($activity['date'], $activity['start']);
    $end = googleCalendarDocentiDateTime($activity['date'], $activity['end']);
    $startDb = str_replace('T', ' ', $start);
    $endDb = str_replace('T', ' ', $end);

    dbExec("
        INSERT INTO google_calendar_docenti_sync
            (username, user_email, calendar_id, google_event_id, source_key, source_hash, source_type, checksum, data_inizio, data_fine, stato, last_sync)
        VALUES
            (" . dbQ($username) . ",
             " . dbQ($userEmail) . ",
             " . dbQ($calendarId) . ",
             " . dbQ($eventId) . ",
             " . dbQ($activity['source_key']) . ",
             " . dbQ(hash('sha256', (string)$activity['source_key'])) . ",
             " . dbQ($activity['source_type']) . ",
             " . dbQ($checksum) . ",
             " . dbQ($startDb) . ",
             " . dbQ($endDb) . ",
             'SYNC',
             NOW())
        ON DUPLICATE KEY UPDATE
            user_email = VALUES(user_email),
            calendar_id = VALUES(calendar_id),
            google_event_id = VALUES(google_event_id),
            source_type = VALUES(source_type),
            checksum = VALUES(checksum),
            data_inizio = VALUES(data_inizio),
            data_fine = VALUES(data_fine),
            stato = 'SYNC',
            last_sync = NOW()
    ");
}

function googleCalendarDocentiUpsertGoogleEvent($userEmail, $calendarId, $eventId, array $event, $sendUpdates, $preferUpdate)
{
    $calendarBase = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendarId);
    $eventUrl = $calendarBase . '/events/' . rawurlencode($eventId);
    $listUrl = $calendarBase . '/events';
    $query = ['sendUpdates' => $sendUpdates];

    if ($preferUpdate) {
        try {
            return googleCalendarDocentiRequest($userEmail, 'PUT', $eventUrl, $event, $query);
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'HTTP 404') === false) {
                throw $e;
            }
        }
    }

    $eventWithId = $event;
    $eventWithId['id'] = $eventId;

    try {
        return googleCalendarDocentiRequest($userEmail, 'POST', $listUrl, $eventWithId, $query);
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), 'HTTP 409') === false) {
            throw $e;
        }
    }

    return googleCalendarDocentiRequest($userEmail, 'PUT', $eventUrl, $event, $query);
}

function googleCalendarDocentiSync($username, $from, $to)
{
    $teachers = googleCalendarDocentiGetTeachers($username);
    $results = [];

    foreach ($teachers as $teacher) {
        try {
            $results[] = googleCalendarDocentiSyncTeacher($teacher, $from, $to);
        } catch (Throwable $e) {
            errorGoogleCalendar('Sync docente fallito per ' . ($teacher['username'] ?? '?') . ': ' . $e->getMessage());
            $results[] = [
                'username' => $teacher['username'] ?? '',
                'ok' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    return $results;
}

function googleCalendarDocentiSyncUsernames(array $usernames, $from, $to, $onlyEnabled = false)
{
    $seen = [];
    $results = [];
    $enabledSet = [];
    if ($onlyEnabled) {
        foreach (googleCalendarDocentiEnabledTeacherUsernames() as $enabledUsername) {
            $enabledSet[strtolower(trim((string)$enabledUsername))] = true;
        }
    }

    foreach ($usernames as $username) {
        $username = trim((string)$username);
        if ($username === '' || isset($seen[strtolower($username)])) continue;
        $seen[strtolower($username)] = true;

        if ($onlyEnabled && !isset($enabledSet[strtolower($username)])) {
            $results[] = [
                'username' => $username,
                'ok' => true,
                'skipped' => true,
                'reason' => 'Sync Google Calendar non abilitato dal docente'
            ];
            continue;
        }

        $teachers = googleCalendarDocentiGetTeachers($username);
        if (empty($teachers)) {
            $results[] = [
                'username' => $username,
                'ok' => false,
                'error' => 'Docente non trovato o non attivo'
            ];
            continue;
        }

        try {
            $results[] = googleCalendarDocentiSyncTeacher($teachers[0], $from, $to);
        } catch (Throwable $e) {
            errorGoogleCalendar('Sync mirato docente fallito per ' . $username . ': ' . $e->getMessage());
            $results[] = [
                'username' => $username,
                'ok' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    return $results;
}

function googleCalendarDocentiSyncEnabledTeachers($from, $to, $syncKind = 'cron')
{
    $usernames = googleCalendarDocentiEnabledTeacherUsernames();
    if (empty($usernames)) {
        return [];
    }

    $results = googleCalendarDocentiSyncUsernames($usernames, $from, $to);
    foreach ($results as $result) {
        $username = trim((string)($result['username'] ?? ''));
        if ($username === '') {
            continue;
        }

        $values = [
            'last_sync_from' => $from,
            'last_sync_to' => $to,
            'last_error' => empty($result['error']) ? null : (string)$result['error'],
        ];
        if ($syncKind === 'manual') {
            $values['last_manual_sync_at'] = date('Y-m-d H:i:s');
        } else {
            $values['last_cron_sync_at'] = date('Y-m-d H:i:s');
        }
        googleCalendarDocentiUpsertPreference($username, $values);
    }

    return $results;
}

function googleCalendarDocentiSyncTeacherIds(array $ids, $from, $to, $onlyEnabled = false)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
        return $id > 0;
    })));
    if (empty($ids)) return [];

    $rows = dbGetAll("
        SELECT username
        FROM docente
        WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")
          AND attivo = true
          AND username IS NOT NULL
          AND username <> ''
    ") ?: [];

    $usernames = [];
    foreach ($rows as $row) {
        $usernames[] = (string)($row['username'] ?? '');
    }

    return googleCalendarDocentiSyncUsernames($usernames, $from, $to, $onlyEnabled);
}

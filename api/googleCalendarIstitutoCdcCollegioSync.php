<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/connectMBApp.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/googleCalendarLib.php';
require_once __DIR__ . '/googleCalendarDocentiLib.php';

setLogChannel('google_calendar_mbapp');

$isCli = (PHP_SAPI === 'cli');
if (!$isCli && !defined('GESTORE_CDC_COLLEGIO_SYNC_LIBRARY')) {
    header('Content-Type: application/json; charset=utf-8');
}

function cdcParam($name, $default = '')
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        foreach (($argv ?? []) as $arg) {
            if (strpos($arg, '--' . $name . '=') === 0) {
                return substr($arg, strlen($name) + 3);
            }
        }
        return $default;
    }
    return isset($_REQUEST[$name]) ? trim((string)$_REQUEST[$name]) : $default;
}

function cdcIsIsoDate($v)
{
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v);
}

function cdcMbEsc($v)
{
    global $__conMBApp;
    return mysqli_real_escape_string($__conMBApp, (string)$v);
}

function cdcAnnoScolasticoCorrenteId()
{
    global $__anno_scolastico_corrente_id;

    if (isset($__anno_scolastico_corrente_id) && intval($__anno_scolastico_corrente_id) > 0) {
        return intval($__anno_scolastico_corrente_id);
    }

    return intval(dbGetValue('SELECT anno_scolastico_id FROM anno_scolastico_corrente LIMIT 1'));
}

function cdcExpandClasseToken($token)
{
    $token = strtoupper(trim((string)$token));
    $token = preg_replace('/\s+/', '', $token);
    if ($token === '') {
        return [];
    }

    $parts = array_values(array_filter(explode('-', $token), 'strlen'));
    if (empty($parts)) {
        return [];
    }

    $defaultYear = '';
    $defaultSuffix = '';

    foreach ($parts as $part) {
        if (preg_match('/^([0-9])([A-Z]{1,4})$/u', $part, $m)) {
            if ($defaultYear === '') {
                $defaultYear = $m[1];
            }
            if ($defaultSuffix === '') {
                $defaultSuffix = $m[2];
            }
        }
    }

    $out = [];
    foreach ($parts as $part) {
        $classe = '';

        if (preg_match('/^([0-9])([A-Z]{1,4})$/u', $part)) {
            $classe = $part;
        } elseif (preg_match('/^[A-Z]{1,4}$/u', $part) && $defaultYear !== '') {
            $classe = $defaultYear . $part;
        } elseif (preg_match('/^[0-9]$/u', $part) && $defaultSuffix !== '') {
            $classe = $part . $defaultSuffix;
        }

        if ($classe !== '' && !in_array($classe, $out, true)) {
            $out[] = $classe;
        }
    }

    return $out;
}

function cdcExtractClassiCdc($dettagli)
{
    $det = strtoupper(trim((string)$dettagli));
    $det = preg_replace('/\s*-\s*/', '-', $det);
    $token = '';

    if (preg_match('/\bCC\s+([0-9A-Z]+(?:-[0-9A-Z]+)*)\b/u', $det, $m)) {
        $token = strtoupper(trim($m[1]));
    } elseif (preg_match('/\b([0-9][A-Z]{1,4}(?:-[0-9A-Z]+)*)\b/u', $det, $m)) {
        $token = strtoupper(trim($m[1]));
    }

    $classi = cdcExpandClasseToken($token);

    return [
        'label' => $token,
        'classi' => $classi
    ];
}

function cdcExtractClasse($dettagli)
{
    $data = cdcExtractClassiCdc($dettagli);
    return (string)($data['label'] ?? '');
}

function cdcMergeAttendees(array $a, array $b)
{
    $out = [];
    $seen = [];

    foreach (array_merge($a, $b) as $attendee) {
        $email = strtolower(trim((string)($attendee['email'] ?? '')));
        if ($email === '') continue;
        if (isset($seen[$email])) continue;

        $seen[$email] = true;
        $out[] = $attendee;
    }

    return $out;
}

function cdcNormalizeDateTime($date, $time)
{
    $date = trim((string)$date);
    $time = trim((string)$time);

    if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        $time .= ':00';
    }

    $dt = new DateTime($date . ' ' . $time, new DateTimeZone('Europe/Rome'));
    return $dt->format('c');
}

function cdcDateTimeForDb($dateTime)
{
    $dt = new DateTime($dateTime);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format('Y-m-d H:i:s');
}

function cdcGoogleEventsListUrl($calendarId, array $params)
{
    $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
        rawurlencode((string)$calendarId) .
        '/events';

    $query = [];
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $item) {
                $query[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$item);
            }
        } else {
            $query[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }
    }

    return $url . (empty($query) ? '' : '?' . implode('&', $query));
}

function cdcFindExistingGoogleEvent($calendarId, $idAssenza, array $event)
{
    $start = (string)($event['start']['dateTime'] ?? '');
    if ($start === '') {
        return null;
    }

    $day = new DateTime($start);
    $day->setTimezone(new DateTimeZone('Europe/Rome'));
    $startDay = clone $day;
    $startDay->setTime(0, 0, 0);
    $endDay = clone $day;
    $endDay->setTime(23, 59, 59);
    $timeMin = $startDay->format('c');
    $timeMax = $endDay->format('c');

    $url = cdcGoogleEventsListUrl($calendarId, [
        'singleEvents' => 'true',
        'showDeleted' => 'false',
        'timeMin' => $timeMin,
        'timeMax' => $timeMax,
        'maxResults' => 10,
        'privateExtendedProperty' => [
            'source=GESTORE_CDC_COLLEGIO',
            'idAssenza=' . intval($idAssenza)
        ]
    ]);

    $response = googleCalendarApiRequest('GET', $url);
    foreach (($response['items'] ?? []) as $item) {
        if (($item['status'] ?? '') === 'cancelled') {
            continue;
        }
        if (trim((string)($item['id'] ?? '')) !== '') {
            return $item;
        }
    }

    return null;
}

function cdcCalendarioIstituto()
{
    global $__settings;

    $preferredConfigId = intval($__settings->local->googleCalendar->calendarIstitutoConfigId ?? 0);
    if ($preferredConfigId > 0) {
        $row = dbGetFirst("
            SELECT *
            FROM google_calendar_config
            WHERE id = " . dbI($preferredConfigId) . "
              AND attivo = 1
              AND tipo = 'ISTITUTO'
            LIMIT 1
        ");

        if ($row) {
            return $row;
        }
    }

    $row = dbGetFirst("
        SELECT c.*
        FROM google_calendar_config c
        LEFT JOIN google_calendar_event_sync s
            ON s.google_calendar_config_id = c.id
           AND s.stato <> 'ANNULLATO'
        WHERE c.attivo = 1
          AND c.tipo = 'ISTITUTO'
        GROUP BY c.id
        ORDER BY COUNT(s.id) DESC, c.id ASC
        LIMIT 1
    ");

    if (!$row) {
        throw new Exception('Nessun calendario ISTITUTO attivo trovato in google_calendar_config');
    }

    return $row;
}

function cdcAuleByAssenza($idAssenza)
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

function cdcMaybeAddDirigenteAttendee(array $attendees)
{
    global $__settings;

    $cfg = $__settings->local->googleCalendarDocenti ?? null;
    if (!$cfg) return $attendees;

    $enabled = !empty($cfg->aggiungiDirigenteAEventiOrganiCollegiali);
    if (!$enabled) return $attendees;

    $email = strtolower(trim((string)($cfg->emailDirigenteEventiOrganiCollegiali ?? '')));
    if ($email === '' || strpos($email, '@') === false) return $attendees;

    foreach ($attendees as $a) {
        if (strtolower(trim((string)($a['email'] ?? ''))) === $email) {
            return $attendees;
        }
    }

    $attendees[] = [
        'email' => $email,
        'displayName' => 'Dirigente scolastico'
    ];

    return $attendees;
}

function cdcDocentiRowsToAttendees($rows)
{
    $out = [];
    $seen = [];

    foreach ($rows as $r) {
        $email = googleCalendarDocentiTeacherEmail($r);
        $email = strtolower(trim((string)$email));

        if ($email === '' || strpos($email, '@') === false) continue;
        if (isset($seen[$email])) continue;

        $seen[$email] = true;

        $displayName = trim((string)($r['nome'] ?? '') . ' ' . (string)($r['cognome'] ?? ''));

        $out[] = [
            'email' => $email,
            'displayName' => $displayName
        ];
    }

    return $out;
}

function cdcDocentiByClasse($classe)
{
    $classeEsc = dbEscape($classe);
    $idAnnoScolastico = cdcAnnoScolasticoCorrenteId();

    if ($idAnnoScolastico <= 0) {
        throw new Exception('Anno scolastico corrente non determinato');
    }

    $rows = dbGetAll("
        SELECT DISTINCT d.username, d.email, d.cognome, d.nome
        FROM docente_insegna di
        JOIN docente d ON d.id = di.id_docente
        JOIN classi c ON c.id = di.id_classe
        WHERE UPPER(TRIM(c.classe)) = UPPER(TRIM('$classeEsc'))
          AND d.attivo = TRUE
          AND c.attiva = 1
          AND d.username IS NOT NULL
          AND d.username <> ''
          AND di.id_anno_scolastico = " . dbI($idAnnoScolastico) . "
        ORDER BY d.cognome, d.nome
    ") ?: [];

    return cdcDocentiRowsToAttendees($rows);
}

function cdcDocentiByClassi(array $classi)
{
    $attendees = [];

    foreach ($classi as $classe) {
        $attendees = cdcMergeAttendees($attendees, cdcDocentiByClasse($classe));
    }

    return $attendees;
}

function cdcTuttiDocenti()
{
    $rows = dbGetAll("
        SELECT DISTINCT username, email, cognome, nome
        FROM docente
        WHERE attivo = TRUE
          AND username IS NOT NULL
          AND username <> ''
        ORDER BY cognome, nome
    ") ?: [];

    return cdcDocentiRowsToAttendees($rows);
}

function cdcFetchAssenze($from, $to)
{
    $fromEsc = cdcMbEsc($from);
    $toEsc = cdcMbEsc($to);

    return mb_dbGetAll("
        SELECT a.*
        FROM assenze a
        WHERE (
            UPPER(TRIM(COALESCE(a.motivo, ''))) = 'CONSIGLIO DI CLASSE'
            OR (
                UPPER(TRIM(COALESCE(a.motivo, ''))) = 'IMPEGNO IN ISTITUTO'
                AND UPPER(TRIM(COALESCE(a.dettagli, ''))) LIKE '%COLLEGIO DOCENTI%'
            )
        )
          AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
          AND DATE(a.dataInizio) <= '$toEsc'
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
        ORDER BY a.dataInizio, a.oraInizio, a.idAssenza
    ") ?: [];
}

function cdcBuildGoogleEvent($a)
{
    $idAssenza = intval($a['idAssenza'] ?? 0);
    if ($idAssenza <= 0) return null;

    $motivo = strtoupper(trim((string)($a['motivo'] ?? '')));
    $dettagli = trim((string)($a['dettagli'] ?? ''));

    $isCdc = ($motivo === 'CONSIGLIO DI CLASSE');
    $isCollegio = (!$isCdc && stripos($dettagli, 'Collegio Docenti') !== false);

    if (!$isCdc && !$isCollegio) return null;

    $date = substr((string)($a['dataInizio'] ?? ''), 0, 10);
    if ($date === '') return null;

    $startOra = googleCalendarDocentiPickOraInizio($a);
    $endOra = googleCalendarDocentiPickOraFine($a);
    if ($endOra <= $startOra) {
        $endOra = googleCalendarDocentiSlotEnd($startOra);
    }

    $aule = cdcAuleByAssenza($idAssenza);

    if ($isCdc) {
        $classiCdc = cdcExtractClassiCdc($dettagli);
        $classe = (string)($classiCdc['label'] ?? '');
        $classi = $classiCdc['classi'] ?? [];
        if ($classe === '' || empty($classi)) return null;

        $summary = 'Consiglio di classe ' . $classe;
        $attendees = cdcMaybeAddDirigenteAttendee(cdcDocentiByClassi($classi));
    } else {
        $summary = 'Collegio Docenti';
        $classi = [];
        $attendees = cdcMaybeAddDirigenteAttendee(cdcTuttiDocenti());

        if (empty($aule)) {
            $aule = ['221'];
        }
    }

    $descrizione = "Evento sincronizzato da GestOre / MBApp";
    $descrizione .= "\nID assenza MBApp: " . $idAssenza;
    $descrizione .= "\nTipo: " . ($isCdc ? 'Consiglio di classe' : 'Collegio Docenti');

    if (!empty($classi)) {
        $descrizione .= "\nClasse: " . implode(', ', $classi);
    }

    if (!empty($aule)) {
        $descrizione .= "\nAula: " . implode(', ', $aule);
    }

    if ($dettagli !== '') {
        $descrizione .= "\nDettagli: " . $dettagli;
    }

    return [
        'idAssenza' => $idAssenza,
        'summary' => $summary,
        'startDb' => $date . ' ' . $startOra . ':00',
        'endDb' => $date . ' ' . $endOra . ':00',
        'event' => [
            'summary' => $summary,
            'location' => !empty($aule) ? 'Aula ' . implode(', ', $aule) : '',
            'description' => $descrizione,
            'start' => [
                'dateTime' => cdcNormalizeDateTime($date, $startOra),
                'timeZone' => 'Europe/Rome'
            ],
            'end' => [
                'dateTime' => cdcNormalizeDateTime($date, $endOra),
                'timeZone' => 'Europe/Rome'
            ],
            'attendees' => $attendees,
            'guestsCanInviteOthers' => false,
            'guestsCanModify' => false,
            'guestsCanSeeOtherGuests' => true,
            'extendedProperties' => [
                'private' => [
                    'source' => 'GESTORE_CDC_COLLEGIO',
                    'idAssenza' => (string)$idAssenza,
                    'tipo' => $isCdc ? 'CDC' : 'COLLEGIO_DOCENTI'
                ]
            ]
        ],
        'attendees_count' => count($attendees)
    ];
}

function cdcUpsertGoogleEvent($calendarConfig, $item, $dryRun = false, array $options = [])
{
    $configId = intval($calendarConfig['id']);
    $calendarId = (string)$calendarConfig['calendar_id'];
    $idAssenza = intval($item['idAssenza']);
    $event = $item['event'];
    $updateExisting = array_key_exists('update_existing', $options) ? (bool)$options['update_existing'] : true;

    $sync = dbGetFirst("
        SELECT *
        FROM google_calendar_event_sync
        WHERE google_calendar_config_id = $configId
          AND idAssenza = $idAssenza
          AND stato <> 'ANNULLATO'
        LIMIT 1
    ");

    if (!$sync) {
        $sync = dbGetFirst("
            SELECT s.*, c.calendar_id AS sync_calendar_id, c.nome AS sync_calendar_nome
            FROM google_calendar_event_sync s
            INNER JOIN google_calendar_config c ON c.id = s.google_calendar_config_id
            WHERE s.idAssenza = $idAssenza
              AND s.stato <> 'ANNULLATO'
              AND c.attivo = 1
              AND c.tipo = 'ISTITUTO'
            ORDER BY s.updated_at DESC, s.id DESC
            LIMIT 1
        ");
    }

    if ($sync && intval($sync['google_calendar_config_id'] ?? 0) !== $configId) {
        $configId = intval($sync['google_calendar_config_id']);
        $calendarId = (string)($sync['sync_calendar_id'] ?? $calendarId);
        $calendarConfig['id'] = $configId;
        if (!empty($sync['sync_calendar_nome'])) {
            $calendarConfig['nome'] = $sync['sync_calendar_nome'];
        }
    }

    $existingGoogleEvent = null;
    if (!$sync) {
        $existingGoogleEvent = cdcFindExistingGoogleEvent($calendarId, $idAssenza, $event);
    }

    if ($dryRun) {
        $action = 'would_insert';
        $googleEventId = '';
        if ($sync) {
            $action = $updateExisting ? 'would_update' : 'would_skip_existing';
            $googleEventId = (string)($sync['google_event_id'] ?? '');
        } elseif ($existingGoogleEvent) {
            $action = $updateExisting ? 'would_adopt_update' : 'would_skip_existing';
            $googleEventId = (string)($existingGoogleEvent['id'] ?? '');
        }

        return [
            'ok' => true,
            'action' => $action,
            'idAssenza' => $idAssenza,
            'calendar_config_id' => $configId,
            'calendar_nome' => $calendarConfig['nome'] ?? '',
            'google_event_id' => $googleEventId,
            'titolo' => (string)($event['summary'] ?? ''),
            'attendees_count' => $item['attendees_count'],
            'event' => $event
        ];
    }

    if (($sync && trim((string)($sync['google_event_id'] ?? '')) !== '') || $existingGoogleEvent) {
        if (!$updateExisting) {
            return [
                'ok' => true,
                'action' => 'skip_existing',
                'idAssenza' => $idAssenza,
                'calendar_config_id' => $configId,
                'calendar_nome' => $calendarConfig['nome'] ?? '',
                'google_event_id' => $sync ? (string)($sync['google_event_id'] ?? '') : (string)($existingGoogleEvent['id'] ?? ''),
                'titolo' => (string)($event['summary'] ?? ''),
                'attendees_count' => $item['attendees_count'],
                'event' => $event
            ];
        }

        $googleEventId = $sync ? (string)$sync['google_event_id'] : (string)($existingGoogleEvent['id'] ?? '');

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
            rawurlencode($calendarId) .
            '/events/' .
            rawurlencode($googleEventId) .
            '?sendUpdates=none';

        $response = googleCalendarApiRequest('PUT', $url, $event);
        $action = $sync ? 'update' : 'adopt_update';
    } else {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
            rawurlencode($calendarId) .
            '/events?sendUpdates=none';

        $response = googleCalendarApiRequest('POST', $url, $event);

        $googleEventId = (string)($response['id'] ?? '');
        $action = 'insert';
    }

    if ($googleEventId === '') {
        throw new Exception('google_event_id vuoto per idAssenza ' . $idAssenza);
    }

    $titolo = (string)($event['summary'] ?? '');
    $inizio = cdcDateTimeForDb((string)($event['start']['dateTime'] ?? ''));
    $fine = cdcDateTimeForDb((string)($event['end']['dateTime'] ?? ''));

    dbExec("
        INSERT INTO google_calendar_event_sync
            (
                google_calendar_config_id,
                idAssenza,
                idCalendario,
                google_event_id,
                google_ical_uid,
                google_etag,
                titolo,
                inizio,
                fine,
                stato,
                ultimo_errore,
                created_at,
                updated_at
            )
        VALUES
            (
                $configId,
                $idAssenza,
                NULL,
                '" . dbEscape($googleEventId) . "',
                '" . dbEscape((string)($response['iCalUID'] ?? '')) . "',
                '" . dbEscape((string)($response['etag'] ?? '')) . "',
                '" . dbEscape($titolo) . "',
                '" . dbEscape($inizio) . "',
                '" . dbEscape($fine) . "',
                'CONFERMATO',
                NULL,
                NOW(),
                NOW()
            )
        ON DUPLICATE KEY UPDATE
            google_event_id = VALUES(google_event_id),
            google_ical_uid = VALUES(google_ical_uid),
            google_etag = VALUES(google_etag),
            titolo = VALUES(titolo),
            inizio = VALUES(inizio),
            fine = VALUES(fine),
            stato = 'CONFERMATO',
            ultimo_errore = NULL,
            updated_at = NOW()
    ");

    infoGoogleCalendar('Sync CDC/Collegio calendario istituto OK: ' . json_encode([
        'action' => $action,
        'idAssenza' => $idAssenza,
        'calendar_config_id' => $configId,
        'google_event_id' => $googleEventId,
        'attendees_count' => $item['attendees_count']
    ], JSON_UNESCAPED_UNICODE));

    return [
        'ok' => true,
        'action' => $action,
        'idAssenza' => $idAssenza,
        'calendar_config_id' => $configId,
        'calendar_nome' => $calendarConfig['nome'] ?? '',
        'google_event_id' => $googleEventId,
        'titolo' => $titolo,
        'attendees_count' => $item['attendees_count'],
        'event' => $event
    ];
}

function cdcRunSync($from, $to, $dryRun = false, array $options = [])
{
    if (!cdcIsIsoDate($from) || !cdcIsIsoDate($to)) {
        throw new Exception('Date non valide: usare from/to in formato YYYY-MM-DD');
    }

    if ($to < $from) {
        throw new Exception('Intervallo date non valido');
    }

    $calendarConfig = cdcCalendarioIstituto();
    $rows = cdcFetchAssenze($from, $to);
    $onlyIdAssenza = intval($options['idAssenza'] ?? 0);
    if ($onlyIdAssenza > 0) {
        $rows = array_values(array_filter($rows, function ($row) use ($onlyIdAssenza) {
            return intval($row['idAssenza'] ?? 0) === $onlyIdAssenza;
        }));
    }

    $results = [];
    foreach ($rows as $a) {
        $item = cdcBuildGoogleEvent($a);
        if (!$item) continue;
        $results[] = cdcUpsertGoogleEvent($calendarConfig, $item, $dryRun, $options);
    }

    return [
        'ok' => true,
        'from' => $from,
        'to' => $to,
        'dry_run' => $dryRun,
        'update_existing' => array_key_exists('update_existing', $options) ? (bool)$options['update_existing'] : true,
        'idAssenza' => $onlyIdAssenza > 0 ? $onlyIdAssenza : null,
        'id_anno_scolastico' => cdcAnnoScolasticoCorrenteId(),
        'calendar_config_id' => intval($calendarConfig['id']),
        'calendar_nome' => $calendarConfig['nome'] ?? '',
        'calendar_id' => $calendarConfig['calendar_id'] ?? '',
        'found' => count($rows),
        'processed' => count($results),
        'results' => $results
    ];
}

if (!defined('GESTORE_CDC_COLLEGIO_SYNC_LIBRARY')) {
try {
    global $__settings;

    $token = cdcParam('token');
    $expected = (string)($__settings->local->googleCalendar->syncSecret ?? '');

    if (!$isCli && ($expected === '' || !hash_equals($expected, $token))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token non valido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $from = cdcParam('from', date('Y-m-d'));
    $to = cdcParam('to', date('Y-m-d', strtotime('+30 days')));
    $dryRun = in_array(strtolower(cdcParam('dry_run', '0')), ['1', 'true', 'yes'], true);
    $defaultUpdateExisting = !empty($__settings->local->googleCalendar->calendarIstitutoUpdateExisting);
    $updateExisting = !in_array(strtolower(cdcParam('update_existing', $defaultUpdateExisting ? '1' : '0')), ['0', 'false', 'no'], true);
    $idAssenza = intval(cdcParam('idAssenza', '0'));

    echo json_encode(cdcRunSync($from, $to, $dryRun, [
        'update_existing' => $updateExisting,
        'idAssenza' => $idAssenza
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($isCli) echo PHP_EOL;

} catch (Throwable $e) {
    errorGoogleCalendar('Errore sync CDC/Collegio calendario istituto: ' . $e->getMessage());

    if (!$isCli) {
        http_response_code(500);
    }

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($isCli) echo PHP_EOL;
}
}

<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/connectMBApp.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/googleCalendarLib.php';
require_once __DIR__ . '/googleCalendarDocentiLib.php';

setLogChannel('google_calendar_mbapp');

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
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

function cdcExtractClasse($dettagli)
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

function cdcCalendarioIstituto()
{
    $row = dbGetFirst("
        SELECT *
        FROM google_calendar_config
        WHERE attivo = 1
          AND tipo = 'ISTITUTO'
        ORDER BY id ASC
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

    $rows = dbGetAll("
        SELECT DISTINCT d.username, d.email, d.cognome, d.nome
        FROM docente_insegna di
        JOIN docente d ON d.id = di.id_docente
        JOIN classi c ON c.id = di.id_classe
        WHERE UPPER(TRIM(c.classe)) = UPPER(TRIM('$classeEsc'))
          AND d.attivo = TRUE
          AND d.username IS NOT NULL
          AND d.username <> ''
        ORDER BY d.cognome, d.nome
    ") ?: [];

    return cdcDocentiRowsToAttendees($rows);
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
        $classe = cdcExtractClasse($dettagli);
        if ($classe === '') return null;

        $summary = 'Consiglio di classe ' . $classe;
        $classi = [$classe];
        $attendees = cdcMaybeAddDirigenteAttendee(cdcDocentiByClasse($classe));
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

function cdcUpsertGoogleEvent($calendarConfig, $item, $dryRun = false)
{
    $configId = intval($calendarConfig['id']);
    $calendarId = (string)$calendarConfig['calendar_id'];
    $idAssenza = intval($item['idAssenza']);
    $event = $item['event'];

    $sync = dbGetFirst("
        SELECT *
        FROM google_calendar_event_sync
        WHERE google_calendar_config_id = $configId
          AND idAssenza = $idAssenza
          AND stato <> 'ANNULLATO'
        LIMIT 1
    ");

    if ($dryRun) {
        return [
            'ok' => true,
            'action' => $sync ? 'would_update' : 'would_insert',
            'idAssenza' => $idAssenza,
            'calendar_config_id' => $configId,
            'calendar_nome' => $calendarConfig['nome'] ?? '',
            'attendees_count' => $item['attendees_count'],
            'event' => $event
        ];
    }

    if ($sync && trim((string)($sync['google_event_id'] ?? '')) !== '') {
        $googleEventId = (string)$sync['google_event_id'];

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
            rawurlencode($calendarId) .
            '/events/' .
            rawurlencode($googleEventId) .
            '?sendUpdates=none';

        $response = googleCalendarApiRequest('PUT', $url, $event);
        $action = 'update';
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
        'attendees_count' => $item['attendees_count']
    ];
}

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

    if (!cdcIsIsoDate($from) || !cdcIsIsoDate($to)) {
        throw new Exception('Date non valide: usare from/to in formato YYYY-MM-DD');
    }

    if ($to < $from) {
        throw new Exception('Intervallo date non valido');
    }

    $calendarConfig = cdcCalendarioIstituto();
    $rows = cdcFetchAssenze($from, $to);

    $results = [];
    foreach ($rows as $a) {
        $item = cdcBuildGoogleEvent($a);
        if (!$item) continue;
        $results[] = cdcUpsertGoogleEvent($calendarConfig, $item, $dryRun);
    }

    echo json_encode([
        'ok' => true,
        'from' => $from,
        'to' => $to,
        'dry_run' => $dryRun,
        'calendar_config_id' => intval($calendarConfig['id']),
        'calendar_nome' => $calendarConfig['nome'] ?? '',
        'calendar_id' => $calendarConfig['calendar_id'] ?? '',
        'found' => count($rows),
        'processed' => count($results),
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

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
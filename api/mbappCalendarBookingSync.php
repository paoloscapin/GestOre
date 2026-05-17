<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/googleCalendarLib.php';
require_once __DIR__ . '/googleCalendarDocentiLib.php';

setLogChannel('google_calendar_mbapp');

header('Content-Type: application/json; charset=utf-8');

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        throw new Exception('Payload JSON non valido');
    }

    $token = (string)($payload['token'] ?? '');
    $expectedToken = (string)($__settings->local->googleCalendar->syncSecret ?? '');

    if ($expectedToken === '' || $token !== $expectedToken) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'Token non valido'
        ]);
        exit;
    }

    $azione = strtoupper(trim((string)($payload['azione'] ?? 'CONFERMA')));
    $idAssenza = intval($payload['idAssenza'] ?? 0);
    $nroAula = trim((string)($payload['nroAula'] ?? ''));
    $pubblicaIstituto = intval($payload['pubblicaIstituto'] ?? 0) === 1 ? 1 : 0;
infoGoogleCalendar(
    'MBApp endpoint payload ricevuto: ' .
    json_encode([
        'azione' => $azione,
        'idAssenza' => $idAssenza,
        'old_idAssenza' => intval($payload['old_idAssenza'] ?? 0),
        'nroAula' => $nroAula,
        'pubblicaIstituto' => $pubblicaIstituto,
        'motivo' => $payload['motivo'] ?? '',
        'dettagli' => $payload['dettagli'] ?? '',
        'stato' => $payload['stato'] ?? ''
    ], JSON_UNESCAPED_UNICODE)
);
    if ($idAssenza <= 0) {
        throw new Exception('idAssenza mancante');
    }

    if ($azione !== 'CONFERMA' && $azione !== 'UPDATE' && $azione !== 'ANNULLA') {
        throw new Exception('Azione non valida: ' . $azione);
    }

    if ($azione === 'ANNULLA') {
        $result = mbappCalendarBookingDeleteFromGoogle($idAssenza);
        $docentiSync = mbappCalendarBookingSyncDocentiCoinvolti($payload);

        echo json_encode([
            'ok' => true,
            'action' => 'ANNULLA',
            'result' => $result,
            'calendarDocentiSync' => $docentiSync
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

$oldIdAssenza = intval($payload['old_idAssenza'] ?? 0);
$syncIdAssenza = $oldIdAssenza > 0 ? $oldIdAssenza : $idAssenza;

// Se l'evento era già pubblicato sul calendario di Istituto,
// lo mantengo anche se pubblicaIstituto non viene ripassato dal cambio aula.
if ($pubblicaIstituto !== 1 && mbappCalendarBookingHasIstitutoSync($syncIdAssenza)) {
    $pubblicaIstituto = 1;
}

$calendari = mbappCalendarBookingGetTargetCalendars($nroAula, $pubblicaIstituto);

$event = mbappCalendarBookingBuildGoogleEvent($payload);

    $results = mbappCalendarBookingSyncTargets(
        $calendari,
        $idAssenza,
        $event,
        $payload
    );
    $docentiSync = mbappCalendarBookingSyncDocentiCoinvolti($payload);

    echo json_encode([
        'ok' => true,
        'action' => $azione,
        'idAssenza' => $idAssenza,
        'calendari_target' => count($calendari),
        'results' => $results,
        'calendarDocentiSync' => $docentiSync
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    errorGoogleCalendar(
        'Errore mbappCalendarBookingSync: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// ============================================================================


function mbappCalendarBookingHasIstitutoSync(int $idAssenza): bool
{
    if ($idAssenza <= 0) {
        return false;
    }

    $row = dbGetFirst("
        SELECT s.id
        FROM google_calendar_event_sync s
        INNER JOIN google_calendar_config c
            ON c.id = s.google_calendar_config_id
        WHERE s.idAssenza = " . intval($idAssenza) . "
          AND s.stato <> 'ANNULLATO'
          AND c.tipo = 'ISTITUTO'
        LIMIT 1
    ");

    return $row != null;
}

function mbappCalendarBookingGetTargetCalendars(string $nroAula, int $pubblicaIstituto): array
{
    $conditions = [];

    if ($nroAula !== '') {
        $conditions[] = "(tipo = 'AULA' AND nroAula = '" . dbEscape($nroAula) . "')";
    }

    if ($pubblicaIstituto === 1) {
        $conditions[] = "(tipo = 'ISTITUTO')";
    }

    if (count($conditions) === 0) {
        return [];
    }

    $query = "
        SELECT *
        FROM google_calendar_config
        WHERE attivo = 1
          AND (" . implode(' OR ', $conditions) . ")
        ORDER BY tipo ASC, nome ASC
    ";

    return dbGetAll($query) ?: [];
}

function mbappCalendarBookingParseList(string $value): array
{
    $parts = preg_split('/[\s,;]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
    $out = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }

    return array_values(array_unique($out));
}

function mbappCalendarBookingSyncDocentiCoinvolti(array $payload): array
{
    global $__settings;

    if (!(bool)($__settings->local->googleCalendarDocenti->syncOnMbappPrenotazioni ?? true)) {
        infoGoogleCalendar('Sync Google Calendar docenti post prenotazione MBApp disabilitata da configurazione.');
        return [];
    }

    $usernames = mbappCalendarBookingParseList((string)($payload['docenti'] ?? ''));
    if (empty($usernames)) return [];

    $dataInizio = trim((string)($payload['dataInizio'] ?? ''));
    $dataFine = trim((string)($payload['dataFine'] ?? ''));
    if ($dataFine === '') $dataFine = $dataInizio;

    $dataInizio = mbappCalendarBookingNormalizeDateOnly($dataInizio);
    $dataFine = mbappCalendarBookingNormalizeDateOnly($dataFine);
    if ($dataInizio === '' || $dataFine === '') return [];
    if ($dataFine < $dataInizio) $dataFine = $dataInizio;

    try {
        $results = googleCalendarDocentiSyncUsernames($usernames, $dataInizio, $dataFine);
        infoGoogleCalendar('Sync Google Calendar docenti post prenotazione MBApp: ' . json_encode([
            'docenti' => $usernames,
            'from' => $dataInizio,
            'to' => $dataFine,
            'risultati' => count($results)
        ], JSON_UNESCAPED_UNICODE));
        return $results;
    } catch (Throwable $e) {
        warningGoogleCalendar('Sync Google Calendar docenti post prenotazione MBApp fallito: ' . $e->getMessage());
        return [[
            'ok' => false,
            'error' => $e->getMessage()
        ]];
    }
}

function mbappCalendarBookingNormalizeDateOnly(string $date): string
{
    $date = trim($date);
    if ($date === '') return '';
    $date = str_replace('/', '-', $date);
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) {
        $date = $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
}

function mbappCalendarBookingTeacherTitle(string $nome): string
{
    $nome = strtoupper(trim($nome));

    if ($nome === '') {
        return 'prof.';
    }

    $primoNome = explode(' ', $nome)[0] ?? '';

    // Eccezioni maschili che finiscono per A
    $eccezioniMaschili = [
        'ANDREA',
        'LUCA',
        'GEREMIA'
    ];

    if (in_array($primoNome, $eccezioniMaschili, true)) {
        return 'prof.';
    }

    $eccezioniFemminili = [
        'IRENE',
        'ILDE',
        'SHARON',
        'MIRIAM'
    ];

    if (in_array($primoNome, $eccezioniFemminili, true)) {
        return 'prof.ssa';
    }

    // Regola semplice italiana
    if (substr($primoNome, -1) === 'A') {
        return 'prof.ssa';
    }

    return 'prof.';
}

function mbappCalendarBookingGetDocentiDaUsernames(string $docentiRaw): array
{
    $usernames = mbappCalendarBookingParseList($docentiRaw);

    if (count($usernames) === 0) {
        return [];
    }

    $quoted = [];
    foreach ($usernames as $username) {
        $quoted[] = "'" . dbEscape($username) . "'";
    }

    $rows = dbGetAll("
        SELECT username, nome, cognome, email
        FROM docente
        WHERE username IN (" . implode(',', $quoted) . ")
    ") ?: [];

    $map = [];
    foreach ($rows as $row) {
        $map[$row['username']] = $row;
    }

    $docenti = [];

    foreach ($usernames as $username) {
        if (!isset($map[$username])) {
            continue;
        }

        $row = $map[$username];

        $nome = trim((string)($row['nome'] ?? ''));
        $cognome = trim((string)($row['cognome'] ?? ''));
        $email = trim((string)($row['email'] ?? ''));

        if ($email === '') {
            continue;
        }

        $inizialeNome = $nome !== '' ? mb_substr($nome, 0, 1, 'UTF-8') . '.' : '';

        $titolo = mbappCalendarBookingTeacherTitle($nome);
        $docenti[] = [
            'username' => $username,
            'nome' => $nome,
            'cognome' => $cognome,
            'email' => $email,
            'titolo' => $titolo,
            'label' => trim($cognome . ' ' . $inizialeNome)
        ];
    }

    return $docenti;
}

function mbappCalendarBookingBuildTitle(string $titoloBase, array $docenti, string $classiRaw): string
{
    $parti = [$titoloBase];

    $docentiLabel = [];
    foreach ($docenti as $docente) {
        if (($docente['label'] ?? '') !== '') {
            $docentiLabel[] =
                trim(($docente['titolo'] ?? 'prof.') . ' ' . $docente['label']);
        }
    }

    if (count($docentiLabel) > 0) {
        $parti[] = implode(', ', $docentiLabel);
    }

    $classi = mbappCalendarBookingParseList($classiRaw);
    if (count($classi) > 0) {
        $parti[] = 'classe ' . implode(', ', $classi);
    }

    return implode(' - ', $parti);
}

function mbappCalendarBookingBuildGoogleEvent(array $payload): array
{
    $titoloBase = trim((string)($payload['dettagli'] ?? ''));
    if ($titoloBase === '') {
        $titoloBase = trim((string)($payload['motivo'] ?? 'Prenotazione aula'));
    }
    if ($titoloBase === '') {
        $titoloBase = 'Prenotazione aula';
    }

    $docentiRaw = trim((string)($payload['docenti'] ?? ''));
    $classiRaw = trim((string)($payload['classi'] ?? ''));

    $docentiInfo = mbappCalendarBookingGetDocentiDaUsernames($docentiRaw);

    $titolo = mbappCalendarBookingBuildTitle(
        $titoloBase,
        $docentiInfo,
        $classiRaw
    );

    $dataInizio = trim((string)($payload['dataInizio'] ?? ''));
    $dataFine = trim((string)($payload['dataFine'] ?? ''));

    if ($dataFine === '') {
        $dataFine = $dataInizio;
    }

    $oraInizio = trim((string)($payload['oraInizioReale'] ?? ''));
    $oraFine = trim((string)($payload['oraFineReale'] ?? ''));

    if ($oraInizio === '') {
        $oraInizio = trim((string)($payload['oraInizio'] ?? ''));
    }

    if ($oraFine === '') {
        $oraFine = trim((string)($payload['oraFine'] ?? ''));
    }

    if ($dataInizio === '' || $oraInizio === '' || $oraFine === '') {
        throw new Exception('Data/ora prenotazione incomplete');
    }

    $start = mbappCalendarBookingNormalizeDateTime($dataInizio, $oraInizio);
    $end = mbappCalendarBookingNormalizeDateTime($dataFine, $oraFine);

    $note = trim((string)($payload['note'] ?? ''));
    $nroAula = trim((string)($payload['nroAula'] ?? ''));
    $idAssenza = intval($payload['idAssenza'] ?? 0);

    $descrizione = "Evento sincronizzato da MBApp";
    $descrizione .= "\nID assenza MBApp: " . $idAssenza;

    if ($docentiRaw !== '') {
        $descrizione .= "\nDocenti: " . $docentiRaw;
    }

    if ($classiRaw !== '') {
        $descrizione .= "\nClassi: " . $classiRaw;
    }

    if ($note !== '') {
        $descrizione .= "\nNote: " . $note;
    }

    $attendees = [];

    foreach ($docentiInfo as $docente) {
        $attendees[] = [
            'email' => $docente['email'],
            'displayName' => trim(($docente['nome'] ?? '') . ' ' . ($docente['cognome'] ?? ''))
        ];
    }

    return [
        'summary' => $titolo,
        'location' => $nroAula !== '' ? 'Aula ' . $nroAula : '',
        'description' => $descrizione,
        'start' => [
            'dateTime' => $start,
            'timeZone' => 'Europe/Rome'
        ],
        'end' => [
            'dateTime' => $end,
            'timeZone' => 'Europe/Rome'
        ],
        'attendees' => $attendees,
        'extendedProperties' => [
            'private' => [
                'source' => 'MBAPP',
                'idAssenza' => (string)$idAssenza
            ]
        ]
    ];
}

function mbappCalendarBookingNormalizeDateTime(string $date, string $time): string
{
    $date = trim($date);
    $time = trim($time);

    $date = str_replace('/', '-', $date);

    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) {
        $date = $m[3] . '-' . $m[2] . '-' . $m[1];
    }

    if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        $time .= ':00';
    }

    $dt = new DateTime($date . ' ' . $time, new DateTimeZone('Europe/Rome'));

    return $dt->format('c');
}
function mbappCalendarBookingSyncTargets(array $targetCalendars, int $idAssenza, array $event, array $payload): array
{
    $oldIdAssenza = intval($payload['old_idAssenza'] ?? 0);
    $syncIdAssenza = $oldIdAssenza > 0 ? $oldIdAssenza : $idAssenza;
infoGoogleCalendar(
    'MBApp sync targets start: ' .
    json_encode([
        'idAssenza' => $idAssenza,
        'oldIdAssenza' => $oldIdAssenza,
        'syncIdAssenza' => $syncIdAssenza,
        'targetCalendars' => array_map(function ($cal) {
            return [
                'id' => intval($cal['id']),
                'nome' => $cal['nome'] ?? '',
                'tipo' => $cal['tipo'] ?? '',
                'nroAula' => $cal['nroAula'] ?? ''
            ];
        }, $targetCalendars)
    ], JSON_UNESCAPED_UNICODE)
);
    $targetByConfigId = [];
    foreach ($targetCalendars as $cal) {
        $targetByConfigId[intval($cal['id'])] = $cal;
    }

    $existingRows = dbGetAll("
        SELECT s.*, c.calendar_id, c.nome, c.tipo, c.nroAula
        FROM google_calendar_event_sync s
        INNER JOIN google_calendar_config c
            ON c.id = s.google_calendar_config_id
        WHERE s.idAssenza = " . intval($syncIdAssenza) . "
          AND s.stato <> 'ANNULLATO'
    ") ?: [];

    $results = [];
infoGoogleCalendar(
    'MBApp existing sync rows: ' .
    json_encode([
        'syncIdAssenza' => $syncIdAssenza,
        'count' => count($existingRows),
        'rows' => array_map(function ($row) {
            return [
                'sync_id' => intval($row['id']),
                'idAssenza' => intval($row['idAssenza']),
                'calendar_config_id' => intval($row['google_calendar_config_id']),
                'calendar_nome' => $row['nome'] ?? '',
                'google_event_id' => $row['google_event_id'] ?? '',
                'stato' => $row['stato'] ?? ''
            ];
        }, $existingRows)
    ], JSON_UNESCAPED_UNICODE)
);
    foreach ($existingRows as $row) {
        $configId = intval($row['google_calendar_config_id']);

        if (!isset($targetByConfigId[$configId])) {
            $results[] = mbappCalendarBookingDeleteSingleGoogleEvent($row, 'non più target');
        }
    }

    foreach ($targetCalendars as $cal) {
        $results[] = mbappCalendarBookingUpsertGoogleEvent($cal, $idAssenza, $event, $payload);
    }

    if (count($targetCalendars) === 0 && count($existingRows) === 0) {
        infoGoogleCalendar(
            'MBApp booking sync skipped: nessun calendario target e nessuna sync precedente ' .
                json_encode([
                    'idAssenza' => $idAssenza,
                    'old_idAssenza' => $oldIdAssenza,
                    'syncIdAssenza' => $syncIdAssenza
                ], JSON_UNESCAPED_UNICODE)
        );

        $results[] = [
            'ok' => true,
            'action' => 'skip',
            'message' => 'Nessun calendario target e nessuna sync precedente'
        ];
    }

    return $results;
}

function mbappCalendarBookingDeleteSingleGoogleEvent(array $row, string $reason = ''): array
{
    $calendarId = (string)($row['calendar_id'] ?? '');
    $googleEventId = (string)($row['google_event_id'] ?? '');

    $deleted = false;
    $error = '';

    if ($calendarId !== '' && $googleEventId !== '') {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
            rawurlencode($calendarId) .
            '/events/' .
            rawurlencode($googleEventId) .
            '?sendUpdates=none';

        try {
            googleCalendarApiRequest('DELETE', $url);
            $deleted = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();

            // Se Google risponde 404/410, per GestOre la sync è comunque da chiudere.
            if (strpos($error, 'HTTP 404') !== false || strpos($error, 'HTTP 410') !== false) {
                $deleted = true;
            }
        }
    }

    dbExec("
        UPDATE google_calendar_event_sync
        SET stato = 'ANNULLATO',
            ultimo_errore = " . ($error !== '' ? "'" . dbEscape($error) . "'" : "NULL") . ",
            updated_at = NOW()
        WHERE id = " . intval($row['id']) . "
        LIMIT 1
    ");

    infoGoogleCalendar(
        'MBApp booking delete Google event: ' .
            json_encode([
                'sync_id' => intval($row['id']),
                'idAssenza' => intval($row['idAssenza']),
                'calendar_config_id' => intval($row['google_calendar_config_id']),
                'calendar_nome' => $row['nome'] ?? '',
                'google_event_id' => $googleEventId,
                'deleted' => $deleted,
                'reason' => $reason,
                'error' => $error
            ], JSON_UNESCAPED_UNICODE)
    );

    return [
        'ok' => $deleted || $error === '',
        'action' => 'delete',
        'calendar_config_id' => intval($row['google_calendar_config_id']),
        'calendar_nome' => $row['nome'] ?? '',
        'google_event_id' => $googleEventId,
        'reason' => $reason,
        'error' => $error
    ];
}

function mbappCalendarBookingUpsertGoogleEvent(array $calendarConfig, int $idAssenza, array $event, array $payload): array
{
    $configId = intval($calendarConfig['id']);
    $calendarId = (string)$calendarConfig['calendar_id'];

    $oldIdAssenza = intval($payload['old_idAssenza'] ?? 0);
    $syncIdAssenza = $oldIdAssenza > 0 ? $oldIdAssenza : $idAssenza;

    $sync = dbGetFirst("
    SELECT *
    FROM google_calendar_event_sync
    WHERE google_calendar_config_id = " . intval($configId) . "
      AND idAssenza = " . intval($syncIdAssenza) . "
    LIMIT 1
");

    infoGoogleCalendar(
        'MBApp booking upsert lookup: ' .
            json_encode([
                'configId' => $configId,
                'idAssenza' => $idAssenza,
                'oldIdAssenza' => $oldIdAssenza,
                'syncIdAssenza' => $syncIdAssenza,
                'sync_found' => $sync != null ? 1 : 0,
                'google_event_id' => $sync['google_event_id'] ?? ''
            ], JSON_UNESCAPED_UNICODE)
    );
    if ($sync != null && trim((string)($sync['google_event_id'] ?? '')) !== '') {
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

    $titolo = (string)($event['summary'] ?? '');
    $inizio = mbappCalendarBookingDateTimeForDb((string)($event['start']['dateTime'] ?? ''));
    $fine = mbappCalendarBookingDateTimeForDb((string)($event['end']['dateTime'] ?? ''));

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
                " . intval($configId) . ",
                " . intval($idAssenza) . ",
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
            idAssenza = VALUES(idAssenza),
            titolo = VALUES(titolo),
            inizio = VALUES(inizio),
            fine = VALUES(fine),
            stato = 'CONFERMATO',
            ultimo_errore = NULL,
            updated_at = NOW()
    ");

    infoGoogleCalendar(
        'MBApp booking sync Google OK: ' .
            json_encode([
                'action' => $action,
                'idAssenza' => $idAssenza,
                'calendar_config_id' => $configId,
                'calendar_nome' => $calendarConfig['nome'] ?? '',
                'google_event_id' => $googleEventId
            ], JSON_UNESCAPED_UNICODE)
    );

    return [
        'ok' => true,
        'action' => $action,
        'calendar_config_id' => $configId,
        'calendar_nome' => $calendarConfig['nome'] ?? '',
        'google_event_id' => $googleEventId
    ];
}

function mbappCalendarBookingDateTimeForDb(string $dateTime): string
{
    if ($dateTime === '') {
        return '';
    }

    $dt = new DateTime($dateTime);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));

    return $dt->format('Y-m-d H:i:s');
}

function mbappCalendarBookingDeleteFromGoogle(int $idAssenza): array
{
    $rows = dbGetAll("
        SELECT s.*, c.calendar_id, c.nome, c.tipo, c.nroAula
        FROM google_calendar_event_sync s
        INNER JOIN google_calendar_config c
            ON c.id = s.google_calendar_config_id
        WHERE s.idAssenza = " . intval($idAssenza) . "
          AND s.stato <> 'ANNULLATO'
    ") ?: [];

    $results = [];

    foreach ($rows as $row) {
        $results[] = mbappCalendarBookingDeleteSingleGoogleEvent($row, 'annulla da MBApp');
    }

    return $results;
}

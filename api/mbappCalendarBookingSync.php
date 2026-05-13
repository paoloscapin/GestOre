<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/__Log.php';
require_once __DIR__ . '/googleCalendarLib.php';

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

    if ($idAssenza <= 0) {
        throw new Exception('idAssenza mancante');
    }

    if ($azione !== 'CONFERMA' && $azione !== 'UPDATE' && $azione !== 'ANNULLA') {
        throw new Exception('Azione non valida: ' . $azione);
    }

    if ($azione === 'ANNULLA') {
        $result = mbappCalendarBookingDeleteFromGoogle($idAssenza);

        echo json_encode([
            'ok' => true,
            'action' => 'ANNULLA',
            'result' => $result
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $calendari = mbappCalendarBookingGetTargetCalendars($nroAula, $pubblicaIstituto);

    if (count($calendari) === 0) {
        infoGoogleCalendar(
            'MBApp booking sync skipped: nessun calendario target ' .
                json_encode([
                    'idAssenza' => $idAssenza,
                    'nroAula' => $nroAula,
                    'pubblicaIstituto' => $pubblicaIstituto
                ], JSON_UNESCAPED_UNICODE)
        );

        echo json_encode([
            'ok' => true,
            'skipped' => true,
            'message' => 'Nessun calendario da aggiornare'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $event = mbappCalendarBookingBuildGoogleEvent($payload);

    $results = [];

    foreach ($calendari as $cal) {
        $results[] = mbappCalendarBookingUpsertGoogleEvent($cal, $idAssenza, $event, $payload);
    }

    echo json_encode([
        'ok' => true,
        'action' => $azione,
        'idAssenza' => $idAssenza,
        'calendari' => count($calendari),
        'results' => $results
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

function mbappCalendarBookingUpsertGoogleEvent(array $calendarConfig, int $idAssenza, array $event, array $payload): array
{
    $configId = intval($calendarConfig['id']);
    $calendarId = (string)$calendarConfig['calendar_id'];

    $sync = dbGetFirst("
        SELECT *
        FROM google_calendar_event_sync
        WHERE google_calendar_config_id = " . intval($configId) . "
          AND idAssenza = " . intval($idAssenza) . "
        LIMIT 1
    ");

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
        SELECT s.*, c.calendar_id, c.nome
        FROM google_calendar_event_sync s
        INNER JOIN google_calendar_config c
            ON c.id = s.google_calendar_config_id
        WHERE s.idAssenza = " . intval($idAssenza) . "
          AND s.stato <> 'ANNULLATO'
    ") ?: [];

    $results = [];

    foreach ($rows as $row) {
        $calendarId = (string)$row['calendar_id'];
        $googleEventId = (string)$row['google_event_id'];

        if ($calendarId !== '' && $googleEventId !== '') {
            $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
                rawurlencode($calendarId) .
                '/events/' .
                rawurlencode($googleEventId);

            try {
                googleCalendarApiRequest('DELETE', $url);
            } catch (Throwable $e) {
                // Se non esiste più su Google, considero comunque chiuso lato sync.
            }
        }

        dbExec(
            "
            UPDATE google_calendar_event_sync
            SET stato = 'ANNULLATO',
                updated_at = NOW()
            WHERE id = " . intval($row['id'])
        );

        $results[] = [
            'calendar_nome' => $row['nome'] ?? '',
            'google_event_id' => $googleEventId,
            'deleted' => true
        ];
    }

    return $results;
}

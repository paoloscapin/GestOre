<?php

require_once __DIR__ . '/../common/__Settings.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/connectMBApp.php';
require_once __DIR__ . '/../common/__Log.php';

function mbappCalendarGiornoIt($dataYmd)
{
    $giorni = [
        'Sunday' => 'domenica',
        'Monday' => 'lunedi',
        'Tuesday' => 'martedi',
        'Wednesday' => 'mercoledi',
        'Thursday' => 'giovedi',
        'Friday' => 'venerdi',
        'Saturday' => 'sabato'
    ];

    $ts = strtotime($dataYmd);
    $eng = $ts ? date('l', $ts) : '';

    return $giorni[$eng] ?? '';
}

function mbappCalendarSchoolSlots()
{
    return [
        ['inizio' => '07:50', 'fine' => '08:40'],
        ['inizio' => '08:40', 'fine' => '09:30'],
        ['inizio' => '09:30', 'fine' => '10:20'],
        ['inizio' => '10:30', 'fine' => '11:20'],
        ['inizio' => '11:20', 'fine' => '12:10'],
        ['inizio' => '12:10', 'fine' => '13:00'],
        ['inizio' => '13:00', 'fine' => '13:50'],
        ['inizio' => '13:50', 'fine' => '14:40'],
        ['inizio' => '14:40', 'fine' => '15:30'],
        ['inizio' => '15:30', 'fine' => '16:20'],
        ['inizio' => '16:20', 'fine' => '17:10'],
        ['inizio' => '17:10', 'fine' => '18:00']
    ];
}

function mbappCalendarGetSchoolHoursOccupied($oraInizioReale, $oraFineReale)
{
    $startMin = mbappCalendarTimeToMinutes($oraInizioReale);
    $endMin = mbappCalendarTimeToMinutes($oraFineReale);

    $ore = [];

    foreach (mbappCalendarSchoolSlots() as $slot) {
        $slotStart = mbappCalendarTimeToMinutes($slot['inizio']);
        $slotEnd = mbappCalendarTimeToMinutes($slot['fine']);

        // L'evento occupa questa ora se si sovrappone anche solo in parte
        if ($startMin < $slotEnd && $endMin > $slotStart) {
            $ore[] = $slot['inizio'];
        }
    }

    return $ore;
}

function mbappCalendarTimeToMinutes($hhmm)
{
    $parts = explode(':', substr(trim((string)$hhmm), 0, 5));
    if (count($parts) < 2) {
        return 0;
    }

    return intval($parts[0]) * 60 + intval($parts[1]);
}

function mbappCalendarGetSchoolRange($oraInizioReale, $oraFineReale)
{
    $startMin = mbappCalendarTimeToMinutes($oraInizioReale);
    $endMin = mbappCalendarTimeToMinutes($oraFineReale);

    $slots = mbappCalendarSchoolSlots();

    $schoolStart = $slots[0]['inizio'];
    $schoolEnd = $slots[count($slots) - 1]['fine'];

    foreach ($slots as $slot) {
        $slotStart = mbappCalendarTimeToMinutes($slot['inizio']);
        $slotEnd = mbappCalendarTimeToMinutes($slot['fine']);

        if ($startMin >= $slotStart && $startMin < $slotEnd) {
            $schoolStart = $slot['inizio'];
            break;
        }
    }

    foreach ($slots as $slot) {
        $slotStart = mbappCalendarTimeToMinutes($slot['inizio']);
        $slotEnd = mbappCalendarTimeToMinutes($slot['fine']);

        if ($endMin > $slotStart && $endMin <= $slotEnd) {
            $schoolEnd = $slot['fine'];
            break;
        }

        if ($endMin == $slotStart) {
            $schoolEnd = $slot['inizio'];
            break;
        }
    }

    return [
        'oraInizio' => $schoolStart,
        'oraFine' => $schoolEnd
    ];
}

function mbappCalendarDateTimeFromGoogle($value)
{
    if ($value === '') {
        return null;
    }

    try {
        $dt = new DateTime($value);
        $dt->setTimezone(new DateTimeZone('Europe/Rome'));
        return $dt;
    } catch (Throwable $e) {
        errorGoogleCalendarMBApp('Errore conversione data Google: ' . $e->getMessage());
        return null;
    }
}

function mbappCalendarExtractEventTimes($event)
{
    $startValue = $event['start']['dateTime'] ?? ($event['start']['date'] ?? '');
    $endValue = $event['end']['dateTime'] ?? ($event['end']['date'] ?? '');

    $start = mbappCalendarDateTimeFromGoogle($startValue);
    $end = mbappCalendarDateTimeFromGoogle($endValue);

    if ($start == null || $end == null) {
        warningGoogleCalendarMBApp(
            'Date evento Google non valide: ' .
                json_encode([
                    'event_id' => $event['id'] ?? '',
                    'start' => $startValue,
                    'end' => $endValue
                ], JSON_UNESCAPED_UNICODE)
        );

        return null;
    }

    $oraInizioReale = $start->format('H:i');
    $oraFineReale = $end->format('H:i');
    $schoolRange = mbappCalendarGetSchoolRange($oraInizioReale, $oraFineReale);

    return [
        'data' => $start->format('Y-m-d'),

        'oraInizio' => $schoolRange['oraInizio'],
        'oraFine' => $schoolRange['oraFine'],

        'oraInizioReale' => $oraInizioReale,
        'oraFineReale' => $oraFineReale,
        'oreScolasticheOccupate' => mbappCalendarGetSchoolHoursOccupied($oraInizioReale, $oraFineReale),
        'inizioDb' => $start->format('Y-m-d H:i:s'),
        'fineDb' => $end->format('Y-m-d H:i:s'),
        'giorno' => mbappCalendarGiornoIt($start->format('Y-m-d'))
    ];
}

function mbappCalendarSyncFromGoogleEvent($config, $event)
{
    setLogChannel('google_calendar_mbapp');

    $tipo = strtoupper((string)($config['tipo'] ?? ''));

    if ($tipo !== 'AULA') {
        warningGoogleCalendarMBApp(
            'SKIP calendario non AULA: ' .
                json_encode([
                    'config_id' => $config['id'] ?? 0,
                    'tipo' => $config['tipo'] ?? '',
                    'calendar_id' => $config['calendar_id'] ?? '',
                    'event_id' => $event['id'] ?? '',
                    'summary' => $event['summary'] ?? ''
                ], JSON_UNESCAPED_UNICODE)
        );

        return [
            'ok' => true,
            'action' => 'skip',
            'msg' => 'Calendario non AULA: per ora non creo MBApp'
        ];
    }

    $googleConfigId = intval($config['id'] ?? 0);
    $nroAula = trim((string)($config['nroAula'] ?? ''));

    if ($googleConfigId <= 0 || $nroAula === '') {
        errorGoogleCalendarMBApp(
            'Configurazione calendario senza id o nroAula: ' .
                json_encode([
                    'config_id' => $googleConfigId,
                    'nroAula' => $nroAula,
                    'event_id' => $event['id'] ?? ''
                ], JSON_UNESCAPED_UNICODE)
        );

        return [
            'ok' => false,
            'action' => 'error',
            'msg' => 'Configurazione calendario senza id o nroAula'
        ];
    }

    $googleEventId = (string)($event['id'] ?? '');
    $status = (string)($event['status'] ?? '');

    if ($googleEventId === '') {
        errorGoogleCalendarMBApp(
            'Evento Google senza id: ' .
                json_encode($event, JSON_UNESCAPED_UNICODE)
        );

        return [
            'ok' => false,
            'action' => 'error',
            'msg' => 'Evento Google senza id'
        ];
    }

    $googleIcalUid = (string)($event['iCalUID'] ?? '');

    $sync = dbGetFirst("
    SELECT *
    FROM google_calendar_event_sync
    WHERE google_calendar_config_id = " . intval($googleConfigId) . "
      AND google_event_id = '" . dbEscape($googleEventId) . "'
    LIMIT 1
");


    if ($sync == null && $googleIcalUid !== '') {
        $sync = dbGetFirst("
        SELECT *
        FROM google_calendar_event_sync
        WHERE google_ical_uid = '" . dbEscape($googleIcalUid) . "'
          AND stato <> 'ANNULLATO'
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");

        if ($sync != null) {
            infoGoogleCalendarMBApp(
                'SYNC trovato tramite iCalUID, probabile cambio calendario/aula: ' .
                    json_encode([
                        'old_sync_id' => intval($sync['id']),
                        'old_config_id' => intval($sync['google_calendar_config_id']),
                        'new_config_id' => $googleConfigId,
                        'idAssenza' => intval($sync['idAssenza']),
                        'old_google_event_id' => $sync['google_event_id'] ?? '',
                        'new_google_event_id' => $googleEventId,
                        'google_ical_uid' => $googleIcalUid
                    ], JSON_UNESCAPED_UNICODE)
            );
        }
    }

    if ($status === 'cancelled') {
        return mbappCalendarCancelGoogleEvent($config, $event, $sync);
    }

    if ($status !== 'confirmed') {
        warningGoogleCalendarMBApp(
            'SKIP status Google non gestito: ' .
                json_encode([
                    'event_id' => $googleEventId,
                    'status' => $status,
                    'summary' => $event['summary'] ?? ''
                ], JSON_UNESCAPED_UNICODE)
        );

        return [
            'ok' => true,
            'action' => 'skip',
            'msg' => 'Status Google non gestito: ' . $status
        ];
    }

    $times = mbappCalendarExtractEventTimes($event);

    if ($times == null) {
        return [
            'ok' => false,
            'action' => 'error',
            'msg' => 'Date evento Google non valide'
        ];
    }

    if ($sync != null && intval($sync['idAssenza'] ?? 0) > 0) {
        return mbappCalendarUpdateGoogleEvent($config, $event, $sync, $times);
    }

    return mbappCalendarCreateGoogleEvent($config, $event, $times);
}

function mbappCalendarCreateGoogleEvent($config, $event, $times)
{
    setLogChannel('google_calendar_mbapp');

    $googleConfigId = intval($config['id']);
    $nroAula = trim((string)$config['nroAula']);

    $googleEventId = (string)($event['id'] ?? '');
    $googleIcalUid = (string)($event['iCalUID'] ?? '');
    $googleEtag = (string)($event['etag'] ?? '');

    $titolo = trim((string)($event['summary'] ?? 'Prenotazione da Google Calendar'));
    if ($titolo === '') {
        $titolo = 'Prenotazione da Google Calendar';
    }
    $motivo = 'IMPEGNO IN ISTITUTO';
    $dettagli = $titolo;

    $dataEsc = addslashes($times['data']);
    $oraInizioEsc = addslashes($times['oraInizio']);
    $oraFineEsc = addslashes($times['oraFine']);
    $oraInizioRealeEsc = addslashes($times['oraInizioReale']);
    $oraFineRealeEsc = addslashes($times['oraFineReale']);
    $noteEsc = addslashes('Sync da Google Calendar');
    $giornoEsc = addslashes($times['giorno']);
    $motivoEsc = addslashes($motivo);
    $titoloEsc = addslashes($titolo);
    $dettagliEsc = addslashes($dettagli);
    $aulaEsc = addslashes($nroAula);

    $stato = 'IN ATTESA';

    mb_dbExec("
        INSERT INTO assenze
        (docenti, dataInizio, dataFine, oraInizio, oraFine, oraInizioReale, oraFineReale, motivo, dettagli, note, stato)
        VALUES
        ('', '$dataEsc', '$dataEsc', '$oraInizioEsc', '$oraFineEsc', '$oraInizioRealeEsc', '$oraFineRealeEsc', '$motivoEsc', '$dettagliEsc', '$noteEsc', '$stato')    
");

    $idAssenza = intval(mb_dbGetValue("SELECT LAST_INSERT_ID()"));

    if ($idAssenza <= 0) {
        errorGoogleCalendarMBApp(
            'INSERT assenze fallito: ' .
                json_encode([
                    'google_event_id' => $googleEventId,
                    'titolo' => $titolo
                ], JSON_UNESCAPED_UNICODE)
        );

        return [
            'ok' => false,
            'action' => 'create',
            'msg' => 'INSERT assenze fallito'
        ];
    }

    $idCalendario = 0;

    $oreScolastiche = $times['oreScolasticheOccupate'] ?? [];

    foreach ($oreScolastiche as $oraScolastica) {
        $oraScolasticaEsc = addslashes($oraScolastica);

        mb_dbExec("
        INSERT INTO oralezione
            (nroAula, dataGiorno, giorno, ora, attivitaProgetto, stato, idAssenza)
        VALUES
            ('$aulaEsc', '$dataEsc', '$giornoEsc', '$oraScolasticaEsc', '$titoloEsc', '$stato', $idAssenza)
    ");

        if ($idCalendario <= 0) {
            $idCalendario = intval(mb_dbGetValue("SELECT LAST_INSERT_ID()"));
        }
    }

    if ($idCalendario <= 0) {
        errorGoogleCalendarMBApp(
            'INSERT oralezione fallito: ' .
                json_encode([
                    'idAssenza' => $idAssenza,
                    'google_event_id' => $googleEventId,
                    'titolo' => $titolo
                ], JSON_UNESCAPED_UNICODE)
        );

        return [
            'ok' => false,
            'action' => 'create',
            'msg' => 'INSERT oralezione fallito',
            'idAssenza' => $idAssenza
        ];
    }

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
                " . intval($googleConfigId) . ",
                " . intval($idAssenza) . ",
                " . intval($idCalendario) . ",
                '" . dbEscape($googleEventId) . "',
                '" . dbEscape($googleIcalUid) . "',
                '" . dbEscape($googleEtag) . "',
                '" . dbEscape($titolo) . "',
                '" . dbEscape($times['inizioDb']) . "',
                '" . dbEscape($times['fineDb']) . "',
                'CONFERMATO',
                NULL,
                NOW(),
                NOW()
            )
        ON DUPLICATE KEY UPDATE
            idAssenza = VALUES(idAssenza),
            idCalendario = VALUES(idCalendario),
            google_event_id = VALUES(google_event_id),
            google_ical_uid = VALUES(google_ical_uid),
            google_etag = VALUES(google_etag),
            titolo = VALUES(titolo),
            inizio = VALUES(inizio),
            fine = VALUES(fine),
            stato = VALUES(stato),
            ultimo_errore = NULL,
            updated_at = NOW()
    ");

    infoGoogleCalendarMBApp(
        'CREATE MBApp da Google OK: ' .
            json_encode([
                'idAssenza' => $idAssenza,
                'idCalendario' => $idCalendario,
                'google_event_id' => $googleEventId,
                'google_ical_uid' => $googleIcalUid,
                'titolo' => $titolo,
                'nroAula' => $nroAula
            ], JSON_UNESCAPED_UNICODE)
    );

    return [
        'ok' => true,
        'action' => 'create',
        'msg' => 'Creato evento MBApp da Google Calendar',
        'idAssenza' => $idAssenza,
        'idCalendario' => $idCalendario
    ];
}

function mbappCalendarUpdateGoogleEvent($config, $event, $sync, $times)
{
    setLogChannel('google_calendar_mbapp');

    $idAssenza = intval($sync['idAssenza']);
    $idCalendario = intval($sync['idCalendario']);
    $nroAula = trim((string)$config['nroAula']);

    $googleEventId = (string)($event['id'] ?? '');
    $googleIcalUid = (string)($event['iCalUID'] ?? '');
    $googleEtag = (string)($event['etag'] ?? '');

    $titolo = trim((string)($event['summary'] ?? 'Prenotazione da Google Calendar'));
    if ($titolo === '') {
        $titolo = 'Prenotazione da Google Calendar';
    }
    $motivo = 'IMPEGNO IN ISTITUTO';
    $dataEsc = addslashes($times['data']);
    $oraInizioEsc = addslashes($times['oraInizio']);
    $oraFineEsc = addslashes($times['oraFine']);
    $oraInizioRealeEsc = addslashes($times['oraInizioReale']);
    $oraFineRealeEsc = addslashes($times['oraFineReale']);
    $noteEsc = addslashes('Sync da Google Calendar');
    $giornoEsc = addslashes($times['giorno']);
    $motivoEsc = addslashes($motivo);
    $titoloEsc = addslashes($titolo);
    $aulaEsc = addslashes($nroAula);
    $dettagli = $titolo;

    $dettagliEsc = addslashes($dettagli);
    $statoCorrente = strtoupper(trim((string)mb_dbGetValue("
        SELECT stato
        FROM assenze
        WHERE idAssenza = $idAssenza
        LIMIT 1
    ")));
    $stato = ($statoCorrente === 'CONFERMATO') ? 'CONFERMATO' : 'IN ATTESA';
    $statoEsc = addslashes($stato);
    // Ricreo le righe oralezione perché cambiando orario può cambiare il numero di ore scolastiche occupate
    mb_dbExec("
    DELETE FROM oralezione
    WHERE idAssenza = $idAssenza
");

    $idCalendarioNuovo = 0;

    $oreScolastiche = $times['oreScolasticheOccupate'] ?? [];

    foreach ($oreScolastiche as $oraScolastica) {
        $oraScolasticaEsc = addslashes($oraScolastica);

        mb_dbExec("
        INSERT INTO oralezione
            (nroAula, dataGiorno, giorno, ora, attivitaProgetto, stato, idAssenza)
        VALUES
            ('$aulaEsc', '$dataEsc', '$giornoEsc', '$oraScolasticaEsc', '$titoloEsc', '$statoEsc', $idAssenza)
    ");

        if ($idCalendarioNuovo <= 0) {
            $idCalendarioNuovo = intval(mb_dbGetValue("SELECT LAST_INSERT_ID()"));
        }
    }

    if ($idCalendarioNuovo > 0) {
        $idCalendario = $idCalendarioNuovo;
    }

    mb_dbExec("
        UPDATE assenze
        SET
        dataInizio = '$dataEsc',
        dataFine = '$dataEsc',
        oraInizio = '$oraInizioEsc',
        oraFine = '$oraFineEsc',
        oraInizioReale = '$oraInizioRealeEsc',
        oraFineReale = '$oraFineRealeEsc',
        motivo = '$motivoEsc',
        dettagli = '$dettagliEsc',
        note = '$noteEsc',
        stato = '$statoEsc'
        WHERE idAssenza = $idAssenza
        LIMIT 1
    ");

    dbExec("
        UPDATE google_calendar_event_sync
        SET
            google_calendar_config_id = " . intval($config['id']) . ",
            google_event_id = '" . dbEscape($googleEventId) . "',
            idCalendario = " . intval($idCalendario) . ",
            google_ical_uid = '" . dbEscape($googleIcalUid) . "',
            google_etag = '" . dbEscape($googleEtag) . "',
            titolo = '" . dbEscape($titolo) . "',
            inizio = '" . dbEscape($times['inizioDb']) . "',
            fine = '" . dbEscape($times['fineDb']) . "',
            stato = 'CONFERMATO',
            ultimo_errore = NULL,
            updated_at = NOW()
        WHERE id = " . intval($sync['id']) . "
        LIMIT 1
    ");

    infoGoogleCalendarMBApp(
        'UPDATE MBApp da Google OK: ' .
            json_encode([
                'idAssenza' => $idAssenza,
                'idCalendario' => $idCalendario,
                'google_event_id' => $googleEventId,
                'google_ical_uid' => $googleIcalUid,
                'titolo' => $titolo,
                'nroAula' => $nroAula
            ], JSON_UNESCAPED_UNICODE)
    );

    return [
        'ok' => true,
        'action' => 'update',
        'msg' => 'Aggiornato evento MBApp da Google Calendar',
        'idAssenza' => $idAssenza,
        'idCalendario' => $idCalendario
    ];
}

function mbappCalendarCancelGoogleEvent($config, $event, $sync)
{
    setLogChannel('google_calendar_mbapp');

    if (strtoupper((string)($config['tipo'] ?? '')) !== 'AULA') {
        warningGoogleCalendarMBApp(
            'CANCEL Google ignorato: calendario non AULA: ' .
                json_encode([
                    'config_id' => $config['id'] ?? 0,
                    'tipo' => $config['tipo'] ?? '',
                    'google_event_id' => $event['id'] ?? ''
                ], JSON_UNESCAPED_UNICODE)
        );

        return [
            'ok' => true,
            'action' => 'skip',
            'msg' => 'Cancellazione ignorata: calendario non AULA'
        ];
    }

    $googleEventId = (string)($event['id'] ?? '');

    if ($sync == null) {
        warningGoogleCalendarMBApp(
            'CANCEL ignorato, sync non trovato: ' .
                json_encode([
                    'google_event_id' => $googleEventId,
                    'summary' => $event['summary'] ?? ''
                ], JSON_UNESCAPED_UNICODE)
        );

        return [
            'ok' => true,
            'action' => 'skip',
            'msg' => 'Evento cancellato ma sync non trovato'
        ];
    }

    $idAssenza = intval($sync['idAssenza'] ?? 0);

    $idCalendario = intval($sync['idCalendario'] ?? 0);

    if ($idAssenza > 0) {
        mb_dbExec("
            DELETE FROM oralezione
            WHERE idAssenza = $idAssenza
        ");

        mb_dbExec("
            DELETE FROM assenze
            WHERE idAssenza = $idAssenza
            LIMIT 1
        ");
    }

    if ($idAssenza > 0) {
        mbappCalendarDeleteOtherGoogleEventsForAssenza(
            $idAssenza,
            intval($sync['id'] ?? 0)
        );
    }

    dbExec("
        UPDATE google_calendar_event_sync
        SET
            stato = 'ANNULLATO',
            updated_at = NOW()
        WHERE id = " . intval($sync['id']) . "
        LIMIT 1
    ");

    infoGoogleCalendarMBApp(
        'DELETE MBApp da Google OK: ' .
            json_encode([
                'idAssenza' => $idAssenza,
                'idCalendario' => $idCalendario,
                'google_event_id' => $googleEventId
            ], JSON_UNESCAPED_UNICODE)
    );

    return [
        'ok' => true,
        'action' => 'delete',
        'msg' => 'Eliminato evento MBApp da cancellazione Google Calendar',
        'idAssenza' => $idAssenza,
        'idCalendario' => $idCalendario
    ];
}

function mbappCalendarDeleteOtherGoogleEventsForAssenza($idAssenza, $excludeSyncId = 0)
{
    $idAssenza = intval($idAssenza);
    $excludeSyncId = intval($excludeSyncId);

    if ($idAssenza <= 0) {
        return;
    }

    $rows = dbGetAll("
        SELECT s.*, c.calendar_id, c.nome
        FROM google_calendar_event_sync s
        INNER JOIN google_calendar_config c
            ON c.id = s.google_calendar_config_id
        WHERE s.idAssenza = $idAssenza
          AND s.stato <> 'ANNULLATO'
          AND s.id <> $excludeSyncId
    ") ?: [];

    foreach ($rows as $row) {
        $calendarId = trim((string)($row['calendar_id'] ?? ''));
        $googleEventId = trim((string)($row['google_event_id'] ?? ''));

        if ($calendarId !== '' && $googleEventId !== '') {
            $url = 'https://www.googleapis.com/calendar/v3/calendars/' .
                rawurlencode($calendarId) .
                '/events/' .
                rawurlencode($googleEventId) .
                '?sendUpdates=none';

            try {
                googleCalendarApiRequest('DELETE', $url);
            } catch (Throwable $e) {
                // Se già cancellato/non trovato, proseguo comunque
            }
        }

        dbExec("
            UPDATE google_calendar_event_sync
            SET stato = 'ANNULLATO',
                updated_at = NOW()
            WHERE id = " . intval($row['id']) . "
            LIMIT 1
        ");
    }
}

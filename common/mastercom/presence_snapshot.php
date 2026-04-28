<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/../checkSession.php';
require_once __DIR__ . '/../__MasterCom.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function mcPresenceCurrentTs(): int
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->getTimestamp();
}

function mcPresenceDayBoundaryTs(string $time): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', mcPresenceCurrentDate() . ' ' . $time, new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mcPresenceFormatTs(int $timestamp, string $format = 'Y-m-d'): string
{
    if ($timestamp <= 0) {
        return '';
    }

    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format($format);
}

function mcPresenceCurrentDate(): string
{
    return mcPresenceFormatTs(mcPresenceCurrentTs(), 'Y-m-d');
}

function mcPresenceExtractEntryTimestamps(array $entry): array
{
    $timestamps = [];
    foreach (['data', 'data_ts', 'start_ts', 'end_ts', 'data_inizio_ts', 'data_fine_ts'] as $key) {
        if (isset($entry[$key]) && is_numeric($entry[$key])) {
            $timestamps[] = intval($entry[$key]);
        }
    }

    foreach (['data_inizio', 'data_fine', 'inizio', 'fine', 'date'] as $key) {
        $value = trim((string)($entry[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        $dt = strtotime($value);
        if ($dt !== false) {
            $timestamps[] = $dt;
        }
    }

    return $timestamps;
}

function mcPresenceEntryIsCurrent(array $entry, bool $strictDate = false): bool
{
    $timestamps = mcPresenceExtractEntryTimestamps($entry);
    if (empty($timestamps)) {
        return !$strictDate;
    }

    $today = mcPresenceCurrentDate();
    foreach ($timestamps as $timestamp) {
        if (mcPresenceFormatTs($timestamp, 'Y-m-d') === $today) {
            return true;
        }
    }

    $startTs = null;
    $endTs = null;
    if (isset($entry['data_inizio_ts']) && is_numeric($entry['data_inizio_ts'])) {
        $startTs = intval($entry['data_inizio_ts']);
    } elseif (isset($entry['start_ts']) && is_numeric($entry['start_ts'])) {
        $startTs = intval($entry['start_ts']);
    }

    if (isset($entry['data_fine_ts']) && is_numeric($entry['data_fine_ts'])) {
        $endTs = intval($entry['data_fine_ts']);
    } elseif (isset($entry['end_ts']) && is_numeric($entry['end_ts'])) {
        $endTs = intval($entry['end_ts']);
    }

    $nowTs = mcPresenceCurrentTs();
    if ($startTs !== null && $endTs !== null) {
        return $startTs <= $nowTs && $endTs >= $nowTs;
    }

    return false;
}

function mcPresenceNormalizeEntries(array $entries, bool $strictDate = false): array
{
    $normalized = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (!mcPresenceEntryIsCurrent($entry, $strictDate)) {
            continue;
        }
        $normalized[] = $entry;
    }

    return $normalized;
}

function mcPresenceMergeUniqueEntries(array $baseEntries, array $extraEntries): array
{
    $merged = [];
    $seen = [];

    foreach (array_merge($baseEntries, $extraEntries) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $key = '';
        if (isset($entry['id_assenza']) && intval($entry['id_assenza']) > 0) {
            $key = 'id:' . intval($entry['id_assenza']);
        } else {
            $key = 'k:' . implode('|', [
                trim((string)($entry['codice'] ?? '')),
                trim((string)($entry['descrizione'] ?? '')),
                trim((string)($entry['orario'] ?? '')),
                trim((string)($entry['data'] ?? '')),
            ]);
        }

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $merged[] = $entry;
    }

    return $merged;
}

function mcPresenceMergeTodayAbsencesIntoAppealRow(array $appealRow, array $absenceRecords): array
{
    $extraAssenze = [];
    $extraEntrate = [];
    $extraUscite = [];
    $extraPermessi = [];

    foreach ($absenceRecords as $record) {
        if (!is_array($record)) {
            continue;
        }

        $code = strtoupper(trim((string)($record['codice'] ?? '')));
        $tipo = intval($record['tipo'] ?? 0);

        if ($code === 'E' || $tipo === 2) {
            $extraEntrate[] = $record;
        } elseif ($code === 'U' || $tipo === 3) {
            $extraUscite[] = $record;
        } elseif ($code === 'P') {
            $extraPermessi[] = $record;
        } else {
            $extraAssenze[] = $record;
        }
    }

    $appealRow['assenze'] = mcPresenceMergeUniqueEntries(is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : [], $extraAssenze);
    $appealRow['entrate'] = mcPresenceMergeUniqueEntries(is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : [], $extraEntrate);
    $appealRow['uscite'] = mcPresenceMergeUniqueEntries(is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : [], $extraUscite);
    $appealRow['permessi'] = mcPresenceMergeUniqueEntries(is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : [], $extraPermessi);

    return $appealRow;
}

function mcPresenceWeekRangeTs(): array
{
    $today = new DateTime('now', new DateTimeZone('Europe/Rome'));
    $start = clone $today;
    $start->modify('monday this week')->setTime(0, 0, 0);
    $end = clone $start;
    $end->modify('+6 days')->setTime(23, 59, 59);

    return [
        'start' => $start->getTimestamp(),
        'end' => $end->getTimestamp(),
    ];
}

function mcPresenceBuildActiveCalendarEventsMap(array $calendarResponse): array
{
    $map = [];
    $debugCode = $calendarResponse['debug_code'] ?? [];
    if (!is_array($debugCode)) {
        return $map;
    }

    $nowTs = mcPresenceCurrentTs();
    foreach ($debugCode as $group) {
        if (!is_array($group)) {
            continue;
        }

        foreach ($group as $event) {
            if (!is_array($event)) {
                continue;
            }

            $startTs = intval($event['data_inizio'] ?? 0);
            $endTs = intval($event['data_fine'] ?? 0);
            if ($startTs <= 0 || $endTs <= 0 || $startTs > $nowTs || $endTs < $nowTs) {
                continue;
            }

            $participants = is_array($event['partecipanti'] ?? null) ? $event['partecipanti'] : [];
            if (empty($participants)) {
                continue;
            }

            foreach ($participants as $studentId => $studentName) {
                $studentId = intval($studentId);
                if ($studentId <= 0) {
                    continue;
                }

                $map[$studentId][] = [
                    'id_annotazione_agenda' => intval($event['id_evento'] ?? 0),
                    'titolo' => trim((string)($event['nome'] ?? '')),
                    'descrizione' => trim((string)($event['descrizione'] ?? '')),
                    'data_inizio_ts' => $startTs,
                    'data_fine_ts' => $endTs,
                    'partecipante' => trim((string)$studentName),
                ];
            }
        }
    }

    return $map;
}

function mcPresenceNormalizeAppealRow(array $appealRow): array
{
    $appealRow['entrate'] = mcPresenceNormalizeEntries(is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : [], true);
    $appealRow['uscite'] = mcPresenceNormalizeEntries(is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : [], true);
    $appealRow['permessi'] = mcPresenceNormalizeEntries(is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : [], true);
    $appealRow['eventi'] = mcPresenceNormalizeEntries(is_array($appealRow['eventi'] ?? null) ? $appealRow['eventi'] : [], true);
    $appealRow['assenze'] = mcPresenceNormalizeEntries(is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : [], true);

    if (
        !empty($appealRow['entrate']) ||
        !empty($appealRow['eventi']) ||
        intval($appealRow['ha_lezione'] ?? 0) === 1
    ) {
        $appealRow['assenze'] = [];
    }

    return $appealRow;
}

$classId = intval($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
if ($classId <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Parametro class_id mancante o non valido',
    ]);
    exit;
}

function classifyAppealState(array $appealRow): array
{
    $assenze = $appealRow['assenze'] ?? [];
    $entrate = $appealRow['entrate'] ?? [];
    $uscite = $appealRow['uscite'] ?? [];
    $permessi = $appealRow['permessi'] ?? [];
    $eventi = $appealRow['eventi'] ?? [];
    $haLezione = intval($appealRow['ha_lezione'] ?? 0) === 1;

    $status = 'nessuna_lezione';
    $presentAtSchool = false;
    $presentInClass = false;

    if (!empty($eventi)) {
        $status = 'evento';
        $presentAtSchool = true;
    } elseif (!empty($permessi)) {
        $status = 'permesso';
    } elseif (!empty($uscite)) {
        $status = 'uscita';
    } elseif (!empty($entrate)) {
        $status = 'presente_entrato_in_ritardo';
        $presentAtSchool = true;
        $presentInClass = true;
    } elseif (!empty($assenze)) {
        $status = 'assente';
    } elseif ($haLezione) {
        $status = 'presente_in_classe';
        $presentAtSchool = true;
        $presentInClass = true;
    }

    return [
        'status' => $status,
        'present_at_school' => $presentAtSchool,
        'present_in_class' => $presentInClass,
        'ha_lezione' => $haLezione,
    ];
}

$authResult = mastercomAuthenticateService([
    'profile' => 'MasterComDocenteAuth',
    'method' => 'POST',
    'timeout' => 60,
]);

if (!$authResult['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Autenticazione MasterCom docente fallita',
        'error' => $authResult['error'] ?? 'AUTH_FAILED',
        'http_code' => $authResult['http_code'] ?? 0,
    ]);
    exit;
}

$studentsResult = mastercomLoadStudentsList($authResult, $classId, [
    'method' => 'POST',
    'timeout' => 120,
]);
$appealResult = mastercomLoadAppealData($authResult, $classId, [
    'method' => 'POST',
    'timeout' => 120,
]);

if (!$studentsResult['ok'] || !$appealResult['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento dati presenza MasterCom fallito',
        'students_error' => $studentsResult['error'] ?? '',
        'appeal_error' => $appealResult['error'] ?? '',
    ]);
    exit;
}

$students = $studentsResult['response']['result'] ?? [];
$appeal = $appealResult['response']['result'] ?? [];
$todayStartTs = mcPresenceDayBoundaryTs('00:00:00');
$todayEndTs = mcPresenceDayBoundaryTs('23:59:59');
$weekRange = mcPresenceWeekRangeTs();
$calendarResult = mastercomLoadCalendarNotes($authResult, $classId, $weekRange['start'], $weekRange['end'], [
    'method' => 'POST',
    'timeout' => 120,
]);
$calendarEventsMap = ($calendarResult['ok'] && is_array($calendarResult['response'] ?? null))
    ? mcPresenceBuildActiveCalendarEventsMap($calendarResult['response'])
    : [];

$records = [];
$summary = [
    'presenti_in_classe' => 0,
    'presenti_a_scuola_ma_fuori_classe' => 0,
    'assenti' => 0,
    'usciti_o_permesso' => 0,
    'senza_lezione' => 0,
];

foreach ($students as $student) {
    $studentId = intval($student['id_studente'] ?? 0);
    $appealRow = mcPresenceNormalizeAppealRow(is_array($appeal[(string)$studentId] ?? null) ? $appeal[(string)$studentId] : []);
    if ($studentId > 0 && !empty($calendarEventsMap[$studentId])) {
        $appealRow['eventi'] = mcPresenceMergeUniqueEntries(is_array($appealRow['eventi'] ?? null) ? $appealRow['eventi'] : [], $calendarEventsMap[$studentId]);
    }
    if ($studentId > 0 && $todayStartTs > 0 && $todayEndTs > 0) {
        $absencesResult = mastercomLoadAbsencesData($authResult, $studentId, $todayStartTs, $todayEndTs, [
            'method' => 'POST',
            'timeout' => 120,
        ]);
        if ($absencesResult['ok'] && is_array($absencesResult['response']['result'] ?? null)) {
            $appealRow = mcPresenceMergeTodayAbsencesIntoAppealRow($appealRow, $absencesResult['response']['result']);
            $appealRow = mcPresenceNormalizeAppealRow($appealRow);
        }
    }
    $state = classifyAppealState($appealRow);

    if ($state['status'] === 'presente_in_classe' || $state['status'] === 'presente_entrato_in_ritardo') {
        $summary['presenti_in_classe']++;
    } elseif ($state['status'] === 'evento') {
        $summary['presenti_a_scuola_ma_fuori_classe']++;
    } elseif ($state['status'] === 'assente') {
        $summary['assenti']++;
    } elseif ($state['status'] === 'uscita' || $state['status'] === 'permesso') {
        $summary['usciti_o_permesso']++;
    } else {
        $summary['senza_lezione']++;
    }

    $records[] = [
        'id_studente' => $studentId,
        'cognome' => $student['cognome'] ?? '',
        'nome' => $student['nome'] ?? '',
        'registro' => $student['registro'] ?? null,
        'status' => $state['status'],
        'present_at_school' => $state['present_at_school'],
        'present_in_class' => $state['present_in_class'],
        'ha_lezione' => $state['ha_lezione'],
        'appeal' => $appealRow,
    ];
}

echo json_encode([
    'ok' => true,
    'class_id' => $classId,
    'summary' => $summary,
    'records' => $records,
    'debug' => $appealResult['response']['debug_code'] ?? null,
    'note' => 'Questo snapshot usa get_appeal_data, quindi rappresenta la situazione corrente e non uno storico arbitrario.',
], JSON_UNESCAPED_UNICODE);

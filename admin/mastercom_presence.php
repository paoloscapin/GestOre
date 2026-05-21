<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

function mastercomPresenceCurrentTs(): int
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->getTimestamp();
}

function mastercomPresenceDayBoundaryTs(string $time): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', mastercomPresenceCurrentDate() . ' ' . $time, new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomPresenceFormatTs(int $timestamp, string $format = 'Y-m-d'): string
{
    if ($timestamp <= 0) {
        return '';
    }

    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format($format);
}

function mastercomPresenceCurrentDate(): string
{
    return mastercomPresenceFormatTs(mastercomPresenceCurrentTs(), 'Y-m-d');
}

function mastercomPresenceExtractEntryTimestamps(array $entry): array
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

function mastercomPresenceEntryIsCurrent(array $entry, bool $strictDate = false): bool
{
    $timestamps = mastercomPresenceExtractEntryTimestamps($entry);
    if (empty($timestamps)) {
        return !$strictDate;
    }

    $today = mastercomPresenceCurrentDate();
    foreach ($timestamps as $timestamp) {
        if (mastercomPresenceFormatTs($timestamp, 'Y-m-d') === $today) {
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

    $nowTs = mastercomPresenceCurrentTs();
    if ($startTs !== null && $endTs !== null) {
        return $startTs <= $nowTs && $endTs >= $nowTs;
    }

    return false;
}

function mastercomPresenceNormalizeEntries(array $entries, bool $strictDate = false): array
{
    $normalized = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        if (!mastercomPresenceEntryIsCurrent($entry, $strictDate)) {
            continue;
        }

        $normalized[] = $entry;
    }

    return $normalized;
}

function mastercomPresenceNormalizeTime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^0?(\d{1,2}:\d{2})$/', $value, $matches)) {
        return $matches[1];
    }

    return ltrim($value, '0');
}

function mastercomPresenceMergeUniqueEntries(array $baseEntries, array $extraEntries): array
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

function mastercomPresenceAbsenceRecordKind(array $record): string
{
    $code = strtoupper(trim((string)($record['codice'] ?? '')));
    $tipo = intval($record['tipo'] ?? 0);

    if ($code === 'E' || $tipo === 2) {
        return 'entrata';
    }
    if ($code === 'U' || $tipo === 3) {
        return 'uscita';
    }
    if ($code === 'P') {
        return 'permesso';
    }

    $text = mastercomAdminNorm(implode(' ', [
        (string)($record['descrizione'] ?? ''),
        (string)($record['nome'] ?? ''),
        (string)($record['titolo'] ?? ''),
        (string)($record['testo'] ?? ''),
    ]));
    if (strpos($text, 'USCITA') !== false) {
        return 'uscita';
    }
    if (strpos($text, 'ENTRATA') !== false || strpos($text, 'RITARDO') !== false) {
        return 'entrata';
    }

    return 'assenza';
}

function mastercomPresenceMergeTodayAbsencesIntoAppealRow(array $appealRow, array $absenceRecords): array
{
    $extraAssenze = [];
    $extraEntrate = [];
    $extraUscite = [];
    $extraPermessi = [];

    foreach ($absenceRecords as $record) {
        if (!is_array($record)) {
            continue;
        }

        $kind = mastercomPresenceAbsenceRecordKind($record);
        if ($kind === 'entrata') {
            $extraEntrate[] = $record;
        } elseif ($kind === 'uscita') {
            $extraUscite[] = $record;
        } elseif ($kind === 'permesso') {
            $extraPermessi[] = $record;
        } else {
            $extraAssenze[] = $record;
        }
    }

    $appealRow['assenze'] = mastercomPresenceMergeUniqueEntries(is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : [], $extraAssenze);
    $appealRow['entrate'] = mastercomPresenceMergeUniqueEntries(is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : [], $extraEntrate);
    $appealRow['uscite'] = mastercomPresenceMergeUniqueEntries(is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : [], $extraUscite);
    $appealRow['permessi'] = mastercomPresenceMergeUniqueEntries(is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : [], $extraPermessi);

    return $appealRow;
}

function mastercomPresenceRedistributeAbsenceEntries(array $appealRow): array
{
    $assenze = is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : [];
    $realAssenze = [];
    $extraEntrate = [];
    $extraUscite = [];
    $extraPermessi = [];

    foreach ($assenze as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $kind = mastercomPresenceAbsenceRecordKind($entry);
        if ($kind === 'entrata') {
            $extraEntrate[] = $entry;
        } elseif ($kind === 'uscita') {
            $extraUscite[] = $entry;
        } elseif ($kind === 'permesso') {
            $extraPermessi[] = $entry;
        } else {
            $realAssenze[] = $entry;
        }
    }

    $appealRow['assenze'] = $realAssenze;
    $appealRow['entrate'] = mastercomPresenceMergeUniqueEntries(is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : [], $extraEntrate);
    $appealRow['uscite'] = mastercomPresenceMergeUniqueEntries(is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : [], $extraUscite);
    $appealRow['permessi'] = mastercomPresenceMergeUniqueEntries(is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : [], $extraPermessi);

    return $appealRow;
}

function mastercomPresenceWeekRangeTs(): array
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

function mastercomPresenceBuildActiveCalendarEventsMap(array $calendarResponse): array
{
    $map = [];
    $debugCode = $calendarResponse['debug_code'] ?? [];
    if (!is_array($debugCode)) {
        return $map;
    }

    $nowTs = mastercomPresenceCurrentTs();
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
                    'titolo' => mastercomAdminCleanText($event['nome'] ?? ''),
                    'descrizione' => mastercomAdminCleanText($event['descrizione'] ?? ''),
                    'data_inizio_ts' => $startTs,
                    'data_fine_ts' => $endTs,
                    'partecipante' => mastercomAdminCleanText((string)$studentName),
                ];
            }
        }
    }

    return $map;
}

function mastercomPresenceNormalizeAppealRow(array $appealRow): array
{
    $appealRow['entrate'] = mastercomPresenceNormalizeEntries(is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : [], true);
    $appealRow['uscite'] = mastercomPresenceNormalizeEntries(is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : [], true);
    $appealRow['permessi'] = mastercomPresenceNormalizeEntries(is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : [], true);
    $appealRow['eventi'] = mastercomPresenceNormalizeEntries(is_array($appealRow['eventi'] ?? null) ? $appealRow['eventi'] : [], true);
    $appealRow['assenze'] = mastercomPresenceNormalizeEntries(is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : [], true);

    $stato = is_array($appealRow['stato'] ?? null) ? $appealRow['stato'] : [];
    if (!empty($stato['assente']) && is_array($stato['ultimo'] ?? null)) {
        $lastKind = mastercomPresenceAbsenceRecordKind($stato['ultimo']);
        if ($lastKind === 'entrata') {
            $appealRow['entrate'] = mastercomPresenceMergeUniqueEntries($appealRow['entrate'], [$stato['ultimo']]);
        } elseif ($lastKind === 'uscita') {
            $appealRow['uscite'] = mastercomPresenceMergeUniqueEntries($appealRow['uscite'], [$stato['ultimo']]);
        } elseif ($lastKind === 'permesso') {
            $appealRow['permessi'] = mastercomPresenceMergeUniqueEntries($appealRow['permessi'], [$stato['ultimo']]);
        } else {
            $appealRow['assenze'] = mastercomPresenceMergeUniqueEntries($appealRow['assenze'], [$stato['ultimo']]);
        }
    }

    $appealRow = mastercomPresenceRedistributeAbsenceEntries($appealRow);

    if (!empty($appealRow['entrate']) || !empty($appealRow['eventi'])) {
        $appealRow['assenze'] = [];
    }

    return $appealRow;
}

function mastercomPresenceClassifyAppealState(array $appealRow): array
{
    $assenze = is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : [];
    $entrate = is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : [];
    $uscite = is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : [];
    $permessi = is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : [];
    $eventi = is_array($appealRow['eventi'] ?? null) ? $appealRow['eventi'] : [];
    $stato = is_array($appealRow['stato'] ?? null) ? $appealRow['stato'] : [];
    $isMarkedAbsent = !empty($stato['assente']);
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
    } elseif ($isMarkedAbsent || !empty($assenze)) {
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

function mastercomPresenceStatusMeta(string $status): array
{
    $map = [
        'presente_in_classe' => ['label' => 'Presente in classe', 'class' => 'success'],
        'presente_entrato_in_ritardo' => ['label' => 'Presente, entrato in ritardo', 'class' => 'info'],
        'assente' => ['label' => 'Assente', 'class' => 'danger'],
        'uscita' => ['label' => 'Uscito', 'class' => 'warning'],
        'permesso' => ['label' => 'Permesso', 'class' => 'warning'],
        'evento' => ['label' => 'Evento / fuori classe', 'class' => 'primary'],
        'nessuna_lezione' => ['label' => 'Nessuna lezione', 'class' => 'default'],
    ];

    return $map[$status] ?? ['label' => $status, 'class' => 'default'];
}

function mastercomPresenceDescribeEntries(array $entries): string
{
    if (empty($entries)) {
        return '';
    }

    $parts = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $fragments = [];
        $eventName = mastercomAdminCleanText(
            $entry['titolo']
            ?? $entry['title']
            ?? $entry['nome_evento']
            ?? $entry['nome']
            ?? $entry['testo']
            ?? ''
        );
        $descrizione = mastercomAdminCleanText($entry['descrizione'] ?? '');
        $orario = mastercomAdminCleanText($entry['orario'] ?? '');
        $codice = mastercomAdminCleanText($entry['codice'] ?? '');

        if ($eventName !== null && $eventName !== '') {
            $fragments[] = $eventName;
        } elseif ($descrizione !== null && $descrizione !== '') {
            $fragments[] = $descrizione;
        } elseif ($codice !== null && $codice !== '') {
            $fragments[] = $codice;
        }

        $alreadyHasTime = false;
        if ($orario !== null && $orario !== '') {
            foreach ($fragments as $fragment) {
                if (strpos((string)$fragment, $orario) !== false) {
                    $alreadyHasTime = true;
                    break;
                }
            }
        }

        $normalizedTime = mastercomPresenceNormalizeTime($orario);
        if ($normalizedTime !== '') {
            foreach ($fragments as $fragment) {
                if (mastercomPresenceNormalizeTime((string)$fragment) === $normalizedTime || strpos(mastercomPresenceNormalizeTime((string)$fragment), $normalizedTime) !== false) {
                    $alreadyHasTime = true;
                    break;
                }
            }
        }

        if (!$alreadyHasTime && $orario !== null && $orario !== '') {
            $fragments[] = $orario;
        }

        if (!empty($fragments)) {
            $parts[] = implode(' - ', $fragments);
        }
    }

    return implode(' | ', $parts);
}

function mastercomPresenceActionableEntries(array $entries): array
{
    $items = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $absenceId = intval($entry['id_assenza'] ?? 0);
        $absenceDate = intval($entry['data'] ?? 0);
        if ($absenceId <= 0 || $absenceDate <= 0) {
            continue;
        }
        $items[] = [
            'id_assenza' => $absenceId,
            'data_assenza' => $absenceDate,
            'tipo_assenza' => intval($entry['tipo'] ?? 0),
            'orario' => mastercomAdminCleanText($entry['orario'] ?? ''),
            'label' => mastercomPresenceDescribeEntries([$entry]),
        ];
    }
    return $items;
}

$missingTables = mastercomAdminMissingTables(['mastercom_classi']);
$selectedClassId = intval($_GET['class_id'] ?? 0);
$classRows = empty($missingTables)
    ? mastercomAdminOperationalClassRows('mastercom_id_classe, nome')
    : [];
if ($selectedClassId > 0 && !mastercomAdminIsOperationalClassId($selectedClassId)) {
    $selectedClassId = 0;
}

$summary = null;
$records = [];
$errorMessage = '';
$selectedClassName = '';
$studentPhotoMap = [];

if (empty($missingTables) && $selectedClassId > 0) {
    $selectedClassName = trim((string)(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . $selectedClassId . " LIMIT 1") ?? ''));

    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComDocenteAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);

    if (!$authResult['ok']) {
        $errorMessage = 'Autenticazione MasterCom docente fallita';
    } else {
        $studentsResult = mastercomLoadStudentsList($authResult, $selectedClassId, [
            'method' => 'POST',
            'timeout' => 120,
        ]);
        $appealResult = mastercomLoadAppealData($authResult, $selectedClassId, [
            'method' => 'POST',
            'timeout' => 120,
        ]);

        if (!$studentsResult['ok'] || !$appealResult['ok']) {
            $errorMessage = 'Caricamento dati presenza MasterCom fallito';
        } else {
            $students = is_array($studentsResult['response']['result'] ?? null) ? $studentsResult['response']['result'] : [];
            $appeal = is_array($appealResult['response']['result'] ?? null) ? $appealResult['response']['result'] : [];
            $todayStartTs = mastercomPresenceDayBoundaryTs('00:00:00');
            $todayEndTs = mastercomPresenceDayBoundaryTs('23:59:59');
            $weekRange = mastercomPresenceWeekRangeTs();
            $calendarResult = mastercomLoadCalendarNotes($authResult, $selectedClassId, $weekRange['start'], $weekRange['end'], [
                'method' => 'POST',
                'timeout' => 120,
            ]);
            $calendarEventsMap = ($calendarResult['ok'] && is_array($calendarResult['response'] ?? null))
                ? mastercomPresenceBuildActiveCalendarEventsMap($calendarResult['response'])
                : [];
            $photoRows = dbGetAll("
                SELECT mastercom_id_studente, foto
                FROM mastercom_studenti
                WHERE mastercom_id_classe_corrente = " . intval($selectedClassId) . "
            ");
            foreach ($photoRows as $photoRow) {
                $studentPhotoMap[intval($photoRow['mastercom_id_studente'] ?? 0)] = trim((string)($photoRow['foto'] ?? ''));
            }

            $summary = [
                'presenti_in_classe' => 0,
                'presenti_a_scuola_ma_fuori_classe' => 0,
                'assenti' => 0,
                'usciti_o_permesso' => 0,
                'senza_lezione' => 0,
            ];

            foreach ($students as $student) {
                $studentId = intval($student['id_studente'] ?? 0);
                $appealRow = $appeal[(string)$studentId] ?? [];
                $appealRow = mastercomPresenceNormalizeAppealRow(is_array($appealRow) ? $appealRow : []);
                if ($studentId > 0 && !empty($calendarEventsMap[$studentId])) {
                    $appealRow['eventi'] = mastercomPresenceMergeUniqueEntries(is_array($appealRow['eventi'] ?? null) ? $appealRow['eventi'] : [], $calendarEventsMap[$studentId]);
                }

                if ($studentId > 0 && $todayStartTs > 0 && $todayEndTs > 0) {
                    $absencesResult = mastercomLoadAbsencesData($authResult, $studentId, $todayStartTs, $todayEndTs, [
                        'method' => 'POST',
                        'timeout' => 120,
                    ]);
                    if ($absencesResult['ok'] && is_array($absencesResult['response']['result'] ?? null)) {
                        $appealRow = mastercomPresenceMergeTodayAbsencesIntoAppealRow($appealRow, $absencesResult['response']['result']);
                        $appealRow = mastercomPresenceNormalizeAppealRow($appealRow);
                    }
                }

                $state = mastercomPresenceClassifyAppealState($appealRow);

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
                    'registro' => $student['registro'] ?? null,
                    'cognome' => $student['cognome'] ?? '',
                    'nome' => $student['nome'] ?? '',
                    'status' => $state['status'],
                    'meta' => mastercomPresenceStatusMeta($state['status']),
                    'details' => [
                        'assenze' => mastercomPresenceDescribeEntries(is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : []),
                        'entrate' => mastercomPresenceDescribeEntries(is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : []),
                        'uscite' => mastercomPresenceDescribeEntries(is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : []),
                        'permessi' => mastercomPresenceDescribeEntries(is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : []),
                        'eventi' => mastercomPresenceDescribeEntries(is_array($appealRow['eventi'] ?? null) ? $appealRow['eventi'] : []),
                    ],
                    'detail_records' => [
                        'assenze' => mastercomPresenceActionableEntries(is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : []),
                        'entrate' => mastercomPresenceActionableEntries(is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : []),
                        'uscite' => mastercomPresenceActionableEntries(is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : []),
                        'permessi' => mastercomPresenceActionableEntries(is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : []),
                    ],
                    'ha_lezione' => $state['ha_lezione'],
                    'present_at_school' => $state['present_at_school'],
                    'present_in_class' => $state['present_in_class'],
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Presence Snapshot</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-ok-circle"></span>&emsp;Presence Snapshot MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Mancano tabelle: <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.</div>
            <?php else: ?>
                <form method="get" action="mastercom_presence.php" class="form-inline" style="margin-bottom: 15px;" id="mc-presence-form">
                    <div class="form-group">
                        <label for="class_id">Classe&nbsp;</label>
                        <select name="class_id" id="class_id" class="form-control" onchange="mastercomPresenceShowLoading(); this.form.submit();">
                            <option value="0">Seleziona una classe</option>
                            <?php foreach ($classRows as $classRow): ?>
                                <option value="<?php echo intval($classRow['mastercom_id_classe']); ?>" <?php echo $selectedClassId === intval($classRow['mastercom_id_classe']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($classRow['nome'] ?? '') . ' [' . ($classRow['mastercom_id_classe'] ?? '') . ']'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <a href="mastercom_presence.php" class="btn btn-default">Reset</a>
                </form>

                <?php if ($selectedClassId <= 0): ?>
                    <div class="alert alert-info">Seleziona una classe per vedere la situazione attuale di presenze, assenze, uscite, permessi ed eventi.</div>
                <?php elseif ($errorMessage !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Snapshot corrente per la classe <strong><?php echo htmlspecialchars($selectedClassName !== '' ? $selectedClassName : (string)$selectedClassId); ?></strong>.
                        Questo dato arriva da MasterCom e rappresenta la situazione attuale, non uno storico.
                    </div>

                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-2">
                            <div class="well text-center">
                                <strong><?php echo intval($summary['presenti_in_classe'] ?? 0); ?></strong><br>
                                Presenti in classe
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="well text-center">
                                <strong><?php echo intval($summary['presenti_a_scuola_ma_fuori_classe'] ?? 0); ?></strong><br>
                                Fuori classe / evento
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="well text-center">
                                <strong><?php echo intval($summary['assenti'] ?? 0); ?></strong><br>
                                Assenti
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="well text-center">
                                <strong><?php echo intval($summary['usciti_o_permesso'] ?? 0); ?></strong><br>
                                Usciti / permesso
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="well text-center">
                                <strong><?php echo intval($summary['senza_lezione'] ?? 0); ?></strong><br>
                                Nessuna lezione
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="well text-center">
                                <strong><?php echo count($records); ?></strong><br>
                                Totale studenti
                            </div>
                        </div>
                    </div>

                    <table class="table table-striped table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Registro</th>
                                <th style="width: 70px;">Foto</th>
                                <th>Studente</th>
                                <th>Stato</th>
                                <th>Dettagli</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td style="white-space: nowrap; text-align: center;"><?php echo htmlspecialchars((string)($record['registro'] ?? '')); ?></td>
                                    <td style="text-align: center;">
                                        <?php
                                        $photoFile = trim((string)($studentPhotoMap[intval($record['id_studente'])] ?? ''));
                                        $photoUrl = $photoFile !== ''
                                            ? ($__application_base_path . '/common/mastercom/photo.php?proxy=1&file=' . urlencode($photoFile))
                                            : '';
                                        ?>
                                        <?php if ($photoUrl !== ''): ?>
                                            <img
                                                src="<?php echo htmlspecialchars($photoUrl); ?>"
                                                alt="Foto studente"
                                                style="width: 42px; height: 42px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc;"
                                                loading="lazy"
                                            >
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(trim(($record['cognome'] ?? '') . ' ' . ($record['nome'] ?? ''))); ?>
                                    </td>
                                    <td>
                                        <span class="label label-<?php echo htmlspecialchars($record['meta']['class'] ?? 'default'); ?>">
                                            <?php echo htmlspecialchars($record['meta']['label'] ?? ($record['status'] ?? '')); ?>
                                        </span>
                                        <?php if (!empty($record['ha_lezione'])): ?>
                                            <br><small>ha lezione</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($record['details']['assenze'])): ?>
                                            <div><strong>Assenze:</strong> <?php echo htmlspecialchars($record['details']['assenze']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['details']['entrate'])): ?>
                                            <div><strong>Entrate:</strong> <?php echo htmlspecialchars($record['details']['entrate']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['details']['uscite'])): ?>
                                            <div><strong>Uscite:</strong> <?php echo htmlspecialchars($record['details']['uscite']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['details']['permessi'])): ?>
                                            <div><strong>Permessi:</strong> <?php echo htmlspecialchars($record['details']['permessi']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['details']['eventi'])): ?>
                                            <div><strong>Eventi:</strong> <?php echo htmlspecialchars($record['details']['eventi']); ?></div>
                                        <?php endif; ?>
                                        <?php foreach (['assenze' => 'Assenza', 'entrate' => 'Entrata', 'uscite' => 'Uscita', 'permessi' => 'Permesso'] as $detailKey => $detailLabel): ?>
                                            <?php foreach (($record['detail_records'][$detailKey] ?? []) as $detailRecord): ?>
                                                <?php
                                                $editType = intval($detailRecord['tipo_assenza'] ?? 0);
                                                if ($editType <= 0) {
                                                    $editType = $detailKey === 'entrate' ? 2 : ($detailKey === 'uscite' ? 3 : 1);
                                                }
                                                $editParams = [
                                                    'student_id' => intval($record['id_studente']),
                                                    'class_id' => intval($selectedClassId),
                                                    'classe' => $selectedClassName !== '' ? $selectedClassName : (string)$selectedClassId,
                                                    'cognome' => (string)($record['cognome'] ?? ''),
                                                    'nome' => (string)($record['nome'] ?? ''),
                                                    'id_assenza' => intval($detailRecord['id_assenza']),
                                                    'data_assenza' => intval($detailRecord['data_assenza']),
                                                    'tipo_assenza' => $editType,
                                                ];
                                                $editTime = trim((string)($detailRecord['orario'] ?? ''));
                                                if (preg_match('/^\d{1,2}:\d{2}$/', $editTime)) {
                                                    $editParams['absence_time'] = $editTime;
                                                }
                                                ?>
                                                <a class="btn btn-xs btn-primary" style="margin: 3px 4px 0 0;" href="mastercom_absence_edit.php?<?php echo htmlspecialchars(http_build_query($editParams)); ?>" title="<?php echo htmlspecialchars($detailRecord['label']); ?>">
                                                    Modifica <?php echo htmlspecialchars($detailLabel); ?>
                                                </a>
                                                <form method="post" action="mastercom_absence_delete.php" style="display:inline-block; margin: 3px 4px 0 0;" onsubmit="return confirm('Confermi eliminazione da MasterCom?');">
                                                    <input type="hidden" name="student_id" value="<?php echo intval($record['id_studente']); ?>">
                                                    <input type="hidden" name="class_id" value="<?php echo intval($selectedClassId); ?>">
                                                    <input type="hidden" name="classe" value="<?php echo htmlspecialchars($selectedClassName !== '' ? $selectedClassName : (string)$selectedClassId); ?>">
                                                    <input type="hidden" name="cognome" value="<?php echo htmlspecialchars((string)($record['cognome'] ?? '')); ?>">
                                                    <input type="hidden" name="nome" value="<?php echo htmlspecialchars((string)($record['nome'] ?? '')); ?>">
                                                    <input type="hidden" name="id_assenza" value="<?php echo intval($detailRecord['id_assenza']); ?>">
                                                    <input type="hidden" name="data_assenza" value="<?php echo intval($detailRecord['data_assenza']); ?>">
                                                    <button type="submit" class="btn btn-xs btn-danger" title="<?php echo htmlspecialchars($detailRecord['label']); ?>">
                                                        Elimina <?php echo htmlspecialchars($detailLabel); ?>
                                                    </button>
                                                </form>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                        <?php if (
                                            empty($record['details']['assenze']) &&
                                            empty($record['details']['entrate']) &&
                                            empty($record['details']['uscite']) &&
                                            empty($record['details']['permessi']) &&
                                            empty($record['details']['eventi'])
                                        ): ?>
                                            <span class="text-muted">nessun dettaglio aggiuntivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="btn btn-xs btn-info" href="mastercom_student_absences.php?student_id=<?php echo intval($record['id_studente']); ?>">
                                            Storico assenze
                                        </a>
                                        <a class="btn btn-xs btn-warning" href="mastercom_absence_create.php?student_id=<?php echo intval($record['id_studente']); ?>&class_id=<?php echo intval($selectedClassId); ?>&cognome=<?php echo urlencode((string)($record['cognome'] ?? '')); ?>&nome=<?php echo urlencode((string)($record['nome'] ?? '')); ?>&classe=<?php echo urlencode($selectedClassName !== '' ? $selectedClassName : (string)$selectedClassId); ?>" style="margin-top: 4px;">
                                            Nuova assenza
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<div id="mc-presence-loading" style="display:none; position:fixed; inset:0; background:rgba(255,255,255,0.75); z-index:9999;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); text-align:center; min-width:260px; padding:24px 28px; background:#fff; border:1px solid #cfd8dc; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.15);">
        <div class="glyphicon glyphicon-hourglass" style="font-size:32px; color:#0f6b78; margin-bottom:12px;"></div>
        <div style="font-size:18px; font-weight:600; margin-bottom:6px;">Caricamento snapshot</div>
        <div style="color:#666;">Sto leggendo i dati da MasterCom, attendi qualche secondo.</div>
    </div>
</div>
<script>
function mastercomPresenceShowLoading() {
    var overlay = document.getElementById('mc-presence-loading');
    if (overlay) {
        overlay.style.display = 'block';
    }
}

(function () {
    var form = document.getElementById('mc-presence-form');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function () {
        mastercomPresenceShowLoading();
    });
})();
</script>
</body>
</html>

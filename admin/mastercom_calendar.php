<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mastercomCalendarRomeToday(string $format = 'Y-m-d'): string
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format($format);
}

function mastercomCalendarFormatTs(int $timestamp, string $format = 'd/m/Y H:i'): string
{
    if ($timestamp <= 0) {
        return '';
    }

    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format($format);
}

function mastercomCalendarDayStartTs(string $date): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', trim($date) . ' 00:00:00', new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomCalendarDayEndTs(string $date): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', trim($date) . ' 23:59:59', new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomCalendarCurrentWeekDates(): array
{
    $today = new DateTime('now', new DateTimeZone('Europe/Rome'));
    $start = clone $today;
    $start->modify('monday this week');
    $end = clone $start;
    $end->modify('+4 days');

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
    ];
}

function mastercomCalendarWeekDays(string $startDate, string $endDate): array
{
    $start = DateTime::createFromFormat('Y-m-d H:i:s', trim($startDate) . ' 00:00:00', new DateTimeZone('Europe/Rome'));
    $end = DateTime::createFromFormat('Y-m-d H:i:s', trim($endDate) . ' 00:00:00', new DateTimeZone('Europe/Rome'));
    if (!$start instanceof DateTime || !$end instanceof DateTime || $end < $start) {
        return [];
    }

    $days = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        if (intval($cursor->format('N')) > 5) {
            $cursor->modify('+1 day');
            continue;
        }
        $days[] = [
            'date' => $cursor->format('Y-m-d'),
            'label' => mastercomCalendarItalianDayLabel($cursor),
            'start_ts' => (clone $cursor)->setTime(0, 0, 0)->getTimestamp(),
            'end_ts' => (clone $cursor)->setTime(23, 59, 59)->getTimestamp(),
        ];
        $cursor->modify('+1 day');
    }

    return $days;
}

function mastercomCalendarItalianDayLabel(DateTime $date): string
{
    $days = [
        1 => 'Lunedi',
        2 => 'Martedi',
        3 => 'Mercoledi',
        4 => 'Giovedi',
        5 => 'Venerdi',
        6 => 'Sabato',
        7 => 'Domenica',
    ];

    return ($days[intval($date->format('N'))] ?? $date->format('D')) . ' ' . $date->format('d/m');
}

function mastercomCalendarEventTimeLabelForDay(array $row, array $day): string
{
    $startTs = intval($row['data_inizio'] ?? 0);
    $endTs = intval($row['data_fine'] ?? 0);
    if ($startTs <= 0 && $endTs <= 0) {
        return '';
    }

    $startsBeforeDay = $startTs > 0 && $startTs < intval($day['start_ts']);
    $endsAfterDay = $endTs > 0 && $endTs > intval($day['end_ts']);
    $startLabel = $startsBeforeDay ? 'inizio giornata' : mastercomCalendarFormatTs($startTs, 'H:i');
    $endLabel = $endsAfterDay ? 'fine giornata' : mastercomCalendarFormatTs($endTs, 'H:i');

    if ($startLabel === '' && $endLabel === '') {
        return '';
    }
    if ($endLabel === '' || $startLabel === $endLabel) {
        return $startLabel;
    }

    return $startLabel . '-' . $endLabel;
}

function mastercomCalendarTypeClass(array $row): string
{
    if (!empty($row['evento'])) {
        return 'agenda-week-event-evento';
    }
    if (!empty($row['colloquio'])) {
        return 'agenda-week-event-colloquio';
    }

    return 'agenda-week-event-nota';
}

function mastercomCalendarDisplayTitle($title, $detail): string
{
    $cleanTitle = trim((string)(mastercomAdminCleanText($title) ?? ''));
    $cleanDetail = trim((string)(mastercomAdminCleanText($detail) ?? ''));
    $normalizedTitle = mb_strtolower($cleanTitle, 'UTF-8');

    if ($cleanTitle === '' || $normalizedTitle === '(senza titolo)' || $normalizedTitle === 'senza titolo') {
        return $cleanDetail !== '' ? $cleanDetail : '(senza titolo)';
    }

    return $cleanTitle;
}

function mastercomCalendarPickAuthor(array $note, array $eventDebug): ?string
{
    foreach ([
        $note['autore'] ?? null,
        $note['docente'] ?? null,
        $note['professore'] ?? null,
        $eventDebug['autore'] ?? null,
        $eventDebug['docente'] ?? null,
        $eventDebug['professore'] ?? null,
        $eventDebug['nome_docente'] ?? null,
        $eventDebug['docente_nome'] ?? null,
    ] as $value) {
        $clean = mastercomAdminCleanText($value ?? '');
        if ($clean !== null && $clean !== '') {
            return $clean;
        }
    }

    $nome = mastercomAdminCleanText($eventDebug['nome_professore'] ?? $note['nome_professore'] ?? '');
    $cognome = mastercomAdminCleanText($eventDebug['cognome_professore'] ?? $note['cognome_professore'] ?? '');
    $fullName = trim((string)$nome . ' ' . (string)$cognome);
    return $fullName !== '' ? $fullName : null;
}

function mastercomCalendarBuildDebugMap(array $response): array
{
    $map = [];
    $debug = $response['debug_code'] ?? [];
    if (!is_array($debug)) {
        return $map;
    }

    foreach ($debug as $group) {
        if (!is_array($group)) {
            continue;
        }
        foreach ($group as $eventId => $eventData) {
            $map[intval($eventId)] = is_array($eventData) ? $eventData : [];
        }
    }

    return $map;
}

$missingTables = mastercomAdminMissingTables(['mastercom_classi']);
$classRows = empty($missingTables) ? mastercomAdminOperationalClassRows('mastercom_id_classe, nome') : [];
$selectedClassId = intval($_GET['class_id'] ?? 0);
if ($selectedClassId > 0 && !mastercomAdminIsOperationalClassId($selectedClassId)) {
    $selectedClassId = 0;
}
$weekDates = mastercomCalendarCurrentWeekDates();
$startDate = trim((string)($_GET['start_date'] ?? $weekDates['start']));
$endDate = trim((string)($_GET['end_date'] ?? $weekDates['end']));
$errorMessage = '';
$rows = [];
$weekDays = mastercomCalendarWeekDays($startDate, $endDate);
$selectedClassName = '';

if (empty($missingTables) && $selectedClassId > 0) {
    $startTs = mastercomCalendarDayStartTs($startDate);
    $endTs = mastercomCalendarDayEndTs($endDate);
    $selectedClassName = trim((string)(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . $selectedClassId . " LIMIT 1") ?? ''));

    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        $errorMessage = 'Intervallo date non valido';
    } else {
        $authResult = mastercomAuthenticateService([
            'profile' => 'MasterComDocenteAuth',
            'method' => 'POST',
            'timeout' => 60,
        ]);

        if (!$authResult['ok']) {
            $errorMessage = 'Autenticazione MasterCom docente fallita';
        } else {
            $calendarResult = mastercomLoadCalendarNotes($authResult, $selectedClassId, $startTs, $endTs, [
                'method' => 'POST',
                'timeout' => 120,
            ]);

            if (!$calendarResult['ok'] || !is_array($calendarResult['response'] ?? null)) {
                $errorMessage = 'Caricamento agenda classe MasterCom fallito';
            } else {
                $debugMap = mastercomCalendarBuildDebugMap($calendarResult['response']);
                $notes = is_array($calendarResult['response']['result'] ?? null) ? $calendarResult['response']['result'] : [];

                foreach ($notes as $note) {
                    if (!is_array($note)) {
                        continue;
                    }

                    $noteId = intval($note['id_annotazione_agenda'] ?? 0);
                    $eventDebug = $debugMap[$noteId] ?? [];
                    $participants = is_array($eventDebug['partecipanti'] ?? null) ? $eventDebug['partecipanti'] : [];
                    $participantsList = [];
                    foreach ($participants as $name) {
                        $clean = mastercomAdminCleanText((string)$name);
                        if ($clean !== null && $clean !== '') {
                            $participantsList[] = $clean;
                        }
                    }

                    $text = mastercomAdminCleanText($note['testo'] ?? '') ?: mastercomAdminCleanText($eventDebug['descrizione'] ?? '');
                    $title = mastercomCalendarDisplayTitle(
                        mastercomAdminCleanText($note['titolo'] ?? '') ?: mastercomAdminCleanText($eventDebug['nome'] ?? ''),
                        $text
                    );

                    $rows[] = [
                        'id' => $noteId,
                        'titolo' => $title,
                        'testo' => $text,
                        'data_inizio' => intval($note['data_inizio'] ?? $eventDebug['data_inizio'] ?? 0),
                        'data_fine' => intval($note['data_fine'] ?? $eventDebug['data_fine'] ?? 0),
                        'autore' => mastercomCalendarPickAuthor($note, $eventDebug),
                        'evento' => intval($note['evento'] ?? 0) === 1,
                        'colloquio' => intval($note['colloquio'] ?? 0) === 1,
                        'participants_count' => count($participantsList),
                        'participants' => $participantsList,
                    ];
                }
                usort($rows, function (array $a, array $b): int {
                    return intval($a['data_inizio'] ?? 0) <=> intval($b['data_inizio'] ?? 0);
                });
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Agenda Classe</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .agenda-week-wrap {
            margin-bottom: 18px;
            overflow-x: auto;
        }
        .agenda-week-table {
            table-layout: fixed;
            min-width: 900px;
            width: 100%;
        }
        .agenda-week-table th {
            background: #eef7f7;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }
        .agenda-week-table td {
            background: #fbfefe;
            height: 170px;
            min-width: 140px;
            vertical-align: top;
        }
        .agenda-week-event {
            border-radius: 5px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
            color: #1f2933;
            display: block;
            font-size: 12px;
            line-height: 1.25;
            margin-bottom: 6px;
            padding: 6px 7px;
        }
        .agenda-week-event-evento {
            background: #d9edf7;
            border-left: 5px solid #31708f;
        }
        .agenda-week-event-colloquio {
            background: #fcf8e3;
            border-left: 5px solid #8a6d3b;
        }
        .agenda-week-event-nota {
            background: #eeeeee;
            border-left: 5px solid #777777;
        }
        .agenda-week-event-title {
            font-weight: 700;
            margin-bottom: 3px;
        }
        .agenda-week-event-meta {
            color: #52616b;
            font-size: 11px;
        }
        .agenda-week-empty {
            color: #9aa5b1;
            font-size: 12px;
            padding: 6px;
            text-align: center;
        }
    </style>
</head>
<body>
<?php require_once headerAdminDidatticaPath('../common'); ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-calendar"></span>&emsp;Agenda Classe MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Mancano tabelle: <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.</div>
            <?php else: ?>
                <form method="get" action="mastercom_calendar.php" class="form-inline" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="class_id">Classe&nbsp;</label>
                        <select name="class_id" id="class_id" class="form-control">
                            <option value="0">Seleziona una classe</option>
                            <?php foreach ($classRows as $classRow): ?>
                                <option value="<?php echo intval($classRow['mastercom_id_classe']); ?>" <?php echo $selectedClassId === intval($classRow['mastercom_id_classe']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($classRow['nome'] ?? '') . ' [' . ($classRow['mastercom_id_classe'] ?? '') . ']'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-left: 10px;">
                        <label for="start_date">Dal&nbsp;</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="form-group" style="margin-left: 10px;">
                        <label for="end_date">Al&nbsp;</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Aggiorna</button>
                </form>

                <?php if ($selectedClassId <= 0): ?>
                    <div class="alert alert-info">Seleziona una classe per vedere gli eventi e le note in agenda.</div>
                <?php elseif ($errorMessage !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Classe <strong><?php echo htmlspecialchars($selectedClassName !== '' ? $selectedClassName : (string)$selectedClassId); ?></strong>.
                        Eventi/note trovati: <strong><?php echo count($rows); ?></strong>
                    </div>

                    <?php if (!empty($weekDays)): ?>
                        <h4>Calendario settimanale</h4>
                        <div class="agenda-week-wrap">
                            <table class="table table-bordered agenda-week-table">
                                <thead>
                                    <tr>
                                        <?php foreach ($weekDays as $day): ?>
                                            <th><?php echo htmlspecialchars($day['label']); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <?php foreach ($weekDays as $day): ?>
                                            <?php
                                            $dayEvents = [];
                                            foreach ($rows as $row) {
                                                $start = intval($row['data_inizio'] ?? 0);
                                                $end = intval($row['data_fine'] ?? 0);
                                                if ($start <= 0 && $end <= 0) {
                                                    continue;
                                                }
                                                if ($end <= 0) {
                                                    $end = $start;
                                                }
                                                if ($start <= intval($day['end_ts']) && $end >= intval($day['start_ts'])) {
                                                    $dayEvents[] = $row;
                                                }
                                            }
                                            ?>
                                            <td>
                                                <?php if (empty($dayEvents)): ?>
                                                    <div class="agenda-week-empty">Nessun evento</div>
                                                <?php else: ?>
                                                    <?php foreach ($dayEvents as $event): ?>
                                                        <div class="agenda-week-event <?php echo htmlspecialchars(mastercomCalendarTypeClass($event)); ?>">
                                                            <div class="agenda-week-event-title"><?php echo htmlspecialchars($event['titolo']); ?></div>
                                                            <div class="agenda-week-event-meta">
                                                                <?php echo htmlspecialchars(mastercomCalendarEventTimeLabelForDay($event, $day)); ?>
                                                                <?php if (!empty($event['participants_count'])): ?>
                                                                    &middot; <?php echo intval($event['participants_count']); ?> partecipanti
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!empty($event['autore'])): ?>
                                                                <div class="agenda-week-event-meta">
                                                                    Docente: <?php echo htmlspecialchars($event['autore']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <table class="table table-striped table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th style="text-align: center;">ID</th>
                                <th>Titolo</th>
                                <th style="text-align: center;">Inizio</th>
                                <th style="text-align: center;">Fine</th>
                                <th style="text-align: center;">Tipo</th>
                                <th style="text-align: center;">Partecipanti</th>
                                <th>Dettagli</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo intval($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['titolo']); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars(mastercomCalendarFormatTs($row['data_inizio'])); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars(mastercomCalendarFormatTs($row['data_fine'])); ?></td>
                                    <td style="text-align: center;">
                                        <?php if (!empty($row['evento'])): ?>
                                            <span class="label label-primary">Evento</span>
                                        <?php elseif (!empty($row['colloquio'])): ?>
                                            <span class="label label-info">Colloquio</span>
                                        <?php else: ?>
                                            <span class="label label-default">Nota</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($row['participants_count'] > 0): ?>
                                            <strong><?php echo $row['participants_count']; ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['autore'])): ?>
                                            <div><strong>Autore:</strong> <?php echo htmlspecialchars($row['autore']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['testo'])): ?>
                                            <div><?php echo nl2br(htmlspecialchars($row['testo'])); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['participants'])): ?>
                                            <div><strong>Partecipanti:</strong> <?php echo htmlspecialchars(implode('; ', $row['participants'])); ?></div>
                                        <?php endif; ?>
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
</body>
</html>

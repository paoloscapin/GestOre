<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

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
    $end->modify('+6 days');

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
    ];
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
$classRows = empty($missingTables) ? dbGetAll("SELECT mastercom_id_classe, nome FROM mastercom_classi ORDER BY nome ASC") : [];
$selectedClassId = intval($_GET['class_id'] ?? 0);
$weekDates = mastercomCalendarCurrentWeekDates();
$startDate = trim((string)($_GET['start_date'] ?? $weekDates['start']));
$endDate = trim((string)($_GET['end_date'] ?? $weekDates['end']));
$errorMessage = '';
$rows = [];
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

                    $title = mastercomAdminCleanText($note['titolo'] ?? '') ?: mastercomAdminCleanText($eventDebug['nome'] ?? '') ?: '(senza titolo)';
                    $text = mastercomAdminCleanText($note['testo'] ?? '') ?: mastercomAdminCleanText($eventDebug['descrizione'] ?? '');

                    $rows[] = [
                        'id' => $noteId,
                        'titolo' => $title,
                        'testo' => $text,
                        'data_inizio' => intval($note['data_inizio'] ?? $eventDebug['data_inizio'] ?? 0),
                        'data_fine' => intval($note['data_fine'] ?? $eventDebug['data_fine'] ?? 0),
                        'autore' => mastercomAdminCleanText($note['autore'] ?? ''),
                        'evento' => intval($note['evento'] ?? 0) === 1,
                        'colloquio' => intval($note['colloquio'] ?? 0) === 1,
                        'participants_count' => count($participantsList),
                        'participants' => $participantsList,
                    ];
                }
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
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
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

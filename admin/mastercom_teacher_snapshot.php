<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mastercomSnapshotRomeNow(): DateTime
{
    return new DateTime('now', new DateTimeZone('Europe/Rome'));
}

function mastercomSnapshotClean($value): string
{
    return trim((string)(mastercomAdminCleanText($value) ?? ''));
}

function mastercomSnapshotFirstValue(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $row)) {
            continue;
        }
        if (is_array($row[$key])) {
            continue;
        }
        $value = mastercomSnapshotClean($row[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function mastercomSnapshotClassId(array $row): int
{
    foreach (['id_classe', 'mastercom_id_classe', 'classe_id', 'idClasse', 'valore', 'value'] as $key) {
        if (isset($row[$key]) && is_numeric($row[$key])) {
            return intval($row[$key]);
        }
    }
    return 0;
}

function mastercomSnapshotClassName(array $row): string
{
    $value = mastercomSnapshotFirstValue($row, ['nome_classe', 'classe_nome', 'classe', 'nome', 'label', 'descrizione']);
    return $value !== '' ? $value : '(classe senza nome)';
}

function mastercomSnapshotTeacherName(array $row): string
{
    $direct = mastercomSnapshotFirstValue($row, [
        'docente',
        'docenti',
        'professore',
        'professori',
        'nome_docente',
        'docente_nome',
        'professore_nome',
        'teacher',
        'teacher_name',
        'full_name',
        'nome_visualizzato',
    ]);
    if ($direct !== '') {
        return $direct;
    }

    $hasTeacherId = false;
    foreach (['id_docente', 'id_professore', 'id_user', 'id_utente', 'teacher_id'] as $key) {
        if (!empty($row[$key])) {
            $hasTeacherId = true;
            break;
        }
    }
    if ($hasTeacherId) {
        $name = mastercomSnapshotFirstValue($row, ['name', 'nome']);
        $surname = mastercomSnapshotFirstValue($row, ['surname', 'cognome']);
        $fullName = trim($surname . ' ' . $name);
        return $fullName !== '' ? $fullName : $name;
    }

    return '';
}

function mastercomSnapshotSubject(array $row): string
{
    return mastercomSnapshotFirstValue($row, [
        'materia',
        'nome_materia',
        'materia_nome',
        'materia_breve',
        'subject',
        'disciplina',
    ]);
}

function mastercomSnapshotTimeSlot(array $row): string
{
    $direct = mastercomSnapshotFirstValue($row, [
        'fascia_oraria',
        'fascia',
        'orario',
        'ora',
        'ora_lezione',
        'lezione',
        'periodo',
    ]);
    if ($direct !== '') {
        return $direct;
    }

    $start = mastercomSnapshotFirstValue($row, ['ora_inizio', 'inizio', 'start', 'start_time', 'dalle']);
    $end = mastercomSnapshotFirstValue($row, ['ora_fine', 'fine', 'end', 'end_time', 'alle']);
    if ($start !== '' || $end !== '') {
        return trim($start . ' - ' . $end, ' -');
    }

    return '';
}

function mastercomSnapshotOperationalClassNames(): array
{
    $rows = mastercomAdminOperationalClassRows('*') ?: [];
    $classes = [];
    foreach ($rows as $row) {
        $classId = intval($row['mastercom_id_classe'] ?? 0);
        $mastercomName = mastercomSnapshotClean($row['nome'] ?? '');
        $parsed = mastercomAdminParseClassName($mastercomName);
        $classLabel = mastercomSnapshotClean($parsed['classe_label'] ?? '');
        if ($classId <= 0 || $mastercomName === '' || $classLabel === '') {
            continue;
        }
        if (!preg_match('/^\d+[A-Z]+$/i', $classLabel)) {
            continue;
        }
        $displayName = $classLabel;
        if (!empty($parsed['codice_indirizzo'])) {
            $displayName .= ' ' . mastercomSnapshotClean($parsed['codice_indirizzo']);
        }
        $classes[$classId] = $displayName;
    }
    return $classes;
}

function mastercomSnapshotCalendarBuildDebugMap(array $response): array
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

function mastercomSnapshotFormatEventTime(int $timestamp): string
{
    if ($timestamp <= 0) {
        return '';
    }
    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format('H:i');
}

function mastercomSnapshotEventKind(string $title): string
{
    $normalized = mb_strtolower(trim($title), 'UTF-8');
    if ($normalized === '') {
        return '';
    }
    if (strpos($normalized, 'viaggio') !== false || strpos($normalized, 'istruzione') !== false) {
        return 'viaggio di istruzione';
    }
    if (strpos($normalized, 'uscita') !== false) {
        return 'uscita didattica';
    }
    return '';
}

function mastercomSnapshotActiveClassEvent(array $authResult, int $classId, DateTime $now): ?array
{
    $dayStart = clone $now;
    $dayStart->setTime(0, 0, 0);
    $dayEnd = clone $now;
    $dayEnd->setTime(23, 59, 59);
    $nowTs = $now->getTimestamp();

    $calendarResult = mastercomLoadCalendarNotes($authResult, $classId, $dayStart->getTimestamp(), $dayEnd->getTimestamp(), [
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$calendarResult['ok'] || !is_array($calendarResult['response'] ?? null)) {
        return null;
    }

    $debugMap = mastercomSnapshotCalendarBuildDebugMap($calendarResult['response']);
    $notes = is_array($calendarResult['response']['result'] ?? null) ? $calendarResult['response']['result'] : [];
    foreach ($notes as $note) {
        if (!is_array($note)) {
            continue;
        }
        $noteId = intval($note['id_annotazione_agenda'] ?? 0);
        $eventDebug = $debugMap[$noteId] ?? [];
        $isEvent = intval($note['evento'] ?? $eventDebug['evento'] ?? 0) === 1 || intval($eventDebug['id_evento'] ?? 0) > 0;
        if (!$isEvent) {
            continue;
        }

        $startTs = intval($note['data_inizio'] ?? $eventDebug['data_inizio'] ?? 0);
        $endTs = intval($note['data_fine'] ?? $eventDebug['data_fine'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $startTs > $nowTs || $endTs < $nowTs) {
            continue;
        }

        $title = mastercomSnapshotClean($note['titolo'] ?? '') ?: mastercomSnapshotClean($eventDebug['nome'] ?? '') ?: 'Evento agenda classe';
        $kind = mastercomSnapshotEventKind($title);
        if ($kind === '') {
            continue;
        }

        return [
            'kind' => $kind,
            'title' => $title,
            'start' => mastercomSnapshotFormatEventTime($startTs),
            'end' => mastercomSnapshotFormatEventTime($endTs),
        ];
    }

    return null;
}

function mastercomSnapshotIsAssociative(array $array): bool
{
    return array_keys($array) !== range(0, count($array) - 1);
}

function mastercomSnapshotPushTeacher(array &$teachers, array $row, array $classContext): void
{
    $teacher = mastercomSnapshotTeacherName($row);
    if ($teacher === '') {
        return;
    }

    $subject = mastercomSnapshotSubject($row);
    $slot = mastercomSnapshotTimeSlot($row);
    $key = mastercomAdminNorm($teacher . '|' . $subject . '|' . $slot);
    if (isset($teachers[$key])) {
        return;
    }

    $teachers[$key] = [
        'docente' => $teacher,
        'materia' => $subject,
        'fascia' => $slot,
        'raw_hint' => implode(', ', array_slice(array_keys($row), 0, 8)),
    ];
}

function mastercomSnapshotCollectTeachers($node, array &$teachers, array $classContext): void
{
    if (!is_array($node)) {
        return;
    }

    if (mastercomSnapshotIsAssociative($node)) {
        mastercomSnapshotPushTeacher($teachers, $node, $classContext);
    }

    foreach ($node as $child) {
        if (is_array($child)) {
            mastercomSnapshotCollectTeachers($child, $teachers, $classContext);
        }
    }
}

function mastercomSnapshotExtractClasses(array $userInfoResult): array
{
    $root = $userInfoResult['response']['result'] ?? [];
    $classes = is_array($root) && is_array($root['classi'] ?? null) ? $root['classi'] : [];
    $localClassNames = mastercomSnapshotOperationalClassNames();

    $rowsByClass = [];
    foreach ($localClassNames as $classId => $className) {
        $rowsByClass[$classId] = [
            'id_classe' => $classId,
            'classe' => $className,
            'docenti' => [],
        ];
    }
    if (empty($rowsByClass)) {
        return [];
    }

    $currentLessons = $root['sostituzioni']['principali'] ?? [];
    if (is_array($currentLessons)) {
        foreach ($currentLessons as $classIdKey => $subjects) {
            if (!is_array($subjects)) {
                continue;
            }

            foreach ($subjects as $subjectIdKey => $lessonRow) {
                if (!is_array($lessonRow)) {
                    continue;
                }

                $classId = mastercomSnapshotClassId($lessonRow);
                if ($classId <= 0 && is_numeric($classIdKey)) {
                    $classId = intval($classIdKey);
                }
                if ($classId <= 0) {
                    continue;
                }
                if (!empty($localClassNames) && empty($localClassNames[$classId])) {
                    continue;
                }

                $className = $localClassNames[$classId] ?? mastercomSnapshotFirstValue($lessonRow, ['csi', 'classe_nome', 'nome_classe']);
                if ($className === '') {
                    $className = 'Classe ' . $classId;
                }

                if (!isset($rowsByClass[$classId])) {
                    $rowsByClass[$classId] = [
                        'id_classe' => $classId,
                        'classe' => $className,
                        'docenti' => [],
                    ];
                } elseif ($className !== '') {
                    $rowsByClass[$classId]['classe'] = $className;
                }

                $teacher = mastercomSnapshotFirstValue($lessonRow, ['professore', 'docente']);
                if ($teacher === '') {
                    continue;
                }

                $subject = mastercomSnapshotFirstValue($lessonRow, ['materia_breve', 'materia']);
                $slot = mastercomSnapshotTimeSlot($lessonRow);
                $teacherKey = mastercomAdminNorm($teacher . '|' . $subject . '|' . $slot);
                if ($teacherKey === '') {
                    continue;
                }

                $rowsByClass[$classId]['docenti'][$teacherKey] = [
                    'docente' => $teacher,
                    'materia' => $subject,
                    'fascia' => $slot,
                    'id_professore' => intval($lessonRow['id_professore'] ?? 0),
                    'id_materia' => intval($lessonRow['id_materia'] ?? (is_numeric($subjectIdKey) ? $subjectIdKey : 0)),
                ];
            }
        }
    }

    $rows = array_values(array_filter(array_map(function ($row) {
        $row['docenti'] = array_values($row['docenti']);
        usort($row['docenti'], function ($a, $b) {
            $teacherCompare = strnatcasecmp((string)$a['docente'], (string)$b['docente']);
            if ($teacherCompare !== 0) {
                return $teacherCompare;
            }
            return strnatcasecmp((string)$a['materia'], (string)$b['materia']);
        });
        return $row;
    }, $rowsByClass), function ($row) {
        $parsed = mastercomAdminParseClassName((string)($row['classe'] ?? ''));
        return preg_match('/^\d+[A-Z]+$/i', (string)($parsed['classe_label'] ?? '')) === 1;
    }));

    usort($rows, function ($a, $b) {
        return strnatcasecmp((string)$a['classe'], (string)$b['classe']);
    });

    return $rows;
}

function mastercomSnapshotApplyClassEvents(array $rows, array $authResult, DateTime $now): array
{
    $eventsByClass = [];
    foreach ($rows as &$row) {
        if (empty($row['docenti'])) {
            continue;
        }

        $classId = intval($row['id_classe'] ?? 0);
        if ($classId <= 0) {
            continue;
        }

        if (!array_key_exists($classId, $eventsByClass)) {
            $eventsByClass[$classId] = mastercomSnapshotActiveClassEvent($authResult, $classId, $now);
        }

        if (is_array($eventsByClass[$classId])) {
            $row['evento_classe'] = $eventsByClass[$classId];
            $row['docenti'] = [];
        }
    }
    unset($row);

    return $rows;
}

$now = mastercomSnapshotRomeNow();
$errorMessage = '';
$snapshotRows = [];
$debugInfo = [];

$authResult = mastercomAuthenticateService([
    'profile' => 'MasterComDocenteAuth',
    'method' => 'POST',
    'timeout' => 60,
]);

if (!$authResult['ok']) {
    $errorMessage = 'Autenticazione MasterCom docente fallita: ' . ($authResult['error'] ?? 'AUTH_FAILED');
} else {
    $userInfoResult = mastercomLoadCurrentUserInfo($authResult, [
        'method' => 'POST',
        'timeout' => 120,
    ]);
    if (!$userInfoResult['ok']) {
        $errorMessage = 'Caricamento get_user_info MasterCom fallito: ' . ($userInfoResult['error'] ?? 'LOAD_FAILED');
    } else {
        $snapshotRows = mastercomSnapshotExtractClasses($userInfoResult);
        $snapshotSource = 'result.sostituzioni.principali + agenda classe';
        $snapshotRows = mastercomSnapshotApplyClassEvents($snapshotRows, $authResult, $now);
        $root = $userInfoResult['response']['result'] ?? [];
        $debugInfo = [
            'root_keys' => is_array($root) ? implode(', ', array_slice(array_keys($root), 0, 20)) : '',
            'classi_count' => count($snapshotRows),
            'fasce' => is_array($root['fasce'] ?? null) ? implode(', ', $root['fasce']) : '',
            'source' => $snapshotSource,
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Snapshot Docenti</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .snapshot-empty {
            color: #777;
            font-style: italic;
        }
        .snapshot-teacher {
            margin-bottom: 6px;
        }
        .snapshot-teacher:last-child {
            margin-bottom: 0;
        }
        .snapshot-subject {
            color: #2f6f9f;
            font-weight: 600;
        }
        .snapshot-slot {
            color: #777;
        }
        .snapshot-class-event {
            color: #a94442;
            font-weight: 700;
        }
        .snapshot-class-event-title {
            color: #333;
            font-weight: 600;
        }
        .snapshot-filter-row {
            margin: 10px 0 15px;
        }
        .snapshot-filter-count {
            color: #666;
            margin-left: 10px;
        }
        .snapshot-class-link {
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-blackboard"></span>&emsp;Snapshot docenti in classe MasterCom
        </div>
        <div class="panel-body">
            <p>
                Situazione letta da <code>get_user_info</code> alle
                <strong><?php echo htmlspecialchars($now->format('d/m/Y H:i:s')); ?></strong>.
                <?php if (!empty($debugInfo['fasce'])): ?>
                    Fascia corrente: <strong><?php echo htmlspecialchars($debugInfo['fasce']); ?></strong>.
                <?php endif; ?>
                <a class="btn btn-xs btn-default" href="mastercom_teacher_snapshot.php" style="margin-left: 10px;">
                    <span class="glyphicon glyphicon-refresh"></span> Aggiorna
                </a>
                <a class="btn btn-xs btn-default" href="mastercom.php">Dashboard MasterCom</a>
            </p>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php elseif (empty($snapshotRows)): ?>
                <div class="alert alert-warning">
                    Nessuna classe trovata nella risposta <code>get_user_info</code>.
                    <?php if (!empty($debugInfo['root_keys'])): ?>
                        Campi principali ricevuti: <code><?php echo htmlspecialchars($debugInfo['root_keys']); ?></code>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="snapshot-filter-row form-inline">
                    <div class="form-group">
                        <label for="snapshotTeacherFilter">Cerca docente&nbsp;</label>
                        <input type="text" id="snapshotTeacherFilter" class="form-control" placeholder="Scrivi nome o cognome docente">
                    </div>
                    <span id="snapshotTeacherFilterCount" class="snapshot-filter-count"></span>
                </div>

                <table id="snapshotTeacherTable" class="table table-striped table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th style="width: 90px; text-align: center;">ID</th>
                            <th style="width: 170px;">Classe</th>
                            <th>Docenti attualmente in classe</th>
                            <th style="width: 130px;">Fascia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($snapshotRows as $row): ?>
                            <?php
                            $classEvent = is_array($row['evento_classe'] ?? null) ? $row['evento_classe'] : null;
                            $rowSlots = [];
                            if ($classEvent !== null) {
                                $eventSlot = trim(($classEvent['start'] ?? '') . ' - ' . ($classEvent['end'] ?? ''), ' -');
                                if ($eventSlot !== '') {
                                    $rowSlots[$eventSlot] = $eventSlot;
                                }
                            } else {
                                foreach ($row['docenti'] as $teacher) {
                                    if ($teacher['fascia'] !== '') {
                                        $rowSlots[$teacher['fascia']] = $teacher['fascia'];
                                    }
                                }
                            }
                            ?>
                            <tr class="snapshot-row">
                                <td style="text-align: center;"><?php echo intval($row['id_classe']); ?></td>
                                <td>
                                    <a
                                        class="snapshot-class-link"
                                        href="mastercom_presence.php?class_id=<?php echo intval($row['id_classe']); ?>"
                                        title="Apri lo snapshot studenti della classe <?php echo htmlspecialchars($row['classe']); ?>"
                                    >
                                        <?php echo htmlspecialchars($row['classe']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($classEvent !== null): ?>
                                        <div class="snapshot-class-event">
                                            Classe in <?php echo htmlspecialchars($classEvent['kind']); ?>
                                            <?php if (!empty($classEvent['title'])): ?>
                                                <span class="snapshot-class-event-title"> - <?php echo htmlspecialchars($classEvent['title']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (empty($row['docenti'])): ?>
                                        <span class="snapshot-empty">Nessun docente indicato nella fascia corrente</span>
                                    <?php else: ?>
                                        <?php foreach ($row['docenti'] as $teacher): ?>
                                            <div class="snapshot-teacher">
                                                <strong><?php echo htmlspecialchars($teacher['docente']); ?></strong>
                                                <?php if ($teacher['materia'] !== ''): ?>
                                                    <span class="snapshot-subject"> - <?php echo htmlspecialchars($teacher['materia']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($teacher['id_professore'])): ?>
                                                    <span class="snapshot-slot"> #<?php echo intval($teacher['id_professore']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (empty($rowSlots)): ?>
                                        <span class="snapshot-empty">-</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars(implode(', ', $rowSlots)); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="alert alert-info">
                    Le classi arrivano da <code>result.classi</code>; i docenti della fascia corrente arrivano da
                    <code><?php echo htmlspecialchars($debugInfo['source'] ?? 'result.sostituzioni.principali'); ?></code>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var filterInput = document.getElementById('snapshotTeacherFilter');
    var filterCount = document.getElementById('snapshotTeacherFilterCount');
    var table = document.getElementById('snapshotTeacherTable');
    if (!filterInput || !filterCount || !table) {
        return;
    }

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr.snapshot-row'));
    function normalize(value) {
        return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function applyFilter() {
        var needle = normalize(filterInput.value);
        var visible = 0;
        rows.forEach(function (row) {
            var haystack = normalize(row.textContent);
            var show = needle === '' || haystack.indexOf(needle) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) {
                visible++;
            }
        });
        filterCount.textContent = needle === '' ? '' : visible + ' classi visualizzate';
    }

    filterInput.addEventListener('input', applyFilter);
})();
</script>
</body>
</html>

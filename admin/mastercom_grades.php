<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

function mastercomGradesRomeToday(string $format = 'Y-m-d'): string
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format($format);
}

function mastercomGradesFormatTs(int $timestamp, string $format = 'd/m/Y'): string
{
    if ($timestamp <= 0) {
        return '';
    }

    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format($format);
}

function mastercomGradesSchoolYearRange(): array
{
    $year = trim((string)(mastercomAdminCurrentSchoolYear() ?? ''));
    if (preg_match('/^(\d{4})\s*\/\s*(\d{4})$/', $year, $matches)) {
        return [
            'start' => $matches[1] . '-09-01',
            'end' => $matches[2] . '-08-31',
        ];
    }

    $currentYear = intval(mastercomGradesRomeToday('Y'));
    $currentMonth = intval(mastercomGradesRomeToday('n'));
    $startYear = $currentMonth >= 9 ? $currentYear : ($currentYear - 1);

    return [
        'start' => $startYear . '-09-01',
        'end' => ($startYear + 1) . '-08-31',
    ];
}

function mastercomGradesDayStartTs(string $date): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', trim($date) . ' 00:00:00', new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomGradesDayEndTs(string $date): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', trim($date) . ' 23:59:59', new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomGradesTypeLabel($type): string
{
    $map = [
        0 => 'Scritto',
        1 => 'Orale',
        2 => 'Pratico',
    ];

    $type = intval($type);
    return $map[$type] ?? ('Tipo ' . $type);
}

function mastercomGradesShortTypeLabel(string $type): string
{
    $map = [
        'Scritto' => 'S',
        'Orale' => 'O',
        'Pratico' => 'P',
    ];

    return $map[$type] ?? mb_substr($type, 0, 1, 'UTF-8');
}

function mastercomGradesDayMonthLabel(int $timestamp): string
{
    if ($timestamp <= 0) {
        return '';
    }

    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format('d/m');
}

function mastercomGradesCleanText($value): string
{
    $text = html_entity_decode(trim((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

function mastercomGradesSortSubjects(array $rows): array
{
    usort($rows, function ($a, $b) {
        $nameA = mastercomGradesCleanText($a['materia'] ?? '');
        $nameB = mastercomGradesCleanText($b['materia'] ?? '');
        $cmp = strcmp($nameA, $nameB);
        if ($cmp !== 0) {
            return $cmp;
        }
        return intval($a['mastercom_id_materia'] ?? 0) <=> intval($b['mastercom_id_materia'] ?? 0);
    });

    return $rows;
}

function mastercomGradesCollectSubjectsFromNode($node, array &$subjects, ?int $contextClassId = null): void
{
    if (!is_array($node)) {
        return;
    }

    $currentClassId = $contextClassId;
    if (isset($node['id_classe']) && is_numeric($node['id_classe'])) {
        $currentClassId = intval($node['id_classe']);
    }

    $subjectId = 0;
    if (isset($node['id_materia']) && is_numeric($node['id_materia'])) {
        $subjectId = intval($node['id_materia']);
    } elseif (isset($node['materia']) && isset($node['valore']) && is_numeric($node['valore'])) {
        $subjectId = intval($node['valore']);
    }

    $subjectName = '';
    foreach (['materia', 'materia_breve', 'nome_materia', 'nome'] as $field) {
        $candidate = mastercomGradesCleanText($node[$field] ?? '');
        if ($candidate !== '') {
            $subjectName = $candidate;
            break;
        }
    }

    if ($subjectId > 0 && $subjectName !== '') {
        $rowClassId = isset($node['id_classe']) && is_numeric($node['id_classe']) ? intval($node['id_classe']) : $currentClassId;
        $key = $subjectId . ':' . intval($rowClassId ?? 0);
        $subjects[$key] = [
            'mastercom_id_materia' => $subjectId,
            'materia' => $subjectName,
            'id_classe' => $rowClassId,
        ];
    }

    foreach ($node as $child) {
        if (is_array($child)) {
            mastercomGradesCollectSubjectsFromNode($child, $subjects, $currentClassId);
        }
    }
}

function mastercomGradesExtractSubjectsFromUserInfo(array $userInfoResult, int $classId): array
{
    $root = $userInfoResult['response']['result'] ?? null;
    if (!is_array($root)) {
        return [];
    }

    $subjects = [];
    mastercomGradesCollectSubjectsFromNode($root, $subjects, null);

    $allSubjects = array_values($subjects);
    $classSubjects = array_values(array_filter($allSubjects, function ($row) use ($classId) {
        return intval($row['id_classe'] ?? 0) === $classId;
    }));

    if (!empty($classSubjects)) {
        return mastercomGradesSortSubjects(array_map(function ($row) {
            return [
                'mastercom_id_materia' => $row['mastercom_id_materia'],
                'materia' => $row['materia'],
            ];
        }, $classSubjects));
    }

    $fallback = [];
    foreach ($allSubjects as $row) {
        $fallback[intval($row['mastercom_id_materia'])] = [
            'mastercom_id_materia' => intval($row['mastercom_id_materia']),
            'materia' => $row['materia'],
        ];
    }

    return mastercomGradesSortSubjects(array_values($fallback));
}

$missingTables = mastercomAdminMissingTables(['mastercom_classi']);
$classRows = empty($missingTables) ? mastercomAdminOperationalClassRows('mastercom_id_classe, nome') : [];
$selectedClassId = intval($_GET['class_id'] ?? 0);
if ($selectedClassId > 0 && !mastercomAdminIsOperationalClassId($selectedClassId)) {
    $selectedClassId = 0;
}
$selectedSubjectId = intval($_GET['subject_id'] ?? 0);
$range = mastercomGradesSchoolYearRange();
$startDate = trim((string)($_GET['start_date'] ?? $range['start']));
$endDate = trim((string)($_GET['end_date'] ?? min($range['end'], mastercomGradesRomeToday('Y-m-d'))));
$errorMessage = '';
$avgRows = [];
$gradeRows = [];
$selectedClassName = '';
$subjectRows = [];
$subjectMap = [];
$subjectsToLoad = [];
$studentMap = [];
$gradeCalendarDates = [];
$gradeCalendarStudents = [];
$gradeCalendarCells = [];

if (empty($missingTables) && mastercomAdminTableExists('mastercom_docenti_classi_materie')) {
    if ($selectedClassId > 0) {
        $subjectRows = dbGetAll("
            SELECT DISTINCT
                mastercom_id_materia,
                materia
            FROM mastercom_docenti_classi_materie
            WHERE mastercom_id_classe = " . intval($selectedClassId) . "
            ORDER BY materia ASC, mastercom_id_materia ASC
        ");
    }
}

if (empty($missingTables) && $selectedClassId > 0 && empty($subjectRows)) {
    $subjectAuthResult = mastercomAuthenticateService([
        'profile' => 'MasterComDocenteAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);

    if ($subjectAuthResult['ok']) {
        $userInfoResult = mastercomLoadCurrentUserInfo($subjectAuthResult, [
            'method' => 'POST',
            'timeout' => 120,
        ]);

        if ($userInfoResult['ok']) {
            $subjectRows = mastercomGradesExtractSubjectsFromUserInfo($userInfoResult, $selectedClassId);
        }
    }
}

foreach ($subjectRows as $subjectRow) {
    $subjectId = intval($subjectRow['mastercom_id_materia'] ?? 0);
    if ($subjectId <= 0) {
        continue;
    }
    $subjectMap[$subjectId] = mastercomGradesCleanText($subjectRow['materia'] ?? ('Materia ' . $subjectId));
}
if ($selectedSubjectId > 0 && !isset($subjectMap[$selectedSubjectId])) {
    $selectedSubjectId = 0;
}

if (empty($missingTables) && $selectedClassId > 0) {
    $selectedClassName = trim((string)(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . $selectedClassId . " LIMIT 1") ?? ''));
    $studentsMirror = dbGetAll("
        SELECT mastercom_id_studente, cognome, nome
        FROM mastercom_studenti
        WHERE mastercom_id_classe_corrente = " . intval($selectedClassId) . "
        ORDER BY cognome ASC, nome ASC
    ");
    foreach ($studentsMirror as $studentRow) {
        $studentMap[intval($studentRow['mastercom_id_studente'])] = trim((string)($studentRow['cognome'] ?? '') . ' ' . (string)($studentRow['nome'] ?? ''));
    }
}

if (empty($missingTables) && $selectedClassId > 0 && !empty($subjectMap)) {
    if ($selectedSubjectId > 0) {
        $subjectsToLoad[$selectedSubjectId] = $subjectMap[$selectedSubjectId] ?? ('Materia ' . $selectedSubjectId);
    } else {
        $subjectsToLoad = $subjectMap;
    }
}

if (empty($missingTables) && $selectedClassId > 0 && !empty($subjectsToLoad)) {
    $startTs = mastercomGradesDayStartTs($startDate);
    $endTs = mastercomGradesDayEndTs($endDate);
    $loadErrors = [];

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
            foreach ($subjectsToLoad as $subjectId => $subjectName) {
                $subjectId = intval($subjectId);
                if ($subjectId <= 0) {
                    continue;
                }

                $avgResult = mastercomLoadGradesAvg($authResult, $selectedClassId, $subjectId, $startTs, $endTs, [
                    'method' => 'POST',
                    'timeout' => 120,
                ]);
                $gradesResult = mastercomLoadGradesData($authResult, $selectedClassId, $subjectId, $startTs, $endTs, [
                    'method' => 'POST',
                    'timeout' => 120,
                ]);

                if (!$avgResult['ok'] || !$gradesResult['ok']) {
                    $loadErrors[] = $subjectName . ' [' . $subjectId . ']';
                    continue;
                }

                $avgData = is_array($avgResult['response']['result'] ?? null) ? $avgResult['response']['result'] : [];
                foreach ($avgData as $studentId => $avgRow) {
                    if (!is_array($avgRow)) {
                        continue;
                    }

                    $avgRows[] = [
                        'subject_id' => $subjectId,
                        'subject_name' => $subjectName,
                        'student_id' => intval($studentId),
                        'student_name' => $studentMap[intval($studentId)] ?? ('Studente ' . intval($studentId)),
                        'scritto' => $avgRow['scritto'] ?? null,
                        'orale' => $avgRow['orale'] ?? null,
                        'pratico' => $avgRow['pratico'] ?? null,
                        'totale' => $avgRow['totale'] ?? null,
                    ];
                }

                $gradeData = is_array($gradesResult['response']['result'] ?? null) ? $gradesResult['response']['result'] : [];
                foreach ($gradeData as $gradeRow) {
                    if (!is_array($gradeRow)) {
                        continue;
                    }

                    $studentId = intval($gradeRow['id_studente'] ?? 0);
                    $gradeRows[] = [
                        'id_voto' => intval($gradeRow['id_voto'] ?? 0),
                        'subject_id' => $subjectId,
                        'subject_name' => $subjectName,
                        'student_id' => $studentId,
                        'student_name' => $studentMap[$studentId] ?? ('Studente ' . $studentId),
                        'date_ts' => intval($gradeRow['data'] ?? 0),
                        'date' => mastercomGradesFormatTs(intval($gradeRow['data'] ?? 0)),
                        'tipo' => mastercomGradesTypeLabel($gradeRow['tipo'] ?? 0),
                        'voto' => (string)($gradeRow['voto'] ?? ''),
                        'note' => mastercomAdminCleanText($gradeRow['note'] ?? '') ?? '',
                        'professore' => intval($gradeRow['id_professore'] ?? 0),
                    ];
                }
            }

            if (empty($avgRows) && empty($gradeRows) && !empty($loadErrors)) {
                $errorMessage = 'Caricamento voti MasterCom fallito per: ' . implode(', ', $loadErrors);
            } else {
                usort($avgRows, function ($a, $b) {
                    $cmp = strcmp($a['student_name'], $b['student_name']);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    return strcmp($a['subject_name'] ?? '', $b['subject_name'] ?? '');
                });

                foreach ($avgRows as $row) {
                    $gradeCalendarStudents[intval($row['student_id'])] = $row['student_name'];
                }

                foreach ($gradeRows as $row) {
                    $dateTs = intval($row['date_ts'] ?? 0);
                    if ($dateTs <= 0) {
                        continue;
                    }

                    $dateKey = date('Y-m-d', $dateTs);
                    $gradeCalendarDates[$dateKey] = [
                        'ts' => $dateTs,
                        'label' => mastercomGradesDayMonthLabel($dateTs),
                        'full' => $row['date'],
                    ];

                    $studentId = intval($row['student_id']);
                    if (!isset($gradeCalendarStudents[$studentId])) {
                        $gradeCalendarStudents[$studentId] = $row['student_name'];
                    }

                    if (!isset($gradeCalendarCells[$studentId])) {
                        $gradeCalendarCells[$studentId] = [];
                    }
                    if (!isset($gradeCalendarCells[$studentId][$dateKey])) {
                        $gradeCalendarCells[$studentId][$dateKey] = [];
                    }

                    $gradeCalendarCells[$studentId][$dateKey][] = [
                        'voto' => $row['voto'],
                        'tipo' => $row['tipo'],
                        'tipo_short' => mastercomGradesShortTypeLabel($row['tipo']),
                        'note' => $row['note'],
                        'subject_name' => $row['subject_name'] ?? '',
                    ];
                }

                uasort($gradeCalendarDates, function ($a, $b) {
                    return intval($a['ts']) <=> intval($b['ts']);
                });
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Voti</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .mc-grades-calendar-wrap {
            overflow-x: auto;
            border: 1px solid #d8e4ea;
            background: #fff;
        }

        .mc-grades-calendar {
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .mc-grades-calendar th,
        .mc-grades-calendar td {
            border-right: 1px solid #d8e4ea;
            border-bottom: 1px solid #d8e4ea;
            vertical-align: top;
            padding: 6px;
        }

        .mc-grades-calendar thead th {
            position: sticky;
            top: 0;
            background: #edf6fa;
            z-index: 2;
            text-align: center;
            min-width: 88px;
        }

        .mc-grades-calendar .mc-student-col {
            position: sticky;
            left: 0;
            z-index: 3;
            background: #f8fbfc;
            min-width: 240px;
            max-width: 240px;
        }

        .mc-grades-calendar thead .mc-student-col {
            z-index: 4;
        }

        .mc-grade-chip {
            display: block;
            margin-bottom: 5px;
            padding: 4px 6px;
            border-radius: 6px;
            background: #f2f7f9;
            border: 1px solid #d7e6eb;
            line-height: 1.2;
        }

        .mc-grade-chip:last-child {
            margin-bottom: 0;
        }

        .mc-grade-score {
            font-weight: 700;
            color: #0d5a7a;
        }

        .mc-grade-type {
            display: inline-block;
            min-width: 18px;
            text-align: center;
            margin-right: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #4b5f69;
        }

        .mc-grade-note {
            display: block;
            margin-top: 3px;
            font-size: 11px;
            color: #5e6d75;
        }

        .mc-empty-cell {
            background: #fbfcfd;
            min-width: 88px;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-list-alt"></span>&emsp;Voti Classe / Materia MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Mancano tabelle: <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.</div>
            <?php else: ?>
                <form method="get" action="mastercom_grades.php" class="form-inline" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="class_id">Classe&nbsp;</label>
                        <select name="class_id" id="class_id" class="form-control" onchange="this.form.submit()">
                            <option value="0">Seleziona una classe</option>
                            <?php foreach ($classRows as $classRow): ?>
                                <option value="<?php echo intval($classRow['mastercom_id_classe']); ?>" <?php echo $selectedClassId === intval($classRow['mastercom_id_classe']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($classRow['nome'] ?? '') . ' [' . ($classRow['mastercom_id_classe'] ?? '') . ']'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-left: 10px;">
                        <label for="subject_id">Materia&nbsp;</label>
                        <select name="subject_id" id="subject_id" class="form-control" onchange="this.form.submit()" <?php echo $selectedClassId <= 0 || empty($subjectRows) ? 'disabled' : ''; ?>>
                            <?php if ($selectedClassId <= 0): ?>
                                <option value="0">Seleziona prima una classe</option>
                            <?php elseif (empty($subjectRows)): ?>
                                <option value="0">Nessuna materia trovata</option>
                            <?php else: ?>
                                <option value="0" <?php echo $selectedSubjectId <= 0 ? 'selected' : ''; ?>>Tutte le materie</option>
                                <?php foreach ($subjectRows as $subjectRow): ?>
                                    <option value="<?php echo intval($subjectRow['mastercom_id_materia']); ?>" <?php echo $selectedSubjectId === intval($subjectRow['mastercom_id_materia']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(($subjectRow['materia'] ?? 'Materia') . ' [' . ($subjectRow['mastercom_id_materia'] ?? '') . ']'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                    <div class="alert alert-info">Seleziona classe e intervallo per vedere medie e voti dettagliati.</div>
                <?php elseif (empty($subjectRows)): ?>
                    <div class="alert alert-warning">Nessuna materia MasterCom trovata per la classe selezionata. Sincronizza docenti/classi/materie oppure verifica i dati MasterCom.</div>
                <?php elseif ($errorMessage !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Classe <strong><?php echo htmlspecialchars($selectedClassName !== '' ? $selectedClassName : (string)$selectedClassId); ?></strong>,
                        materia <strong><?php echo $selectedSubjectId > 0 ? htmlspecialchars($subjectMap[$selectedSubjectId] ?? ('Materia ' . $selectedSubjectId)) : 'Tutte le materie'; ?></strong>.
                    </div>

                    <h4><?php echo $selectedSubjectId > 0 ? 'Medie per studente' : 'Medie per studente e materia'; ?></h4>
                    <table class="table table-striped table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th>Studente</th>
                                <?php if ($selectedSubjectId <= 0): ?>
                                    <th>Materia</th>
                                <?php endif; ?>
                                <th style="text-align: center;">Scritto</th>
                                <th style="text-align: center;">Orale</th>
                                <th style="text-align: center;">Pratico</th>
                                <th style="text-align: center;">Totale</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avgRows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <?php if ($selectedSubjectId <= 0): ?>
                                        <td><?php echo htmlspecialchars($row['subject_name'] ?? ''); ?></td>
                                    <?php endif; ?>
                                    <td style="text-align: center;"><?php echo htmlspecialchars((string)$row['scritto']); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars((string)$row['orale']); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars((string)$row['pratico']); ?></td>
                                    <td style="text-align: center;"><strong><?php echo htmlspecialchars((string)$row['totale']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <h4>Calendario voti</h4>
                    <?php if (empty($gradeCalendarDates) || empty($gradeCalendarStudents)): ?>
                        <div class="alert alert-info">Nessun voto dettagliato trovato nell'intervallo selezionato.</div>
                    <?php else: ?>
                        <div class="mc-grades-calendar-wrap">
                            <table class="table mc-grades-calendar">
                                <thead>
                                    <tr>
                                        <th class="mc-student-col">Studente</th>
                                        <?php foreach ($gradeCalendarDates as $dateInfo): ?>
                                            <th>
                                                <?php echo htmlspecialchars($dateInfo['label']); ?><br>
                                                <small><?php echo htmlspecialchars($dateInfo['full']); ?></small>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gradeCalendarStudents as $studentId => $studentName): ?>
                                        <tr>
                                            <td class="mc-student-col"><?php echo htmlspecialchars($studentName); ?></td>
                                            <?php foreach ($gradeCalendarDates as $dateKey => $dateInfo): ?>
                                                <?php $cellEntries = $gradeCalendarCells[intval($studentId)][$dateKey] ?? []; ?>
                                                <td class="<?php echo empty($cellEntries) ? 'mc-empty-cell' : ''; ?>">
                                                    <?php foreach ($cellEntries as $entry): ?>
                                                        <?php
                                                        $chipTitleParts = [];
                                                        if ($selectedSubjectId <= 0 && trim((string)($entry['subject_name'] ?? '')) !== '') {
                                                            $chipTitleParts[] = $entry['subject_name'];
                                                        }
                                                        $chipTitleParts[] = $entry['tipo'];
                                                        if ($entry['note'] !== '') {
                                                            $chipTitleParts[] = $entry['note'];
                                                        }
                                                        ?>
                                                        <span class="mc-grade-chip" title="<?php echo htmlspecialchars(implode(' - ', $chipTitleParts)); ?>">
                                                            <?php if ($selectedSubjectId <= 0 && trim((string)($entry['subject_name'] ?? '')) !== ''): ?>
                                                                <span class="mc-grade-note"><strong><?php echo htmlspecialchars($entry['subject_name']); ?></strong></span>
                                                            <?php endif; ?>
                                                            <span class="mc-grade-type"><?php echo htmlspecialchars($entry['tipo_short']); ?></span>
                                                            <span class="mc-grade-score"><?php echo htmlspecialchars($entry['voto']); ?></span>
                                                            <?php if ($entry['note'] !== ''): ?>
                                                                <span class="mc-grade-note"><?php echo htmlspecialchars($entry['note']); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

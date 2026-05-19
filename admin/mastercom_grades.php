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

function mastercomGradesHasValue($value): bool
{
    if ($value === null) {
        return false;
    }
    $text = trim((string)$value);
    if ($text === '' || $text === '-' || preg_match('/^0+(?:[\.,]0+)?$/', $text)) {
        return false;
    }
    return true;
}

function mastercomGradesFormatAverage($value): string
{
    return mastercomGradesHasValue($value) ? (string)$value : '-';
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

    return [];
}

function mastercomGradesExtractAllSubjectsFromUserInfo(array $userInfoResult): array
{
    $root = $userInfoResult['response']['result'] ?? null;
    if (!is_array($root)) {
        return [];
    }

    $subjects = [];
    mastercomGradesCollectSubjectsFromNode($root, $subjects, null);
    $fallback = [];
    foreach (array_values($subjects) as $row) {
        $subjectId = intval($row['mastercom_id_materia'] ?? 0);
        if ($subjectId <= 0) {
            continue;
        }
        $fallback[$subjectId] = [
            'mastercom_id_materia' => $subjectId,
            'materia' => $row['materia'],
        ];
    }

    return mastercomGradesSortSubjects(array_values($fallback));
}

function mastercomGradesExtractClassSubjectsFromHtml(string $html): array
{
    if (trim($html) === '') {
        return [];
    }

    $subjects = [];
    if (!preg_match_all('/<select\b[^>]*name=["\']materia\[\]["\'][^>]*>(.*?)<\/select>/is', $html, $selectMatches)) {
        return [];
    }

    foreach ($selectMatches[1] as $selectHtml) {
        if (!preg_match_all('/<option\b([^>]*)>(.*?)<\/option>/is', $selectHtml, $optionMatches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($optionMatches as $optionMatch) {
            $attributes = (string)($optionMatch[1] ?? '');
            if (stripos($attributes, 'selected') === false) {
                continue;
            }

            if (!preg_match('/\bvalue\s*=\s*(["\']?)(-?\d+)\1/i', $attributes, $valueMatch)) {
                continue;
            }

            $subjectId = intval($valueMatch[2] ?? 0);
            if ($subjectId <= 0) {
                continue;
            }

            $subjectName = mastercomGradesCleanText(strip_tags((string)($optionMatch[2] ?? '')));
            if ($subjectName === '') {
                $subjectName = 'Materia ' . $subjectId;
            }

            $subjects[$subjectId] = [
                'mastercom_id_materia' => $subjectId,
                'materia' => $subjectName,
            ];
        }
    }

    return mastercomGradesSortSubjects(array_values($subjects));
}

function mastercomGradesLoadClassSubjectsFromAdmin(array $authResult, int $classId): array
{
    if ($classId <= 0) {
        return [];
    }

    $response = mastercomRawRequest([
        'form_stato' => 'amministratore',
        'stato_principale' => 'impostazioni_principale',
        'stato_secondario' => 'gestione_classi_indirizzi_display',
        'id_classe' => $classId,
        'operazione' => '',
        'id_indirizzo' => '',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
    ], [
        'base_url' => mastercomIndexUrl(),
        'cookie' => implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => 'POST',
        'send_in_body' => true,
        'timeout' => 120,
    ]);

    if (empty($response['ok'])) {
        return [];
    }

    return mastercomGradesExtractClassSubjectsFromHtml((string)($response['body'] ?? ''));
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
$probeSubjectMap = [];
$candidateSubjectMap = [];
$studentMap = [];
$teacherMap = [];
$avgMatrixSubjects = [];
$avgMatrixStudents = [];
$avgMatrixCells = [];
$gradeCalendarDates = [];
$gradeCalendarStudents = [];
$gradeCalendarCells = [];

if (empty($missingTables) && $selectedClassId > 0) {
    $subjectAuthResult = mastercomAuthenticateService([
        'profile' => 'MasterComAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);

    if ($subjectAuthResult['ok']) {
        $subjectRows = mastercomGradesLoadClassSubjectsFromAdmin($subjectAuthResult, $selectedClassId);
    }
}

if (empty($missingTables) && $selectedClassId > 0 && empty($subjectRows) && mastercomAdminTableExists('mastercom_docenti_classi_materie')) {
    $subjectRows = dbGetAll("
        SELECT DISTINCT
            mastercom_id_materia,
            materia
        FROM mastercom_docenti_classi_materie
        WHERE mastercom_id_classe = " . intval($selectedClassId) . "
        ORDER BY materia ASC, mastercom_id_materia ASC
    ");
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

if (empty($missingTables) && mastercomAdminTableExists('mastercom_docenti')) {
    $teacherRows = dbGetAll("
        SELECT
            m.mastercom_id_user,
            m.nome_visualizzato,
            d.cognome,
            d.nome
        FROM mastercom_docenti m
        LEFT JOIN docente d ON d.id = m.id_docente_gestore
        WHERE m.mastercom_id_user IS NOT NULL
          AND m.mastercom_id_user > 0
    ");
    foreach ($teacherRows as $teacherRow) {
        $teacherId = intval($teacherRow['mastercom_id_user'] ?? 0);
        if ($teacherId <= 0) {
            continue;
        }
        $teacherName = trim((string)($teacherRow['cognome'] ?? '') . ' ' . (string)($teacherRow['nome'] ?? ''));
        if ($teacherName === '') {
            $teacherName = trim((string)($teacherRow['nome_visualizzato'] ?? ''));
        }
        if ($teacherName !== '') {
            $teacherMap[$teacherId] = $teacherName;
        }
    }
}

if (empty($missingTables) && $selectedClassId > 0) {
    $candidateSubjectMap = $subjectMap;
    if ($selectedSubjectId > 0 && !isset($candidateSubjectMap[$selectedSubjectId])) {
        $selectedSubjectId = 0;
    }
    $subjectsToLoad = $candidateSubjectMap;
}

if (empty($missingTables) && $selectedClassId > 0 && !empty($subjectsToLoad)) {
    $startTs = mastercomGradesDayStartTs($startDate);
    $endTs = mastercomGradesDayEndTs($endDate);
    $loadErrors = [];
    $loadedSubjectIds = [];

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
                    $studentId = intval($studentId);
                    if (!isset($studentMap[$studentId])) {
                        continue;
                    }

                    $avgRows[] = [
                        'subject_id' => $subjectId,
                        'subject_name' => $subjectName,
                        'student_id' => $studentId,
                        'student_name' => $studentMap[$studentId],
                        'scritto' => $avgRow['scritto'] ?? null,
                        'orale' => $avgRow['orale'] ?? null,
                        'pratico' => $avgRow['pratico'] ?? null,
                        'totale' => $avgRow['totale'] ?? null,
                    ];

                    if (
                        mastercomGradesHasValue($avgRow['scritto'] ?? null) ||
                        mastercomGradesHasValue($avgRow['orale'] ?? null) ||
                        mastercomGradesHasValue($avgRow['pratico'] ?? null) ||
                        mastercomGradesHasValue($avgRow['totale'] ?? null)
                    ) {
                        $loadedSubjectIds[$subjectId] = true;
                    }
                }

                $gradeData = is_array($gradesResult['response']['result'] ?? null) ? $gradesResult['response']['result'] : [];
                foreach ($gradeData as $gradeRow) {
                    if (!is_array($gradeRow)) {
                        continue;
                    }

                    $studentId = intval($gradeRow['id_studente'] ?? 0);
                    if (!isset($studentMap[$studentId])) {
                        continue;
                    }
                    $gradeRows[] = [
                        'id_voto' => intval($gradeRow['id_voto'] ?? 0),
                        'subject_id' => $subjectId,
                        'subject_name' => $subjectName,
                        'student_id' => $studentId,
                        'student_name' => $studentMap[$studentId],
                        'date_ts' => intval($gradeRow['data'] ?? 0),
                        'date' => mastercomGradesFormatTs(intval($gradeRow['data'] ?? 0)),
                        'tipo' => mastercomGradesTypeLabel($gradeRow['tipo'] ?? 0),
                        'voto' => (string)($gradeRow['voto'] ?? ''),
                        'note' => mastercomAdminCleanText($gradeRow['note'] ?? '') ?? '',
                        'professore' => intval($gradeRow['id_professore'] ?? 0),
                        'professore_nome' => $teacherMap[intval($gradeRow['id_professore'] ?? 0)] ?? '',
                    ];
                    if (trim((string)($gradeRow['voto'] ?? '')) !== '') {
                        $loadedSubjectIds[$subjectId] = true;
                    }
                }
            }

            if (!empty($loadedSubjectIds)) {
                $avgRows = array_values(array_filter($avgRows, function ($row) use ($loadedSubjectIds) {
                    return isset($loadedSubjectIds[intval($row['subject_id'] ?? 0)]) && (
                        mastercomGradesHasValue($row['scritto'] ?? null) ||
                        mastercomGradesHasValue($row['orale'] ?? null) ||
                        mastercomGradesHasValue($row['pratico'] ?? null) ||
                        mastercomGradesHasValue($row['totale'] ?? null)
                    );
                }));

                $filteredSubjectRows = [];
                foreach ($subjectsToLoad as $subjectId => $subjectName) {
                    $subjectId = intval($subjectId);
                    if (isset($loadedSubjectIds[$subjectId])) {
                        $filteredSubjectRows[] = [
                            'mastercom_id_materia' => $subjectId,
                            'materia' => $subjectName,
                        ];
                    }
                }
                if (!empty($filteredSubjectRows)) {
                    $subjectRows = mastercomGradesSortSubjects($filteredSubjectRows);
                    $subjectMap = [];
                    foreach ($subjectRows as $subjectRow) {
                        $subjectId = intval($subjectRow['mastercom_id_materia'] ?? 0);
                        if ($subjectId > 0) {
                            $subjectMap[$subjectId] = mastercomGradesCleanText($subjectRow['materia'] ?? ('Materia ' . $subjectId));
                        }
                    }
                }

                if ($selectedSubjectId > 0 && !isset($loadedSubjectIds[$selectedSubjectId])) {
                    $selectedSubjectId = 0;
                }
                if ($selectedSubjectId > 0) {
                    $avgRows = array_values(array_filter($avgRows, function ($row) use ($selectedSubjectId) {
                        return intval($row['subject_id'] ?? 0) === $selectedSubjectId;
                    }));
                    $gradeRows = array_values(array_filter($gradeRows, function ($row) use ($selectedSubjectId) {
                        return intval($row['subject_id'] ?? 0) === $selectedSubjectId;
                    }));
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

                if ($selectedSubjectId <= 0) {
                    $avgMatrixSubjects = $subjectMap;
                    $avgMatrixStudents = $studentMap;
                    foreach ($avgRows as $row) {
                        $studentId = intval($row['student_id'] ?? 0);
                        $subjectId = intval($row['subject_id'] ?? 0);
                        if ($studentId > 0 && $subjectId > 0) {
                            $avgMatrixCells[$studentId][$subjectId] = $row;
                        }
                    }
                }

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
                        'period' => intval(date('n', $dateTs)) >= 9 ? 'first' : 'second',
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
                        'tipo_class' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $row['tipo'])),
                        'note' => $row['note'],
                        'subject_name' => $row['subject_name'] ?? '',
                        'professore_nome' => $row['professore_nome'] ?? '',
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
            overflow-y: visible;
            max-width: 100%;
            width: 100%;
            border: 1px solid #d8e4ea;
            background: #fff;
        }

        .mc-avg-matrix-wrap {
            overflow-x: auto;
            max-width: 100%;
            width: 100%;
            border: 1px solid #d8e4ea;
            background: #fff;
            margin-bottom: 18px;
        }

        .mc-avg-matrix {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
        }

        .mc-avg-matrix th,
        .mc-avg-matrix td {
            border-right: 1px solid #d8e4ea;
            border-bottom: 1px solid #d8e4ea;
            padding: 7px 8px;
            vertical-align: middle;
            text-align: center;
        }

        .mc-avg-matrix thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #edf6fa;
            color: #1f3f52;
            font-size: 12px;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .mc-avg-matrix .mc-student-col {
            position: sticky;
            left: 0;
            z-index: 3;
            background: #f8fbfc;
            text-align: left;
            font-weight: 700;
            width: 220px;
            min-width: 220px;
            max-width: 220px;
        }

        .mc-avg-matrix thead .mc-student-col {
            z-index: 4;
            text-align: center;
        }

        .mc-avg-cell {
            display: inline-block;
            min-width: 46px;
            padding: 4px 9px;
            border-radius: 999px;
            background: #e8f2ff;
            border: 1px solid #9ec7f5;
            color: #0d5a7a;
            font-weight: 800;
        }

        .mc-avg-cell-empty {
            color: #98a6ad;
            font-weight: 700;
        }

        .mc-grades-calendar {
            width: 100%;
            table-layout: fixed;
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

        .mc-grades-calendar th.mc-period-first,
        .mc-grades-calendar td.mc-period-first {
            background: #fff8d8;
        }

        .mc-grades-calendar th.mc-period-second,
        .mc-grades-calendar td.mc-period-second {
            background: #eaf7ff;
        }

        .mc-grades-calendar thead th {
            position: sticky;
            top: 0;
            background: #edf6fa;
            z-index: 2;
            text-align: center;
            width: 150px;
        }

        .mc-grades-calendar .mc-student-col {
            position: sticky;
            left: 0;
            z-index: 3;
            background: #f8fbfc;
            width: 220px;
            min-width: 220px;
            max-width: 220px;
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
            overflow: hidden;
        }

        .mc-grade-chip.mc-grade-scritto {
            background: #e8f2ff;
            border-color: #9ec7f5;
        }

        .mc-grade-chip.mc-grade-orale {
            background: #e9f8ed;
            border-color: #9bd4aa;
        }

        .mc-grade-chip.mc-grade-pratico {
            background: #fff0df;
            border-color: #efbf86;
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
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .mc-grade-note-long {
            max-height: 42px;
            overflow: hidden;
        }

        .mc-grade-teacher {
            display: block;
            margin-top: 3px;
            font-size: 10px;
            font-weight: 700;
            color: #42515a;
        }

        .mc-empty-cell {
            width: 150px;
        }

        #mc_grades_loading_overlay {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.78);
            align-items: center;
            justify-content: center;
        }

        #mc_grades_loading_overlay .mc-loading-box {
            min-width: 300px;
            background: #ffffff;
            border: 1px solid #d7e3f0;
            border-radius: 8px;
            box-shadow: 0 12px 34px rgba(15, 23, 42, 0.18);
            padding: 20px 22px;
            text-align: center;
            font-weight: 700;
            color: #0d5a7a;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div id="mc_grades_loading_overlay">
    <div class="mc-loading-box">
        <span class="glyphicon glyphicon-refresh"></span>
        Caricamento voti MasterCom in corso...
    </div>
</div>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-list-alt"></span>&emsp;Voti Classe / Materia MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Mancano tabelle: <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.</div>
            <?php else: ?>
                <form method="get" action="mastercom_grades.php" class="form-inline" style="margin-bottom: 15px;" onsubmit="document.getElementById('mc_grades_loading_overlay').style.display='flex';">
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

                    <h4><?php echo $selectedSubjectId > 0 ? 'Medie per studente' : 'Quadro medie per materia'; ?></h4>
                    <?php if ($selectedSubjectId <= 0): ?>
                        <?php if (empty($avgMatrixSubjects) || empty($avgMatrixStudents)): ?>
                            <div class="alert alert-info">Nessuna media trovata nell'intervallo selezionato.</div>
                        <?php else: ?>
                            <div class="mc-avg-matrix-wrap">
                                <table class="table mc-avg-matrix" style="min-width: <?php echo 220 + (max(1, count($avgMatrixSubjects)) * 125); ?>px;">
                                    <colgroup>
                                        <col style="width: 220px;">
                                        <?php foreach ($avgMatrixSubjects as $_subjectId => $_subjectName): ?>
                                            <col style="width: 125px;">
                                        <?php endforeach; ?>
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th class="mc-student-col">Studente</th>
                                            <?php foreach ($avgMatrixSubjects as $subjectName): ?>
                                                <th><?php echo htmlspecialchars($subjectName); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($avgMatrixStudents as $studentId => $studentName): ?>
                                            <tr>
                                                <td class="mc-student-col"><?php echo htmlspecialchars($studentName); ?></td>
                                                <?php foreach ($avgMatrixSubjects as $subjectId => $subjectName): ?>
                                                    <?php $avgCell = $avgMatrixCells[intval($studentId)][intval($subjectId)] ?? null; ?>
                                                    <td>
                                                        <?php if (is_array($avgCell)): ?>
                                                            <?php
                                                            $avgTooltip = $subjectName
                                                                . ' - Scritto: ' . mastercomGradesFormatAverage($avgCell['scritto'] ?? null)
                                                                . ' - Orale: ' . mastercomGradesFormatAverage($avgCell['orale'] ?? null)
                                                                . ' - Pratico: ' . mastercomGradesFormatAverage($avgCell['pratico'] ?? null);
                                                            ?>
                                                            <span class="mc-avg-cell" title="<?php echo htmlspecialchars($avgTooltip); ?>">
                                                                <?php echo htmlspecialchars(mastercomGradesFormatAverage($avgCell['totale'] ?? null)); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="mc-avg-cell-empty">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <table class="table table-striped table-bordered table-condensed">
                            <thead>
                                <tr>
                                    <th>Studente</th>
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
                                        <td style="text-align: center;"><?php echo htmlspecialchars(mastercomGradesFormatAverage($row['scritto'])); ?></td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars(mastercomGradesFormatAverage($row['orale'])); ?></td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars(mastercomGradesFormatAverage($row['pratico'])); ?></td>
                                        <td style="text-align: center;"><strong><?php echo htmlspecialchars(mastercomGradesFormatAverage($row['totale'])); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <h4>Calendario voti</h4>
                    <?php if (empty($gradeCalendarDates) || empty($gradeCalendarStudents)): ?>
                        <div class="alert alert-info">Nessun voto dettagliato trovato nell'intervallo selezionato.</div>
                    <?php else: ?>
                        <div class="mc-grades-calendar-wrap">
                            <table class="table mc-grades-calendar" style="min-width: <?php echo 220 + (max(1, count($gradeCalendarDates)) * 150); ?>px;">
                                <colgroup>
                                    <col style="width: 220px;">
                                    <?php foreach ($gradeCalendarDates as $_dateInfo): ?>
                                        <col style="width: 150px;">
                                    <?php endforeach; ?>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="mc-student-col">Studente</th>
                                        <?php foreach ($gradeCalendarDates as $dateInfo): ?>
                                            <th class="mc-period-<?php echo htmlspecialchars($dateInfo['period'] ?? 'second'); ?>">
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
                                                <td class="<?php echo empty($cellEntries) ? 'mc-empty-cell ' : ''; ?>mc-period-<?php echo htmlspecialchars($dateInfo['period'] ?? 'second'); ?>">
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
                                                        if (($entry['professore_nome'] ?? '') !== '') {
                                                            $chipTitleParts[] = 'Docente: ' . $entry['professore_nome'];
                                                        }
                                                        ?>
                                                        <span class="mc-grade-chip mc-grade-<?php echo htmlspecialchars($entry['tipo_class'] ?? ''); ?>" title="<?php echo htmlspecialchars(implode(' - ', $chipTitleParts)); ?>">
                                                            <?php if ($selectedSubjectId <= 0 && trim((string)($entry['subject_name'] ?? '')) !== ''): ?>
                                                                <span class="mc-grade-note"><strong><?php echo htmlspecialchars($entry['subject_name']); ?></strong></span>
                                                            <?php endif; ?>
                                                            <span class="mc-grade-type"><?php echo htmlspecialchars($entry['tipo_short']); ?></span>
                                                            <span class="mc-grade-score"><?php echo htmlspecialchars($entry['voto']); ?></span>
                                                            <?php if (($entry['professore_nome'] ?? '') !== ''): ?>
                                                                <span class="mc-grade-teacher"><?php echo htmlspecialchars($entry['professore_nome']); ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($entry['note'] !== ''): ?>
                                                                <span class="mc-grade-note mc-grade-note-long"><?php echo htmlspecialchars($entry['note']); ?></span>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[action="mastercom_grades.php"]');
    var overlay = document.getElementById('mc_grades_loading_overlay');
    if (!form || !overlay) {
        return;
    }
    var showOverlay = function () {
        overlay.style.display = 'flex';
    };
    form.addEventListener('submit', showOverlay);
    ['class_id', 'subject_id'].forEach(function (id) {
        var field = document.getElementById(id);
        if (field) {
            field.addEventListener('change', showOverlay);
        }
    });
});
</script>
</body>
</html>

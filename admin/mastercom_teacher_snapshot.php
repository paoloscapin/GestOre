<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

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
    $localClassNames = [];
    if (mastercomAdminTableExists('mastercom_classi')) {
        foreach (dbGetAll("SELECT mastercom_id_classe, nome FROM mastercom_classi ORDER BY nome ASC") as $row) {
            $localClassNames[intval($row['mastercom_id_classe'])] = mastercomSnapshotClean($row['nome'] ?? '');
        }
    }

    $rowsByClass = [];
    foreach ($classes as $classRow) {
        if (!is_array($classRow)) {
            continue;
        }

        $classId = mastercomSnapshotClassId($classRow);
        $className = mastercomSnapshotClassName($classRow);
        if ($classId > 0 && !empty($localClassNames[$classId])) {
            $className = $localClassNames[$classId];
        }

        $rowsByClass[$classId] = [
            'id_classe' => $classId,
            'classe' => $className,
            'docenti' => [],
        ];
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

                $className = mastercomSnapshotFirstValue($lessonRow, ['csi', 'classe_nome', 'nome_classe']);
                if ($className === '' && !empty($localClassNames[$classId])) {
                    $className = $localClassNames[$classId];
                }
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

    $rows = array_values(array_map(function ($row) {
        $row['docenti'] = array_values($row['docenti']);
        usort($row['docenti'], function ($a, $b) {
            $teacherCompare = strnatcasecmp((string)$a['docente'], (string)$b['docente']);
            if ($teacherCompare !== 0) {
                return $teacherCompare;
            }
            return strnatcasecmp((string)$a['materia'], (string)$b['materia']);
        });
        return $row;
    }, $rowsByClass));

    usort($rows, function ($a, $b) {
        return strnatcasecmp((string)$a['classe'], (string)$b['classe']);
    });

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
        $root = $userInfoResult['response']['result'] ?? [];
        $debugInfo = [
            'root_keys' => is_array($root) ? implode(', ', array_slice(array_keys($root), 0, 20)) : '',
            'classi_count' => count($snapshotRows),
            'fasce' => is_array($root['fasce'] ?? null) ? implode(', ', $root['fasce']) : '',
            'source' => 'result.sostituzioni.principali',
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
                <table class="table table-striped table-bordered table-condensed">
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
                            $rowSlots = [];
                            foreach ($row['docenti'] as $teacher) {
                                if ($teacher['fascia'] !== '') {
                                    $rowSlots[$teacher['fascia']] = $teacher['fascia'];
                                }
                            }
                            ?>
                            <tr>
                                <td style="text-align: center;"><?php echo intval($row['id_classe']); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['classe']); ?></strong></td>
                                <td>
                                    <?php if (empty($row['docenti'])): ?>
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
</body>
</html>

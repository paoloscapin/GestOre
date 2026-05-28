<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';
require_once '../common/mastercom/grades_cache_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mcGradeH($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mcGradeDateParts(string $date): array
{
    $dt = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        $dt = new DateTime('today', new DateTimeZone('Europe/Rome'));
    }
    return [
        'Date_Day' => (string)intval($dt->format('d')),
        'Date_Month' => $dt->format('m'),
        'Date_Year' => $dt->format('Y'),
    ];
}

function mcGradeRowsByClass(array $rows, string $classField): array
{
    $map = [];
    foreach ($rows as $row) {
        $classId = intval($row[$classField] ?? 0);
        if ($classId <= 0) {
            continue;
        }
        if (!isset($map[$classId])) {
            $map[$classId] = [];
        }
        $map[$classId][] = $row;
    }
    return $map;
}

function mcGradeBuildPayload(array $row, array $studentsById, array $classesById): array
{
    $classId = intval($row['class_id'] ?? 0);
    $studentId = intval($row['student_id'] ?? 0);
    $subjectId = intval($row['subject_id'] ?? 0);
    $teacherId = intval($row['teacher_id'] ?? 0);
    $type = intval($row['tipo_voto'] ?? -1);
    $grade = trim((string)($row['voto'] ?? ''));
    $date = trim((string)($row['data'] ?? ''));
    $note = trim((string)($row['note'] ?? ''));

    if ($classId <= 0 || $studentId <= 0 || $subjectId <= 0 || $teacherId <= 0 || $type < 0 || $grade === '' || $date === '') {
        throw new Exception('Riga incompleta.');
    }
    if (!isset($studentsById[$studentId])) {
        throw new Exception('Studente MasterCom non trovato: ' . $studentId);
    }
    if (!isset($classesById[$classId])) {
        throw new Exception('Classe MasterCom non trovata: ' . $classId);
    }

    $student = $studentsById[$studentId];
    $class = $classesById[$classId];
    $dateParts = mcGradeDateParts($date);

    return array_merge([
        'id_professore' => $teacherId,
        'tipo_voto' => $type,
        'voto' => $grade,
        'note_voto' => $note,
        'id_classe' => $classId,
        'classe' => trim((string)($class['nome'] ?? '')),
        'indirizzo' => '',
        'id_indirizzo' => intval($student['id_indirizzo'] ?? $student['mastercom_id_indirizzo'] ?? $class['id_indirizzo'] ?? 0),
        'id_stud' => $studentId,
        'cognome_stud' => trim((string)($student['cognome'] ?? '')),
        'nome_stud' => trim((string)($student['nome'] ?? '')),
        'id_materia' => $subjectId,
    ], $dateParts);
}

function mcGradeGradeOptions(): array
{
    return [
        'A' => 'A',
        'NC' => 'NC',
        '3' => '3',
        '3.25' => '3+',
        '3.50' => '3.5',
        '3.75' => '4-',
        '4' => '4',
        '4.25' => '4+',
        '4.50' => '4.5',
        '4.75' => '5-',
        '5' => '5',
        '5.25' => '5+',
        '5.50' => '5.5',
        '5.75' => '6-',
        '6' => '6',
        '6.25' => '6+',
        '6.50' => '6.5',
        '6.75' => '7-',
        '7' => '7',
        '7.25' => '7+',
        '7.50' => '7.5',
        '7.75' => '8-',
        '8' => '8',
        '8.25' => '8+',
        '8.50' => '8.5',
        '8.75' => '9-',
        '9' => '9',
        '9.25' => '9+',
        '9.50' => '9.5',
        '9.75' => '10-',
        '10' => '10',
    ];
}

$missingTables = mastercomAdminMissingTables(['mastercom_classi', 'mastercom_studenti', 'mastercom_docenti']);
$message = '';
$error = '';
$results = [];

$classRows = empty($missingTables) ? mastercomAdminOperationalClassRows('*') : [];
$classesById = [];
foreach ($classRows as $classRow) {
    $classesById[intval($classRow['mastercom_id_classe'] ?? 0)] = $classRow;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'sync_subjects' && empty($missingTables)) {
    @set_time_limit(900);
    @ini_set('max_execution_time', '900');

    $syncClassId = intval($_POST['sync_class_id'] ?? 0);
    $classesToSync = [];
    foreach ($classRows as $classRow) {
        $classId = intval($classRow['mastercom_id_classe'] ?? 0);
        if ($classId <= 0) {
            continue;
        }
        if ($syncClassId > 0 && $classId !== $syncClassId) {
            continue;
        }
        $classesToSync[] = $classId;
    }

    if (empty($classesToSync)) {
        $error = 'Nessuna classe da aggiornare.';
    } else {
        $authResult = mastercomAuthenticateService(['profile' => 'MasterComAuth', 'method' => 'POST', 'timeout' => 180]);
        if (empty($authResult['ok'])) {
            $error = 'Autenticazione MasterCom fallita: ' . ($authResult['error'] ?? '');
        } else {
            $updatedSubjects = 0;
            foreach ($classesToSync as $classId) {
                $subjects = mastercomGradesCacheLoadClassSubjectsFromAdmin($authResult, $classId, ['timeout' => 300]);
                foreach ($subjects as $subject) {
                    $subjectId = intval($subject['mastercom_id_materia'] ?? 0);
                    if ($subjectId <= 0) {
                        continue;
                    }
                    mastercomGradesCacheUpsertSubject($classId, $subjectId, (string)($subject['materia'] ?? ('Materia ' . $subjectId)));
                    $updatedSubjects++;
                }
            }
            $message = 'Materie MasterCom aggiornate: ' . $updatedSubjects . '.';
        }
    }
}

$studentRows = empty($missingTables) ? (dbGetAll("
    SELECT *
    FROM mastercom_studenti
    WHERE mastercom_id_studente IS NOT NULL
      AND mastercom_id_studente > 0
    ORDER BY cognome ASC, nome ASC
") ?: []) : [];
$studentsById = [];
foreach ($studentRows as $studentRow) {
    $studentsById[intval($studentRow['mastercom_id_studente'] ?? 0)] = $studentRow;
}
$studentsByClass = mcGradeRowsByClass($studentRows, 'mastercom_id_classe_corrente');

$subjectRows = [];
if (mastercomAdminTableExists('mastercom_voti_materie')) {
    $subjectRows = array_merge($subjectRows, dbGetAll("
        SELECT DISTINCT mastercom_id_classe, mastercom_id_materia, materia
        FROM mastercom_voti_materie
        WHERE mastercom_id_classe > 0 AND mastercom_id_materia > 0
    ") ?: []);
}
if (mastercomAdminTableExists('mastercom_docenti_classi_materie')) {
    $subjectRows = array_merge($subjectRows, dbGetAll("
        SELECT DISTINCT mastercom_id_classe, mastercom_id_materia, materia
        FROM mastercom_docenti_classi_materie
        WHERE mastercom_id_classe > 0 AND mastercom_id_materia > 0
    ") ?: []);
}
$subjectUnique = [];
foreach ($subjectRows as $subjectRow) {
    $key = intval($subjectRow['mastercom_id_classe'] ?? 0) . ':' . intval($subjectRow['mastercom_id_materia'] ?? 0);
    if ($key === '0:0' || isset($subjectUnique[$key])) {
        continue;
    }
    $subjectUnique[$key] = $subjectRow;
}
$subjectsByClass = mcGradeRowsByClass(array_values($subjectUnique), 'mastercom_id_classe');

$teacherRows = empty($missingTables) ? (dbGetAll("
    SELECT
        m.mastercom_id_user,
        m.nome_visualizzato,
        d.cognome,
        d.nome
    FROM mastercom_docenti m
    LEFT JOIN docente d ON d.id = m.id_docente_gestore
    WHERE m.mastercom_id_user IS NOT NULL
      AND m.mastercom_id_user > 0
    ORDER BY COALESCE(d.cognome, m.nome_visualizzato), COALESCE(d.nome, '')
") ?: []) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) !== 'sync_subjects' && empty($missingTables)) {
    $postedRows = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];
    $validRows = [];
    foreach ($postedRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $hasAny = false;
        foreach (['class_id', 'student_id', 'subject_id', 'teacher_id', 'tipo_voto', 'voto', 'data', 'note'] as $field) {
            if (trim((string)($row[$field] ?? '')) !== '') {
                $hasAny = true;
                break;
            }
        }
        if ($hasAny) {
            $validRows[] = $row;
        }
    }

    if (empty($validRows)) {
        $error = 'Nessun voto da inserire.';
    } else {
        $authResult = mastercomAuthenticateService(['profile' => 'MasterComAuth', 'method' => 'POST', 'timeout' => 60]);
        if (empty($authResult['ok'])) {
            $error = 'Autenticazione MasterCom fallita: ' . ($authResult['error'] ?? '');
        } else {
            $ok = 0;
            $ko = 0;
            foreach ($validRows as $index => $row) {
                try {
                    $payload = mcGradeBuildPayload($row, $studentsById, $classesById);
                    $submit = mastercomSubmitAdminGradeAction($authResult, $payload, ['timeout' => 120]);
                    $hasWarnings = !empty($submit['html_warnings']);
                    $success = !empty($submit['ok']) && !$hasWarnings;
                    $success ? $ok++ : $ko++;
                    $results[] = [
                        'ok' => $success,
                        'label' => 'Riga ' . ($index + 1) . ' - ' . ($payload['cognome_stud'] ?? '') . ' ' . ($payload['nome_stud'] ?? ''),
                        'message' => $success ? 'inserita' : (($submit['error'] ?? '') . (!empty($submit['html_warnings']) ? ' ' . implode(', ', $submit['html_warnings']) : '')),
                    ];
                } catch (Throwable $e) {
                    $ko++;
                    $results[] = [
                        'ok' => false,
                        'label' => 'Riga ' . ($index + 1),
                        'message' => $e->getMessage(),
                    ];
                }
            }
            $message = "Inserimento completato. Voti inseriti: $ok. Errori: $ko.";
        }
    }
}

$today = (new DateTime('today', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
$gradeOptions = mcGradeGradeOptions();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inserimento voti MasterCom</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .mc-grade-toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 12px; }
        .mc-grade-table th, .mc-grade-table td { vertical-align: middle !important; }
        .mc-grade-table select, .mc-grade-table input { min-width: 115px; }
        .mc-grade-note { min-width: 220px; }
        #mc_grade_wait { display:none; position:fixed; inset:0; z-index:9999; background:rgba(255,255,255,.72); align-items:center; justify-content:center; }
        #mc_grade_wait .box { background:#fff; border:1px solid #b7d7e8; border-radius:8px; padding:22px 28px; box-shadow:0 12px 34px rgba(0,0,0,.18); color:#0d5a7a; font-weight:700; text-align:center; }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div id="mc_grade_wait"><div class="box"><span class="glyphicon glyphicon-refresh"></span> Inserimento voti in MasterCom...</div></div>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-pencil"></span>&emsp;Inserimento voti MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Mancano tabelle: <?php echo mcGradeH(implode(', ', $missingTables)); ?>.</div>
            <?php endif; ?>
            <div id="mc_grade_last_result">
                <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo mcGradeH($message); ?></div><?php endif; ?>
                <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo mcGradeH($error); ?></div><?php endif; ?>
                <?php if (!empty($results)): ?>
                    <table class="table table-condensed table-bordered">
                        <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr class="<?php echo !empty($result['ok']) ? 'success' : 'danger'; ?>">
                                <td style="width:120px;"><?php echo !empty($result['ok']) ? 'OK' : 'Errore'; ?></td>
                                <td><?php echo mcGradeH($result['label']); ?></td>
                                <td><?php echo mcGradeH($result['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if (empty($missingTables)): ?>
                <div class="well well-sm">
                    <form method="post" class="form-inline" onsubmit="document.getElementById('mc_grade_wait').style.display='flex';">
                        <input type="hidden" name="action" value="sync_subjects">
                        <div class="form-group">
                            <label>Aggiorna materie da MasterCom&nbsp;</label>
                            <select name="sync_class_id" class="form-control input-sm">
                                <option value="0">Tutte le classi</option>
                                <?php foreach ($classRows as $classRow): ?>
                                    <option value="<?php echo intval($classRow['mastercom_id_classe']); ?>">
                                        <?php echo mcGradeH(($classRow['nome'] ?? '') . ' [' . ($classRow['mastercom_id_classe'] ?? '') . ']'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-info btn-sm">
                            <span class="glyphicon glyphicon-refresh"></span> Aggiorna materie
                        </button>
                        <span class="text-muted" style="margin-left:8px;">Serve quando una classe appena inserita non mostra ancora le materie.</span>
                    </form>
                </div>
                <form method="post" id="mc_grade_form" onsubmit="document.getElementById('mc_grade_wait').style.display='flex';">
                    <div class="mc-grade-toolbar">
                        <button type="button" class="btn btn-success" onclick="mcGradeAddRow();"><span class="glyphicon glyphicon-plus"></span> Aggiungi riga</button>
                        <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-ok"></span> Inserisci voti</button>
                        <span class="text-muted">Compila una o piu righe: possono essere studenti, classi, materie e date diverse.</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mc-grade-table">
                            <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Studente</th>
                                <th>Materia</th>
                                <th>Docente</th>
                                <th>Tipo</th>
                                <th>Voto</th>
                                <th>Data</th>
                                <th>Note</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="mc_grade_rows"></tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-ok"></span> Inserisci voti</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
var mcGradeClasses = <?php echo json_encode(array_values($classRows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var mcGradeStudentsByClass = <?php echo json_encode($studentsByClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var mcGradeSubjectsByClass = <?php echo json_encode($subjectsByClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var mcGradeTeachers = <?php echo json_encode($teacherRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var mcGradeOptions = <?php echo json_encode($gradeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var mcGradeToday = <?php echo json_encode($today); ?>;
var mcGradeRowIndex = 0;

function mcGradeOption(value, text) {
    return $('<option>').val(value).text(text);
}

function mcGradeTeacherLabel(row) {
    var local = $.trim((row.cognome || '') + ' ' + (row.nome || ''));
    return local || row.nome_visualizzato || ('Docente ' + row.mastercom_id_user);
}

function mcGradeRebuildDependent(row, selectedStudentId, selectedSubjectId) {
    var classId = row.find('.js-class').val() || '0';
    var studentSelect = row.find('.js-student').empty().append(mcGradeOption('', 'Studente'));
    $.each(mcGradeStudentsByClass[classId] || [], function (_, student) {
        var option = mcGradeOption(student.mastercom_id_studente, $.trim((student.cognome || '') + ' ' + (student.nome || '')) + ' [' + student.mastercom_id_studente + ']');
        if (selectedStudentId && String(selectedStudentId) === String(student.mastercom_id_studente)) {
            option.prop('selected', true);
        }
        studentSelect.append(option);
    });

    var subjectSelect = row.find('.js-subject').empty().append(mcGradeOption('', 'Materia'));
    $.each(mcGradeSubjectsByClass[classId] || [], function (_, subject) {
        var option = mcGradeOption(subject.mastercom_id_materia, (subject.materia || 'Materia') + ' [' + subject.mastercom_id_materia + ']');
        if (selectedSubjectId && String(selectedSubjectId) === String(subject.mastercom_id_materia)) {
            option.prop('selected', true);
        }
        subjectSelect.append(option);
    });
}

function mcGradeAddRow() {
    var previous = $('#mc_grade_rows tr:last');
    var previousValues = {};
    if (previous.length) {
        previousValues = {
            classId: previous.find('.js-class').val() || '',
            studentId: previous.find('.js-student').val() || '',
            subjectId: previous.find('.js-subject').val() || '',
            teacherId: previous.find('select[name$="[teacher_id]"]').val() || '',
            type: previous.find('select[name$="[tipo_voto]"]').val() || '',
            grade: previous.find('select[name$="[voto]"]').val() || '',
            date: previous.find('input[name$="[data]"]').val() || mcGradeToday,
            note: previous.find('input[name$="[note]"]').val() || ''
        };
    }

    var index = mcGradeRowIndex++;
    var row = $('<tr>');
    var classSelect = $('<select class="form-control input-sm js-class" name="rows[' + index + '][class_id]">').append(mcGradeOption('', 'Classe'));
    $.each(mcGradeClasses, function (_, cls) {
        var option = mcGradeOption(cls.mastercom_id_classe, (cls.nome || '') + ' [' + cls.mastercom_id_classe + ']');
        if (previousValues.classId && String(previousValues.classId) === String(cls.mastercom_id_classe)) {
            option.prop('selected', true);
        }
        classSelect.append(option);
    });
    var studentSelect = $('<select class="form-control input-sm js-student" name="rows[' + index + '][student_id]">').append(mcGradeOption('', 'Studente'));
    var subjectSelect = $('<select class="form-control input-sm js-subject" name="rows[' + index + '][subject_id]">').append(mcGradeOption('', 'Materia'));
    var teacherSelect = $('<select class="form-control input-sm" name="rows[' + index + '][teacher_id]">').append(mcGradeOption('', 'Docente'));
    $.each(mcGradeTeachers, function (_, teacher) {
        var option = mcGradeOption(teacher.mastercom_id_user, mcGradeTeacherLabel(teacher) + ' [' + teacher.mastercom_id_user + ']');
        if (previousValues.teacherId && String(previousValues.teacherId) === String(teacher.mastercom_id_user)) {
            option.prop('selected', true);
        }
        teacherSelect.append(option);
    });
    var typeSelect = $('<select class="form-control input-sm" name="rows[' + index + '][tipo_voto]">')
        .append(mcGradeOption('', 'Tipo'))
        .append(mcGradeOption('0', 'Scritto/Grafico'))
        .append(mcGradeOption('1', 'Orale'))
        .append(mcGradeOption('2', 'Pratico'));
    typeSelect.val(previousValues.type || '');
    var gradeSelect = $('<select class="form-control input-sm" name="rows[' + index + '][voto]">').append(mcGradeOption('', 'Voto'));
    $.each(mcGradeOptions, function (value, label) {
        gradeSelect.append(mcGradeOption(value, label));
    });
    gradeSelect.val(previousValues.grade || '');
    row.append($('<td>').append(classSelect));
    row.append($('<td>').append(studentSelect));
    row.append($('<td>').append(subjectSelect));
    row.append($('<td>').append(teacherSelect));
    row.append($('<td>').append(typeSelect));
    row.append($('<td>').append(gradeSelect));
    row.append($('<td>').append($('<input type="date" class="form-control input-sm" name="rows[' + index + '][data]">').val(previousValues.date || mcGradeToday)));
    row.append($('<td>').append($('<input type="text" class="form-control input-sm mc-grade-note" name="rows[' + index + '][note]">').val(previousValues.note || '')));
    row.append($('<td class="text-center">').append($('<button type="button" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span></button>').on('click', function () { row.remove(); })));
    row.find('.js-class').on('change', function () { mcGradeRebuildDependent(row); });
    $('#mc_grade_rows').append(row);
    if (previousValues.classId) {
        mcGradeRebuildDependent(row, previousValues.studentId, previousValues.subjectId);
    }
}

$(function () {
    mcGradeAddRow();
    if ($('#mc_grade_last_result').children().length > 0) {
        setTimeout(function () {
            $('#mc_grade_last_result').fadeOut(350);
        }, 10000);
    }
});
</script>
</body>
</html>

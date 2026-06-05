<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/l2_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

$message = '';
$error = '';
$selectedL2ClassId = intval($_GET['id_l2_classe'] ?? $_POST['id_l2_classe'] ?? 0);
$weekOf = trim((string)($_GET['week_of'] ?? $_POST['week_of'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'add_class') {
        $result = mastercomL2AddClass((string)($_POST['classe_mbapp'] ?? ''));
        if (!empty($result['ok'])) {
            $message = 'Classe L2 aggiunta.';
        } else {
            $error = trim((string)($result['error'] ?? 'Errore aggiunta classe L2'));
        }
    } elseif ($action === 'delete_class') {
        $result = mastercomL2DeactivateClass(intval($_POST['id_l2_classe'] ?? 0));
        if (!empty($result['ok'])) {
            $message = 'Classe L2 rimossa.';
            if ($selectedL2ClassId === intval($_POST['id_l2_classe'] ?? 0)) {
                $selectedL2ClassId = 0;
            }
        } else {
            $error = trim((string)($result['error'] ?? 'Errore rimozione classe L2'));
        }
    } elseif ($action === 'save_students') {
        $selectedL2ClassId = intval($_POST['id_l2_classe'] ?? 0);
        $result = mastercomL2SaveStudents($selectedL2ClassId, $_POST);
        if (!empty($result['ok'])) {
            $message = 'Studenti L2 aggiornati.';
        } else {
            $error = trim((string)($result['error'] ?? 'Errore salvataggio studenti L2'));
        }
    } elseif ($action === 'add_student') {
        $selectedL2ClassId = intval($_POST['id_l2_classe'] ?? 0);
        $result = mastercomL2AddStudent($selectedL2ClassId, intval($_POST['mastercom_id_studente'] ?? 0));
        if (!empty($result['ok'])) {
            $message = 'Studente aggiunto al gruppo L2.';
        } else {
            $error = trim((string)($result['error'] ?? 'Errore aggiunta studente L2'));
        }
    } elseif ($action === 'remove_student') {
        $selectedL2ClassId = intval($_POST['id_l2_classe'] ?? 0);
        $result = mastercomL2RemoveStudent($selectedL2ClassId, intval($_POST['mastercom_id_studente'] ?? 0));
        if (!empty($result['ok'])) {
            $message = 'Studente tolto dal gruppo L2.';
        } else {
            $error = trim((string)($result['error'] ?? 'Errore rimozione studente L2'));
        }
    } elseif ($action === 'save_student_hours') {
        $selectedL2ClassId = intval($_POST['id_l2_classe'] ?? 0);
        $weekOf = trim((string)($_POST['week_of'] ?? ''));
        $scheduleSlots = mastercomL2LoadScheduleSlotsForClass($selectedL2ClassId, $weekOf);
        $result = mastercomL2SaveStudentHourConfig($selectedL2ClassId, $_POST, $scheduleSlots);
        if (!empty($result['ok'])) {
            $message = 'Ore L2 per studente aggiornate.';
        } else {
            $error = trim((string)($result['error'] ?? 'Errore salvataggio ore L2 per studente'));
        }
    }
}

$missingCoreTables = mastercomAdminMissingTables(['mastercom_studenti', 'mastercom_classi']);
$missingL2Tables = mastercomAdminMissingTables(['mastercom_l2_classi_mbapp', 'mastercom_l2_gruppo_studenti', 'mastercom_l2_appelli', 'mastercom_l2_appello_studenti']);
$mbappClasses = empty($missingL2Tables) ? mastercomL2LoadMbappClasses() : [];
$configuredClasses = empty($missingL2Tables) ? mastercomL2LoadConfiguredClasses(false) : [];
$configuredByName = [];
foreach ($configuredClasses as $classRow) {
    $configuredByName[trim((string)$classRow['classe_mbapp'])] = $classRow;
}
$activeClasses = empty($missingL2Tables) ? mastercomL2LoadConfiguredClasses(true) : [];
if ($selectedL2ClassId <= 0 && !empty($activeClasses)) {
    $selectedL2ClassId = intval($activeClasses[0]['id'] ?? 0);
}
$students = empty($missingCoreTables) && empty($missingL2Tables) && $selectedL2ClassId > 0
    ? mastercomL2LoadStudentsForSelection($selectedL2ClassId)
    : [];
$selectedStudents = array_values(array_filter($students, function ($student) {
    return intval($student['l2_attivo'] ?? 0) === 1;
}));
$availableStudents = array_values(array_filter($students, function ($student) {
    return intval($student['l2_attivo'] ?? 0) !== 1;
}));
$week = mastercomL2WeekContext($weekOf);
$scheduleSlots = empty($missingL2Tables) && $selectedL2ClassId > 0
    ? mastercomL2LoadScheduleSlotsForClass($selectedL2ClassId, $week['reference_date'])
    : [];
$studentHourConfig = empty($missingL2Tables) && $selectedL2ClassId > 0
    ? mastercomL2LoadStudentHourConfig($selectedL2ClassId)
    : [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom L2</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once headerAdminDidatticaPath('../common'); ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-education"></span>&emsp;MasterCom L2
        </div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($missingL2Tables)): ?>
                <div class="alert alert-warning">
                    Mancano le tabelle L2: <?php echo htmlspecialchars(implode(', ', $missingL2Tables)); ?>.
                    Esegui <code>doc/mastercom_l2_schema.sql</code>.
                </div>
            <?php elseif (!empty($missingCoreTables)): ?>
                <div class="alert alert-warning">
                    Mancano le tabelle MasterCom: <?php echo htmlspecialchars(implode(', ', $missingCoreTables)); ?>.
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    In questa fase GestOre gestisce classi L2, studenti e appelli. La generazione automatica degli eventi su MasterCom verra aggiunta nello step successivo.
                </div>
                <?php if (!mastercomL2StudentHoursTableAvailable()): ?>
                    <div class="alert alert-warning">
                        La configurazione delle ore per singolo studente richiede la tabella <code>mastercom_l2_studente_ore</code>.
                        Esegui <code>doc/mastercom_l2_studente_ore_migration.sql</code>. Nel frattempo ogni studente resta previsto in tutte le ore del gruppo.
                    </div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-4">
                        <div class="panel panel-default">
                            <div class="panel-heading"><strong>Gruppi L2 da classi MBApp</strong></div>
                            <div class="panel-body">
                                <form method="post" action="mastercom_l2.php" class="form-inline" style="margin-bottom: 12px;">
                                    <input type="hidden" name="action" value="add_class">
                                    <div class="form-group" style="width: 100%;">
                                        <label for="classe_mbapp">Classe MBApp</label>
                                        <select class="form-control" name="classe_mbapp" id="classe_mbapp" style="width: 100%;">
                                            <option value="">Seleziona classe</option>
                                            <?php foreach ($mbappClasses as $className): ?>
                                                <option value="<?php echo htmlspecialchars($className); ?>"><?php echo htmlspecialchars($className); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Aggiungi gruppo L2</button>
                                </form>

                                <?php if (empty($activeClasses)): ?>
                                    <div class="alert alert-info">Nessun gruppo L2 configurato.</div>
                                <?php else: ?>
                                    <table class="table table-bordered table-condensed">
                                        <thead>
                                            <tr>
                                                <th>Gruppo attivo</th>
                                                <th style="width: 80px; text-align: center;">Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activeClasses as $classRow): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($classRow['classe_mbapp']); ?></strong>
                                                        <?php if (trim((string)($classRow['nome_gruppo'] ?? '')) !== '' && trim((string)$classRow['nome_gruppo']) !== trim((string)$classRow['classe_mbapp'])): ?>
                                                            <br><span class="text-muted"><?php echo htmlspecialchars($classRow['nome_gruppo']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <form method="post" action="mastercom_l2.php" onsubmit="return confirm('Rimuovere questo gruppo L2? Gli appelli gia salvati restano nello storico.');">
                                                            <input type="hidden" name="action" value="delete_class">
                                                            <input type="hidden" name="id_l2_classe" value="<?php echo intval($classRow['id']); ?>">
                                                            <button type="submit" class="btn btn-xs btn-danger">Togli</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="panel panel-default">
                            <div class="panel-heading"><strong>Studenti del gruppo L2</strong></div>
                            <div class="panel-body">
                                <?php if (empty($activeClasses)): ?>
                                    <div class="alert alert-info">Seleziona almeno una classe MBApp da usare per L2.</div>
                                <?php else: ?>
                                    <form method="get" action="mastercom_l2.php" class="form-inline" style="margin-bottom: 12px;">
                                        <div class="form-group">
                                            <label for="id_l2_classe">Gruppo L2&nbsp;</label>
                                            <select class="form-control" name="id_l2_classe" id="id_l2_classe" onchange="this.form.submit()">
                                                <?php foreach ($activeClasses as $classRow): ?>
                                                    <option value="<?php echo intval($classRow['id']); ?>" <?php echo $selectedL2ClassId === intval($classRow['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($classRow['classe_mbapp']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <input type="hidden" name="week_of" value="<?php echo htmlspecialchars($week['reference_date']); ?>">
                                        <noscript><button class="btn btn-default" type="submit">Apri</button></noscript>
                                    </form>
                                    <div class="panel panel-info">
                                        <div class="panel-heading">
                                            <strong>Studenti nel gruppo</strong>
                                            <span class="badge"><?php echo count($selectedStudents); ?></span>
                                        </div>
                                        <div class="panel-body" style="padding: 0;">
                                            <?php if (empty($selectedStudents)): ?>
                                                <div class="alert alert-info" style="margin: 15px;">Nessuno studente inserito nel gruppo selezionato.</div>
                                            <?php else: ?>
                                                <table class="table table-bordered table-striped table-condensed" style="margin-bottom: 0;">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 80px; text-align: center;">Registro</th>
                                                            <th>Studente</th>
                                                            <th style="width: 120px;">Classe</th>
                                                            <th style="width: 80px; text-align: center;">Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($selectedStudents as $student): ?>
                                                            <tr>
                                                                <td style="text-align: center;"><?php echo intval($student['registro_numero'] ?? 0); ?></td>
                                                                <td><strong><?php echo htmlspecialchars(trim((string)(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '')))); ?></strong></td>
                                                                <td><?php echo htmlspecialchars($student['classe_mastercom'] ?? ''); ?></td>
                                                                <td style="text-align: center;">
                                                                    <form method="post" action="mastercom_l2.php" onsubmit="return confirm('Togliere questo studente dal gruppo L2?');">
                                                                        <input type="hidden" name="action" value="remove_student">
                                                                        <input type="hidden" name="id_l2_classe" value="<?php echo intval($selectedL2ClassId); ?>">
                                                                        <input type="hidden" name="mastercom_id_studente" value="<?php echo intval($student['mastercom_id_studente']); ?>">
                                                                        <button type="submit" class="btn btn-xs btn-danger">Togli</button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="panel panel-warning">
                                        <div class="panel-heading">
                                            <strong>Ore da seguire per studente</strong>
                                        </div>
                                        <div class="panel-body">
                                            <form method="get" action="mastercom_l2.php" class="form-inline" style="margin-bottom: 12px;">
                                                <input type="hidden" name="id_l2_classe" value="<?php echo intval($selectedL2ClassId); ?>">
                                                <div class="form-group">
                                                    <label for="week_of">Settimana orario&nbsp;</label>
                                                    <input type="date" class="form-control" name="week_of" id="week_of" value="<?php echo htmlspecialchars($week['reference_date']); ?>">
                                                </div>
                                                <button type="submit" class="btn btn-default">Carica ore</button>
                                            </form>

                                            <?php if (!mastercomL2StudentHoursTableAvailable()): ?>
                                                <div class="alert alert-warning">Configurazione non disponibile: manca la tabella dedicata.</div>
                                            <?php elseif (empty($selectedStudents)): ?>
                                                <div class="alert alert-info">Aggiungi almeno uno studente al gruppo per configurarne le ore.</div>
                                            <?php elseif (empty($scheduleSlots)): ?>
                                                <div class="alert alert-info">Nessuna ora L2 trovata nell'orario della settimana selezionata per questo gruppo.</div>
                                            <?php else: ?>
                                                <form method="post" action="mastercom_l2.php">
                                                    <input type="hidden" name="action" value="save_student_hours">
                                                    <input type="hidden" name="id_l2_classe" value="<?php echo intval($selectedL2ClassId); ?>">
                                                    <input type="hidden" name="week_of" value="<?php echo htmlspecialchars($week['reference_date']); ?>">
                                                    <div class="table-responsive" style="max-height: 520px; overflow: auto;">
                                                        <table class="table table-bordered table-condensed table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th style="min-width: 220px;">Studente</th>
                                                                    <th style="width: 110px;">Classe</th>
                                                                    <?php foreach ($scheduleSlots as $slot): ?>
                                                                        <th style="min-width: 118px; text-align: center;">
                                                                            <?php echo htmlspecialchars(($slot['weekday_label'] ?? '') . ' ' . ($slot['hour'] ?? '')); ?>
                                                                            <br><small><?php echo htmlspecialchars(($slot['hour'] ?? '') . ' - ' . ($slot['end_hour'] ?? '')); ?></small>
                                                                        </th>
                                                                    <?php endforeach; ?>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($selectedStudents as $student): ?>
                                                                    <?php
                                                                    $studentId = intval($student['mastercom_id_studente'] ?? 0);
                                                                    $studentConfig = is_array($studentHourConfig[$studentId] ?? null) ? $studentHourConfig[$studentId] : [];
                                                                    $studentConfigured = !empty($studentConfig['configured']);
                                                                    $studentHours = is_array($studentConfig['hours'] ?? null) ? $studentConfig['hours'] : [];
                                                                    ?>
                                                                    <tr>
                                                                        <td>
                                                                            <input type="hidden" name="configured_students[]" value="<?php echo $studentId; ?>">
                                                                            <strong><?php echo htmlspecialchars(trim((string)(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '')))); ?></strong>
                                                                        </td>
                                                                        <td><?php echo htmlspecialchars($student['classe_mastercom'] ?? ''); ?></td>
                                                                        <?php foreach ($scheduleSlots as $slot): ?>
                                                                            <?php
                                                                            $slotKey = trim((string)($slot['key'] ?? ''));
                                                                            $checked = !$studentConfigured || !empty($studentHours[$slotKey]);
                                                                            ?>
                                                                            <td style="text-align: center;">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    name="student_hours[<?php echo $studentId; ?>][<?php echo htmlspecialchars($slotKey); ?>]"
                                                                                    value="1"
                                                                                    <?php echo $checked ? 'checked' : ''; ?>>
                                                                            </td>
                                                                        <?php endforeach; ?>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <button type="submit" class="btn btn-warning">Salva ore studenti</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <form method="post" action="mastercom_l2.php" id="l2-add-students-form">
                                        <input type="hidden" name="action" value="add_student">
                                        <input type="hidden" name="id_l2_classe" value="<?php echo intval($selectedL2ClassId); ?>">
                                        <div class="form-group">
                                            <label for="student_search">Cerca e aggiungi studente</label>
                                            <input type="text" class="form-control" id="student_search" placeholder="Cerca per nome, cognome o classe">
                                        </div>
                                        <div class="table-responsive" style="max-height: 560px; overflow-y: auto;">
                                            <table class="table table-bordered table-striped table-condensed">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 80px; text-align: center;">Registro</th>
                                                        <th>Studente</th>
                                                        <th style="width: 120px;">Classe</th>
                                                        <th style="width: 90px; text-align: center;">Azioni</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($availableStudents as $student): ?>
                                                        <?php
                                                        $studentSearchText = trim((string)(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '') . ' ' . ($student['classe_mastercom'] ?? '')));
                                                        ?>
                                                        <tr class="l2-student-row" data-search="<?php echo htmlspecialchars(mb_strtolower($studentSearchText, 'UTF-8')); ?>">
                                                            <td style="text-align: center;"><?php echo intval($student['registro_numero'] ?? 0); ?></td>
                                                            <td><strong><?php echo htmlspecialchars(trim((string)(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '')))); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($student['classe_mastercom'] ?? ''); ?></td>
                                                            <td style="text-align: center;">
                                                                <button type="submit" name="mastercom_id_studente" value="<?php echo intval($student['mastercom_id_studente']); ?>" class="btn btn-xs btn-primary">Aggiungi</button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <a href="mastercom_l2_registro.php" class="btn btn-success">Apri registro L2</a>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('student_search');
    if (!input) return;
    input.addEventListener('input', function () {
        var needle = (input.value || '').toLowerCase().trim();
        document.querySelectorAll('.l2-student-row').forEach(function (row) {
            var haystack = row.getAttribute('data-search') || '';
            row.style.display = needle === '' || haystack.indexOf(needle) !== -1 ? '' : 'none';
        });
    });
});
</script>
</body>
</html>

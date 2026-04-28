<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/noirc_lib.php';

ruoloRichiesto('admin');

$weekOf = trim((string)($_GET['week_of'] ?? $_POST['week_of'] ?? ''));
$weekContext = mastercomNoIrcWeekContext($weekOf);
$message = '';
$error = '';
$assignmentId = intval($_GET['edit_id'] ?? $_POST['assignment_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'save_assignment') {
        $saveResult = mastercomNoIrcSaveAssignment($_POST, intval($_POST['assignment_id'] ?? 0));
        if ($saveResult['ok']) {
            $message = 'Assegnazione docente salvata';
            $assignmentId = 0;
        } else {
            $error = trim((string)($saveResult['error'] ?? 'Errore salvataggio assegnazione'));
        }
    } elseif ($action === 'delete_assignment') {
        $deleteResult = mastercomNoIrcDeleteAssignment(intval($_POST['assignment_id'] ?? 0));
        if ($deleteResult['ok']) {
            $message = 'Assegnazione docente eliminata';
            $assignmentId = 0;
        } else {
            $error = trim((string)($deleteResult['error'] ?? 'Errore eliminazione assegnazione'));
        }
    }
}

$optionalMissingTables = mastercomAdminMissingTables(['mastercom_noirc_docenti_assegnazioni']);
$teacherRows = mastercomNoIrcLoadTeacherRows();
$assignments = mastercomAdminTableExists('mastercom_noirc_docenti_assegnazioni')
    ? dbGetAll("
        SELECT
            a.*,
            d.cognome,
            d.nome
        FROM mastercom_noirc_docenti_assegnazioni a
        LEFT JOIN docente d
            ON d.id = a.id_docente
        ORDER BY a.data_inizio DESC, a.giorno_settimana ASC, a.ora ASC, d.cognome ASC, d.nome ASC
    ")
    : [];
$editingAssignment = $assignmentId > 0 && !empty($assignments)
    ? dbGetFirst("SELECT * FROM mastercom_noirc_docenti_assegnazioni WHERE id = " . $assignmentId . " LIMIT 1")
    : null;
$weekdayLabels = mastercomNoIrcWeekdayLabels();
$hours = mastercomNoIrcOrari();
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom NO IRC - Docenti</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-user"></span>&emsp;NO IRC - Assegnazioni docenti
        </div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($optionalMissingTables)): ?>
                <div class="alert alert-warning">
                    Manca la tabella <code>mastercom_noirc_docenti_assegnazioni</code>. Esegui lo script <code>doc/mastercom_noirc_schema.sql</code>.
                </div>
            <?php endif; ?>

            <form method="get" action="mastercom_noirc_assignments.php" class="form-inline" style="margin-bottom: 15px;">
                <div class="form-group">
                    <label for="week_of">Settimana di riferimento&nbsp;</label>
                    <input type="date" class="form-control" name="week_of" id="week_of" value="<?php echo htmlspecialchars($weekContext['reference_date']); ?>">
                </div>
                <button type="submit" class="btn btn-default" style="margin-left: 10px;">Aggiorna</button>
                <a href="mastercom_noirc.php?week_of=<?php echo urlencode($weekContext['reference_date']); ?>" class="btn btn-primary" style="margin-left: 10px;">Torna alla settimana NO IRC</a>
            </form>

            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong><?php echo $editingAssignment ? 'Modifica assegnazione' : 'Nuova assegnazione'; ?></strong>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="mastercom_noirc_assignments.php">
                                <input type="hidden" name="action" value="save_assignment">
                                <input type="hidden" name="assignment_id" value="<?php echo intval($editingAssignment['id'] ?? 0); ?>">
                                <input type="hidden" name="week_of" value="<?php echo htmlspecialchars($weekContext['reference_date']); ?>">

                                <div class="form-group">
                                    <label for="id_docente">Docente</label>
                                    <select class="form-control" name="id_docente" id="id_docente" required>
                                        <option value="0">Seleziona docente</option>
                                        <?php foreach ($teacherRows as $teacherRow): ?>
                                            <option value="<?php echo intval($teacherRow['id']); ?>" <?php echo intval($editingAssignment['id_docente'] ?? 0) === intval($teacherRow['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(trim((string)(($teacherRow['cognome'] ?? '') . ' ' . ($teacherRow['nome'] ?? '')))); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="giorno_settimana">Giorno</label>
                                    <select class="form-control" name="giorno_settimana" id="giorno_settimana" required>
                                        <?php for ($weekday = 1; $weekday <= 6; $weekday++): ?>
                                            <option value="<?php echo $weekday; ?>" <?php echo intval($editingAssignment['giorno_settimana'] ?? 1) === $weekday ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($weekdayLabels[$weekday] ?? (string)$weekday); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="ora">Ora</label>
                                    <select class="form-control" name="ora" id="ora" required>
                                        <?php foreach ($hours as $hour): ?>
                                            <option value="<?php echo htmlspecialchars($hour); ?>" <?php echo trim((string)($editingAssignment['ora'] ?? '')) === $hour ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($hour); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="data_inizio">Data inizio</label>
                                    <input type="date" class="form-control" name="data_inizio" id="data_inizio" value="<?php echo htmlspecialchars($editingAssignment['data_inizio'] ?? $weekContext['week_start']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="data_fine">Data fine</label>
                                    <input type="date" class="form-control" name="data_fine" id="data_fine" value="<?php echo htmlspecialchars($editingAssignment['data_fine'] ?? $weekContext['week_end']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="aula">Aula</label>
                                    <input type="text" class="form-control" name="aula" id="aula" value="<?php echo htmlspecialchars($editingAssignment['aula'] ?? ''); ?>" placeholder="Aula dedicata">
                                </div>

                                <div class="form-group">
                                    <label for="note">Note</label>
                                    <textarea class="form-control" name="note" id="note" rows="3"><?php echo htmlspecialchars($editingAssignment['note'] ?? ''); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary">Salva assegnazione</button>
                                <?php if ($editingAssignment): ?>
                                    <a href="mastercom_noirc_assignments.php?week_of=<?php echo urlencode($weekContext['reference_date']); ?>" class="btn btn-default">Annulla modifica</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Assegnazioni registrate</strong></div>
                        <div class="panel-body" style="padding: 0;">
                            <?php if (empty($assignments)): ?>
                                <div class="alert alert-info" style="margin: 15px;">Nessuna assegnazione docente NO IRC registrata.</div>
                            <?php else: ?>
                                <table class="table table-bordered table-striped table-condensed" style="margin-bottom: 0;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">Docente</th>
                                            <th style="text-align: center;">Giorno</th>
                                            <th style="text-align: center;">Ora</th>
                                            <th style="text-align: center;">Periodo</th>
                                            <th style="text-align: center;">Aula</th>
                                            <th>Note</th>
                                            <th style="text-align: center;">Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <tr>
                                                <td style="text-align: center;"><?php echo htmlspecialchars(trim((string)(($assignment['cognome'] ?? '') . ' ' . ($assignment['nome'] ?? '')))); ?></td>
                                                <td style="text-align: center;"><?php echo htmlspecialchars($weekdayLabels[intval($assignment['giorno_settimana'] ?? 0)] ?? ''); ?></td>
                                                <td style="text-align: center;"><?php echo htmlspecialchars(trim((string)($assignment['ora'] ?? ''))); ?></td>
                                                <td style="text-align: center;">
                                                    <?php echo htmlspecialchars((new DateTime((string)$assignment['data_inizio']))->format('d/m/Y')); ?>
                                                    -
                                                    <?php echo htmlspecialchars((new DateTime((string)$assignment['data_fine']))->format('d/m/Y')); ?>
                                                </td>
                                                <td style="text-align: center;"><?php echo htmlspecialchars(trim((string)($assignment['aula'] ?? ''))); ?></td>
                                                <td><?php echo htmlspecialchars(trim((string)($assignment['note'] ?? ''))); ?></td>
                                                <td style="text-align: center; white-space: nowrap;">
                                                    <a class="btn btn-xs btn-warning" href="mastercom_noirc_assignments.php?week_of=<?php echo urlencode($weekContext['reference_date']); ?>&edit_id=<?php echo intval($assignment['id']); ?>">Modifica</a>
                                                    <form method="post" action="mastercom_noirc_assignments.php" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('Eliminare questa assegnazione?');">
                                                        <input type="hidden" name="action" value="delete_assignment">
                                                        <input type="hidden" name="assignment_id" value="<?php echo intval($assignment['id']); ?>">
                                                        <input type="hidden" name="week_of" value="<?php echo htmlspecialchars($weekContext['reference_date']); ?>">
                                                        <button type="submit" class="btn btn-xs btn-danger">Elimina</button>
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
            </div>
        </div>
    </div>
</div>
</body>
</html>

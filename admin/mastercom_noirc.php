<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/noirc_lib.php';

ruoloRichiesto('admin');

$referenceDate = trim((string)($_GET['week_of'] ?? ''));
$context = mastercomNoIrcBuildWeekSlots($referenceDate);
$optionalMissingTables = mastercomAdminMissingTables(mastercomNoIrcOptionalTables());

function mastercomNoIrcStudentBadge(array $student): string
{
    $choice = trim((string)($student['scelta_sigla'] ?? ''));
    if ($choice === '') {
        $choice = 'n/d';
    }

    $label = trim((string)($student['cognome'] ?? '') . ' ' . trim((string)($student['nome'] ?? '')));
    $classLabel = trim((string)($student['classe'] ?? ''));
    if ($classLabel !== '') {
        $label .= ' (' . $classLabel . ')';
    }

    return htmlspecialchars($label) . ' <span class="label label-default">' . htmlspecialchars($choice) . '</span>';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom NO IRC</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .noirc-grid th,
        .noirc-grid td {
            vertical-align: top !important;
        }
        .noirc-slot-box {
            min-height: 120px;
            font-size: 12px;
            line-height: 1.45;
        }
        .noirc-slot-box .slot-meta {
            margin-bottom: 6px;
        }
        .noirc-slot-box .slot-students {
            max-height: 210px;
            overflow-y: auto;
        }
        .noirc-student-line {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-book"></span>&emsp;NO IRC - Struttura settimanale
        </div>
        <div class="panel-body">
            <?php if (!empty($optionalMissingTables)): ?>
                <div class="alert alert-warning">
                    Le tabelle future per assegnazioni/appelli non sono ancora presenti: <?php echo htmlspecialchars(implode(', ', $optionalMissingTables)); ?>.
                    La vista settimanale funziona comunque; per le assegnazioni esegui lo script <code>doc/mastercom_noirc_schema.sql</code>.
                </div>
            <?php endif; ?>

            <form method="get" action="mastercom_noirc.php" class="form-inline" style="margin-bottom: 15px;">
                <div class="form-group">
                    <label for="week_of">Settimana di riferimento&nbsp;</label>
                    <input type="date" class="form-control" name="week_of" id="week_of" value="<?php echo htmlspecialchars($context['week']['reference_date']); ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Aggiorna</button>
                <a href="mastercom_noirc_assignments.php?week_of=<?php echo urlencode($context['week']['reference_date']); ?>" class="btn btn-default" style="margin-left: 10px;">Gestisci docenti</a>
            </form>

            <div class="alert alert-info">
                Settimana <strong><?php echo htmlspecialchars((new DateTime($context['week']['week_start']))->format('d/m/Y')); ?></strong>
                -
                <strong><?php echo htmlspecialchars((new DateTime($context['week']['week_end']))->format('d/m/Y')); ?></strong>.
                Slot con IRC: <strong><?php echo intval($context['summary']['slots_count']); ?></strong>.
                Studenti distinti NO IRC: <strong><?php echo intval($context['summary']['students_distinct_count']); ?></strong>.
            </div>

            <?php if (empty($context['hours'])): ?>
                <div class="alert alert-info">Nella settimana selezionata non risultano slot IRC nell'orario caricato.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped noirc-grid">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 90px;">Ora</th>
                                <?php foreach ($context['days'] as $dayInfo): ?>
                                    <th style="text-align: center;"><?php echo htmlspecialchars($dayInfo['label']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($context['hours'] as $hour): ?>
                                <tr>
                                    <th style="text-align: center;"><?php echo htmlspecialchars($hour); ?></th>
                                    <?php foreach ($context['days'] as $dayInfo): ?>
                                        <?php $slot = $context['slots'][$dayInfo['date'] . '|' . $hour] ?? null; ?>
                                        <td>
                                            <?php if ($slot === null): ?>
                                                <span class="text-muted">Nessun IRC</span>
                                            <?php else: ?>
                                                <div class="noirc-slot-box">
                                                    <div class="slot-meta">
                                                        <div><strong>Classi IRC:</strong> <?php echo htmlspecialchars(implode(', ', $slot['classi_irc'])); ?></div>
                                                        <div><strong>Studenti NO IRC:</strong> <?php echo count($slot['students']); ?></div>
                                                        <?php if ($slot['excluded_outside_count'] > 0): ?>
                                                            <div><strong>AES esclusi:</strong> <?php echo intval($slot['excluded_outside_count']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if ($slot['unknown_choice_count'] > 0): ?>
                                                            <div><strong>Scelta non definita:</strong> <?php echo intval($slot['unknown_choice_count']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($slot['assignments'])): ?>
                                                            <div>
                                                                <strong>Docenti:</strong>
                                                                <?php echo htmlspecialchars(implode(' | ', array_map(function ($assignment) {
                                                                    $parts = [];
                                                                    if (($assignment['teacher_name'] ?? '') !== '') {
                                                                        $parts[] = $assignment['teacher_name'];
                                                                    }
                                                                    if (($assignment['aula'] ?? '') !== '') {
                                                                        $parts[] = 'aula ' . $assignment['aula'];
                                                                    }
                                                                    return trim(implode(' - ', $parts));
                                                                }, $slot['assignments']))); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-warning"><strong>Docenti:</strong> non assegnati</div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="slot-students">
                                                        <?php if (empty($slot['students'])): ?>
                                                            <span class="text-muted">Nessuno studente in aula alternativa.</span>
                                                        <?php else: ?>
                                                            <?php foreach ($slot['students_by_class'] as $classLabel => $students): ?>
                                                                <div style="margin-bottom: 6px;">
                                                                    <strong><?php echo htmlspecialchars($classLabel); ?></strong>
                                                                </div>
                                                                <?php foreach ($students as $student): ?>
                                                                    <div class="noirc-student-line"><?php echo mastercomNoIrcStudentBadge($student); ?></div>
                                                                <?php endforeach; ?>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Dettaglio slot</strong></div>
                    <div class="panel-body" style="padding: 0;">
                        <table class="table table-bordered table-condensed table-striped" style="margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">Giorno</th>
                                    <th style="text-align: center;">Ora</th>
                                    <th>Classi IRC</th>
                                    <th style="text-align: center;">Studenti NO IRC</th>
                                    <th>Docenti assegnati</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($context['slots'] as $slot): ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($context['days'][$slot['date']]['label'] ?? $slot['date']); ?></td>
                                        <td style="text-align: center;"><?php echo htmlspecialchars($slot['hour']); ?></td>
                                        <td><?php echo htmlspecialchars(implode(', ', $slot['classi_irc'])); ?></td>
                                        <td style="text-align: center;"><?php echo count($slot['students']); ?></td>
                                        <td>
                                            <?php if (empty($slot['assignments'])): ?>
                                                <span class="text-warning">Non assegnato</span>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars(implode(' | ', array_map(function ($assignment) {
                                                    $parts = [];
                                                    if (($assignment['teacher_name'] ?? '') !== '') {
                                                        $parts[] = $assignment['teacher_name'];
                                                    }
                                                    if (($assignment['aula'] ?? '') !== '') {
                                                        $parts[] = 'aula ' . $assignment['aula'];
                                                    }
                                                    return implode(' - ', $parts);
                                                }, $slot['assignments']))); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/noirc_lib.php';

ruoloRichiesto('admin');

$referenceDate = trim((string)($_GET['week_of'] ?? ''));
$context = mastercomNoIrcBuildWeekSlots($referenceDate);
$optionalMissingTables = mastercomAdminMissingTables(mastercomNoIrcOptionalTables());

function mastercomNoIrcChoiceTooltip(string $choice): string
{
    $map = [
        'ASD' => 'Attività di Studio e/o di Ricerche individuali con assistenza di personale docente',
        'LAS' => 'Libera Attività di Studio e/o ricerca individuale senza assistenza di personale docente',
        'AES' => 'Allontanarsi o assentarsi da Edificio Scolastico',
        'n/d' => 'Scelta non definita'
    ];

    return $map[$choice] ?? $choice;
}

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

    $tooltip = mastercomNoIrcChoiceTooltip($choice);

    return htmlspecialchars($label)
        . ' <span class="label label-default" data-toggle="tooltip" data-placement="top" title="'
        . htmlspecialchars($tooltip)
        . '">'
        . htmlspecialchars($choice)
        . '</span>';
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
        .noirc-group-box {
            border: 1px solid #d9edf7;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 8px;
            background: #fcfdff;
        }
        .noirc-group-box.unassigned {
            border-color: #f0ad4e;
            background: #fffaf2;
        }
        .noirc-group-title {
            font-size: 13px;
            margin-bottom: 6px;
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
                <a href="mastercom_noirc_rooms.php?week_of=<?php echo urlencode($context['week']['reference_date']); ?>" class="btn btn-default" style="margin-left: 10px;">Setup aule</a>
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
                                                            <div><strong>Fuori edificio (AES):</strong> <?php echo intval($slot['excluded_outside_count']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if ($slot['unknown_choice_count'] > 0): ?>
                                                            <div><strong>Scelta non definita:</strong> <?php echo intval($slot['unknown_choice_count']); ?></div>
                                                        <?php endif; ?>
                                                        <div><strong>Gruppi/Aule:</strong> <?php echo count($slot['group_buckets']); ?></div>
                                                    </div>
                                                    <div class="slot-students">
                                                        <?php if (empty($slot['students'])): ?>
                                                            <span class="text-muted">Nessuno studente in aula alternativa.</span>
                                                        <?php else: ?>
                                                            <?php foreach ($slot['group_buckets'] as $bucket): ?>
                                                                <?php
                                                                $boxClass = $bucket['type'] === 'unassigned' ? 'noirc-group-box unassigned' : 'noirc-group-box';
                                                                $groupMeta = [];
                                                                if (trim((string)($bucket['teacher_name'] ?? '')) !== '') {
                                                                    $groupMeta[] = (string)$bucket['teacher_name'];
                                                                }
                                                                if (trim((string)($bucket['aula'] ?? '')) !== '') {
                                                                    $groupMeta[] = 'aula ' . trim((string)$bucket['aula']);
                                                                }
                                                                if (intval($bucket['capienza_massima'] ?? 0) > 0) {
                                                                    $groupMeta[] = 'capienza ' . intval($bucket['capienza_massima']);
                                                                }
                                                                ?>
                                                                <div class="<?php echo $boxClass; ?>">
                                                                    <div class="noirc-group-title">
                                                                        <strong>
                                                                            <?php echo htmlspecialchars(($bucket['type'] ?? '') === 'room_setup'
                                                                                ? trim((string)($bucket['group_label'] ?? 'Aula'))
                                                                                : ('Gruppo ' . trim((string)($bucket['group_label'] ?? 'A')))); ?>
                                                                        </strong>
                                                                        <?php if (!empty($groupMeta)): ?>
                                                                            - <?php echo htmlspecialchars(implode(' - ', $groupMeta)); ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <?php if (trim((string)($bucket['class_filters_raw'] ?? '')) !== ''): ?>
                                                                        <div><strong>Classi assegnate:</strong> <?php echo htmlspecialchars(trim((string)$bucket['class_filters_raw'])); ?></div>
                                                                    <?php endif; ?>
                                                                    <?php if (trim((string)($bucket['note'] ?? '')) !== ''): ?>
                                                                        <div><strong>Note:</strong> <?php echo htmlspecialchars(trim((string)$bucket['note'])); ?></div>
                                                                    <?php endif; ?>
                                                                    <div><strong>Studenti:</strong> <?php echo count($bucket['students']); ?></div>
                                                                    <?php if (empty($bucket['students'])): ?>
                                                                        <div class="text-muted">Nessuno studente assegnato a questo gruppo.</div>
                                                                    <?php else: ?>
                                                                        <?php
                                                                        $studentsByClass = [];
                                                                        foreach ($bucket['students'] as $student) {
                                                                            $studentsByClass[$student['classe'] ?? ''][] = $student;
                                                                        }
                                                                        ksort($studentsByClass);
                                                                        ?>
                                                                        <?php foreach ($studentsByClass as $classLabel => $students): ?>
                                                                            <div style="margin: 6px 0 4px;">
                                                                                <strong><?php echo htmlspecialchars((string)$classLabel); ?></strong>
                                                                            </div>
                                                                            <?php foreach ($students as $student): ?>
                                                                                <div class="noirc-student-line"><?php echo mastercomNoIrcStudentBadge($student); ?></div>
                                                                            <?php endforeach; ?>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </div>
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
                                    <th>Gruppi / Docenti</th>
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
                                            <?php if (empty($slot['group_buckets'])): ?>
                                                <span class="text-warning">Non assegnato</span>
                                            <?php else: ?>
                                                <?php foreach ($slot['group_buckets'] as $bucket): ?>
                                                    <?php
                                                    $parts = [];
                                                    $parts[] = ($bucket['type'] ?? '') === 'room_setup'
                                                        ? trim((string)($bucket['group_label'] ?? 'Aula'))
                                                        : ('Gruppo ' . trim((string)($bucket['group_label'] ?? 'A')));
                                                    if (trim((string)($bucket['teacher_name'] ?? '')) !== '') {
                                                        $parts[] = trim((string)$bucket['teacher_name']);
                                                    }
                                                    if (trim((string)($bucket['aula'] ?? '')) !== '') {
                                                        $parts[] = 'aula ' . trim((string)$bucket['aula']);
                                                    }
                                                    $parts[] = count($bucket['students']) . ' studenti';
                                                    ?>
                                                    <div><?php echo htmlspecialchars(implode(' - ', $parts)); ?></div>
                                                <?php endforeach; ?>
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

<script>
$(function () {
    $('[data-toggle="tooltip"]').tooltip({
        container: 'body'
    });
});
</script>

</body>
</html>

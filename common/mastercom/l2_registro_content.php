<?php

$l2IsAdmin = !empty($l2IsAdmin);
$l2CurrentDocenteId = intval($l2CurrentDocenteId ?? 0);
$l2ActionUrl = trim((string)($l2ActionUrl ?? ''));
$weekOf = trim((string)($_GET['week_of'] ?? $_POST['week_of'] ?? ''));
$week = mastercomL2WeekContext($weekOf);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mastercom_apply'])) {
    $postClassId = intval($_POST['id_l2_classe'] ?? 0);
    $postDate = mastercomL2NormDate((string)($_POST['data_giorno'] ?? ''));
    $postStartHour = mastercomL2NormHour($_POST['ora'] ?? '');
    $applyParts = explode('|', (string)($_POST['mastercom_apply'] ?? ''), 2);
    $postStudentId = intval($applyParts[0] ?? 0);
    $postSyncHour = mastercomL2NormHour($applyParts[1] ?? '');
    $slotEnd = mastercomL2SlotEnd($postSyncHour);
    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
    $isPastSlot = $postDate < $now->format('Y-m-d') || ($postDate === $now->format('Y-m-d') && $slotEnd !== '' && $slotEnd <= $now->format('H:i'));
    $canApplyPost = $l2IsAdmin || (!$isPastSlot && mastercomL2LessonByRequest($postClassId, $postDate, $postStartHour, $l2CurrentDocenteId) !== null);

    if (!$canApplyPost) {
        $error = 'Non puoi aggiornare MasterCom per questo appello L2.';
    } elseif ($postStudentId <= 0 || $postSyncHour === '') {
        $error = 'Studente o ora L2 non validi per aggiornare MasterCom.';
    } else {
        $studentsToSync = mastercomL2LoadStudentsForClass($postClassId);
        $studentToSync = null;
        foreach ($studentsToSync as $candidateStudent) {
            if (intval($candidateStudent['mastercom_id_studente'] ?? 0) === $postStudentId) {
                $studentToSync = $candidateStudent;
                break;
            }
        }

        if ($studentToSync === null) {
            $error = 'Studente non trovato nel gruppo L2 selezionato.';
        } else {
            $studentHourConfig = mastercomL2LoadStudentHourConfig($postClassId);
            if (!mastercomL2StudentExpectedForHour($studentHourConfig, $postStudentId, $postDate, $postSyncHour)) {
                $error = 'Lo studente non e configurato per questa ora L2.';
            } else {
                $postedStates = is_array($_POST['stato'] ?? null) ? $_POST['stato'] : [];
                $selectedL2State = strtoupper(trim((string)($postedStates[$postStudentId][$postSyncHour] ?? '')));
                if (!in_array($selectedL2State, ['PRESENTE', 'ASSENTE', 'RITARDO', 'USCITA'], true)) {
                    $selectedL2State = 'PRESENTE';
                }
                $presenceByHour = mastercomL2LoadPresenceMaps([$studentToSync], $postDate, [$postSyncHour]);
                $presenceRow = is_array($presenceByHour[$postSyncHour][$postStudentId] ?? null)
                    ? $presenceByHour[$postSyncHour][$postStudentId]
                    : ['stato' => 'NON_VERIFICATO'];
                $plan = mastercomL2PlanMastercomAction($studentToSync, $presenceRow, $selectedL2State, $postDate, $postSyncHour);
                $executeResult = mastercomL2ExecuteMastercomAction($plan);
                if (!empty($executeResult['ok'])) {
                    $studentLabel = trim((string)($studentToSync['cognome'] ?? '') . ' ' . (string)($studentToSync['nome'] ?? ''));
                    $message = 'Azione MasterCom inviata per ' . $studentLabel . ': ' . trim((string)($plan['summary'] ?? ''));
                } else {
                    $error = trim((string)($executeResult['error'] ?? 'Azione MasterCom non riuscita'));
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'save_appeal' && !isset($_POST['mastercom_apply'])) {
    $postClassId = intval($_POST['id_l2_classe'] ?? 0);
    $postDate = mastercomL2NormDate((string)($_POST['data_giorno'] ?? ''));
    $postHour = mastercomL2NormHour($_POST['ora'] ?? '');
    $canSavePost = $l2IsAdmin || mastercomL2LessonByRequest($postClassId, $postDate, $postHour, $l2CurrentDocenteId) !== null;
    if (!$canSavePost) {
        $error = 'Non puoi modificare questo appello L2 perche non risulta assegnato a te.';
    } else {
        $save = mastercomL2SaveAppeal($_POST, $l2CurrentDocenteId);
        if (!empty($save['ok'])) {
            $message = 'Appello L2 salvato.';
        } else {
            $error = trim((string)($save['error'] ?? 'Errore salvataggio appello L2'));
        }
    }
}

$missingTables = mastercomL2MissingTables();
$selectedClassId = intval($_GET['id_l2_classe'] ?? $_POST['id_l2_classe'] ?? 0);
$selectedDate = mastercomL2NormDate((string)($_GET['data_giorno'] ?? $_POST['data_giorno'] ?? $week['reference_date']));
$selectedHour = mastercomL2NormHour($_GET['ora'] ?? $_POST['ora'] ?? '');
$lessons = empty($missingTables) ? mastercomL2LoadLessons($week['week_start'], $week['week_end'], $l2IsAdmin ? 0 : $l2CurrentDocenteId) : [];
$lessonBlocks = mastercomL2BuildLessonBlocks($lessons);
$blocksByDateHour = mastercomL2BlocksByDateHour($lessonBlocks);
$appealSummaryByBlock = [];
foreach ($lessonBlocks as $block) {
    $summaryKey = intval($block['id_l2_classe'] ?? 0) . '|' . ($block['date'] ?? '') . '|' . ($block['start_hour'] ?? '');
    $appealSummaryByBlock[$summaryKey] = mastercomL2LoadAppealSummary(intval($block['id_l2_classe'] ?? 0), (string)($block['date'] ?? ''), (string)($block['start_hour'] ?? ''));
}
$weekDays = mastercomL2WeekDays($week['week_start']);
$hours = mastercomL2Orari();
$teacherDebug = (!$l2IsAdmin && haRuolo('admin')) ? mastercomL2TeacherLookup($l2CurrentDocenteId) : null;
$activeL2ClassesDebug = (!$l2IsAdmin && haRuolo('admin')) ? mastercomL2LoadConfiguredClasses(true) : [];
$selectedLesson = null;
foreach ($lessonBlocks as $block) {
    if (intval($block['id_l2_classe'] ?? 0) === $selectedClassId
        && (string)($block['date'] ?? '') === $selectedDate
        && (string)($block['start_hour'] ?? '') === $selectedHour) {
        $selectedLesson = $block;
        break;
    }
}
$students = $selectedClassId > 0 ? mastercomL2LoadStudentsForClass($selectedClassId) : [];
$appeal = $selectedClassId > 0 && $selectedHour !== '' ? mastercomL2LoadAppeal($selectedClassId, $selectedDate, $selectedHour) : null;
$appealRows = $appeal ? mastercomL2LoadAppealRows(intval($appeal['id'])) : [];
$canEdit = $l2IsAdmin || $selectedLesson !== null;
$showAppeal = $selectedClassId > 0 && $selectedHour !== '';
$hasPerHourStudentAppeal = mastercomAdminTableColumnExists('mastercom_l2_appello_studenti', 'ora_inizio')
    || mastercomAdminTableColumnExists('mastercom_l2_appello_studenti', 'ora');
$selectedBlockHours = is_array($selectedLesson['hours'] ?? null) && !empty($selectedLesson['hours'])
    ? array_values($selectedLesson['hours'])
    : [$selectedHour];
$studentHourConfig = mastercomL2LoadStudentHourConfig($selectedClassId);
if ($selectedClassId > 0 && $selectedHour !== '' && !empty($students)) {
    $students = array_values(array_filter($students, function ($student) use ($studentHourConfig, $selectedDate, $selectedBlockHours) {
        return mastercomL2StudentExpectedForAnyHour($studentHourConfig, intval($student['mastercom_id_studente'] ?? 0), $selectedDate, $selectedBlockHours);
    }));
}
$mastercomPresenceByHour = $selectedClassId > 0 && !empty($students) && !empty($selectedBlockHours)
    ? mastercomL2LoadPresenceMaps($students, $selectedDate, $selectedBlockHours)
    : [];
$now = new DateTime('now');
$today = $now->format('Y-m-d');
$currentTime = $now->format('H:i');
$hourEditableMap = [];
foreach ($selectedBlockHours as $blockHour) {
    $slotEnd = mastercomL2SlotEnd((string)$blockHour);
    $isPastSlot = $selectedDate < $today || ($selectedDate === $today && $slotEnd !== '' && $slotEnd <= $currentTime);
    $hourEditableMap[$blockHour] = $l2IsAdmin || !$isPastSlot;
}
$hasEditableHour = in_array(true, $hourEditableMap, true);
$prevWeek = (new DateTime($week['week_start']))->modify('-7 days')->format('Y-m-d');
$nextWeek = (new DateTime($week['week_start']))->modify('+7 days')->format('Y-m-d');
$baseQueryParts = [];
if (!$l2IsAdmin && $l2CurrentDocenteId > 0) {
    $baseQueryParts['docente_id'] = $l2CurrentDocenteId;
}
$baseWeekUrl = $l2ActionUrl . '?' . http_build_query(array_merge($baseQueryParts, ['week_of' => $week['reference_date']]));
?>
<style>
    .l2-week-grid th,
    .l2-week-grid td {
        vertical-align: top !important;
    }
    .l2-week-grid .l2-hour {
        width: 76px;
        text-align: center;
        white-space: nowrap;
        background: #f7f7f7;
    }
    .l2-slot {
        min-height: 72px;
    }
    .l2-lesson-card {
        display: block;
        border: 2px solid #2b7bbb;
        border-left-width: 7px;
        border-radius: 6px;
        padding: 8px 10px;
        margin-bottom: 6px;
        background: #f8fbff;
        color: #1b2f45;
        text-decoration: none;
    }
    .l2-lesson-card:hover,
    .l2-lesson-card:focus {
        text-decoration: none;
        background: #eef7ff;
        color: #0f253a;
    }
    .l2-lesson-title {
        font-weight: 700;
    }
    .l2-lesson-meta {
        font-size: 12px;
        color: #4f6478;
        margin-top: 4px;
    }
    .l2-card-summary {
        margin-top: 6px;
    }
    .l2-card-pill {
        display: inline-block;
        border-radius: 10px;
        padding: 2px 7px;
        margin: 2px 3px 0 0;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
    }
    .l2-card-pill-presenti,
    .l2-card-pill-ritardi {
        background: #43a047;
    }
    .l2-card-pill-assenti,
    .l2-card-pill-uscite {
        background: #d32f2f;
    }
    .l2-student-photo {
        width: 58px;
        height: 72px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #ddd;
        background: #f5f5f5;
    }
    .l2-student-photo-placeholder {
        width: 58px;
        height: 72px;
        border-radius: 4px;
        border: 1px solid #ddd;
        background: #f5f5f5;
        color: #999;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
    }
    .l2-status-badges {
        margin-top: 5px;
    }
    .l2-status-badge {
        display: inline-block;
        border-radius: 4px;
        padding: 3px 6px;
        margin: 2px 3px 0 0;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        white-space: nowrap;
    }
    .l2-status-present,
    .l2-status-presente,
    .l2-status-ritardo {
        background: #43a047;
    }
    .l2-status-assente,
    .l2-status-uscita {
        background: #d32f2f;
    }
    .l2-mastercom-cell .label {
        display: inline-block;
        margin: 0 4px 4px 0;
    }
    .l2-mastercom-detail {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 6px;
    }
    .l2-mastercom-action {
        margin-top: 6px;
        font-size: 12px;
        color: #6b7280;
    }
    .l2-mastercom-action .btn {
        margin-top: 5px;
    }
    #l2_loading_overlay {
        display: none;
        position: fixed;
        z-index: 9999;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.78);
        align-items: center;
        justify-content: center;
    }
    #l2_loading_overlay.is-visible {
        display: flex;
    }
    .l2-loading-box {
        min-width: 260px;
        max-width: 420px;
        padding: 20px 24px;
        border-radius: 6px;
        background: #fff;
        border: 1px solid #d9e2ec;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.16);
        text-align: center;
        color: #1f2937;
        font-weight: 700;
    }
    .l2-loading-spinner {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 4px solid #dbeafe;
        border-top-color: #1d4ed8;
        margin: 0 auto 12px;
        animation: l2-spin 0.8s linear infinite;
    }
    .l2-loading-subtitle {
        margin-top: 6px;
        font-size: 13px;
        font-weight: 400;
        color: #64748b;
    }
    @keyframes l2-spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>
<div id="l2_loading_overlay" aria-live="polite" aria-busy="true">
    <div class="l2-loading-box">
        <div class="l2-loading-spinner"></div>
        <div id="l2_loading_title">Caricamento in corso...</div>
        <div id="l2_loading_subtitle" class="l2-loading-subtitle">Sto preparando il registro L2.</div>
    </div>
</div>
<div class="panel panel-teal4">
    <div class="panel-heading">
        <span class="glyphicon glyphicon-check"></span>&emsp;Registro L2
    </div>
    <div class="panel-body">
        <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($missingTables)): ?>
            <div class="alert alert-warning">
                Mancano le tabelle L2: <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.
                Esegui <code>doc/mastercom_l2_schema.sql</code>.
            </div>
        <?php else: ?>
            <?php if (!$showAppeal): ?>
                <div class="clearfix" style="margin-bottom: 15px;">
                    <div class="pull-left">
                        <a class="btn btn-default" href="<?php echo htmlspecialchars($l2ActionUrl . '?' . http_build_query(array_merge($baseQueryParts, ['week_of' => $prevWeek]))); ?>">
                            <span class="glyphicon glyphicon-chevron-left"></span> Settimana precedente
                        </a>
                    </div>
                    <form method="get" action="<?php echo htmlspecialchars($l2ActionUrl); ?>" class="form-inline pull-left" style="margin-left: 10px;">
                        <?php foreach ($baseQueryParts as $key => $value): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars((string)$value); ?>">
                        <?php endforeach; ?>
                        <div class="form-group">
                            <label for="week_of">Settimana&nbsp;</label>
                            <input type="date" class="form-control" name="week_of" id="week_of" value="<?php echo htmlspecialchars($week['reference_date']); ?>">
                        </div>
                        <button type="submit" class="btn btn-default" style="margin-left: 10px;">Vai</button>
                    </form>
                    <div class="pull-right">
                        <a class="btn btn-default" href="<?php echo htmlspecialchars($l2ActionUrl . '?' . http_build_query(array_merge($baseQueryParts, ['week_of' => $nextWeek]))); ?>">
                            Settimana successiva <span class="glyphicon glyphicon-chevron-right"></span>
                        </a>
                    </div>
                </div>

                <?php if (empty($lessonBlocks)): ?>
                    <div class="alert alert-info">Nessuna ora L2 nella settimana selezionata.</div>
                                <?php if (!$l2IsAdmin && haRuolo('admin')): ?>
                                    <div class="alert alert-warning">
                                        <strong>Diagnostica admin</strong><br>
                                        Docente: <?php echo htmlspecialchars(trim((string)(($teacherDebug['name'] ?? '') . ' #' . ($teacherDebug['id'] ?? 0)))); ?><br>
                                        Username GestOre: <?php echo htmlspecialchars($teacherDebug['username'] ?? ''); ?><br>
                                        Email: <?php echo htmlspecialchars($teacherDebug['email'] ?? ''); ?><br>
                                        Username cercati in MBApp: <?php echo htmlspecialchars(implode(', ', $teacherDebug['candidates'] ?? [])); ?><br>
                                        Settimana: <?php echo htmlspecialchars($week['week_start'] . ' - ' . $week['week_end']); ?><br>
                                        Classi L2 attive: <?php echo htmlspecialchars(implode(', ', array_map(function ($row) {
                                            return trim((string)($row['classe_mbapp'] ?? ''));
                                        }, $activeL2ClassesDebug))); ?>
                                    </div>
                                <?php endif; ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered l2-week-grid">
                            <thead>
                                <tr>
                                    <th class="l2-hour">Ora</th>
                                    <?php foreach ($weekDays as $day): ?>
                                        <th>
                                            <?php echo htmlspecialchars($day['label']); ?><br>
                                            <span class="text-muted"><?php echo htmlspecialchars($day['short']); ?></span>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hours as $hour): ?>
                                    <tr>
                                        <th class="l2-hour"><?php echo htmlspecialchars($hour); ?></th>
                                        <?php foreach ($weekDays as $day): ?>
                                            <td>
                                                <div class="l2-slot">
                                                    <?php foreach (($blocksByDateHour[$day['date'] . '|' . $hour] ?? []) as $block): ?>
                                                        <?php
                                                        $query = array_merge($baseQueryParts, [
                                                            'week_of' => $week['reference_date'],
                                                            'id_l2_classe' => intval($block['id_l2_classe']),
                                                            'data_giorno' => (string)$block['date'],
                                                            'ora' => (string)$block['start_hour'],
                                                        ]);
                                                        $href = $l2ActionUrl . '?' . http_build_query($query);
                                                        $summaryKey = intval($block['id_l2_classe'] ?? 0) . '|' . ($block['date'] ?? '') . '|' . ($block['start_hour'] ?? '');
                                                        $summary = $appealSummaryByBlock[$summaryKey] ?? ['done' => false];
                                                        $absentTooltip = implode("\n", $summary['assenti_names'] ?? []);
                                                        ?>
                                                        <a class="l2-lesson-card" href="<?php echo htmlspecialchars($href); ?>">
                                                            <div class="l2-lesson-title"><?php echo htmlspecialchars($block['classe_mbapp']); ?></div>
                                                            <div><?php echo htmlspecialchars(trim((string)(($block['sigla_materia'] ?? '') . ' ' . ($block['nome_materia'] ?? '')))); ?></div>
                                                            <div class="l2-lesson-meta">
                                                                <?php echo htmlspecialchars(($block['display_hour'] ?? $block['start_hour']) . ' - ' . mastercomL2SlotEnd((string)($block['display_hour'] ?? $block['start_hour']))); ?>
                                                                <?php if (intval($block['span'] ?? 1) > 1): ?>
                                                                    · appello unico <?php echo htmlspecialchars($block['start_hour'] . ' - ' . $block['end_hour']); ?>
                                                                <?php endif; ?>
                                                                <?php if (trim((string)($block['aula'] ?? '')) !== ''): ?>
                                                                    · aula <?php echo htmlspecialchars($block['aula']); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!empty($summary['done'])): ?>
                                                                <div class="l2-card-summary">
                                                                    <span class="l2-card-pill l2-card-pill-presenti">P <?php echo intval($summary['presenti'] ?? 0); ?></span>
                                                                    <?php if (intval($summary['assenti'] ?? 0) > 0): ?>
                                                                        <span class="l2-card-pill l2-card-pill-assenti" title="<?php echo htmlspecialchars($absentTooltip); ?>">A <?php echo intval($summary['assenti']); ?></span>
                                                                    <?php endif; ?>
                                                                    <?php if (intval($summary['ritardi'] ?? 0) > 0): ?>
                                                                        <span class="l2-card-pill l2-card-pill-ritardi">R <?php echo intval($summary['ritardi']); ?></span>
                                                                    <?php endif; ?>
                                                                    <?php if (intval($summary['uscite'] ?? 0) > 0): ?>
                                                                        <span class="l2-card-pill l2-card-pill-uscite" title="<?php echo htmlspecialchars($absentTooltip); ?>">U <?php echo intval($summary['uscite']); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p>
                    <a class="btn btn-default" href="<?php echo htmlspecialchars($baseWeekUrl); ?>">
                        <span class="glyphicon glyphicon-chevron-left"></span> Torna all'orario settimanale
                    </a>
                </p>
                <?php if ($selectedLesson === null): ?>
                    <div class="alert alert-warning">La lezione L2 selezionata non risulta disponibile per questo docente nella settimana corrente.</div>
                <?php else: ?>
                        <?php if (!$hasPerHourStudentAppeal && count($selectedBlockHours) > 1): ?>
                            <div class="alert alert-warning">
                                Per gestire stati diversi nelle singole ore del blocco devi aggiornare la tabella con <code>doc/mastercom_l2_appello_ore_migration.sql</code>.
                            </div>
                        <?php endif; ?>
                        <form method="post" action="<?php echo htmlspecialchars($l2ActionUrl); ?>">
                            <input type="hidden" name="action" value="save_appeal">
                            <input type="hidden" name="week_of" value="<?php echo htmlspecialchars($week['reference_date']); ?>">
                            <input type="hidden" name="id_l2_classe" value="<?php echo intval($selectedClassId); ?>">
                            <input type="hidden" name="data_giorno" value="<?php echo htmlspecialchars($selectedDate); ?>">
                            <input type="hidden" name="ora" value="<?php echo htmlspecialchars($selectedHour); ?>">
                            <input type="hidden" name="ora_fine" value="<?php echo htmlspecialchars($selectedLesson['end_hour'] ?? ''); ?>">
                            <input type="hidden" name="aula" value="<?php echo htmlspecialchars($selectedLesson['aula'] ?? ($appeal['aula'] ?? '')); ?>">
                            <?php if (!$l2IsAdmin && $l2CurrentDocenteId > 0): ?>
                                <input type="hidden" name="docente_id" value="<?php echo intval($l2CurrentDocenteId); ?>">
                            <?php endif; ?>

                            <div class="alert alert-info">
                                <strong><?php echo htmlspecialchars((new DateTime($selectedDate))->format('d/m/Y') . ' - ' . $selectedHour); ?></strong>
                                <?php if ($selectedLesson): ?>
                                    &nbsp; Classe L2 <strong><?php echo htmlspecialchars($selectedLesson['classe_mbapp']); ?></strong>
                                    <?php if (trim((string)($selectedLesson['end_hour'] ?? '')) !== ''): ?>
                                        - fino alle <?php echo htmlspecialchars($selectedLesson['end_hour']); ?>
                                    <?php endif; ?>
                                    <?php if (trim((string)$selectedLesson['aula']) !== ''): ?> - aula <?php echo htmlspecialchars($selectedLesson['aula']); ?><?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!$canEdit): ?>
                                <div class="alert alert-warning">Puoi vedere lo stato dell'appello, ma non modificarlo perche' questa ora non risulta assegnata a te nell'orario L2.</div>
                            <?php endif; ?>

                            <?php if (empty($students)): ?>
                                <div class="alert alert-warning">Nessuno studente previsto per questa ora L2.</div>
                            <?php else: ?>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 76px; text-align: center;">Foto</th>
                                            <th style="width: 80px; text-align: center;">Registro</th>
                                            <th>Studente</th>
                                            <th style="width: 120px;">Classe</th>
                                            <th style="width: 210px;">MasterCom</th>
                                            <?php foreach ($selectedBlockHours as $blockHour): ?>
                                                <th style="width: 160px;">
                                                    <?php echo htmlspecialchars($blockHour . ' - ' . mastercomL2SlotEnd((string)$blockHour)); ?>
                                                </th>
                                            <?php endforeach; ?>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <?php
                                            $studentId = intval($student['mastercom_id_studente'] ?? 0);
                                            $studentExpectedHours = array_values(array_filter($selectedBlockHours, function ($blockHour) use ($studentHourConfig, $studentId, $selectedDate) {
                                                return mastercomL2StudentExpectedForHour($studentHourConfig, $studentId, $selectedDate, (string)$blockHour);
                                            }));
                                            $studentNoteHour = $studentExpectedHours[0] ?? ($selectedBlockHours[0] ?? '');
                                            $studentAppealRows = $appealRows[$studentId] ?? [];
                                            $legacyRow = isset($studentAppealRows['stato']) ? $studentAppealRows : null;
                                            $firstHourRow = is_array($studentAppealRows) && isset($studentAppealRows[$studentNoteHour])
                                                ? $studentAppealRows[$studentNoteHour]
                                                : $legacyRow;
                                            $studentNote = trim((string)($firstHourRow['note'] ?? ''));
                                            ?>
                                            <tr>
                                                <?php
                                                $badgeStates = [];
                                                $badgeLabels = [
                                                    'PRESENTE' => 'Presente',
                                                    'ASSENTE' => 'Assente',
                                                    'RITARDO' => 'Entrata in ritardo',
                                                    'USCITA' => 'Uscita anticipata',
                                                ];
                                                $badgeShortLabels = [
                                                    'PRESENTE' => 'Presente',
                                                    'ASSENTE' => 'Assente',
                                                    'RITARDO' => 'Ritardo',
                                                    'USCITA' => 'Uscita',
                                                ];
                                                foreach ($selectedBlockHours as $blockHour) {
                                                    if (!mastercomL2StudentExpectedForHour($studentHourConfig, $studentId, $selectedDate, $blockHour)) {
                                                        continue;
                                                    }
                                                    $row = is_array($studentAppealRows) && isset($studentAppealRows[$blockHour])
                                                        ? $studentAppealRows[$blockHour]
                                                        : $legacyRow;
                                                    $presenceRow = is_array($mastercomPresenceByHour[$blockHour][$studentId] ?? null)
                                                        ? $mastercomPresenceByHour[$blockHour][$studentId]
                                                        : [];
                                                    $badgeState = is_array($row)
                                                        ? strtoupper(trim((string)($row['stato'] ?? 'PRESENTE')))
                                                        : mastercomL2PresenceToAppealState($presenceRow);
                                                    if (!isset($badgeLabels[$badgeState])) {
                                                        $badgeState = 'PRESENTE';
                                                    }
                                                    $badgeStates[$blockHour] = $badgeState;
                                                }
                                                $badgeHtml = '';
                                                $uniqueStates = array_values(array_unique(array_values($badgeStates)));
                                                if (count($uniqueStates) === 1) {
                                                    $state = $uniqueStates[0];
                                                    $badgeHtml = '<span class="l2-status-badge l2-status-' . htmlspecialchars(strtolower($state)) . '">'
                                                        . htmlspecialchars($badgeLabels[$state])
                                                        . '</span>';
                                                } else {
                                                    $previousState = null;
                                                    foreach ($badgeStates as $blockHour => $state) {
                                                        if ($state === $previousState) {
                                                            continue;
                                                        }
                                                        $badgeHtml .= '<span class="l2-status-badge l2-status-' . htmlspecialchars(strtolower($state)) . '">'
                                                            . htmlspecialchars($blockHour . ' ' . $badgeShortLabels[$state])
                                                            . '</span>';
                                                        $previousState = $state;
                                                    }
                                                }
                                                ?>
                                                <td style="text-align: center;">
                                                    <?php
                                                    $photoFile = trim((string)($student['foto'] ?? ''));
                                                    $photoUrl = $photoFile !== '' && isset($__application_base_path)
                                                        ? ($__application_base_path . '/common/mastercom/photo.php?proxy=1&file=' . urlencode($photoFile))
                                                        : '';
                                                    ?>
                                                    <?php if ($photoUrl !== ''): ?>
                                                        <img class="l2-student-photo" src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Foto studente">
                                                    <?php else: ?>
                                                        <div class="l2-student-photo-placeholder">Foto</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: center;"><?php echo intval($student['registro_numero'] ?? 0); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars(trim((string)(($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '')))); ?></strong>
                                                    <div class="l2-status-badges"><?php echo $badgeHtml; ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['classe_mastercom'] ?? ''); ?></td>
                                                <td class="l2-mastercom-cell">
                                                    <?php $presenceSegments = mastercomL2BuildPresenceSegments($mastercomPresenceByHour, $studentId, $selectedBlockHours); ?>
                                                    <?php foreach ($presenceSegments as $presenceSegment): ?>
                                                        <?php
                                                        $presenceRow = is_array($presenceSegment['row'] ?? null) ? $presenceSegment['row'] : [];
                                                        $presenceLabel = trim((string)($presenceSegment['label'] ?? 'Da verificare'));
                                                        $presenceDetail = trim((string)($presenceSegment['detail'] ?? ''));
                                                        $presenceEvents = trim((string)($presenceSegment['events'] ?? ''));
                                                        $presencePrefix = mastercomL2PresenceSegmentLabel(
                                                            (string)($presenceSegment['start_hour'] ?? ''),
                                                            (string)($presenceSegment['end_hour'] ?? ''),
                                                            count($presenceSegments)
                                                        );
                                                        ?>
                                                        <div>
                                                            <span class="label label-<?php echo htmlspecialchars(mastercomL2PresenceBadgeClass($presenceRow)); ?>">
                                                                <?php echo htmlspecialchars($presencePrefix . $presenceLabel); ?>
                                                            </span>
                                                        </div>
                                                        <?php if ($presenceDetail !== '' || $presenceEvents !== ''): ?>
                                                            <div class="l2-mastercom-detail">
                                                                <?php echo htmlspecialchars(trim($presenceDetail . ($presenceEvents !== '' ? ' - ' . $presenceEvents : ''))); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </td>
                                                <?php foreach ($selectedBlockHours as $blockHour): ?>
                                                    <?php
                                                    $hourExpected = mastercomL2StudentExpectedForHour($studentHourConfig, $studentId, $selectedDate, $blockHour);
                                                    $row = is_array($studentAppealRows) && isset($studentAppealRows[$blockHour])
                                                        ? $studentAppealRows[$blockHour]
                                                        : $legacyRow;
                                                    $presenceRow = is_array($mastercomPresenceByHour[$blockHour][$studentId] ?? null)
                                                        ? $mastercomPresenceByHour[$blockHour][$studentId]
                                                        : [];
                                                    $state = is_array($row)
                                                        ? trim((string)($row['stato'] ?? 'PRESENTE'))
                                                        : mastercomL2PresenceToAppealState($presenceRow);
                                                    $hourEditable = $canEdit && !empty($hourEditableMap[$blockHour]);
                                                    ?>
                                                    <td>
                                                        <?php if (!$hourExpected): ?>
                                                            <span class="label label-default">Non prevista</span>
                                                        <?php else: ?>
                                                        <select
                                                            class="form-control l2-hour-state"
                                                            name="stato[<?php echo $studentId; ?>][<?php echo htmlspecialchars($blockHour); ?>]"
                                                            data-student="<?php echo intval($studentId); ?>"
                                                            data-hour="<?php echo htmlspecialchars($blockHour); ?>"
                                                            <?php echo $hourEditable ? '' : 'disabled'; ?>>
                                                            <?php foreach (['PRESENTE' => 'Presente', 'ASSENTE' => 'Assente', 'RITARDO' => 'Ritardo', 'USCITA' => 'Uscita'] as $value => $label): ?>
                                                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $state === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php if (!$hourEditable && $canEdit): ?>
                                                            <small class="text-muted">Ora chiusa</small>
                                                        <?php endif; ?>
                                                        <?php
                                                        $mastercomCanApply = $l2IsAdmin || $hourEditable;
                                                        $mastercomPlan = is_array($row)
                                                            ? mastercomL2PlanMastercomAction($student, $presenceRow, $state, $selectedDate, $blockHour, false)
                                                            : [
                                                                'kind' => 'pending_save',
                                                                'summary' => 'Salva l\'appello prima di applicare modifiche a MasterCom.',
                                                                'payload' => null,
                                                            ];
                                                        ?>
                                                        <div class="l2-mastercom-action">
                                                            <div><?php echo htmlspecialchars($mastercomPlan['summary'] ?? ''); ?></div>
                                                            <?php if (($mastercomPlan['kind'] ?? 'none') !== 'none' && ($mastercomPlan['kind'] ?? '') !== 'pending_save' && $mastercomCanApply): ?>
                                                                <button
                                                                    type="submit"
                                                                    class="btn btn-warning btn-xs"
                                                                    name="mastercom_apply"
                                                                    value="<?php echo htmlspecialchars(intval($studentId) . '|' . $blockHour); ?>"
                                                                    onclick="return confirm('Confermi invio azione a MasterCom per questo studente?');">
                                                                    Applica a MasterCom
                                                                </button>
                                                            <?php elseif (($mastercomPlan['kind'] ?? '') === 'pending_save' && $canEdit): ?>
                                                                <span class="label label-warning">Da salvare</span>
                                                            <?php elseif (!$mastercomCanApply): ?>
                                                                <span class="label label-default">Sola lettura</span>
                                                            <?php else: ?>
                                                                <span class="label label-default">Nessuna azione</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td><input class="form-control" type="text" name="note_studente[<?php echo $studentId; ?>][<?php echo htmlspecialchars($studentNoteHour); ?>]" value="<?php echo htmlspecialchars($studentNote); ?>" <?php echo ($canEdit && $hasEditableHour) ? '' : 'disabled'; ?>></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="form-group">
                                    <label for="note_appello">Note appello</label>
                                    <textarea class="form-control" name="note_appello" id="note_appello" rows="2" <?php echo ($canEdit && $hasEditableHour) ? '' : 'disabled'; ?>><?php echo htmlspecialchars($appeal['note'] ?? ''); ?></textarea>
                                </div>
                                <?php if ($canEdit && $hasEditableHour): ?>
                                    <button type="submit" class="btn btn-primary">Salva appello L2</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </form>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function showL2Loading(title, subtitle) {
        var overlay = document.getElementById('l2_loading_overlay');
        var titleNode = document.getElementById('l2_loading_title');
        var subtitleNode = document.getElementById('l2_loading_subtitle');
        if (!overlay) {
            return;
        }
        if (titleNode && title) {
            titleNode.textContent = title;
        }
        if (subtitleNode && subtitle) {
            subtitleNode.textContent = subtitle;
        }
        overlay.classList.add('is-visible');
    }

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var submitter = event.submitter || document.activeElement;
            if (submitter && submitter.name === 'mastercom_apply') {
                showL2Loading('Aggiornamento MasterCom...', 'Invio la modifica dello studente selezionato.');
                return;
            }
            showL2Loading('Salvataggio in corso...', "Sto aggiornando l'appello L2.");
        });
    });

    document.querySelectorAll('.l2-lesson-card').forEach(function (link) {
        link.addEventListener('click', function () {
            showL2Loading('Caricamento appello...', 'Sto leggendo studenti, presenze MasterCom ed eventi.');
        });
    });

    document.querySelectorAll('.panel-teal4 a.btn[href]').forEach(function (link) {
        link.addEventListener('click', function () {
            showL2Loading('Caricamento in corso...', 'Sto aggiornando la vista del registro L2.');
        });
    });

    document.querySelectorAll('.l2-hour-state').forEach(function (select) {
        select.addEventListener('change', function () {
            var studentId = select.getAttribute('data-student');
            var foundCurrent = false;
            document.querySelectorAll('.l2-hour-state[data-student="' + studentId + '"]').forEach(function (candidate) {
                if (candidate === select) {
                    foundCurrent = true;
                    return;
                }
                if (!foundCurrent || candidate.disabled) {
                    return;
                }
                candidate.value = select.value;
            });
        });
    });
});
</script>

<?php

if (!function_exists('mastercomNoIrcRegistroH')) {
    function mastercomNoIrcRegistroH($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mastercomNoIrcRegistroChoiceTooltip')) {
    function mastercomNoIrcRegistroChoiceTooltip(string $choice): string
    {
        $map = [
            'ASD' => 'Attivita di Studio e/o di Ricerche individuali con assistenza di personale docente',
            'LAS' => 'Libera Attivita di Studio e/o ricerca individuale senza assistenza di personale docente',
            'AES' => 'Allontanarsi o assentarsi da Edificio Scolastico',
        ];

        return $map[$choice] ?? $choice;
    }
}

$noircIsAdmin = !empty($noircIsAdmin);
$noircCurrentDocenteId = intval($noircCurrentDocenteId ?? 0);
$noircActionUrl = (string)($noircActionUrl ?? '');
$noircExtraParams = is_array($noircExtraParams ?? null) ? $noircExtraParams : [];
$noircSelectedDate = mastercomNoIrcNormalizeDate((string)($_POST['data_giorno'] ?? $_GET['data_giorno'] ?? ''));
$noircSelectedHour = mastercomNoIrcNormalizeHour((string)($_POST['ora'] ?? $_GET['ora'] ?? ''));
$noircSelectedAula = trim((string)($_POST['aula'] ?? $_GET['aula'] ?? ''));
$noircMessages = [];
$noircErrors = [];

$noircMissingTables = mastercomAdminMissingTables(['mastercom_noirc_appelli', 'mastercom_noirc_appello_studenti']);
if (empty($noircMissingTables) && !mastercomNoIrcAppealAulaColumnExists()) {
    $noircMessages[] = [
        'type' => 'warning',
        'text' => 'La tabella mastercom_noirc_appelli non ha ancora la colonna aula: gli appelli funzionano, ma per distinguere meglio due aule nello stesso slot conviene aggiornare lo schema.',
    ];
}

$noircWeekContext = mastercomNoIrcBuildWeekSlots($noircSelectedDate);
$noircAvailable = [];
mastercomNoIrcLog('page_start', [
    'is_admin' => $noircIsAdmin,
    'current_docente_id' => $noircCurrentDocenteId,
    'selected_date' => $noircSelectedDate,
    'selected_hour' => $noircSelectedHour,
    'selected_aula' => $noircSelectedAula,
    'extra_params' => $noircExtraParams,
    'slots_count' => count($noircWeekContext['slots'] ?? []),
]);
foreach ($noircWeekContext['slots'] as $slot) {
    foreach ($slot['group_buckets'] as $bucket) {
        $matchesDocente = $noircIsAdmin || mastercomNoIrcBucketMatchesDocente($bucket, $slot, $noircCurrentDocenteId);
        mastercomNoIrcLog('page_bucket_check', [
            'slot_date' => $slot['date'] ?? '',
            'slot_hour' => $slot['hour'] ?? '',
            'docente_id' => $noircCurrentDocenteId,
            'matches' => $matchesDocente,
            'bucket' => [
                'type' => $bucket['type'] ?? '',
                'assignment_id' => intval($bucket['assignment_id'] ?? 0),
                'id_docente' => intval($bucket['id_docente'] ?? 0),
                'aula' => trim((string)($bucket['aula'] ?? '')),
                'students' => count($bucket['students'] ?? []),
                'teacher_name' => trim((string)($bucket['teacher_name'] ?? '')),
                'effective_teacher_name' => trim((string)($bucket['effective_teacher_name'] ?? $bucket['teacher_name'] ?? '')),
                'substitution' => !empty($bucket['substitution']),
            ],
        ]);
        if (!$matchesDocente) {
            continue;
        }
        if (empty($bucket['students'])) {
            continue;
        }
        $noircAvailable[] = [
            'date' => (string)$slot['date'],
            'hour' => (string)$slot['hour'],
            'aula' => trim((string)($bucket['aula'] ?? '')),
            'teacher_name' => trim((string)($bucket['effective_teacher_name'] ?? $bucket['teacher_name'] ?? '')),
            'original_teacher_name' => trim((string)($bucket['original_teacher_name'] ?? $bucket['teacher_name'] ?? '')),
            'substitution' => !empty($bucket['substitution']) && is_array($bucket['substitution']) ? $bucket['substitution'] : null,
            'label' => trim((string)($bucket['group_label'] ?? '')),
            'students_count' => count($bucket['students']),
            'classes' => implode(', ', array_values($bucket['class_filters'] ?? [])),
        ];
    }
}
mastercomNoIrcLog('page_available_result', [
    'current_docente_id' => $noircCurrentDocenteId,
    'available_count' => count($noircAvailable),
    'available' => $noircAvailable,
]);

if ($noircSelectedHour === '' && !empty($noircAvailable)) {
    foreach ($noircAvailable as $candidate) {
        if ($candidate['date'] === $noircSelectedDate) {
            $noircSelectedHour = $candidate['hour'];
            $noircSelectedAula = $candidate['aula'];
            break;
        }
    }
    if ($noircSelectedHour === '') {
        $noircSelectedDate = $noircAvailable[0]['date'];
        $noircSelectedHour = $noircAvailable[0]['hour'];
        $noircSelectedAula = $noircAvailable[0]['aula'];
    }
}

$noircRegistroContext = null;
$noircSavedAppeal = ['appeal' => null, 'rows' => []];
$noircPresence = ['ok' => false, 'map' => [], 'error' => ''];
$noircCanEditAppeal = $noircIsAdmin;
if ($noircSelectedHour !== '') {
    $tmpContext = mastercomNoIrcFindRegistroContext($noircSelectedDate, $noircSelectedHour, $noircSelectedAula, $noircCurrentDocenteId, $noircIsAdmin);
    if (!empty($tmpContext['ok'])) {
        $noircRegistroContext = $tmpContext;
        $noircSelectedDate = $tmpContext['date'];
        $noircSelectedHour = $tmpContext['hour'];
        $noircSelectedAula = $tmpContext['aula'];
        $noircSavedAppeal = mastercomNoIrcLoadAppealRows($noircSelectedDate, $noircSelectedHour, intval($tmpContext['assignment_id'] ?? 0), $noircSelectedAula);
        $noircPresence = mastercomNoIrcLoadPresenceMap($tmpContext['students'], $noircSelectedDate, $noircSelectedHour);
        $noircCanEditAppeal = $noircIsAdmin || mastercomNoIrcSlotIsCurrent($noircSelectedDate, $noircSelectedHour);
    } else {
        $noircErrors[] = trim((string)($tmpContext['error'] ?? 'Slot NO IRC non disponibile'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mastercom_student_id']) && $noircRegistroContext !== null && !$noircCanEditAppeal) {
    $noircErrors[] = 'Operazione non consentita: il docente puo aggiornare MasterCom solo durante l’ora NO IRC selezionata.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mastercom_student_id']) && $noircRegistroContext !== null && $noircCanEditAppeal) {
    $studentIdToSync = intval($_POST['mastercom_student_id']);
    $studentToSync = null;
    foreach ($noircRegistroContext['students'] as $candidateStudent) {
        if (intval($candidateStudent['mastercom_id_studente'] ?? 0) === $studentIdToSync) {
            $studentToSync = $candidateStudent;
            break;
        }
    }

    if ($studentToSync === null) {
        $noircErrors[] = 'Studente non trovato nello slot NO IRC selezionato.';
    } else {
        $postedStates = is_array($_POST['stato'] ?? null) ? $_POST['stato'] : [];
        $selectedNoircState = strtoupper(trim((string)($postedStates[$studentIdToSync] ?? 'PRESENTE')));
        $presenceRow = $noircPresence['map'][$studentIdToSync] ?? ['stato' => 'NON_VERIFICATO'];
        $plan = mastercomNoIrcPlanMastercomAction($studentToSync, $presenceRow, $selectedNoircState, $noircSelectedDate, $noircSelectedHour);
        $executeResult = mastercomNoIrcExecuteMastercomAction($plan);
        if (!empty($executeResult['ok'])) {
            $studentLabel = trim((string)($studentToSync['cognome'] ?? '') . ' ' . (string)($studentToSync['nome'] ?? ''));
            $noircMessages[] = [
                'type' => 'success',
                'text' => 'Azione MasterCom inviata per ' . $studentLabel . ': ' . trim((string)($plan['summary'] ?? '')),
            ];
            $noircPresence = mastercomNoIrcLoadPresenceMap($noircRegistroContext['students'], $noircSelectedDate, $noircSelectedHour);
        } else {
            $noircErrors[] = trim((string)($executeResult['error'] ?? 'Azione MasterCom non riuscita'));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'save_noirc_appeal' && !isset($_POST['mastercom_student_id'])) {
    if (!$noircCanEditAppeal) {
        $noircErrors[] = 'Operazione non consentita: il docente puo salvare l’appello solo durante l’ora NO IRC selezionata.';
    } else {
        $saveResult = mastercomNoIrcSaveAppeal($_POST, intval($GLOBALS['__utente_id'] ?? 0), $noircCurrentDocenteId, $noircIsAdmin);
        if (!empty($saveResult['ok'])) {
            $noircMessages[] = [
                'type' => 'success',
                'text' => 'Appello NO IRC salvato: ' . intval($saveResult['saved'] ?? 0) . ' studenti aggiornati.',
            ];
            $noircSavedAppeal = mastercomNoIrcLoadAppealRows($noircSelectedDate, $noircSelectedHour, intval($noircRegistroContext['assignment_id'] ?? 0), $noircSelectedAula);
        } else {
            $noircErrors[] = trim((string)($saveResult['error'] ?? 'Salvataggio non riuscito'));
        }
    }
}

$noircStateLabels = [
    'PRESENTE' => 'Presente',
    'ASSENTE_MASTERCOM' => 'Assente su MasterCom',
    'ASSENTE_NOIRC' => 'Assente NO IRC',
    'USCITA' => 'Uscita',
    'PERMESSO' => 'Permesso',
    'EVENTO' => 'Evento',
    'NON_VERIFICATO' => 'Da verificare',
];
?>

<style>
    .noirc-registro-layout {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 16px;
    }
    .noirc-registro-slots {
        max-height: 620px;
        overflow-y: auto;
    }
    .noirc-registro-slot {
        display: block;
        padding: 10px 12px;
        border: 1px solid #d9e2ef;
        border-radius: 4px;
        margin-bottom: 8px;
        color: #1f2937;
        background: #fff;
    }
    .noirc-registro-slot.active {
        border-color: #337ab7;
        background: #eef6ff;
        font-weight: 600;
    }
    .noirc-registro-substitution {
        margin-top: 6px;
        color: #8a4b00;
        font-size: 12px;
    }
    .noirc-registro-student {
        vertical-align: middle !important;
    }
    .noirc-registro-presence {
        min-width: 155px;
    }
    .noirc-loading-overlay {
        display: none;
        position: fixed;
        z-index: 9999;
        inset: 0;
        background: rgba(255, 255, 255, 0.72);
        align-items: center;
        justify-content: center;
    }
    .noirc-loading-overlay.active {
        display: flex;
    }
    .noirc-loading-box {
        padding: 18px 24px;
        border: 1px solid #bcd2e8;
        border-radius: 4px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
        color: #24527a;
        font-weight: 600;
    }
    @media (max-width: 900px) {
        .noirc-registro-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

<div id="noirc-loading-overlay" class="noirc-loading-overlay" aria-live="polite" aria-busy="true">
    <div class="noirc-loading-box">
        <span class="glyphicon glyphicon-refresh"></span>&ensp;Caricamento in corso...
    </div>
</div>

<div class="panel panel-teal4">
    <div class="panel-heading">
        <span class="glyphicon glyphicon-check"></span>&emsp;Registro NO IRC
    </div>
    <div class="panel-body">
        <?php if (!empty($noircMissingTables)): ?>
            <div class="alert alert-warning">
                Mancano le tabelle <?php echo mastercomNoIrcRegistroH(implode(', ', $noircMissingTables)); ?>.
                Esegui lo script <code>doc/mastercom_noirc_schema.sql</code> prima di usare il registro.
            </div>
        <?php endif; ?>

        <?php foreach ($noircMessages as $message): ?>
            <div class="alert alert-<?php echo mastercomNoIrcRegistroH($message['type']); ?>"><?php echo mastercomNoIrcRegistroH($message['text']); ?></div>
        <?php endforeach; ?>
        <?php foreach ($noircErrors as $error): ?>
            <div class="alert alert-danger"><?php echo mastercomNoIrcRegistroH($error); ?></div>
        <?php endforeach; ?>

        <form method="get" action="<?php echo mastercomNoIrcRegistroH($noircActionUrl); ?>" class="form-inline" style="margin-bottom: 15px;">
            <?php foreach ($noircExtraParams as $extraName => $extraValue): ?>
                <input type="hidden" name="<?php echo mastercomNoIrcRegistroH($extraName); ?>" value="<?php echo mastercomNoIrcRegistroH($extraValue); ?>">
            <?php endforeach; ?>
            <div class="form-group">
                <label for="data_giorno">Settimana/giorno&nbsp;</label>
                <input type="date" class="form-control" name="data_giorno" id="data_giorno" value="<?php echo mastercomNoIrcRegistroH($noircSelectedDate); ?>">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Aggiorna</button>
        </form>

        <?php if (empty($noircAvailable)): ?>
            <div class="alert alert-info">
                <?php echo $noircIsAdmin ? 'Nella settimana selezionata non ci sono gruppi NO IRC con studenti.' : 'Non risultano sorveglianze NO IRC assegnate a questo docente nella settimana selezionata.'; ?>
            </div>
        <?php else: ?>
            <div class="noirc-registro-layout">
                <div>
                    <h4 style="margin-top: 0;">Slot disponibili</h4>
                    <div class="noirc-registro-slots">
                        <?php foreach ($noircAvailable as $slotLink): ?>
                            <?php
                            $active = $slotLink['date'] === $noircSelectedDate
                                && $slotLink['hour'] === $noircSelectedHour
                                && $slotLink['aula'] === $noircSelectedAula;
                            $query = http_build_query(array_merge($noircExtraParams, [
                                'data_giorno' => $slotLink['date'],
                                'ora' => $slotLink['hour'],
                                'aula' => $slotLink['aula'],
                            ]));
                            ?>
                            <a class="noirc-registro-slot <?php echo $active ? 'active' : ''; ?>" href="<?php echo mastercomNoIrcRegistroH($noircActionUrl . '?' . $query); ?>">
                                <div><?php echo mastercomNoIrcRegistroH((new DateTime($slotLink['date']))->format('d/m/Y') . ' - ' . $slotLink['hour']); ?></div>
                                <div>Aula <?php echo mastercomNoIrcRegistroH($slotLink['aula'] !== '' ? $slotLink['aula'] : 'n/d'); ?></div>
                                <?php if ($slotLink['classes'] !== ''): ?>
                                    <div class="text-muted">Classi: <?php echo mastercomNoIrcRegistroH($slotLink['classes']); ?></div>
                                <?php endif; ?>
                                <div class="text-muted">Studenti: <?php echo intval($slotLink['students_count']); ?></div>
                                <?php if ($noircIsAdmin && $slotLink['teacher_name'] !== ''): ?>
                                    <div class="text-muted"><?php echo mastercomNoIrcRegistroH($slotLink['teacher_name']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($slotLink['substitution'])): ?>
                                    <div class="noirc-registro-substitution">
                                        Sostituzione per <?php echo mastercomNoIrcRegistroH($slotLink['original_teacher_name'] !== '' ? $slotLink['original_teacher_name'] : 'docente assente'); ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <?php if ($noircRegistroContext === null): ?>
                        <div class="alert alert-info">Seleziona uno slot NO IRC per aprire l'appello.</div>
                    <?php else: ?>
                        <?php
                        $bucket = $noircRegistroContext['bucket'];
                        $appeal = $noircSavedAppeal['appeal'];
                        $effectiveTeacherName = trim((string)($bucket['effective_teacher_name'] ?? $bucket['teacher_name'] ?? ''));
                        $originalTeacherName = trim((string)($bucket['original_teacher_name'] ?? $bucket['teacher_name'] ?? ''));
                        $bucketSubstitution = !empty($bucket['substitution']) && is_array($bucket['substitution']) ? $bucket['substitution'] : null;
                        ?>
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong><?php echo mastercomNoIrcRegistroH((new DateTime($noircSelectedDate))->format('d/m/Y') . ' - ' . $noircSelectedHour); ?></strong>
                                &nbsp; Aula <strong><?php echo mastercomNoIrcRegistroH($noircSelectedAula !== '' ? $noircSelectedAula : 'n/d'); ?></strong>
                                <?php if ($effectiveTeacherName !== ''): ?>
                                    &nbsp; Docente <strong><?php echo mastercomNoIrcRegistroH($effectiveTeacherName); ?></strong>
                                <?php endif; ?>
                            </div>
                            <div class="panel-body">
                                <?php if ($bucketSubstitution !== null): ?>
                                    <div class="alert alert-warning">
                                        Sostituzione attiva: <strong><?php echo mastercomNoIrcRegistroH($effectiveTeacherName !== '' ? $effectiveTeacherName : 'docente sostituto'); ?></strong>
                                        sostituisce <strong><?php echo mastercomNoIrcRegistroH($originalTeacherName !== '' ? $originalTeacherName : 'docente assente'); ?></strong>.
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($noircPresence['error'])): ?>
                                    <div class="alert alert-warning">
                                        Stato MasterCom: <?php echo mastercomNoIrcRegistroH($noircPresence['error']); ?>.
                                    </div>
                                <?php endif; ?>
                                <?php if (is_array($appeal) && intval($appeal['id'] ?? 0) > 0): ?>
                                    <div class="alert alert-info">
                                        Appello gia salvato il <?php echo mastercomNoIrcRegistroH($appeal['created_at'] ?? ''); ?>.
                                    </div>
                                <?php endif; ?>
                                <?php if (!$noircCanEditAppeal): ?>
                                    <div class="alert alert-warning">
                                        Vista in sola lettura: il docente puo fare appello e inviare aggiornamenti a MasterCom solo durante l’ora NO IRC selezionata.
                                    </div>
                                <?php endif; ?>

                                <form method="post" action="<?php echo mastercomNoIrcRegistroH($noircActionUrl); ?>">
                                    <input type="hidden" name="action" value="save_noirc_appeal">
                                    <?php foreach ($noircExtraParams as $extraName => $extraValue): ?>
                                        <input type="hidden" name="<?php echo mastercomNoIrcRegistroH($extraName); ?>" value="<?php echo mastercomNoIrcRegistroH($extraValue); ?>">
                                    <?php endforeach; ?>
                                    <input type="hidden" name="data_giorno" value="<?php echo mastercomNoIrcRegistroH($noircSelectedDate); ?>">
                                    <input type="hidden" name="ora" value="<?php echo mastercomNoIrcRegistroH($noircSelectedHour); ?>">
                                    <input type="hidden" name="aula" value="<?php echo mastercomNoIrcRegistroH($noircSelectedAula); ?>">

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th style="width: 80px; text-align: center;">Registro</th>
                                                    <th>Studente</th>
                                                    <th style="width: 110px;">Classe</th>
                                                    <th class="noirc-registro-presence">MasterCom</th>
                                                    <th style="width: 210px;">Appello NO IRC</th>
                                                    <th>Note</th>
                                                    <th style="width: 290px;">Azione MasterCom</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($noircRegistroContext['students'] as $student): ?>
                                                    <?php
                                                    $studentId = intval($student['mastercom_id_studente'] ?? 0);
                                                    $savedRow = $noircSavedAppeal['rows'][$studentId] ?? null;
                                                    $presenceRow = $noircPresence['map'][$studentId] ?? ['stato' => 'NON_VERIFICATO', 'label' => 'Da verificare', 'detail' => ''];
                                                    $selectedState = is_array($savedRow)
                                                        ? strtoupper(trim((string)($savedRow['stato'] ?? 'PRESENTE')))
                                                        : strtoupper(trim((string)($presenceRow['stato'] ?? 'PRESENTE')));
                                                    if (!is_array($savedRow) && mastercomNoIrcIsFirstAppealHour($noircSelectedHour)) {
                                                        $selectedState = 'ASSENTE_NOIRC';
                                                    }
                                                    if (!isset($noircStateLabels[$selectedState])) {
                                                        $selectedState = 'PRESENTE';
                                                    }
                                                    $mastercomPlan = is_array($savedRow)
                                                        ? mastercomNoIrcPlanMastercomAction($student, $presenceRow, $selectedState, $noircSelectedDate, $noircSelectedHour)
                                                        : [
                                                            'kind' => 'pending_save',
                                                            'summary' => 'Salva l’appello prima di applicare modifiche a MasterCom.',
                                                            'payload' => null,
                                                        ];
                                                    ?>
                                                    <tr>
                                                        <td class="noirc-registro-student" style="text-align: center;"><?php echo intval($student['registro_numero'] ?? 0) ?: ''; ?></td>
                                                        <td class="noirc-registro-student">
                                                            <strong><?php echo mastercomNoIrcRegistroH(trim((string)($student['cognome'] ?? '') . ' ' . (string)($student['nome'] ?? ''))); ?></strong>
                                                            <?php if (trim((string)($student['scelta_sigla'] ?? '')) !== ''): ?>
                                                                <?php $choiceSigla = trim((string)$student['scelta_sigla']); ?>
                                                                <span class="label label-default" data-toggle="tooltip" data-placement="top" title="<?php echo mastercomNoIrcRegistroH(mastercomNoIrcRegistroChoiceTooltip($choiceSigla)); ?>"><?php echo mastercomNoIrcRegistroH($choiceSigla); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="noirc-registro-student"><?php echo mastercomNoIrcRegistroH($student['classe'] ?? ''); ?></td>
                                                        <td class="noirc-registro-student">
                                                            <span class="label label-<?php echo in_array(($presenceRow['stato'] ?? ''), ['ASSENTE_MASTERCOM', 'USCITA'], true) ? 'danger' : (($presenceRow['stato'] ?? '') === 'NON_VERIFICATO' ? 'default' : (($presenceRow['stato'] ?? '') === 'EVENTO' ? 'info' : 'success')); ?>">
                                                                <?php echo mastercomNoIrcRegistroH($presenceRow['label'] ?? ''); ?>
                                                            </span>
                                                            <?php if (trim((string)($presenceRow['detail'] ?? '')) !== ''): ?>
                                                                <div class="text-muted" style="font-size: 12px; margin-top: 4px;"><?php echo mastercomNoIrcRegistroH($presenceRow['detail']); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <select class="form-control noirc-state-select" name="stato[<?php echo intval($studentId); ?>]" <?php echo $noircCanEditAppeal ? '' : 'disabled'; ?>>
                                                                <?php foreach ($noircStateLabels as $state => $label): ?>
                                                                    <option value="<?php echo mastercomNoIrcRegistroH($state); ?>" <?php echo $selectedState === $state ? 'selected' : ''; ?>>
                                                                        <?php echo mastercomNoIrcRegistroH($label); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td class="noirc-mastercom-action">
                                                            <input type="text" class="form-control" name="studente_note[<?php echo intval($studentId); ?>]" value="<?php echo mastercomNoIrcRegistroH(is_array($savedRow) ? ($savedRow['note'] ?? '') : ''); ?>" <?php echo $noircCanEditAppeal ? '' : 'disabled'; ?>>
                                                        </td>
                                                        <td>
                                                            <div style="font-size: 12px; margin-bottom: 8px;">
                                                                <?php echo mastercomNoIrcRegistroH($mastercomPlan['summary'] ?? ''); ?>
                                                            </div>
                                                            <?php if (($mastercomPlan['kind'] ?? 'none') !== 'none' && ($mastercomPlan['kind'] ?? '') !== 'pending_save' && $noircCanEditAppeal): ?>
                                                                <button type="submit" class="btn btn-warning btn-xs noirc-mastercom-button" name="mastercom_student_id" value="<?php echo intval($studentId); ?>" onclick="return confirm('Confermi invio azione a MasterCom per questo studente?');">
                                                                    Applica a MasterCom
                                                                </button>
                                                            <?php elseif (($mastercomPlan['kind'] ?? '') === 'pending_save' && $noircCanEditAppeal): ?>
                                                                <span class="label label-warning">Da salvare</span>
                                                            <?php elseif (!$noircCanEditAppeal): ?>
                                                                <span class="label label-default">Sola lettura</span>
                                                            <?php else: ?>
                                                                <span class="label label-default">Nessuna azione</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="form-group" style="width: 100%;">
                                        <label for="note">Note appello</label>
                                        <input type="text" class="form-control" id="note" name="note" value="<?php echo mastercomNoIrcRegistroH(is_array($appeal) ? ($appeal['note'] ?? '') : ''); ?>" <?php echo $noircCanEditAppeal ? '' : 'disabled'; ?>>
                                    </div>

                                    <button type="submit" class="btn btn-success" <?php echo (!empty($noircMissingTables) || !$noircCanEditAppeal) ? 'disabled' : ''; ?>>
                                        <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva appello
                                    </button>
                                    <span class="text-muted" style="margin-left: 10px;">
                                        Prima salva l'appello NO IRC; dopo il salvataggio potrai aggiornare su MasterCom le assenze o i ritardi proposti per i singoli studenti.
                                    </span>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
function noircShowLoading() {
    var overlay = document.getElementById('noirc-loading-overlay');
    if (overlay) {
        overlay.classList.add('active');
    }
}
if (window.jQuery && typeof jQuery.fn.tooltip === 'function') {
    jQuery('[data-toggle="tooltip"]').tooltip();
}
document.querySelectorAll('.noirc-registro-slot').forEach(function (link) {
    link.addEventListener('click', noircShowLoading);
});
document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', noircShowLoading);
});
document.querySelectorAll('.noirc-state-select').forEach(function (select) {
    select.addEventListener('change', function () {
        var row = select.closest('tr');
        if (!row) return;
        var actionCell = row.querySelector('.noirc-mastercom-action');
        if (!actionCell) return;
        var button = actionCell.querySelector('.noirc-mastercom-button');
        if (button) button.disabled = true;
        var message = actionCell.querySelector('div');
        if (message) {
            message.textContent = "Stato modificato: salva l'appello per aggiornare la proposta MasterCom prima di applicarla.";
        }
    });
});
</script>

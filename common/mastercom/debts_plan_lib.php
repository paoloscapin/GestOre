<?php

require_once __DIR__ . '/debts_lib.php';

function mastercomDebtsPlanEnsureTables(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_carenze_plan_locks` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_anno_scolastico` INT NOT NULL,
            `plan_id` VARCHAR(100) NOT NULL,
            `slots_json` MEDIUMTEXT NOT NULL,
            `aula` VARCHAR(100) NULL,
            `id_docente` INT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mastercom_carenze_plan_lock` (`id_anno_scolastico`, `plan_id`),
            KEY `idx_mastercom_carenze_plan_lock_docente` (`id_docente`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function mastercomDebtsPlanSlotKey(array $slot): string
{
    return trim((string)($slot['date'] ?? '')) . '|' . trim((string)($slot['start'] ?? '')) . '|' . trim((string)($slot['end'] ?? ''));
}

function mastercomDebtsPlanTimeMinutes(string $time): int
{
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
        return -1;
    }
    return intval($matches[1]) * 60 + intval($matches[2]);
}

function mastercomDebtsPlanSlotsOverlap(array $a, array $b): bool
{
    if (trim((string)($a['date'] ?? '')) === '' || trim((string)($a['date'] ?? '')) !== trim((string)($b['date'] ?? ''))) {
        return false;
    }

    $aStart = mastercomDebtsPlanTimeMinutes((string)($a['start'] ?? ''));
    $aEnd = mastercomDebtsPlanTimeMinutes((string)($a['end'] ?? ''));
    $bStart = mastercomDebtsPlanTimeMinutes((string)($b['start'] ?? ''));
    $bEnd = mastercomDebtsPlanTimeMinutes((string)($b['end'] ?? ''));
    if ($aStart < 0 || $aEnd <= $aStart || $bStart < 0 || $bEnd <= $bStart) {
        return false;
    }

    return $aStart < $bEnd && $bStart < $aEnd;
}

function mastercomDebtsPlanHasStudentConflict(array $busySlots, array $slot, array $studentIds): bool
{
    foreach ($busySlots as $busy) {
        if (!mastercomDebtsPlanSlotsOverlap($busy['slot'] ?? [], $slot)) {
            continue;
        }
        if (!empty(array_intersect($busy['student_ids'] ?? [], $studentIds))) {
            return true;
        }
    }

    return false;
}

function mastercomDebtsPlanReserveSlot(array &$busySlots, array $slot, array $studentIds): void
{
    $busySlots[] = [
        'slot' => $slot,
        'student_ids' => array_values(array_unique(array_map('intval', $studentIds))),
    ];
}

function mastercomDebtsPlanFormatSlot(array $slot): array
{
    $date = trim((string)($slot['date'] ?? ''));
    $start = trim((string)($slot['start'] ?? ''));
    $end = trim((string)($slot['end'] ?? ''));
    $label = '';
    if ($date !== '' && $start !== '' && $end !== '') {
        try {
            $label = (new DateTime($date))->format('d/m/Y') . ' ' . $start . '-' . $end;
        } catch (Exception $e) {
            $label = $date . ' ' . $start . '-' . $end;
        }
    }

    return [
        'date' => $date,
        'date_label' => $date !== '' ? (function () use ($date) {
            try {
                return (new DateTime($date))->format('d/m/Y');
            } catch (Exception $e) {
                return $date;
            }
        })() : '',
        'start' => $start,
        'end' => $end,
        'day_key' => $date,
        'label' => $label,
    ];
}

function mastercomDebtsPlanLoadLocks(int $schoolYearId): array
{
    mastercomDebtsPlanEnsureTables();
    if ($schoolYearId <= 0) {
        return [];
    }

    $rows = dbGetAll("
        SELECT l.*, CONCAT(COALESCE(d.cognome, ''), ' ', COALESCE(d.nome, '')) AS docente_nome
        FROM mastercom_carenze_plan_locks l
        LEFT JOIN docente d ON d.id = l.id_docente
        WHERE l.id_anno_scolastico = " . dbI($schoolYearId) . "
    ") ?: [];

    $locks = [];
    foreach ($rows as $row) {
        $planId = trim((string)($row['plan_id'] ?? ''));
        if ($planId === '') {
            continue;
        }
        $slots = json_decode((string)($row['slots_json'] ?? '[]'), true);
        if (!is_array($slots)) {
            $slots = [];
        }
        $locks[$planId] = [
            'plan_id' => $planId,
            'slots' => array_values(array_map('mastercomDebtsPlanFormatSlot', $slots)),
            'aula' => trim((string)($row['aula'] ?? '')),
            'id_docente' => intval($row['id_docente'] ?? 0),
            'docente_nome' => trim((string)($row['docente_nome'] ?? '')),
        ];
    }

    return $locks;
}

function mastercomDebtsPlanSaveLock(int $schoolYearId, string $planId, array $slots, string $aula, int $docenteId): void
{
    mastercomDebtsPlanEnsureTables();
    $planId = trim($planId);
    if ($schoolYearId <= 0 || $planId === '') {
        return;
    }

    $cleanSlots = [];
    foreach ($slots as $slot) {
        $formatted = mastercomDebtsPlanFormatSlot($slot);
        if ($formatted['date'] === '' || $formatted['start'] === '' || $formatted['end'] === '') {
            continue;
        }
        $cleanSlots[] = $formatted;
    }

    dbExec("
        INSERT INTO mastercom_carenze_plan_locks
            (id_anno_scolastico, plan_id, slots_json, aula, id_docente, created_at, updated_at)
        VALUES
            (" . dbI($schoolYearId) . ",
             " . dbQ($planId) . ",
             " . dbQ(json_encode($cleanSlots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . ",
             " . dbQ($aula) . ",
             " . dbI($docenteId > 0 ? $docenteId : null) . ",
             NOW(),
             NOW())
        ON DUPLICATE KEY UPDATE
            slots_json = VALUES(slots_json),
            aula = VALUES(aula),
            id_docente = VALUES(id_docente),
            updated_at = NOW()
    ");
}

function mastercomDebtsPlanDeleteLock(int $schoolYearId, string $planId): void
{
    mastercomDebtsPlanEnsureTables();
    $planId = trim($planId);
    if ($schoolYearId <= 0 || $planId === '') {
        return;
    }

    dbExec("
        DELETE FROM mastercom_carenze_plan_locks
        WHERE id_anno_scolastico = " . dbI($schoolYearId) . "
          AND plan_id = " . dbQ($planId) . "
        LIMIT 1
    ");
}

function mastercomDebtsPlanTeacherRows(): array
{
    return dbGetAll("
        SELECT id, cognome, nome
        FROM docente
        WHERE attivo = 1
        ORDER BY cognome ASC, nome ASC
    ") ?: [];
}

function mastercomDebtsPlanSchoolYearLabel(int $schoolYearId): string
{
    if ($schoolYearId <= 0) {
        return '';
    }
    return trim((string)(dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($schoolYearId) . " LIMIT 1") ?? ''));
}

function mastercomDebtsPlanCalendarYear(int $schoolYearId): int
{
    $label = mastercomDebtsPlanSchoolYearLabel($schoolYearId);
    if (preg_match('/^\d{4}\s*\/\s*(\d{4})$/', $label, $matches)) {
        return intval($matches[1]);
    }

    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
    return intval($now->format('Y'));
}

function mastercomDebtsPlanClassYear(string $className): string
{
    $className = trim($className);
    if (preg_match('/([1-5])/', $className, $matches)) {
        return $matches[1];
    }
    return 'NA';
}

function mastercomDebtsPlanStudentLabel(array $row): string
{
    $name = trim((string)($row['studente_gestore'] ?? ''));
    if ($name === '') {
        $name = trim((string)($row['studente_nome'] ?? ''));
    }
    return $name;
}

function mastercomDebtsPlanSourceRows(int $schoolYearId): array
{
    if ($schoolYearId <= 0) {
        return [];
    }

    return dbGetAll("
        SELECT
            mc.id,
            mc.mastercom_id_classe,
            mc.classe,
            mc.mastercom_id_studente,
            mc.id_studente_gestore,
            mc.studente_nome,
            mc.id_anno_scolastico,
            mc.materia,
            mc.id_materia_gestore,
            mc.recuperato_mastercom,
            mc.tipo_debito,
            m.nome AS materia_gestore,
            cl.classe AS classe_gestore,
            CONCAT(COALESCE(s.cognome, ''), ' ', COALESCE(s.nome, '')) AS studente_gestore
        FROM mastercom_carenze mc
        LEFT JOIN materia m ON m.id = mc.id_materia_gestore
        LEFT JOIN classi cl ON cl.id = mc.id_classe_gestore
        LEFT JOIN studente s ON s.id = mc.id_studente_gestore
        WHERE mc.id_anno_scolastico = " . dbI($schoolYearId) . "
          AND COALESCE(mc.recuperato_mastercom, 0) = 0
        ORDER BY mc.materia ASC, mc.studente_nome ASC, mc.classe ASC
    ") ?: [];
}

function mastercomDebtsPlanBuildBaseGroups(array $rows): array
{
    $groups = [];
    $seen = [];

    foreach ($rows as $row) {
        $studentId = intval($row['id_studente_gestore'] ?? 0);
        $subjectId = intval($row['id_materia_gestore'] ?? 0);
        if ($studentId <= 0 || $subjectId <= 0) {
            continue;
        }

        $studentLabel = mastercomDebtsPlanStudentLabel($row);
        if ($studentLabel === '') {
            continue;
        }

        $className = trim((string)(($row['classe_gestore'] ?? '') ?: ($row['classe'] ?? '')));
        $classYear = mastercomDebtsPlanClassYear($className);
        $subjectName = trim((string)(($row['materia_gestore'] ?? '') ?: ($row['materia'] ?? 'Materia')));
        $key = $classYear . ':' . $subjectId;
        $studentKey = $key . ':' . $studentId;
        if (isset($seen[$studentKey])) {
            continue;
        }
        $seen[$studentKey] = true;

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'key' => $key,
                'class_year' => $classYear,
                'subject_id' => $subjectId,
                'subject' => $subjectName,
                'students' => [],
            ];
        }

        $groups[$key]['students'][] = [
            'id' => $studentId,
            'name' => $studentLabel,
            'class' => $className,
        ];
    }

    foreach ($groups as &$group) {
        usort($group['students'], function ($a, $b) {
            $cmp = strcmp((string)($a['class'] ?? ''), (string)($b['class'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
    }
    unset($group);

    uasort($groups, function ($a, $b) {
        $cmp = strcmp((string)$a['class_year'], (string)$b['class_year']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)$a['subject'], (string)$b['subject']);
    });

    return array_values($groups);
}

function mastercomDebtsPlanSplitGroups(array $baseGroups, int $minSize = 4, int $maxSize = 10): array
{
    $courseGroups = [];
    $autonomousGroups = [];

    foreach ($baseGroups as $baseGroup) {
        $students = array_values($baseGroup['students'] ?? []);
        $count = count($students);
        if ($count < $minSize) {
            $baseGroup['student_count'] = $count;
            $baseGroup['reason'] = 'Gruppo sotto minimo';
            $autonomousGroups[] = $baseGroup;
            continue;
        }

        $parts = max(1, (int)ceil($count / max(1, $maxSize)));
        while ($parts > 1 && floor($count / $parts) < $minSize) {
            $parts--;
        }

        $chunksByClass = [];
        foreach ($students as $student) {
            $classKey = trim((string)($student['class'] ?? ''));
            if ($classKey === '') {
                $classKey = 'Senza classe';
            }
            if (!isset($chunksByClass[$classKey])) {
                $chunksByClass[$classKey] = [];
            }
            $chunksByClass[$classKey][] = $student;
        }
        uasort($chunksByClass, function ($a, $b) {
            return count($b) <=> count($a);
        });

        $splitStudents = array_fill(0, $parts, []);
        foreach ($chunksByClass as $classStudents) {
            $targetIndex = 0;
            $targetCount = count($splitStudents[0]);
            foreach ($splitStudents as $index => $candidateStudents) {
                $candidateCount = count($candidateStudents);
                if ($candidateCount < $targetCount) {
                    $targetIndex = $index;
                    $targetCount = $candidateCount;
                }
            }
            $splitStudents[$targetIndex] = array_merge($splitStudents[$targetIndex], $classStudents);
        }

        for ($i = 0; $i < $parts; $i++) {
            $group = $baseGroup;
            $group['students'] = $splitStudents[$i];
            $group['student_count'] = count($group['students']);
            $group['part_index'] = $i + 1;
            $group['part_total'] = $parts;
            $group['plan_id'] = $baseGroup['key'] . ':' . ($i + 1);
            $courseGroups[] = $group;
        }
    }

    return [$courseGroups, $autonomousGroups];
}

function mastercomDebtsPlanSlots(int $year): array
{
    $slots = [];
    $timezone = new DateTimeZone('Europe/Rome');
    $start = new DateTime($year . '-08-24', $timezone);
    $end = new DateTime($year . '-09-05', $timezone);
    $times = [
        ['08:00', '09:40'],
        ['10:00', '11:40'],
        ['14:00', '15:40'],
    ];

    for ($day = clone $start; $day <= $end; $day->modify('+1 day')) {
        if (intval($day->format('N')) === 7) {
            continue;
        }
        foreach ($times as $time) {
            $slots[] = [
                'date' => $day->format('Y-m-d'),
                'date_label' => $day->format('d/m/Y'),
                'weekday' => $day->format('N'),
                'start' => $time[0],
                'end' => $time[1],
                'day_key' => $day->format('Y-m-d'),
                'label' => $day->format('d/m/Y') . ' ' . $time[0] . '-' . $time[1],
            ];
        }
    }

    return $slots;
}

function mastercomDebtsPlanFindLessonSlots(array $slots, array $busySlots, array $studentIds): array
{
    $selected = [];
    $selectedDays = [];

    foreach ($slots as $slotIndex => $slot) {
        $dayKey = (string)($slot['day_key'] ?? $slot['date'] ?? '');
        if ($dayKey === '' || isset($selectedDays[$dayKey])) {
            continue;
        }

        if (mastercomDebtsPlanHasStudentConflict($busySlots, $slot, $studentIds)) {
            continue;
        }

        $selected[] = ['index' => $slotIndex, 'slot' => $slot];
        $selectedDays[$dayKey] = true;
        if (count($selected) >= 3) {
            return $selected;
        }
    }

    return [];
}

function mastercomDebtsPlanScheduleGroups(array $courseGroups, int $calendarYear, array $locks = []): array
{
    $slots = mastercomDebtsPlanSlots($calendarYear);
    $busySlots = [];
    $scheduled = [];
    $unscheduled = [];
    $remainingGroups = [];

    foreach ($courseGroups as $group) {
        $planId = trim((string)($group['plan_id'] ?? ''));
        $studentIds = array_values(array_unique(array_map('intval', array_column($group['students'] ?? [], 'id'))));
        if ($planId === '' || !isset($locks[$planId])) {
            $remainingGroups[] = $group;
            continue;
        }

        $lock = $locks[$planId];
        $lockedSlots = array_values(array_filter(array_map('mastercomDebtsPlanFormatSlot', $lock['slots'] ?? []), function ($slot) {
            return ($slot['date'] ?? '') !== '' && ($slot['start'] ?? '') !== '' && ($slot['end'] ?? '') !== '';
        }));
        if (empty($lockedSlots)) {
            $remainingGroups[] = $group;
            continue;
        }

        foreach ($lockedSlots as $slot) {
            mastercomDebtsPlanReserveSlot($busySlots, $slot, $studentIds);
        }

        $group['slots'] = $lockedSlots;
        $group['slot'] = $lockedSlots[0] ?? null;
        $group['slot_index'] = -1;
        $group['locked'] = true;
        $group['aula'] = trim((string)($lock['aula'] ?? ''));
        $group['id_docente'] = intval($lock['id_docente'] ?? 0);
        $group['docente_nome'] = trim((string)($lock['docente_nome'] ?? ''));
        $scheduled[] = $group;
    }

    usort($remainingGroups, function ($a, $b) {
        $aStudents = array_column($a['students'] ?? [], 'id');
        $bStudents = array_column($b['students'] ?? [], 'id');
        $cmp = count($bStudents) <=> count($aStudents);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)$a['subject'], (string)$b['subject']);
    });

    foreach ($remainingGroups as $group) {
        $studentIds = array_values(array_unique(array_map('intval', array_column($group['students'] ?? [], 'id'))));
        $lessonSlots = mastercomDebtsPlanFindLessonSlots($slots, $busySlots, $studentIds);
        if (empty($lessonSlots)) {
            $group['slot'] = null;
            $group['slots'] = [];
            $unscheduled[] = $group;
            continue;
        }

        foreach ($lessonSlots as $lessonSlot) {
            mastercomDebtsPlanReserveSlot($busySlots, $lessonSlot['slot'] ?? [], $studentIds);
        }

        $group['slots'] = array_column($lessonSlots, 'slot');
        $group['slot'] = $group['slots'][0] ?? null;
        $group['slot_index'] = intval($lessonSlots[0]['index'] ?? 0);
        $group['locked'] = false;
        $group['aula'] = '';
        $group['id_docente'] = 0;
        $group['docente_nome'] = '';
        $scheduled[] = $group;
    }

    usort($scheduled, function ($a, $b) {
        if (!empty($a['locked']) && empty($b['locked'])) {
            return -1;
        }
        if (empty($a['locked']) && !empty($b['locked'])) {
            return 1;
        }
        $cmp = intval($a['slot_index'] ?? 0) <=> intval($b['slot_index'] ?? 0);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)$a['subject'], (string)$b['subject']);
    });

    return [$scheduled, $unscheduled, $slots];
}

function mastercomDebtsPlanBuild(int $schoolYearId, int $minSize = 4, int $maxSize = 10): array
{
    mastercomDebtsEnsureTables();
    mastercomDebtsPlanEnsureTables();
    mastercomDebtsRefreshMissingSubjectMatches();
    mastercomDebtsRefreshCachedClassMatches();

    $rows = mastercomDebtsPlanSourceRows($schoolYearId);
    $baseGroups = mastercomDebtsPlanBuildBaseGroups($rows);
    [$courseGroups, $autonomousGroups] = mastercomDebtsPlanSplitGroups($baseGroups, $minSize, $maxSize);
    $calendarYear = mastercomDebtsPlanCalendarYear($schoolYearId);
    $locks = mastercomDebtsPlanLoadLocks($schoolYearId);
    [$scheduledGroups, $unscheduledGroups, $slots] = mastercomDebtsPlanScheduleGroups($courseGroups, $calendarYear, $locks);

    $studentCourseCounts = [];
    foreach ($courseGroups as $group) {
        foreach (($group['students'] ?? []) as $student) {
            $studentId = intval($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }
            $studentCourseCounts[$studentId] = ($studentCourseCounts[$studentId] ?? 0) + 1;
        }
    }

    return [
        'school_year_id' => $schoolYearId,
        'school_year_label' => mastercomDebtsPlanSchoolYearLabel($schoolYearId),
        'calendar_year' => $calendarYear,
        'source_rows' => count($rows),
        'base_groups' => $baseGroups,
        'course_groups' => $courseGroups,
        'scheduled_groups' => $scheduledGroups,
        'unscheduled_groups' => $unscheduledGroups,
        'autonomous_groups' => $autonomousGroups,
        'slots' => $slots,
        'student_course_counts' => $studentCourseCounts,
        'locks' => $locks,
        'min_size' => $minSize,
        'max_size' => $maxSize,
    ];
}

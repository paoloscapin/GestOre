<?php

require_once __DIR__ . '/debts_lib.php';
require_once __DIR__ . '/../iscrizioniPrimeLib.php';

const MASTERCOM_DEBTS_PLAN_NEO_CARENZE_DOCENTE_ID = 652;

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

    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_carenze_plan_real_courses` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_anno_scolastico` INT NOT NULL,
            `course_key` VARCHAR(190) NOT NULL,
            `kind` VARCHAR(20) NOT NULL DEFAULT 'corso',
            `class_year` VARCHAR(10) NULL,
            `subject` VARCHAR(190) NOT NULL,
            `date_text` VARCHAR(190) NULL,
            `time_text` VARCHAR(100) NULL,
            `slots_json` MEDIUMTEXT NOT NULL,
            `teacher_name` VARCHAR(190) NULL,
            `aula` VARCHAR(100) NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `source_hash` VARCHAR(64) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mcdp_real_course` (`id_anno_scolastico`, `course_key`),
            KEY `idx_mcdp_real_course_year_kind` (`id_anno_scolastico`, `kind`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_carenze_plan_real_students` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `course_id` INT NOT NULL,
            `id_studente` INT NULL,
            `student_name` VARCHAR(190) NOT NULL,
            `class_name` VARCHAR(50) NULL,
            `debt_teacher_name` VARCHAR(190) NULL,
            `auditor` TINYINT(1) NOT NULL DEFAULT 0,
            `sort_order` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mcdp_real_student` (`course_id`, `student_name`, `class_name`),
            KEY `idx_mcdp_real_student_course` (`course_id`),
            KEY `idx_mcdp_real_student_studente` (`id_studente`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    if (dbGetFirst("SHOW COLUMNS FROM `mastercom_carenze_plan_real_students` LIKE 'auditor'") === null) {
        dbExec("ALTER TABLE `mastercom_carenze_plan_real_students` ADD COLUMN `auditor` TINYINT(1) NOT NULL DEFAULT 0 AFTER `debt_teacher_name`");
    }

    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_carenze_plan_course_map` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_anno_scolastico_corsi` INT NOT NULL,
            `id_anno_scolastico_carenze` INT NOT NULL,
            `real_course_id` INT NOT NULL,
            `course_key` VARCHAR(190) NOT NULL,
            `id_corso` INT NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mcdp_course_map_real` (`id_anno_scolastico_corsi`, `real_course_id`),
            UNIQUE KEY `uk_mcdp_course_map_key` (`id_anno_scolastico_corsi`, `course_key`),
            KEY `idx_mcdp_course_map_corso` (`id_corso`)
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

function mastercomDebtsPlanSplitSubjects(string $text): array
{
    $parts = preg_split('/[\r\n;,]+/', $text) ?: [];
    $subjects = [];
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part !== '') {
            $subjects[] = $part;
        }
    }
    return array_values(array_unique($subjects));
}

function mastercomDebtsPlanPracticeCarenzeSubjects(array $pratica): array
{
    $materie = [];
    $decoded = json_decode((string)($pratica['carenze_formative_materie'] ?? '[]'), true);
    if (is_array($decoded)) {
        foreach ($decoded as $materia) {
            $materia = trim((string)$materia);
            if ($materia !== '' && $materia !== '__ALTRO__') {
                $materie[] = $materia;
            }
        }
    }
    $altro = trim((string)($pratica['carenze_formative_altro'] ?? ''));
    if ($altro !== '') {
        $materie = array_merge($materie, mastercomDebtsPlanSplitSubjects($altro));
    }
    return array_values(array_unique(array_filter($materie, static fn($value) => trim((string)$value) !== '')));
}

function mastercomDebtsPlanSyncNeoIscrizioniCarenze(int $debtSchoolYearId): array
{
    iscrizioniPrimeEnsureSchema();
    $summary = ['read' => 0, 'students_synced' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    if ($debtSchoolYearId <= 0 || !mastercomAdminTableExists('carenze')) {
        return $summary;
    }

    $rows = dbGetAll("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE studente_interno = 0
          AND stato IN ('inviata', 'verifica_iniziale_ok', 'verificata')
          AND carenze_formative_dichiarate = 'si'
          AND (
                TRIM(COALESCE(carenze_formative_materie, '')) NOT IN ('', '[]')
             OR TRIM(COALESCE(carenze_formative_altro, '')) <> ''
          )
        ORDER BY cognome ASC, nome ASC, id ASC
    ") ?: [];
    $summary['read'] = count($rows);

    foreach ($rows as $pratica) {
        try {
            $sync = iscrizioniPrimeSyncGestoreStudentAndParents($pratica);
            $studentId = intval($sync['studente_id'] ?? 0);
            $classCode = (string)($sync['classe'] ?? '');
            $classId = $classCode !== '' ? iscrizioniPrimeClassIdByCode($classCode) : 0;
            if ($studentId <= 0 || $classId <= 0) {
                $summary['skipped']++;
                continue;
            }
            $summary['students_synced']++;
            foreach (mastercomDebtsPlanPracticeCarenzeSubjects($pratica) as $subjectName) {
                $subjectId = mastercomDebtsPlanResolveSubjectId($subjectName);
                if ($subjectId <= 0) {
                    $summary['skipped']++;
                    if (count($summary['errors']) < 20) {
                        $summary['errors'][] = trim((string)($pratica['cognome'] ?? '') . ' ' . (string)($pratica['nome'] ?? '')) . ': materia non trovata "' . $subjectName . '"';
                    }
                    continue;
                }
                $existing = dbGetFirst("
                    SELECT id, id_docente
                    FROM carenze
                    WHERE id_studente = " . dbI($studentId) . "
                      AND id_materia = " . dbI($subjectId) . "
                      AND id_classe = " . dbI($classId) . "
                      AND id_anno_scolastico = " . dbI($debtSchoolYearId) . "
                    LIMIT 1
                ");
                if ($existing) {
                    if (intval($existing['id_docente'] ?? 0) <= 0) {
                        dbExec("
                            UPDATE carenze
                            SET id_docente = " . dbI(MASTERCOM_DEBTS_PLAN_NEO_CARENZE_DOCENTE_ID) . "
                            WHERE id = " . dbI($existing['id'] ?? 0) . "
                            LIMIT 1
                        ");
                        $summary['updated']++;
                    }
                    continue;
                }
                dbExec("
                    INSERT INTO carenze
                        (id_studente, id_materia, id_classe, id_docente, id_anno_scolastico, stato, data_inserimento, data_validazione, data_invio)
                    VALUES
                        (" . dbI($studentId) . ",
                         " . dbI($subjectId) . ",
                         " . dbI($classId) . ",
                         " . dbI(MASTERCOM_DEBTS_PLAN_NEO_CARENZE_DOCENTE_ID) . ",
                         " . dbI($debtSchoolYearId) . ",
                         0,
                         NOW(),
                         '',
                         '')
                ");
                $summary['created']++;
            }
        } catch (Throwable $e) {
            $summary['skipped']++;
            if (count($summary['errors']) < 20) {
                $summary['errors'][] = trim((string)($pratica['cognome'] ?? '') . ' ' . (string)($pratica['nome'] ?? '')) . ': ' . $e->getMessage();
            }
        }
    }

    return $summary;
}

function mastercomDebtsPlanNeoCarenzeRows(int $courseSchoolYearId, int $debtSchoolYearId = 0): array
{
    mastercomDebtsPlanEnsureTables();
    if ($courseSchoolYearId <= 0 || $debtSchoolYearId <= 0 || !mastercomAdminTableExists('carenze')) {
        return [];
    }

    $rows = dbGetAll("
        SELECT c.id AS carenza_id,
               c.id_studente,
               c.id_materia,
               c.id_classe,
               c.id_anno_scolastico,
               CONCAT(COALESCE(s.cognome, ''), ' ', COALESCE(s.nome, '')) AS studente_nome,
               cls.classe AS classe_attuale,
               m.nome AS materia_nome,
               GROUP_CONCAT(DISTINCT g.email ORDER BY g.email SEPARATOR ', ') AS email_genitori
        FROM carenze c
        INNER JOIN studente s ON s.id = c.id_studente
        INNER JOIN classi cls ON cls.id = c.id_classe
        INNER JOIN materia m ON m.id = c.id_materia
        LEFT JOIN genitori_studenti gs ON gs.id_studente = s.id
        LEFT JOIN genitori g ON g.id = gs.id_genitore
        WHERE c.id_anno_scolastico = " . dbI($debtSchoolYearId) . "
          AND UPPER(TRIM(cls.classe)) IN ('MEDIE', 'EE')
          AND COALESCE(c.id_docente, 0) = " . dbI(MASTERCOM_DEBTS_PLAN_NEO_CARENZE_DOCENTE_ID) . "
        GROUP BY c.id
        ORDER BY s.cognome ASC, s.nome ASC, m.nome ASC
    ") ?: [];

    $result = [];
    foreach ($rows as $row) {
        $studentId = intval($row['id_studente'] ?? 0);
        $subjectId = intval($row['id_materia'] ?? 0);
        $alreadyPlaced = intval(dbGetValue("
            SELECT COUNT(*)
            FROM corso_iscritti ci
            INNER JOIN corso co ON co.id = ci.id_corso
            WHERE ci.id_studente = " . dbI($studentId) . "
              AND co.id_anno_scolastico = " . dbI($courseSchoolYearId) . "
              AND co.carenza = 1
              AND co.id_materia = " . dbI($subjectId) . "
        ") ?? 0) > 0;
        if ($alreadyPlaced) {
            continue;
        }
        $courses = dbGetAll("
            SELECT co.id, co.titolo, CONCAT(COALESCE(d.cognome, ''), ' ', COALESCE(d.nome, '')) AS docente_nome,
                   (SELECT COUNT(*) FROM corso_iscritti ci WHERE ci.id_corso = co.id) AS iscritti
            FROM corso co
            LEFT JOIN docente d ON d.id = co.id_docente
            WHERE co.id_anno_scolastico = " . dbI($courseSchoolYearId) . "
              AND co.carenza = 1
              AND co.id_materia = " . dbI($subjectId) . "
            ORDER BY co.titolo ASC, co.id ASC
        ") ?: [];
        $parentEmails = preg_split('/\s*,\s*/', (string)($row['email_genitori'] ?? '')) ?: [];
        $parentEmails = array_values(array_filter(array_map('trim', $parentEmails), static fn($email) => $email !== ''));
        $result[] = [
            'carenza_id' => intval($row['carenza_id'] ?? 0),
            'student_id' => $studentId,
            'student_name' => trim((string)($row['studente_nome'] ?? '')),
            'class_name' => trim((string)($row['classe_attuale'] ?? '')),
            'subject' => trim((string)($row['materia_nome'] ?? '')),
            'subject_id' => $subjectId,
            'courses' => $courses,
            'parents' => $parentEmails,
        ];
    }

    return $result;
}

function mastercomDebtsPlanAssignNeoCarenza(int $courseId, int $studentId): array
{
    if ($courseId <= 0 || $studentId <= 0) {
        return ['ok' => false, 'message' => 'Corso o studente non valido.'];
    }
    $course = dbGetFirst("SELECT id, prevede_esami FROM corso WHERE id = " . dbI($courseId) . " AND carenza = 1 LIMIT 1");
    if (!$course) {
        return ['ok' => false, 'message' => 'Corso carenze non trovato.'];
    }
    $exists = intval(dbGetValue("
        SELECT COUNT(*)
        FROM corso_iscritti
        WHERE id_corso = " . dbI($courseId) . "
          AND id_studente = " . dbI($studentId) . "
    ") ?? 0) > 0;
    if (!$exists) {
        dbExec("INSERT INTO corso_iscritti (id_corso, id_studente) VALUES (" . dbI($courseId) . ", " . dbI($studentId) . ")");
    }
    if (intval($course['prevede_esami'] ?? 0) === 1) {
        dbExec("
            INSERT INTO corso_esiti (id_corso, id_studente)
            SELECT " . dbI($courseId) . ", " . dbI($studentId) . "
            WHERE NOT EXISTS (
                SELECT 1 FROM corso_esiti
                WHERE id_corso = " . dbI($courseId) . "
                  AND id_studente = " . dbI($studentId) . "
            )
        ");
    }

    return ['ok' => true, 'message' => $exists ? 'Studente gia presente nel corso.' : 'Neo-iscritto aggiunto al corso.'];
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
    $upper = strtoupper($className);
    if ($upper === 'MEDIE') {
        return '1';
    }
    if ($upper === 'EE') {
        return '3';
    }
    if (preg_match('/([1-5])/', $className, $matches)) {
        return $matches[1];
    }
    return 'NA';
}

function mastercomDebtsPlanLoadRealGroups(int $schoolYearId): array
{
    mastercomDebtsPlanEnsureTables();
    if ($schoolYearId <= 0) {
        return ['course_groups' => [], 'autonomous_groups' => [], 'student_course_counts' => [], 'source_rows' => 0];
    }

    $courseRows = dbGetAll("
        SELECT *
        FROM mastercom_carenze_plan_real_courses
        WHERE id_anno_scolastico = " . dbI($schoolYearId) . "
        ORDER BY sort_order ASC, id ASC
    ") ?: [];

    if (empty($courseRows)) {
        return ['course_groups' => [], 'autonomous_groups' => [], 'student_course_counts' => [], 'source_rows' => 0];
    }

    $ids = array_values(array_filter(array_map(function ($row) {
        return intval($row['id'] ?? 0);
    }, $courseRows)));
    $studentsByCourse = [];
    if (!empty($ids)) {
        $studentRows = dbGetAll("
            SELECT *
            FROM mastercom_carenze_plan_real_students
            WHERE course_id IN (" . implode(',', $ids) . ")
            ORDER BY course_id ASC, sort_order ASC, class_name ASC, student_name ASC
        ") ?: [];
        foreach ($studentRows as $studentRow) {
            $courseId = intval($studentRow['course_id'] ?? 0);
            if ($courseId <= 0) {
                continue;
            }
            $studentsByCourse[$courseId][] = [
                'id' => intval($studentRow['id_studente'] ?? 0),
                'name' => trim((string)($studentRow['student_name'] ?? '')),
                'class' => trim((string)($studentRow['class_name'] ?? '')),
                'teacher' => trim((string)($studentRow['debt_teacher_name'] ?? '')),
                'auditor' => intval($studentRow['auditor'] ?? 0) === 1,
            ];
        }
    }

    $courseGroups = [];
    $autonomousGroups = [];
    $studentCourseCounts = [];
    $sourceRows = 0;
    foreach ($courseRows as $courseIndex => $courseRow) {
        $courseId = intval($courseRow['id'] ?? 0);
        $students = $studentsByCourse[$courseId] ?? [];
        $sourceRows += count($students);
        $slots = json_decode((string)($courseRow['slots_json'] ?? '[]'), true);
        if (!is_array($slots)) {
            $slots = [];
        }
        $slots = array_values(array_map('mastercomDebtsPlanFormatSlot', $slots));
        $classYear = trim((string)($courseRow['class_year'] ?? ''));
        if ($classYear === '' && !empty($students)) {
            $classYear = mastercomDebtsPlanClassYear((string)($students[0]['class'] ?? ''));
        }

        $group = [
            'key' => 'real:' . $courseId,
            'plan_id' => 'real:' . $courseId,
            'class_year' => $classYear !== '' ? $classYear : 'NA',
            'subject_id' => 0,
            'subject' => trim((string)($courseRow['subject'] ?? '')),
            'students' => $students,
            'student_count' => count($students),
            'part_index' => 1,
            'part_total' => 1,
            'slots' => $slots,
            'slot' => $slots[0] ?? null,
            'slot_index' => $courseIndex,
            'locked' => true,
            'imported' => true,
            'aula' => trim((string)($courseRow['aula'] ?? '')),
            'docente_nome' => trim((string)($courseRow['teacher_name'] ?? '')),
            'id_docente' => 0,
            'date_text' => trim((string)($courseRow['date_text'] ?? '')),
            'time_text' => trim((string)($courseRow['time_text'] ?? '')),
        ];

        foreach ($students as $student) {
            $studentId = intval($student['id'] ?? 0);
            if ($studentId > 0) {
                $studentCourseCounts[$studentId] = ($studentCourseCounts[$studentId] ?? 0) + 1;
            }
        }

        if (trim((string)($courseRow['kind'] ?? 'corso')) === 'itinere') {
            $group['reason'] = 'Recupero in itinere da CSV';
            $autonomousGroups[] = $group;
        } else {
            $courseGroups[] = $group;
        }
    }

    return [
        'course_groups' => $courseGroups,
        'autonomous_groups' => $autonomousGroups,
        'student_course_counts' => $studentCourseCounts,
        'source_rows' => $sourceRows,
    ];
}

function mastercomDebtsPlanImportNorm(string $value): string
{
    $value = trim($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $plain = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($plain !== false) {
        $value = $plain;
    }
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function mastercomDebtsPlanImportHeaderKey(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $value = str_replace("\xC2\xA0", ' ', $value);
    return mastercomDebtsPlanImportNorm($value);
}

function mastercomDebtsPlanImportStudentInfo(string $studentName): array
{
    $studentName = trim($studentName);
    $auditor = preg_match('/\budit(?:ore|rice)\b/iu', $studentName) === 1;
    if ($auditor) {
        $studentName = trim(preg_replace('/\s*\budit(?:ore|rice)\b\s*/iu', ' ', $studentName) ?? $studentName);
    }
    return [
        'name' => preg_replace('/\s+/', ' ', $studentName) ?? $studentName,
        'auditor' => $auditor,
    ];
}

function mastercomDebtsPlanImportSubjectKeys(string $subjectName): array
{
    $keys = [mastercomDebtsPlanImportNorm($subjectName)];
    $primary = $keys[0] ?? '';
    if ($primary === 'inglese') {
        $keys[] = mastercomDebtsPlanImportNorm('Lingua inglese');
    } elseif ($primary === 'linguainglese') {
        $keys[] = mastercomDebtsPlanImportNorm('Inglese');
    }
    return array_values(array_unique(array_filter($keys)));
}

function mastercomDebtsPlanImportCanonicalSubject(string $subjectName): string
{
    if (mastercomDebtsPlanImportNorm($subjectName) === 'inglese') {
        return 'Lingua inglese';
    }
    return trim($subjectName);
}

function mastercomDebtsPlanImportTimeValue(string $value): string
{
    $value = trim(str_replace('.', ':', $value));
    if (preg_match('/^\d{1,2}$/', $value)) {
        $value .= ':00';
    }
    if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $value, $matches)) {
        return sprintf('%02d:%02d', intval($matches[1]), intval($matches[2]));
    }
    return '';
}

function mastercomDebtsPlanImportTimeRange(string $value): array
{
    $parts = preg_split('/\s*-\s*/', trim($value));
    if (!is_array($parts) || count($parts) !== 2) {
        return ['', ''];
    }
    return [mastercomDebtsPlanImportTimeValue($parts[0]), mastercomDebtsPlanImportTimeValue($parts[1])];
}

function mastercomDebtsPlanImportParseSlots(string $dateText, string $timeText, int $year, array &$warnings): array
{
    [$defaultStart, $defaultEnd] = mastercomDebtsPlanImportTimeRange($timeText);
    if ($defaultStart === '' || $defaultEnd === '') {
        return [];
    }

    $slots = [];
    $lastMonth = 0;
    foreach (preg_split('/\s*;\s*/', trim($dateText)) ?: [] as $segment) {
        $segment = trim($segment);
        if ($segment === '') {
            continue;
        }

        $start = $defaultStart;
        $end = $defaultEnd;
        if (preg_match('/\(([^)]*)\)/', $segment, $matches)) {
            [$overrideStart, $overrideEnd] = mastercomDebtsPlanImportTimeRange($matches[1]);
            if ($overrideStart !== '' && $overrideEnd !== '') {
                $start = $overrideStart;
                $end = $overrideEnd;
            }
            $segment = trim(preg_replace('/\([^)]*\)/', '', $segment) ?? $segment);
        }

        $month = 0;
        $daysText = $segment;
        if (preg_match('/^(.+?)\/(\d{1,2})$/', $segment, $matches)) {
            $daysText = trim($matches[1]);
            $month = intval($matches[2]);
            $lastMonth = $month;
        } else {
            $month = $lastMonth > 0 ? $lastMonth : 0;
        }

        foreach (preg_split('/\s*-\s*/', $daysText) ?: [] as $dayText) {
            $day = intval(trim($dayText));
            if ($day <= 0) {
                continue;
            }
            $slotMonth = $month > 0 ? $month : ($day >= 24 ? 8 : 9);
            if (!checkdate($slotMonth, $day, $year) && $slotMonth === 9 && $day === 31) {
                $warnings[] = "Corretto 31/9 in 31/8 per '" . $dateText . "'";
                $slotMonth = 8;
            }
            if (!checkdate($slotMonth, $day, $year)) {
                $warnings[] = "Data non valida ignorata: " . $day . "/" . $slotMonth . "/" . $year . " in '" . $dateText . "'";
                continue;
            }
            $slots[] = mastercomDebtsPlanFormatSlot([
                'date' => sprintf('%04d-%02d-%02d', $year, $slotMonth, $day),
                'start' => $start,
                'end' => $end,
            ]);
        }
    }

    return $slots;
}

function mastercomDebtsPlanImportDebtIndex(int $schoolYearId): array
{
    $index = [];
    foreach (mastercomDebtsPlanSourceRows($schoolYearId) as $row) {
        $studentName = mastercomDebtsPlanStudentLabel($row);
        $className = trim((string)(($row['classe_gestore'] ?? '') ?: ($row['classe'] ?? '')));
        $subjectName = trim((string)(($row['materia_gestore'] ?? '') ?: ($row['materia'] ?? '')));
        foreach (mastercomDebtsPlanImportSubjectKeys($subjectName) as $subjectKey) {
            $key = mastercomDebtsPlanImportNorm($className) . '|' . $subjectKey . '|' . mastercomDebtsPlanImportNorm($studentName);
            $index[$key] = intval($row['id_studente_gestore'] ?? 0);
        }
    }
    return $index;
}

function mastercomDebtsPlanImportRealCsv(int $schoolYearId, string $csvPath, bool $apply = false): array
{
    mastercomDebtsPlanEnsureTables();
    $summary = [
        'ok' => false,
        'applied' => false,
        'rows' => 0,
        'groups' => 0,
        'matched' => 0,
        'unmatched' => [],
        'warnings' => [],
        'itinere_groups' => 0,
        'auditors' => 0,
        'auditors_matched' => 0,
        'auditors_unmatched' => 0,
        'message' => '',
    ];

    if ($schoolYearId <= 0 || !is_file($csvPath)) {
        $summary['message'] = 'File CSV o anno scolastico non valido.';
        return $summary;
    }

    $calendarYear = mastercomDebtsPlanCalendarYear($schoolYearId);
    $debtIndex = mastercomDebtsPlanImportDebtIndex($schoolYearId);
    $handle = fopen($csvPath, 'rb');
    if (!$handle) {
        $summary['message'] = 'Impossibile aprire il CSV.';
        return $summary;
    }

    $headers = fgetcsv($handle, 0, ';');
    if (!is_array($headers)) {
        fclose($handle);
        $summary['message'] = 'CSV vuoto o intestazione non valida.';
        return $summary;
    }
    $headerMap = [];
    foreach ($headers as $index => $header) {
        $canonicalKey = mastercomDebtsPlanImportHeaderKey((string)$header);
        if ($canonicalKey !== '') {
            $headerMap[$canonicalKey] = $index;
        }
    }
    $requiredHeaders = ['Studente', 'Classe studente', 'Materia', 'Docente classe', 'Data corso', 'Orario corso', 'Docente corso', 'Aula'];
    foreach ($requiredHeaders as $header) {
        if (!array_key_exists(mastercomDebtsPlanImportHeaderKey($header), $headerMap)) {
            fclose($handle);
            $summary['message'] = 'Colonna mancante nel CSV: ' . $header;
            return $summary;
        }
    }

    $groups = [];
    while (($values = fgetcsv($handle, 0, ';')) !== false) {
        $row = [];
        foreach ($requiredHeaders as $header) {
            $index = intval($headerMap[mastercomDebtsPlanImportHeaderKey($header)] ?? -1);
            $row[$header] = $index >= 0 ? trim((string)($values[$index] ?? '')) : '';
        }

        $studentInfo = mastercomDebtsPlanImportStudentInfo($row['Studente'] ?? '');
        $student = $studentInfo['name'];
        $isAuditor = !empty($studentInfo['auditor']);
        $class = $row['Classe studente'] ?? '';
        $subject = mastercomDebtsPlanImportCanonicalSubject($row['Materia'] ?? '');
        if ($student === '' || $class === '' || $subject === '') {
            continue;
        }

        $summary['rows']++;
        $dateText = $row['Data corso'] ?? '';
        $timeText = $row['Orario corso'] ?? '';
        $courseTeacher = $row['Docente corso'] ?? '';
        $aula = $row['Aula'] ?? '';
        $kind = ($dateText === '' || $timeText === '' || mastercomDebtsPlanImportNorm($courseTeacher) === 'initinere') ? 'itinere' : 'corso';
        $courseKey = sha1(mastercomDebtsPlanImportNorm($subject) . '|' . mastercomDebtsPlanImportNorm($dateText) . '|' . mastercomDebtsPlanImportNorm($timeText) . '|' . mastercomDebtsPlanImportNorm($courseTeacher) . '|' . mastercomDebtsPlanImportNorm($aula));

        if (!isset($groups[$courseKey])) {
            $slots = $kind === 'corso' ? mastercomDebtsPlanImportParseSlots($dateText, $timeText, $calendarYear, $summary['warnings']) : [];
            $groups[$courseKey] = [
                'course_key' => $courseKey,
                'kind' => $kind,
                'subject' => $subject,
                'date_text' => $dateText,
                'time_text' => $timeText,
                'teacher_name' => $courseTeacher,
                'aula' => $aula,
                'slots' => $slots,
                'students' => [],
                'class_years' => [],
            ];
        }

        $studentId = 0;
        if ($isAuditor) {
            $studentId = mastercomDebtsPlanSyncStudentIdByNameClass($student, $class, $schoolYearId);
        } else {
            foreach (mastercomDebtsPlanImportSubjectKeys($subject) as $subjectKey) {
                $debtKey = mastercomDebtsPlanImportNorm($class) . '|' . $subjectKey . '|' . mastercomDebtsPlanImportNorm($student);
                $studentId = intval($debtIndex[$debtKey] ?? 0);
                if ($studentId > 0) {
                    break;
                }
            }
        }
        if ($isAuditor) {
            $summary['auditors'] = intval($summary['auditors'] ?? 0) + 1;
            if ($studentId > 0) {
                $summary['auditors_matched'] = intval($summary['auditors_matched'] ?? 0) + 1;
            } else {
                $summary['auditors_unmatched'] = intval($summary['auditors_unmatched'] ?? 0) + 1;
                $summary['unmatched'][] = $student . ' - ' . $class . ' - ' . $subject . ' (uditore non agganciato)';
            }
        } elseif ($studentId > 0) {
            $summary['matched']++;
        } else {
            $summary['unmatched'][] = $student . ' - ' . $class . ' - ' . $subject;
        }

        $classYear = mastercomDebtsPlanClassYear($class);
        if ($classYear !== 'NA') {
            $groups[$courseKey]['class_years'][$classYear] = true;
        }
        $groups[$courseKey]['students'][] = [
            'id_studente' => $studentId,
            'student_name' => $student,
            'class_name' => $class,
            'debt_teacher_name' => $row['Docente classe'] ?? '',
            'auditor' => $isAuditor,
        ];
    }
    fclose($handle);

    $summary['groups'] = count($groups);
    $summary['itinere_groups'] = count(array_filter($groups, function ($group) {
        return ($group['kind'] ?? '') === 'itinere';
    }));
    $summary['unmatched'] = array_values(array_unique($summary['unmatched']));
    $summary['warnings'] = array_values(array_unique($summary['warnings']));
    $summary['ok'] = true;

    if (!$apply) {
        $summary['message'] = 'Anteprima completata. Nessun dato scritto.';
        return $summary;
    }

    dbExec("START TRANSACTION");
    dbExec("
        DELETE s
        FROM mastercom_carenze_plan_real_students s
        INNER JOIN mastercom_carenze_plan_real_courses c ON c.id = s.course_id
        WHERE c.id_anno_scolastico = " . dbI($schoolYearId) . "
    ");
    dbExec("DELETE FROM mastercom_carenze_plan_real_courses WHERE id_anno_scolastico = " . dbI($schoolYearId));

    $sortOrder = 0;
    foreach ($groups as $group) {
        $classYears = array_keys($group['class_years']);
        sort($classYears, SORT_NATURAL);
        dbExec("
            INSERT INTO mastercom_carenze_plan_real_courses
                (id_anno_scolastico, course_key, kind, class_year, subject, date_text, time_text, slots_json, teacher_name, aula, sort_order, source_hash, created_at, updated_at)
            VALUES
                (" . dbI($schoolYearId) . ",
                 " . dbQ($group['course_key']) . ",
                 " . dbQ($group['kind']) . ",
                 " . dbQ(implode(',', $classYears)) . ",
                 " . dbQ($group['subject']) . ",
                 " . dbQ($group['date_text']) . ",
                 " . dbQ($group['time_text']) . ",
                 " . dbQ(json_encode($group['slots'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . ",
                 " . dbQ($group['teacher_name']) . ",
                 " . dbQ($group['aula']) . ",
                 " . dbI($sortOrder++) . ",
                 " . dbQ(sha1(json_encode($group, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) . ",
                 NOW(),
                 NOW())
        ");
        $courseId = dblastId();
        $studentOrder = 0;
        foreach ($group['students'] as $student) {
            dbExec("
                INSERT INTO mastercom_carenze_plan_real_students
                    (course_id, id_studente, student_name, class_name, debt_teacher_name, auditor, sort_order)
                VALUES
                    (" . dbI($courseId) . ",
                     " . dbI($student['id_studente'] > 0 ? $student['id_studente'] : null) . ",
                     " . dbQ($student['student_name']) . ",
                     " . dbQ($student['class_name']) . ",
                     " . dbQ($student['debt_teacher_name']) . ",
                     " . dbI(!empty($student['auditor']) ? 1 : 0) . ",
                     " . dbI($studentOrder++) . ")
            ");
        }
    }
    dbExec("COMMIT");

    $summary['applied'] = true;
    $summary['message'] = 'Import applicato: ' . $summary['groups'] . ' gruppi e ' . $summary['rows'] . ' partecipazioni salvati.';
    return $summary;
}

function mastercomDebtsPlanPreviousSchoolYearId(int $courseSchoolYearId): int
{
    $label = mastercomDebtsPlanSchoolYearLabel($courseSchoolYearId);
    if (preg_match('/^(\d{4})\s*\/\s*\d{4}$/', $label, $matches)) {
        $previousLabel = (intval($matches[1]) - 1) . '/' . intval($matches[1]);
        $previousId = dbGetValue("SELECT id FROM anno_scolastico WHERE anno = " . dbQ($previousLabel) . " LIMIT 1");
        if ($previousId !== null) {
            return intval($previousId);
        }
    }
    $previousId = dbGetValue("SELECT id FROM anno_scolastico WHERE id < " . dbI($courseSchoolYearId) . " ORDER BY id DESC LIMIT 1");
    return $previousId !== null ? intval($previousId) : 0;
}

function mastercomDebtsPlanResolveSubjectId(string $subjectName): int
{
    $subjectName = mastercomDebtsPlanImportCanonicalSubject($subjectName);
    $rows = dbGetAll("SELECT id, nome FROM materia") ?: [];
    $wantedKeys = mastercomDebtsPlanImportSubjectKeys($subjectName);
    foreach ($rows as $row) {
        if (in_array(mastercomDebtsPlanImportNorm((string)($row['nome'] ?? '')), $wantedKeys, true)) {
            return intval($row['id'] ?? 0);
        }
    }
    return 0;
}

function mastercomDebtsPlanTeacherInputName(string $teacherName): string
{
    $teacherName = trim(preg_replace('/\s+\d+$/', '', $teacherName) ?? $teacherName);
    $teacherName = preg_replace('/\bprof(?:\.|essoressa|essore|ssa)?\b/iu', ' ', $teacherName) ?? $teacherName;
    return trim(preg_replace('/\s+/', ' ', $teacherName) ?? $teacherName);
}

function mastercomDebtsPlanTeacherLabel(array $teacher): string
{
    return trim((string)($teacher['cognome'] ?? '') . ' ' . (string)($teacher['nome'] ?? ''));
}

function mastercomDebtsPlanTeacherClassIds(array $students): array
{
    $classKeys = [];
    foreach ($students as $student) {
        $className = trim((string)($student['class_name'] ?? $student['class'] ?? ''));
        if ($className !== '') {
            $classKeys[mastercomDebtsPlanImportNorm($className)] = true;
        }
    }
    if (empty($classKeys)) {
        return [];
    }

    $ids = [];
    foreach (dbGetAll("SELECT id, classe FROM classi") ?: [] as $row) {
        if (isset($classKeys[mastercomDebtsPlanImportNorm((string)($row['classe'] ?? ''))])) {
            $ids[] = intval($row['id'] ?? 0);
        }
    }
    return array_values(array_filter(array_unique($ids)));
}

function mastercomDebtsPlanFilterTeachersByTeaching(array $teachers, int $subjectId, int $schoolYearId, array $students): array
{
    $teacherIds = array_values(array_filter(array_map(function ($row) {
        return intval($row['id'] ?? 0);
    }, $teachers)));
    if (empty($teacherIds) || $subjectId <= 0 || $schoolYearId <= 0) {
        return [];
    }

    $classIds = mastercomDebtsPlanTeacherClassIds($students);
    $query = "
        SELECT DISTINCT id_docente
        FROM docente_insegna
        WHERE id_docente IN (" . implode(',', $teacherIds) . ")
          AND id_anno_scolastico = " . dbI($schoolYearId) . "
          AND id_materia = " . dbI($subjectId) . "
    ";
    if (!empty($classIds)) {
        $query .= " AND id_classe IN (" . implode(',', $classIds) . ")";
    }

    $allowed = [];
    foreach (dbGetAll($query) ?: [] as $row) {
        $allowed[intval($row['id_docente'] ?? 0)] = true;
    }
    if (empty($allowed)) {
        return [];
    }

    return array_values(array_filter($teachers, function ($row) use ($allowed) {
        return isset($allowed[intval($row['id'] ?? 0)]);
    }));
}

function mastercomDebtsPlanResolveTeacher(string $teacherName, int $subjectId = 0, int $schoolYearId = 0, array $students = []): array
{
    $teacherName = mastercomDebtsPlanTeacherInputName($teacherName);
    if ($teacherName === '' || mastercomDebtsPlanImportNorm($teacherName) === 'initinere') {
        return ['id' => 0, 'status' => 'missing', 'message' => 'Docente corso vuoto'];
    }

    $teacherKey = mastercomDebtsPlanImportNorm($teacherName);
    $exact = [];
    $surname = [];
    $initial = [];
    foreach (dbGetAll("SELECT id, cognome, nome FROM docente WHERE attivo = 1") ?: [] as $row) {
        $surnameKey = mastercomDebtsPlanImportNorm((string)($row['cognome'] ?? ''));
        $nameKey = mastercomDebtsPlanImportNorm((string)($row['nome'] ?? ''));
        $fullKey = mastercomDebtsPlanImportNorm(trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')));
        $reverseFullKey = mastercomDebtsPlanImportNorm(trim((string)($row['nome'] ?? '') . ' ' . (string)($row['cognome'] ?? '')));
        $firstNameInitial = $nameKey !== '' ? substr($nameKey, 0, 1) : '';

        if ($teacherKey === $fullKey || $teacherKey === $reverseFullKey) {
            $exact[] = $row;
        } elseif ($teacherKey === $surnameKey) {
            $surname[] = $row;
        } elseif ($firstNameInitial !== '' && ($teacherKey === $surnameKey . $firstNameInitial || $teacherKey === $firstNameInitial . $surnameKey)) {
            $initial[] = $row;
        }
    }

    foreach ([$exact, $surname, $initial] as $candidates) {
        if (count($candidates) === 1) {
            return ['id' => intval($candidates[0]['id'] ?? 0), 'status' => 'ok', 'message' => ''];
        }
        if (count($candidates) > 1) {
            $teachingCandidates = mastercomDebtsPlanFilterTeachersByTeaching($candidates, $subjectId, $schoolYearId, $students);
            if (count($teachingCandidates) === 1) {
                return ['id' => intval($teachingCandidates[0]['id'] ?? 0), 'status' => 'ok_teaching', 'message' => ''];
            }
            $labels = array_map('mastercomDebtsPlanTeacherLabel', !empty($teachingCandidates) ? $teachingCandidates : $candidates);
            return [
                'id' => 0,
                'status' => 'ambiguous',
                'message' => 'Docente corso ambiguo: ' . $teacherName . ' (' . implode(', ', array_slice($labels, 0, 5)) . ')',
            ];
        }
    }

    return ['id' => 0, 'status' => 'missing', 'message' => 'Docente corso non trovato in GestOre: ' . $teacherName];
}

function mastercomDebtsPlanResolveTeacherId(string $teacherName): int
{
    return intval(mastercomDebtsPlanResolveTeacher($teacherName)['id'] ?? 0);
}

function mastercomDebtsPlanRealCoursesForSync(int $courseSchoolYearId): array
{
    $courses = dbGetAll("
        SELECT *
        FROM mastercom_carenze_plan_real_courses
        WHERE id_anno_scolastico = " . dbI($courseSchoolYearId) . "
          AND kind = 'corso'
        ORDER BY sort_order ASC, id ASC
    ") ?: [];
    if (empty($courses)) {
        return [];
    }

    $ids = array_values(array_filter(array_map(function ($row) {
        return intval($row['id'] ?? 0);
    }, $courses)));
    $studentsByCourse = [];
    if (!empty($ids)) {
        foreach (dbGetAll("
            SELECT *
            FROM mastercom_carenze_plan_real_students
            WHERE course_id IN (" . implode(',', $ids) . ")
            ORDER BY course_id ASC, sort_order ASC, class_name ASC, student_name ASC
        ") ?: [] as $studentRow) {
            $studentsByCourse[intval($studentRow['course_id'] ?? 0)][] = $studentRow;
        }
    }

    foreach ($courses as &$course) {
        $course['students'] = $studentsByCourse[intval($course['id'] ?? 0)] ?? [];
        $slots = json_decode((string)($course['slots_json'] ?? '[]'), true);
        $course['slots'] = is_array($slots) ? array_values(array_map('mastercomDebtsPlanFormatSlot', $slots)) : [];
    }
    unset($course);

    return $courses;
}

function mastercomDebtsPlanSyncStudentId(array $student, int $debtSchoolYearId): int
{
    $studentId = intval($student['id_studente'] ?? 0);
    if ($studentId > 0) {
        return $studentId;
    }

    $studentName = mastercomDebtsPlanImportStudentInfo((string)($student['student_name'] ?? ''))['name'];
    $className = trim((string)($student['class_name'] ?? ''));
    $studentId = mastercomDebtsPlanSyncStudentIdByNameClass($studentName, $className, $debtSchoolYearId);
    if ($studentId > 0) {
        return $studentId;
    }
    if (intval($student['auditor'] ?? 0) === 1) {
        return 0;
    }
    $debtIndex = mastercomDebtsPlanImportDebtIndex($debtSchoolYearId);
    foreach (mastercomDebtsPlanImportSubjectKeys((string)($student['subject'] ?? '')) as $subjectKey) {
        $key = mastercomDebtsPlanImportNorm($className) . '|' . $subjectKey . '|' . mastercomDebtsPlanImportNorm($studentName);
        $studentId = intval($debtIndex[$key] ?? 0);
        if ($studentId > 0) {
            return $studentId;
        }
    }
    return 0;
}

function mastercomDebtsPlanSyncStudentIdByNameClass(string $studentName, string $className, int $schoolYearId): int
{
    $nameKey = mastercomDebtsPlanImportNorm($studentName);
    if ($nameKey === '') {
        return 0;
    }
    $classKey = mastercomDebtsPlanImportNorm($className);
    $rows = dbGetAll("
        SELECT
            s.id,
            s.cognome,
            s.nome,
            c.classe
        FROM studente s
        LEFT JOIN studente_frequenta sf
               ON sf.id_studente = s.id
              AND sf.id_anno_scolastico = " . dbI($schoolYearId) . "
        LEFT JOIN classi c ON c.id = sf.id_classe
        WHERE COALESCE(s.attivo, 1) = 1
    ") ?: [];

    $matches = [];
    foreach ($rows as $row) {
        $full1 = mastercomDebtsPlanImportNorm(trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')));
        $full2 = mastercomDebtsPlanImportNorm(trim((string)($row['nome'] ?? '') . ' ' . (string)($row['cognome'] ?? '')));
        if ($nameKey !== $full1 && $nameKey !== $full2) {
            continue;
        }
        if ($classKey !== '' && mastercomDebtsPlanImportNorm((string)($row['classe'] ?? '')) !== $classKey) {
            continue;
        }
        $matches[] = intval($row['id'] ?? 0);
    }
    $matches = array_values(array_unique(array_filter($matches)));
    return count($matches) === 1 ? intval($matches[0]) : 0;
}

function mastercomDebtsPlanSyncDates(int $courseId, array $slots, string $aula, array &$summary): void
{
    $existing = dbGetAll("SELECT * FROM corso_date WHERE id_corso = " . dbI($courseId) . " ORDER BY data_inizio ASC, id ASC") ?: [];
    $max = max(count($existing), count($slots));
    for ($i = 0; $i < $max; $i++) {
        $slot = $slots[$i] ?? null;
        $existingRow = $existing[$i] ?? null;
        if ($slot !== null) {
            $start = trim((string)($slot['date'] ?? '')) . ' ' . trim((string)($slot['start'] ?? '')) . ':00';
            $end = trim((string)($slot['date'] ?? '')) . ' ' . trim((string)($slot['end'] ?? '')) . ':00';
            if ($existingRow) {
                dbExec("
                    UPDATE corso_date
                    SET data_inizio = " . dbQ($start) . ",
                        data_fine = " . dbQ($end) . ",
                        aula = " . dbQ($aula) . "
                    WHERE id = " . dbI($existingRow['id'] ?? 0) . "
                ");
            } else {
                dbExec("
                    INSERT INTO corso_date (id_corso, data_inizio, data_fine, aula)
                    VALUES (" . dbI($courseId) . ", " . dbQ($start) . ", " . dbQ($end) . ", " . dbQ($aula) . ")
                ");
            }
        } elseif ($existingRow) {
            if (intval($existingRow['firmato'] ?? 0) === 1) {
                $summary['warnings'][] = 'Lezione firmata non rimossa dal corso #' . $courseId . ': ' . (string)($existingRow['data_inizio'] ?? '');
            } else {
                dbExec("DELETE FROM corso_date WHERE id = " . dbI($existingRow['id'] ?? 0));
            }
        }
    }
}

function mastercomDebtsPlanSyncStudents(int $courseId, array $students, int $debtSchoolYearId, string $subjectName, array &$summary): void
{
    $wanted = [];
    foreach ($students as $student) {
        $student['subject'] = $subjectName;
        $studentId = mastercomDebtsPlanSyncStudentId($student, $debtSchoolYearId);
        if ($studentId > 0) {
            $wanted[$studentId] = intval($student['auditor'] ?? 0) === 1 ? 'auditor' : 'debt';
        } else {
            $suffix = intval($student['auditor'] ?? 0) === 1 ? ' (uditore non agganciato)' : '';
            $summary['unmatched_students'][] = trim((string)($student['student_name'] ?? '')) . ' - ' . trim((string)($student['class_name'] ?? '')) . ' - ' . $subjectName . $suffix;
        }
    }

    $existingRows = dbGetAll("SELECT id, id_studente FROM corso_iscritti WHERE id_corso = " . dbI($courseId)) ?: [];
    $existing = [];
    foreach ($existingRows as $row) {
        $existing[intval($row['id_studente'] ?? 0)] = intval($row['id'] ?? 0);
    }

    foreach ($wanted as $studentId => $studentType) {
        if (!isset($existing[$studentId])) {
            dbExec("INSERT INTO corso_iscritti (id_corso, id_studente) VALUES (" . dbI($courseId) . ", " . dbI($studentId) . ")");
            $summary['students_added']++;
        }
        if ($studentType === 'auditor') {
            dbExec("DELETE FROM corso_esiti WHERE id_corso = " . dbI($courseId) . " AND id_studente = " . dbI($studentId));
            $summary['auditors_synced'] = intval($summary['auditors_synced'] ?? 0) + 1;
        } else {
            dbExec("
                INSERT INTO corso_esiti (id_corso, id_studente)
                SELECT " . dbI($courseId) . ", " . dbI($studentId) . "
                WHERE NOT EXISTS (
                    SELECT 1 FROM corso_esiti
                    WHERE id_corso = " . dbI($courseId) . "
                      AND id_studente = " . dbI($studentId) . "
                )
            ");
        }
    }

    foreach ($existing as $studentId => $subscriptionId) {
        if ($studentId <= 0 || isset($wanted[$studentId])) {
            continue;
        }
        dbExec("DELETE FROM corso_iscritti WHERE id = " . dbI($subscriptionId));
        dbExec("DELETE FROM corso_esiti WHERE id_corso = " . dbI($courseId) . " AND id_studente = " . dbI($studentId));
        $summary['students_removed']++;
    }
}

function mastercomDebtsPlanSyncRealCoursesToCorsi(int $realPlanSchoolYearId, ?int $debtSchoolYearId = null, ?int $courseSchoolYearId = null): array
{
    mastercomDebtsPlanEnsureTables();
    $courseSchoolYearId = $courseSchoolYearId !== null ? intval($courseSchoolYearId) : $realPlanSchoolYearId;
    $debtSchoolYearId = $debtSchoolYearId !== null ? intval($debtSchoolYearId) : mastercomDebtsPlanPreviousSchoolYearId($courseSchoolYearId);
    $summary = [
        'ok' => false,
        'message' => '',
        'real_plan_year_id' => $realPlanSchoolYearId,
        'course_year_id' => $courseSchoolYearId,
        'debt_year_id' => $debtSchoolYearId,
        'created' => 0,
        'updated' => 0,
        'dates_synced' => 0,
        'students_added' => 0,
        'students_removed' => 0,
        'auditors_synced' => 0,
        'actual_courses' => 0,
        'mapped_courses' => 0,
        'created_ids' => [],
        'skipped' => [],
        'warnings' => [],
        'unmatched_students' => [],
    ];

    if ($realPlanSchoolYearId <= 0 || $courseSchoolYearId <= 0 || $debtSchoolYearId <= 0) {
        $summary['message'] = 'Anno piano, anno corsi o anno carenze non valido.';
        return $summary;
    }

    $realCourses = mastercomDebtsPlanRealCoursesForSync($realPlanSchoolYearId);
    if (empty($realCourses)) {
        $summary['message'] = 'Nessun corso reale con calendario da sincronizzare.';
        return $summary;
    }

    dbExec("START TRANSACTION");
    foreach ($realCourses as $realCourse) {
        $realCourseId = intval($realCourse['id'] ?? 0);
        $courseKey = trim((string)($realCourse['course_key'] ?? ''));
        $subjectName = mastercomDebtsPlanImportCanonicalSubject((string)($realCourse['subject'] ?? ''));
        $subjectId = mastercomDebtsPlanResolveSubjectId($subjectName);
        if ($subjectId <= 0) {
            $summary['skipped'][] = 'Materia non trovata: ' . $subjectName;
            continue;
        }

        $teacherName = trim((string)($realCourse['teacher_name'] ?? ''));
        $teacherMatch = mastercomDebtsPlanResolveTeacher($teacherName, $subjectId, $debtSchoolYearId, $realCourse['students'] ?? []);
        $teacherId = intval($teacherMatch['id'] ?? 0);
        if ($teacherId <= 0) {
            $reason = trim((string)($teacherMatch['message'] ?? 'Docente corso non agganciato'));
            $summary['skipped'][] = $reason . ' - ' . $subjectName;
            continue;
        }

        $map = dbGetFirst("
            SELECT *
            FROM mastercom_carenze_plan_course_map
            WHERE id_anno_scolastico_corsi = " . dbI($courseSchoolYearId) . "
              AND real_course_id = " . dbI($realCourseId) . "
            LIMIT 1
        ");
        if ($map === null && $courseKey !== '') {
            $map = dbGetFirst("
                SELECT *
                FROM mastercom_carenze_plan_course_map
                WHERE id_anno_scolastico_corsi = " . dbI($courseSchoolYearId) . "
                  AND course_key = " . dbQ($courseKey) . "
                LIMIT 1
            ");
        }
        $courseId = intval($map['id_corso'] ?? 0);
        if ($courseId > 0 && dbGetFirst("SELECT id FROM corso WHERE id = " . dbI($courseId) . " LIMIT 1") === null) {
            $courseId = 0;
        }

        $title = 'Recupero carenze - ' . $subjectName;
        if ($courseId > 0) {
            dbExec("
                UPDATE mastercom_carenze_plan_course_map
                SET id_anno_scolastico_carenze = " . dbI($debtSchoolYearId) . ",
                    real_course_id = " . dbI($realCourseId) . ",
                    course_key = " . dbQ($courseKey) . ",
                    updated_at = NOW()
                WHERE id = " . dbI($map['id'] ?? 0) . "
            ");
            dbExec("
                UPDATE corso
                SET id_materia = " . dbI($subjectId) . ",
                    id_docente = " . dbI($teacherId) . ",
                    id_anno_scolastico = " . dbI($courseSchoolYearId) . ",
                    titolo = " . dbQ($title) . ",
                    carenza = 1,
                    carenza_sessione = 1,
                    in_itinere = 0,
                    prevede_esami = 1
                WHERE id = " . dbI($courseId) . "
            ");
            $summary['updated']++;
        } else {
            dbExec("
                INSERT INTO corso
                    (id_materia, id_docente, id_anno_scolastico, titolo, carenza, carenza_sessione, in_itinere, prevede_esami)
                VALUES
                    (" . dbI($subjectId) . ",
                     " . dbI($teacherId) . ",
                     " . dbI($courseSchoolYearId) . ",
                     " . dbQ($title) . ",
                     1,
                     1,
                     0,
                     1)
            ");
            $courseId = intval(dblastId());
            if ($courseId <= 0 || dbGetFirst("SELECT id FROM corso WHERE id = " . dbI($courseId) . " LIMIT 1") === null) {
                $summary['warnings'][] = 'Insert corso non verificato per ' . $subjectName . ' - ' . ($teacherName !== '' ? $teacherName : '(docente vuoto)') . ' (id generato: ' . $courseId . ')';
                continue;
            }
            dbExec("
                INSERT INTO mastercom_carenze_plan_course_map
                    (id_anno_scolastico_corsi, id_anno_scolastico_carenze, real_course_id, course_key, id_corso, created_at, updated_at)
                VALUES
                    (" . dbI($courseSchoolYearId) . ",
                     " . dbI($debtSchoolYearId) . ",
                     " . dbI($realCourseId) . ",
                     " . dbQ($courseKey) . ",
                     " . dbI($courseId) . ",
                     NOW(),
                     NOW())
                ON DUPLICATE KEY UPDATE
                    id_anno_scolastico_carenze = VALUES(id_anno_scolastico_carenze),
                    course_key = VALUES(course_key),
                    id_corso = VALUES(id_corso),
                    updated_at = NOW()
            ");
            $summary['created']++;
            if (count($summary['created_ids']) < 10) {
                $summary['created_ids'][] = $courseId;
            }
        }

        dbExec("DELETE FROM corso_docenti WHERE id_corso = " . dbI($courseId));
        dbExec("INSERT INTO corso_docenti (id_corso, id_docente, principale) VALUES (" . dbI($courseId) . ", " . dbI($teacherId) . ", 1)");

        mastercomDebtsPlanSyncDates($courseId, $realCourse['slots'] ?? [], trim((string)($realCourse['aula'] ?? '')), $summary);
        $summary['dates_synced'] += count($realCourse['slots'] ?? []);
        mastercomDebtsPlanSyncStudents($courseId, $realCourse['students'] ?? [], $debtSchoolYearId, $subjectName, $summary);
    }
    dbExec("COMMIT");

    $actualRows = dbGetFirst("
        SELECT COUNT(DISTINCT c.id) AS actual_courses,
               COUNT(DISTINCT m.id) AS mapped_courses
        FROM mastercom_carenze_plan_course_map m
        LEFT JOIN corso c ON c.id = m.id_corso
        WHERE m.id_anno_scolastico_corsi = " . dbI($courseSchoolYearId) . "
          AND m.id_anno_scolastico_carenze = " . dbI($debtSchoolYearId) . "
    ") ?: [];
    $summary['actual_courses'] = intval($actualRows['actual_courses'] ?? 0);
    $summary['mapped_courses'] = intval($actualRows['mapped_courses'] ?? 0);
    if ($summary['mapped_courses'] > 0 && $summary['actual_courses'] === 0) {
        $summary['warnings'][] = 'Attenzione: la mappa contiene corsi sincronizzati, ma nessun id risulta presente nella tabella corso.';
    }

    $summary['skipped'] = array_values(array_unique($summary['skipped']));
    $summary['warnings'] = array_values(array_unique($summary['warnings']));
    $summary['unmatched_students'] = array_values(array_unique($summary['unmatched_students']));
    $summary['ok'] = true;
    $summary['message'] = 'Sincronizzazione completata: ' . $summary['created'] . ' corsi creati, ' . $summary['updated'] . ' aggiornati, ' . $summary['actual_courses'] . ' presenti in corso.';
    return $summary;
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

    $mastercomRows = dbGetAll("
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
            CONCAT(COALESCE(s.cognome, ''), ' ', COALESCE(s.nome, '')) AS studente_gestore,
            (
                SELECT GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(dc.cognome, ''), ' ', COALESCE(dc.nome, ''))) ORDER BY dc.cognome, dc.nome SEPARATOR ', ')
                FROM carenze cdoc
                INNER JOIN docente dc ON dc.id = cdoc.id_docente
                WHERE cdoc.id_studente = mc.id_studente_gestore
                  AND cdoc.id_materia = mc.id_materia_gestore
                  AND cdoc.id_classe = mc.id_classe_gestore
                  AND cdoc.id_anno_scolastico = mc.id_anno_scolastico
                  AND COALESCE(cdoc.id_docente, 0) > 0
            ) AS docente_carenza,
            (
                SELECT GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(di_doc.cognome, ''), ' ', COALESCE(di_doc.nome, ''))) ORDER BY di_doc.cognome, di_doc.nome SEPARATOR ', ')
                FROM docente_insegna di
                INNER JOIN docente di_doc ON di_doc.id = di.id_docente
                LEFT JOIN classi di_cl ON di_cl.id = di.id_classe
                WHERE (
                        di.id_classe = mc.id_classe_gestore
                        OR (
                            COALESCE(mc.id_classe_gestore, 0) <= 0
                            AND di_cl.classe = mc.classe
                        )
                  )
                  AND di.id_materia = mc.id_materia_gestore
                  AND (
                        di.id_anno_scolastico = mc.id_anno_scolastico
                        OR di.id_anno_scolastico IS NULL
                        OR di.id_anno_scolastico = 0
                  )
            ) AS docenti_insegnamento
        FROM mastercom_carenze mc
        LEFT JOIN materia m ON m.id = mc.id_materia_gestore
        LEFT JOIN classi cl ON cl.id = mc.id_classe_gestore
        LEFT JOIN studente s ON s.id = mc.id_studente_gestore
        WHERE mc.id_anno_scolastico = " . dbI($schoolYearId) . "
          AND COALESCE(mc.recuperato_mastercom, 0) = 0
        ORDER BY mc.materia ASC, mc.studente_nome ASC, mc.classe ASC
    ") ?: [];

    $localRows = dbGetAll("
        SELECT
            c.id,
            0 AS mastercom_id_classe,
            cls.classe,
            0 AS mastercom_id_studente,
            c.id_studente AS id_studente_gestore,
            CONCAT(COALESCE(s.cognome, ''), ' ', COALESCE(s.nome, '')) AS studente_nome,
            c.id_anno_scolastico,
            m.nome AS materia,
            c.id_materia AS id_materia_gestore,
            0 AS recuperato_mastercom,
            'NEO_ISCRITTO' AS tipo_debito,
            m.nome AS materia_gestore,
            cls.classe AS classe_gestore,
            CONCAT(COALESCE(s.cognome, ''), ' ', COALESCE(s.nome, '')) AS studente_gestore,
            CONCAT(COALESCE(d.cognome, ''), ' ', COALESCE(d.nome, '')) AS docente_carenza,
            '' AS docenti_insegnamento
        FROM carenze c
        INNER JOIN studente s ON s.id = c.id_studente
        INNER JOIN materia m ON m.id = c.id_materia
        INNER JOIN classi cls ON cls.id = c.id_classe
        LEFT JOIN docente d ON d.id = c.id_docente
        WHERE c.id_anno_scolastico = " . dbI($schoolYearId) . "
          AND UPPER(TRIM(cls.classe)) IN ('MEDIE', 'EE')
          AND COALESCE(c.id_docente, 0) = " . dbI(MASTERCOM_DEBTS_PLAN_NEO_CARENZE_DOCENTE_ID) . "
        ORDER BY m.nome ASC, s.cognome ASC, s.nome ASC
    ") ?: [];

    return array_merge($mastercomRows, $localRows);
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
        $teacherName = trim((string)($row['docente_carenza'] ?? ''));
        if ($teacherName === '') {
            $teacherName = trim((string)($row['docenti_insegnamento'] ?? ''));
        }
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
            'teacher' => $teacherName,
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

    $calendarYear = mastercomDebtsPlanCalendarYear($schoolYearId);
    $realPlan = mastercomDebtsPlanLoadRealGroups($schoolYearId);
    if (!empty($realPlan['course_groups']) || !empty($realPlan['autonomous_groups'])) {
        $courseGroups = $realPlan['course_groups'];
        $autonomousGroups = $realPlan['autonomous_groups'];
        return [
            'school_year_id' => $schoolYearId,
            'school_year_label' => mastercomDebtsPlanSchoolYearLabel($schoolYearId),
            'calendar_year' => $calendarYear,
            'source_rows' => intval($realPlan['source_rows'] ?? 0),
            'base_groups' => array_merge($courseGroups, $autonomousGroups),
            'course_groups' => $courseGroups,
            'scheduled_groups' => $courseGroups,
            'unscheduled_groups' => [],
            'autonomous_groups' => $autonomousGroups,
            'slots' => mastercomDebtsPlanSlots($calendarYear),
            'student_course_counts' => $realPlan['student_course_counts'] ?? [],
            'locks' => [],
            'min_size' => $minSize,
            'max_size' => $maxSize,
            'using_imported_plan' => true,
        ];
    }

    $rows = mastercomDebtsPlanSourceRows($schoolYearId);
    $baseGroups = mastercomDebtsPlanBuildBaseGroups($rows);
    [$courseGroups, $autonomousGroups] = mastercomDebtsPlanSplitGroups($baseGroups, $minSize, $maxSize);
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

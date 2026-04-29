<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../connectMBApp.php';
require_once __DIR__ . '/admin_lib.php';

function mastercomNoIrcOrari(): array
{
    return ["07:50", "08:40", "09:30", "10:30", "11:20", "12:10", "13:00", "13:50", "14:40", "15:30", "16:20", "17:10", "18:00", "18:50", "19:40", "20:30", "21:30", "22:20"];
}

function mastercomNoIrcWeekdayLabels(): array
{
    return [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Gio',
        5 => 'Ven',
        6 => 'Sab',
        7 => 'Dom',
    ];
}

function mastercomNoIrcNormalizeDate(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        $date = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        $dt = new DateTime('now', new DateTimeZone('Europe/Rome'));
    }

    return $dt->format('Y-m-d');
}

function mastercomNoIrcWeekContext(string $referenceDate): array
{
    $referenceDate = mastercomNoIrcNormalizeDate($referenceDate);
    $dt = DateTime::createFromFormat('Y-m-d', $referenceDate, new DateTimeZone('Europe/Rome'));
    $monday = clone $dt;
    $monday->modify('monday this week');

    $days = [];
    $labels = mastercomNoIrcWeekdayLabels();
    for ($i = 0; $i < 5; $i++) {
        $day = clone $monday;
        $day->modify('+' . $i . ' days');
        $weekday = intval($day->format('N'));
        $days[$day->format('Y-m-d')] = [
            'date' => $day->format('Y-m-d'),
            'weekday' => $weekday,
            'weekday_label' => $labels[$weekday] ?? $day->format('D'),
            'label' => ($labels[$weekday] ?? $day->format('D')) . ' ' . $day->format('d/m'),
        ];
    }

    $sunday = clone $monday;
    $sunday->modify('+6 days');

    return [
        'reference_date' => $referenceDate,
        'week_start' => $monday->format('Y-m-d'),
        'week_end' => $sunday->format('Y-m-d'),
        'days' => $days,
    ];
}

function mastercomNoIrcNormalizeHour(?string $hour): string
{
    $hour = trim((string)$hour);
    if ($hour === '') {
        return '';
    }

    return substr($hour, 0, 5);
}

function mastercomNoIrcSlotSortKey(string $hour): int
{
    $hours = mastercomNoIrcOrari();
    $index = array_search($hour, $hours, true);
    if ($index === false) {
        return 9999;
    }

    return intval($index);
}

function mastercomNoIrcNormalizeClassLabel(?string $classLabel): string
{
    return strtoupper(trim((string)$classLabel));
}

function mastercomNoIrcMastercomClassBaseLabel(?string $className): string
{
    $className = trim((string)$className);
    if ($className === '') {
        return '';
    }

    $parts = preg_split('/\s+/', $className);
    return mastercomNoIrcNormalizeClassLabel((string)($parts[0] ?? ''));
}

function mastercomNoIrcChoiceCode(?string $choiceDescription): string
{
    $choiceDescription = trim((string)$choiceDescription);
    if ($choiceDescription === '') {
        return '';
    }

    if (preg_match('/^([A-Z]{2,4})\b/u', strtoupper($choiceDescription), $matches)) {
        return trim((string)$matches[1]);
    }

    return '';
}

function mastercomNoIrcIsOutsideSchoolChoice(?string $choiceDescription): bool
{
    $normalized = mastercomAdminNorm($choiceDescription);
    if ($normalized === '') {
        return false;
    }

    return strpos($normalized, 'ALLONTANARSI') !== false
        || strpos($normalized, 'ASSENTARSI DA EDIFICIO SCOLASTICO') !== false
        || strpos($normalized, ' AES ') !== false
        || strpos($normalized, 'AES ') === 0;
}

function mastercomNoIrcIsIrcSubject(?string $siglaMateria, ?string $nomeMateria): bool
{
    $siglaCompact = mastercomAdminNormCompact($siglaMateria);
    $nomeNorm = mastercomAdminNorm($nomeMateria);

    if (in_array($siglaCompact, ['IRC', 'REL', 'RELIGIONE'], true)) {
        return true;
    }

    if ($nomeNorm === '') {
        return false;
    }

    return strpos($nomeNorm, 'RELIG') !== false
        || strpos($nomeNorm, 'CATTOLICA') !== false;
}

function mastercomNoIrcOptionalTables(): array
{
    return [
        'mastercom_noirc_docenti_assegnazioni',
        'mastercom_noirc_appelli',
        'mastercom_noirc_appello_studenti',
    ];
}

function mastercomNoIrcAssignmentExtraColumns(): array
{
    return [
        'gruppo_label' => mastercomAdminTableColumnExists('mastercom_noirc_docenti_assegnazioni', 'gruppo_label'),
        'classi_incluse' => mastercomAdminTableColumnExists('mastercom_noirc_docenti_assegnazioni', 'classi_incluse'),
        'capienza_massima' => mastercomAdminTableColumnExists('mastercom_noirc_docenti_assegnazioni', 'capienza_massima'),
    ];
}

function mastercomNoIrcParseClassFilter(?string $classiIncluse): array
{
    $classiIncluse = trim((string)$classiIncluse);
    if ($classiIncluse === '') {
        return [];
    }

    $parts = preg_split('/[\s,;|]+/', $classiIncluse, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $values = [];
    foreach ($parts as $part) {
        $value = mastercomNoIrcNormalizeClassLabel($part);
        if ($value !== '') {
            $values[$value] = true;
        }
    }

    return array_values(array_keys($values));
}

function mastercomNoIrcLoadStudentPoolsByClass(): array
{
    global $__anno_scolastico_corrente_id;

    $hasAlternativeColumn = mastercomAdminTableColumnExists('mastercom_studenti', 'descrizione_materia_integrativa');
    $alternativeSql = $hasAlternativeColumn
        ? "s.descrizione_materia_integrativa AS descrizione_materia_integrativa,"
        : "NULL AS descrizione_materia_integrativa,";

    $rows = dbGetAll("
        SELECT
            s.mastercom_id_studente,
            s.id_studente_gestore,
            s.cognome,
            s.nome,
            s.registro_numero,
            s.esonero_religione,
            $alternativeSql
            s.raw_json,
            s.mastercom_id_classe_corrente,
            mc.nome AS classe_mastercom,
            st.attivo AS gestore_attivo,
            c.classe AS classe_locale
        FROM mastercom_studenti s
        LEFT JOIN studente st
            ON st.id = s.id_studente_gestore
        LEFT JOIN studente_frequenta sf
            ON sf.id_studente = st.id
            AND sf.id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
        LEFT JOIN classi c
            ON c.id = sf.id_classe
        LEFT JOIN mastercom_classi mc
            ON mc.mastercom_id_classe = s.mastercom_id_classe_corrente
        WHERE COALESCE(s.esonero_religione, 0) = 1
          AND (st.id IS NULL OR st.attivo = 1)
        ORDER BY s.cognome ASC, s.nome ASC
    ") ?: [];

    $pools = [];
    foreach ($rows as $row) {
        $classLabel = mastercomNoIrcNormalizeClassLabel($row['classe_locale'] ?? '');
        if ($classLabel === '') {
            $classLabel = mastercomNoIrcMastercomClassBaseLabel($row['classe_mastercom'] ?? '');
        }
        if ($classLabel === '') {
            continue;
        }

        $rawJson = json_decode((string)($row['raw_json'] ?? ''), true);
        $csvExport = is_array($rawJson['_csv_export'] ?? null) ? $rawJson['_csv_export'] : [];
        $choiceDescription = mastercomAdminCleanText($row['descrizione_materia_integrativa'] ?? '') ?? '';
        if ($choiceDescription === '' && !empty($csvExport)) {
            $choiceDescription = mastercomAdminCleanText($csvExport['descrizione_materia_integrativa'] ?? '') ?? '';
        }

        if (!isset($pools[$classLabel])) {
            $pools[$classLabel] = [
                'included' => [],
                'excluded_outside_count' => 0,
                'unknown_choice_count' => 0,
            ];
        }

        $student = [
            'mastercom_id_studente' => intval($row['mastercom_id_studente'] ?? 0),
            'id_studente_gestore' => intval($row['id_studente_gestore'] ?? 0),
            'registro_numero' => intval($row['registro_numero'] ?? 0),
            'cognome' => trim((string)($row['cognome'] ?? '')),
            'nome' => trim((string)($row['nome'] ?? '')),
            'classe' => $classLabel,
            'scelta_descrizione' => $choiceDescription,
            'scelta_sigla' => mastercomNoIrcChoiceCode($choiceDescription),
            'scelta_fuori_scuola' => mastercomNoIrcIsOutsideSchoolChoice($choiceDescription),
        ];

        if ($student['scelta_descrizione'] === '') {
            $pools[$classLabel]['unknown_choice_count']++;
        }

        if ($student['scelta_fuori_scuola']) {
            $pools[$classLabel]['excluded_outside_count']++;
            continue;
        }

        $pools[$classLabel]['included'][] = $student;
    }

    foreach ($pools as &$pool) {
        usort($pool['included'], function ($left, $right) {
            $leftKey = trim((string)($left['cognome'] ?? '')) . ' ' . trim((string)($left['nome'] ?? ''));
            $rightKey = trim((string)($right['cognome'] ?? '')) . ' ' . trim((string)($right['nome'] ?? ''));
            return strnatcasecmp($leftKey, $rightKey);
        });
    }
    unset($pool);

    return $pools;
}

function mastercomNoIrcLoadIrcLessons(string $weekStart, string $weekEnd): array
{
    global $__conMBApp;

    $weekStart = mastercomNoIrcNormalizeDate($weekStart);
    $weekEnd = mastercomNoIrcNormalizeDate($weekEnd);
    $fromEsc = mysqli_real_escape_string($__conMBApp, $weekStart);
    $toEsc = mysqli_real_escape_string($__conMBApp, $weekEnd);

    $rows = mb_dbGetAll("
        SELECT
            o.idCalendario,
            o.dataGiorno,
            o.ora,
            o.siglaMateria,
            m.nomeMateria,
            GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi
        FROM oralezione o
        INNER JOIN occupa oc
            ON oc.idCalendario = o.idCalendario
        LEFT JOIN materia m
            ON m.siglaMateria = o.siglaMateria
        WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
          AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
        GROUP BY o.idCalendario, o.dataGiorno, o.ora, o.siglaMateria, m.nomeMateria
        ORDER BY o.dataGiorno ASC, o.ora ASC, classi ASC
    ") ?: [];

    $lessons = [];
    foreach ($rows as $row) {
        if (!mastercomNoIrcIsIrcSubject($row['siglaMateria'] ?? '', $row['nomeMateria'] ?? '')) {
            continue;
        }

        $date = substr(trim((string)($row['dataGiorno'] ?? '')), 0, 10);
        $hour = mastercomNoIrcNormalizeHour($row['ora'] ?? '');
        if ($date === '' || $hour === '') {
            continue;
        }

        $classes = array_values(array_filter(array_map(function ($value) {
            return mastercomNoIrcNormalizeClassLabel($value);
        }, preg_split('/\s*,\s*/', trim((string)($row['classi'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [])));

        if (empty($classes)) {
            continue;
        }

        $lessons[] = [
            'date' => $date,
            'hour' => $hour,
            'sigla_materia' => trim((string)($row['siglaMateria'] ?? '')),
            'nome_materia' => trim((string)($row['nomeMateria'] ?? '')),
            'classi' => array_values(array_unique($classes)),
        ];
    }

    return $lessons;
}

function mastercomNoIrcLoadAssignments(string $weekStart, string $weekEnd): array
{
    if (!mastercomAdminTableExists('mastercom_noirc_docenti_assegnazioni')) {
        return [];
    }

    $weekStart = mastercomNoIrcNormalizeDate($weekStart);
    $weekEnd = mastercomNoIrcNormalizeDate($weekEnd);
    $extraColumns = mastercomNoIrcAssignmentExtraColumns();
    $groupSql = $extraColumns['gruppo_label'] ? "a.gruppo_label," : "NULL AS gruppo_label,";
    $classFilterSql = $extraColumns['classi_incluse'] ? "a.classi_incluse," : "NULL AS classi_incluse,";
    $capacitySql = $extraColumns['capienza_massima'] ? "a.capienza_massima," : "NULL AS capienza_massima,";

    $rows = dbGetAll("
        SELECT
            a.*,
            $groupSql
            $classFilterSql
            $capacitySql
            d.cognome,
            d.nome
        FROM mastercom_noirc_docenti_assegnazioni a
        LEFT JOIN docente d
            ON d.id = a.id_docente
        WHERE a.attivo = 1
          AND a.data_inizio <= " . dbQ($weekEnd) . "
          AND a.data_fine >= " . dbQ($weekStart) . "
        ORDER BY a.giorno_settimana ASC, a.ora ASC, d.cognome ASC, d.nome ASC
    ") ?: [];

    $map = [];
    foreach ($rows as $row) {
        $key = intval($row['giorno_settimana'] ?? 0) . '|' . mastercomNoIrcNormalizeHour($row['ora'] ?? '');
        if (!isset($map[$key])) {
            $map[$key] = [];
        }
        $teacherName = trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
        $groupLabel = trim((string)($row['gruppo_label'] ?? ''));
        if ($groupLabel === '') {
            $groupLabel = 'A';
        }
        $map[$key][] = [
            'id' => intval($row['id'] ?? 0),
            'group_label' => $groupLabel,
            'teacher_name' => $teacherName,
            'aula' => trim((string)($row['aula'] ?? '')),
            'note' => trim((string)($row['note'] ?? '')),
            'data_inizio' => trim((string)($row['data_inizio'] ?? '')),
            'data_fine' => trim((string)($row['data_fine'] ?? '')),
            'class_filters' => mastercomNoIrcParseClassFilter((string)($row['classi_incluse'] ?? '')),
            'class_filters_raw' => trim((string)($row['classi_incluse'] ?? '')),
            'capienza_massima' => intval($row['capienza_massima'] ?? 0),
        ];
    }

    return $map;
}

function mastercomNoIrcBuildWeekSlots(string $referenceDate): array
{
    $week = mastercomNoIrcWeekContext($referenceDate);
    $studentPools = mastercomNoIrcLoadStudentPoolsByClass();
    $assignmentsMap = mastercomNoIrcLoadAssignments($week['week_start'], $week['week_end']);
    $lessons = mastercomNoIrcLoadIrcLessons($week['week_start'], $week['week_end']);
    $slots = [];
    $hoursUsed = [];
    $distinctStudents = [];

    foreach ($lessons as $lesson) {
        if (!isset($week['days'][$lesson['date']])) {
            continue;
        }

        $slotKey = $lesson['date'] . '|' . $lesson['hour'];
        if (!isset($slots[$slotKey])) {
            $weekday = intval($week['days'][$lesson['date']]['weekday']);
            $assignmentKey = $weekday . '|' . $lesson['hour'];
            $slots[$slotKey] = [
                'date' => $lesson['date'],
                'hour' => $lesson['hour'],
                'weekday' => $weekday,
                'weekday_label' => $week['days'][$lesson['date']]['weekday_label'],
                'classi_irc' => [],
                'students' => [],
                'students_by_class' => [],
                'excluded_outside_count' => 0,
                'unknown_choice_count' => 0,
                'assignments' => $assignmentsMap[$assignmentKey] ?? [],
                'group_buckets' => [],
            ];
        }

        foreach ($lesson['classi'] as $classLabel) {
            $slots[$slotKey]['classi_irc'][$classLabel] = true;
            if (!isset($slots[$slotKey]['students_by_class'][$classLabel])) {
                $slots[$slotKey]['students_by_class'][$classLabel] = [];
            }

            $pool = $studentPools[$classLabel] ?? [
                'included' => [],
                'excluded_outside_count' => 0,
                'unknown_choice_count' => 0,
            ];

            $slots[$slotKey]['excluded_outside_count'] += intval($pool['excluded_outside_count'] ?? 0);
            $slots[$slotKey]['unknown_choice_count'] += intval($pool['unknown_choice_count'] ?? 0);

            foreach ($pool['included'] as $student) {
                $studentKey = intval($student['mastercom_id_studente'] ?? 0);
                if ($studentKey <= 0) {
                    continue;
                }
                $slots[$slotKey]['students'][$studentKey] = $student;
                $slots[$slotKey]['students_by_class'][$classLabel][$studentKey] = $student;
                $distinctStudents[$studentKey] = true;
            }
        }

        $hoursUsed[$lesson['hour']] = true;
    }

    foreach ($slots as &$slot) {
        $slot['classi_irc'] = array_values(array_keys($slot['classi_irc']));
        sort($slot['classi_irc']);

        $slot['students'] = array_values($slot['students']);
        usort($slot['students'], function ($left, $right) {
            $leftKey = ($left['classe'] ?? '') . '|' . ($left['cognome'] ?? '') . '|' . ($left['nome'] ?? '');
            $rightKey = ($right['classe'] ?? '') . '|' . ($right['cognome'] ?? '') . '|' . ($right['nome'] ?? '');
            return strnatcasecmp($leftKey, $rightKey);
        });

        foreach ($slot['students_by_class'] as $classLabel => $students) {
            $students = array_values($students);
            usort($students, function ($left, $right) {
                $leftKey = ($left['cognome'] ?? '') . '|' . ($left['nome'] ?? '');
                $rightKey = ($right['cognome'] ?? '') . '|' . ($right['nome'] ?? '');
                return strnatcasecmp($leftKey, $rightKey);
            });
            $slot['students_by_class'][$classLabel] = $students;
        }
        ksort($slot['students_by_class']);

        $groupBuckets = [];
        foreach ($slot['assignments'] as $assignment) {
            $assignmentId = intval($assignment['id'] ?? 0);
            $bucketKey = $assignmentId > 0 ? ('assignment:' . $assignmentId) : ('group:' . ($assignment['group_label'] ?? 'A'));
            $groupBuckets[$bucketKey] = [
                'type' => 'assignment',
                'group_label' => trim((string)($assignment['group_label'] ?? 'A')),
                'teacher_name' => trim((string)($assignment['teacher_name'] ?? '')),
                'aula' => trim((string)($assignment['aula'] ?? '')),
                'note' => trim((string)($assignment['note'] ?? '')),
                'class_filters' => array_values($assignment['class_filters'] ?? []),
                'class_filters_raw' => trim((string)($assignment['class_filters_raw'] ?? '')),
                'capienza_massima' => intval($assignment['capienza_massima'] ?? 0),
                'students' => [],
            ];
        }

        foreach ($slot['students_by_class'] as $classLabel => $students) {
            $matchedBucketKey = null;
            foreach ($slot['assignments'] as $assignment) {
                $assignmentId = intval($assignment['id'] ?? 0);
                $bucketKey = $assignmentId > 0 ? ('assignment:' . $assignmentId) : ('group:' . ($assignment['group_label'] ?? 'A'));
                $classFilters = array_values($assignment['class_filters'] ?? []);
                if (!empty($classFilters) && in_array($classLabel, $classFilters, true)) {
                    $matchedBucketKey = $bucketKey;
                    break;
                }
            }

            if ($matchedBucketKey === null && count($slot['assignments']) === 1) {
                $onlyAssignment = $slot['assignments'][0];
                $assignmentId = intval($onlyAssignment['id'] ?? 0);
                $matchedBucketKey = $assignmentId > 0 ? ('assignment:' . $assignmentId) : ('group:' . ($onlyAssignment['group_label'] ?? 'A'));
            }

            if ($matchedBucketKey === null) {
                if (!isset($groupBuckets['unassigned'])) {
                    $groupBuckets['unassigned'] = [
                        'type' => 'unassigned',
                        'group_label' => 'Da distribuire',
                        'teacher_name' => '',
                        'aula' => '',
                        'note' => '',
                        'class_filters' => [],
                        'class_filters_raw' => '',
                        'capienza_massima' => 0,
                        'students' => [],
                    ];
                }
                $matchedBucketKey = 'unassigned';
            }

            foreach ($students as $student) {
                $groupBuckets[$matchedBucketKey]['students'][] = $student;
            }
        }

        foreach ($groupBuckets as &$bucket) {
            usort($bucket['students'], function ($left, $right) {
                $leftKey = ($left['classe'] ?? '') . '|' . ($left['cognome'] ?? '') . '|' . ($left['nome'] ?? '');
                $rightKey = ($right['classe'] ?? '') . '|' . ($right['cognome'] ?? '') . '|' . ($right['nome'] ?? '');
                return strnatcasecmp($leftKey, $rightKey);
            });
        }
        unset($bucket);

        $slot['group_buckets'] = array_values($groupBuckets);
    }
    unset($slot);

    $hours = [];
    foreach (mastercomNoIrcOrari() as $hour) {
        if (isset($hoursUsed[$hour])) {
            $hours[] = $hour;
        }
    }

    uasort($slots, function ($left, $right) {
        if (($left['date'] ?? '') === ($right['date'] ?? '')) {
            return mastercomNoIrcSlotSortKey((string)($left['hour'] ?? '')) <=> mastercomNoIrcSlotSortKey((string)($right['hour'] ?? ''));
        }
        return strcmp((string)($left['date'] ?? ''), (string)($right['date'] ?? ''));
    });

    return [
        'week' => $week,
        'slots' => $slots,
        'hours' => $hours,
        'days' => $week['days'],
        'summary' => [
            'slots_count' => count($slots),
            'students_distinct_count' => count($distinctStudents),
        ],
    ];
}

function mastercomNoIrcLoadTeacherRows(): array
{
    return dbGetAll("
        SELECT id, cognome, nome
        FROM docente
        WHERE attivo = 1
        ORDER BY cognome ASC, nome ASC
    ") ?: [];
}

function mastercomNoIrcValidateAssignmentPayload(array $payload): array
{
    $teacherId = intval($payload['id_docente'] ?? 0);
    $weekday = intval($payload['giorno_settimana'] ?? 0);
    $hour = mastercomNoIrcNormalizeHour($payload['ora'] ?? '');
    $startDate = mastercomNoIrcNormalizeDate((string)($payload['data_inizio'] ?? ''));
    $endDate = mastercomNoIrcNormalizeDate((string)($payload['data_fine'] ?? ''));
    $aula = trim((string)($payload['aula'] ?? ''));
    $note = trim((string)($payload['note'] ?? ''));
    $groupLabel = strtoupper(trim((string)($payload['gruppo_label'] ?? 'A')));
    $classiIncluse = trim((string)($payload['classi_incluse'] ?? ''));
    $capienzaMassima = max(0, intval($payload['capienza_massima'] ?? 0));

    if ($teacherId <= 0) {
        return ['ok' => false, 'error' => 'Seleziona un docente'];
    }
    if ($weekday < 1 || $weekday > 6) {
        return ['ok' => false, 'error' => 'Seleziona un giorno valido'];
    }
    if ($hour === '') {
        return ['ok' => false, 'error' => 'Seleziona un orario valido'];
    }
    if ($endDate < $startDate) {
        return ['ok' => false, 'error' => 'La data fine non puo essere precedente alla data inizio'];
    }
    if ($groupLabel === '') {
        $groupLabel = 'A';
    }

    return [
        'ok' => true,
        'data' => [
            'id_docente' => $teacherId,
            'giorno_settimana' => $weekday,
            'ora' => $hour,
            'data_inizio' => $startDate,
            'data_fine' => $endDate,
            'aula' => $aula,
            'note' => $note,
            'gruppo_label' => $groupLabel,
            'classi_incluse' => $classiIncluse,
            'capienza_massima' => $capienzaMassima,
        ],
    ];
}

function mastercomNoIrcSaveAssignment(array $payload, int $assignmentId = 0): array
{
    if (!mastercomAdminTableExists('mastercom_noirc_docenti_assegnazioni')) {
        return ['ok' => false, 'error' => 'Tabella mastercom_noirc_docenti_assegnazioni mancante'];
    }

    $validation = mastercomNoIrcValidateAssignmentPayload($payload);
    if (!$validation['ok']) {
        return $validation;
    }

    $data = $validation['data'];
    $assignmentId = intval($assignmentId);
    $extraColumns = mastercomNoIrcAssignmentExtraColumns();

    if ($assignmentId > 0) {
        $extraSet = '';
        if ($extraColumns['gruppo_label']) {
            $extraSet .= ",
                gruppo_label = " . dbQ($data['gruppo_label']);
        }
        if ($extraColumns['classi_incluse']) {
            $extraSet .= ",
                classi_incluse = " . dbQ($data['classi_incluse']);
        }
        if ($extraColumns['capienza_massima']) {
            $extraSet .= ",
                capienza_massima = " . ($data['capienza_massima'] > 0 ? dbI($data['capienza_massima']) : 'NULL');
        }
        dbExec("
            UPDATE mastercom_noirc_docenti_assegnazioni
            SET id_docente = " . dbI($data['id_docente']) . ",
                giorno_settimana = " . dbI($data['giorno_settimana']) . ",
                ora = " . dbQ($data['ora']) . ",
                data_inizio = " . dbQ($data['data_inizio']) . ",
                data_fine = " . dbQ($data['data_fine']) . ",
                aula = " . dbQ($data['aula']) . ",
                note = " . dbQ($data['note']) . $extraSet . ",
                updated_at = NOW()
            WHERE id = " . $assignmentId . "
        ");
    } else {
        $columns = ['id_docente', 'giorno_settimana', 'ora', 'data_inizio', 'data_fine', 'aula', 'note', 'attivo', 'created_at', 'updated_at'];
        $values = [
            dbI($data['id_docente']),
            dbI($data['giorno_settimana']),
            dbQ($data['ora']),
            dbQ($data['data_inizio']),
            dbQ($data['data_fine']),
            dbQ($data['aula']),
            dbQ($data['note']),
            '1',
            'NOW()',
            'NOW()',
        ];
        if ($extraColumns['gruppo_label']) {
            $columns[] = 'gruppo_label';
            $values[] = dbQ($data['gruppo_label']);
        }
        if ($extraColumns['classi_incluse']) {
            $columns[] = 'classi_incluse';
            $values[] = dbQ($data['classi_incluse']);
        }
        if ($extraColumns['capienza_massima']) {
            $columns[] = 'capienza_massima';
            $values[] = $data['capienza_massima'] > 0 ? dbI($data['capienza_massima']) : 'NULL';
        }
        dbExec("
            INSERT INTO mastercom_noirc_docenti_assegnazioni
                (" . implode(', ', $columns) . ")
            VALUES
                (" . implode(', ', $values) . ")
        ");
        $assignmentId = intval(dblastId());
    }

    return ['ok' => true, 'id' => $assignmentId];
}

function mastercomNoIrcDeleteAssignment(int $assignmentId): array
{
    if (!mastercomAdminTableExists('mastercom_noirc_docenti_assegnazioni')) {
        return ['ok' => false, 'error' => 'Tabella mastercom_noirc_docenti_assegnazioni mancante'];
    }

    $assignmentId = intval($assignmentId);
    if ($assignmentId <= 0) {
        return ['ok' => false, 'error' => 'Assegnazione non valida'];
    }

    dbExec("DELETE FROM mastercom_noirc_docenti_assegnazioni WHERE id = " . $assignmentId);
    return ['ok' => true];
}

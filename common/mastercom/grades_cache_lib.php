<?php

require_once __DIR__ . '/admin_lib.php';

function mastercomGradesCacheRequiredTables(): array
{
    return [
        'mastercom_voti_materie',
        'mastercom_voti_medie',
        'mastercom_voti_dettaglio',
        'mastercom_voti_sync_log',
    ];
}

function mastercomGradesCacheMissingTables(): array
{
    return mastercomAdminMissingTables(mastercomGradesCacheRequiredTables());
}

function mastercomGradesCacheRomeToday(string $format = 'Y-m-d'): string
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format($format);
}

function mastercomGradesCacheSchoolYearRange(): array
{
    $year = trim((string)(mastercomAdminCurrentSchoolYear() ?? ''));
    if (preg_match('/^(\d{4})\s*\/\s*(\d{4})$/', $year, $matches)) {
        return [
            'start' => $matches[1] . '-09-01',
            'end' => $matches[2] . '-08-31',
        ];
    }

    $currentYear = intval(mastercomGradesCacheRomeToday('Y'));
    $currentMonth = intval(mastercomGradesCacheRomeToday('n'));
    $startYear = $currentMonth >= 9 ? $currentYear : ($currentYear - 1);

    return [
        'start' => $startYear . '-09-01',
        'end' => ($startYear + 1) . '-08-31',
    ];
}

function mastercomGradesCacheResolveRange(string $scope): array
{
    $scope = strtolower(trim($scope));
    $today = new DateTime('today', new DateTimeZone('Europe/Rome'));
    $schoolYear = mastercomGradesCacheSchoolYearRange();

    if (in_array($scope, ['today', 'oggi'], true)) {
        return ['start' => $today->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
    }

    if (in_array($scope, ['yesterday', 'ieri'], true)) {
        $start = clone $today;
        $start->modify('-1 day');
        return ['start' => $start->format('Y-m-d'), 'end' => $start->format('Y-m-d')];
    }

    if (in_array($scope, ['lastweek', '7gg', '7days', 'week'], true)) {
        $start = clone $today;
        $start->modify('-7 days');
        return ['start' => $start->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
    }

    if (in_array($scope, ['lastmonth', '30gg', '30days', 'month'], true)) {
        $start = clone $today;
        $start->modify('-30 days');
        return ['start' => $start->format('Y-m-d'), 'end' => $today->format('Y-m-d')];
    }

    if (in_array($scope, ['schoolyear', 'annoscolastico', 'all'], true)) {
        return ['start' => $schoolYear['start'], 'end' => min($schoolYear['end'], $today->format('Y-m-d'))];
    }

    return ['start' => $schoolYear['start'], 'end' => min($schoolYear['end'], $today->format('Y-m-d'))];
}

function mastercomGradesCacheDateTs(string $date, string $time): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', trim($date) . ' ' . $time, new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomGradesCacheFormatDateFromTs(int $timestamp): string
{
    if ($timestamp <= 0) {
        return '';
    }
    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format('Y-m-d');
}

function mastercomGradesCacheCleanText($value): string
{
    $text = html_entity_decode(trim((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

function mastercomGradesCacheSortSubjects(array $rows): array
{
    usort($rows, function ($a, $b) {
        $cmp = strcmp(mastercomGradesCacheCleanText($a['materia'] ?? ''), mastercomGradesCacheCleanText($b['materia'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        return intval($a['mastercom_id_materia'] ?? 0) <=> intval($b['mastercom_id_materia'] ?? 0);
    });
    return $rows;
}

function mastercomGradesCacheExtractClassSubjectsFromHtml(string $html): array
{
    if (trim($html) === '') {
        return [];
    }

    $subjects = [];
    if (!preg_match_all('/<select\b[^>]*name=["\']materia\[\]["\'][^>]*>(.*?)<\/select>/is', $html, $selectMatches)) {
        return [];
    }

    foreach ($selectMatches[1] as $selectHtml) {
        if (!preg_match_all('/<option\b([^>]*)>(.*?)<\/option>/is', $selectHtml, $optionMatches, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($optionMatches as $optionMatch) {
            $attributes = (string)($optionMatch[1] ?? '');
            if (stripos($attributes, 'selected') === false) {
                continue;
            }
            if (!preg_match('/\bvalue\s*=\s*(["\']?)(-?\d+)\1/i', $attributes, $valueMatch)) {
                continue;
            }
            $subjectId = intval($valueMatch[2] ?? 0);
            if ($subjectId <= 0) {
                continue;
            }
            $subjectName = mastercomGradesCacheCleanText(strip_tags((string)($optionMatch[2] ?? '')));
            if ($subjectName === '') {
                $subjectName = 'Materia ' . $subjectId;
            }
            $subjects[$subjectId] = [
                'mastercom_id_materia' => $subjectId,
                'materia' => $subjectName,
            ];
        }
    }

    return mastercomGradesCacheSortSubjects(array_values($subjects));
}

function mastercomGradesCacheLoadClassSubjectsFromAdmin(array $authResult, int $classId): array
{
    $response = mastercomRawRequest([
        'form_stato' => 'amministratore',
        'stato_principale' => 'impostazioni_principale',
        'stato_secondario' => 'gestione_classi_indirizzi_display',
        'id_classe' => $classId,
        'operazione' => '',
        'id_indirizzo' => '',
        'current_user' => mastercomCurrentUser($authResult),
        'current_key' => mastercomCurrentKey($authResult),
    ], [
        'base_url' => mastercomIndexUrl(),
        'cookie' => implode('; ', array_filter($authResult['cookies'] ?? [])),
        'method' => 'POST',
        'send_in_body' => true,
        'timeout' => 120,
    ]);

    if (empty($response['ok'])) {
        return [];
    }
    return mastercomGradesCacheExtractClassSubjectsFromHtml((string)($response['body'] ?? ''));
}

function mastercomGradesCacheSubjectRowsForClass(int $classId): array
{
    if ($classId <= 0 || !mastercomAdminTableExists('mastercom_voti_materie')) {
        return [];
    }
    return dbGetAll("
        SELECT mastercom_id_materia, materia
        FROM mastercom_voti_materie
        WHERE mastercom_id_classe = " . dbI($classId) . "
        ORDER BY materia ASC, mastercom_id_materia ASC
    ") ?: [];
}

function mastercomGradesCacheUpsertSubject(int $classId, int $subjectId, string $subjectName): void
{
    dbExec("
        INSERT INTO mastercom_voti_materie
            (mastercom_id_classe, mastercom_id_materia, materia, last_sync_at)
        VALUES
            (" . dbI($classId) . ", " . dbI($subjectId) . ", " . dbQNotNull($subjectName, 'Materia ' . $subjectId) . ", NOW())
        ON DUPLICATE KEY UPDATE
            materia = VALUES(materia),
            last_sync_at = NOW()
    ");
}

function mastercomGradesCacheUpsertAverage(int $classId, int $subjectId, int $studentId, string $startDate, string $endDate, array $avgRow): void
{
    dbExec("
        INSERT INTO mastercom_voti_medie
            (mastercom_id_classe, mastercom_id_materia, mastercom_id_studente, range_start, range_end, scritto, orale, pratico, totale, last_sync_at)
        VALUES
            (
                " . dbI($classId) . ",
                " . dbI($subjectId) . ",
                " . dbI($studentId) . ",
                " . dbQ($startDate) . ",
                " . dbQ($endDate) . ",
                " . dbQ((string)($avgRow['scritto'] ?? '')) . ",
                " . dbQ((string)($avgRow['orale'] ?? '')) . ",
                " . dbQ((string)($avgRow['pratico'] ?? '')) . ",
                " . dbQ((string)($avgRow['totale'] ?? '')) . ",
                NOW()
            )
        ON DUPLICATE KEY UPDATE
            scritto = VALUES(scritto),
            orale = VALUES(orale),
            pratico = VALUES(pratico),
            totale = VALUES(totale),
            last_sync_at = NOW()
    ");
}

function mastercomGradesCacheUpsertGrade(int $classId, int $subjectId, array $gradeRow): void
{
    $gradeId = intval($gradeRow['id_voto'] ?? 0);
    $studentId = intval($gradeRow['id_studente'] ?? 0);
    $dateTs = intval($gradeRow['data'] ?? 0);
    if ($gradeId <= 0 || $studentId <= 0 || $dateTs <= 0) {
        return;
    }

    dbExec("
        INSERT INTO mastercom_voti_dettaglio
            (id_voto, mastercom_id_classe, mastercom_id_materia, mastercom_id_studente, mastercom_id_professore, data_ts, data_giorno, tipo, tipo_aggiuntivo, voto, note, id_obiettivo, raw_json, last_sync_at)
        VALUES
            (
                " . dbI($gradeId) . ",
                " . dbI($classId) . ",
                " . dbI($subjectId) . ",
                " . dbI($studentId) . ",
                " . dbI($gradeRow['id_professore'] ?? null) . ",
                " . dbI($dateTs) . ",
                " . dbQ(mastercomGradesCacheFormatDateFromTs($dateTs)) . ",
                " . dbI($gradeRow['tipo'] ?? 0) . ",
                " . dbQ($gradeRow['tipo_aggiuntivo'] ?? null) . ",
                " . dbQ((string)($gradeRow['voto'] ?? '')) . ",
                " . dbQ(mastercomAdminCleanText($gradeRow['note'] ?? '') ?? '') . ",
                " . dbI($gradeRow['id_obiettivo'] ?? null) . ",
                " . dbQ(mastercomAdminJson($gradeRow)) . ",
                NOW()
            )
        ON DUPLICATE KEY UPDATE
            mastercom_id_classe = VALUES(mastercom_id_classe),
            mastercom_id_materia = VALUES(mastercom_id_materia),
            mastercom_id_studente = VALUES(mastercom_id_studente),
            mastercom_id_professore = VALUES(mastercom_id_professore),
            data_ts = VALUES(data_ts),
            data_giorno = VALUES(data_giorno),
            tipo = VALUES(tipo),
            tipo_aggiuntivo = VALUES(tipo_aggiuntivo),
            voto = VALUES(voto),
            note = VALUES(note),
            id_obiettivo = VALUES(id_obiettivo),
            raw_json = VALUES(raw_json),
            last_sync_at = NOW()
    ");
}

function mastercomGradesCacheFallbackSubjectsForClass(int $classId): array
{
    if ($classId <= 0 || !mastercomAdminTableExists('mastercom_docenti_classi_materie')) {
        return [];
    }
    return dbGetAll("
        SELECT DISTINCT mastercom_id_materia, materia
        FROM mastercom_docenti_classi_materie
        WHERE mastercom_id_classe = " . dbI($classId) . "
        ORDER BY materia ASC, mastercom_id_materia ASC
    ") ?: [];
}

function mastercomGradesCacheSync(array $options = [], ?callable $progress = null): array
{
    $missingTables = mastercomGradesCacheMissingTables();
    if (!empty($missingTables)) {
        return ['ok' => false, 'message' => 'Tabelle cache voti mancanti: ' . implode(', ', $missingTables), 'stats' => []];
    }

    $range = mastercomGradesCacheSchoolYearRange();
    $startDate = trim((string)($options['start_date'] ?? $range['start']));
    $endDate = trim((string)($options['end_date'] ?? min($range['end'], mastercomGradesCacheRomeToday('Y-m-d'))));
    $classIdFilter = intval($options['class_id'] ?? 0);
    $subjectIdFilter = intval($options['subject_id'] ?? 0);
    $startTs = mastercomGradesCacheDateTs($startDate, '00:00:00');
    $endTs = mastercomGradesCacheDateTs($endDate, '23:59:59');
    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return ['ok' => false, 'message' => 'Intervallo date non valido', 'stats' => []];
    }

    $logId = null;
    dbExec("
        INSERT INTO mastercom_voti_sync_log
            (started_at, mastercom_id_classe, mastercom_id_materia, range_start, range_end, stato)
        VALUES
            (NOW(), " . dbI($classIdFilter ?: null) . ", " . dbI($subjectIdFilter ?: null) . ", " . dbQ($startDate) . ", " . dbQ($endDate) . ", 'RUNNING')
    ");
    $logId = dblastId();

    $stats = [
        'classes' => 0,
        'subjects' => 0,
        'averages' => 0,
        'grades' => 0,
        'errors' => 0,
    ];
    $errors = [];

    $adminAuth = mastercomAuthenticateService(['profile' => 'MasterComAuth', 'method' => 'POST', 'timeout' => 60]);
    $gradesAuth = mastercomAuthenticateService(['profile' => 'MasterComDocenteAuth', 'method' => 'POST', 'timeout' => 60]);
    if (!$adminAuth['ok'] || !$gradesAuth['ok']) {
        $message = 'Autenticazione MasterCom fallita';
        dbExec("UPDATE mastercom_voti_sync_log SET finished_at = NOW(), stato = 'ERROR', message = " . dbQ($message) . " WHERE id = " . dbI($logId));
        return ['ok' => false, 'message' => $message, 'stats' => $stats];
    }

    $classes = [];
    if ($classIdFilter > 0) {
        $className = trim((string)(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . dbI($classIdFilter) . " LIMIT 1") ?? ''));
        $classes[] = ['mastercom_id_classe' => $classIdFilter, 'nome' => $className !== '' ? $className : (string)$classIdFilter];
    } else {
        $classes = mastercomAdminOperationalClassRows('mastercom_id_classe, nome') ?: [];
    }

    $totalClasses = count($classes);
    foreach ($classes as $classIndex => $classRow) {
        $classId = intval($classRow['mastercom_id_classe'] ?? 0);
        if ($classId <= 0) {
            continue;
        }
        $className = trim((string)($classRow['nome'] ?? $classId));
        $stats['classes']++;
        if ($progress) {
            $progress('grades', $classIndex + 1, $totalClasses, 'Classe ' . $className . ' - lettura materie');
        }

        $subjects = mastercomGradesCacheLoadClassSubjectsFromAdmin($adminAuth, $classId);
        if (empty($subjects)) {
            $subjects = mastercomGradesCacheFallbackSubjectsForClass($classId);
        }
        if ($subjectIdFilter > 0) {
            $subjects = array_values(array_filter($subjects, function ($row) use ($subjectIdFilter) {
                return intval($row['mastercom_id_materia'] ?? 0) === $subjectIdFilter;
            }));
        }

        foreach ($subjects as $subjectRow) {
            $subjectId = intval($subjectRow['mastercom_id_materia'] ?? 0);
            if ($subjectId <= 0) {
                continue;
            }
            $subjectName = mastercomGradesCacheCleanText($subjectRow['materia'] ?? ('Materia ' . $subjectId));
            mastercomGradesCacheUpsertSubject($classId, $subjectId, $subjectName);
            $stats['subjects']++;

            if ($progress) {
                $progress('grades', $classIndex + 1, $totalClasses, 'Classe ' . $className . ' - ' . $subjectName);
            }

            $avgResult = mastercomLoadGradesAvg($gradesAuth, $classId, $subjectId, $startTs, $endTs, ['method' => 'POST', 'timeout' => 120]);
            $gradesResult = mastercomLoadGradesData($gradesAuth, $classId, $subjectId, $startTs, $endTs, ['method' => 'POST', 'timeout' => 120]);
            if (!$avgResult['ok'] || !$gradesResult['ok']) {
                $stats['errors']++;
                $errors[] = $className . ' - ' . $subjectName;
                continue;
            }

            dbExec("
                DELETE FROM mastercom_voti_medie
                WHERE mastercom_id_classe = " . dbI($classId) . "
                  AND mastercom_id_materia = " . dbI($subjectId) . "
                  AND range_start = " . dbQ($startDate) . "
                  AND range_end = " . dbQ($endDate) . "
            ");
            $avgData = is_array($avgResult['response']['result'] ?? null) ? $avgResult['response']['result'] : [];
            foreach ($avgData as $studentId => $avgRow) {
                if (!is_array($avgRow)) {
                    continue;
                }
                mastercomGradesCacheUpsertAverage($classId, $subjectId, intval($studentId), $startDate, $endDate, $avgRow);
                $stats['averages']++;
            }

            dbExec("
                DELETE FROM mastercom_voti_dettaglio
                WHERE mastercom_id_classe = " . dbI($classId) . "
                  AND mastercom_id_materia = " . dbI($subjectId) . "
                  AND data_giorno BETWEEN " . dbQ($startDate) . " AND " . dbQ($endDate) . "
            ");
            $gradeData = is_array($gradesResult['response']['result'] ?? null) ? $gradesResult['response']['result'] : [];
            foreach ($gradeData as $gradeRow) {
                if (!is_array($gradeRow)) {
                    continue;
                }
                mastercomGradesCacheUpsertGrade($classId, $subjectId, $gradeRow);
                $stats['grades']++;
            }
        }
    }

    $ok = $stats['errors'] === 0;
    $message = $ok
        ? 'Sincronizzazione voti completata'
        : ('Sincronizzazione voti completata con errori: ' . implode('; ', array_slice($errors, 0, 20)));
    dbExec("
        UPDATE mastercom_voti_sync_log
        SET finished_at = NOW(),
            stato = " . dbQ($ok ? 'OK' : 'WARNING') . ",
            message = " . dbQ($message) . ",
            stats_json = " . dbQ(mastercomAdminJson($stats)) . "
        WHERE id = " . dbI($logId) . "
    ");

    return ['ok' => $ok, 'message' => $message, 'stats' => $stats, 'errors' => $errors];
}

function mastercomGradesCacheLastSyncLabel(?int $classId = null, ?int $subjectId = null): string
{
    if (!mastercomAdminTableExists('mastercom_voti_sync_log')) {
        return '';
    }
    $where = "WHERE stato IN ('OK', 'WARNING')";
    if ($classId !== null && $classId > 0) {
        $where .= " AND (mastercom_id_classe = " . dbI($classId) . " OR mastercom_id_classe IS NULL)";
    }
    if ($subjectId !== null && $subjectId > 0) {
        $where .= " AND (mastercom_id_materia = " . dbI($subjectId) . " OR mastercom_id_materia IS NULL)";
    }
    $row = dbGetFirst("
        SELECT finished_at, message
        FROM mastercom_voti_sync_log
        $where
        ORDER BY finished_at DESC, id DESC
        LIMIT 1
    ");
    if (!is_array($row) || trim((string)($row['finished_at'] ?? '')) === '') {
        return '';
    }
    return trim((string)$row['finished_at'] . ' - ' . (string)($row['message'] ?? ''));
}

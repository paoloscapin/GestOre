<?php

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/admin_lib.php';
require_once __DIR__ . '/noirc_lib.php';

function mastercomL2RequiredTables(): array
{
    return [
        'mastercom_l2_classi_mbapp',
        'mastercom_l2_gruppo_studenti',
        'mastercom_l2_appelli',
        'mastercom_l2_appello_studenti',
        'mastercom_studenti',
        'mastercom_classi',
    ];
}

function mastercomL2MissingTables(): array
{
    return mastercomAdminMissingTables(mastercomL2RequiredTables());
}

function mastercomL2NormDate(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        return date('Y-m-d');
    }
    $ts = strtotime($date);
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
}

function mastercomL2NormHour($hour): string
{
    $hour = trim((string)$hour);
    if ($hour === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2})[:.](\d{2})/', $hour, $m)) {
        return sprintf('%02d:%02d', intval($m[1]), intval($m[2]));
    }
    if (preg_match('/^(\d{1,2})(\d{2})$/', $hour, $m)) {
        return sprintf('%02d:%02d', intval($m[1]), intval($m[2]));
    }
    return $hour;
}

function mastercomL2WeekContext(string $referenceDate = ''): array
{
    $ref = mastercomL2NormDate($referenceDate);
    $dt = new DateTime($ref);
    $weekday = intval($dt->format('N'));
    $start = clone $dt;
    $start->modify('-' . max(0, $weekday - 1) . ' days');
    $end = clone $start;
    $end->modify('+4 days');

    return [
        'reference_date' => $ref,
        'week_start' => $start->format('Y-m-d'),
        'week_end' => $end->format('Y-m-d'),
    ];
}

function mastercomL2Orari(): array
{
    return ["07:50", "08:40", "09:30", "10:30", "11:20", "12:10", "13:00", "13:50", "14:40", "15:30", "16:20", "17:10", "18:00", "18:50", "19:40", "20:30", "21:30", "22:20"];
}

function mastercomL2WeekdayLabels(): array
{
    return [1 => 'Lunedi', 2 => 'Martedi', 3 => 'Mercoledi', 4 => 'Giovedi', 5 => 'Venerdi'];
}

function mastercomL2SlotEnd(string $hour): string
{
    $hours = mastercomL2Orari();
    $index = array_search(mastercomL2NormHour($hour), $hours, true);
    if ($index !== false && isset($hours[$index + 1])) {
        return $hours[$index + 1];
    }
    $ts = strtotime('2000-01-01 ' . mastercomL2NormHour($hour));
    return $ts ? date('H:i', $ts + 50 * 60) : '';
}

function mastercomL2WeekDays(string $weekStart): array
{
    $days = [];
    $start = new DateTime(mastercomL2NormDate($weekStart));
    $labels = mastercomL2WeekdayLabels();
    for ($i = 0; $i < 5; $i++) {
        $day = clone $start;
        $day->modify('+' . $i . ' days');
        $weekday = intval($day->format('N'));
        $days[] = [
            'date' => $day->format('Y-m-d'),
            'label' => $labels[$weekday] ?? $day->format('D'),
            'short' => $day->format('d/m'),
        ];
    }
    return $days;
}

function mastercomL2MbEsc($value): string
{
    mastercomL2EnsureMbappConnection();
    global $__conMBApp;
    return mysqli_real_escape_string($__conMBApp, (string)$value);
}

function mastercomL2EnsureMbappConnection(): bool
{
    if (function_exists('mb_dbGetAll')) {
        return true;
    }

    require_once __DIR__ . '/../connectMBApp.php';
    return function_exists('mb_dbGetAll');
}

function mastercomL2TableColumns(string $tableName): array
{
    if (!mastercomAdminTableExists($tableName)) {
        return [];
    }
    $rows = dbGetAll("SHOW COLUMNS FROM `$tableName`") ?: [];
    $columns = [];
    foreach ($rows as $row) {
        $field = trim((string)($row['Field'] ?? ''));
        if ($field !== '') {
            $columns[$field] = true;
        }
    }
    return $columns;
}

function mastercomL2Column(array $columns, array $candidates, string $fallback = ''): string
{
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) {
            return $candidate;
        }
    }
    return $fallback;
}

function mastercomL2SqlNowColumns(array $columns, array &$insertColumns, array &$insertValues): void
{
    if (isset($columns['created_at'])) {
        $insertColumns[] = 'created_at';
        $insertValues[] = 'NOW()';
    }
    if (isset($columns['updated_at'])) {
        $insertColumns[] = 'updated_at';
        $insertValues[] = 'NOW()';
    }
}

function mastercomL2SqlTouch(array $columns): string
{
    return isset($columns['updated_at']) ? ', updated_at = NOW()' : '';
}

function mastercomL2ClassDbColumn(array $columns): string
{
    return mastercomL2Column($columns, ['classe_mbapp', 'mbapp_classe_nome', 'classe', 'nome_classe', 'gruppo', 'nome_gruppo'], 'mbapp_classe_nome');
}

function mastercomL2GroupClassIdColumn(array $columns): string
{
    return mastercomL2Column($columns, ['id_l2_classe', 'id_l2_classe_mbapp'], 'id_l2_classe_mbapp');
}

function mastercomL2AppealClassIdColumn(array $columns): string
{
    return mastercomL2Column($columns, ['id_l2_classe', 'id_l2_classe_mbapp'], 'id_l2_classe_mbapp');
}

function mastercomL2AppealHourColumn(array $columns): string
{
    return mastercomL2Column($columns, ['ora', 'ora_inizio'], 'ora_inizio');
}

function mastercomL2AppealTeacherColumn(array $columns): string
{
    return mastercomL2Column($columns, ['id_docente', 'id_docente_gestore'], 'id_docente_gestore');
}

function mastercomL2DbHourValue(string $hour, string $columnName): string
{
    $hour = mastercomL2NormHour($hour);
    if ($columnName === 'ora_inizio' && preg_match('/^\d{2}:\d{2}$/', $hour)) {
        return $hour . ':00';
    }
    return $hour;
}

function mastercomL2DisplayDbHour($value): string
{
    return substr(mastercomL2NormHour((string)$value), 0, 5);
}

function mastercomL2Exec(string $query): array
{
    global $__con;

    dbDebug($query);
    if (!mysqli_query($__con, $query)) {
        $error = mysqli_error($__con);
        dbError('errore L2 query=' . $query . PHP_EOL . 'error message=' . $error);
        return [
            'ok' => false,
            'error' => $error,
            'query' => $query,
        ];
    }

    return ['ok' => true];
}

function mastercomL2LoadMbappClasses(): array
{
    if (!mastercomL2EnsureMbappConnection()) {
        return [];
    }

    $rows = mb_dbGetAll("
        SELECT DISTINCT oc.classe
        FROM occupa oc
        WHERE oc.classe IS NOT NULL
          AND TRIM(oc.classe) <> ''
        ORDER BY oc.classe ASC
    ") ?: [];

    return array_values(array_filter(array_map(function ($row) {
        return trim((string)($row['classe'] ?? ''));
    }, $rows)));
}

function mastercomL2LoadConfiguredClasses(bool $activeOnly = false): array
{
    if (!mastercomAdminTableExists('mastercom_l2_classi_mbapp')) {
        return [];
    }
    $columns = mastercomL2TableColumns('mastercom_l2_classi_mbapp');
    $classColumn = mastercomL2ClassDbColumn($columns);
    $activeColumn = mastercomL2Column($columns, ['attiva', 'attivo'], '');
    $where = $activeOnly && $activeColumn !== '' ? 'WHERE `' . $activeColumn . '` = 1' : '';
    return dbGetAll("
        SELECT *, `$classColumn` AS classe_mbapp
        FROM mastercom_l2_classi_mbapp
        $where
        ORDER BY `$classColumn` ASC
    ") ?: [];
}

function mastercomL2SaveClassSelection(array $post): array
{
    if (!mastercomAdminTableExists('mastercom_l2_classi_mbapp')) {
        return ['ok' => false, 'error' => 'Manca la tabella mastercom_l2_classi_mbapp'];
    }
    $columns = mastercomL2TableColumns('mastercom_l2_classi_mbapp');
    $classColumn = mastercomL2ClassDbColumn($columns);
    $activeColumn = mastercomL2Column($columns, ['attiva', 'attivo'], '');
    $descriptionColumn = mastercomL2Column($columns, ['descrizione', 'note'], '');

    $selected = [];
    foreach (($post['classi_l2'] ?? []) as $className) {
        $className = trim((string)$className);
        if ($className !== '') {
            $selected[$className] = true;
        }
    }

    if ($activeColumn !== '') {
        $exec = mastercomL2Exec("UPDATE mastercom_l2_classi_mbapp SET `$activeColumn` = 0");
        if (empty($exec['ok'])) {
            return ['ok' => false, 'error' => 'Errore aggiornamento classi L2: ' . $exec['error'], 'query' => $exec['query']];
        }
    }
    foreach (array_keys($selected) as $className) {
        $existingId = intval(dbGetValue("SELECT id FROM mastercom_l2_classi_mbapp WHERE `$classColumn` = " . dbQ($className) . " LIMIT 1") ?? 0);
        $touch = mastercomL2SqlTouch($columns);
        if ($existingId > 0) {
            if ($activeColumn !== '') {
                $exec = mastercomL2Exec("UPDATE mastercom_l2_classi_mbapp SET `$activeColumn` = 1 $touch WHERE id = " . dbI($existingId));
                if (empty($exec['ok'])) {
                    return ['ok' => false, 'error' => 'Errore riattivazione classe L2: ' . $exec['error'], 'query' => $exec['query']];
                }
            }
        } else {
            $insertColumns = ['`' . $classColumn . '`'];
            $insertValues = [dbQ($className)];
            if (isset($columns['nome_gruppo']) && $classColumn !== 'nome_gruppo') {
                $insertColumns[] = '`nome_gruppo`';
                $insertValues[] = dbQ($className);
            }
            if ($descriptionColumn !== '') {
                $insertColumns[] = '`' . $descriptionColumn . '`';
                $insertValues[] = dbQ($className);
            }
            if ($activeColumn !== '') {
                $insertColumns[] = '`' . $activeColumn . '`';
                $insertValues[] = '1';
            }
            mastercomL2SqlNowColumns($columns, $insertColumns, $insertValues);
            $exec = mastercomL2Exec("
                INSERT INTO mastercom_l2_classi_mbapp (" . implode(', ', $insertColumns) . ")
                VALUES (" . implode(', ', $insertValues) . ")
            ");
            if (empty($exec['ok'])) {
                return ['ok' => false, 'error' => 'Errore inserimento classe L2: ' . $exec['error'], 'query' => $exec['query']];
            }
        }
    }

    return ['ok' => true, 'count' => count($selected)];
}

function mastercomL2AddClass(string $className): array
{
    if (!mastercomAdminTableExists('mastercom_l2_classi_mbapp')) {
        return ['ok' => false, 'error' => 'Manca la tabella mastercom_l2_classi_mbapp'];
    }

    $className = trim($className);
    if ($className === '') {
        return ['ok' => false, 'error' => 'Seleziona una classe MBApp'];
    }

    $columns = mastercomL2TableColumns('mastercom_l2_classi_mbapp');
    $classColumn = mastercomL2ClassDbColumn($columns);
    $activeColumn = mastercomL2Column($columns, ['attiva', 'attivo'], '');
    $descriptionColumn = mastercomL2Column($columns, ['descrizione', 'note'], '');
    $existingId = intval(dbGetValue("SELECT id FROM mastercom_l2_classi_mbapp WHERE `$classColumn` = " . dbQ($className) . " LIMIT 1") ?? 0);

    if ($existingId > 0) {
        if ($activeColumn !== '') {
            $exec = mastercomL2Exec("UPDATE mastercom_l2_classi_mbapp SET `$activeColumn` = 1" . mastercomL2SqlTouch($columns) . " WHERE id = " . dbI($existingId));
            if (empty($exec['ok'])) {
                return ['ok' => false, 'error' => 'Errore riattivazione classe L2: ' . $exec['error']];
            }
        }
        return ['ok' => true, 'id' => $existingId, 'created' => false];
    }

    $insertColumns = ['`' . $classColumn . '`'];
    $insertValues = [dbQ($className)];
    if (isset($columns['nome_gruppo']) && $classColumn !== 'nome_gruppo') {
        $insertColumns[] = '`nome_gruppo`';
        $insertValues[] = dbQ($className);
    }
    if ($descriptionColumn !== '') {
        $insertColumns[] = '`' . $descriptionColumn . '`';
        $insertValues[] = dbQ($className);
    }
    if ($activeColumn !== '') {
        $insertColumns[] = '`' . $activeColumn . '`';
        $insertValues[] = '1';
    }
    mastercomL2SqlNowColumns($columns, $insertColumns, $insertValues);

    $exec = mastercomL2Exec("
        INSERT INTO mastercom_l2_classi_mbapp (" . implode(', ', $insertColumns) . ")
        VALUES (" . implode(', ', $insertValues) . ")
    ");
    if (empty($exec['ok'])) {
        return ['ok' => false, 'error' => 'Errore inserimento classe L2: ' . $exec['error']];
    }

    return ['ok' => true, 'id' => dblastId(), 'created' => true];
}

function mastercomL2DeactivateClass(int $l2ClassId): array
{
    if ($l2ClassId <= 0) {
        return ['ok' => false, 'error' => 'Classe L2 non valida'];
    }
    if (!mastercomAdminTableExists('mastercom_l2_classi_mbapp')) {
        return ['ok' => false, 'error' => 'Manca la tabella mastercom_l2_classi_mbapp'];
    }

    $columns = mastercomL2TableColumns('mastercom_l2_classi_mbapp');
    $activeColumn = mastercomL2Column($columns, ['attiva', 'attivo'], '');
    if ($activeColumn === '') {
        return ['ok' => false, 'error' => 'La tabella L2 non ha una colonna attivo/attiva'];
    }

    $exec = mastercomL2Exec("UPDATE mastercom_l2_classi_mbapp SET `$activeColumn` = 0" . mastercomL2SqlTouch($columns) . " WHERE id = " . dbI($l2ClassId));
    if (empty($exec['ok'])) {
        return ['ok' => false, 'error' => 'Errore rimozione classe L2: ' . $exec['error']];
    }

    return ['ok' => true];
}

function mastercomL2LoadStudentsForSelection(int $selectedClassId = 0): array
{
    if (!mastercomAdminTableExists('mastercom_studenti')) {
        return [];
    }
    $linkColumns = mastercomL2TableColumns('mastercom_l2_gruppo_studenti');
    $linkClassColumn = mastercomL2GroupClassIdColumn($linkColumns);
    $activeColumn = mastercomL2Column($linkColumns, ['attivo', 'attiva'], '');
    $noteColumn = mastercomL2Column($linkColumns, ['note', 'descrizione'], '');
    $activeSql = $activeColumn !== '' ? "COALESCE(l2s.`$activeColumn`, 0)" : 'CASE WHEN l2s.id IS NULL THEN 0 ELSE 1 END';
    $noteSql = $noteColumn !== '' ? "l2s.`$noteColumn`" : "NULL";

    $operationalClassIds = array_map(function ($row) {
        return intval($row['mastercom_id_classe'] ?? 0);
    }, mastercomAdminOperationalClassRows('mastercom_id_classe'));
    $operationalClassIds = array_values(array_filter($operationalClassIds));
    $where = !empty($operationalClassIds)
        ? 'WHERE s.mastercom_id_classe_corrente IN (' . implode(',', $operationalClassIds) . ')'
        : '';

    $assignedJoin = $selectedClassId > 0
        ? "LEFT JOIN mastercom_l2_gruppo_studenti l2s ON l2s.mastercom_id_studente = s.mastercom_id_studente AND l2s.`$linkClassColumn` = " . dbI($selectedClassId)
        : "LEFT JOIN mastercom_l2_gruppo_studenti l2s ON 1 = 0";

    return dbGetAll("
        SELECT
            s.mastercom_id_studente,
            s.cognome,
            s.nome,
            s.registro_numero,
            s.mastercom_id_classe_corrente,
            mc.nome AS classe_mastercom,
            $activeSql AS l2_attivo,
            $noteSql AS l2_note
        FROM mastercom_studenti s
        LEFT JOIN mastercom_classi mc
            ON mc.mastercom_id_classe = s.mastercom_id_classe_corrente
        $assignedJoin
        $where
        ORDER BY mc.nome ASC, s.cognome ASC, s.nome ASC
    ") ?: [];
}

function mastercomL2SaveStudents(int $l2ClassId, array $post): array
{
    if ($l2ClassId <= 0) {
        return ['ok' => false, 'error' => 'Seleziona una classe L2'];
    }
    if (!mastercomAdminTableExists('mastercom_l2_gruppo_studenti')) {
        return ['ok' => false, 'error' => 'Manca la tabella mastercom_l2_gruppo_studenti'];
    }
    $columns = mastercomL2TableColumns('mastercom_l2_gruppo_studenti');
    $classIdColumn = mastercomL2GroupClassIdColumn($columns);
    $activeColumn = mastercomL2Column($columns, ['attivo', 'attiva'], '');

    $selected = [];
    foreach (($post['studenti_l2'] ?? []) as $studentId) {
        $studentId = intval($studentId);
        if ($studentId > 0) {
            $selected[$studentId] = true;
        }
    }

    if ($activeColumn !== '') {
        dbExec("UPDATE mastercom_l2_gruppo_studenti SET `$activeColumn` = 0" . mastercomL2SqlTouch($columns) . " WHERE `$classIdColumn` = " . dbI($l2ClassId));
    } else {
        dbExec("DELETE FROM mastercom_l2_gruppo_studenti WHERE `$classIdColumn` = " . dbI($l2ClassId));
    }
    foreach (array_keys($selected) as $studentId) {
        $existingId = intval(dbGetValue("
            SELECT id
            FROM mastercom_l2_gruppo_studenti
            WHERE `$classIdColumn` = " . dbI($l2ClassId) . "
              AND mastercom_id_studente = " . dbI($studentId) . "
            LIMIT 1
        ") ?? 0);
        if ($existingId > 0) {
            if ($activeColumn !== '') {
                dbExec("UPDATE mastercom_l2_gruppo_studenti SET `$activeColumn` = 1" . mastercomL2SqlTouch($columns) . " WHERE id = " . dbI($existingId));
            }
        } else {
            $insertColumns = ['`' . $classIdColumn . '`', 'mastercom_id_studente'];
            $insertValues = [dbI($l2ClassId), dbI($studentId)];
            if (isset($columns['data_inizio'])) {
                $insertColumns[] = 'data_inizio';
                $insertValues[] = dbQ(date('Y-m-d'));
            }
            if ($activeColumn !== '') {
                $insertColumns[] = '`' . $activeColumn . '`';
                $insertValues[] = '1';
            }
            mastercomL2SqlNowColumns($columns, $insertColumns, $insertValues);
            dbExec("
                INSERT INTO mastercom_l2_gruppo_studenti (" . implode(', ', $insertColumns) . ")
                VALUES (" . implode(', ', $insertValues) . ")
            ");
        }
    }

    return ['ok' => true, 'count' => count($selected)];
}

function mastercomL2AddStudent(int $l2ClassId, int $studentId): array
{
    if ($l2ClassId <= 0 || $studentId <= 0) {
        return ['ok' => false, 'error' => 'Studente o gruppo L2 non valido'];
    }

    if (!mastercomAdminTableExists('mastercom_l2_gruppo_studenti')) {
        return ['ok' => false, 'error' => 'Manca la tabella mastercom_l2_gruppo_studenti'];
    }

    $columns = mastercomL2TableColumns('mastercom_l2_gruppo_studenti');
    $classIdColumn = mastercomL2GroupClassIdColumn($columns);
    $activeColumn = mastercomL2Column($columns, ['attivo', 'attiva'], '');
    $existingId = intval(dbGetValue("
        SELECT id
        FROM mastercom_l2_gruppo_studenti
        WHERE `$classIdColumn` = " . dbI($l2ClassId) . "
          AND mastercom_id_studente = " . dbI($studentId) . "
        LIMIT 1
    ") ?? 0);

    if ($existingId > 0) {
        if ($activeColumn !== '') {
            $exec = mastercomL2Exec("UPDATE mastercom_l2_gruppo_studenti SET `$activeColumn` = 1" . mastercomL2SqlTouch($columns) . " WHERE id = " . dbI($existingId));
            if (empty($exec['ok'])) {
                return ['ok' => false, 'error' => 'Errore aggiunta studente L2: ' . $exec['error']];
            }
        }
        return ['ok' => true];
    }

    $insertColumns = ['`' . $classIdColumn . '`', 'mastercom_id_studente'];
    $insertValues = [dbI($l2ClassId), dbI($studentId)];
    if (isset($columns['data_inizio'])) {
        $insertColumns[] = 'data_inizio';
        $insertValues[] = dbQ(date('Y-m-d'));
    }
    if ($activeColumn !== '') {
        $insertColumns[] = '`' . $activeColumn . '`';
        $insertValues[] = '1';
    }
    mastercomL2SqlNowColumns($columns, $insertColumns, $insertValues);

    $exec = mastercomL2Exec("
        INSERT INTO mastercom_l2_gruppo_studenti (" . implode(', ', $insertColumns) . ")
        VALUES (" . implode(', ', $insertValues) . ")
    ");
    if (empty($exec['ok'])) {
        return ['ok' => false, 'error' => 'Errore aggiunta studente L2: ' . $exec['error']];
    }

    return ['ok' => true];
}

function mastercomL2RemoveStudent(int $l2ClassId, int $studentId): array
{
    if ($l2ClassId <= 0 || $studentId <= 0) {
        return ['ok' => false, 'error' => 'Studente o gruppo L2 non valido'];
    }
    if (!mastercomAdminTableExists('mastercom_l2_gruppo_studenti')) {
        return ['ok' => false, 'error' => 'Manca la tabella mastercom_l2_gruppo_studenti'];
    }

    $columns = mastercomL2TableColumns('mastercom_l2_gruppo_studenti');
    $classIdColumn = mastercomL2GroupClassIdColumn($columns);
    $activeColumn = mastercomL2Column($columns, ['attivo', 'attiva'], '');

    if ($activeColumn !== '') {
        $query = "
            UPDATE mastercom_l2_gruppo_studenti
            SET `$activeColumn` = 0" . mastercomL2SqlTouch($columns) . "
            WHERE `$classIdColumn` = " . dbI($l2ClassId) . "
              AND mastercom_id_studente = " . dbI($studentId) . "
        ";
    } else {
        $query = "
            DELETE FROM mastercom_l2_gruppo_studenti
            WHERE `$classIdColumn` = " . dbI($l2ClassId) . "
              AND mastercom_id_studente = " . dbI($studentId) . "
        ";
    }

    $exec = mastercomL2Exec($query);
    if (empty($exec['ok'])) {
        return ['ok' => false, 'error' => 'Errore rimozione studente L2: ' . $exec['error']];
    }

    return ['ok' => true];
}

function mastercomL2TeacherName(array $row): string
{
    return trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
}

function mastercomL2TeacherLookup(int $docenteId): array
{
    if ($docenteId <= 0) {
        return ['id' => 0, 'username' => '', 'email' => '', 'name' => '', 'candidates' => []];
    }

    $row = dbGetFirst("SELECT id, cognome, nome, username, email FROM docente WHERE id = " . dbI($docenteId) . " LIMIT 1");
    if (!$row) {
        return ['id' => $docenteId, 'username' => '', 'email' => '', 'name' => '', 'candidates' => []];
    }

    $candidates = [];
    foreach ([$row['username'] ?? '', $row['email'] ?? ''] as $value) {
        $value = trim((string)$value);
        if ($value !== '') {
            $candidates[$value] = true;
        }
        if (strpos($value, '@') !== false) {
            $local = trim((string)strstr($value, '@', true));
            if ($local !== '') {
                $candidates[$local] = true;
            }
        }
    }

    if (mastercomAdminTableExists('mastercom_docenti')) {
        $mirrorRows = dbGetAll("SELECT raw_json FROM mastercom_docenti WHERE id_docente_gestore = " . dbI($docenteId)) ?: [];
        foreach ($mirrorRows as $mirrorRow) {
            $raw = json_decode((string)($mirrorRow['raw_json'] ?? ''), true);
            if (!is_array($raw)) {
                continue;
            }
            foreach (['username', 'userName', 'login', 'email', 'email1', 'mail'] as $key) {
                $value = trim((string)($raw[$key] ?? ''));
                if ($value !== '') {
                    $candidates[$value] = true;
                }
                if (strpos($value, '@') !== false) {
                    $local = trim((string)strstr($value, '@', true));
                    if ($local !== '') {
                        $candidates[$local] = true;
                    }
                }
            }
        }
    }

    return [
        'id' => intval($row['id']),
        'username' => trim((string)($row['username'] ?? '')),
        'email' => trim((string)($row['email'] ?? '')),
        'name' => trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''))),
        'candidates' => array_values(array_keys($candidates)),
    ];
}

function mastercomL2LoadLessons(string $from, string $to, int $docenteId = 0): array
{
    $classes = mastercomL2LoadConfiguredClasses(true);
    if (empty($classes)) {
        return [];
    }
    if (!mastercomL2EnsureMbappConnection()) {
        return [];
    }

    $classByName = [];
    foreach ($classes as $classRow) {
        $classByName[trim((string)$classRow['classe_mbapp'])] = $classRow;
    }

    $classSql = implode(',', array_map(function ($className) {
        return "'" . mastercomL2MbEsc($className) . "'";
    }, array_keys($classByName)));
    $fromEsc = mastercomL2MbEsc(mastercomL2NormDate($from));
    $toEsc = mastercomL2MbEsc(mastercomL2NormDate($to));
    $teacherFilter = '';
    if ($docenteId > 0) {
        $teacher = mastercomL2TeacherLookup($docenteId);
        $teacherCandidates = array_values(array_filter(array_map('trim', $teacher['candidates'] ?? [])));
        if (empty($teacherCandidates)) {
            return [];
        }
        $teacherSql = implode(',', array_map(function ($username) {
            return "'" . mastercomL2MbEsc($username) . "'";
        }, $teacherCandidates));
        $teacherFilter = "AND EXISTS (
            SELECT 1 FROM utilizza utf
            WHERE utf.idCalendario = o.idCalendario
              AND utf.username IN ($teacherSql)
        )";
    }

    $rows = mb_dbGetAll("
        SELECT
            o.idCalendario,
            o.dataGiorno,
            o.ora,
            o.siglaMateria,
            o.attivitaProgetto,
            m.nomeMateria,
            GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi,
            GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_nomi,
            GROUP_CONCAT(DISTINCT u.username ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_usernames,
            GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule
        FROM oralezione o
        INNER JOIN occupa oc
            ON oc.idCalendario = o.idCalendario
        LEFT JOIN utilizza ut
            ON ut.idCalendario = o.idCalendario
        LEFT JOIN utente u
            ON u.username = ut.username
        LEFT JOIN materia m
            ON m.siglaMateria = o.siglaMateria
        WHERE oc.classe IN ($classSql)
          AND o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
          AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
          $teacherFilter
        GROUP BY o.idCalendario, o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto, m.nomeMateria
        ORDER BY o.dataGiorno ASC, o.ora ASC, classi ASC
    ") ?: [];

    $lessons = [];
    foreach ($rows as $row) {
        $lessonClasses = array_map('trim', explode(',', (string)($row['classi'] ?? '')));
        $l2Class = null;
        foreach ($lessonClasses as $className) {
            if (isset($classByName[$className])) {
                $l2Class = $classByName[$className];
                break;
            }
        }
        if ($l2Class === null) {
            continue;
        }

        $lessons[] = [
            'id_l2_classe' => intval($l2Class['id']),
            'classe_mbapp' => (string)$l2Class['classe_mbapp'],
            'idCalendario' => intval($row['idCalendario'] ?? 0),
            'date' => substr((string)($row['dataGiorno'] ?? ''), 0, 10),
            'hour' => mastercomL2NormHour($row['ora'] ?? ''),
            'sigla_materia' => trim((string)($row['siglaMateria'] ?? '')),
            'nome_materia' => trim((string)($row['nomeMateria'] ?? '')),
            'aula' => trim((string)($row['aule'] ?? '')),
            'docenti' => trim((string)($row['docenti_nomi'] ?? '')),
            'docenti_usernames' => trim((string)($row['docenti_usernames'] ?? '')),
        ];
    }

    return $lessons;
}

function mastercomL2LessonSignature(array $lesson): string
{
    return implode('|', [
        intval($lesson['id_l2_classe'] ?? 0),
        trim((string)($lesson['classe_mbapp'] ?? '')),
        trim((string)($lesson['aula'] ?? '')),
        trim((string)($lesson['docenti_usernames'] ?? '')),
    ]);
}

function mastercomL2BuildLessonBlocks(array $lessons): array
{
    $bySlot = [];
    foreach ($lessons as $lesson) {
        $key = (string)($lesson['date'] ?? '') . '|' . (string)($lesson['hour'] ?? '');
        if ($key !== '|') {
            $bySlot[$key][] = $lesson;
        }
    }

    $blocks = [];
    $consumed = [];
    foreach (mastercomL2WeekDays($lessons[0]['date'] ?? date('Y-m-d')) as $unused) {
        unset($unused);
    }
    $hours = mastercomL2Orari();

    foreach ($lessons as $lesson) {
        $date = (string)($lesson['date'] ?? '');
        $hour = (string)($lesson['hour'] ?? '');
        $signature = mastercomL2LessonSignature($lesson);
        $consumeKey = $date . '|' . $hour . '|' . $signature;
        if (isset($consumed[$consumeKey])) {
            continue;
        }

        $startIndex = array_search($hour, $hours, true);
        $blockHours = [$hour];
        $lastHour = $hour;
        $consumed[$consumeKey] = true;

        if ($startIndex !== false) {
            for ($i = $startIndex + 1; $i < count($hours); $i++) {
                $nextHour = $hours[$i];
                $slotLessons = $bySlot[$date . '|' . $nextHour] ?? [];
                $matched = null;
                foreach ($slotLessons as $slotLesson) {
                    if (mastercomL2LessonSignature($slotLesson) === $signature) {
                        $matched = $slotLesson;
                        break;
                    }
                }
                if ($matched === null) {
                    break;
                }
                $blockHours[] = $nextHour;
                $lastHour = $nextHour;
                $consumed[$date . '|' . $nextHour . '|' . $signature] = true;
            }
        }

        $block = $lesson;
        $block['start_hour'] = $hour;
        $block['end_hour'] = mastercomL2SlotEnd($lastHour);
        $block['hours'] = $blockHours;
        $block['span'] = count($blockHours);
        $blocks[] = $block;
    }

    usort($blocks, function ($left, $right) {
        return strcmp(($left['date'] ?? '') . ' ' . ($left['start_hour'] ?? ''), ($right['date'] ?? '') . ' ' . ($right['start_hour'] ?? ''));
    });
    return $blocks;
}

function mastercomL2BlocksByDateHour(array $blocks): array
{
    $map = [];
    foreach ($blocks as $block) {
        $date = (string)($block['date'] ?? '');
        $hours = $block['hours'] ?? [];
        if (empty($hours)) {
            $hours = [(string)($block['start_hour'] ?? $block['hour'] ?? '')];
        }
        if ($date === '') {
            continue;
        }
        foreach ($hours as $hour) {
            $hour = (string)$hour;
            if ($hour === '') {
                continue;
            }
            $slotBlock = $block;
            $slotBlock['display_hour'] = $hour;
            $slotBlock['is_block_start'] = $hour === (string)($block['start_hour'] ?? '');
            $map[$date . '|' . $hour][] = $slotBlock;
        }
    }
    return $map;
}

function mastercomL2LoadStudentsForClass(int $l2ClassId): array
{
    if ($l2ClassId <= 0 || !mastercomAdminTableExists('mastercom_l2_gruppo_studenti')) {
        return [];
    }
    $columns = mastercomL2TableColumns('mastercom_l2_gruppo_studenti');
    $classIdColumn = mastercomL2GroupClassIdColumn($columns);
    $activeColumn = mastercomL2Column($columns, ['attivo', 'attiva'], '');
    $activeWhere = $activeColumn !== '' ? "AND l2s.`$activeColumn` = 1" : '';

    return dbGetAll("
        SELECT
            s.mastercom_id_studente,
            s.cognome,
            s.nome,
            s.registro_numero,
            s.foto,
            s.mastercom_id_classe_corrente,
            mc.nome AS classe_mastercom
        FROM mastercom_l2_gruppo_studenti l2s
        INNER JOIN mastercom_studenti s
            ON s.mastercom_id_studente = l2s.mastercom_id_studente
        LEFT JOIN mastercom_classi mc
            ON mc.mastercom_id_classe = s.mastercom_id_classe_corrente
        WHERE l2s.`$classIdColumn` = " . dbI($l2ClassId) . "
          $activeWhere
        ORDER BY s.cognome ASC, s.nome ASC
    ") ?: [];
}

function mastercomL2LoadPresenceMaps(array $students, string $date, array $hours): array
{
    $maps = [];
    foreach ($hours as $hour) {
        $hour = mastercomL2NormHour($hour);
        if ($hour === '') {
            continue;
        }
        $presence = mastercomNoIrcLoadPresenceMap($students, $date, $hour);
        $hourMap = is_array($presence['map'] ?? null) ? $presence['map'] : [];
        foreach ($hourMap as $studentId => $presenceRow) {
            if (is_array($presenceRow)) {
                $hourMap[$studentId] = mastercomL2NormalizePresenceRow($presenceRow);
            }
        }
        $maps[$hour] = $hourMap;
    }
    return $maps;
}

function mastercomL2NormalizePresenceRow(array $presenceRow): array
{
    $state = strtoupper(trim((string)($presenceRow['stato'] ?? '')));
    $detailNorm = mastercomAdminNorm($presenceRow['detail'] ?? '');

    if ($state === 'NON_VERIFICATO' && strpos($detailNorm, 'MASTERCOM NON INDICA LEZIONE CORRENTE') !== false) {
        $presenceRow['stato'] = 'PRESENTE';
        $presenceRow['label'] = 'Presente';
        $presenceRow['detail'] = 'Nessuna assenza, entrata, uscita o evento in questa ora';
    }

    return $presenceRow;
}

function mastercomL2PresenceToAppealState(array $presenceRow): string
{
    $presenceRow = mastercomL2NormalizePresenceRow($presenceRow);
    $state = strtoupper(trim((string)($presenceRow['stato'] ?? '')));
    if ($state === 'ASSENTE_MASTERCOM' || $state === 'EVENTO') {
        return 'ASSENTE';
    }
    if ($state === 'ENTRATA_RITARDO') {
        return 'RITARDO';
    }
    if ($state === 'USCITA') {
        return 'USCITA';
    }
    return 'PRESENTE';
}

function mastercomL2PresenceBadgeClass(array $presenceRow): string
{
    $presenceRow = mastercomL2NormalizePresenceRow($presenceRow);
    $state = strtoupper(trim((string)($presenceRow['stato'] ?? 'NON_VERIFICATO')));
    if ($state === 'ASSENTE_MASTERCOM' || $state === 'USCITA') {
        return 'danger';
    }
    if ($state === 'EVENTO') {
        return 'info';
    }
    if ($state === 'NON_VERIFICATO') {
        return 'default';
    }
    return 'success';
}

function mastercomL2PresenceSegmentLabel(string $startHour, string $endHour, int $segmentCount): string
{
    if ($segmentCount <= 1) {
        return '';
    }
    if ($startHour === $endHour) {
        return $startHour . ' ';
    }
    return $startHour . '-' . mastercomL2SlotEnd($endHour) . ' ';
}

function mastercomL2BuildPresenceSegments(array $presenceByHour, int $studentId, array $hours): array
{
    $segments = [];
    foreach ($hours as $hour) {
        $hour = mastercomL2NormHour($hour);
        if ($hour === '') {
            continue;
        }
        $presenceRow = is_array($presenceByHour[$hour][$studentId] ?? null)
            ? mastercomL2NormalizePresenceRow($presenceByHour[$hour][$studentId])
            : ['stato' => 'NON_VERIFICATO', 'label' => 'Da verificare', 'detail' => ''];
        $label = trim((string)($presenceRow['label'] ?? 'Da verificare'));
        $detail = trim((string)($presenceRow['detail'] ?? ''));
        $events = mastercomL2PresenceEventsText($presenceRow);
        $key = implode('|', [
            strtoupper(trim((string)($presenceRow['stato'] ?? 'NON_VERIFICATO'))),
            $label,
            $detail,
            $events,
        ]);

        $lastIndex = count($segments) - 1;
        if ($lastIndex >= 0 && $segments[$lastIndex]['key'] === $key) {
            $segments[$lastIndex]['end_hour'] = $hour;
            continue;
        }

        $segments[] = [
            'key' => $key,
            'start_hour' => $hour,
            'end_hour' => $hour,
            'row' => $presenceRow,
            'label' => $label,
            'detail' => $detail,
            'events' => $events,
        ];
    }
    return $segments;
}

function mastercomL2PresenceEventsText(array $presenceRow): string
{
    $appeal = is_array($presenceRow['appeal'] ?? null) ? $presenceRow['appeal'] : [];
    $events = is_array($appeal['eventi'] ?? null) ? $appeal['eventi'] : [];
    $labels = [];
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $title = trim((string)($event['titolo'] ?? $event['descrizione'] ?? 'Evento agenda'));
        if ($title !== '') {
            $labels[$title] = true;
        }
    }
    return implode(', ', array_keys($labels));
}

function mastercomL2PlanMastercomAction(array $student, array $presenceRow, string $l2State, string $date, string $hour, bool $withPayload = true): array
{
    $presenceRow = mastercomL2NormalizePresenceRow($presenceRow);
    $hour = mastercomNoIrcNormalizeHour($hour);
    $l2State = strtoupper(trim($l2State));
    $mcState = strtoupper(trim((string)($presenceRow['stato'] ?? 'NON_VERIFICATO')));
    $isAfternoon = mastercomNoIrcIsAfternoonHour($hour);
    $isFirstMorning = $hour === '07:50';
    $isFirstAfternoon = in_array($hour, ['13:00', '13:50'], true);
    $typeLabels = mastercomNoIrcAbsenceTypeLabels();

    if ($mcState === 'EVENTO') {
        return ['kind' => 'none', 'summary' => 'Nessuna azione: lo studente risulta coperto da un evento in agenda classe MasterCom.', 'payload' => null];
    }

    if ($l2State === 'PRESENTE') {
        if ($mcState === 'ASSENTE_MASTERCOM') {
            $absence = mastercomNoIrcFindRelevantAbsence($presenceRow);
            $absenceId = $absence !== null ? mastercomNoIrcAbsenceId($absence) : 0;
            $absenceDate = $absence !== null ? mastercomNoIrcAbsenceDateTs($absence) : 0;
            if ($absenceId <= 0 || $absenceDate <= 0) {
                return ['kind' => 'none', 'summary' => 'Azione non disponibile: non trovo id/data dell\'assenza MasterCom da modificare.', 'payload' => null];
            }
            $type = $isAfternoon ? 8 : 2;
            return [
                'kind' => 'edit',
                'summary' => 'Modifichera l\'assenza esistente in ' . $typeLabels[$type] . ' con orario ' . $hour . '.',
                'payload' => $withPayload ? mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'modifica_assenze_studente_update', [
                    'x' => '18',
                    'y' => '13',
                    'id_assenza' => $absenceId,
                    'data_assenza' => $absenceDate,
                ]) : null,
                'type_label' => $typeLabels[$type],
            ];
        }
        return ['kind' => 'none', 'summary' => 'Nessuna azione necessaria per MasterCom.', 'payload' => null];
    }

    if ($l2State === 'RITARDO') {
        if ($mcState === 'ENTRATA_RITARDO') {
            return ['kind' => 'none', 'summary' => 'Nessuna azione: entrata in ritardo gia registrata su MasterCom.', 'payload' => null];
        }
        $type = $isAfternoon ? 8 : 2;
        if ($mcState === 'ASSENTE_MASTERCOM') {
            $absence = mastercomNoIrcFindRelevantAbsence($presenceRow);
            $absenceId = $absence !== null ? mastercomNoIrcAbsenceId($absence) : 0;
            $absenceDate = $absence !== null ? mastercomNoIrcAbsenceDateTs($absence) : 0;
            if ($absenceId <= 0 || $absenceDate <= 0) {
                return ['kind' => 'none', 'summary' => 'Azione non disponibile: non trovo id/data dell\'assenza MasterCom da modificare.', 'payload' => null];
            }
            return [
                'kind' => 'edit',
                'summary' => 'Modifichera l\'assenza esistente in ' . $typeLabels[$type] . ' con orario ' . $hour . '.',
                'payload' => $withPayload ? mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'modifica_assenze_studente_update', [
                    'x' => '18',
                    'y' => '13',
                    'id_assenza' => $absenceId,
                    'data_assenza' => $absenceDate,
                ]) : null,
                'type_label' => $typeLabels[$type],
            ];
        }
        return [
            'kind' => 'create',
            'summary' => 'Inserira su MasterCom una ' . $typeLabels[$type] . ' non giustificata con orario ' . $hour . '.',
            'payload' => $withPayload ? mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'inserisci_assenze_studente_update') : null,
            'type_label' => $typeLabels[$type],
        ];
    }

    if ($l2State === 'USCITA') {
        if ($mcState === 'USCITA') {
            return ['kind' => 'none', 'summary' => 'Nessuna azione: uscita gia registrata su MasterCom.', 'payload' => null];
        }
        $type = $isAfternoon ? 9 : 3;
        return [
            'kind' => 'create',
            'summary' => 'Inserira su MasterCom una ' . $typeLabels[$type] . ' non giustificata con orario ' . $hour . '.',
            'payload' => $withPayload ? mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'inserisci_assenze_studente_update') : null,
            'type_label' => $typeLabels[$type],
        ];
    }

    if ($l2State === 'ASSENTE') {
        if ($isFirstMorning) {
            if ($mcState === 'ASSENTE_MASTERCOM') {
                return ['kind' => 'none', 'summary' => 'Nessuna azione: assenza gia presente su MasterCom.', 'payload' => null];
            }
            $type = 1;
            return [
                'kind' => 'create',
                'summary' => 'Inserira su MasterCom una Assenza Giornaliera per la prima ora del mattino.',
                'payload' => $withPayload ? mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'inserisci_assenze_studente_update') : null,
                'type_label' => $typeLabels[$type],
            ];
        }

        if ($isFirstAfternoon) {
            $dailyAbsence = mastercomNoIrcFindRelevantAbsence($presenceRow, [1]);
            if ($dailyAbsence !== null) {
                return ['kind' => 'none', 'summary' => 'Nessuna azione: lo studente risulta gia assente per l\'intera giornata.', 'payload' => null];
            }
            if ($mcState === 'ASSENTE_MASTERCOM') {
                return ['kind' => 'none', 'summary' => 'Nessuna azione: risulta gia una assenza su MasterCom.', 'payload' => null];
            }
            $type = 7;
            return [
                'kind' => 'create',
                'summary' => 'Inserira su MasterCom una Assenza solo al Pomeriggio.',
                'payload' => $withPayload ? mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'inserisci_assenze_studente_update') : null,
                'type_label' => $typeLabels[$type],
            ];
        }

        if ($mcState === 'ASSENTE_MASTERCOM') {
            return ['kind' => 'none', 'summary' => 'Nessuna azione: lo studente era gia assente su MasterCom e resta assente.', 'payload' => null];
        }

        $type = $isAfternoon ? 9 : 3;
        return [
            'kind' => 'create',
            'summary' => 'Inserira su MasterCom una ' . $typeLabels[$type] . ' non giustificata con orario ' . $hour . '.',
            'payload' => $withPayload ? mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'inserisci_assenze_studente_update') : null,
            'type_label' => $typeLabels[$type],
        ];
    }

    return ['kind' => 'none', 'summary' => 'Nessuna azione: stato L2 non operativo per MasterCom.', 'payload' => null];
}

function mastercomL2ExecuteMastercomAction(array $plan): array
{
    return mastercomNoIrcExecuteMastercomAction($plan);
}

function mastercomL2LoadAppeal(int $l2ClassId, string $date, string $hour): ?array
{
    if (!mastercomAdminTableExists('mastercom_l2_appelli')) {
        return null;
    }
    $columns = mastercomL2TableColumns('mastercom_l2_appelli');
    $classIdColumn = mastercomL2AppealClassIdColumn($columns);
    $hourColumn = mastercomL2AppealHourColumn($columns);
    $dbHour = mastercomL2DbHourValue($hour, $hourColumn);
    return dbGetFirst("
        SELECT *, `$classIdColumn` AS id_l2_classe, `$hourColumn` AS ora
        FROM mastercom_l2_appelli
        WHERE `$classIdColumn` = " . dbI($l2ClassId) . "
          AND data_giorno = " . dbQ(mastercomL2NormDate($date)) . "
          AND `$hourColumn` = " . dbQ($dbHour) . "
        LIMIT 1
    ");
}

function mastercomL2LoadAppealRows(int $appealId): array
{
    if ($appealId <= 0 || !mastercomAdminTableExists('mastercom_l2_appello_studenti')) {
        return [];
    }
    $columns = mastercomL2TableColumns('mastercom_l2_appello_studenti');
    $hourColumn = mastercomL2Column($columns, ['ora_inizio', 'ora'], '');
    $rows = dbGetAll("
        SELECT *
        FROM mastercom_l2_appello_studenti
        WHERE id_appello = " . dbI($appealId) . "
    ") ?: [];
    $map = [];
    foreach ($rows as $row) {
        $studentId = intval($row['mastercom_id_studente'] ?? 0);
        $hour = $hourColumn !== '' ? mastercomL2DisplayDbHour($row[$hourColumn] ?? '') : '';
        if ($hour !== '') {
            if (!isset($map[$studentId])) {
                $map[$studentId] = [];
            }
            $map[$studentId][$hour] = $row;
        } else {
            $map[$studentId] = $row;
        }
    }
    return $map;
}

function mastercomL2AppealStudentSchemaSupportsPerHour(): array
{
    if (!mastercomAdminTableExists('mastercom_l2_appello_studenti')) {
        return ['ok' => false, 'error' => 'Manca la tabella mastercom_l2_appello_studenti.'];
    }

    $columns = mastercomL2TableColumns('mastercom_l2_appello_studenti');
    $hourColumn = mastercomL2Column($columns, ['ora_inizio', 'ora'], '');
    if ($hourColumn === '') {
        return [
            'ok' => false,
            'error' => 'La tabella mastercom_l2_appello_studenti non ha ancora la colonna ora_inizio per salvare appelli su piu ore.',
        ];
    }

    $indexes = dbGetAll("SHOW INDEX FROM mastercom_l2_appello_studenti") ?: [];
    $uniqueColumnsByKey = [];
    foreach ($indexes as $index) {
        if (intval($index['Non_unique'] ?? 1) !== 0) {
            continue;
        }
        $keyName = (string)($index['Key_name'] ?? '');
        $columnName = (string)($index['Column_name'] ?? '');
        if ($keyName === '' || $keyName === 'PRIMARY' || $columnName === '') {
            continue;
        }
        $uniqueColumnsByKey[$keyName][] = $columnName;
    }

    foreach ($uniqueColumnsByKey as $keyColumns) {
        $hasAppeal = in_array('id_appello', $keyColumns, true);
        $hasStudent = in_array('mastercom_id_studente', $keyColumns, true);
        $hasHour = in_array($hourColumn, $keyColumns, true);
        if ($hasAppeal && $hasStudent && !$hasHour) {
            return [
                'ok' => false,
                'error' => 'Nel database e rimasto un vincolo unico su id_appello + mastercom_id_studente senza ora_inizio: cosi MySQL blocca il salvataggio di piu ore per lo stesso studente.',
            ];
        }
    }

    return ['ok' => true, 'error' => ''];
}

function mastercomL2LoadAppealSummary(int $l2ClassId, string $date, string $hour): array
{
    $appeal = mastercomL2LoadAppeal($l2ClassId, $date, $hour);
    if (!$appeal) {
        return [
            'done' => false,
            'presenti' => 0,
            'assenti' => 0,
            'ritardi' => 0,
            'uscite' => 0,
            'assenti_names' => [],
        ];
    }

    $appealId = intval($appeal['id'] ?? 0);
    if ($appealId <= 0 || !mastercomAdminTableExists('mastercom_l2_appello_studenti')) {
        return ['done' => true, 'presenti' => 0, 'assenti' => 0, 'ritardi' => 0, 'uscite' => 0, 'assenti_names' => []];
    }

    $rows = dbGetAll("
        SELECT
            aps.stato,
            s.cognome,
            s.nome
        FROM mastercom_l2_appello_studenti aps
        LEFT JOIN mastercom_studenti s
            ON s.mastercom_id_studente = aps.mastercom_id_studente
        WHERE aps.id_appello = " . dbI($appealId) . "
    ") ?: [];

    $summary = [
        'done' => true,
        'presenti' => 0,
        'assenti' => 0,
        'ritardi' => 0,
        'uscite' => 0,
        'assenti_names' => [],
    ];

    foreach ($rows as $row) {
        $state = strtoupper(trim((string)($row['stato'] ?? '')));
        if ($state === 'ASSENTE') {
            $summary['assenti']++;
            $summary['assenti_names'][] = trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
        } elseif ($state === 'USCITA') {
            $summary['uscite']++;
            $summary['assenti_names'][] = trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''))) . ' (uscita)';
        } elseif ($state === 'RITARDO') {
            $summary['ritardi']++;
        } elseif ($state === 'PRESENTE') {
            $summary['presenti']++;
        }
    }

    $summary['assenti_names'] = array_values(array_filter(array_unique($summary['assenti_names'])));
    return $summary;
}

function mastercomL2SaveAppeal(array $post, int $docenteId = 0): array
{
    $l2ClassId = intval($post['id_l2_classe'] ?? 0);
    $date = mastercomL2NormDate((string)($post['data_giorno'] ?? ''));
    $hour = mastercomL2NormHour($post['ora'] ?? '');
    if ($l2ClassId <= 0 || $date === '' || $hour === '') {
        return ['ok' => false, 'error' => 'Slot L2 non valido'];
    }
    $postedStates = is_array($post['stato'] ?? null) ? $post['stato'] : [];
    $maxHoursPerStudent = 0;
    foreach ($postedStates as $stateOrHours) {
        $hoursCount = is_array($stateOrHours) ? count($stateOrHours) : 1;
        if ($hoursCount > $maxHoursPerStudent) {
            $maxHoursPerStudent = $hoursCount;
        }
    }
    if ($maxHoursPerStudent > 1) {
        $schemaCheck = mastercomL2AppealStudentSchemaSupportsPerHour();
        if (empty($schemaCheck['ok'])) {
            return [
                'ok' => false,
                'error' => ($schemaCheck['error'] ?? 'Schema appello L2 non compatibile con appelli su piu ore.')
                    . ' Esegui la migrazione doc/mastercom_l2_appello_ore_migration.sql correggendo il nome del vecchio indice se diverso.',
            ];
        }
    }
    $appealColumns = mastercomL2TableColumns('mastercom_l2_appelli');
    $appealClassColumn = mastercomL2AppealClassIdColumn($appealColumns);
    $appealHourColumn = mastercomL2AppealHourColumn($appealColumns);
    $appealTeacherColumn = mastercomL2AppealTeacherColumn($appealColumns);
    $dbHour = mastercomL2DbHourValue($hour, $appealHourColumn);

    $note = trim((string)($post['note_appello'] ?? ''));
    $aula = trim((string)($post['aula'] ?? ''));
    $endHour = mastercomL2NormHour($post['ora_fine'] ?? '');
    $appeal = mastercomL2LoadAppeal($l2ClassId, $date, $hour);
    if ($appeal) {
        $appealId = intval($appeal['id']);
        $endSql = isset($appealColumns['ora_fine']) ? (", ora_fine = " . dbQ(mastercomL2DbHourValue($endHour, 'ora_inizio'))) : '';
        $exec = mastercomL2Exec("
            UPDATE mastercom_l2_appelli
            SET `$appealTeacherColumn` = " . dbI($docenteId > 0 ? $docenteId : ($appeal[$appealTeacherColumn] ?? null)) . ",
                aula = " . dbQ($aula) . ",
                note = " . dbQ($note) . "
                $endSql
                " . mastercomL2SqlTouch($appealColumns) . "
            WHERE id = " . dbI($appealId) . "
        ");
        if (empty($exec['ok'])) {
            return ['ok' => false, 'error' => 'Errore aggiornamento appello L2: ' . ($exec['error'] ?? '')];
        }
    } else {
        $insertColumns = ['`' . $appealClassColumn . '`', 'data_giorno', '`' . $appealHourColumn . '`', '`' . $appealTeacherColumn . '`', 'aula', 'note'];
        $insertValues = [dbI($l2ClassId), dbQ($date), dbQ($dbHour), dbI($docenteId > 0 ? $docenteId : null), dbQ($aula), dbQ($note)];
        if (isset($appealColumns['ora_fine'])) {
            $insertColumns[] = 'ora_fine';
            $insertValues[] = $endHour !== '' ? dbQ(mastercomL2DbHourValue($endHour, 'ora_inizio')) : 'NULL';
        }
        mastercomL2SqlNowColumns($appealColumns, $insertColumns, $insertValues);
        $exec = mastercomL2Exec("
            INSERT INTO mastercom_l2_appelli (" . implode(', ', $insertColumns) . ")
            VALUES (" . implode(', ', $insertValues) . ")
        ");
        if (empty($exec['ok'])) {
            return ['ok' => false, 'error' => 'Errore creazione appello L2: ' . ($exec['error'] ?? '')];
        }
        $appealId = dblastId();
    }

    $saved = 0;
    $allowed = ['PRESENTE', 'ASSENTE', 'RITARDO', 'USCITA'];
    $studentColumns = mastercomL2TableColumns('mastercom_l2_appello_studenti');
    $studentHourColumn = mastercomL2Column($studentColumns, ['ora_inizio', 'ora'], '');
    foreach (($post['stato'] ?? []) as $studentId => $stateOrHours) {
        $studentId = intval($studentId);
        if ($studentId <= 0) {
            continue;
        }

        $statesByHour = is_array($stateOrHours) ? $stateOrHours : ['' => $stateOrHours];
        foreach ($statesByHour as $hour => $state) {
            $hour = mastercomL2NormHour((string)$hour);
            $state = strtoupper(trim((string)$state));
            if (!in_array($state, $allowed, true)) {
                continue;
            }

            $studentNote = is_array($post['note_studente'] ?? null)
                ? trim((string)(is_array(($post['note_studente'][$studentId] ?? null))
                    ? (($post['note_studente'][$studentId][$hour] ?? '') ?: ($post['note_studente'][$studentId][''] ?? ''))
                    : ($post['note_studente'][$studentId] ?? '')))
                : '';

            $hourWhere = '';
            if ($studentHourColumn !== '') {
                $dbStudentHour = mastercomL2DbHourValue($hour !== '' ? $hour : $date, $studentHourColumn);
                $hourWhere = " AND `$studentHourColumn` = " . dbQ($dbStudentHour);
            }

            $rowId = intval(dbGetValue("
                SELECT id
                FROM mastercom_l2_appello_studenti
                WHERE id_appello = " . dbI($appealId) . "
                  AND mastercom_id_studente = " . dbI($studentId) . "
                  $hourWhere
                LIMIT 1
            ") ?? 0);
            if ($rowId > 0) {
                $exec = mastercomL2Exec("
                    UPDATE mastercom_l2_appello_studenti
                    SET stato = " . dbQ($state) . ",
                        note = " . dbQ($studentNote) . "
                        " . mastercomL2SqlTouch($studentColumns) . "
                    WHERE id = " . dbI($rowId) . "
                ");
                if (empty($exec['ok'])) {
                    return ['ok' => false, 'error' => 'Errore aggiornamento riga appello L2: ' . ($exec['error'] ?? '')];
                }
            } else {
                $insertColumns = ['id_appello', 'mastercom_id_studente', 'stato', 'note'];
                $insertValues = [dbI($appealId), dbI($studentId), dbQ($state), dbQ($studentNote)];
                if ($studentHourColumn !== '') {
                    $insertColumns[] = '`' . $studentHourColumn . '`';
                    $insertValues[] = dbQ(mastercomL2DbHourValue($hour, $studentHourColumn));
                }
                mastercomL2SqlNowColumns($studentColumns, $insertColumns, $insertValues);
                $exec = mastercomL2Exec("
                    INSERT INTO mastercom_l2_appello_studenti (" . implode(', ', $insertColumns) . ")
                    VALUES (" . implode(', ', $insertValues) . ")
                ");
                if (empty($exec['ok'])) {
                    return ['ok' => false, 'error' => 'Errore inserimento riga appello L2: ' . ($exec['error'] ?? '')];
                }
            }
            $saved++;
        }
    }

    return ['ok' => true, 'id_appello' => $appealId, 'saved' => $saved];
}

function mastercomL2LessonByRequest(int $l2ClassId, string $date, string $hour, int $docenteId = 0): ?array
{
    foreach (mastercomL2LoadLessons($date, $date, $docenteId) as $lesson) {
        if (intval($lesson['id_l2_classe'] ?? 0) === $l2ClassId && (string)$lesson['hour'] === mastercomL2NormHour($hour)) {
            return $lesson;
        }
    }
    return null;
}

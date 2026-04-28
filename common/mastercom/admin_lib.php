<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../__MasterCom.php';
require_once __DIR__ . '/../__Log.php';

function mastercomAdminTableExists(string $tableName): bool
{
    $tableName = trim($tableName);
    if ($tableName === '') {
        return false;
    }

    $value = dbGetValue("SHOW TABLES LIKE " . dbQ($tableName));
    return $value !== null;
}

function mastercomAdminRequiredTables(): array
{
    return [
        'mastercom_studenti',
        'mastercom_genitori',
        'mastercom_docenti',
        'mastercom_classi',
        'mastercom_studenti_classi',
        'mastercom_genitori_studenti',
    ];
}

function mastercomAdminMissingTables(array $tables = null): array
{
    $tables = $tables ?? mastercomAdminRequiredTables();
    $missing = [];
    foreach ($tables as $tableName) {
        if (!mastercomAdminTableExists($tableName)) {
            $missing[] = $tableName;
        }
    }
    return $missing;
}

function mastercomAdminNorm(?string $value): string
{
    $value = trim((string)$value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = mb_strtoupper($value, 'UTF-8');
    $value = preg_replace('/\s+/', ' ', $value);
    return trim((string)$value);
}

function mastercomAdminCleanText($value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace("\xc2\xa0", ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim((string)$value);

    return $value === '' ? null : $value;
}

function mastercomAdminNormCompact(?string $value): string
{
    return preg_replace('/[^A-Z0-9]/', '', mastercomAdminNorm($value));
}

function mastercomAdminJson($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function mastercomAdminFirstRecord($response): ?array
{
    if (!is_array($response)) {
        return null;
    }

    if (array_keys($response) === range(0, count($response) - 1)) {
        $first = $response[0] ?? null;
        return is_array($first) ? $first : null;
    }

    return $response;
}

function mastercomAdminParseClassName(string $name): array
{
    $name = trim($name);
    $parsed = [
        'classe_label' => null,
        'classe_numero' => null,
        'sezione' => null,
        'codice_indirizzo' => null,
    ];

    if ($name === '') {
        return $parsed;
    }

    $parts = preg_split('/\s+/', $name);
    if (!$parts) {
        return $parsed;
    }

    if (preg_match('/^(\d+)([A-Z]+)$/u', $parts[0], $matches)) {
        $parsed['classe_label'] = $parts[0];
        $parsed['classe_numero'] = intval($matches[1]);
        $parsed['sezione'] = $matches[2];
        if (isset($parts[1])) {
            $parsed['codice_indirizzo'] = $parts[1];
        }
        return $parsed;
    }

    if (isset($parts[0])) {
        $parsed['classe_label'] = $parts[0];
    }
    if (isset($parts[0]) && preg_match('/^(\d+)/', $parts[0], $matches)) {
        $parsed['classe_numero'] = intval($matches[1]);
    }
    if (isset($parts[1])) {
        $parsed['sezione'] = $parts[1];
    }
    if (isset($parts[2])) {
        $parsed['codice_indirizzo'] = $parts[2];
    }

    return $parsed;
}

function mastercomAdminFindLocalClassIdByName(string $className): ?int
{
    $className = trim($className);
    if ($className === '') {
        return null;
    }

    $value = dbGetValue("SELECT id FROM classi WHERE classe = " . dbQ($className) . " LIMIT 1");
    if ($value !== null) {
        return intval($value);
    }

    $parsed = mastercomAdminParseClassName($className);
    $classLabel = trim((string)($parsed['classe_label'] ?? ''));
    if ($classLabel === '') {
        return null;
    }

    $value = dbGetValue("SELECT id FROM classi WHERE classe = " . dbQ($classLabel) . " LIMIT 1");
    return $value !== null ? intval($value) : null;
}

function mastercomAdminFindLocalTeacher(array $masterTeacher): ?array
{
    $name = trim((string)($masterTeacher['name'] ?? $masterTeacher['nome_visualizzato'] ?? ''));
    if ($name === '') {
        return null;
    }

    $query = "
        SELECT *
        FROM docente
        WHERE UPPER(CONCAT(TRIM(cognome), ' ', TRIM(nome))) = " . dbQ(mastercomAdminNorm($name)) . "
        ORDER BY attivo DESC, id DESC
        LIMIT 1
    ";

    return dbGetFirst($query);
}

function mastercomAdminFindLocalStudent(array $masterStudent): ?array
{
    global $__anno_scolastico_corrente_id;

    $conditions = [];
    $cf = trim((string)($masterStudent['codice_fiscale'] ?? ''));
    $email = trim((string)($masterStudent['email1'] ?? ''));
    $cognome = trim((string)($masterStudent['cognome'] ?? ''));
    $nome = trim((string)($masterStudent['nome'] ?? ''));

    if ($cf !== '') {
        $conditions[] = "LOWER(s.codice_fiscale) = LOWER(" . dbQ($cf) . ")";
    }
    if ($email !== '') {
        $conditions[] = "LOWER(s.email) = LOWER(" . dbQ($email) . ")";
    }
    if ($cognome !== '' && $nome !== '') {
        $conditions[] = "(LOWER(s.cognome) = LOWER(" . dbQ($cognome) . ") AND LOWER(s.nome) = LOWER(" . dbQ($nome) . "))";
    }

    if (empty($conditions)) {
        return null;
    }

    $query = "
        SELECT
            s.*,
            sf.id_classe AS id_classe_corrente,
            c.classe AS classe_corrente
        FROM studente s
        LEFT JOIN studente_frequenta sf
            ON sf.id_studente = s.id
            AND sf.id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
        LEFT JOIN classi c
            ON c.id = sf.id_classe
        WHERE " . implode(' OR ', $conditions) . "
        ORDER BY s.attivo DESC, s.id DESC
        LIMIT 1
    ";

    return dbGetFirst($query);
}

function mastercomAdminFindLocalParent(array $masterParent): ?array
{
    $cf = trim((string)($masterParent['codice_fiscale'] ?? ''));
    $cognome = trim((string)($masterParent['cognome'] ?? ''));
    $nome = trim((string)($masterParent['nome'] ?? ''));

    if ($cf !== '') {
        $query = "
            SELECT *
            FROM genitori g
            WHERE LOWER(g.codice_fiscale) = LOWER(" . dbQ($cf) . ")
            ORDER BY g.attivo DESC, g.id DESC
            LIMIT 1
        ";

        return dbGetFirst($query);
    }

    if ($cognome !== '' && $nome !== '') {
        $query = "
            SELECT *
            FROM genitori g
            WHERE LOWER(g.cognome) = LOWER(" . dbQ($cognome) . ")
              AND LOWER(g.nome) = LOWER(" . dbQ($nome) . ")
            ORDER BY g.attivo DESC, g.id DESC
            LIMIT 1
        ";

        return dbGetFirst($query);
    }

    return null;
}

function mastercomAdminResolveLocalParent(array $mirrorRow): ?array
{
    $linked = null;
    if (!empty($mirrorRow['id_genitore_gestore'])) {
        $linked = dbGetFirst("SELECT * FROM genitori WHERE id = " . intval($mirrorRow['id_genitore_gestore']) . " LIMIT 1");
    }

    $matched = mastercomAdminFindLocalParent($mirrorRow);
    $mirrorCf = mastercomAdminNormCompact($mirrorRow['codice_fiscale'] ?? '');

    if ($mirrorCf !== '') {
        if ($matched != null) {
            return $matched;
        }

        if ($linked != null && mastercomAdminNormCompact($linked['codice_fiscale'] ?? '') === $mirrorCf) {
            return $linked;
        }

        return null;
    }

    if ($matched != null) {
        return $matched;
    }

    return $linked;
}

function mastercomAdminUpsertByField(string $tableName, string $keyField, $keyValue, array $data): int
{
    $keyValueSql = is_numeric($keyValue) ? dbI($keyValue) : dbQ($keyValue);
    $existingId = dbGetValue("SELECT id FROM `$tableName` WHERE `$keyField` = $keyValueSql LIMIT 1");

    $assignments = [];
    foreach ($data as $field => $value) {
        $assignments[] = "`$field` = " . mastercomAdminSqlValue($value);
    }

    if ($existingId !== null) {
        $query = "UPDATE `$tableName` SET " . implode(",\n", $assignments) . " WHERE id = " . intval($existingId);
        dbExec($query);
        return intval($existingId);
    }

    $fields = [];
    $values = [];
    foreach ($data as $field => $value) {
        $fields[] = "`$field`";
        $values[] = mastercomAdminSqlValue($value);
    }

    $query = "INSERT INTO `$tableName` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
    dbExec($query);
    return intval(dblastId());
}

function mastercomAdminSqlValue($value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return dbQ((string)$value);
}

function mastercomAdminNow(): string
{
    return date('Y-m-d H:i:s');
}

function mastercomAdminCurrentSchoolYear(): ?string
{
    global $__anno_scolastico_corrente_anno;

    $year = trim((string)($__anno_scolastico_corrente_anno ?? ''));
    if ($year !== '') {
        return $year;
    }

    $row = dbGetFirst("SELECT anno FROM anno_scolastico_corrente LIMIT 1");
    $year = trim((string)($row['anno'] ?? ''));

    return $year !== '' ? $year : null;
}

function mastercomAdminRootPath(): string
{
    return dirname(__DIR__, 2);
}

function mastercomAdminSyncCacheDir(): string
{
    return mastercomAdminRootPath() . DIRECTORY_SEPARATOR . 'log';
}

function mastercomAdminParentsSyncFile(string $token): string
{
    return mastercomAdminSyncCacheDir() . DIRECTORY_SEPARATOR . 'mastercom_sync_parents_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $token) . '.json';
}

function mastercomAdminStudentsSyncFile(string $token): string
{
    return mastercomAdminSyncCacheDir() . DIRECTORY_SEPARATOR . 'mastercom_sync_students_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $token) . '.json';
}

function mastercomAdminStudentsAllSyncFile(string $token): string
{
    return mastercomAdminSyncCacheDir() . DIRECTORY_SEPARATOR . 'mastercom_sync_students_all_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $token) . '.json';
}

function mastercomAdminProgress(callable $progress = null, string $stage = '', int $current = 0, int $total = 0, string $message = ''): void
{
    if ($progress !== null) {
        $progress($stage, $current, $total, $message);
    }
}

function mastercomAdminExec(string $query, string $context = ''): void
{
    global $__con;

    debug($query);
    if (!mysqli_query($__con, $query)) {
        $message = 'MasterCom admin SQL error';
        if ($context !== '') {
            $message .= ' [' . $context . ']';
        }
        $message .= ': ' . mysqli_error($__con) . ' | query=' . $query;
        error($message);
        throw new RuntimeException($message);
    }
}

function mastercomAdminLoadParentsList(): array
{
    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$authResult['ok']) {
        return ['ok' => false, 'message' => 'Autenticazione admin MasterCom fallita'];
    }

    $parentsResult = mastercomLoadParents($authResult, [
        'method' => 'POST',
        'timeout' => 120,
    ]);
    if (!$parentsResult['ok']) {
        return ['ok' => false, 'message' => 'Caricamento genitori MasterCom fallito'];
    }

    return [
        'ok' => true,
        'records' => is_array($parentsResult['response'] ?? null) ? $parentsResult['response'] : [],
    ];
}

function mastercomAdminLoadStudentsListForClass(int $classId): array
{
    if ($classId <= 0) {
        return ['ok' => false, 'message' => 'class_id non valido'];
    }

    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComDocenteAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$authResult['ok']) {
        return ['ok' => false, 'message' => 'Autenticazione docente fallita'];
    }

    $studentsResult = mastercomLoadStudentsList($authResult, $classId, [
        'method' => 'POST',
        'timeout' => 120,
    ]);
    if (!$studentsResult['ok']) {
        return ['ok' => false, 'message' => 'Caricamento studenti MasterCom fallito'];
    }

    return [
        'ok' => true,
        'records' => is_array($studentsResult['response']['result'] ?? null) ? $studentsResult['response']['result'] : [],
    ];
}

function mastercomAdminResolveLocalClass(array $mirrorRow): ?array
{
    if (!empty($mirrorRow['id_classe_gestore'])) {
        $local = dbGetFirst("SELECT * FROM classi WHERE id = " . intval($mirrorRow['id_classe_gestore']) . " LIMIT 1");
        if ($local != null) {
            return $local;
        }
    }

    $className = trim((string)($mirrorRow['nome'] ?? ''));
    if ($className === '') {
        return null;
    }

    $localClassId = mastercomAdminFindLocalClassIdByName($className);
    if ($localClassId <= 0) {
        return null;
    }

    return dbGetFirst("SELECT * FROM classi WHERE id = " . intval($localClassId) . " LIMIT 1");
}

function mastercomAdminSyncTeachers(callable $progress = null): array
{
    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComDocenteAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$authResult['ok']) {
        return ['ok' => false, 'message' => 'Autenticazione docente fallita'];
    }

    $usersResult = mastercomLoadUsersList($authResult, [
        'method' => 'POST',
        'timeout' => 120,
    ]);
    if (!$usersResult['ok']) {
        return ['ok' => false, 'message' => 'Caricamento utenti MasterCom fallito'];
    }

    $teachers = mastercomExtractTeacherUsers($usersResult);
    $total = count($teachers);
    $updated = 0;
    foreach ($teachers as $teacher) {
        mastercomAdminProgress($progress, 'teachers', $updated + 1, $total, 'Sincronizzazione docente ' . ($teacher['name'] ?? ''));
        $localTeacher = mastercomAdminFindLocalTeacher($teacher);
        mastercomAdminUpsertByField('mastercom_docenti', 'mastercom_id_user', intval($teacher['id_user']), [
            'id_docente_gestore' => $localTeacher['id'] ?? null,
            'mastercom_id_user' => intval($teacher['id_user']),
            'nome_visualizzato' => mastercomAdminCleanText($teacher['name'] ?? ''),
            'tipo_utente' => mastercomAdminCleanText($teacher['type'] ?? ''),
            'attivo_mastercom' => 1,
            'last_sync_at' => mastercomAdminNow(),
            'last_seen_at' => mastercomAdminNow(),
            'raw_json' => mastercomAdminJson($teacher),
        ]);
        $updated++;
    }

    return ['ok' => true, 'message' => "Docenti sincronizzati: $updated"];
}

function mastercomAdminSyncClasses(callable $progress = null): array
{
    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComDocenteAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$authResult['ok']) {
        return ['ok' => false, 'message' => 'Autenticazione docente fallita'];
    }

    $userInfoResult = mastercomLoadCurrentUserInfo($authResult, [
        'method' => 'POST',
        'timeout' => 120,
    ]);
    if (!$userInfoResult['ok']) {
        return ['ok' => false, 'message' => 'Caricamento classi MasterCom fallito'];
    }

    $classes = mastercomExtractClasses($userInfoResult);
    $year = mastercomAdminCurrentSchoolYear()
        ?? mastercomAdminCleanText($userInfoResult['response']['result']['anno_scolastico'] ?? null);
    $total = count($classes);
    $updated = 0;
    foreach ($classes as $class) {
        if (!is_array($class)) {
            continue;
        }
        mastercomAdminProgress($progress, 'classes', $updated + 1, $total, 'Sincronizzazione classe ' . (($class['nome'] ?? '')));

        $className = trim((string)($class['nome'] ?? ''));
        $parsed = mastercomAdminParseClassName($className);
        $localClassId = mastercomAdminFindLocalClassIdByName($className);

        mastercomAdminUpsertByField('mastercom_classi', 'mastercom_id_classe', intval($class['valore']), [
            'id_classe_gestore' => $localClassId,
            'mastercom_id_classe' => intval($class['valore']),
            'nome' => mastercomAdminCleanText($className),
            'classe_numero' => $class['classe'] ?? $parsed['classe_numero'],
            'sezione' => mastercomAdminCleanText($parsed['sezione']),
            'codice_indirizzo' => mastercomAdminCleanText($parsed['codice_indirizzo']),
            'anno_scolastico' => mastercomAdminCleanText($year),
            'attiva_mastercom' => 1,
            'last_sync_at' => mastercomAdminNow(),
            'last_seen_at' => mastercomAdminNow(),
            'raw_json' => mastercomAdminJson($class),
        ]);
        $updated++;
    }

    return ['ok' => true, 'message' => "Classi sincronizzate: $updated"];
}

function mastercomAdminSyncStudentsForClass(int $classId, callable $progress = null): array
{
    $loadResult = mastercomAdminLoadStudentsListForClass($classId);
    if (!$loadResult['ok']) {
        return $loadResult;
    }

    return mastercomAdminSyncStudentsChunk($classId, $loadResult['records'], 0, count($loadResult['records']), $progress);
}

function mastercomAdminSyncStudentsChunk(int $classId, array $masterStudents, int $baseOffset = 0, int $overallTotal = 0, callable $progress = null): array
{
    if ($classId <= 0) {
        return ['ok' => false, 'message' => 'class_id non valido'];
    }

    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComDocenteAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$authResult['ok']) {
        return ['ok' => false, 'message' => 'Autenticazione docente fallita'];
    }

    $classRow = dbGetFirst("SELECT * FROM mastercom_classi WHERE mastercom_id_classe = " . intval($classId) . " LIMIT 1");
    $classLabel = $classRow['nome'] ?? ('classe ' . $classId);
    $total = $overallTotal > 0 ? $overallTotal : count($masterStudents);
    $updated = 0;

    foreach ($masterStudents as $index => $masterStudent) {
        $studentId = intval($masterStudent['id_studente'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }
        $current = $baseOffset + $index + 1;
        mastercomAdminProgress($progress, 'students_class', $current, $total, 'Classe ' . $classLabel . ' - ' . (($masterStudent['cognome'] ?? '') . ' ' . ($masterStudent['nome'] ?? '')));

        $detailResult = mastercomLoadStudentDetails($authResult, $studentId, [
            'method' => 'GET',
            'timeout' => 120,
        ]);
        $detail = mastercomAdminFirstRecord($detailResult['response'] ?? null) ?? [];
        $merged = array_merge($detail, $masterStudent);
        $localStudent = mastercomAdminFindLocalStudent([
            'codice_fiscale' => $detail['cf'] ?? '',
            'email1' => $masterStudent['email1'] ?? $detail['email'] ?? '',
            'cognome' => $masterStudent['cognome'] ?? $detail['surname'] ?? '',
            'nome' => $masterStudent['nome'] ?? $detail['first_name'] ?? '',
        ]);

        mastercomAdminUpsertByField('mastercom_studenti', 'mastercom_id_studente', $studentId, [
            'id_studente_gestore' => $localStudent['id'] ?? null,
            'mastercom_id_studente' => $studentId,
            'mastercom_id_classe_corrente' => $classId,
            'registro_numero' => isset($masterStudent['registro']) ? intval($masterStudent['registro']) : null,
            'cognome' => mastercomAdminCleanText($masterStudent['cognome'] ?? $detail['surname'] ?? null),
            'nome' => mastercomAdminCleanText($masterStudent['nome'] ?? $detail['first_name'] ?? null),
            'codice_fiscale' => mastercomAdminCleanText($detail['cf'] ?? null),
            'data_nascita_ts' => isset($masterStudent['data_nascita']) ? intval($masterStudent['data_nascita']) : null,
            'data_nascita' => empty($masterStudent['data_nascita']) ? null : date('Y-m-d', intval($masterStudent['data_nascita'])),
            'email1' => mastercomAdminCleanText($masterStudent['email1'] ?? $detail['email'] ?? null),
            'email2' => mastercomAdminCleanText($masterStudent['email2'] ?? null),
            'foto' => mastercomAdminCleanText($masterStudent['foto'] ?? null),
            'classe_numero' => isset($masterStudent['classe']) ? intval($masterStudent['classe']) : null,
            'sezione' => mastercomAdminCleanText($masterStudent['sezione'] ?? null),
            'codice_indirizzo' => mastercomAdminCleanText($masterStudent['codice_indirizzi'] ?? null),
            'descrizione_indirizzo' => mastercomAdminCleanText($masterStudent['descrizione_indirizzi'] ?? null),
            'tipo_indirizzo' => isset($masterStudent['tipo_indirizzo']) ? intval($masterStudent['tipo_indirizzo']) : null,
            'ordinamento' => isset($masterStudent['ordinamento']) ? intval($masterStudent['ordinamento']) : null,
            'esonero_religione' => isset($masterStudent['esonero_religione']) ? intval($masterStudent['esonero_religione']) : null,
            'esonero_ed_fisica' => isset($masterStudent['esonero_ed_fisica']) ? intval($masterStudent['esonero_ed_fisica']) : null,
            'servizio_mensa' => isset($masterStudent['servizio_mensa']) ? intval($masterStudent['servizio_mensa']) : null,
            'necessita_sostegno' => isset($masterStudent['necessita_sostegno']) ? intval($masterStudent['necessita_sostegno']) : null,
            'esito' => mastercomAdminCleanText($masterStudent['esito'] ?? null),
            'esito_corrente_calcolato' => mastercomAdminCleanText($masterStudent['esito_corrente_calcolato'] ?? null),
            'data_inizio_partecipazione_ts' => isset($masterStudent['data_inizio_partecipazione']) ? intval($masterStudent['data_inizio_partecipazione']) : null,
            'data_fine_partecipazione_ts' => isset($masterStudent['data_fine_partecipazione']) ? intval($masterStudent['data_fine_partecipazione']) : null,
            'attivo_mastercom' => 1,
            'last_sync_at' => mastercomAdminNow(),
            'last_seen_at' => mastercomAdminNow(),
            'raw_json' => mastercomAdminJson($merged),
        ]);

        mastercomAdminUpsertByField('mastercom_studenti_classi', 'id', dbGetValue("SELECT id FROM mastercom_studenti_classi WHERE mastercom_id_studente = " . $studentId . " AND mastercom_id_classe = " . intval($classId) . " LIMIT 1") ?? 0, [
            'mastercom_id_studente' => $studentId,
            'mastercom_id_classe' => $classId,
            'anno_scolastico' => mastercomAdminCleanText($classRow['anno_scolastico'] ?? null),
            'classe_numero' => isset($masterStudent['classe']) ? intval($masterStudent['classe']) : null,
            'sezione' => mastercomAdminCleanText($masterStudent['sezione'] ?? null),
            'codice_indirizzo' => mastercomAdminCleanText($masterStudent['codice_indirizzi'] ?? null),
            'descrizione_indirizzo' => mastercomAdminCleanText($masterStudent['descrizione_indirizzi'] ?? null),
            'esito' => mastercomAdminCleanText($masterStudent['esito'] ?? null),
            'data_inizio_partecipazione_ts' => isset($masterStudent['data_inizio_partecipazione']) ? intval($masterStudent['data_inizio_partecipazione']) : null,
            'data_fine_partecipazione_ts' => isset($masterStudent['data_fine_partecipazione']) ? intval($masterStudent['data_fine_partecipazione']) : null,
            'last_sync_at' => mastercomAdminNow(),
            'raw_json' => mastercomAdminJson($merged),
        ]);

        $updated++;
    }

    return ['ok' => true, 'message' => "Studenti sincronizzati per classe $classId: $updated"];
}

function mastercomAdminSyncStudentsForAllClasses(callable $progress = null): array
{
    $classIds = dbGetAllValues("SELECT mastercom_id_classe FROM mastercom_classi ORDER BY nome ASC");
    if (empty($classIds)) {
        return ['ok' => false, 'message' => 'Nessuna classe MasterCom disponibile. Sincronizza prima le classi.'];
    }

    $totalClasses = 0;
    $overall = count($classIds);
    $messages = [];
    foreach ($classIds as $classId) {
        $classId = intval($classId);
        if ($classId <= 0) {
            continue;
        }
        mastercomAdminProgress($progress, 'students_all', $totalClasses + 1, $overall, 'Avvio sincronizzazione classe ' . $classId);

        $result = mastercomAdminSyncStudentsForClass($classId, $progress);
        if (!$result['ok']) {
            return [
                'ok' => false,
                'message' => 'Errore sulla classe ' . $classId . ': ' . ($result['message'] ?? 'SYNC_FAILED'),
            ];
        }

        $totalClasses++;
        $messages[] = $result['message'] ?? ('Classe ' . $classId . ' sincronizzata');
    }

    return [
        'ok' => true,
        'message' => 'Studenti sincronizzati per tutte le classi: ' . $totalClasses,
        'details' => $messages,
    ];
}

function mastercomAdminSyncParents(callable $progress = null): array
{
    try {
        $listResult = mastercomAdminLoadParentsList();
        if (!$listResult['ok']) {
            return $listResult;
        }
        return mastercomAdminSyncParentsChunk($listResult['records'], 0, count($listResult['records']), $progress);
    } catch (Throwable $e) {
        error('mastercomAdminSyncParents failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Errore sync genitori. Controllare il log applicativo per il dettaglio tecnico.'];
    }
}

function mastercomAdminSyncParentsChunk(array $parents, int $baseOffset = 0, int $overallTotal = 0, callable $progress = null): array
{
    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$authResult['ok']) {
        return ['ok' => false, 'message' => 'Autenticazione admin MasterCom fallita'];
    }

    $total = $overallTotal > 0 ? $overallTotal : count($parents);
    $updated = 0;
    foreach ($parents as $index => $parent) {
        $parentId = intval($parent['id_parente'] ?? 0);
        if ($parentId <= 0) {
            continue;
        }
        $current = $baseOffset + $index + 1;
        mastercomAdminProgress($progress, 'parents', $current, $total, 'Sincronizzazione genitore #' . $parentId . ' ' . (($parent['cognome'] ?? '') . ' ' . ($parent['nome'] ?? '')));

        $detailResult = mastercomLoadParentDetails($authResult, $parentId, [
            'method' => 'GET',
            'timeout' => 120,
        ]);
        $detail = mastercomAdminFirstRecord($detailResult['response'] ?? null) ?? [];
        $merged = array_merge($detail, $parent);
        $localParent = mastercomAdminFindLocalParent([
            'codice_fiscale' => $parent['codice_fiscale'] ?? $detail['cf'] ?? '',
            'email' => $detail['email'] ?? '',
            'cognome' => $parent['cognome'] ?? $detail['surname'] ?? '',
            'nome' => $parent['nome'] ?? $detail['first_name'] ?? '',
        ]);

        mastercomAdminUpsertByField('mastercom_genitori', 'mastercom_id_parente', $parentId, [
            'id_genitore_gestore' => $localParent['id'] ?? null,
            'mastercom_id_parente' => $parentId,
            'cognome' => mastercomAdminCleanText($parent['cognome'] ?? $detail['surname'] ?? null),
            'nome' => mastercomAdminCleanText($parent['nome'] ?? $detail['first_name'] ?? null),
            'codice_fiscale' => mastercomAdminCleanText($parent['codice_fiscale'] ?? $detail['cf'] ?? null),
            'email' => mastercomAdminCleanText($detail['email'] ?? null),
            'telefono' => mastercomAdminCleanText($detail['telephone'] ?? null),
            'cellulare' => mastercomAdminCleanText($detail['cellphone'] ?? null),
            'indirizzo' => mastercomAdminCleanText($detail['address'] ?? null),
            'cap' => mastercomAdminCleanText($detail['postal_code'] ?? null),
            'citta' => mastercomAdminCleanText($detail['city'] ?? null),
            'provincia' => mastercomAdminCleanText($detail['province'] ?? null),
            'comune_nascita' => mastercomAdminCleanText($detail['birth_place'] ?? null),
            'data_nascita_ts' => isset($detail['birth_date']) && is_numeric($detail['birth_date']) ? intval($detail['birth_date']) : null,
            'data_nascita' => (isset($detail['birth_date']) && is_numeric($detail['birth_date'])) ? date('Y-m-d', intval($detail['birth_date'])) : null,
            'attivo_mastercom' => 1,
            'last_sync_at' => mastercomAdminNow(),
            'last_seen_at' => mastercomAdminNow(),
            'raw_json' => mastercomAdminJson($merged),
        ]);

        foreach (($parent['studenti_abbinati'] ?? []) as $child) {
            $studentMcId = intval($child['id_studente'] ?? 0);
            if ($studentMcId <= 0) {
                continue;
            }
            $studentMirror = dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . $studentMcId . " LIMIT 1");
            if ($studentMirror == null) {
                warning('mastercomAdminSyncParents: studente mirror mancante, link saltato parent_id=' . $parentId . ' student_id=' . $studentMcId);
                continue;
            }

            $existingLinkId = dbGetValue("SELECT id FROM mastercom_genitori_studenti WHERE mastercom_id_parente = " . $parentId . " AND mastercom_id_studente = " . $studentMcId . " LIMIT 1");
            if ($existingLinkId !== null) {
                mastercomAdminExec("
                    UPDATE mastercom_genitori_studenti
                    SET
                        id_genitore_gestore = " . dbI($localParent['id'] ?? null) . ",
                        id_studente_gestore = " . dbI($studentMirror['id_studente_gestore'] ?? null) . ",
                        last_sync_at = " . dbQ(mastercomAdminNow()) . ",
                        raw_json = " . dbQ(mastercomAdminJson($child)) . "
                    WHERE id = " . intval($existingLinkId),
                    'sync parent link update parent_id=' . $parentId . ' student_id=' . $studentMcId
                );
            } else {
                mastercomAdminExec("
                    INSERT INTO mastercom_genitori_studenti (
                        mastercom_id_parente,
                        mastercom_id_studente,
                        id_genitore_gestore,
                        id_studente_gestore,
                        source_mastercom,
                        last_sync_at,
                        raw_json
                    ) VALUES (
                        " . intval($parentId) . ",
                        " . intval($studentMcId) . ",
                        " . dbI($localParent['id'] ?? null) . ",
                        " . dbI($studentMirror['id_studente_gestore'] ?? null) . ",
                        'mastercom',
                        " . dbQ(mastercomAdminNow()) . ",
                        " . dbQ(mastercomAdminJson($child)) . "
                    )",
                    'sync parent link insert parent_id=' . $parentId . ' student_id=' . $studentMcId
                );
            }
        }

        $updated++;
    }

    return ['ok' => true, 'message' => "Genitori sincronizzati: $updated"];
}

function mastercomAdminStudentDiffs(array $mirrorRow): array
{
    $local = null;
    if (!empty($mirrorRow['id_studente_gestore'])) {
        $local = dbGetFirst("SELECT * FROM studente WHERE id = " . intval($mirrorRow['id_studente_gestore']) . " LIMIT 1");
    }
    if ($local == null) {
        $local = mastercomAdminFindLocalStudent($mirrorRow);
    }

    $diffs = [];
    if ($local == null) {
        $diffs['studente_gestore'] = 'non collegato';
        return ['local' => null, 'diffs' => $diffs];
    }

    if (mastercomAdminNorm($local['cognome'] ?? '') !== mastercomAdminNorm($mirrorRow['cognome'] ?? '')) {
        $diffs['cognome'] = ['gestore' => $local['cognome'] ?? '', 'mastercom' => $mirrorRow['cognome'] ?? ''];
    }
    if (mastercomAdminNorm($local['nome'] ?? '') !== mastercomAdminNorm($mirrorRow['nome'] ?? '')) {
        $diffs['nome'] = ['gestore' => $local['nome'] ?? '', 'mastercom' => $mirrorRow['nome'] ?? ''];
    }
    if (mastercomAdminNorm($local['email'] ?? '') !== mastercomAdminNorm($mirrorRow['email1'] ?? '')) {
        $diffs['email'] = ['gestore' => $local['email'] ?? '', 'mastercom' => $mirrorRow['email1'] ?? ''];
    }
    if (mastercomAdminNormCompact($local['codice_fiscale'] ?? '') !== mastercomAdminNormCompact($mirrorRow['codice_fiscale'] ?? '')) {
        $diffs['codice_fiscale'] = ['gestore' => $local['codice_fiscale'] ?? '', 'mastercom' => $mirrorRow['codice_fiscale'] ?? ''];
    }

    return ['local' => $local, 'diffs' => $diffs];
}

function mastercomAdminParentDiffs(array $mirrorRow): array
{
    $local = mastercomAdminResolveLocalParent($mirrorRow);

    $diffs = [];
    if ($local == null) {
        $diffs['genitore_gestore'] = 'non collegato';
        return ['local' => null, 'diffs' => $diffs];
    }

    if (mastercomAdminNorm($local['cognome'] ?? '') !== mastercomAdminNorm($mirrorRow['cognome'] ?? '')) {
        $diffs['cognome'] = ['gestore' => $local['cognome'] ?? '', 'mastercom' => $mirrorRow['cognome'] ?? ''];
    }
    if (mastercomAdminNorm($local['nome'] ?? '') !== mastercomAdminNorm($mirrorRow['nome'] ?? '')) {
        $diffs['nome'] = ['gestore' => $local['nome'] ?? '', 'mastercom' => $mirrorRow['nome'] ?? ''];
    }
    if (mastercomAdminNorm($local['email'] ?? '') !== mastercomAdminNorm($mirrorRow['email'] ?? '')) {
        $diffs['email'] = ['gestore' => $local['email'] ?? '', 'mastercom' => $mirrorRow['email'] ?? ''];
    }
    if (mastercomAdminNormCompact($local['codice_fiscale'] ?? '') !== mastercomAdminNormCompact($mirrorRow['codice_fiscale'] ?? '')) {
        $diffs['codice_fiscale'] = ['gestore' => $local['codice_fiscale'] ?? '', 'mastercom' => $mirrorRow['codice_fiscale'] ?? ''];
    }

    return ['local' => $local, 'diffs' => $diffs];
}

function mastercomAdminDiffStatus(array $compareResult): array
{
    $local = $compareResult['local'] ?? null;
    $diffs = $compareResult['diffs'] ?? [];
    $count = is_array($diffs) ? count($diffs) : 0;

    if ($local == null) {
        return [
            'key' => 'missing',
            'label' => 'non presente in GestOre',
            'class' => 'warning',
        ];
    }

    if ($count === 0) {
        return [
            'key' => 'aligned',
            'label' => 'allineato',
            'class' => 'success',
        ];
    }

    if ($count === 1) {
        return [
            'key' => 'low',
            'label' => 'differenza lieve',
            'class' => 'info',
        ];
    }

    if ($count === 2) {
        return [
            'key' => 'medium',
            'label' => 'differenze medie',
            'class' => 'primary',
        ];
    }

    return [
        'key' => 'high',
        'label' => 'differenze alte',
        'class' => 'danger',
    ];
}

function mastercomAdminTeacherStatus(array $mirrorRow): array
{
    if (!empty($mirrorRow['id_docente_gestore'])) {
        return [
            'key' => 'linked',
            'label' => 'collegato',
            'class' => 'success',
        ];
    }

    return [
        'key' => 'missing',
        'label' => 'non presente in GestOre',
        'class' => 'warning',
    ];
}

function mastercomAdminTeacherMatchesFilter(array $row, string $filter): bool
{
    $status = mastercomAdminTeacherStatus($row);
    $isLinked = !empty($row['id_docente_gestore']);
    $isActiveInGestore = $isLinked && intval($row['gestore_attivo'] ?? 0) === 1;

    if ($filter === 'aligned') {
        return $isLinked;
    }
    if ($filter === 'issues') {
        return !$isLinked;
    }
    if ($filter === 'active_gestore') {
        return $isActiveInGestore;
    }

    return true;
}

function mastercomAdminParentMatchesFilter(array $compareResult, string $filter): bool
{
    $status = mastercomAdminDiffStatus($compareResult);
    $key = (string)($status['key'] ?? '');

    if ($filter === 'aligned') {
        return $key === 'aligned';
    }
    if ($filter === 'missing') {
        return $key === 'missing';
    }
    if ($filter === 'issues') {
        return in_array($key, ['low', 'medium', 'high'], true);
    }
    if ($filter === 'low') {
        return $key === 'low';
    }
    if ($filter === 'medium') {
        return $key === 'medium';
    }
    if ($filter === 'high') {
        return $key === 'high';
    }

    return true;
}

function mastercomAdminAlignGestoreStudentFromMastercom(int $mastercomStudentId): array
{
    global $__anno_scolastico_corrente_id;

    $mirror = dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . intval($mastercomStudentId) . " LIMIT 1");
    if ($mirror == null) {
        return ['ok' => false, 'message' => 'Studente MasterCom non trovato'];
    }

    $local = !empty($mirror['id_studente_gestore'])
        ? dbGetFirst("SELECT * FROM studente WHERE id = " . intval($mirror['id_studente_gestore']) . " LIMIT 1")
        : mastercomAdminFindLocalStudent($mirror);

    if ($local == null) {
        return ['ok' => false, 'message' => 'Studente GestOre non trovato'];
    }

    dbExec("
        UPDATE studente
        SET
            cognome = " . dbQ($mirror['cognome']) . ",
            nome = " . dbQ($mirror['nome']) . ",
            email = " . dbQ($mirror['email1']) . ",
            codice_fiscale = " . dbQ($mirror['codice_fiscale']) . "
        WHERE id = " . intval($local['id'])
    );

    if (!empty($mirror['mastercom_id_classe_corrente'])) {
        $classMirror = dbGetFirst("SELECT * FROM mastercom_classi WHERE mastercom_id_classe = " . intval($mirror['mastercom_id_classe_corrente']) . " LIMIT 1");
        $localClassId = intval($classMirror['id_classe_gestore'] ?? 0);
        if ($localClassId > 0) {
            $freqId = dbGetValue("
                SELECT id
                FROM studente_frequenta
                WHERE id_studente = " . intval($local['id']) . "
                  AND id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
                LIMIT 1
            ");
            if ($freqId !== null) {
                dbExec("UPDATE studente_frequenta SET id_classe = " . $localClassId . " WHERE id = " . intval($freqId));
            } else {
                dbExec("
                    INSERT INTO studente_frequenta (id_studente, id_classe, id_anno_scolastico)
                    VALUES (" . intval($local['id']) . ", " . $localClassId . ", " . intval($__anno_scolastico_corrente_id) . ")
                ");
            }
        }
    }

    dbExec("UPDATE mastercom_studenti SET id_studente_gestore = " . intval($local['id']) . " WHERE id = " . intval($mirror['id']));

    return ['ok' => true, 'message' => 'Studente GestOre allineato da MasterCom'];
}

function mastercomAdminAlignMirrorStudentFromGestore(int $mastercomStudentId): array
{
    global $__anno_scolastico_corrente_id;

    $mirror = dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . intval($mastercomStudentId) . " LIMIT 1");
    if ($mirror == null) {
        return ['ok' => false, 'message' => 'Studente MasterCom non trovato'];
    }

    $local = !empty($mirror['id_studente_gestore'])
        ? dbGetFirst("SELECT * FROM studente WHERE id = " . intval($mirror['id_studente_gestore']) . " LIMIT 1")
        : mastercomAdminFindLocalStudent($mirror);

    if ($local == null) {
        return ['ok' => false, 'message' => 'Studente GestOre non trovato'];
    }

    $localClassId = dbGetValue("
        SELECT id_classe
        FROM studente_frequenta
        WHERE id_studente = " . intval($local['id']) . "
          AND id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
        LIMIT 1
    ");
    $localClass = $localClassId !== null ? dbGetFirst("SELECT * FROM classi WHERE id = " . intval($localClassId) . " LIMIT 1") : null;
    $classMirrorId = null;
    if ($localClass != null) {
        $classMirrorId = dbGetValue("SELECT mastercom_id_classe FROM mastercom_classi WHERE id_classe_gestore = " . intval($localClass['id']) . " LIMIT 1");
    }

    dbExec("
        UPDATE mastercom_studenti
        SET
            id_studente_gestore = " . intval($local['id']) . ",
            mastercom_id_classe_corrente = " . dbI($classMirrorId) . ",
            cognome = " . dbQ($local['cognome'] ?? '') . ",
            nome = " . dbQ($local['nome'] ?? '') . ",
            email1 = " . dbQ($local['email'] ?? '') . ",
            codice_fiscale = " . dbQ($local['codice_fiscale'] ?? '') . ",
            last_sync_at = " . dbQ(mastercomAdminNow()) . "
        WHERE id = " . intval($mirror['id'])
    );

    return ['ok' => true, 'message' => 'Scheda MasterCom locale studente allineata da GestOre'];
}

function mastercomAdminAlignGestoreParentFromMastercom(int $mastercomParentId): array
{
    $mirror = dbGetFirst("SELECT * FROM mastercom_genitori WHERE mastercom_id_parente = " . intval($mastercomParentId) . " LIMIT 1");
    if ($mirror == null) {
        return ['ok' => false, 'message' => 'Genitore MasterCom non trovato'];
    }

    $local = mastercomAdminResolveLocalParent($mirror);

    if ($local == null) {
        return ['ok' => false, 'message' => 'Genitore GestOre non trovato'];
    }

    dbExec("
        UPDATE genitori
        SET
            cognome = " . dbQ($mirror['cognome']) . ",
            nome = " . dbQ($mirror['nome']) . ",
            email = " . dbQ($mirror['email']) . ",
            codice_fiscale = " . dbQ($mirror['codice_fiscale']) . "
        WHERE id = " . intval($local['id'])
    );

    dbExec("UPDATE mastercom_genitori SET id_genitore_gestore = " . intval($local['id']) . " WHERE id = " . intval($mirror['id']));

    return ['ok' => true, 'message' => 'Genitore GestOre allineato da MasterCom'];
}

function mastercomAdminAlignMirrorParentFromGestore(int $mastercomParentId): array
{
    $mirror = dbGetFirst("SELECT * FROM mastercom_genitori WHERE mastercom_id_parente = " . intval($mastercomParentId) . " LIMIT 1");
    if ($mirror == null) {
        return ['ok' => false, 'message' => 'Genitore MasterCom non trovato'];
    }

    $local = mastercomAdminResolveLocalParent($mirror);

    if ($local == null) {
        return ['ok' => false, 'message' => 'Genitore GestOre non trovato'];
    }

    dbExec("
        UPDATE mastercom_genitori
        SET
            id_genitore_gestore = " . intval($local['id']) . ",
            cognome = " . dbQ($local['cognome'] ?? '') . ",
            nome = " . dbQ($local['nome'] ?? '') . ",
            email = " . dbQ($local['email'] ?? '') . ",
            codice_fiscale = " . dbQ($local['codice_fiscale'] ?? '') . ",
            last_sync_at = " . dbQ(mastercomAdminNow()) . "
        WHERE id = " . intval($mirror['id'])
    );

    return ['ok' => true, 'message' => 'Scheda MasterCom locale genitore allineata da GestOre'];
}

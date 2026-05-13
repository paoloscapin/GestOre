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
    $first = mastercomNoIrcNormalizeClassLabel((string)($parts[0] ?? ''));
    $second = mastercomNoIrcNormalizeClassLabel((string)($parts[1] ?? ''));

    if ($first !== '' && preg_match('/^\d+$/', $first) && $second !== '' && preg_match('/^[A-Z]+$/', $second)) {
        return $first . $second;
    }

    return $first;
}

function mastercomNoIrcChoiceCode(?string $choiceDescription): string
{
    $normalized = mastercomAdminNorm($choiceDescription);

    if ($normalized === '') {
        return '';
    }

    if (
        strpos($normalized, 'LIBERA ATTIVITA DI STUDIO') !== false
        || (
            strpos($normalized, 'SENZA ASSISTENZA') !== false
            && strpos($normalized, 'DOCENTE') !== false
        )
    ) {
        return 'LAS';
    }

    if (
        strpos($normalized, 'ATTIVITA DI STUDIO') !== false
        && strpos($normalized, 'ASSISTENZA') !== false
        && strpos($normalized, 'DOCENTE') !== false
    ) {
        return 'ASD';
    }

    if (
        strpos($normalized, 'ALLONTANARSI') !== false
        || strpos($normalized, 'ASSENTARSI') !== false
        || strpos($normalized, 'EDIFICIO SCOLASTICO') !== false
    ) {
        return 'AES';
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
        'mastercom_noirc_aula_classi',
        'mastercom_noirc_appelli',
        'mastercom_noirc_appello_studenti',
    ];
}

function mastercomNoIrcDefaultRooms(): array
{
    return ['246', '128'];
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

function mastercomNoIrcExtractStudentsList(array $studentsResult): array
{
    $result = $studentsResult['response']['result'] ?? null;
    if (!is_array($result)) {
        return [];
    }

    if (isset($result['students']) && is_array($result['students'])) {
        return $result['students'];
    }

    if (isset($result['studenti']) && is_array($result['studenti'])) {
        return $result['studenti'];
    }

    return $result;
}

function mastercomNoIrcMapLiveReligionExemption(array $student): ?int
{
    foreach (['esonero_religione', 'esoneroReligione'] as $key) {
        if (array_key_exists($key, $student)) {
            $value = $student[$key];
            if ($value === null || $value === '') {
                return null;
            }
            if (is_numeric($value)) {
                return intval($value) === 1 ? 1 : 0;
            }

            return mastercomAdminMapReligionExemptionValue($value);
        }
    }

    foreach (['irc', 'IRC', 'religione', 'Religione', 'relig', 'relig.'] as $key) {
        if (!array_key_exists($key, $student)) {
            continue;
        }

        $normalized = mastercomAdminNorm((string)$student[$key]);
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['SI', 'S', '1', 'TRUE'], true)) {
            return 0;
        }

        if (in_array($normalized, ['NO', 'N', '0', 'FALSE'], true)) {
            return 1;
        }
    }

    return null;
}

function mastercomNoIrcLoadClassIdsByLabel(array $classLabels): array
{
    $wanted = [];
    foreach ($classLabels as $classLabel) {
        $classLabel = mastercomNoIrcNormalizeClassLabel($classLabel);
        if ($classLabel !== '') {
            $wanted[$classLabel] = true;
        }
    }

    if (empty($wanted)) {
        return [];
    }

    $rows = dbGetAll("
        SELECT mastercom_id_classe, nome
        FROM mastercom_classi
        WHERE mastercom_id_classe IS NOT NULL
          AND mastercom_id_classe > 0
    ") ?: [];

    $map = [];
    foreach ($rows as $row) {
        $label = mastercomNoIrcMastercomClassBaseLabel($row['nome'] ?? '');
        if ($label === '' || !isset($wanted[$label])) {
            continue;
        }
        $map[$label] = intval($row['mastercom_id_classe'] ?? 0);
    }

    return $map;
}

function mastercomNoIrcLoadMirrorStudentsByMastercomId(array $classIds): array
{
    if (empty($classIds)) {
        return [];
    }

    $ids = array_values(array_filter(array_map('intval', $classIds), function ($id) {
        return $id > 0;
    }));
    if (empty($ids)) {
        return [];
    }

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
            s.raw_json
        FROM mastercom_studenti s
        WHERE s.mastercom_id_classe_corrente IN (" . implode(',', $ids) . ")
    ") ?: [];

    $map = [];
    foreach ($rows as $row) {
        $studentId = intval($row['mastercom_id_studente'] ?? 0);
        if ($studentId > 0) {
            $map[$studentId] = $row;
        }
    }

    return $map;
}

function mastercomNoIrcAddStudentToPool(array &$pools, string $classLabel, array $student): void
{
    if ($classLabel === '') {
        return;
    }

    if (!isset($pools[$classLabel])) {
        $pools[$classLabel] = [
            'included' => [],
            'excluded_outside_count' => 0,
            'unknown_choice_count' => 0,
        ];
    }

    if (($student['scelta_descrizione'] ?? '') === '') {
        $pools[$classLabel]['unknown_choice_count']++;
    }

    if (!empty($student['scelta_fuori_scuola'])) {
        $pools[$classLabel]['excluded_outside_count']++;
        return;
    }

    $pools[$classLabel]['included'][] = $student;
}

function mastercomNoIrcSortStudentPools(array &$pools): void
{
    foreach ($pools as &$pool) {
        usort($pool['included'], function ($left, $right) {
            $leftKey = trim((string)($left['cognome'] ?? '')) . ' ' . trim((string)($left['nome'] ?? ''));
            $rightKey = trim((string)($right['cognome'] ?? '')) . ' ' . trim((string)($right['nome'] ?? ''));
            return strnatcasecmp($leftKey, $rightKey);
        });
    }
    unset($pool);
}

function mastercomNoIrcLoadStudentPoolsByClassFromMirror(): array
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
        $localClassLabel = mastercomNoIrcNormalizeClassLabel($row['classe_locale'] ?? '');
        $mastercomClassLabel = mastercomNoIrcMastercomClassBaseLabel($row['classe_mastercom'] ?? '');
        if ($localClassLabel !== '' && $mastercomClassLabel !== '' && $localClassLabel !== $mastercomClassLabel) {
            continue;
        }

        $classLabel = $localClassLabel !== '' ? $localClassLabel : $mastercomClassLabel;
        if ($classLabel === '') {
            continue;
        }

        $rawJson = json_decode((string)($row['raw_json'] ?? ''), true);
        $csvExport = is_array($rawJson['_csv_export'] ?? null) ? $rawJson['_csv_export'] : [];
        $choiceDescription = mastercomAdminCleanText($row['descrizione_materia_integrativa'] ?? '') ?? '';
        if ($choiceDescription === '' && !empty($csvExport)) {
            $choiceDescription = mastercomAdminCleanText($csvExport['descrizione_materia_integrativa'] ?? '') ?? '';
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

        mastercomNoIrcAddStudentToPool($pools, $classLabel, $student);
    }

    mastercomNoIrcSortStudentPools($pools);

    return $pools;
}

function mastercomNoIrcLoadStudentPoolsByClass(array $classLabels = []): array
{
    return mastercomNoIrcLoadStudentPoolsByClassFromMirror();
}

function mastercomNoIrcRefreshStudentMirrorForClasses(array $classLabels): array
{
    $classIdsByLabel = mastercomNoIrcLoadClassIdsByLabel($classLabels);
    if (empty($classIdsByLabel)) {
        return [
            'ok' => false,
            'message' => 'Nessuna classe MasterCom trovata per la settimana selezionata',
            'updated_classes' => 0,
        ];
    }

    $updatedClasses = 0;
    $updatedStudents = 0;
    $errors = [];

    foreach ($classIdsByLabel as $classLabel => $classId) {
        $syncResult = mastercomAdminSyncStudentsForClass(intval($classId));
        if (empty($syncResult['ok'])) {
            $errors[] = $classLabel . ': ' . trim((string)($syncResult['message'] ?? 'sincronizzazione fallita'));
            continue;
        }

        $updatedClasses++;
        $updatedStudents += intval($syncResult['updated'] ?? 0);
    }

    return [
        'ok' => empty($errors),
        'message' => empty($errors)
            ? ('Dati NO IRC aggiornati da MasterCom per ' . $updatedClasses . ' classi')
            : ('Aggiornamento parziale: ' . implode(' | ', $errors)),
        'updated_classes' => $updatedClasses,
        'updated_students' => $updatedStudents,
        'errors' => $errors,
    ];
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

function mastercomNoIrcClassLabelsFromLessons(array $lessons): array
{
    $labels = [];
    foreach ($lessons as $lesson) {
        foreach (($lesson['classi'] ?? []) as $classLabel) {
            $classLabel = mastercomNoIrcNormalizeClassLabel($classLabel);
            if ($classLabel !== '') {
                $labels[$classLabel] = true;
            }
        }
    }

    return array_values(array_keys($labels));
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

function mastercomNoIrcLoadRoomAssignments(string $weekStart, string $weekEnd): array
{
    if (!mastercomAdminTableExists('mastercom_noirc_aula_classi')) {
        return [];
    }

    $weekStart = mastercomNoIrcNormalizeDate($weekStart);
    $weekEnd = mastercomNoIrcNormalizeDate($weekEnd);

    $rows = dbGetAll("
        SELECT *
        FROM mastercom_noirc_aula_classi
        WHERE attivo = 1
          AND data_inizio <= " . dbQ($weekEnd) . "
          AND data_fine >= " . dbQ($weekStart) . "
        ORDER BY giorno_settimana ASC, ora ASC, classe_label ASC, data_inizio DESC
    ") ?: [];

    $map = [];
    foreach ($rows as $row) {
        $weekday = intval($row['giorno_settimana'] ?? 0);
        $hour = mastercomNoIrcNormalizeHour($row['ora'] ?? '');
        $classLabel = mastercomNoIrcNormalizeClassLabel($row['classe_label'] ?? '');
        $room = trim((string)($row['aula'] ?? ''));
        if ($weekday <= 0 || $hour === '' || $classLabel === '' || $room === '') {
            continue;
        }

        $key = $weekday . '|' . $hour;
        if (!isset($map[$key])) {
            $map[$key] = [];
        }
        if (!isset($map[$key][$classLabel])) {
            $map[$key][$classLabel] = [
                'aula' => $room,
                'data_inizio' => trim((string)($row['data_inizio'] ?? '')),
                'data_fine' => trim((string)($row['data_fine'] ?? '')),
                'note' => trim((string)($row['note'] ?? '')),
            ];
        }
    }

    return $map;
}

function mastercomNoIrcSaveRoomSetup(array $payload): array
{
    if (!mastercomAdminTableExists('mastercom_noirc_aula_classi')) {
        return ['ok' => false, 'error' => 'Tabella mastercom_noirc_aula_classi mancante'];
    }

    $weekday = intval($payload['giorno_settimana'] ?? 0);
    $hour = mastercomNoIrcNormalizeHour($payload['ora'] ?? '');
    $startDate = mastercomNoIrcNormalizeDate((string)($payload['data_inizio'] ?? ''));
    $endDate = mastercomNoIrcNormalizeDate((string)($payload['data_fine'] ?? ''));
    $roomsByClass = is_array($payload['room_assignments'] ?? null) ? $payload['room_assignments'] : [];
    $allowedRooms = array_flip(mastercomNoIrcDefaultRooms());

    if ($weekday < 1 || $weekday > 6) {
        return ['ok' => false, 'error' => 'Giorno non valido'];
    }
    if ($hour === '') {
        return ['ok' => false, 'error' => 'Ora non valida'];
    }
    if ($startDate === '') {
        return ['ok' => false, 'error' => 'Seleziona una data inizio valida'];
    }

    if ($endDate === '') {
        return ['ok' => false, 'error' => 'Seleziona una data fine valida'];
    }
    if ($endDate < $startDate) {
        return ['ok' => false, 'error' => 'La data fine non puo essere precedente alla data inizio'];
    }
    if (empty($roomsByClass)) {
        return ['ok' => false, 'error' => 'Nessuna classe da assegnare'];
    }

    $normalized = [];
    foreach ($roomsByClass as $classLabel => $room) {
        $classLabel = mastercomNoIrcNormalizeClassLabel(rawurldecode((string)$classLabel));
        $room = trim((string)$room);
        if ($classLabel === '' || $room === '') {
            continue;
        }
        if (!isset($allowedRooms[$room])) {
            $room = '246';
        }
        $normalized[$classLabel] = $room;
    }

    if (empty($normalized)) {
        return ['ok' => false, 'error' => 'Nessuna assegnazione valida'];
    }

    $classSql = implode(', ', array_map('dbQ', array_keys($normalized)));
    dbExec("
        DELETE FROM mastercom_noirc_aula_classi
        WHERE giorno_settimana = " . dbI($weekday) . "
          AND ora = " . dbQ($hour) . "
          AND classe_label IN ($classSql)
          AND data_inizio <= " . dbQ($endDate) . "
          AND data_fine >= " . dbQ($startDate) . "
    ");

    foreach ($normalized as $classLabel => $room) {
        dbExec("
            INSERT INTO mastercom_noirc_aula_classi
                (giorno_settimana, ora, classe_label, aula, data_inizio, data_fine, attivo, created_at, updated_at)
            VALUES
                (" . dbI($weekday) . ", " . dbQ($hour) . ", " . dbQ($classLabel) . ", " . dbQ($room) . ", " . dbQ($startDate) . ", " . dbQ($endDate) . ", 1, NOW(), NOW())
        ");
    }

    return ['ok' => true, 'count' => count($normalized)];
}

function mastercomNoIrcBuildWeekSlots(string $referenceDate): array
{
    $week = mastercomNoIrcWeekContext($referenceDate);
    $assignmentsMap = mastercomNoIrcLoadAssignments($week['week_start'], $week['week_end']);
    $roomAssignmentsMap = mastercomNoIrcLoadRoomAssignments($week['week_start'], $week['week_end']);
    $lessons = mastercomNoIrcLoadIrcLessons($week['week_start'], $week['week_end']);
    $studentPools = mastercomNoIrcLoadStudentPoolsByClass(mastercomNoIrcClassLabelsFromLessons($lessons));
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
                'room_setup' => $roomAssignmentsMap[$assignmentKey] ?? [],
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
        if (!empty($slot['room_setup'])) {
            foreach (mastercomNoIrcDefaultRooms() as $room) {
                $assignmentForRoom = null;
                foreach ($slot['assignments'] as $assignment) {
                    if (trim((string)($assignment['aula'] ?? '')) === $room) {
                        $assignmentForRoom = $assignment;
                        break;
                    }
                }
                $groupBuckets['room:' . $room] = [
                    'type' => 'room_setup',
                    'group_label' => 'Aula ' . $room,
                    'teacher_name' => $assignmentForRoom !== null ? trim((string)($assignmentForRoom['teacher_name'] ?? '')) : '',
                    'aula' => $room,
                    'note' => $assignmentForRoom !== null ? trim((string)($assignmentForRoom['note'] ?? '')) : '',
                    'class_filters' => [],
                    'class_filters_raw' => '',
                    'capienza_massima' => $room === '246' ? 0 : 0,
                    'students' => [],
                ];
            }

            foreach ($slot['students_by_class'] as $classLabel => $students) {
                $room = trim((string)($slot['room_setup'][$classLabel]['aula'] ?? '246'));
                if ($room === '') {
                    $room = '246';
                }
                $bucketKey = 'room:' . $room;
                if (!isset($groupBuckets[$bucketKey])) {
                    $groupBuckets[$bucketKey] = [
                        'type' => 'room_setup',
                        'group_label' => 'Aula ' . $room,
                        'teacher_name' => '',
                        'aula' => $room,
                        'note' => '',
                        'class_filters' => [],
                        'class_filters_raw' => '',
                        'capienza_massima' => 0,
                        'students' => [],
                    ];
                }
                $groupBuckets[$bucketKey]['class_filters'][] = $classLabel;
                foreach ($students as $student) {
                    $groupBuckets[$bucketKey]['students'][] = $student;
                }
            }

            foreach ($groupBuckets as &$bucket) {
                $bucket['class_filters'] = array_values(array_unique($bucket['class_filters']));
                sort($bucket['class_filters']);
                $bucket['class_filters_raw'] = implode(' ', $bucket['class_filters']);
            }
            unset($bucket);
        } else {
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
    $allowedRooms = array_flip(mastercomNoIrcDefaultRooms());

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
    if (!isset($allowedRooms[$aula])) {
        return ['ok' => false, 'error' => 'Seleziona una delle aule configurate'];
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
            'gruppo_label' => 'A',
            'classi_incluse' => '',
            'capienza_massima' => 0,
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
            WHERE id = " . dbI($assignmentId) . "
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

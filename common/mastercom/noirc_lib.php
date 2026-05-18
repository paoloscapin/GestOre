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

function mastercomNoIrcLog(string $message, array $context = []): void
{
    if (!function_exists('info')) {
        return;
    }

    $suffix = '';
    if (!empty($context)) {
        $suffix = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    info('[NOIRC_REGISTRO] ' . $message . $suffix);
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

    $rows = function_exists('mastercomAdminOperationalClassRows')
        ? mastercomAdminOperationalClassRows('mastercom_id_classe, nome')
        : (dbGetAll("SELECT mastercom_id_classe, nome FROM mastercom_classi WHERE mastercom_id_classe IS NOT NULL AND mastercom_id_classe > 0") ?: []);

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
            'mastercom_id_classe_corrente' => intval($row['mastercom_id_classe_corrente'] ?? 0),
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
            'id_docente' => intval($row['id_docente'] ?? 0),
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

function mastercomNoIrcTimeToMinutes(string $time): int
{
    $time = mastercomNoIrcNormalizeHour($time);
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
        return -1;
    }

    return intval($matches[1]) * 60 + intval($matches[2]);
}

function mastercomNoIrcSubstitutionCoversHour(string $slotHour, string $startHour, string $endHour): bool
{
    $slot = mastercomNoIrcTimeToMinutes($slotHour);
    $start = mastercomNoIrcTimeToMinutes($startHour);
    $end = mastercomNoIrcTimeToMinutes($endHour);
    if ($slot < 0 || $start < 0) {
        return false;
    }
    if ($end < 0) {
        return $slot === $start;
    }
    if ($end <= $start) {
        return $slot === $start;
    }

    return $slot >= $start && $slot < $end;
}

function mastercomNoIrcLoadSubstitutions(string $weekStart, string $weekEnd): array
{
    if (!mastercomAdminTableExists('sostituzioni')) {
        return [];
    }

    $weekStart = mastercomNoIrcNormalizeDate($weekStart);
    $weekEnd = mastercomNoIrcNormalizeDate($weekEnd);
    $hasStato = mastercomAdminTableColumnExists('sostituzioni', 'stato');
    $statoSql = $hasStato ? "s.stato" : "'' AS stato";
    $statoWhere = $hasStato ? "AND (s.stato IS NULL OR UPPER(TRIM(s.stato)) <> 'ANNULLATA')" : "";

    $rows = dbGetAll("
        SELECT
            s.idSostituzione,
            s.data,
            s.oraInizio,
            s.oraFine,
            s.materia,
            s.classe,
            s.aula,
            $statoSql,
            ds.id AS idDocenteSostituto,
            ds.cognome AS cognomeSostituto,
            ds.nome AS nomeSostituto,
            dd.id AS idDocenteSostituito,
            dd.cognome AS cognomeSostituito,
            dd.nome AS nomeSostituito
        FROM sostituzioni s
        LEFT JOIN docente ds
            ON ds.id = s.idDocenteSostituto
        LEFT JOIN docente dd
            ON dd.id = s.idDocenteSostituito
        WHERE s.data >= " . dbQ($weekStart) . "
          AND s.data <= " . dbQ($weekEnd) . "
          $statoWhere
        ORDER BY s.data ASC, s.oraInizio ASC, s.oraFine ASC
    ") ?: [];

    $map = [];
    foreach ($rows as $row) {
        $date = substr(trim((string)($row['data'] ?? '')), 0, 10);
        $replacedId = intval($row['idDocenteSostituito'] ?? 0);
        $substituteId = intval($row['idDocenteSostituto'] ?? 0);
        $start = mastercomNoIrcNormalizeHour($row['oraInizio'] ?? '');
        $end = mastercomNoIrcNormalizeHour($row['oraFine'] ?? '');
        if ($date === '' || $replacedId <= 0 || $substituteId <= 0 || $start === '') {
            continue;
        }

        $record = [
            'idSostituzione' => intval($row['idSostituzione'] ?? 0),
            'date' => $date,
            'oraInizio' => $start,
            'oraFine' => $end,
            'materia' => trim((string)($row['materia'] ?? '')),
            'classe' => mastercomNoIrcNormalizeClassLabel($row['classe'] ?? ''),
            'aula' => trim((string)($row['aula'] ?? '')),
            'stato' => trim((string)($row['stato'] ?? '')),
            'id_docente_sostituto' => $substituteId,
            'id_docente_sostituito' => $replacedId,
            'substitute_name' => trim((string)(($row['cognomeSostituto'] ?? '') . ' ' . ($row['nomeSostituto'] ?? ''))),
            'replaced_name' => trim((string)(($row['cognomeSostituito'] ?? '') . ' ' . ($row['nomeSostituito'] ?? ''))),
        ];

        foreach (mastercomNoIrcOrari() as $slotHour) {
            if (!mastercomNoIrcSubstitutionCoversHour($slotHour, $start, $end)) {
                continue;
            }
            $key = $date . '|' . $slotHour . '|' . $replacedId;
            if (!isset($map[$key])) {
                $map[$key] = [];
            }
            $map[$key][] = $record;
        }
    }

    return $map;
}

function mastercomNoIrcFindBucketSubstitution(array $bucket, array $slot, array $substitutionsMap): ?array
{
    $teacherId = intval($bucket['id_docente'] ?? 0);
    if ($teacherId <= 0) {
        return null;
    }

    $key = trim((string)($slot['date'] ?? '')) . '|' . mastercomNoIrcNormalizeHour($slot['hour'] ?? '') . '|' . $teacherId;
    $candidates = $substitutionsMap[$key] ?? [];
    if (empty($candidates)) {
        return null;
    }

    $bucketAula = trim((string)($bucket['aula'] ?? ''));
    $bucketClasses = array_values($bucket['class_filters'] ?? []);
    foreach ($candidates as $substitution) {
        $subAula = trim((string)($substitution['aula'] ?? ''));
        $subClasse = mastercomNoIrcNormalizeClassLabel($substitution['classe'] ?? '');
        $aulaMatches = $subAula === '' || $bucketAula === '' || $subAula === $bucketAula;
        $classMatches = $subClasse === '' || empty($bucketClasses) || in_array($subClasse, $bucketClasses, true);
        if ($aulaMatches && $classMatches) {
            return $substitution;
        }
    }

    return $candidates[0] ?? null;
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
    $substitutionsMap = mastercomNoIrcLoadSubstitutions($week['week_start'], $week['week_end']);
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
                    'assignment_id' => $assignmentForRoom !== null ? intval($assignmentForRoom['id'] ?? 0) : 0,
                    'id_docente' => $assignmentForRoom !== null ? intval($assignmentForRoom['id_docente'] ?? 0) : 0,
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
                        'assignment_id' => 0,
                        'id_docente' => 0,
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
                    'assignment_id' => $assignmentId,
                    'id_docente' => intval($assignment['id_docente'] ?? 0),
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
                            'assignment_id' => 0,
                            'id_docente' => 0,
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
            $substitution = mastercomNoIrcFindBucketSubstitution($bucket, $slot, $substitutionsMap);
            $bucket['original_id_docente'] = intval($bucket['id_docente'] ?? 0);
            $bucket['original_teacher_name'] = trim((string)($bucket['teacher_name'] ?? ''));
            $bucket['effective_id_docente'] = intval($bucket['id_docente'] ?? 0);
            $bucket['effective_teacher_name'] = trim((string)($bucket['teacher_name'] ?? ''));
            $bucket['substitution'] = null;
            if ($substitution !== null) {
                $bucket['substitution'] = $substitution;
                $bucket['effective_id_docente'] = intval($substitution['id_docente_sostituto'] ?? 0);
                $bucket['effective_teacher_name'] = trim((string)($substitution['substitute_name'] ?? ''));
            }

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

function mastercomNoIrcCurrentDate(): string
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
}

function mastercomNoIrcDateWeekday(string $date): int
{
    $dt = DateTime::createFromFormat('Y-m-d', mastercomNoIrcNormalizeDate($date), new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? intval($dt->format('N')) : 0;
}

function mastercomNoIrcAppealAulaColumnExists(): bool
{
    return mastercomAdminTableColumnExists('mastercom_noirc_appelli', 'aula');
}

function mastercomNoIrcFindRegistroContext(string $date, string $hour, string $aula, int $currentDocenteId = 0, bool $isAdmin = false): array
{
    $date = mastercomNoIrcNormalizeDate($date);
    $hour = mastercomNoIrcNormalizeHour($hour);
    $aula = trim($aula);

    if ($hour === '') {
        return ['ok' => false, 'error' => 'Ora non valida'];
    }

    $context = mastercomNoIrcBuildWeekSlots($date);
    $slot = $context['slots'][$date . '|' . $hour] ?? null;
    if (!is_array($slot)) {
        mastercomNoIrcLog('find_context_slot_missing', [
            'date' => $date,
            'hour' => $hour,
            'aula' => $aula,
            'docente_id' => $currentDocenteId,
            'is_admin' => $isAdmin,
            'slots_count' => count($context['slots'] ?? []),
        ]);
        return ['ok' => false, 'error' => 'Nessuno slot IRC trovato per data e ora selezionate'];
    }

    mastercomNoIrcLog('find_context_slot_found', [
        'date' => $date,
        'hour' => $hour,
        'aula' => $aula,
        'docente_id' => $currentDocenteId,
        'is_admin' => $isAdmin,
        'bucket_count' => count($slot['group_buckets'] ?? []),
        'assignment_count' => count($slot['assignments'] ?? []),
        'assignments' => array_map(function ($assignment) {
            return [
                'id' => intval($assignment['id'] ?? 0),
                'id_docente' => intval($assignment['id_docente'] ?? 0),
                'aula' => trim((string)($assignment['aula'] ?? '')),
                'teacher_name' => trim((string)($assignment['teacher_name'] ?? '')),
            ];
        }, $slot['assignments'] ?? []),
    ]);

    $selectedBucket = null;
    foreach ($slot['group_buckets'] as $bucket) {
        $bucketAula = trim((string)($bucket['aula'] ?? ''));
        if ($aula !== '' && $bucketAula === $aula) {
            $selectedBucket = $bucket;
            break;
        }
    }

    if ($selectedBucket === null && !$isAdmin && $currentDocenteId > 0) {
        foreach ($slot['group_buckets'] as $bucket) {
            if (mastercomNoIrcBucketMatchesDocente($bucket, $slot, $currentDocenteId)) {
                $selectedBucket = $bucket;
                break;
            }
        }
    }

    if ($selectedBucket === null) {
        foreach ($slot['group_buckets'] as $bucket) {
            if (!empty($bucket['students'])) {
                $selectedBucket = $bucket;
                break;
            }
        }
    }

    if ($selectedBucket === null) {
        mastercomNoIrcLog('find_context_no_bucket', [
            'date' => $date,
            'hour' => $hour,
            'aula' => $aula,
            'docente_id' => $currentDocenteId,
            'buckets' => array_map(function ($bucket) {
                return [
                    'type' => $bucket['type'] ?? '',
                    'assignment_id' => intval($bucket['assignment_id'] ?? 0),
                    'id_docente' => intval($bucket['id_docente'] ?? 0),
                    'aula' => trim((string)($bucket['aula'] ?? '')),
                    'students' => count($bucket['students'] ?? []),
                ];
            }, $slot['group_buckets'] ?? []),
        ]);
        return ['ok' => false, 'error' => 'Nessun gruppo/aula disponibile per lo slot selezionato'];
    }

    if (!$isAdmin && !mastercomNoIrcBucketMatchesDocente($selectedBucket, $slot, $currentDocenteId)) {
        mastercomNoIrcLog('find_context_bucket_not_for_docente', [
            'date' => $date,
            'hour' => $hour,
            'aula' => $aula,
            'docente_id' => $currentDocenteId,
            'bucket' => [
                'type' => $selectedBucket['type'] ?? '',
                'assignment_id' => intval($selectedBucket['assignment_id'] ?? 0),
                'id_docente' => intval($selectedBucket['id_docente'] ?? 0),
                'aula' => trim((string)($selectedBucket['aula'] ?? '')),
                'students' => count($selectedBucket['students'] ?? []),
            ],
        ]);
        return ['ok' => false, 'error' => 'Questo appello NO IRC e assegnato a un altro docente'];
    }

    return [
        'ok' => true,
        'week' => $context['week'],
        'slot' => $slot,
        'bucket' => $selectedBucket,
        'date' => $date,
        'hour' => $hour,
        'aula' => trim((string)($selectedBucket['aula'] ?? $aula)),
        'assignment_id' => intval($selectedBucket['assignment_id'] ?? 0),
        'students' => array_values($selectedBucket['students'] ?? []),
    ];
}

function mastercomNoIrcBucketMatchesDocente(array $bucket, array $slot, int $docenteId): bool
{
    if ($docenteId <= 0) {
        return false;
    }

    if (!empty($bucket['substitution']) && is_array($bucket['substitution'])) {
        $effectiveId = intval($bucket['effective_id_docente'] ?? 0);
        if ($effectiveId === $docenteId) {
            mastercomNoIrcLog('bucket_match_substitution', [
                'docente_id' => $docenteId,
                'bucket_aula' => trim((string)($bucket['aula'] ?? '')),
                'bucket_assignment_id' => intval($bucket['assignment_id'] ?? 0),
                'original_id_docente' => intval($bucket['original_id_docente'] ?? 0),
                'id_sostituzione' => intval($bucket['substitution']['idSostituzione'] ?? 0),
            ]);
            return true;
        }

        mastercomNoIrcLog('bucket_no_match_substitution', [
            'docente_id' => $docenteId,
            'effective_id_docente' => $effectiveId,
            'original_id_docente' => intval($bucket['original_id_docente'] ?? 0),
            'bucket_aula' => trim((string)($bucket['aula'] ?? '')),
            'bucket_assignment_id' => intval($bucket['assignment_id'] ?? 0),
            'id_sostituzione' => intval($bucket['substitution']['idSostituzione'] ?? 0),
        ]);
        return false;
    }

    if (intval($bucket['effective_id_docente'] ?? 0) === $docenteId) {
        mastercomNoIrcLog('bucket_match_effective', [
            'docente_id' => $docenteId,
            'bucket_aula' => trim((string)($bucket['aula'] ?? '')),
            'bucket_assignment_id' => intval($bucket['assignment_id'] ?? 0),
        ]);
        return true;
    }

    if (intval($bucket['id_docente'] ?? 0) === $docenteId) {
        mastercomNoIrcLog('bucket_match_direct', [
            'docente_id' => $docenteId,
            'bucket_aula' => trim((string)($bucket['aula'] ?? '')),
            'bucket_assignment_id' => intval($bucket['assignment_id'] ?? 0),
        ]);
        return true;
    }

    $bucketAula = trim((string)($bucket['aula'] ?? ''));
    foreach ($slot['assignments'] ?? [] as $assignment) {
        if (intval($assignment['id_docente'] ?? 0) !== $docenteId) {
            continue;
        }

        $assignmentAula = trim((string)($assignment['aula'] ?? ''));
        if ($assignmentAula === '' || $bucketAula === '' || $assignmentAula === $bucketAula) {
            mastercomNoIrcLog('bucket_match_assignment', [
                'docente_id' => $docenteId,
                'bucket_aula' => $bucketAula,
                'assignment_id' => intval($assignment['id'] ?? 0),
                'assignment_aula' => $assignmentAula,
                'teacher_name' => trim((string)($assignment['teacher_name'] ?? '')),
            ]);
            return true;
        }
    }

    mastercomNoIrcLog('bucket_no_match', [
        'docente_id' => $docenteId,
        'bucket_aula' => trim((string)($bucket['aula'] ?? '')),
        'bucket_assignment_id' => intval($bucket['assignment_id'] ?? 0),
        'bucket_id_docente' => intval($bucket['id_docente'] ?? 0),
        'assignments' => array_map(function ($assignment) {
            return [
                'id' => intval($assignment['id'] ?? 0),
                'id_docente' => intval($assignment['id_docente'] ?? 0),
                'aula' => trim((string)($assignment['aula'] ?? '')),
                'teacher_name' => trim((string)($assignment['teacher_name'] ?? '')),
            ];
        }, $slot['assignments'] ?? []),
    ]);
    return false;
}

function mastercomNoIrcLoadAppealRows(string $date, string $hour, int $assignmentId, string $aula = ''): array
{
    if (!mastercomAdminTableExists('mastercom_noirc_appelli') || !mastercomAdminTableExists('mastercom_noirc_appello_studenti')) {
        return ['appeal' => null, 'rows' => []];
    }

    $where = [
        'data_giorno = ' . dbQ(mastercomNoIrcNormalizeDate($date)),
        'ora = ' . dbQ(mastercomNoIrcNormalizeHour($hour)),
    ];
    if ($assignmentId > 0) {
        $where[] = 'id_assegnazione = ' . dbI($assignmentId);
    } else {
        $where[] = 'id_assegnazione IS NULL';
    }
    if (mastercomNoIrcAppealAulaColumnExists()) {
        $where[] = trim($aula) === ''
            ? "(aula IS NULL OR aula = '')"
            : 'aula = ' . dbQ($aula);
    }

    $appeal = dbGetFirst("SELECT * FROM mastercom_noirc_appelli WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 1");
    if (!is_array($appeal) || empty($appeal)) {
        return ['appeal' => null, 'rows' => []];
    }

    $rows = dbGetAll("
        SELECT *
        FROM mastercom_noirc_appello_studenti
        WHERE id_appello = " . dbI($appeal['id']) . "
    ") ?: [];

    $map = [];
    foreach ($rows as $row) {
        $studentId = intval($row['mastercom_id_studente'] ?? 0);
        if ($studentId > 0) {
            $map[$studentId] = $row;
        }
    }

    return ['appeal' => $appeal, 'rows' => $map];
}

function mastercomNoIrcSaveAppeal(array $payload, int $createdByUserId, int $currentDocenteId = 0, bool $isAdmin = false): array
{
    if (!mastercomAdminTableExists('mastercom_noirc_appelli') || !mastercomAdminTableExists('mastercom_noirc_appello_studenti')) {
        return ['ok' => false, 'error' => 'Tabelle appelli NO IRC mancanti. Esegui doc/mastercom_noirc_schema.sql'];
    }

    $context = mastercomNoIrcFindRegistroContext(
        (string)($payload['data_giorno'] ?? ''),
        (string)($payload['ora'] ?? ''),
        (string)($payload['aula'] ?? ''),
        $currentDocenteId,
        $isAdmin
    );
    if (empty($context['ok'])) {
        return $context;
    }

    $date = $context['date'];
    $hour = $context['hour'];
    $assignmentId = intval($context['assignment_id'] ?? 0);
    $aula = trim((string)($context['aula'] ?? ''));
    $note = trim((string)($payload['note'] ?? ''));
    $existing = mastercomNoIrcLoadAppealRows($date, $hour, $assignmentId, $aula);
    $appeal = $existing['appeal'];

    if (is_array($appeal) && intval($appeal['id'] ?? 0) > 0) {
        dbExec("
            UPDATE mastercom_noirc_appelli
            SET note = " . dbQ($note) . "
            WHERE id = " . dbI($appeal['id']) . "
        ");
        $appealId = intval($appeal['id']);
    } else {
        $columns = ['data_giorno', 'giorno_settimana', 'ora', 'id_assegnazione', 'created_by_user_id', 'note', 'created_at'];
        $values = [
            dbQ($date),
            dbI(mastercomNoIrcDateWeekday($date)),
            dbQ($hour),
            $assignmentId > 0 ? dbI($assignmentId) : 'NULL',
            $createdByUserId > 0 ? dbI($createdByUserId) : 'NULL',
            dbQ($note),
            'NOW()',
        ];
        if (mastercomNoIrcAppealAulaColumnExists()) {
            $columns[] = 'aula';
            $values[] = dbQ($aula);
        }
        dbExec("
            INSERT INTO mastercom_noirc_appelli (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $appealId = intval(dblastId());
    }

    $allowedStates = ['PRESENTE', 'ASSENTE_MASTERCOM', 'ASSENTE_NOIRC', 'USCITA', 'PERMESSO', 'EVENTO', 'NON_VERIFICATO'];
    $postedStates = is_array($payload['stato'] ?? null) ? $payload['stato'] : [];
    $postedNotes = is_array($payload['studente_note'] ?? null) ? $payload['studente_note'] : [];
    $saved = 0;

    foreach ($context['students'] as $student) {
        $mastercomStudentId = intval($student['mastercom_id_studente'] ?? 0);
        if ($mastercomStudentId <= 0) {
            continue;
        }
        $state = strtoupper(trim((string)($postedStates[$mastercomStudentId] ?? 'PRESENTE')));
        if (!in_array($state, $allowedStates, true)) {
            $state = 'PRESENTE';
        }
        $studentNote = trim((string)($postedNotes[$mastercomStudentId] ?? ''));
        $gestoreStudentId = intval($student['id_studente_gestore'] ?? 0);
        $gestoreStudentSql = $gestoreStudentId > 0 ? dbI($gestoreStudentId) : 'NULL';
        dbExec("
            INSERT INTO mastercom_noirc_appello_studenti
                (id_appello, id_studente_gestore, mastercom_id_studente, stato, note, created_at)
            VALUES
                (" . dbI($appealId) . ", " . $gestoreStudentSql . ", " . dbI($mastercomStudentId) . ", " . dbQ($state) . ", " . dbQ($studentNote) . ", NOW())
            ON DUPLICATE KEY UPDATE
                id_studente_gestore = VALUES(id_studente_gestore),
                stato = VALUES(stato),
                note = VALUES(note)
        ");
        $saved++;
    }

    return ['ok' => true, 'id_appello' => $appealId, 'saved' => $saved];
}

function mastercomNoIrcPresenceClassifyAppealRow(array $appealRow): array
{
    $stato = is_array($appealRow['stato'] ?? null) ? $appealRow['stato'] : [];
    $assenze = is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : [];
    $entrate = is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : [];
    $uscite = is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : [];
    $permessi = is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : [];
    $eventi = is_array($appealRow['eventi'] ?? null) ? $appealRow['eventi'] : [];
    $haLezione = intval($appealRow['ha_lezione'] ?? 0) === 1;

    if (!empty($eventi)) {
        return ['stato' => 'EVENTO', 'label' => 'Evento MasterCom', 'detail' => 'Risulta in evento', 'appeal' => $appealRow];
    }
    if (!empty($permessi)) {
        return ['stato' => 'PERMESSO', 'label' => 'Permesso MasterCom', 'detail' => 'Permesso registrato', 'appeal' => $appealRow];
    }
    if (!empty($uscite)) {
        return ['stato' => 'USCITA', 'label' => 'Uscita MasterCom', 'detail' => 'Uscita registrata', 'appeal' => $appealRow];
    }
    if (!empty($entrate)) {
        return ['stato' => 'ENTRATA_RITARDO', 'label' => 'Entrata in ritardo', 'detail' => 'Entrata in ritardo registrata', 'appeal' => $appealRow];
    }
    if (!empty($stato['assente']) || !empty($assenze)) {
        return ['stato' => 'ASSENTE_MASTERCOM', 'label' => 'Assente MasterCom', 'detail' => 'Assenza gia presente sul registro', 'appeal' => $appealRow];
    }
    if ($haLezione) {
        return ['stato' => 'PRESENTE', 'label' => 'Presente', 'detail' => 'Presente', 'appeal' => $appealRow];
    }

    return ['stato' => 'NON_VERIFICATO', 'label' => 'Da verificare', 'detail' => 'MasterCom non indica lezione corrente', 'appeal' => $appealRow];
}

function mastercomNoIrcPresenceFormatTs(int $timestamp, string $format = 'Y-m-d'): string
{
    if ($timestamp <= 0) {
        return '';
    }

    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format($format);
}

function mastercomNoIrcPresenceExtractEntryTimestamps(array $entry): array
{
    $timestamps = [];
    foreach (['data', 'data_ts', 'start_ts', 'end_ts', 'data_inizio_ts', 'data_fine_ts'] as $key) {
        if (isset($entry[$key]) && is_numeric($entry[$key])) {
            $timestamps[] = intval($entry[$key]);
        }
    }

    foreach (['data_inizio', 'data_fine', 'inizio', 'fine', 'date'] as $key) {
        $value = trim((string)($entry[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            $timestamps[] = intval($timestamp);
        }
    }

    return $timestamps;
}

function mastercomNoIrcPresenceEntryIsForDate(array $entry, string $date): bool
{
    $timestamps = mastercomNoIrcPresenceExtractEntryTimestamps($entry);
    if (empty($timestamps)) {
        return false;
    }

    foreach ($timestamps as $timestamp) {
        if (mastercomNoIrcPresenceFormatTs($timestamp, 'Y-m-d') === $date) {
            return true;
        }
    }

    return false;
}

function mastercomNoIrcPresenceNormalizeEntries(array $entries, string $date): array
{
    $normalized = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (!mastercomNoIrcPresenceEntryIsForDate($entry, $date)) {
            continue;
        }
        $normalized[] = $entry;
    }

    return $normalized;
}

function mastercomNoIrcPresenceMergeUniqueEntries(array $baseEntries, array $extraEntries): array
{
    $merged = [];
    $seen = [];

    foreach (array_merge($baseEntries, $extraEntries) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        if (isset($entry['id_assenza']) && intval($entry['id_assenza']) > 0) {
            $key = 'id:' . intval($entry['id_assenza']);
        } else {
            $key = 'k:' . implode('|', [
                trim((string)($entry['codice'] ?? '')),
                trim((string)($entry['descrizione'] ?? '')),
                trim((string)($entry['orario'] ?? '')),
                trim((string)($entry['data'] ?? '')),
            ]);
        }

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $merged[] = $entry;
    }

    return $merged;
}

function mastercomNoIrcPresenceNormalizeAppealRow(array $appealRow, string $date): array
{
    $appealRow['entrate'] = mastercomNoIrcPresenceNormalizeEntries(is_array($appealRow['entrate'] ?? null) ? $appealRow['entrate'] : [], $date);
    $appealRow['uscite'] = mastercomNoIrcPresenceNormalizeEntries(is_array($appealRow['uscite'] ?? null) ? $appealRow['uscite'] : [], $date);
    $appealRow['permessi'] = mastercomNoIrcPresenceNormalizeEntries(is_array($appealRow['permessi'] ?? null) ? $appealRow['permessi'] : [], $date);
    $appealRow['eventi'] = mastercomNoIrcPresenceNormalizeEntries(is_array($appealRow['eventi'] ?? null) ? $appealRow['eventi'] : [], $date);
    $appealRow['assenze'] = mastercomNoIrcPresenceNormalizeEntries(is_array($appealRow['assenze'] ?? null) ? $appealRow['assenze'] : [], $date);

    $stato = is_array($appealRow['stato'] ?? null) ? $appealRow['stato'] : [];
    if (!empty($stato['assente']) && is_array($stato['ultimo'] ?? null) && mastercomNoIrcPresenceEntryIsForDate($stato['ultimo'], $date)) {
        $appealRow['assenze'] = mastercomNoIrcPresenceMergeUniqueEntries($appealRow['assenze'], [$stato['ultimo']]);
    } elseif (!empty($stato['assente'])) {
        $appealRow['stato']['assente'] = false;
    }

    if (!empty($appealRow['entrate']) || !empty($appealRow['eventi'])) {
        $appealRow['assenze'] = [];
        if (is_array($appealRow['stato'] ?? null)) {
            $appealRow['stato']['assente'] = false;
        }
    }

    return $appealRow;
}

function mastercomNoIrcSlotStartTs(string $date, string $hour): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', mastercomNoIrcNormalizeDate($date) . ' ' . mastercomNoIrcNormalizeHour($hour) . ':00', new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomNoIrcSlotEndTs(string $date, string $hour): int
{
    $hours = mastercomNoIrcOrari();
    $hour = mastercomNoIrcNormalizeHour($hour);
    $index = array_search($hour, $hours, true);
    if ($index !== false && isset($hours[$index + 1])) {
        return mastercomNoIrcSlotStartTs($date, $hours[$index + 1]);
    }

    $startTs = mastercomNoIrcSlotStartTs($date, $hour);
    return $startTs > 0 ? $startTs + 50 * 60 : 0;
}

function mastercomNoIrcSlotIsCurrent(string $date, string $hour): bool
{
    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
    $nowTs = $now->getTimestamp();
    $slotStart = mastercomNoIrcSlotStartTs($date, $hour);
    $slotEnd = mastercomNoIrcSlotEndTs($date, $hour);
    return $slotStart > 0 && $slotEnd > 0 && $nowTs >= $slotStart && $nowTs < $slotEnd;
}

function mastercomNoIrcEventOverlapsSlot(int $eventStart, int $eventEnd, int $slotStart, int $slotEnd): bool
{
    if ($eventStart <= 0 || $eventEnd <= 0 || $slotStart <= 0 || $slotEnd <= 0) {
        return false;
    }

    return $eventStart < $slotEnd && $eventEnd > $slotStart;
}

function mastercomNoIrcBuildCalendarDebugMap(array $calendarResponse): array
{
    $map = [];
    $debug = $calendarResponse['debug_code'] ?? [];
    if (!is_array($debug)) {
        return $map;
    }

    foreach ($debug as $group) {
        if (!is_array($group)) {
            continue;
        }
        foreach ($group as $eventId => $eventData) {
            if (is_array($eventData)) {
                $map[intval($eventId)] = $eventData;
            }
        }
    }

    return $map;
}

function mastercomNoIrcBuildCalendarEventEntry(array $note, array $eventDebug): array
{
    return [
        'id_annotazione_agenda' => intval($note['id_annotazione_agenda'] ?? $eventDebug['id_evento'] ?? 0),
        'titolo' => trim((string)(mastercomAdminCleanText($note['titolo'] ?? '') ?: mastercomAdminCleanText($eventDebug['nome'] ?? '') ?: 'Evento agenda classe')),
        'descrizione' => trim((string)(mastercomAdminCleanText($note['testo'] ?? '') ?: mastercomAdminCleanText($eventDebug['descrizione'] ?? '') ?: '')),
        'data_inizio_ts' => intval($note['data_inizio'] ?? $eventDebug['data_inizio'] ?? 0),
        'data_fine_ts' => intval($note['data_fine'] ?? $eventDebug['data_fine'] ?? 0),
    ];
}

function mastercomNoIrcLoadCalendarEventsMap(array $students, string $date, string $hour, array $authResult): array
{
    $slotStart = mastercomNoIrcSlotStartTs($date, $hour);
    $slotEnd = mastercomNoIrcSlotEndTs($date, $hour);
    if ($slotStart <= 0 || $slotEnd <= 0) {
        return [];
    }

    $studentsByClass = [];
    foreach ($students as $student) {
        $classId = intval($student['mastercom_id_classe_corrente'] ?? 0);
        $studentId = intval($student['mastercom_id_studente'] ?? 0);
        if ($classId <= 0 || $studentId <= 0) {
            continue;
        }
        $studentsByClass[$classId][$studentId] = true;
    }

    $map = [];
    foreach ($studentsByClass as $classId => $classStudentIds) {
        $calendarResult = mastercomLoadCalendarNotes($authResult, intval($classId), $slotStart, $slotEnd, [
            'method' => 'POST',
            'timeout' => 120,
        ]);
        if (empty($calendarResult['ok']) || !is_array($calendarResult['response'] ?? null)) {
            continue;
        }

        $debugMap = mastercomNoIrcBuildCalendarDebugMap($calendarResult['response']);
        $notes = is_array($calendarResult['response']['result'] ?? null) ? $calendarResult['response']['result'] : [];
        foreach ($notes as $note) {
            if (!is_array($note)) {
                continue;
            }

            $noteId = intval($note['id_annotazione_agenda'] ?? 0);
            $eventDebug = $debugMap[$noteId] ?? [];
            $isEvent = intval($note['evento'] ?? $eventDebug['evento'] ?? 0) === 1
                || intval($eventDebug['id_evento'] ?? 0) > 0
                || isset($eventDebug['partecipanti']);
            if (!$isEvent) {
                continue;
            }

            $eventStart = intval($note['data_inizio'] ?? $eventDebug['data_inizio'] ?? 0);
            $eventEnd = intval($note['data_fine'] ?? $eventDebug['data_fine'] ?? 0);
            if (!mastercomNoIrcEventOverlapsSlot($eventStart, $eventEnd, $slotStart, $slotEnd)) {
                continue;
            }

            $eventEntry = mastercomNoIrcBuildCalendarEventEntry($note, $eventDebug);
            $participants = is_array($eventDebug['partecipanti'] ?? null) ? $eventDebug['partecipanti'] : [];
            if (empty($participants)) {
                foreach (array_keys($classStudentIds) as $studentId) {
                    $map[$studentId][] = $eventEntry;
                }
                continue;
            }

            foreach (array_keys($participants) as $participantId) {
                $participantId = intval($participantId);
                if ($participantId > 0 && isset($classStudentIds[$participantId])) {
                    $map[$participantId][] = $eventEntry;
                }
            }
        }
    }

    return $map;
}

function mastercomNoIrcLoadPresenceMap(array $students, string $date, string $hour = ''): array
{
    $date = mastercomNoIrcNormalizeDate($date);
    $today = mastercomNoIrcCurrentDate();
    $map = [];
    foreach ($students as $student) {
        $studentId = intval($student['mastercom_id_studente'] ?? 0);
        if ($studentId > 0) {
            $map[$studentId] = [
                'stato' => $date === $today ? 'NON_VERIFICATO' : 'NON_VERIFICATO',
                'label' => $date === $today ? 'Da verificare' : 'Non disponibile',
                'detail' => $date === $today ? 'Snapshot MasterCom non ancora caricato' : 'Lo snapshot MasterCom e disponibile solo per la giornata corrente',
            ];
        }
    }

    if ($date !== $today || empty($students)) {
        return ['ok' => $date === $today, 'map' => $map, 'error' => $date === $today ? '' : 'Snapshot MasterCom solo per oggi'];
    }

    $classIds = [];
    foreach ($students as $student) {
        $classId = intval($student['mastercom_id_classe_corrente'] ?? 0);
        if ($classId > 0) {
            $classIds[$classId] = true;
        }
    }
    if (empty($classIds)) {
        return ['ok' => false, 'map' => $map, 'error' => 'Classi MasterCom non disponibili nella mirror studenti'];
    }

    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComDocenteAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (empty($authResult['ok'])) {
        return ['ok' => false, 'map' => $map, 'error' => 'Autenticazione MasterCom docente fallita'];
    }

    $calendarEventsMap = $hour !== '' ? mastercomNoIrcLoadCalendarEventsMap($students, $date, $hour, $authResult) : [];

    foreach (array_keys($classIds) as $classId) {
        $appealResult = mastercomLoadAppealData($authResult, intval($classId), [
            'method' => 'POST',
            'timeout' => 120,
        ]);
        if (empty($appealResult['ok']) || !is_array($appealResult['response']['result'] ?? null)) {
            continue;
        }
        foreach ($appealResult['response']['result'] as $studentId => $appealRow) {
            $studentId = intval($studentId);
            if ($studentId <= 0 || !isset($map[$studentId]) || !is_array($appealRow)) {
                continue;
            }
            $normalizedAppealRow = mastercomNoIrcPresenceNormalizeAppealRow($appealRow, $date);
            if (!empty($calendarEventsMap[$studentId])) {
                $normalizedAppealRow['eventi'] = mastercomNoIrcPresenceMergeUniqueEntries(
                    is_array($normalizedAppealRow['eventi'] ?? null) ? $normalizedAppealRow['eventi'] : [],
                    $calendarEventsMap[$studentId]
                );
                $normalizedAppealRow = mastercomNoIrcPresenceNormalizeAppealRow($normalizedAppealRow, $date);
            }
            $map[$studentId] = mastercomNoIrcPresenceClassifyAppealRow($normalizedAppealRow);
        }
    }

    return ['ok' => true, 'map' => $map, 'error' => ''];
}

function mastercomNoIrcDateParts(string $date): array
{
    $dt = DateTime::createFromFormat('Y-m-d', mastercomNoIrcNormalizeDate($date), new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        $dt = new DateTime('now', new DateTimeZone('Europe/Rome'));
    }

    return [
        'Date_Day' => (string)intval($dt->format('d')),
        'Date_Month' => $dt->format('m'),
        'Date_Year' => $dt->format('Y'),
    ];
}

function mastercomNoIrcTimeParts(string $time): array
{
    $time = mastercomNoIrcNormalizeHour($time);
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
        $time = '07:50';
        preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches);
    }

    return [
        'Time_Hour' => str_pad((string)max(0, min(23, intval($matches[1] ?? 0))), 2, '0', STR_PAD_LEFT),
        'Time_Minute' => str_pad((string)max(0, min(59, intval($matches[2] ?? 0))), 2, '0', STR_PAD_LEFT),
    ];
}

function mastercomNoIrcAbsenceTypeLabels(): array
{
    return [
        1 => 'Assenza Giornaliera',
        2 => 'Entrata in Ritardo al mattino',
        8 => 'Entrata in Ritardo al pomeriggio',
        3 => 'Uscita in Anticipo al mattino',
        9 => 'Uscita in Anticipo al pomeriggio',
        7 => 'Assenza solo al Pomeriggio',
    ];
}

function mastercomNoIrcIsAfternoonHour(string $hour): bool
{
    return mastercomNoIrcNormalizeHour($hour) >= '13:00';
}

function mastercomNoIrcIsFirstAppealHour(string $hour): bool
{
    $hour = mastercomNoIrcNormalizeHour($hour);
    return in_array($hour, ['07:50', '13:00', '13:50'], true);
}

function mastercomNoIrcFindRelevantAbsence(array $presenceRow, array $types = []): ?array
{
    $appeal = is_array($presenceRow['appeal'] ?? null) ? $presenceRow['appeal'] : [];
    $absences = is_array($appeal['assenze'] ?? null) ? $appeal['assenze'] : [];
    if (empty($absences)) {
        return null;
    }

    foreach ($absences as $absence) {
        if (!is_array($absence)) {
            continue;
        }
        $tipo = intval($absence['tipo'] ?? $absence['tipo_assenza'] ?? 0);
        if (empty($types) || in_array($tipo, $types, true)) {
            return $absence;
        }
    }

    return is_array($absences[0] ?? null) ? $absences[0] : null;
}

function mastercomNoIrcAbsenceId(array $absence): int
{
    return intval($absence['id_assenza'] ?? $absence['id'] ?? 0);
}

function mastercomNoIrcAbsenceDateTs(array $absence): int
{
    foreach (['data', 'data_ts', 'data_assenza', 'data_inizio_ts'] as $key) {
        if (isset($absence[$key]) && is_numeric($absence[$key])) {
            return intval($absence[$key]);
        }
    }
    return 0;
}

function mastercomNoIrcStudentClassName(array $student): string
{
    $classId = intval($student['mastercom_id_classe_corrente'] ?? 0);
    if ($classId <= 0 || !mastercomAdminTableExists('mastercom_classi')) {
        return trim((string)($student['classe'] ?? ''));
    }

    return trim((string)(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . dbI($classId) . " LIMIT 1") ?? $student['classe'] ?? ''));
}

function mastercomNoIrcBuildAdminAbsencePayload(array $student, string $date, string $time, int $type, string $secondaryState, array $extra = []): array
{
    return array_merge([
        'tipo_assenza' => $type,
        'tipo_giustificazione' => intval($extra['tipo_giustificazione'] ?? 0),
        'esclusione_calcolo_monteore' => intval($extra['esclusione_calcolo_monteore'] ?? 0),
        'motivazione' => trim((string)($extra['motivazione'] ?? 'Inserimento da appello NO IRC')),
        'x' => (string)($extra['x'] ?? '17'),
        'y' => (string)($extra['y'] ?? '20'),
        'form_stato' => 'amministratore',
        'stato_principale' => 'assenze_principale',
        'stato_secondario' => $secondaryState,
        'stampa_rapportino' => 'SI',
        'id_classe' => intval($student['mastercom_id_classe_corrente'] ?? 0),
        'classe' => mastercomNoIrcStudentClassName($student),
        'indirizzo' => '',
        'id_indirizzo' => '',
        'id_stud' => intval($student['mastercom_id_studente'] ?? 0),
        'cognome_stud' => trim((string)($student['cognome'] ?? '')),
        'nome_stud' => trim((string)($student['nome'] ?? '')),
        'operazione_sorgente' => '',
        'parametro_nome' => '',
        'parametro_cognome' => '',
        'parametro_indirizzo_abitazione' => '',
        'parametro_luogo_nascita' => '',
        'parametro_citta' => '',
        'parametro_provincia' => '',
        'parametro_sesso' => '',
        'parametro_tel_cell' => '',
        'parametro_email' => '',
        'parametro_matricola' => '',
        'parametro_cap' => '',
        'parametro_nome_genitore' => '',
        'parametro_cognome_genitore' => '',
        'inserimento_diretto' => '',
    ], mastercomNoIrcTimeParts($time), mastercomNoIrcDateParts($date), $extra);
}

function mastercomNoIrcPlanMastercomAction(array $student, array $presenceRow, string $noircState, string $date, string $hour): array
{
    $hour = mastercomNoIrcNormalizeHour($hour);
    $noircState = strtoupper(trim($noircState));
    $isNoircAbsent = in_array($noircState, ['ASSENTE_NOIRC', 'ASSENTE_MASTERCOM'], true);
    $isNoircPresent = $noircState === 'PRESENTE';
    $mcState = strtoupper(trim((string)($presenceRow['stato'] ?? 'NON_VERIFICATO')));
    $isMcAbsent = $mcState === 'ASSENTE_MASTERCOM';
    $isAfternoon = mastercomNoIrcIsAfternoonHour($hour);
    $isFirstMorning = $hour === '07:50';
    $isFirstAfternoon = in_array($hour, ['13:00', '13:50'], true);
    $typeLabels = mastercomNoIrcAbsenceTypeLabels();

    if ($mcState === 'EVENTO') {
        return ['kind' => 'none', 'summary' => 'Nessuna azione: lo studente risulta coperto da un evento in agenda classe MasterCom.', 'payload' => null];
    }

    if ($mcState === 'USCITA') {
        return ['kind' => 'none', 'summary' => 'Nessuna azione: uscita gia registrata su MasterCom.', 'payload' => null];
    }

    if (!$isNoircAbsent && !$isNoircPresent) {
        return ['kind' => 'none', 'summary' => 'Nessuna azione: stato NO IRC non operativo per MasterCom.', 'payload' => null];
    }

    if ($isFirstMorning && $isNoircAbsent) {
        if ($isMcAbsent) {
            return ['kind' => 'none', 'summary' => 'Nessuna azione: assenza gia presente su MasterCom.', 'payload' => null];
        }
        $type = 1;
        return [
            'kind' => 'create',
            'summary' => 'Inserira su MasterCom una Assenza Giornaliera per la prima ora del mattino.',
            'payload' => mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'inserisci_assenze_studente_update'),
            'type_label' => $typeLabels[$type],
        ];
    }

    if ($isFirstAfternoon && $isNoircAbsent) {
        $dailyAbsence = mastercomNoIrcFindRelevantAbsence($presenceRow, [1]);
        if ($dailyAbsence !== null) {
            return ['kind' => 'none', 'summary' => 'Nessuna azione: lo studente risulta gia assente per l’intera giornata.', 'payload' => null];
        }
        if ($isMcAbsent) {
            return ['kind' => 'none', 'summary' => 'Nessuna azione: risulta gia una assenza su MasterCom.', 'payload' => null];
        }
        $type = 7;
        return [
            'kind' => 'create',
            'summary' => 'Inserira su MasterCom una Assenza solo al Pomeriggio.',
            'payload' => mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'inserisci_assenze_studente_update'),
            'type_label' => $typeLabels[$type],
        ];
    }

    if (!$isFirstMorning && !$isFirstAfternoon && $isMcAbsent && $isNoircAbsent) {
        return ['kind' => 'none', 'summary' => 'Nessuna azione: lo studente era gia assente da appello MasterCom e resta assente.', 'payload' => null];
    }

    if ($isMcAbsent && $isNoircPresent) {
        $absence = mastercomNoIrcFindRelevantAbsence($presenceRow);
        $absenceId = $absence !== null ? mastercomNoIrcAbsenceId($absence) : 0;
        $absenceDate = $absence !== null ? mastercomNoIrcAbsenceDateTs($absence) : 0;
        if ($absenceId <= 0 || $absenceDate <= 0) {
            return ['kind' => 'none', 'summary' => 'Azione non disponibile: non trovo id/data dell’assenza MasterCom da modificare.', 'payload' => null];
        }
        $type = $isAfternoon ? 8 : 2;
        $payload = mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'modifica_assenze_studente_update', [
            'x' => '18',
            'y' => '13',
            'id_assenza' => $absenceId,
            'data_assenza' => $absenceDate,
        ]);
        return [
            'kind' => 'edit',
            'summary' => 'Modifichera l’assenza esistente in ' . $typeLabels[$type] . ' con orario ' . $hour . '.',
            'payload' => $payload,
            'type_label' => $typeLabels[$type],
        ];
    }

    if (!$isMcAbsent && $isNoircAbsent) {
        $type = $isAfternoon ? 9 : 3;
        return [
            'kind' => 'create',
            'summary' => 'Inserira su MasterCom una ' . $typeLabels[$type] . ' non giustificata con orario ' . $hour . '.',
            'payload' => mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'inserisci_assenze_studente_update'),
            'type_label' => $typeLabels[$type],
        ];
    }

    return ['kind' => 'none', 'summary' => 'Nessuna azione necessaria per MasterCom.', 'payload' => null];
}

function mastercomNoIrcExecuteMastercomAction(array $plan): array
{
    if (empty($plan['payload']) || !is_array($plan['payload'] ?? null) || ($plan['kind'] ?? 'none') === 'none') {
        return ['ok' => false, 'error' => 'Nessuna azione MasterCom da eseguire'];
    }

    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (empty($authResult['ok'])) {
        return ['ok' => false, 'error' => 'Autenticazione MasterCom amministratore fallita'];
    }

    $submitResult = mastercomSubmitAdminAbsenceAction($authResult, $plan['payload'], [
        'method' => 'POST',
        'timeout' => 120,
        'send_in_body' => ($plan['kind'] ?? '') === 'edit',
    ]);

    if (empty($submitResult['ok'])) {
        return ['ok' => false, 'error' => 'Invio azione MasterCom fallito: ' . trim((string)($submitResult['error'] ?? 'SUBMIT_FAILED'))];
    }

    return ['ok' => true, 'message' => 'Azione inviata a MasterCom'];
}

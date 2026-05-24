<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/connectMBApp.php';

function docenteInsegnaMbappSyncBool($value, bool $default = false): bool
{
    if ($value === null || $value === '') {
        return $default;
    }

    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'si', 'sì'], true);
}

function docenteInsegnaMbappCurrentWeekRange(): array
{
    $tz = new DateTimeZone('Europe/Rome');
    $today = new DateTime('today', $tz);
    $monday = clone $today;
    $monday->modify('monday this week');
    $sunday = clone $monday;
    $sunday->modify('+6 days');

    return [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];
}

function docenteInsegnaMbappLastWeekRange(): array
{
    $tz = new DateTimeZone('Europe/Rome');
    $to = new DateTime('today', $tz);
    $from = clone $to;
    $from->modify('-6 days');

    return [$from->format('Y-m-d'), $to->format('Y-m-d')];
}

function docenteInsegnaMbappIsoDate($value): bool
{
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value);
}

function docenteInsegnaMbappCurrentAnnoId(): int
{
    global $__anno_scolastico_corrente_id;

    if (isset($__anno_scolastico_corrente_id) && intval($__anno_scolastico_corrente_id) > 0) {
        return intval($__anno_scolastico_corrente_id);
    }

    return intval(dbGetValue('SELECT anno_scolastico_id FROM anno_scolastico_corrente LIMIT 1'));
}

function docenteInsegnaMbappEsc($value): string
{
    global $__conMBApp;
    return mysqli_real_escape_string($__conMBApp, (string)$value);
}

function docenteInsegnaMbappTableExists(string $table): bool
{
    $tableEsc = docenteInsegnaMbappEsc($table);
    return mb_dbGetFirst("
        SELECT 1 AS ok
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$tableEsc'
        LIMIT 1
    ") !== null;
}

function docenteInsegnaMbappColumnExists(string $table, string $column): bool
{
    $tableEsc = docenteInsegnaMbappEsc($table);
    $columnEsc = docenteInsegnaMbappEsc($column);

    return mb_dbGetFirst("
        SELECT 1 AS ok
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$tableEsc'
          AND COLUMN_NAME = '$columnEsc'
        LIMIT 1
    ") !== null;
}

function docenteInsegnaMbappPickDateSource(): array
{
    $dateColumns = [
        'data',
        'dataGiorno',
        'dataLezione',
        'data_lezione',
        'dataOra',
        'dataInizio',
        'inizio',
    ];

    if (docenteInsegnaMbappTableExists('calendario') && docenteInsegnaMbappColumnExists('calendario', 'idCalendario')) {
        foreach ($dateColumns as $column) {
            if (docenteInsegnaMbappColumnExists('calendario', $column)) {
                return [
                    'table' => 'calendario',
                    'column' => $column,
                    'join' => 'JOIN calendario cal ON cal.idCalendario = o.idCalendario',
                    'expr' => "cal.`$column`",
                ];
            }
        }
    }

    foreach ($dateColumns as $column) {
        if (docenteInsegnaMbappColumnExists('oralezione', $column)) {
            return [
                'table' => 'oralezione',
                'column' => $column,
                'join' => '',
                'expr' => "o.`$column`",
            ];
        }
    }

    throw new Exception('Non trovo una colonna data in MBApp per filtrare le ore di lezione.');
}

function docenteInsegnaMbappDefaultExcludedClasses(): array
{
    return [
        '',
        'UDIENZE',
        'NOIRC',
        'SORVNOIRC',
        'LABMAT',
        'L2',
        'PRANZO',
        'ROBOTI',
        'SOSTEGNO',
    ];
}

function docenteInsegnaMbappExists(int $idDocente, int $idClasse, int $idMateria, int $idAnno): bool
{
    return dbGetFirst("
        SELECT id
        FROM docente_insegna
        WHERE id_docente = " . dbI($idDocente) . "
          AND id_classe = " . dbI($idClasse) . "
          AND id_materia = " . dbI($idMateria) . "
          AND id_anno_scolastico = " . dbI($idAnno) . "
        LIMIT 1
    ") !== null;
}

function docenteInsegnaMbappInsert(int $idDocente, int $idClasse, int $idMateria, int $idAnno): void
{
    dbExec("
        INSERT INTO docente_insegna (id_docente, id_classe, id_materia, id_anno_scolastico)
        VALUES (" . dbI($idDocente) . ", " . dbI($idClasse) . ", " . dbI($idMateria) . ", " . dbI($idAnno) . ")
    ");
}

function docenteInsegnaMbappDelete(int $idDocente, int $idClasse, int $idMateria, int $idAnno): void
{
    dbExec("
        DELETE FROM docente_insegna
        WHERE id_docente = " . dbI($idDocente) . "
          AND id_classe = " . dbI($idClasse) . "
          AND id_materia = " . dbI($idMateria) . "
          AND id_anno_scolastico = " . dbI($idAnno) . "
        LIMIT 1
    ");
}

function docenteInsegnaMbappDebugRows(array $dateSource, string $from, string $to): array
{
    $fromEsc = docenteInsegnaMbappEsc($from);
    $toEsc = docenteInsegnaMbappEsc($to);
    $joinCal = $dateSource['join'];
    $dateExpr = $dateSource['expr'];

    $queries = [
        'count_senza_filtro_data' => "
            SELECT COUNT(*) AS n
            FROM (
                SELECT DISTINCT u.username, c.classe, o.siglaMateria
                FROM utente u
                JOIN utilizza utz ON u.username = utz.username
                JOIN oralezione o ON utz.idCalendario = o.idCalendario
                $joinCal
                JOIN occupa oc ON o.idCalendario = oc.idCalendario
                JOIN classe c ON oc.classe = c.classe
                WHERE (u.tipo = 'Admin' OR u.tipo = 'Docente')
                  AND u.username NOT LIKE 'test%'
                  AND u.username <> '.'
                  AND u.username <> '.alternativa'
                  AND c.classe IS NOT NULL
                  AND c.classe <> ''
                  AND o.siglaMateria IS NOT NULL
                  AND o.siglaMateria <> ''
            ) x
        ",
        'count_con_filtro_data' => "
            SELECT COUNT(*) AS n
            FROM (
                SELECT DISTINCT u.username, c.classe, o.siglaMateria
                FROM utente u
                JOIN utilizza utz ON u.username = utz.username
                JOIN oralezione o ON utz.idCalendario = o.idCalendario
                $joinCal
                JOIN occupa oc ON o.idCalendario = oc.idCalendario
                JOIN classe c ON oc.classe = c.classe
                WHERE (u.tipo = 'Admin' OR u.tipo = 'Docente')
                  AND u.username NOT LIKE 'test%'
                  AND u.username <> '.'
                  AND u.username <> '.alternativa'
                  AND c.classe IS NOT NULL
                  AND c.classe <> ''
                  AND o.siglaMateria IS NOT NULL
                  AND o.siglaMateria <> ''
                  AND DATE($dateExpr) BETWEEN '$fromEsc' AND '$toEsc'
            ) x
        ",
        'range_date' => "
            SELECT MIN(DATE($dateExpr)) AS min_data, MAX(DATE($dateExpr)) AS max_data
            FROM oralezione o
            $joinCal
            WHERE $dateExpr IS NOT NULL
        ",
    ];

    $rows = [];
    foreach ($queries as $key => $sql) {
        $rows[$key] = mb_dbGetFirst($sql) ?: [];
    }

    return $rows;
}

function docenteInsegnaMbappSync(array $options = []): array
{
    $idAnno = intval($options['id_anno_scolastico'] ?? docenteInsegnaMbappCurrentAnnoId());
    $apply = !empty($options['apply']);
    $removeObsolete = docenteInsegnaMbappSyncBool($options['rimuovi_obsoleti'] ?? null, true);
    $ignoreErrors = docenteInsegnaMbappSyncBool($options['ignora_incongruenze'] ?? null, false);
    $preserveOnEmpty = docenteInsegnaMbappSyncBool($options['preserva_se_vuoto'] ?? null, true);
    $excludedClasses = $options['classi_da_escludere'] ?? docenteInsegnaMbappDefaultExcludedClasses();

    $from = trim((string)($options['from'] ?? ''));
    $to = trim((string)($options['to'] ?? ''));
    if ($from === '' || $to === '') {
        [$from, $to] = docenteInsegnaMbappCurrentWeekRange();
    }

    if ($idAnno <= 0) {
        throw new Exception('Anno scolastico corrente non disponibile.');
    }
    if (!docenteInsegnaMbappIsoDate($from) || !docenteInsegnaMbappIsoDate($to) || $to < $from) {
        throw new Exception('Intervallo date non valido. Usa formato YYYY-MM-DD.');
    }

    $dateSource = docenteInsegnaMbappPickDateSource();
    $dateExpr = $dateSource['expr'];
    $joinCalendario = $dateSource['join'];
    $fromEsc = docenteInsegnaMbappEsc($from);
    $toEsc = docenteInsegnaMbappEsc($to);

    $sqlMbapp = "
        SELECT DISTINCT
            u.username,
            c.classe AS classe,
            o.siglaMateria AS sigla_materia,
            MIN(DATE($dateExpr)) AS prima_data_periodo,
            MAX(DATE($dateExpr)) AS ultima_data_periodo
        FROM utente u
        JOIN utilizza utz
            ON u.username = utz.username
        JOIN oralezione o
            ON utz.idCalendario = o.idCalendario
        $joinCalendario
        JOIN occupa oc
            ON o.idCalendario = oc.idCalendario
        JOIN classe c
            ON oc.classe = c.classe
        WHERE (u.tipo = 'Admin' OR u.tipo = 'Docente')
          AND u.username NOT LIKE 'test%'
          AND u.username <> '.'
          AND u.username <> '.alternativa'
          AND c.classe IS NOT NULL
          AND c.classe <> ''
          AND o.siglaMateria IS NOT NULL
          AND o.siglaMateria <> ''
          AND DATE($dateExpr) BETWEEN '$fromEsc' AND '$toEsc'
        GROUP BY u.username, c.classe, o.siglaMateria
        ORDER BY u.username, c.classe, o.siglaMateria
    ";

    $rawRows = mb_dbGetAll($sqlMbapp) ?: [];
    $debugRows = docenteInsegnaMbappDebugRows($dateSource, $from, $to);
    $mbappRows = [];
    $rawKeys = [];
    $desiredIdKeys = [];
    $toInsert = [];
    $alreadyPresent = [];
    $toRemove = [];
    $errors = [];

    foreach ($rawRows as $row) {
        $username = trim((string)($row['username'] ?? ''));
        $classe = trim((string)($row['classe'] ?? ''));
        $materia = trim((string)($row['sigla_materia'] ?? ''));

        if ($username === '' || $classe === '' || $materia === '') {
            continue;
        }
        if (in_array($classe, $excludedClasses, true)) {
            continue;
        }

        $rawKey = strtolower($username . '|' . $classe . '|' . $materia);
        if (isset($rawKeys[$rawKey])) {
            continue;
        }
        $rawKeys[$rawKey] = true;

        $mbappRows[] = [
            'username' => $username,
            'classe' => $classe,
            'materia' => $materia,
            'prima_data_periodo' => $row['prima_data_periodo'] ?? '',
            'ultima_data_periodo' => $row['ultima_data_periodo'] ?? '',
        ];
    }

    if (empty($mbappRows) && $preserveOnEmpty) {
        return [
            'ok' => true,
            'skipped' => true,
            'skip_reason' => 'Nessuna ora MBApp nel periodo: la tabella docente_insegna non e stata modificata.',
            'apply' => $apply,
            'ignore_errors' => $ignoreErrors,
            'from' => $from,
            'to' => $to,
            'id_anno_scolastico' => $idAnno,
            'date_column' => $dateSource['table'] . '.' . $dateSource['column'],
            'debug' => $debugRows,
            'mbapp_rows' => [],
            'already_present' => [],
            'to_insert' => [],
            'to_remove' => [],
            'errors' => [],
        ];
    }

    foreach ($mbappRows as $row) {
        $docente = dbGetFirst("
            SELECT id, username
            FROM docente
            WHERE username = " . dbQ($row['username']) . "
            LIMIT 1
        ");
        if (!$docente) {
            $errors[] = [
                'tipo' => 'DOCENTE_NON_TROVATO',
                'username' => $row['username'],
                'classe' => $row['classe'],
                'materia' => $row['materia'],
            ];
            continue;
        }

        $classeGestore = dbGetFirst("
            SELECT id, classe
            FROM classi
            WHERE classe = " . dbQ($row['classe']) . "
              AND attiva = 1
            LIMIT 1
        ");
        if (!$classeGestore) {
            $errors[] = [
                'tipo' => 'CLASSE_NON_TROVATA',
                'username' => $row['username'],
                'classe' => $row['classe'],
                'materia' => $row['materia'],
            ];
            continue;
        }

        $materiaGestore = dbGetFirst("
            SELECT id, codice
            FROM materia
            WHERE codice = " . dbQ($row['materia']) . "
            LIMIT 1
        ");
        if (!$materiaGestore) {
            $errors[] = [
                'tipo' => 'MATERIA_NON_TROVATA',
                'username' => $row['username'],
                'classe' => $row['classe'],
                'materia' => $row['materia'],
            ];
            continue;
        }

        $item = [
            'username' => $row['username'],
            'classe' => $row['classe'],
            'materia' => $row['materia'],
            'id_docente' => intval($docente['id']),
            'id_classe' => intval($classeGestore['id']),
            'id_materia' => intval($materiaGestore['id']),
        ];
        $idKey = $item['id_docente'] . '|' . $item['id_classe'] . '|' . $item['id_materia'];
        $desiredIdKeys[$idKey] = true;

        if (docenteInsegnaMbappExists($item['id_docente'], $item['id_classe'], $item['id_materia'], $idAnno)) {
            $alreadyPresent[] = $item;
            continue;
        }

        $toInsert[] = $item;
        if ($apply) {
            docenteInsegnaMbappInsert($item['id_docente'], $item['id_classe'], $item['id_materia'], $idAnno);
        }
    }

    if ($removeObsolete && !empty($mbappRows)) {
        $pairs = [];
        foreach ($mbappRows as $row) {
            $classeGestore = dbGetFirst("
                SELECT id
                FROM classi
                WHERE classe = " . dbQ($row['classe']) . "
                  AND attiva = 1
                LIMIT 1
            ");
            $materiaGestore = dbGetFirst("
                SELECT id
                FROM materia
                WHERE codice = " . dbQ($row['materia']) . "
                LIMIT 1
            ");
            if ($classeGestore && $materiaGestore) {
                $pairs[intval($classeGestore['id']) . '|' . intval($materiaGestore['id'])] = [
                    'id_classe' => intval($classeGestore['id']),
                    'id_materia' => intval($materiaGestore['id']),
                ];
            }
        }

        foreach ($pairs as $pair) {
            $gestoreRows = dbGetAll("
                SELECT
                    di.id_docente,
                    di.id_classe,
                    di.id_materia,
                    d.username,
                    c.classe,
                    m.codice AS materia
                FROM docente_insegna di
                JOIN docente d ON d.id = di.id_docente
                JOIN classi c ON c.id = di.id_classe
                JOIN materia m ON m.id = di.id_materia
                WHERE di.id_anno_scolastico = " . dbI($idAnno) . "
                  AND di.id_classe = " . dbI($pair['id_classe']) . "
                  AND di.id_materia = " . dbI($pair['id_materia']) . "
            ") ?: [];

            foreach ($gestoreRows as $gestoreRow) {
                $idKey = intval($gestoreRow['id_docente']) . '|' . intval($gestoreRow['id_classe']) . '|' . intval($gestoreRow['id_materia']);
                if (isset($desiredIdKeys[$idKey])) {
                    continue;
                }

                $item = [
                    'username' => $gestoreRow['username'],
                    'classe' => $gestoreRow['classe'],
                    'materia' => $gestoreRow['materia'],
                    'id_docente' => intval($gestoreRow['id_docente']),
                    'id_classe' => intval($gestoreRow['id_classe']),
                    'id_materia' => intval($gestoreRow['id_materia']),
                ];
                $toRemove[] = $item;

                if ($apply) {
                    docenteInsegnaMbappDelete($item['id_docente'], $item['id_classe'], $item['id_materia'], $idAnno);
                }
            }
        }
    }

    return [
        'ok' => true,
        'skipped' => false,
        'apply' => $apply,
        'ignore_errors' => $ignoreErrors,
        'from' => $from,
        'to' => $to,
        'id_anno_scolastico' => $idAnno,
        'date_column' => $dateSource['table'] . '.' . $dateSource['column'],
        'debug' => $debugRows,
        'mbapp_rows' => $mbappRows,
        'already_present' => $alreadyPresent,
        'to_insert' => $toInsert,
        'to_remove' => $toRemove,
        'errors' => $errors,
    ];
}

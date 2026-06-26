<?php

/**
 * Formazione provvisoria classi.
 *
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/mastercom/tabelloni_lib.php';
require_once __DIR__ . '/studentiMovimentiLib.php';
require_once __DIR__ . '/studentiAttributiRiservatiLib.php';

function formazioneClassiEnsureTables(): void
{
    studentiMovimentiEnsureTables();
    studentiAttrEnsureTables();

    dbExec("
        CREATE TABLE IF NOT EXISTS `formazione_classi_sessioni` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_anno_scolastico_origine` INT NOT NULL,
            `id_anno_scolastico_target` INT NOT NULL,
            `tipo_formazione` VARCHAR(30) NOT NULL,
            `indirizzo` VARCHAR(80) NULL,
            `stato` ENUM('bozza','confermata','pubblicata') NOT NULL DEFAULT 'bozza',
            `descrizione` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_formazione_sessione` (`id_anno_scolastico_origine`, `id_anno_scolastico_target`, `tipo_formazione`, `indirizzo`),
            KEY `idx_formazione_sessioni_target` (`id_anno_scolastico_target`),
            KEY `idx_formazione_sessioni_stato` (`stato`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS `formazione_classi_studenti` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_sessione` INT NOT NULL,
            `id_studente` INT NOT NULL,
            `studente_nome` VARCHAR(255) NULL,
            `id_classe_origine` INT NULL,
            `classe_origine_label` VARCHAR(80) NULL,
            `id_classe_provvisoria` INT NULL,
            `classe_provvisoria_label` VARCHAR(80) NULL,
            `gruppo_origine` VARCHAR(40) NOT NULL DEFAULT 'promosso',
            `bloccato` TINYINT(1) NOT NULL DEFAULT 0,
            `ordine` INT NOT NULL DEFAULT 0,
            `media_generale` DECIMAL(5,2) NULL,
            `voto_matematica` DECIMAL(5,2) NULL,
            `voto_italiano` DECIMAL(5,2) NULL,
            `voto_capacita_relazionale` DECIMAL(5,2) NULL,
            `voto_esame_terza_media` DECIMAL(5,2) NULL,
            `fonte_valori` VARCHAR(40) NOT NULL DEFAULT 'mastercom',
            `note` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_formazione_studente` (`id_sessione`, `id_studente`),
            KEY `idx_formazione_studenti_classe` (`id_sessione`, `classe_provvisoria_label`),
            KEY `idx_formazione_studenti_gruppo` (`id_sessione`, `gruppo_origine`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function formazioneClassiH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formazioneClassiNorm(string $value): string
{
    return mastercomAdminNorm($value);
}

function formazioneClassiAddressKeyFromClass(string $classLabel): string
{
    $classLabel = trim($classLabel);
    $norm = formazioneClassiNorm($classLabel);
    if (preg_match('/^[1-5]DS\b/u', $norm) || preg_match('/\bDIGITAL\s+SCIENCE\b/u', $norm)) {
        return 'DIGITAL_SCIENCE';
    }
    if (preg_match('/^[3-5]MEA\b/u', $norm) || preg_match('/\bMEA\b/u', $norm)) {
        return 'MEA';
    }
    return formazioneClassiNorm(mastercomTabelloniSummaryAddress($classLabel));
}

function formazioneClassiAddressLabel(string $addressKey): string
{
    $labels = [
        'DIGITAL_SCIENCE' => 'DIGITAL SCIENCE',
        'MEA' => 'MECCANICA E MECCATRONICA - ENERGIA',
    ];
    return $labels[$addressKey] ?? $addressKey;
}

function formazioneClassiTipi(): array
{
    return [
        'prime' => ['anno' => 1, 'label' => 'Future prime'],
        'seconde' => ['anno' => 2, 'label' => 'Future seconde'],
        'terze' => ['anno' => 3, 'label' => 'Future terze'],
        'quarte' => ['anno' => 4, 'label' => 'Future quarte'],
        'quinte' => ['anno' => 5, 'label' => 'Future quinte'],
    ];
}

function formazioneClassiTipoDaAnno(int $classYear): string
{
    foreach (formazioneClassiTipi() as $key => $data) {
        if (intval($data['anno'] ?? 0) === $classYear) {
            return $key;
        }
    }
    return 'quinte';
}

function formazioneClassiAnnoDaTipo(string $tipo): int
{
    $tipi = formazioneClassiTipi();
    return intval($tipi[$tipo]['anno'] ?? 5);
}

function formazioneClassiSchoolYears(): array
{
    return dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY anno DESC") ?: [];
}

function formazioneClassiDefaultTargetYear(int $sourceYearId): int
{
    $source = dbGetFirst("SELECT id, anno FROM anno_scolastico WHERE id = " . dbI($sourceYearId) . " LIMIT 1");
    $sourceLabel = trim((string)($source['anno'] ?? ''));
    if ($sourceLabel !== '' && preg_match('/(\d{4})\D+(\d{2,4})/u', $sourceLabel, $m)) {
        $start = intval($m[1]) + 1;
        $end = intval($m[2]);
        if ($end < 100) {
            $end += 2000;
        }
        $end++;
        $patterns = [
            $start . '/' . substr((string)$end, -2),
            $start . '-' . substr((string)$end, -2),
            $start . '/' . $end,
            $start . '-' . $end,
        ];
        foreach ($patterns as $pattern) {
            $id = dbGetValue("SELECT id FROM anno_scolastico WHERE anno = " . dbQ($pattern) . " LIMIT 1");
            if ($id !== null) {
                return intval($id);
            }
        }
    }

    $next = dbGetValue("SELECT id FROM anno_scolastico WHERE id > " . dbI($sourceYearId) . " ORDER BY id ASC LIMIT 1");
    return $next !== null ? intval($next) : $sourceYearId;
}

function formazioneClassiCurrentYearId(): int
{
    global $__anno_scolastico_corrente_id;
    return intval($__anno_scolastico_corrente_id ?? 0);
}

function formazioneClassiAddressOptions(int $sourceYearId, int $classYear): array
{
    mastercomTabelloniEnsureTables();
    mastercomTabelloniRefreshDerivedFields();

    $rows = dbGetAll("
        SELECT DISTINCT
            t.classe,
            t.classe_tabellone,
            cls_summary.classe AS classe_gestore_studente,
            mcls_summary.nome AS classe_mastercom_studente
        FROM mastercom_tabelloni_scrutini t
        INNER JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
        LEFT JOIN studente_frequenta sf_summary ON sf_summary.id = (
            SELECT sf2.id
            FROM studente_frequenta sf2
            WHERE sf2.id_studente = s.id_studente_gestore
              AND sf2.id_anno_scolastico = t.id_anno_scolastico
            ORDER BY sf2.id DESC
            LIMIT 1
        )
        LEFT JOIN classi cls_summary ON cls_summary.id = sf_summary.id_classe
        LEFT JOIN mastercom_studenti mstu_summary ON mstu_summary.id = (
            SELECT ms2.id
            FROM mastercom_studenti ms2
            WHERE ms2.mastercom_id_studente = s.mastercom_id_studente
            ORDER BY ms2.id DESC
            LIMIT 1
        )
        LEFT JOIN mastercom_classi mcls_summary ON mcls_summary.mastercom_id_classe = mstu_summary.mastercom_id_classe_corrente
        WHERE t.id_anno_scolastico = " . dbI($sourceYearId) . "
          AND t.periodo = '9'
    ") ?: [];

    $addresses = [];
    foreach ($rows as $row) {
        $classLabel = mastercomTabelloniSummaryEffectiveClassLabel($row);
        if (mastercomTabelloniClassYearFromName($classLabel) !== $classYear) {
            continue;
        }
        $address = formazioneClassiAddressKeyFromClass(mastercomTabelloniSummaryClassLabel($row));
        if ($address === '' || $address === 'n/d') {
            continue;
        }
        $addresses[$address] = formazioneClassiAddressLabel($address);
    }
    asort($addresses, SORT_NATURAL | SORT_FLAG_CASE);
    return $addresses;
}

function formazioneClassiTargetClasses(int $classYear, string $indirizzo = ''): array
{
    $rows = dbGetAll("
        SELECT id, classe
        FROM classi
        WHERE anno = " . dbI($classYear) . "
        ORDER BY classe ASC
    ") ?: [];

    $indirizzoNorm = formazioneClassiNorm($indirizzo);
    $result = [];
    foreach ($rows as $row) {
        $label = trim((string)($row['classe'] ?? ''));
        if ($label === '') {
            continue;
        }
        if ($indirizzoNorm !== '' && formazioneClassiNorm(formazioneClassiAddressKeyFromClass($label)) !== $indirizzoNorm) {
            continue;
        }
        $result[] = [
            'id' => intval($row['id'] ?? 0),
            'label' => $label,
        ];
    }
    return $result;
}

function formazioneClassiAddressOptionsForFormation(int $sourceYearId, int $targetClassYear): array
{
    $addresses = [];
    if ($targetClassYear > 1 && $targetClassYear !== 3) {
        foreach (formazioneClassiAddressOptions($sourceYearId, $targetClassYear - 1) as $key => $label) {
            $addresses[$key] = $label;
        }
    }
    foreach (formazioneClassiAddressOptions($sourceYearId, $targetClassYear) as $key => $label) {
        $addresses[$key] = $label;
    }

    asort($addresses, SORT_NATURAL | SORT_FLAG_CASE);
    return $addresses;
}

function formazioneClassiSession(int $sourceYearId, int $targetYearId, string $tipo, string $indirizzo): array
{
    formazioneClassiEnsureTables();

    $tipo = trim($tipo) !== '' ? trim($tipo) : 'quinte';
    $indirizzo = trim($indirizzo);
    $row = dbGetFirst("
        SELECT *
        FROM formazione_classi_sessioni
        WHERE id_anno_scolastico_origine = " . dbI($sourceYearId) . "
          AND id_anno_scolastico_target = " . dbI($targetYearId) . "
          AND tipo_formazione = " . dbQ($tipo) . "
          AND indirizzo = " . dbQ($indirizzo) . "
        LIMIT 1
    ");
    if ($row !== null) {
        return $row;
    }

    dbExec("
        INSERT INTO formazione_classi_sessioni (
            id_anno_scolastico_origine, id_anno_scolastico_target, tipo_formazione,
            indirizzo, stato, descrizione, created_at, updated_at
        ) VALUES (
            " . dbI($sourceYearId) . ",
            " . dbI($targetYearId) . ",
            " . dbQ($tipo) . ",
            " . dbQ($indirizzo) . ",
            'bozza',
            " . dbQ('Formazione classi ' . $tipo . ' - ' . $indirizzo) . ",
            NOW(),
            NOW()
        )
    ");
    return dbGetFirst("SELECT * FROM formazione_classi_sessioni WHERE id = " . dbI(dblastId()) . " LIMIT 1") ?: [];
}

function formazioneClassiLocalClassByLabel(string $label): ?array
{
    $label = trim($label);
    if ($label === '') {
        return null;
    }
    $row = dbGetFirst("SELECT id, classe FROM classi WHERE classe = " . dbQ($label) . " LIMIT 1");
    return $row ?: null;
}

function formazioneClassiNextClassLabel(string $classLabel): string
{
    $classLabel = trim($classLabel);
    if (preg_match('/^([1-4])(.*)$/u', $classLabel, $m)) {
        return (string)(intval($m[1]) + 1) . $m[2];
    }
    return $classLabel;
}

function formazioneClassiTabelloneRows(int $sourceYearId, int $classYear, string $indirizzo, array $outcomes): array
{
    mastercomTabelloniEnsureTables();
    mastercomTabelloniRefreshDerivedFields();

    $rows = dbGetAll("
        SELECT
            t.id AS tabellone_id,
            t.classe,
            t.classe_tabellone,
            t.id_anno_scolastico,
            s.id AS tabellone_studente_id,
            s.id_studente_gestore,
            s.studente_nome,
            s.media,
            s.esito_key,
            cls_summary.id AS id_classe_gestore_studente,
            cls_summary.classe AS classe_gestore_studente,
            mcls_summary.nome AS classe_mastercom_studente,
            GROUP_CONCAT(CONCAT_WS('|', v.materia_codice, v.tipo_colonna, COALESCE(v.valore_num, '')) SEPARATOR '\n') AS valori
        FROM mastercom_tabelloni_scrutini t
        INNER JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
        LEFT JOIN mastercom_tabelloni_scrutini_voti v ON v.tabellone_studente_id = s.id
        LEFT JOIN studente_frequenta sf_summary ON sf_summary.id = (
            SELECT sf2.id
            FROM studente_frequenta sf2
            WHERE sf2.id_studente = s.id_studente_gestore
              AND sf2.id_anno_scolastico = t.id_anno_scolastico
            ORDER BY sf2.id DESC
            LIMIT 1
        )
        LEFT JOIN classi cls_summary ON cls_summary.id = sf_summary.id_classe
        LEFT JOIN mastercom_studenti mstu_summary ON mstu_summary.id = (
            SELECT ms2.id
            FROM mastercom_studenti ms2
            WHERE ms2.mastercom_id_studente = s.mastercom_id_studente
            ORDER BY ms2.id DESC
            LIMIT 1
        )
        LEFT JOIN mastercom_classi mcls_summary ON mcls_summary.mastercom_id_classe = mstu_summary.mastercom_id_classe_corrente
        WHERE t.id_anno_scolastico = " . dbI($sourceYearId) . "
          AND t.periodo = '9'
          AND s.id_studente_gestore IS NOT NULL
          AND s.id_studente_gestore > 0
        GROUP BY t.id, t.classe, t.classe_tabellone, t.id_anno_scolastico,
                 s.id, s.id_studente_gestore, s.studente_nome, s.media, s.esito_key,
                 cls_summary.id, cls_summary.classe, mcls_summary.nome
        ORDER BY t.classe ASC, s.studente_nome ASC
    ") ?: [];

    $outcomeSet = array_fill_keys($outcomes, true);
    $indirizzoNorm = formazioneClassiNorm($indirizzo);
    $result = [];
    foreach ($rows as $row) {
        $classLabel = mastercomTabelloniSummaryEffectiveClassLabel($row);
        if (mastercomTabelloniClassYearFromName($classLabel) !== $classYear) {
            continue;
        }
        if (formazioneClassiNorm(formazioneClassiAddressKeyFromClass($classLabel)) !== $indirizzoNorm) {
            continue;
        }
        $outcome = (string)($row['esito_key'] ?? '');
        if (!isset($outcomeSet[$outcome])) {
            continue;
        }
        $metrics = formazioneClassiMetricsFromValues((string)($row['valori'] ?? ''), $row['media'] ?? null);
        $row['classe_effettiva'] = $classLabel;
        $row['metrics'] = $metrics;
        $result[] = $row;
    }

    return $result;
}

function formazioneClassiMetricsFromValues(string $values, $media): array
{
    $metrics = [
        'media_generale' => $media !== null && $media !== '' ? (float)$media : null,
        'voto_matematica' => null,
        'voto_italiano' => null,
        'voto_capacita_relazionale' => null,
    ];
    foreach (preg_split('/\n/u', $values, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
        [$code, $type, $value] = array_pad(explode('|', $line, 3), 3, '');
        $category = mastercomTabelloniAverageCategory($code, $type);
        if ($value === '' || !is_numeric($value)) {
            continue;
        }
        if ($category === 'matematica') {
            $metrics['voto_matematica'] = (float)$value;
        } elseif ($category === 'italiano') {
            $metrics['voto_italiano'] = (float)$value;
        } elseif ($category === 'crel') {
            $metrics['voto_capacita_relazionale'] = (float)$value;
        } elseif ($category === 'media') {
            $metrics['media_generale'] = (float)$value;
        }
    }
    return $metrics;
}

function formazioneClassiInitDaTabelloni(array $session, int $targetClassYear, string $indirizzo): void
{
    $sessionId = intval($session['id'] ?? 0);
    $sourceYearId = intval($session['id_anno_scolastico_origine'] ?? 0);
    if ($sessionId <= 0 || $sourceYearId <= 0) {
        return;
    }

    if ($targetClassYear > 1 && $targetClassYear !== 3) {
        $promossi = formazioneClassiTabelloneRows($sourceYearId, $targetClassYear - 1, $indirizzo, ['ammesso', 'anno_estero']);
        foreach ($promossi as $row) {
            $sourceLabel = (string)($row['classe_effettiva'] ?? '');
            $targetLabel = formazioneClassiNextClassLabel($sourceLabel);
            $targetClass = formazioneClassiLocalClassByLabel($targetLabel);
            formazioneClassiUpsertStudent($sessionId, $row, 'promosso', $targetClass ? intval($targetClass['id']) : null, $targetLabel);
        }
    }

    $bocciati = formazioneClassiTabelloneRows($sourceYearId, $targetClassYear, $indirizzo, ['non_ammesso', 'in_corso']);
    foreach ($bocciati as $row) {
        formazioneClassiUpsertStudent($sessionId, $row, 'bocciato', null, null);
    }
}

function formazioneClassiUpsertStudent(int $sessionId, array $row, string $gruppo, ?int $targetClassId, ?string $targetLabel): void
{
    $studentId = intval($row['id_studente_gestore'] ?? 0);
    if ($sessionId <= 0 || $studentId <= 0) {
        return;
    }
    $existing = dbGetValue("
        SELECT id
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND id_studente = " . dbI($studentId) . "
        LIMIT 1
    ");
    if ($existing !== null) {
        return;
    }
    $metrics = (array)($row['metrics'] ?? []);
    dbExec("
        INSERT INTO formazione_classi_studenti (
            id_sessione, id_studente, studente_nome, id_classe_origine, classe_origine_label,
            id_classe_provvisoria, classe_provvisoria_label, gruppo_origine,
            media_generale, voto_matematica, voto_italiano, voto_capacita_relazionale,
            fonte_valori, created_at, updated_at
        ) VALUES (
            " . dbI($sessionId) . ",
            " . dbI($studentId) . ",
            " . dbQ($row['studente_nome'] ?? '') . ",
            " . dbI($row['id_classe_gestore_studente'] ?? null) . ",
            " . dbQ($row['classe_effettiva'] ?? '') . ",
            " . dbI($targetClassId) . ",
            " . dbQ($targetLabel) . ",
            " . dbQ($gruppo) . ",
            " . dbF($metrics['media_generale'] ?? null) . ",
            " . dbF($metrics['voto_matematica'] ?? null) . ",
            " . dbF($metrics['voto_italiano'] ?? null) . ",
            " . dbF($metrics['voto_capacita_relazionale'] ?? null) . ",
            'mastercom',
            NOW(),
            NOW()
        )
    ");
}

function formazioneClassiState(int $sourceYearId, int $targetYearId, string $tipo, string $indirizzo): array
{
    $targetClassYear = formazioneClassiAnnoDaTipo($tipo);
    $session = formazioneClassiSession($sourceYearId, $targetYearId, $tipo, $indirizzo);
    formazioneClassiInitDaTabelloni($session, $targetClassYear, $indirizzo);

    $rows = dbGetAll("
        SELECT
            f.*,
            s.cognome,
            s.nome,
            s.codice_fiscale,
            s.sesso,
            mp_status.id AS movimento_pratica_id,
            mp_note.note AS movimento_note,
            mp_note.tipo_pratica AS movimento_tipo_pratica,
            mp_note.stato_pratica AS movimento_stato_pratica,
            mp_note.updated_at AS movimento_updated_at,
            attr.attributi_riservati_raw
        FROM formazione_classi_studenti f
        LEFT JOIN studente s ON s.id = f.id_studente
        LEFT JOIN (
            SELECT
                id_studente,
                GROUP_CONCAT(CONCAT(codice_attributo, '|', fonte) ORDER BY codice_attributo ASC SEPARATOR '||') AS attributi_riservati_raw
            FROM studente_attributi_riservati
            WHERE attivo = 1
            GROUP BY id_studente
        ) attr ON attr.id_studente = f.id_studente
        LEFT JOIN studenti_movimenti_pratiche mp_status ON mp_status.id_studente = f.id_studente
            AND mp_status.tipo_pratica IN ('bocciato_reiscrizione', 'uscita', 'ritiro')
            AND mp_status.stato_pratica <> 'annullata'
            AND mp_status.id = (
                SELECT mp1.id
                FROM studenti_movimenti_pratiche mp1
                WHERE mp1.id_studente = f.id_studente
                  AND mp1.tipo_pratica IN ('bocciato_reiscrizione', 'uscita', 'ritiro')
                  AND mp1.stato_pratica <> 'annullata'
                ORDER BY mp1.updated_at DESC, mp1.id DESC
                LIMIT 1
            )
        LEFT JOIN studenti_movimenti_pratiche mp_note ON mp_note.id_studente = f.id_studente
            AND mp_note.tipo_pratica IN ('bocciato_reiscrizione', 'uscita', 'ritiro')
            AND mp_note.stato_pratica <> 'annullata'
            AND mp_note.id = (
                SELECT mp2.id
                FROM studenti_movimenti_pratiche mp2
                WHERE mp2.id_studente = f.id_studente
                  AND mp2.tipo_pratica IN ('bocciato_reiscrizione', 'uscita', 'ritiro')
                  AND mp2.stato_pratica <> 'annullata'
                  AND TRIM(COALESCE(mp2.note, '')) <> ''
                ORDER BY mp2.updated_at DESC, mp2.id DESC
                LIMIT 1
            )
        WHERE f.id_sessione = " . dbI($session['id'] ?? 0) . "
        ORDER BY COALESCE(f.classe_provvisoria_label, 'ZZZ') ASC,
                 COALESCE(s.cognome, f.studente_nome) ASC,
                 COALESCE(s.nome, '') ASC,
                 f.studente_nome ASC
    ") ?: [];

    $classes = [];
    foreach (formazioneClassiTargetClasses($targetClassYear, $indirizzo) as $targetClass) {
        $label = (string)$targetClass['label'];
        $classes[$label] = [
            'label' => $label,
            'id_classe' => intval($targetClass['id'] ?? 0),
            'students' => [],
            'stats' => [],
        ];
    }
    $unassigned = [];
    foreach ($rows as $row) {
        $item = formazioneClassiStudentView($row);
        $label = trim((string)($row['classe_provvisoria_label'] ?? ''));
        if ($label === '') {
            $unassigned[] = $item;
            continue;
        }
        if (!isset($classes[$label])) {
            $classes[$label] = [
                'label' => $label,
                'id_classe' => intval($row['id_classe_provvisoria'] ?? 0),
                'students' => [],
                'stats' => [],
            ];
        }
        $classes[$label]['students'][] = $item;
    }
    ksort($classes, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($classes as &$class) {
        $class['stats'] = formazioneClassiStats($class['students']);
    }
    unset($class);

    return [
        'session' => $session,
        'classes' => array_values($classes),
        'unassigned' => $unassigned,
        'unassigned_stats' => formazioneClassiStats($unassigned),
    ];
}

function formazioneClassiQuinteState(int $sourceYearId, int $targetYearId, string $indirizzo): array
{
    return formazioneClassiState($sourceYearId, $targetYearId, 'quinte', $indirizzo);
}

function formazioneClassiStudentView(array $row): array
{
    $name = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
    if ($name === '') {
        $name = trim((string)($row['studente_nome'] ?? ''));
    }
    return [
        'id' => intval($row['id'] ?? 0),
        'id_studente' => intval($row['id_studente'] ?? 0),
        'nome' => $name,
        'codice_fiscale' => (string)($row['codice_fiscale'] ?? ''),
        'sesso' => strtoupper(trim((string)($row['sesso'] ?? ''))),
        'classe_origine' => (string)($row['classe_origine_label'] ?? ''),
        'gruppo_origine' => (string)($row['gruppo_origine'] ?? ''),
        'bloccato' => intval($row['bloccato'] ?? 0),
        'id_movimento' => intval($row['movimento_pratica_id'] ?? 0),
        'media_generale' => formazioneClassiNullableFloat($row['media_generale'] ?? null),
        'voto_matematica' => formazioneClassiNullableFloat($row['voto_matematica'] ?? null),
        'voto_italiano' => formazioneClassiNullableFloat($row['voto_italiano'] ?? null),
        'voto_capacita_relazionale' => formazioneClassiNullableFloat($row['voto_capacita_relazionale'] ?? null),
        'note_formazione' => trim((string)($row['movimento_note'] ?? '')),
        'note_formazione_origine' => trim((string)($row['movimento_tipo_pratica'] ?? '')),
        'note_formazione_stato' => trim((string)($row['movimento_stato_pratica'] ?? '')),
        'note_formazione_updated_at' => trim((string)($row['movimento_updated_at'] ?? '')),
        'attributi_riservati' => formazioneClassiParseStudentAttrs((string)($row['attributi_riservati_raw'] ?? '')),
    ];
}

function formazioneClassiParseStudentAttrs(string $raw): array
{
    $rows = [];
    foreach (preg_split('/\|\|/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $chunk) {
        [$code, $source] = array_pad(explode('|', $chunk, 2), 2, '');
        $rows[] = [
            'codice_attributo' => $code,
            'fonte' => $source,
        ];
    }
    return studentiAttrRowsToDisplay($rows);
}

function formazioneClassiNullableFloat($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    return (float)$value;
}

function formazioneClassiStats(array $students): array
{
    $stats = [
        'count' => count($students),
        'maschi' => 0,
        'femmine' => 0,
        'media_generale' => null,
        'voto_matematica' => null,
        'voto_italiano' => null,
        'voto_capacita_relazionale' => null,
    ];
    foreach ($students as $student) {
        $sesso = strtoupper(trim((string)($student['sesso'] ?? '')));
        if ($sesso === 'M') {
            $stats['maschi']++;
        } elseif ($sesso === 'F') {
            $stats['femmine']++;
        }
    }
    foreach (['media_generale', 'voto_matematica', 'voto_italiano', 'voto_capacita_relazionale'] as $field) {
        $sum = 0.0;
        $count = 0;
        foreach ($students as $student) {
            if (($student[$field] ?? null) === null) {
                continue;
            }
            $sum += (float)$student[$field];
            $count++;
        }
        $stats[$field] = $count > 0 ? $sum / $count : null;
    }
    return $stats;
}

function formazioneClassiMoveStudent(int $sessionId, int $rowId, string $targetLabel): array
{
    $row = dbGetFirst("
        SELECT *
        FROM formazione_classi_studenti
        WHERE id = " . dbI($rowId) . "
          AND id_sessione = " . dbI($sessionId) . "
        LIMIT 1
    ");
    if (!$row) {
        return ['ok' => false, 'message' => 'Studente non trovato nella bozza.'];
    }
    if (intval($row['bloccato'] ?? 0) === 1) {
        return ['ok' => false, 'message' => 'Studente bloccato: sbloccalo prima di spostarlo.'];
    }

    $targetLabel = trim($targetLabel);
    $classId = null;
    if ($targetLabel !== '') {
        $targetClass = formazioneClassiLocalClassByLabel($targetLabel);
        $classId = $targetClass ? intval($targetClass['id']) : null;
    }
    dbExec("
        UPDATE formazione_classi_studenti
        SET id_classe_provvisoria = " . dbI($classId) . ",
            classe_provvisoria_label = " . dbQ($targetLabel) . ",
            updated_at = NOW()
        WHERE id = " . dbI($rowId) . "
          AND id_sessione = " . dbI($sessionId) . "
        LIMIT 1
    ");
    dbExec("UPDATE formazione_classi_sessioni SET updated_at = NOW() WHERE id = " . dbI($sessionId) . " LIMIT 1");

    return ['ok' => true, 'message' => 'Spostamento salvato.'];
}

function formazioneClassiFormatAvg($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    return number_format((float)$value, 2, ',', '');
}

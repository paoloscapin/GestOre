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
require_once __DIR__ . '/iscrizioniPrimeLib.php';

function formazioneClassiEnsureTables(): void
{
    studentiMovimentiEnsureTables();
    studentiAttrEnsureTables();
    formazioneClassiEnsureClassiAnnoTabletColumn();

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
            `blocco_individuale` TINYINT(1) NOT NULL DEFAULT 0,
            `blocco_classe` TINYINT(1) NOT NULL DEFAULT 0,
            `assegnazione_manuale` TINYINT(1) NOT NULL DEFAULT 0,
            `ordine` INT NOT NULL DEFAULT 0,
            `richiesta_tablet` TINYINT(1) NULL,
            `media_generale` DECIMAL(5,2) NULL,
            `voto_matematica` DECIMAL(5,2) NULL,
            `voto_italiano` DECIMAL(5,2) NULL,
            `voto_capacita_relazionale` DECIMAL(5,2) NULL,
            `voto_esame_terza_media` DECIMAL(5,2) NULL,
            `fonte_valori` VARCHAR(40) NOT NULL DEFAULT 'mastercom',
            `note` TEXT NULL,
            `consiglio_orientativo` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_formazione_studente` (`id_sessione`, `id_studente`),
            KEY `idx_formazione_studenti_classe` (`id_sessione`, `classe_provvisoria_label`),
            KEY `idx_formazione_studenti_gruppo` (`id_sessione`, `gruppo_origine`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    dbExec("
        CREATE TABLE IF NOT EXISTS `formazione_classi_snapshot` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_sessione` INT NOT NULL,
            `nome` VARCHAR(160) NOT NULL,
            `note` TEXT NULL,
            `created_by` VARCHAR(120) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_formazione_snapshot_sessione` (`id_sessione`),
            KEY `idx_formazione_snapshot_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    dbExec("
        CREATE TABLE IF NOT EXISTS `formazione_classi_snapshot_studenti` (
            `id_snapshot` INT NOT NULL,
            `id_studente` INT NOT NULL,
            `classe_provvisoria_label` VARCHAR(80) NULL,
            `bloccato` TINYINT(1) NOT NULL DEFAULT 0,
            `blocco_individuale` TINYINT(1) NOT NULL DEFAULT 0,
            `blocco_classe` TINYINT(1) NOT NULL DEFAULT 0,
            `assegnazione_manuale` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_snapshot`, `id_studente`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    dbExec("
        CREATE TABLE IF NOT EXISTS `formazione_classi_undo` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_sessione` INT NOT NULL,
            `azione` VARCHAR(60) NOT NULL,
            `descrizione` VARCHAR(255) DEFAULT NULL,
            `payload_json` MEDIUMTEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_formazione_undo_sessione` (`id_sessione`, `id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    if (!studentiMovimentiColumnExists('formazione_classi_studenti', 'assegnazione_manuale')) {
        dbExec("ALTER TABLE formazione_classi_studenti ADD COLUMN `assegnazione_manuale` TINYINT(1) NOT NULL DEFAULT 0 AFTER `bloccato`");
    }
    if (!studentiMovimentiColumnExists('formazione_classi_studenti', 'blocco_individuale')) {
        dbExec("ALTER TABLE formazione_classi_studenti ADD COLUMN `blocco_individuale` TINYINT(1) NOT NULL DEFAULT 0 AFTER `bloccato`");
    }
    if (!studentiMovimentiColumnExists('formazione_classi_studenti', 'blocco_classe')) {
        dbExec("ALTER TABLE formazione_classi_studenti ADD COLUMN `blocco_classe` TINYINT(1) NOT NULL DEFAULT 0 AFTER `blocco_individuale`");
    }
    if (!studentiMovimentiColumnExists('formazione_classi_studenti', 'richiesta_tablet')) {
        dbExec("ALTER TABLE formazione_classi_studenti ADD COLUMN `richiesta_tablet` TINYINT(1) NULL AFTER `ordine`");
    }
    if (!studentiMovimentiColumnExists('formazione_classi_studenti', 'consiglio_orientativo')) {
        dbExec("ALTER TABLE formazione_classi_studenti ADD COLUMN `consiglio_orientativo` TEXT NULL AFTER `note`");
    }
    dbExec("
        UPDATE formazione_classi_studenti
        SET blocco_individuale = 1
        WHERE COALESCE(bloccato, 0) = 1
          AND COALESCE(blocco_individuale, 0) = 0
          AND COALESCE(blocco_classe, 0) = 0
    ");
}

function formazioneClassiEnsureClassiAnnoTabletColumn(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    dbExec("
        CREATE TABLE IF NOT EXISTS `classi_anno_scolastico` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_classe` INT NOT NULL,
            `id_anno_scolastico` INT NOT NULL,
            `attiva` TINYINT(1) NOT NULL DEFAULT 1,
            `is_tablet` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_classi_anno_classe_anno` (`id_classe`, `id_anno_scolastico`),
            KEY `idx_classi_anno_anno` (`id_anno_scolastico`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    $exists = dbGetValue("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'classi_anno_scolastico'
          AND COLUMN_NAME = 'is_tablet'
    ");
    if (intval($exists) === 0) {
        dbExec("ALTER TABLE classi_anno_scolastico ADD COLUMN is_tablet TINYINT(1) NOT NULL DEFAULT 0 AFTER attiva");
    }
}

function formazioneClassiH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formazioneClassiNorm(string $value): string
{
    return mastercomAdminNorm($value);
}

function formazioneClassiAddressKeyFromAddressName(string $addressName): string
{
    $norm = formazioneClassiNorm($addressName);
    if ($norm === '' || $norm === 'N/D') {
        return '';
    }
    if (strpos($norm, 'BIENNIO') !== false) {
        return 'BIENNIO';
    }
    if (strpos($norm, 'DIGITAL') !== false && strpos($norm, 'SCIENCE') !== false) {
        return 'DIGITAL SCIENCE';
    }
    return $norm;
}

function formazioneClassiAddressKeyFromClass(string $classLabel): string
{
    $classLabel = trim($classLabel);
    $norm = formazioneClassiNorm($classLabel);
    if (preg_match('/^[1-5]DS\b/u', $norm) || preg_match('/\bDIGITAL\s+SCIENCE\b/u', $norm)) {
        return 'DIGITAL SCIENCE';
    }
    if (preg_match('/^[3-5]([A-Z0-9]{2,})\b/u', $norm, $matches)) {
        $code = (string)$matches[1];
        if (formazioneClassiIsKnownAddressKey($code)) {
            return $code;
        }
    }

    $summaryAddress = formazioneClassiAddressKeyFromAddressName(mastercomTabelloniSummaryAddress($classLabel));
    if ($summaryAddress !== '') {
        return $summaryAddress;
    }

    $year = mastercomTabelloniClassYearFromName($classLabel);
    if ($year > 0 && $year <= 2) {
        return 'BIENNIO';
    }

    return '';
}

function formazioneClassiIsKnownAddressKey(string $addressKey): bool
{
    $key = formazioneClassiNorm($addressKey);
    return in_array($key, ['BIENNIO', 'DIGITAL_SCIENCE', 'MEA', 'AUA', 'ELA', 'INF', 'TEL', 'TLC', 'BTS', 'BTA', 'CHI', 'GRA'], true);
}

function formazioneClassiAddressLabel(string $addressKey): string
{
    $labels = [
        'BIENNIO' => 'BIENNIO COMUNE',
        'DIGITAL_SCIENCE' => 'DIGITAL SCIENCE',
    ];
    return $labels[$addressKey] ?? str_replace('_', ' ', $addressKey);
}

function formazioneClassiAddressAliases(string $addressKey): array
{
    $key = formazioneClassiNorm($addressKey);
    $aliases = [
        'BIENNIO' => ['BIENNIO', 'BIENNIO COMUNE', 'BIENNIO SETTORE TECNOLOGICO'],
        'DIGITAL SCIENCE' => ['DIGITAL SCIENCE', 'DIGITAL_SCIENCE', 'DS'],
        'DIGITAL_SCIENCE' => ['DIGITAL SCIENCE', 'DIGITAL_SCIENCE', 'DS'],
        'MEA' => ['MEA', 'MECCANICA ED ENERGIA', 'MECCANICA E MECCATRONICA ENERGIA', 'MECCANICA E MECCATRONICA - ENERGIA'],
        'ELA' => ['ELA', 'ELETTRONICA ELETTROTECNICA', 'ELETTRONICA / ELETTROTECNICA'],
        'AUA' => ['AUA', 'AUTOMAZIONE'],
        'INF' => ['INF', 'INFORMATICA'],
        'TEL' => ['TEL', 'TLC', 'TELECOMUNICAZIONI'],
        'TLC' => ['TEL', 'TLC', 'TELECOMUNICAZIONI'],
        'BTS' => ['BTS', 'BIOTECNOLOGIE SANITARIE'],
        'BTA' => ['BTA', 'BIOTECNOLOGIE AMBIENTALI'],
        'CHI' => ['CHI', 'CHIMICA E MATERIALI'],
        'GRA' => ['GRA', 'GRAFICA', 'COMUNICAZIONE'],
    ];
    $values = $aliases[$key] ?? [$key];
    $values[] = $key;
    return array_values(array_unique(array_map('formazioneClassiNorm', $values)));
}

function formazioneClassiAddressKeysMatch(string $left, string $right): bool
{
    $leftNorm = formazioneClassiNorm($left);
    $rightNorm = formazioneClassiNorm($right);
    $leftKey = ctype_digit(trim($left)) ? formazioneClassiGestoreAddressKeyFromId((int)trim($left)) : '';
    $rightKey = ctype_digit(trim($right)) ? formazioneClassiGestoreAddressKeyFromId((int)trim($right)) : '';
    if ($leftKey !== '' || $rightKey !== '') {
        $resolvedLeft = $leftKey !== '' ? $leftKey : $left;
        $resolvedRight = $rightKey !== '' ? $rightKey : $right;
        if (formazioneClassiNorm($resolvedLeft) === formazioneClassiNorm($resolvedRight)) {
            return true;
        }
        if (count(array_intersect(formazioneClassiAddressAliases($resolvedLeft), formazioneClassiAddressAliases($resolvedRight))) > 0) {
            return true;
        }
    }
    if ($leftNorm !== '' && $leftNorm === $rightNorm) {
        return true;
    }
    if (ctype_digit(trim($left)) || ctype_digit(trim($right))) {
        $leftResolved = ctype_digit(trim($left)) ? trim($left) : (string)formazioneClassiAddressIdByName($left, 5);
        $rightResolved = ctype_digit(trim($right)) ? trim($right) : (string)formazioneClassiAddressIdByName($right, 5);
        return $leftResolved !== '' && $leftResolved !== '0' && $leftResolved === $rightResolved;
    }
    $leftAliases = formazioneClassiAddressAliases($left);
    $rightAliases = formazioneClassiAddressAliases($right);
    return count(array_intersect($leftAliases, $rightAliases)) > 0;
}

function formazioneClassiAddressKeysMatchStrict(string $left, string $right): bool
{
    if (ctype_digit(trim($left)) || ctype_digit(trim($right))) {
        $leftKey = ctype_digit(trim($left)) ? formazioneClassiGestoreAddressKeyFromId((int)trim($left)) : $left;
        $rightKey = ctype_digit(trim($right)) ? formazioneClassiGestoreAddressKeyFromId((int)trim($right)) : $right;
        if (trim((string)$leftKey) !== '' || trim((string)$rightKey) !== '') {
            return formazioneClassiAddressKeysMatch((string)$leftKey, (string)$rightKey);
        }
    }
    $leftNorm = formazioneClassiNorm($left);
    $rightNorm = formazioneClassiNorm($right);
    $equivalences = [
        'CHIMICA E MATERIALI' => 'CHIMICA DEI MATERIALI',
    ];
    $leftNorm = $equivalences[$leftNorm] ?? $leftNorm;
    $rightNorm = $equivalences[$rightNorm] ?? $rightNorm;
    if ($leftNorm === '' || $rightNorm === '') {
        return false;
    }
    if ($leftNorm === $rightNorm) {
        return true;
    }
    $leftBiennio = strpos($leftNorm, 'BIENNIO') !== false;
    $rightBiennio = strpos($rightNorm, 'BIENNIO') !== false;
    if ($leftBiennio && $rightBiennio) {
        return true;
    }
    $leftDs = strpos($leftNorm, 'DIGITAL') !== false && strpos($leftNorm, 'SCIENCE') !== false;
    $rightDs = strpos($rightNorm, 'DIGITAL') !== false && strpos($rightNorm, 'SCIENCE') !== false;
    if ($leftDs && $rightDs) {
        return true;
    }
    return count(array_intersect(formazioneClassiAddressAliases($left), formazioneClassiAddressAliases($right))) > 0;
}

function formazioneClassiGestoreAddressKeyFromId($id): string
{
    $id = intval($id);
    if ($id <= 0) {
        return '';
    }
    $name = trim((string)dbGetValue("SELECT nome FROM indirizzo WHERE id = " . dbI($id) . " LIMIT 1"));
    return $name !== '' ? formazioneClassiAddressKeyFromAddressName($name) : '';
}

function formazioneClassiGestoreAddressKeyFromPracticeText(string $value): string
{
    $norm = formazioneClassiNorm($value);
    if ($norm === '') {
        return '';
    }
    $equivalences = [
        'CHIMICA E MATERIALI' => 'CHIMICA DEI MATERIALI',
    ];
    if (isset($equivalences[$norm])) {
        $norm = $equivalences[$norm];
    }
    foreach (formazioneClassiIndirizziRows(range(1, 10)) as $row) {
        $name = (string)($row['nome'] ?? '');
        if (formazioneClassiNorm($name) === $norm) {
            return formazioneClassiAddressKeyFromAddressName($name);
        }
    }
    if ($norm === 'ENERGIA') {
        foreach (formazioneClassiIndirizziRows(range(1, 10)) as $row) {
            $nameNorm = formazioneClassiNorm((string)($row['nome'] ?? ''));
            if (strpos($nameNorm, 'ENERGIA') !== false && strpos($nameNorm, 'MECCANICA') !== false) {
                return formazioneClassiAddressKeyFromAddressName((string)($row['nome'] ?? ''));
            }
        }
    }
    return '';
}

function formazioneClassiIndirizziRows(?array $ids = null): array
{
    $where = '';
    if ($ids !== null) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (empty($ids)) {
            return [];
        }
        $where = 'WHERE id IN (' . implode(',', array_map('dbI', $ids)) . ')';
    }

    return dbGetAll("
        SELECT id, nome
        FROM indirizzo
        $where
        ORDER BY id ASC
    ") ?: [];
}

function formazioneClassiAddressOptionsFromIndirizzi(int $targetClassYear): array
{
    $ids = $targetClassYear <= 2 ? [11] : range(1, 10);
    if ($targetClassYear <= 2) {
        $digitalScienceId = formazioneClassiAddressIdByName('DIGITAL SCIENCE', 5);
        if ($digitalScienceId > 0) {
            $ids[] = $digitalScienceId;
        }
    }
    $addresses = [];
    foreach (formazioneClassiIndirizziRows($ids) as $row) {
        $id = intval($row['id'] ?? 0);
        $name = trim((string)($row['nome'] ?? ''));
        if ($id <= 0 || $name === '') {
            continue;
        }
        $addresses[(string)$id] = $name;
    }
    return $addresses;
}

function formazioneClassiAddressIdByName(string $name, int $targetClassYear): int
{
    $nameNorm = formazioneClassiNorm($name);
    if ($nameNorm === '') {
        return 0;
    }
    if ($targetClassYear <= 2 && strpos($nameNorm, 'BIENNIO') !== false && strpos($nameNorm, 'TECNOLOG') !== false) {
        return 11;
    }

    foreach (formazioneClassiIndirizziRows($targetClassYear <= 2 ? [11] : range(1, 10)) as $row) {
        $id = intval($row['id'] ?? 0);
        $indirizzoNorm = formazioneClassiNorm((string)($row['nome'] ?? ''));
        if ($id <= 0 || $indirizzoNorm === '') {
            continue;
        }
        if ($nameNorm === $indirizzoNorm
            || strpos($nameNorm, $indirizzoNorm) !== false
            || strpos($indirizzoNorm, $nameNorm) !== false
            || count(array_intersect(formazioneClassiAddressAliases($name), formazioneClassiAddressAliases((string)($row['nome'] ?? '')))) > 0) {
            return $id;
        }
    }
    return 0;
}

function formazioneClassiClassMatchesAddress(array $classRow, string $indirizzo): bool
{
    $indirizzoId = intval($indirizzo);
    if ($indirizzoId <= 0) {
        return formazioneClassiAddressKeysMatch(formazioneClassiAddressKeyFromClass((string)($classRow['classe'] ?? '')), $indirizzo);
    }
    $classLabel = (string)($classRow['classe'] ?? '');
    $classYear = mastercomTabelloniClassYearFromName($classLabel);
    if (preg_match('/^[1-5]DS\b/u', formazioneClassiNorm($classLabel))) {
        return formazioneClassiAddressKeysMatch('DIGITAL_SCIENCE', $indirizzo);
    }
    $firstAddressId = intval($classRow['id_primo_indirizzo'] ?? 0);
    if ($firstAddressId > 0 && $firstAddressId === $indirizzoId) {
        return true;
    }
    if ($firstAddressId > 0) {
        return false;
    }
    $classAddressKey = formazioneClassiAddressKeyFromClass($classLabel);
    if ($classAddressKey !== '' && formazioneClassiAddressKeysMatch($classAddressKey, $indirizzo)) {
        return true;
    }
    if ($classYear > 0 && $classYear <= 2 && $indirizzoId === 11) {
        return true;
    }
    return false;
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

function formazioneClassiAutoDistributionAllowedForYear(int $targetClassYear): bool
{
    return in_array($targetClassYear, [1, 3], true);
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

function formazioneClassiRefreshTabelloniOnce(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    mastercomTabelloniRefreshDerivedFields();
    $done = true;
}

function formazioneClassiAddressOptions(int $sourceYearId, int $classYear): array
{
    mastercomTabelloniEnsureTables();
    formazioneClassiRefreshTabelloniOnce();

    $rows = dbGetAll("
        SELECT DISTINCT
            t.classe,
            t.classe_tabellone,
            cls_summary.classe AS classe_gestore_studente,
            cls_summary.id_primo_indirizzo AS id_primo_indirizzo_gestore_studente,
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
        $gestoreClassLabel = trim((string)($row['classe_gestore_studente'] ?? ''));
        $filterLabel = $gestoreClassLabel !== '' ? $gestoreClassLabel : $classLabel;
        if (mastercomTabelloniClassYearFromName($filterLabel) !== $classYear) {
            continue;
        }
        $address = formazioneClassiGestoreAddressKeyFromId($row['id_primo_indirizzo_gestore_studente'] ?? 0);
        if ($address === '') {
            $address = formazioneClassiAddressKeyFromClass($classLabel);
        }
        if ($address === '' || $address === 'n/d') {
            continue;
        }
        $addresses[$address] = formazioneClassiAddressLabel($address);
    }
    asort($addresses, SORT_NATURAL | SORT_FLAG_CASE);
    return $addresses;
}

function formazioneClassiAddressOptionsFromClasses(array $classYears): array
{
    $years = array_values(array_unique(array_filter(array_map('intval', $classYears), fn($year) => $year > 0)));
    if (empty($years)) {
        return [];
    }

    $rows = dbGetAll("
        SELECT classe
        FROM classi
        WHERE anno IN (" . implode(',', array_map('dbI', $years)) . ")
        ORDER BY anno ASC, classe ASC
    ") ?: [];

    $addresses = [];
    foreach ($rows as $row) {
        $address = formazioneClassiAddressKeyFromClass((string)($row['classe'] ?? ''));
        if ($address === '') {
            continue;
        }
        $addresses[$address] = formazioneClassiAddressLabel($address);
    }
    asort($addresses, SORT_NATURAL | SORT_FLAG_CASE);
    return $addresses;
}

function formazioneClassiAddressKeyFromPractice(array $practice, int $targetClassYear): string
{
    $gestoreId = intval($practice['id_indirizzo_gestore'] ?? 0);
    if ($gestoreId > 0) {
        $address = formazioneClassiGestoreAddressKeyFromId($gestoreId);
        if ($address !== '') {
            return $address;
        }
    }
    if (formazioneClassiPracticeIsDigitalScience($practice)) {
        return 'DIGITAL SCIENCE';
    }
    $course = trim((string)($practice['corso_studi'] ?? ''));
    if ($course === '') {
        $course = trim((string)($practice['corso'] ?? ''));
    }
    if ($course === '') {
        $course = trim((string)($practice['indirizzo'] ?? ''));
    }
    $choice = trim((string)($practice['scelta_formativa'] ?? ''));
    $section = trim((string)($practice['sezione_richiesta'] ?? ''));
    $tabletGroup = trim((string)($practice['tablet_gruppo'] ?? ''));
    $textNorm = formazioneClassiNorm($course . ' ' . $choice . ' ' . $section);
    $sectionNorm = formazioneClassiNorm($section);

    if ($targetClassYear <= 2) {
        return 'BIENNIO';
    }

    foreach ([$course, $choice] as $value) {
        $address = formazioneClassiGestoreAddressKeyFromPracticeText((string)$value);
        if ($address !== '') {
            return $address;
        }
    }

    if (strpos($textNorm, 'ENERGIA') !== false || strpos($textNorm, 'MEA') !== false || strpos($textNorm, 'MECCAN') !== false) {
        $address = formazioneClassiGestoreAddressKeyFromPracticeText('ENERGIA');
        return $address !== '' ? $address : formazioneClassiAddressKeyFromAddressName('MECCANICA ED ENERGIA');
    }

    if (strpos($textNorm, 'AUTOMAZIONE') !== false || strpos($textNorm, 'AUA') !== false) {
        return formazioneClassiAddressKeyFromAddressName('AUTOMAZIONE');
    }

    if (strpos($textNorm, 'ELETTRON') !== false || strpos($textNorm, 'ELETTROTEC') !== false) {
        return formazioneClassiAddressKeyFromAddressName('ELETTRONICA / ELETTROTECNICA');
    }
    if (strpos($textNorm, 'INFORMATICA') !== false) {
        return formazioneClassiAddressKeyFromAddressName('INFORMATICA');
    }
    if (strpos($textNorm, 'TELECOM') !== false) {
        return formazioneClassiAddressKeyFromAddressName('TELECOMUNICAZIONI');
    }
    if (strpos($textNorm, 'BIOTECNOLOGIE SANITARIE') !== false) {
        return formazioneClassiAddressKeyFromAddressName('BIOTECNOLOGIE SANITARIE');
    }
    if (strpos($textNorm, 'BIOTECNOLOGIE AMBIENTALI') !== false) {
        return formazioneClassiAddressKeyFromAddressName('BIOTECNOLOGIE AMBIENTALI');
    }
    if (strpos($textNorm, 'CHIMICA') !== false) {
        return formazioneClassiAddressKeyFromAddressName('CHIMICA E MATERIALI');
    }
    if (strpos($textNorm, 'GRAFICA') !== false || strpos($textNorm, 'COMUNICAZIONE') !== false) {
        return formazioneClassiAddressKeyFromAddressName('GRAFICA E COMUNICAZIONE');
    }

    return '';
}

function formazioneClassiPracticeIsDigitalScience(array $practice): bool
{
    $tabletGroup = trim((string)($practice['tablet_gruppo'] ?? ''));
    $section = formazioneClassiNorm((string)($practice['sezione_richiesta'] ?? ''));
    $text = formazioneClassiNorm(
        (string)($practice['corso_studi'] ?? '')
        . ' ' . (string)($practice['scelta_formativa'] ?? '')
        . ' ' . (string)($practice['corso'] ?? '')
        . ' ' . (string)($practice['indirizzo'] ?? '')
    );
    if ($tabletGroup === 'digital_science' || strpos($text, 'DIGITAL SCIENCE') !== false) {
        return true;
    }
    return preg_match('/^[1-5]?DS\b/u', $section) === 1;
}

function formazioneClassiAddressOptionsFromIscrizioni(int $targetYearId, int $targetClassYear): array
{
    if (!in_array($targetClassYear, [1, 3], true)) {
        return [];
    }

    if (!formazioneClassiIscrizioniTableAvailable()) {
        return [];
    }
    $targetYear = trim((string)dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($targetYearId) . " LIMIT 1"));
    if ($targetYear === '') {
        return [];
    }

    $tipo = $targetClassYear === 3 ? 'terze' : 'prime';
    $rows = dbGetAll("
        SELECT corso_studi, scelta_formativa, sezione_richiesta, tablet_gruppo, id_indirizzo_gestore
        FROM iscrizioni_prime_pratiche p
        WHERE p.tipo_iscrizione = " . dbQ($tipo) . "
          AND (p.stato IS NULL OR p.stato <> 'annullata')
          AND COALESCE(p.raw_prime_json, '') NOT LIKE '%\"FONTE\":\"movimenti_entrata\"%'
          AND COALESCE(p.raw_prime_json, '') NOT LIKE '%\"FONTE\": \"movimenti_entrata\"%'
          AND " . iscrizioniPrimeSchoolYearWhere('p.anno_scolastico', $targetYear) . "
    ") ?: [];

    $addresses = [];
    foreach ($rows as $row) {
        $address = formazioneClassiAddressKeyFromPractice($row, $targetClassYear);
        if ($address === '') {
            continue;
        }
        $addresses[$address] = formazioneClassiAddressLabel($address);
    }
    asort($addresses, SORT_NATURAL | SORT_FLAG_CASE);
    return $addresses;
}

function formazioneClassiIscrizioniTableAvailable(): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    $row = dbGetFirst("SHOW TABLES LIKE 'iscrizioni_prime_pratiche'");
    $available = $row !== null;
    return $available;
}

function formazioneClassiTargetClasses(int $classYear, string $indirizzo = '', int $schoolYearId = 0, string $tabletFilter = 'all'): array
{
    $currentSchoolYearId = intval($GLOBALS['__anno_scolastico_corrente_id'] ?? 0);
    $activeFallback = ($schoolYearId <= 0 || ($currentSchoolYearId > 0 && $schoolYearId === $currentSchoolYearId))
        ? 'COALESCE(c.attiva, 1)'
        : '0';
    $rows = dbGetAll("
        SELECT
            c.id,
            c.classe,
            c.id_primo_indirizzo,
            c.id_secondo_indirizzo,
            COALESCE(cas.is_tablet, 0) AS is_tablet,
            COALESCE(cas.attiva, $activeFallback) AS attiva
        FROM classi c
        LEFT JOIN classi_anno_scolastico cas
            ON cas.id_classe = c.id
           AND cas.id_anno_scolastico = " . dbI($schoolYearId) . "
        WHERE c.anno = " . dbI($classYear) . "
          AND COALESCE(cas.attiva, $activeFallback) = 1
        ORDER BY c.classe ASC
    ") ?: [];

    $indirizzoNorm = formazioneClassiNorm($indirizzo);
    $tabletFilter = formazioneClassiNormalizeTabletFilter($tabletFilter);
    $result = [];
    foreach ($rows as $row) {
        $label = trim((string)($row['classe'] ?? ''));
        if ($label === '') {
            continue;
        }
        if ($indirizzoNorm !== '') {
            $gestoreAddress = preg_match('/^[1-5]DS\b/u', formazioneClassiNorm($label))
                ? 'DIGITAL SCIENCE'
                : formazioneClassiGestoreAddressKeyFromId($row['id_primo_indirizzo'] ?? 0);
            if ($gestoreAddress === '') {
                $gestoreAddress = formazioneClassiAddressKeyFromClass($label);
            }
            if (!formazioneClassiAddressKeysMatchStrict($gestoreAddress, $indirizzo)) {
                continue;
            }
        }
        if (in_array($classYear, [1, 2], true) && $tabletFilter !== 'all') {
            $isTablet = intval($row['is_tablet'] ?? 0) === 1;
            if ($tabletFilter === 'tablet' && !$isTablet) {
                continue;
            }
            if ($tabletFilter === 'non_tablet' && $isTablet) {
                continue;
            }
        }
        $result[] = [
            'id' => intval($row['id'] ?? 0),
            'label' => $label,
            'is_tablet' => intval($row['is_tablet'] ?? 0),
        ];
    }
    return $result;
}

function formazioneClassiSingleTargetClass(int $classYear, string $indirizzo, int $schoolYearId, string $tabletFilter = 'all'): ?array
{
    $classes = formazioneClassiTargetClasses($classYear, $indirizzo, $schoolYearId, $tabletFilter);
    return count($classes) === 1 ? $classes[0] : null;
}

function formazioneClassiIsActiveTargetClassLabel(string $classLabel, int $classYear, string $indirizzo, int $schoolYearId, string $tabletFilter = 'all'): bool
{
    $classLabel = trim($classLabel);
    if ($classLabel === '') {
        return false;
    }
    foreach (formazioneClassiTargetClasses($classYear, $indirizzo, $schoolYearId, $tabletFilter) as $class) {
        if (strcasecmp((string)($class['label'] ?? ''), $classLabel) === 0) {
            return true;
        }
    }
    return false;
}

function formazioneClassiNormalizeTabletFilter(string $tabletFilter): string
{
    $tabletFilter = trim($tabletFilter);
    return in_array($tabletFilter, ['tablet', 'non_tablet'], true) ? $tabletFilter : 'all';
}

function formazioneClassiAddressOptionsForFormation(int $sourceYearId, int $targetClassYear, int $targetYearId = 0): array
{
    $addresses = [];
    if ($targetClassYear === 3) {
        foreach (formazioneClassiIndirizziRows(range(1, 10)) as $row) {
            $address = formazioneClassiAddressKeyFromAddressName((string)($row['nome'] ?? ''));
            if ($address !== '') {
                $addresses[$address] = formazioneClassiAddressLabel($address);
            }
        }
    } elseif ($targetClassYear > 1) {
        foreach (formazioneClassiAddressOptions($sourceYearId, $targetClassYear - 1) as $key => $label) {
            $addresses[$key] = $label;
        }
    }
    if ($targetClassYear !== 3) {
        foreach (formazioneClassiAddressOptions($sourceYearId, $targetClassYear) as $key => $label) {
            $addresses[$key] = $label;
        }
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
    $row = dbGetFirst("SELECT id, classe, id_primo_indirizzo, id_secondo_indirizzo FROM classi WHERE classe = " . dbQ($label) . " LIMIT 1");
    return $row ?: null;
}

function formazioneClassiBestTabletClass(int $sessionId, int $classYear, string $indirizzo, int $schoolYearId): ?array
{
    $tabletClasses = [];
    foreach (formazioneClassiTargetClasses($classYear, $indirizzo, $schoolYearId) as $class) {
        if (intval($class['is_tablet'] ?? 0) === 1) {
            $tabletClasses[] = $class;
        }
    }
    if (empty($tabletClasses)) {
        return null;
    }

    $counts = [];
    $labels = array_values(array_unique(array_filter(array_map(function ($class) {
        return (string)($class['label'] ?? '');
    }, $tabletClasses))));
    if ($sessionId > 0 && !empty($labels)) {
        $rows = dbGetAll("
            SELECT classe_provvisoria_label, COUNT(*) AS totale
            FROM formazione_classi_studenti
            WHERE id_sessione = " . dbI($sessionId) . "
              AND classe_provvisoria_label IN (" . implode(',', array_map('dbQ', $labels)) . ")
            GROUP BY classe_provvisoria_label
        ") ?: [];
        foreach ($rows as $row) {
            $counts[(string)($row['classe_provvisoria_label'] ?? '')] = intval($row['totale'] ?? 0);
        }
    }

    usort($tabletClasses, function ($a, $b) use ($counts) {
        $labelA = (string)($a['label'] ?? '');
        $labelB = (string)($b['label'] ?? '');
        $countA = intval($counts[$labelA] ?? 0);
        $countB = intval($counts[$labelB] ?? 0);
        if ($countA !== $countB) {
            return $countA <=> $countB;
        }
        return strnatcasecmp($labelA, $labelB);
    });

    return $tabletClasses[0];
}

function formazioneClassiPracticeHasConfirmedTablet(array $practice): bool
{
    return intval($practice['tablet_scelto'] ?? 0) === 1
        && trim((string)($practice['tablet_stato'] ?? '')) === 'confermato';
}

function formazioneClassiBestClassByLabels(int $sessionId, array $labels, int $classYear = 0, string $indirizzo = '', int $schoolYearId = 0, string $tabletFilter = 'all'): ?array
{
    $classes = [];
    foreach (array_values(array_unique(array_filter($labels))) as $label) {
        if ($classYear > 0 && $schoolYearId > 0 && !formazioneClassiIsActiveTargetClassLabel((string)$label, $classYear, $indirizzo, $schoolYearId, $tabletFilter)) {
            continue;
        }
        $class = formazioneClassiLocalClassByLabel((string)$label);
        if ($class) {
            $classes[] = ['id' => intval($class['id'] ?? 0), 'label' => (string)($class['classe'] ?? $label)];
        }
    }
    if (!$classes) {
        return null;
    }

    $counts = [];
    $classLabels = array_map(static function ($class) {
        return (string)($class['label'] ?? '');
    }, $classes);
    if ($sessionId > 0 && $classLabels) {
        $rows = dbGetAll("
            SELECT classe_provvisoria_label, COUNT(*) AS totale
            FROM formazione_classi_studenti
            WHERE id_sessione = " . dbI($sessionId) . "
              AND classe_provvisoria_label IN (" . implode(',', array_map('dbQ', $classLabels)) . ")
            GROUP BY classe_provvisoria_label
        ") ?: [];
        foreach ($rows as $row) {
            $counts[(string)($row['classe_provvisoria_label'] ?? '')] = intval($row['totale'] ?? 0);
        }
    }

    usort($classes, static function ($a, $b) use ($counts) {
        $labelA = (string)($a['label'] ?? '');
        $labelB = (string)($b['label'] ?? '');
        $countA = intval($counts[$labelA] ?? 0);
        $countB = intval($counts[$labelB] ?? 0);
        if ($countA !== $countB) {
            return $countA <=> $countB;
        }
        return strnatcasecmp($labelA, $labelB);
    });

    return $classes[0];
}

function formazioneClassiCatCurvatureLabels(array $practice): array
{
    $curvature = strtolower(trim((string)($practice['curvatura_design'] ?? '')));
    if ($curvature === 'design') {
        return ['3CTC', '3CTD'];
    }
    if ($curvature === 'normale') {
        return ['3CTA', '3CTB'];
    }
    return [];
}

function formazioneClassiClassLabelAddressKey(string $classLabel): string
{
    if (preg_match('/^[1-5]DS\b/u', formazioneClassiNorm($classLabel))) {
        return 'DIGITAL SCIENCE';
    }
    $classAddress = formazioneClassiAddressKeyFromClass($classLabel);
    if ($classAddress !== '') {
        return $classAddress;
    }
    $localClass = formazioneClassiLocalClassByLabel($classLabel);
    if ($localClass) {
        $address = formazioneClassiGestoreAddressKeyFromId($localClass['id_primo_indirizzo'] ?? 0);
        if ($address !== '') {
            return $address;
        }
    }
    return '';
}

function formazioneClassiCanonicalClassLabel(string $classLabel): string
{
    $classLabel = trim($classLabel);
    $norm = formazioneClassiNorm($classLabel);
    if (preg_match('/^([1-5])DS\b/u', $norm, $matches)) {
        return $matches[1] . 'DS';
    }
    $exact = formazioneClassiLocalClassByLabel($classLabel);
    if ($exact) {
        return (string)$exact['classe'];
    }
    if (preg_match('/^([1-5][A-Z0-9]+)\b/u', $norm, $matches)) {
        $local = formazioneClassiLocalClassByLabel($matches[1]);
        if ($local) {
            return (string)$local['classe'];
        }
    }
    return $classLabel;
}

function formazioneClassiNextClassLabel(string $classLabel): string
{
    $classLabel = formazioneClassiCanonicalClassLabel($classLabel);
    if (preg_match('/^([1-4])(.*)$/u', $classLabel, $m)) {
        return (string)(intval($m[1]) + 1) . $m[2];
    }
    return $classLabel;
}

function formazioneClassiTabelloneRows(int $sourceYearId, int $classYear, string $indirizzo, array $outcomes): array
{
    mastercomTabelloniEnsureTables();
    formazioneClassiRefreshTabelloniOnce();

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
            st_gestore.codice_fiscale AS codice_fiscale_gestore,
            cls_summary.id AS id_classe_gestore_studente,
            cls_summary.classe AS classe_gestore_studente,
            cls_summary.id_primo_indirizzo AS id_primo_indirizzo_gestore_studente,
            cls_summary.id_secondo_indirizzo AS id_secondo_indirizzo_gestore_studente,
            mcls_summary.nome AS classe_mastercom_studente,
            GROUP_CONCAT(CONCAT_WS('|', v.materia_codice, v.tipo_colonna, COALESCE(v.valore_num, '')) SEPARATOR '\n') AS valori
        FROM mastercom_tabelloni_scrutini t
        INNER JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
        LEFT JOIN mastercom_tabelloni_scrutini_voti v ON v.tabellone_studente_id = s.id
        LEFT JOIN studente st_gestore ON st_gestore.id = s.id_studente_gestore
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
                 st_gestore.codice_fiscale,
                 cls_summary.id, cls_summary.classe, cls_summary.id_primo_indirizzo, cls_summary.id_secondo_indirizzo,
                 mcls_summary.nome
        ORDER BY t.classe ASC, s.studente_nome ASC
    ") ?: [];

    $outcomeSet = array_fill_keys($outcomes, true);
    $indirizzoNorm = formazioneClassiNorm($indirizzo);
    $result = [];
    foreach ($rows as $row) {
        $classLabel = mastercomTabelloniSummaryEffectiveClassLabel($row);
        $gestoreClassLabel = trim((string)($row['classe_gestore_studente'] ?? ''));
        $filterLabel = $gestoreClassLabel !== '' ? $gestoreClassLabel : $classLabel;
        if (mastercomTabelloniClassYearFromName($filterLabel) !== $classYear) {
            continue;
        }
        if ($indirizzoNorm !== '' && !formazioneClassiAddressKeysMatch(formazioneClassiClassLabelAddressKey($filterLabel), $indirizzo)) {
            continue;
        }
        $outcome = (string)($row['esito_key'] ?? '');
        if (!isset($outcomeSet[$outcome])) {
            continue;
        }
        $metrics = formazioneClassiMetricsFromValues((string)($row['valori'] ?? ''), $row['media'] ?? null);
        $row['classe_effettiva'] = formazioneClassiCanonicalClassLabel($filterLabel);
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

    formazioneClassiNormalizeDigitalScienceAssignments($sessionId);
    formazioneClassiPruneMastercomRowsByAddress($sessionId, $indirizzo, $targetClassYear);
    formazioneClassiPruneTerzaDigitalScienceRows($sessionId, $indirizzo, $targetClassYear);
    formazioneClassiPruneRowsByProvisionalAddress($sessionId, $indirizzo);
    formazioneClassiPruneCatCurvatureAssignments($sessionId, $targetClassYear);

    if ($targetClassYear === 3) {
        $promossi = formazioneClassiTabelloneRows($sourceYearId, 2, '', ['ammesso', 'anno_estero']);
        foreach ($promossi as $row) {
            if (!formazioneClassiSecondaPromossaMatchesTerzaAddress($row, $session, $indirizzo)) {
                continue;
            }
            $targetClass = formazioneClassiTargetClassForTerza($row, $session, $indirizzo);
            formazioneClassiUpsertStudent($sessionId, $row, 'promosso', $targetClass['id'] !== null ? intval($targetClass['id']) : null, $targetClass['label'] ?? null);
        }
        $bocciatiSeconda = formazioneClassiTabelloneRows($sourceYearId, 2, '', ['non_ammesso', 'in_corso']);
        foreach ($bocciatiSeconda as $row) {
            if (!formazioneClassiSecondaPromossaMatchesTerzaAddress($row, $session, $indirizzo)) {
                continue;
            }
            formazioneClassiUpsertStudent($sessionId, $row, 'bocciato', null, null);
        }
    } elseif ($targetClassYear > 1) {
        $promossi = formazioneClassiTabelloneRows($sourceYearId, $targetClassYear - 1, $indirizzo, ['ammesso', 'anno_estero']);
        foreach ($promossi as $row) {
            $sourceLabel = (string)($row['classe_effettiva'] ?? '');
            $targetLabel = formazioneClassiNextClassLabel($sourceLabel);
            if (!formazioneClassiIsActiveTargetClassLabel($targetLabel, $targetClassYear, $indirizzo, intval($session['id_anno_scolastico_target'] ?? 0))) {
                $targetLabel = null;
                $targetClass = null;
            } else {
                $targetClass = formazioneClassiLocalClassByLabel($targetLabel);
            }
            formazioneClassiUpsertStudent($sessionId, $row, 'promosso', $targetClass ? intval($targetClass['id']) : null, $targetLabel);
        }
    }

    $bocciati = formazioneClassiTabelloneRows($sourceYearId, $targetClassYear, $indirizzo, ['non_ammesso', 'in_corso']);
    foreach ($bocciati as $row) {
        formazioneClassiUpsertStudent($sessionId, $row, 'bocciato', null, null);
    }

    formazioneClassiSyncIscrizioni($session, $targetClassYear, $indirizzo);
    formazioneClassiSyncMovimenti($session, $targetClassYear, $indirizzo);
    formazioneClassiPrunePrimeRowsByPracticeAddress($sessionId, $targetClassYear, $indirizzo, intval($session['id_anno_scolastico_target'] ?? 0));
    formazioneClassiPruneTerzaRowsByPracticeAddress($sessionId, $targetClassYear, $indirizzo, intval($session['id_anno_scolastico_target'] ?? 0));
    formazioneClassiPruneCatCurvatureAssignments($sessionId, $targetClassYear);
    formazioneClassiClearPendingOutgoingOnlyLocks($sessionId);
}

function formazioneClassiClearPendingOutgoingOnlyLocks(int $sessionId): void
{
    if ($sessionId <= 0) {
        return;
    }
    $confirmedStates = implode(',', array_map('dbQ', formazioneClassiConfirmedOutgoingStates()));
    dbExec("
        UPDATE formazione_classi_studenti f
        SET f.blocco_individuale = 0,
            f.bloccato = CASE WHEN COALESCE(f.blocco_classe, 0) = 1 THEN 1 ELSE 0 END,
            f.updated_at = NOW()
        WHERE f.id_sessione = " . dbI($sessionId) . "
          AND f.gruppo_origine = 'bocciato'
          AND COALESCE(f.blocco_individuale, 0) = 1
          AND COALESCE(f.blocco_classe, 0) = 0
          AND EXISTS (
              SELECT 1
              FROM studenti_movimenti_pratiche m_pending
              WHERE m_pending.id_studente = f.id_studente
                AND m_pending.tipo_pratica IN ('uscita', 'ritiro')
                AND m_pending.stato_pratica <> 'annullata'
                AND m_pending.stato_pratica NOT IN ($confirmedStates)
              LIMIT 1
          )
          AND NOT EXISTS (
              SELECT 1
              FROM studenti_movimenti_pratiche m_confirmed
              WHERE m_confirmed.id_studente = f.id_studente
                AND m_confirmed.tipo_pratica IN ('uscita', 'ritiro')
                AND m_confirmed.stato_pratica IN ($confirmedStates)
              LIMIT 1
          )
    ");
}

function formazioneClassiPruneCatCurvatureAssignments(int $sessionId, int $targetClassYear): void
{
    if ($sessionId <= 0 || !in_array($targetClassYear, [3, 4], true)) {
        return;
    }
    $rows = dbGetAll("
        SELECT id, note, classe_provvisoria_label
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND COALESCE(bloccato, 0) = 0
          AND TRIM(COALESCE(classe_provvisoria_label, '')) <> ''
    ") ?: [];
    foreach ($rows as $row) {
        $label = trim((string)($row['classe_provvisoria_label'] ?? ''));
        if ($label === '' || formazioneClassiAutoRowCompatibleWithLabel($row, $label, $targetClassYear)) {
            continue;
        }
        dbExec("
            UPDATE formazione_classi_studenti
            SET id_classe_provvisoria = NULL,
                classe_provvisoria_label = NULL,
                assegnazione_manuale = 0,
                updated_at = NOW()
            WHERE id = " . dbI($row['id'] ?? 0) . "
            LIMIT 1
        ");
    }
}

function formazioneClassiNormalizeDigitalScienceAssignments(int $sessionId): void
{
    if ($sessionId <= 0) {
        return;
    }

    $rows = dbGetAll("
        SELECT id, classe_provvisoria_label
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND TRIM(COALESCE(classe_provvisoria_label, '')) <> ''
    ") ?: [];

    foreach ($rows as $row) {
        $canonical = formazioneClassiCanonicalClassLabel((string)($row['classe_provvisoria_label'] ?? ''));
        if ($canonical === '' || $canonical === (string)($row['classe_provvisoria_label'] ?? '')) {
            continue;
        }
        $targetClass = formazioneClassiLocalClassByLabel($canonical);
        dbExec("
            UPDATE formazione_classi_studenti SET
                id_classe_provvisoria = " . dbI($targetClass ? intval($targetClass['id']) : null) . ",
                classe_provvisoria_label = " . dbQ($canonical) . ",
                updated_at = NOW()
            WHERE id = " . dbI($row['id'] ?? 0) . "
            LIMIT 1
        ");
    }
}

function formazioneClassiPruneMastercomRowsByAddress(int $sessionId, string $indirizzo, int $targetClassYear): void
{
    if ($sessionId <= 0 || trim($indirizzo) === '') {
        return;
    }
    $rows = dbGetAll("
        SELECT id, classe_origine_label
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND fonte_valori = 'mastercom'
          AND COALESCE(bloccato, 0) = 0
          AND COALESCE(assegnazione_manuale, 0) = 0
          AND TRIM(COALESCE(classe_origine_label, '')) <> ''
    ") ?: [];
    foreach ($rows as $row) {
        $classLabel = (string)($row['classe_origine_label'] ?? '');
        $classYear = mastercomTabelloniClassYearFromName($classLabel);
        if ($classYear > 0 && $classYear !== $targetClassYear) {
            continue;
        }
        $address = formazioneClassiClassLabelAddressKey($classLabel);
        if ($address !== '' && !formazioneClassiAddressKeysMatchStrict($address, $indirizzo)) {
            dbExec("DELETE FROM formazione_classi_studenti WHERE id = " . dbI($row['id'] ?? 0) . " LIMIT 1");
        }
    }
}

function formazioneClassiPruneTerzaDigitalScienceRows(int $sessionId, string $indirizzo, int $targetClassYear): void
{
    if ($sessionId <= 0 || $targetClassYear !== 3 || trim($indirizzo) === '') {
        return;
    }
    if (formazioneClassiAddressKeysMatchStrict('DIGITAL SCIENCE', $indirizzo)) {
        return;
    }
    $rows = dbGetAll("
        SELECT id, classe_origine_label, classe_provvisoria_label
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND fonte_valori IN ('mastercom', 'iscrizioni')
          AND COALESCE(bloccato, 0) = 0
          AND COALESCE(assegnazione_manuale, 0) = 0
          AND (
              TRIM(COALESCE(classe_origine_label, '')) <> ''
              OR TRIM(COALESCE(classe_provvisoria_label, '')) <> ''
          )
    ") ?: [];
    foreach ($rows as $row) {
        $origin = formazioneClassiNorm((string)($row['classe_origine_label'] ?? ''));
        $target = formazioneClassiNorm((string)($row['classe_provvisoria_label'] ?? ''));
        if (preg_match('/^2DS\b/u', $origin) || preg_match('/^3DS\b/u', $target)) {
            dbExec("DELETE FROM formazione_classi_studenti WHERE id = " . dbI($row['id'] ?? 0) . " LIMIT 1");
        }
    }
}

function formazioneClassiPruneRowsByProvisionalAddress(int $sessionId, string $indirizzo): void
{
    if ($sessionId <= 0 || trim($indirizzo) === '') {
        return;
    }
    $rows = dbGetAll("
        SELECT id, classe_provvisoria_label
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND fonte_valori IN ('mastercom', 'iscrizioni', 'movimenti')
          AND COALESCE(bloccato, 0) = 0
          AND COALESCE(assegnazione_manuale, 0) = 0
          AND TRIM(COALESCE(classe_provvisoria_label, '')) <> ''
    ") ?: [];
    foreach ($rows as $row) {
        $address = formazioneClassiClassLabelAddressKey((string)($row['classe_provvisoria_label'] ?? ''));
        if ($address !== '' && !formazioneClassiAddressKeysMatchStrict($address, $indirizzo)) {
            dbExec("
                UPDATE formazione_classi_studenti SET
                    id_classe_provvisoria = NULL,
                    classe_provvisoria_label = NULL,
                    updated_at = NOW()
                WHERE id = " . dbI($row['id'] ?? 0) . "
                LIMIT 1
            ");
        }
    }
}

function formazioneClassiPruneTerzaRowsByPracticeAddress(int $sessionId, int $targetClassYear, string $indirizzo, int $targetYearId): void
{
    if ($sessionId <= 0 || $targetClassYear !== 3 || $targetYearId <= 0 || trim($indirizzo) === '') {
        return;
    }

    $rows = dbGetAll("
        SELECT f.id, f.classe_origine_label, f.fonte_valori, f.gruppo_origine, s.codice_fiscale
        FROM formazione_classi_studenti f
        LEFT JOIN studente s ON s.id = f.id_studente
        WHERE f.id_sessione = " . dbI($sessionId) . "
          AND (
              f.fonte_valori = 'iscrizioni'
              OR f.fonte_valori = 'mastercom'
          )
          AND TRIM(COALESCE(s.codice_fiscale, '')) <> ''
    ") ?: [];

    foreach ($rows as $row) {
        $practice = formazioneClassiTerzaPracticeByCf($targetYearId, (string)($row['codice_fiscale'] ?? ''));
        if (intval($practice['id_indirizzo_gestore'] ?? 0) > 0) {
            $practiceAddress = formazioneClassiAddressKeyFromPractice($practice, 3);
        } elseif (preg_match('/^2DS\b/u', formazioneClassiNorm((string)($row['classe_origine_label'] ?? '')))) {
            $practiceAddress = 'DIGITAL SCIENCE';
        } else {
            $practiceAddress = '';
        }
        if ($practiceAddress === ''
            && (string)($row['fonte_valori'] ?? '') === 'mastercom'
            && (string)($row['gruppo_origine'] ?? '') === 'bocciato') {
            continue;
        }
        if ($practiceAddress === '' || !formazioneClassiAddressKeysMatchStrict($practiceAddress, $indirizzo)) {
            dbExec("DELETE FROM formazione_classi_studenti WHERE id = " . dbI($row['id'] ?? 0) . " LIMIT 1");
        }
    }
}

function formazioneClassiPrunePrimeRowsByPracticeAddress(int $sessionId, int $targetClassYear, string $indirizzo, int $targetYearId): void
{
    if ($sessionId <= 0 || $targetClassYear !== 1 || $targetYearId <= 0 || trim($indirizzo) === '') {
        return;
    }
    if (!formazioneClassiIscrizioniTableAvailable()) {
        return;
    }

    $targetYear = trim((string)dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($targetYearId) . " LIMIT 1"));
    if ($targetYear === '') {
        return;
    }

    $rows = dbGetAll("
        SELECT f.id, s.codice_fiscale
        FROM formazione_classi_studenti f
        LEFT JOIN studente s ON s.id = f.id_studente
        WHERE f.id_sessione = " . dbI($sessionId) . "
          AND f.fonte_valori = 'iscrizioni'
          AND TRIM(COALESCE(s.codice_fiscale, '')) <> ''
    ") ?: [];

    foreach ($rows as $row) {
        $practice = dbGetFirst("
            SELECT *
            FROM iscrizioni_prime_pratiche p
            WHERE p.tipo_iscrizione = 'prime'
              AND (p.stato IS NULL OR p.stato <> 'annullata')
              AND " . iscrizioniPrimeSchoolYearWhere('p.anno_scolastico', $targetYear) . "
              AND UPPER(TRIM(p.codice_fiscale)) = " . dbQ(strtoupper(trim((string)($row['codice_fiscale'] ?? '')))) . "
            ORDER BY p.updated_at DESC, p.id DESC
            LIMIT 1
        ") ?: [];
        $practiceAddress = $practice
            ? (formazioneClassiPracticeIsDigitalScience($practice) ? 'DIGITAL SCIENCE' : formazioneClassiAddressKeyFromPractice($practice, 1))
            : '';
        if ($practiceAddress === '' || !formazioneClassiAddressKeysMatchStrict($practiceAddress, $indirizzo)) {
            dbExec("DELETE FROM formazione_classi_studenti WHERE id = " . dbI($row['id'] ?? 0) . " LIMIT 1");
        }
    }
}

function formazioneClassiSecondaPromossaMatchesTerzaAddress(array $row, array $session, string $indirizzo): bool
{
    $indirizzo = trim($indirizzo);
    if ($indirizzo === '') {
        return true;
    }

    $targetYearId = intval($session['id_anno_scolastico_target'] ?? 0);
    $cf = strtoupper(trim((string)($row['codice_fiscale_gestore'] ?? '')));
    if ($targetYearId <= 0 || $cf === '') {
        return false;
    }

    $practice = formazioneClassiTerzaPracticeByCf($targetYearId, $cf);
    $practiceAddress = intval($practice['id_indirizzo_gestore'] ?? 0) > 0
        ? formazioneClassiAddressKeyFromPractice($practice, 3)
        : '';
    $sourceLabel = (string)($row['classe_effettiva'] ?? '');
    if ($practiceAddress === '' && preg_match('/^2DS\b/u', formazioneClassiNorm($sourceLabel))) {
        $practiceAddress = 'DIGITAL SCIENCE';
    }
    if ($practiceAddress === '') {
        return false;
    }

    return formazioneClassiAddressKeysMatchStrict($practiceAddress, $indirizzo);
}

function formazioneClassiTerzaPracticeByCf(int $targetYearId, string $codiceFiscale): array
{
    static $cache = [];
    $codiceFiscale = strtoupper(trim($codiceFiscale));
    if ($targetYearId <= 0 || $codiceFiscale === '' || !formazioneClassiIscrizioniTableAvailable()) {
        return [];
    }
    if (!isset($cache[$targetYearId])) {
        $targetYear = trim((string)dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($targetYearId) . " LIMIT 1"));
        $cache[$targetYearId] = [];
        if ($targetYear !== '') {
            $rows = dbGetAll("
                SELECT p.*
                FROM iscrizioni_prime_pratiche p
                WHERE p.tipo_iscrizione = 'terze'
                  AND (p.stato IS NULL OR p.stato <> 'annullata')
                  AND " . iscrizioniPrimeSchoolYearWhere('p.anno_scolastico', $targetYear) . "
            ") ?: [];
            foreach ($rows as $practice) {
                $cf = strtoupper(trim((string)($practice['codice_fiscale'] ?? '')));
                if ($cf === '') {
                    continue;
                }
                $cache[$targetYearId][$cf] = $practice;
            }
        }
    }

    return (array)($cache[$targetYearId][$codiceFiscale] ?? []);
}

function formazioneClassiTargetClassForTerza(array $row, array $session, string $indirizzo): array
{
    $sessionId = intval($session['id'] ?? 0);
    $targetYearId = intval($session['id_anno_scolastico_target'] ?? 0);
    $cf = strtoupper(trim((string)($row['codice_fiscale_gestore'] ?? '')));
    $practice = $cf !== '' ? formazioneClassiTerzaPracticeByCf($targetYearId, $cf) : [];
    $labels = [];

    if (!empty($practice)) {
        $curvatureClass = formazioneClassiBestClassByLabels($sessionId, formazioneClassiCatCurvatureLabels($practice), 3, $indirizzo, $targetYearId);
        if ($curvatureClass !== null) {
            return ['id' => intval($curvatureClass['id'] ?? 0), 'label' => (string)($curvatureClass['label'] ?? '')];
        }
    }

    $practiceLabel = !empty($practice) ? formazioneClassiTargetLabelFromPractice($practice, 3) : null;
    if ($practiceLabel !== null) {
        $labels[] = $practiceLabel;
    }

    $sourceLabel = (string)($row['classe_effettiva'] ?? '');
    $explicitPracticeAddress = intval($practice['id_indirizzo_gestore'] ?? 0) > 0
        ? formazioneClassiAddressKeyFromPractice($practice, 3)
        : '';
    if (($explicitPracticeAddress === '' || formazioneClassiAddressKeysMatchStrict($explicitPracticeAddress, 'DIGITAL SCIENCE'))
        && preg_match('/^2DS\b/u', formazioneClassiNorm($sourceLabel))) {
        $labels[] = '3DS';
    }

    foreach (array_values(array_unique(array_filter($labels))) as $label) {
        if (!formazioneClassiIsActiveTargetClassLabel((string)$label, 3, $indirizzo, $targetYearId)) {
            continue;
        }
        $class = formazioneClassiLocalClassByLabel($label);
        if (!$class) {
            continue;
        }
        $address = formazioneClassiClassLabelAddressKey((string)($class['classe'] ?? $label));
        if ($indirizzo === '' || formazioneClassiAddressKeysMatchStrict($address, $indirizzo)) {
            return ['id' => intval($class['id'] ?? 0), 'label' => (string)($class['classe'] ?? $label)];
        }
    }

    $singleClass = formazioneClassiSingleTargetClass(3, $indirizzo, $targetYearId);
    if ($singleClass !== null) {
        return ['id' => intval($singleClass['id'] ?? 0), 'label' => (string)($singleClass['label'] ?? '')];
    }

    return ['id' => null, 'label' => null];
}

function formazioneClassiSecondaOutcomeByCf(int $sourceYearId, string $codiceFiscale): string
{
    static $cache = [];
    $codiceFiscale = strtoupper(trim($codiceFiscale));
    if ($sourceYearId <= 0 || $codiceFiscale === '') {
        return '';
    }
    if (!isset($cache[$sourceYearId])) {
        $cache[$sourceYearId] = [];
        foreach (['ammesso', 'anno_estero', 'non_ammesso', 'in_corso'] as $outcome) {
            foreach (formazioneClassiTabelloneRows($sourceYearId, 2, '', [$outcome]) as $row) {
                $cf = strtoupper(trim((string)($row['codice_fiscale_gestore'] ?? '')));
                if ($cf !== '') {
                    $cache[$sourceYearId][$cf] = (string)($row['esito_key'] ?? $outcome);
                }
            }
        }
    }
    return (string)($cache[$sourceYearId][$codiceFiscale] ?? '');
}

function formazioneClassiTargetLabelFromPractice(array $practice, int $targetClassYear): ?string
{
    $section = strtoupper(trim((string)($practice['sezione_richiesta'] ?? '')));
    if ($section === '') {
        return null;
    }
    $section = preg_replace('/[^A-Z0-9]/u', '', $section);
    if ($section === '') {
        return null;
    }
    if (preg_match('/^[1-5]/u', $section)) {
        return $section;
    }
    return (string)$targetClassYear . $section;
}

function formazioneClassiStudentHasBocciatoReiscrizione(int $studentId): bool
{
    if ($studentId <= 0) {
        return false;
    }
    return dbGetFirst("
        SELECT id
        FROM studenti_movimenti_pratiche
        WHERE id_studente = " . dbI($studentId) . "
          AND tipo_pratica = 'bocciato_reiscrizione'
          AND stato_pratica <> 'annullata'
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ") !== null;
}

function formazioneClassiSyncIscrizioni(array $session, int $targetClassYear, string $indirizzo): void
{
    if (!in_array($targetClassYear, [1, 3], true)) {
        return;
    }

    $sessionId = intval($session['id'] ?? 0);
    $targetYearId = intval($session['id_anno_scolastico_target'] ?? 0);
    if ($sessionId <= 0 || $targetYearId <= 0) {
        return;
    }

    if (!formazioneClassiIscrizioniTableAvailable()) {
        return;
    }
    $targetYear = trim((string)dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($targetYearId) . " LIMIT 1"));
    if ($targetYear === '') {
        return;
    }
    $pagellaVotesSelect = ",
            NULL AS pagella_voto_italiano,
            NULL AS pagella_voto_matematica";
    if (dbGetFirst("SHOW TABLES LIKE 'iscrizioni_prime_voti'") !== null) {
        $pagellaVotesSelect = ",
            (
                SELECT v.voto
                FROM iscrizioni_prime_voti v
                WHERE v.pratica_id = p.id
                  AND v.materia = 'Italiano'
                ORDER BY v.updated_at DESC, v.id DESC
                LIMIT 1
            ) AS pagella_voto_italiano,
            (
                SELECT v.voto
                FROM iscrizioni_prime_voti v
                WHERE v.pratica_id = p.id
                  AND v.materia = 'Matematica'
                ORDER BY v.updated_at DESC, v.id DESC
                LIMIT 1
            ) AS pagella_voto_matematica";
    }

    $tipo = $targetClassYear === 3 ? 'terze' : 'prime';
    $rows = dbGetAll("
        SELECT
            p.*,
            s.id AS id_studente_gestore,
            s.cognome AS studente_cognome,
            s.nome AS studente_nome,
            sf_orig.id_classe AS id_classe_origine,
            c_orig.classe AS classe_origine
            " . $pagellaVotesSelect . "
        FROM iscrizioni_prime_pratiche p
        LEFT JOIN studente s ON UPPER(TRIM(s.codice_fiscale)) = UPPER(TRIM(p.codice_fiscale))
        LEFT JOIN studente_frequenta sf_orig ON sf_orig.id = (
            SELECT sf2.id
            FROM studente_frequenta sf2
            WHERE sf2.id_studente = s.id
              AND sf2.id_anno_scolastico = " . dbI($session['id_anno_scolastico_origine'] ?? 0) . "
            ORDER BY sf2.id DESC
            LIMIT 1
        )
        LEFT JOIN classi c_orig ON c_orig.id = sf_orig.id_classe
        WHERE p.tipo_iscrizione = " . dbQ($tipo) . "
          AND (p.stato IS NULL OR p.stato <> 'annullata')
          AND " . iscrizioniPrimeSchoolYearWhere('p.anno_scolastico', $targetYear) . "
        ORDER BY p.cognome ASC, p.nome ASC
    ") ?: [];

    $indirizzoNorm = formazioneClassiNorm($indirizzo);
    foreach ($rows as $practice) {
        if (intval($practice['id_studente_gestore'] ?? 0) <= 0) {
            $practice['id_studente_gestore'] = iscrizioniPrimeUpsertGestoreStudent($practice);
            $practice['studente_cognome'] = $practice['cognome'] ?? '';
            $practice['studente_nome'] = $practice['nome'] ?? '';
        }

        $studentId = intval($practice['id_studente_gestore'] ?? 0);
        $isOrigin2Ds = $targetClassYear === 3
            && preg_match('/^2DS\b/u', formazioneClassiNorm((string)($practice['classe_origine'] ?? '')));
        $hasExplicitTerzaAddress = $targetClassYear === 3 && intval($practice['id_indirizzo_gestore'] ?? 0) > 0;
        $explicitTerzaAddress = $hasExplicitTerzaAddress ? formazioneClassiAddressKeyFromPractice($practice, 3) : '';
        if ($targetClassYear === 3 && !$isOrigin2Ds && intval($practice['id_indirizzo_gestore'] ?? 0) <= 0) {
            continue;
        }

        $address = formazioneClassiAddressKeyFromPractice($practice, $targetClassYear);
        $isBocciatoReiscrizione = formazioneClassiStudentHasBocciatoReiscrizione($studentId);
        $secondaOutcome = $targetClassYear === 3
            ? formazioneClassiSecondaOutcomeByCf(intval($session['id_anno_scolastico_origine'] ?? 0), (string)($practice['codice_fiscale'] ?? ''))
            : '';
        if (in_array($secondaOutcome, ['non_ammesso', 'in_corso'], true)) {
            $isBocciatoReiscrizione = true;
        }
        $targetClass = null;
        $targetLabel = $isBocciatoReiscrizione ? null : formazioneClassiTargetLabelFromPractice($practice, $targetClassYear);
        if ($targetClassYear === 1 && formazioneClassiPracticeIsDigitalScience($practice)) {
            $address = 'DIGITAL SCIENCE';
            if (!$isBocciatoReiscrizione) {
                $targetLabel = '1DS';
            }
        }
        if ($targetClassYear === 1
            && $indirizzoNorm !== ''
            && !formazioneClassiAddressKeysMatchStrict($address, $indirizzo)) {
            continue;
        }
        if ($targetClassYear === 1
            && !$isBocciatoReiscrizione
            && !formazioneClassiPracticeIsDigitalScience($practice)
            && formazioneClassiPracticeHasConfirmedTablet($practice)) {
            $tabletClass = formazioneClassiBestTabletClass($sessionId, 1, $indirizzo, $targetYearId);
            if ($tabletClass !== null) {
                $targetLabel = (string)($tabletClass['label'] ?? '');
            }
        }
        if ($targetClassYear === 3 && !$isBocciatoReiscrizione) {
            $curvatureClass = formazioneClassiBestClassByLabels($sessionId, formazioneClassiCatCurvatureLabels($practice), $targetClassYear, $indirizzo, $targetYearId);
            if ($curvatureClass !== null) {
                $targetClass = ['id' => intval($curvatureClass['id'] ?? 0), 'classe' => (string)($curvatureClass['label'] ?? '')];
                $targetLabel = (string)($curvatureClass['label'] ?? '');
            }
        }
        if ($isOrigin2Ds && (!$hasExplicitTerzaAddress || formazioneClassiAddressKeysMatchStrict($explicitTerzaAddress, 'DIGITAL SCIENCE'))) {
            $address = 'DIGITAL SCIENCE';
            if (!$isBocciatoReiscrizione) {
                $targetLabel = '3DS';
            }
        }
        if ($targetLabel !== null && preg_match('/^[1-5]DS\b/u', formazioneClassiNorm($targetLabel))) {
            $address = 'DIGITAL SCIENCE';
        }
        if ($indirizzoNorm !== '' && !formazioneClassiAddressKeysMatchStrict($address, $indirizzo)) {
            continue;
        }

        if ($targetLabel !== null && !formazioneClassiIsActiveTargetClassLabel($targetLabel, $targetClassYear, $indirizzo, $targetYearId)) {
            $targetClass = null;
            $targetLabel = null;
        }
        $targetClass = $targetClass ?? ($targetLabel !== null ? formazioneClassiLocalClassByLabel($targetLabel) : null);
        if ($targetClass && $indirizzoNorm !== '' && !formazioneClassiClassMatchesAddress($targetClass, $indirizzo)) {
            $targetClass = null;
            $targetLabel = null;
        }
        if (!$targetClass) {
            $singleClass = !$isBocciatoReiscrizione
                ? formazioneClassiSingleTargetClass($targetClassYear, $indirizzo, $targetYearId)
                : null;
            if ($singleClass !== null) {
                $targetClass = ['id' => intval($singleClass['id'] ?? 0), 'classe' => (string)($singleClass['label'] ?? '')];
                $targetLabel = (string)($singleClass['label'] ?? '');
            } else {
                $targetLabel = null;
            }
        }

        $originClass = (string)($practice['classe_origine'] ?? '');
        $originYear = mastercomTabelloniClassYearFromName($originClass);
        $isExternalProvisionalOrigin = formazioneClassiIsExternalProvisionalClass($originClass);
        $isInternalPractice = !$isExternalProvisionalOrigin
            && (!empty($practice['studente_interno']) || intval($practice['id_classe_origine'] ?? 0) > 0 || $originYear > 0);
        $group = $isBocciatoReiscrizione ? 'bocciato' : ($isInternalPractice ? 'promosso' : 'neo_iscritto');
        formazioneClassiUpsertPracticeStudent(
            $sessionId,
            $practice,
            $group,
            $targetClass ? intval($targetClass['id']) : null,
            $targetLabel
        );
    }
}

function formazioneClassiIsExternalProvisionalClass(string $classLabel): bool
{
    $norm = formazioneClassiNorm($classLabel);
    return in_array($norm, ['EE', 'MEDIE'], true);
}

function formazioneClassiUpsertPracticeStudent(int $sessionId, array $practice, string $gruppo, ?int $targetClassId, ?string $targetLabel): void
{
    $studentId = intval($practice['id_studente_gestore'] ?? 0);
    if ($sessionId <= 0 || $studentId <= 0) {
        return;
    }

    $name = trim((string)(($practice['studente_cognome'] ?? $practice['cognome'] ?? '') . ' ' . ($practice['studente_nome'] ?? $practice['nome'] ?? '')));
    $metrics = formazioneClassiMetricsFromPractice($practice);
    $practiceNotes = formazioneClassiPracticeNotes($practice);
    $consiglioOrientativo = trim((string)($practice['consiglio_orientativo'] ?? ''));
    $tabletRequest = formazioneClassiPracticeHasConfirmedTablet($practice) ? 1 : 0;
    $existing = dbGetFirst("
        SELECT id, bloccato, assegnazione_manuale, fonte_valori, classe_provvisoria_label
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND id_studente = " . dbI($studentId) . "
        LIMIT 1
    ");
    if ($existing !== null) {
        $sets = [
            "studente_nome = COALESCE(NULLIF(studente_nome, ''), " . dbQ($name) . ")",
            "gruppo_origine = CASE
                    WHEN gruppo_origine = 'bocciato' THEN gruppo_origine
                    ELSE " . dbQ($gruppo) . "
                END",
            "classe_origine_label = COALESCE(NULLIF(classe_origine_label, ''), " . dbQ($practice['classe_origine'] ?? '') . ")",
            "richiesta_tablet = " . dbI($tabletRequest),
            "fonte_valori = 'iscrizioni'",
            "note = " . dbQ($practiceNotes),
            "consiglio_orientativo = " . dbQ($consiglioOrientativo !== '' ? $consiglioOrientativo : null),
            "updated_at = NOW()",
        ];
        $metricFields = [
            'media_generale',
            'voto_matematica',
            'voto_italiano',
            'voto_capacita_relazionale',
        ];
        foreach ($metricFields as $field) {
            $valueSql = dbF($metrics[$field] ?? null);
            if ((string)($existing['fonte_valori'] ?? '') === 'iscrizioni') {
                $sets[] = $field . " = " . $valueSql;
            } else {
                $sets[] = $field . " = COALESCE(" . $field . ", " . $valueSql . ")";
            }
        }
        if (intval($existing['bloccato'] ?? 0) === 0 && intval($existing['assegnazione_manuale'] ?? 0) === 0) {
            if ($targetLabel !== null && trim((string)$targetLabel) !== '') {
                $sets[] = "id_classe_provvisoria = " . dbI($targetClassId);
                $sets[] = "classe_provvisoria_label = " . dbQ($targetLabel);
            } elseif ($gruppo === 'neo_iscritto' || trim((string)($existing['classe_provvisoria_label'] ?? '')) === '') {
                $sets[] = "id_classe_provvisoria = NULL";
                $sets[] = "classe_provvisoria_label = NULL";
            }
        }
        dbExec("
            UPDATE formazione_classi_studenti SET
                " . implode(",\n                ", $sets) . "
            WHERE id = " . dbI($existing['id'] ?? 0) . "
            LIMIT 1
        ");
        return;
    }

    dbExec("
        INSERT INTO formazione_classi_studenti (
            id_sessione, id_studente, studente_nome, id_classe_origine, classe_origine_label,
            id_classe_provvisoria, classe_provvisoria_label, gruppo_origine,
            richiesta_tablet,
            media_generale, voto_matematica, voto_italiano, voto_capacita_relazionale,
            fonte_valori, note, consiglio_orientativo, created_at, updated_at
        ) VALUES (
            " . dbI($sessionId) . ",
            " . dbI($studentId) . ",
            " . dbQ($name) . ",
            " . dbI($practice['id_classe_origine'] ?? null) . ",
            " . dbQ($practice['classe_origine'] ?? '') . ",
            " . dbI($targetClassId) . ",
            " . dbQ($targetLabel) . ",
            " . dbQ($gruppo) . ",
            " . dbI($tabletRequest) . ",
            " . dbF($metrics['media_generale'] ?? null) . ",
            " . dbF($metrics['voto_matematica'] ?? null) . ",
            " . dbF($metrics['voto_italiano'] ?? null) . ",
            " . dbF($metrics['voto_capacita_relazionale'] ?? null) . ",
            'iscrizioni',
            " . dbQ($practiceNotes) . ",
            " . dbQ($consiglioOrientativo !== '' ? $consiglioOrientativo : null) . ",
            NOW(),
            NOW()
        )
    ");
}

function formazioneClassiPracticeNotes(array $practice): string
{
    $parts = [];

    $curvature = strtolower(trim((string)($practice['curvatura_design'] ?? '')));
    if ($curvature === 'design') {
        $parts[] = 'Curvatura CAT: Design e riqualificazione ambientale';
    } elseif ($curvature === 'normale') {
        $parts[] = 'Curvatura CAT: Normale';
    }

    $parentNotes = trim((string)($practice['note_genitori_iscrizione'] ?? ''));
    if ($parentNotes !== '') {
        $parts[] = $parentNotes;
    }

    $internalNotes = trim((string)($practice['note_interne'] ?? ''));
    if ($internalNotes !== '') {
        $parts[] = 'Note interne iscrizione: ' . $internalNotes;
    }

    return trim(implode("\n", array_unique(array_filter($parts))));
}

function formazioneClassiMetricsFromPractice(array $practice): array
{
    $schoolExamVote = formazioneClassiNullableFloat($practice['voto_esame_licenza'] ?? null);
    $reportAvg = formazioneClassiNullableFloat($practice['terza_media_pagella'] ?? null);
    return [
        'media_generale' => $schoolExamVote ?? $reportAvg,
        'voto_matematica' => formazioneClassiNullableFloat($practice['pagella_voto_matematica'] ?? null)
            ?? formazioneClassiNullableFloat($practice['terza_voto_matematica'] ?? null),
        'voto_italiano' => formazioneClassiNullableFloat($practice['pagella_voto_italiano'] ?? null)
            ?? formazioneClassiNullableFloat($practice['terza_voto_italiano'] ?? null),
        'voto_capacita_relazionale' => formazioneClassiNullableFloat($practice['terza_voto_capacita_relazionale'] ?? null),
    ];
}

function formazioneClassiConfirmedIncomingStates(): array
{
    return ['idoneo_iscrizione', 'chiusa'];
}

function formazioneClassiConfirmedReiscrizioneStates(): array
{
    return ['reiscrizione_confermata', 'chiusa'];
}

function formazioneClassiConfirmedOutgoingStates(): array
{
    return ['nulla_osta_inviato', 'si_ritira', 'firmato_entrambi', 'chiusa'];
}

function formazioneClassiBlockingOutgoingStates(): array
{
    return array_values(array_unique(array_merge(formazioneClassiConfirmedOutgoingStates(), [
        'da_verificare',
        'cambia_scuola',
        'richiesta_nulla_osta',
        'firmato_un_genitore',
        'colloquio_richiesto',
        'colloquio_da_programmare',
        'colloquio_programmato',
        'colloquio_uscita',
    ])));
}

function formazioneClassiAutoRowHasNoBlockingOutgoing(int $studentId): bool
{
    static $cache = [];
    if ($studentId <= 0) {
        return true;
    }
    if (!array_key_exists($studentId, $cache)) {
        $states = implode(',', array_map('dbQ', formazioneClassiBlockingOutgoingStates()));
        $cache[$studentId] = intval(dbGetValue("
            SELECT COUNT(*)
            FROM studenti_movimenti_pratiche
            WHERE id_studente = " . dbI($studentId) . "
              AND tipo_pratica IN ('uscita', 'ritiro')
              AND stato_pratica IN ($states)
              AND stato_pratica <> 'annullata'
        ") ?? 0) === 0;
    }
    return (bool)$cache[$studentId];
}

function formazioneClassiSyncMovimenti(array $session, int $targetClassYear, string $indirizzo): void
{
    $sessionId = intval($session['id'] ?? 0);
    if ($sessionId <= 0) {
        return;
    }

    $rows = dbGetAll("
        SELECT
            m.*,
            s.cognome AS studente_cognome,
            s.nome AS studente_nome,
            s.codice_fiscale AS studente_cf,
            s.sesso AS studente_sesso
        FROM studenti_movimenti_pratiche m
        LEFT JOIN studente s ON s.id = m.id_studente
        WHERE m.tipo_pratica IN ('entrata','bocciato_reiscrizione')
          AND m.stato_pratica <> 'annullata'
          AND COALESCE(m.id_studente, 0) > 0
          AND COALESCE(m.anno_corso, 0) = " . dbI($targetClassYear) . "
        ORDER BY m.updated_at DESC, m.id DESC
    ") ?: [];

    foreach ($rows as $movement) {
        $requested = trim((string)($movement['classe_richiesta'] ?? ''));
        $isBocciatoReiscrizione = (string)($movement['tipo_pratica'] ?? '') === 'bocciato_reiscrizione';
        $gestoreAddress = formazioneClassiGestoreAddressKeyFromId($movement['id_indirizzo_gestore'] ?? 0);
        if (!$isBocciatoReiscrizione && $gestoreAddress === '') {
            continue;
        }
        $addressSource = $gestoreAddress !== '' ? $gestoreAddress : $requested;
        if (!formazioneClassiMovementMatchesAddress($addressSource, $indirizzo, $targetClassYear)) {
            continue;
        }
        $targetYearId = intval($session['id_anno_scolastico_target'] ?? 0);
        $targetClass = ($isBocciatoReiscrizione && formazioneClassiIsActiveTargetClassLabel($requested, $targetClassYear, $indirizzo, $targetYearId))
            ? formazioneClassiLocalClassByLabel($requested)
            : null;
        $targetLabel = $targetClass ? $requested : null;
        if (!$isBocciatoReiscrizione && $targetClassYear > 0) {
            $singleClass = formazioneClassiSingleTargetClass($targetClassYear, $indirizzo, $targetYearId);
            if ($singleClass !== null) {
                $targetClass = ['id' => intval($singleClass['id'] ?? 0), 'classe' => (string)($singleClass['label'] ?? '')];
                $targetLabel = (string)($singleClass['label'] ?? '');
            }
        }
        $group = $isBocciatoReiscrizione ? 'bocciato' : 'neo_iscritto';
        formazioneClassiUpsertMovementStudent($sessionId, $movement, $group, $targetClass ? intval($targetClass['id']) : null, $targetLabel);
    }
}

function formazioneClassiMovementMatchesAddress(string $value, string $indirizzo, int $targetClassYear): bool
{
    $indirizzoNorm = formazioneClassiNorm($indirizzo);
    if ($indirizzoNorm === '') {
        return true;
    }
    $value = trim($value);
    if ($value === '') {
        return $targetClassYear <= 2;
    }
    $localClass = formazioneClassiLocalClassByLabel($value);
    if ($localClass && formazioneClassiClassMatchesAddress($localClass, $indirizzo)) {
        return true;
    }
    $practiceAddressId = formazioneClassiAddressIdByName($value, $targetClassYear);
    if ($practiceAddressId > 0 && (string)$practiceAddressId === trim($indirizzo)) {
        return true;
    }
    $fromClass = formazioneClassiNorm(formazioneClassiAddressKeyFromClass($value));
    if (formazioneClassiAddressKeysMatch($fromClass, $indirizzo)) {
        return true;
    }
    $valueNorm = formazioneClassiNorm($value);
    return $valueNorm === $indirizzoNorm || strpos($valueNorm, $indirizzoNorm) !== false || strpos($indirizzoNorm, $valueNorm) !== false;
}

function formazioneClassiUpsertMovementStudent(int $sessionId, array $movement, string $gruppo, ?int $targetClassId, ?string $targetLabel): void
{
    $studentId = intval($movement['id_studente'] ?? 0);
    if ($sessionId <= 0 || $studentId <= 0) {
        return;
    }
    $name = trim((string)(($movement['studente_cognome'] ?? $movement['cognome'] ?? '') . ' ' . ($movement['studente_nome'] ?? $movement['nome'] ?? '')));
    $existing = dbGetFirst("
        SELECT id, gruppo_origine, bloccato, assegnazione_manuale, classe_provvisoria_label
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND id_studente = " . dbI($studentId) . "
        LIMIT 1
    ");
    if ($existing) {
        if ((string)($existing['gruppo_origine'] ?? '') !== 'bocciato' || $gruppo === 'bocciato') {
            $sets = [
                "gruppo_origine = " . dbQ($gruppo),
                "classe_origine_label = COALESCE(NULLIF(classe_origine_label, ''), " . dbQ($movement['classe_origine'] ?? '') . ")",
                "richiesta_tablet = CASE WHEN " . dbQ($gruppo) . " = 'neo_iscritto' THEN 0 ELSE richiesta_tablet END",
                "updated_at = NOW()",
            ];
            if (intval($existing['bloccato'] ?? 0) === 0 && intval($existing['assegnazione_manuale'] ?? 0) === 0) {
                if ($targetLabel !== null && trim((string)$targetLabel) !== '') {
                    $sets[] = "id_classe_provvisoria = " . dbI($targetClassId);
                    $sets[] = "classe_provvisoria_label = " . dbQ($targetLabel);
                } elseif ($gruppo === 'neo_iscritto') {
                    $sets[] = "id_classe_provvisoria = NULL";
                    $sets[] = "classe_provvisoria_label = NULL";
                }
            }
            dbExec("
                UPDATE formazione_classi_studenti SET
                    " . implode(",\n                    ", $sets) . "
                WHERE id = " . dbI($existing['id']) . "
                LIMIT 1
            ");
        }
        return;
    }

    dbExec("
        INSERT INTO formazione_classi_studenti (
            id_sessione, id_studente, studente_nome, id_classe_origine, classe_origine_label,
            id_classe_provvisoria, classe_provvisoria_label, gruppo_origine,
            richiesta_tablet, fonte_valori, note, created_at, updated_at
        ) VALUES (
            " . dbI($sessionId) . ",
            " . dbI($studentId) . ",
            " . dbQ($name) . ",
            NULL,
            " . dbQ($movement['classe_origine'] ?? '') . ",
            " . dbI($targetClassId) . ",
            " . dbQ($targetLabel) . ",
            " . dbQ($gruppo) . ",
            " . ($gruppo === 'neo_iscritto' ? '0' : 'NULL') . ",
            'movimenti',
            " . dbQ($movement['note'] ?? '') . ",
            NOW(),
            NOW()
        )
    ");
}

function formazioneClassiUpsertStudent(int $sessionId, array $row, string $gruppo, ?int $targetClassId, ?string $targetLabel): void
{
    $studentId = intval($row['id_studente_gestore'] ?? 0);
    if ($sessionId <= 0 || $studentId <= 0) {
        return;
    }
    $metrics = (array)($row['metrics'] ?? []);
    $existing = dbGetFirst("
        SELECT id, bloccato, assegnazione_manuale
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND id_studente = " . dbI($studentId) . "
        LIMIT 1
    ");
    if ($existing !== null) {
        $sets = [
            "studente_nome = COALESCE(NULLIF(studente_nome, ''), " . dbQ($row['studente_nome'] ?? '') . ")",
            "gruppo_origine = CASE WHEN gruppo_origine = 'bocciato' THEN gruppo_origine ELSE " . dbQ($gruppo) . " END",
            "media_generale = COALESCE(media_generale, " . dbF($metrics['media_generale'] ?? null) . ")",
            "voto_matematica = COALESCE(voto_matematica, " . dbF($metrics['voto_matematica'] ?? null) . ")",
            "voto_italiano = COALESCE(voto_italiano, " . dbF($metrics['voto_italiano'] ?? null) . ")",
            "voto_capacita_relazionale = COALESCE(voto_capacita_relazionale, " . dbF($metrics['voto_capacita_relazionale'] ?? null) . ")",
            "updated_at = NOW()",
        ];
        if (intval($existing['bloccato'] ?? 0) === 0 && intval($existing['assegnazione_manuale'] ?? 0) === 0 && $targetLabel !== null && trim((string)$targetLabel) !== '') {
            $sets[] = "id_classe_provvisoria = " . dbI($targetClassId);
            $sets[] = "classe_provvisoria_label = " . dbQ($targetLabel);
        }
        dbExec("
            UPDATE formazione_classi_studenti SET
                " . implode(",\n                ", $sets) . "
            WHERE id = " . dbI($existing['id'] ?? 0) . "
            LIMIT 1
        ");
        return;
    }
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

function formazioneClassiState(int $sourceYearId, int $targetYearId, string $tipo, string $indirizzo, string $tabletFilter = 'all'): array
{
    $targetClassYear = formazioneClassiAnnoDaTipo($tipo);
    $tabletFilter = in_array($targetClassYear, [1, 2], true) ? formazioneClassiNormalizeTabletFilter($tabletFilter) : 'all';
    $session = formazioneClassiSession($sourceYearId, $targetYearId, $tipo, $indirizzo);
    formazioneClassiInitDaTabelloni($session, $targetClassYear, $indirizzo);
    $targetYear = trim((string)dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($targetYearId) . " LIMIT 1"));
    $primeDocumentSelect = "
            0 AS iscrizioni_pratica_id,
            0 AS has_doc_pagella,
            0 AS has_doc_competenze,
            0 AS has_doc_invalsi,
            '' AS note_genitori_iscrizione,";
    $primeDocumentJoins = "";
    $documentsTableAvailable = dbGetFirst("SHOW TABLES LIKE 'iscrizioni_prime_documenti'") !== null;
    if ($targetClassYear === 1 && formazioneClassiIscrizioniTableAvailable() && $documentsTableAvailable && $targetYear !== '') {
        $primeDocumentSelect = "
            ip_doc.id AS iscrizioni_pratica_id,
            COALESCE(doc_flags.has_pagella, 0) AS has_doc_pagella,
            COALESCE(doc_flags.has_competenze, 0) AS has_doc_competenze,
            COALESCE(doc_flags.has_invalsi, 0) AS has_doc_invalsi,
            COALESCE(ip_doc.note_genitori_iscrizione, '') AS note_genitori_iscrizione,";
        $primeDocumentJoins = "
        LEFT JOIN iscrizioni_prime_pratiche ip_doc ON ip_doc.id = (
            SELECT p1.id
            FROM iscrizioni_prime_pratiche p1
            WHERE p1.tipo_iscrizione = 'prime'
              AND (p1.stato IS NULL OR p1.stato <> 'annullata')
              AND " . iscrizioniPrimeSchoolYearWhere('p1.anno_scolastico', $targetYear) . "
              AND UPPER(TRIM(p1.codice_fiscale)) = UPPER(TRIM(s.codice_fiscale))
            ORDER BY p1.updated_at DESC, p1.id DESC
            LIMIT 1
        )
        LEFT JOIN (
            SELECT
                pratica_id,
                MAX(CASE WHEN tipo_documento = 'pagella' THEN 1 ELSE 0 END) AS has_pagella,
                MAX(CASE WHEN tipo_documento = 'certificazione_competenze' THEN 1 ELSE 0 END) AS has_competenze,
                MAX(CASE WHEN tipo_documento = 'invalsi' THEN 1 ELSE 0 END) AS has_invalsi
            FROM iscrizioni_prime_documenti
            WHERE tipo_documento IN ('pagella', 'certificazione_competenze', 'invalsi')
              AND stato <> 'mancante'
              AND (COALESCE(file_path, '') <> '' OR COALESCE(drive_file_id, '') <> '')
            GROUP BY pratica_id
        ) doc_flags ON doc_flags.pratica_id = ip_doc.id";
    }

    $rows = dbGetAll("
        SELECT
            f.*,
            s.cognome,
            s.nome,
            s.codice_fiscale,
            s.sesso,
            mp_status.id AS movimento_pratica_id,
            mp_status.bocciato_altra_scuola AS movimento_bocciato_altra_scuola,
            mp_status.doppio_bocciato_non_consecutivo AS movimento_doppio_bocciato_non_consecutivo,
            mp_note.note AS movimento_note,
            mp_note.tipo_pratica AS movimento_tipo_pratica,
            mp_note.stato_pratica AS movimento_stato_pratica,
            mp_note.updated_at AS movimento_updated_at,
            mp_exit.id AS uscita_movimento_id,
            mp_exit.tipo_pratica AS uscita_tipo_pratica,
            mp_exit.stato_pratica AS uscita_stato_pratica,
            mp_exit.updated_at AS uscita_updated_at,
            mp_exit_any.id AS uscita_attiva_movimento_id,
            mp_exit_any.tipo_pratica AS uscita_attiva_tipo_pratica,
            mp_exit_any.stato_pratica AS uscita_attiva_stato_pratica,
            mp_exit_any.updated_at AS uscita_attiva_updated_at,
            cas_origin.is_tablet AS classe_origine_is_tablet,
            cas_target.is_tablet AS classe_provvisoria_is_tablet,
            " . $primeDocumentSelect . "
            attr.attributi_riservati_raw
        FROM formazione_classi_studenti f
        LEFT JOIN studente s ON s.id = f.id_studente
        LEFT JOIN classi c_target ON c_target.classe = f.classe_provvisoria_label
        LEFT JOIN classi_anno_scolastico cas_target ON cas_target.id_classe = c_target.id
            AND cas_target.id_anno_scolastico = " . dbI($targetYearId) . "
        LEFT JOIN classi_anno_scolastico cas_origin ON cas_origin.id_classe = f.id_classe_origine
            AND cas_origin.id_anno_scolastico = " . dbI($sourceYearId) . "
        LEFT JOIN (
            SELECT
                id_studente,
                GROUP_CONCAT(CONCAT(codice_attributo, '|', fonte) ORDER BY codice_attributo ASC SEPARATOR '||') AS attributi_riservati_raw
            FROM studente_attributi_riservati
            WHERE attivo = 1
            GROUP BY id_studente
        ) attr ON attr.id_studente = f.id_studente
        " . $primeDocumentJoins . "
        LEFT JOIN studenti_movimenti_pratiche mp_status ON mp_status.id_studente = f.id_studente
            AND mp_status.tipo_pratica IN ('entrata', 'bocciato_reiscrizione', 'uscita', 'ritiro')
            AND mp_status.stato_pratica <> 'annullata'
            AND mp_status.id = (
                SELECT mp1.id
                FROM studenti_movimenti_pratiche mp1
                WHERE mp1.id_studente = f.id_studente
                  AND mp1.tipo_pratica IN ('entrata', 'bocciato_reiscrizione', 'uscita', 'ritiro')
                  AND mp1.stato_pratica <> 'annullata'
                ORDER BY mp1.updated_at DESC, mp1.id DESC
                LIMIT 1
            )
        LEFT JOIN studenti_movimenti_pratiche mp_note ON mp_note.id_studente = f.id_studente
            AND mp_note.tipo_pratica IN ('entrata', 'bocciato_reiscrizione', 'uscita', 'ritiro')
            AND mp_note.stato_pratica <> 'annullata'
            AND mp_note.id = (
                SELECT mp2.id
                FROM studenti_movimenti_pratiche mp2
                WHERE mp2.id_studente = f.id_studente
                  AND mp2.tipo_pratica IN ('entrata', 'bocciato_reiscrizione', 'uscita', 'ritiro')
                  AND mp2.stato_pratica <> 'annullata'
                  AND TRIM(COALESCE(mp2.note, '')) <> ''
                ORDER BY mp2.updated_at DESC, mp2.id DESC
                LIMIT 1
            )
        LEFT JOIN studenti_movimenti_pratiche mp_exit ON mp_exit.id_studente = f.id_studente
            AND mp_exit.tipo_pratica IN ('uscita', 'ritiro')
            AND mp_exit.stato_pratica IN (" . implode(',', array_map('dbQ', formazioneClassiConfirmedOutgoingStates())) . ")
            AND mp_exit.id = (
                SELECT mp3.id
                FROM studenti_movimenti_pratiche mp3
                WHERE mp3.id_studente = f.id_studente
                  AND mp3.tipo_pratica IN ('uscita', 'ritiro')
                  AND mp3.stato_pratica IN (" . implode(',', array_map('dbQ', formazioneClassiConfirmedOutgoingStates())) . ")
                ORDER BY mp3.updated_at DESC, mp3.id DESC
                LIMIT 1
            )
        LEFT JOIN studenti_movimenti_pratiche mp_exit_any ON mp_exit_any.id_studente = f.id_studente
            AND mp_exit_any.tipo_pratica IN ('uscita', 'ritiro')
            AND mp_exit_any.stato_pratica <> 'annullata'
            AND mp_exit_any.id = (
                SELECT mp4.id
                FROM studenti_movimenti_pratiche mp4
                WHERE mp4.id_studente = f.id_studente
                  AND mp4.tipo_pratica IN ('uscita', 'ritiro')
                  AND mp4.stato_pratica <> 'annullata'
                ORDER BY mp4.updated_at DESC, mp4.id DESC
                LIMIT 1
            )
        WHERE f.id_sessione = " . dbI($session['id'] ?? 0) . "
        ORDER BY COALESCE(f.classe_provvisoria_label, 'ZZZ') ASC,
                 COALESCE(s.cognome, f.studente_nome) ASC,
                 COALESCE(s.nome, '') ASC,
                 f.studente_nome ASC
    ") ?: [];

    $classes = [];
    foreach (formazioneClassiTargetClasses($targetClassYear, $indirizzo, $targetYearId, $tabletFilter) as $targetClass) {
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
        if ($tabletFilter !== 'all') {
            $isTabletFilterMatch = true;
            if ($targetClassYear === 1) {
                $isPrimeTabletStudent = formazioneClassiPrimeStudentIsTabletForFilter($item);
                $isTabletFilterMatch = $tabletFilter === 'tablet' ? $isPrimeTabletStudent : !$isPrimeTabletStudent;
            } else {
                $isTabletFilterMatch = formazioneClassiStudentMatchesTabletFilter($item, $tabletFilter);
            }
            if (!$isTabletFilterMatch) {
                continue;
            }
        }
        $label = trim((string)($row['classe_provvisoria_label'] ?? ''));
        if (!empty($item['in_uscita'])) {
            $label = '';
        }
        if ($label !== '' && !formazioneClassiIsActiveTargetClassLabel($label, $targetClassYear, $indirizzo, $targetYearId, $tabletFilter)) {
            $label = '';
        }
        if ($label === '') {
            if (!empty($item['blocco_classe']) && empty($item['blocco_individuale'])) {
                $item['blocco_classe'] = 0;
                $item['bloccato'] = 0;
            }
            $unassigned[] = $item;
            continue;
        }
        if (!isset($classes[$label])) {
            if ($tabletFilter !== 'all') {
                continue;
            }
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
    $gruppoOrigine = (string)($row['gruppo_origine'] ?? '');
    if ((string)($row['movimento_tipo_pratica'] ?? '') === 'bocciato_reiscrizione') {
        $gruppoOrigine = 'bocciato';
    }
    $activeOutgoingId = intval($row['uscita_attiva_movimento_id'] ?? 0);
    $confirmedOutgoingId = intval($row['uscita_movimento_id'] ?? 0);
    $pendingOutgoingId = $activeOutgoingId > 0 && $confirmedOutgoingId <= 0 ? $activeOutgoingId : 0;
    $outgoingId = $confirmedOutgoingId;
    $outgoingType = trim((string)(($confirmedOutgoingId > 0 ? $row['uscita_tipo_pratica'] : $row['uscita_attiva_tipo_pratica']) ?? ''));
    $outgoingState = trim((string)(($confirmedOutgoingId > 0 ? $row['uscita_stato_pratica'] : $row['uscita_attiva_stato_pratica']) ?? ''));
    $outgoingUpdated = trim((string)(($confirmedOutgoingId > 0 ? $row['uscita_updated_at'] : $row['uscita_attiva_updated_at']) ?? ''));
    $blockingPendingOutgoing = $pendingOutgoingId > 0
        && in_array($outgoingState, formazioneClassiBlockingOutgoingStates(), true);
    $failedStudentPendingOutgoing = $pendingOutgoingId > 0 && $gruppoOrigine === 'bocciato';
    $doubleNonConsecutive = intval($row['movimento_doppio_bocciato_non_consecutivo'] ?? 0);
    $externalFailedYear = intval($row['movimento_bocciato_altra_scuola'] ?? 0) > 0;
    if ($doubleNonConsecutive > 0) {
        $outgoingId = 0;
        $confirmedOutgoingId = 0;
        $blockingPendingOutgoing = false;
        $failedStudentPendingOutgoing = false;
        $outgoingType = '';
        $outgoingState = '';
        $outgoingUpdated = '';
    }
    $formationNote = formazioneClassiCleanDisplayNote(trim((string)($row['note'] ?? '')));
    $curvature = formazioneClassiExtractCurvatureFromNote($formationNote);
    if ($curvature !== '') {
        $formationNote = formazioneClassiCleanDisplayNote(preg_replace('/^Curvatura\s+CAT\s*:\s*.*$/mi', '', $formationNote) ?? $formationNote);
    }
    $movementNote = trim((string)($row['movimento_note'] ?? ''));
    $notes = [];
    if ($formationNote !== '') {
        $notes[] = $formationNote;
    }
    if ($movementNote !== '' && $movementNote !== $formationNote) {
        $notes[] = 'Nota movimenti: ' . $movementNote;
    }

    return [
        'id' => intval($row['id'] ?? 0),
        'id_studente' => intval($row['id_studente'] ?? 0),
        'nome' => $name,
        'codice_fiscale' => (string)($row['codice_fiscale'] ?? ''),
        'sesso' => strtoupper(trim((string)($row['sesso'] ?? ''))),
        'classe_origine' => (string)($row['classe_origine_label'] ?? ''),
        'classe_provvisoria' => (string)($row['classe_provvisoria_label'] ?? ''),
        'gruppo_origine' => $gruppoOrigine,
        'fonte_valori' => (string)($row['fonte_valori'] ?? ''),
        'bloccato' => intval($row['bloccato'] ?? 0),
        'blocco_individuale' => intval($row['blocco_individuale'] ?? 0),
        'blocco_classe' => intval($row['blocco_classe'] ?? 0),
        'richiesta_tablet' => !array_key_exists('richiesta_tablet', $row) || $row['richiesta_tablet'] === null ? null : intval($row['richiesta_tablet']),
        'classe_origine_is_tablet' => $row['classe_origine_is_tablet'] === null ? null : intval($row['classe_origine_is_tablet']),
        'classe_provvisoria_is_tablet' => $row['classe_provvisoria_is_tablet'] === null ? null : intval($row['classe_provvisoria_is_tablet']),
        'id_movimento' => intval($row['movimento_pratica_id'] ?? 0),
        'in_uscita' => $confirmedOutgoingId > 0 ? 1 : 0,
        'uscita_confermata' => $confirmedOutgoingId > 0 ? 1 : 0,
        'uscita_non_confermata' => $pendingOutgoingId > 0 ? 1 : 0,
        'uscita_bloccante' => ($confirmedOutgoingId > 0 || $blockingPendingOutgoing || $failedStudentPendingOutgoing) ? 1 : 0,
        'id_movimento_uscita' => $outgoingId,
        'uscita_tipo_pratica' => $outgoingType,
        'uscita_stato_pratica' => $outgoingState,
        'uscita_updated_at' => $outgoingUpdated,
        'bocciato_altra_scuola' => $externalFailedYear ? 1 : 0,
        'media_generale' => formazioneClassiNullableFloat($row['media_generale'] ?? null),
        'voto_matematica' => formazioneClassiNullableFloat($row['voto_matematica'] ?? null),
        'voto_italiano' => formazioneClassiNullableFloat($row['voto_italiano'] ?? null),
        'voto_capacita_relazionale' => formazioneClassiNullableFloat($row['voto_capacita_relazionale'] ?? null),
        'consiglio_orientativo' => trim((string)($row['consiglio_orientativo'] ?? '')),
        'note_genitori_iscrizione' => trim((string)($row['note_genitori_iscrizione'] ?? '')),
        'iscrizioni_pratica_id' => intval($row['iscrizioni_pratica_id'] ?? 0),
        'documenti_prime' => [
            'pagella' => intval($row['has_doc_pagella'] ?? 0) > 0,
            'certificazione_competenze' => intval($row['has_doc_competenze'] ?? 0) > 0,
            'invalsi' => intval($row['has_doc_invalsi'] ?? 0) > 0,
        ],
        'note_formazione' => trim(implode("\n", $notes)),
        'curvatura_design' => $curvature,
        'note_formazione_origine' => $formationNote !== '' ? 'iscrizione' : trim((string)($row['movimento_tipo_pratica'] ?? '')),
        'note_formazione_stato' => trim((string)($row['movimento_stato_pratica'] ?? '')),
        'doppio_bocciato_non_consecutivo' => $doubleNonConsecutive,
        'note_formazione_updated_at' => trim((string)($row['movimento_updated_at'] ?? '')),
        'attributi_riservati' => formazioneClassiParseStudentAttrs((string)($row['attributi_riservati_raw'] ?? '')),
    ];
}

function formazioneClassiStudentMatchesTabletFilter(array $student, string $tabletFilter): bool
{
    $tabletFilter = formazioneClassiNormalizeTabletFilter($tabletFilter);
    if ($tabletFilter === 'all') {
        return true;
    }

    $tabletInfo = formazioneClassiStudentTabletInfo($student);
    $isTablet = (bool)($tabletInfo['is_tablet'] ?? false);

    return $tabletFilter === 'tablet' ? $isTablet : !$isTablet;
}

function formazioneClassiPrimeStudentIsTabletForFilter(array $student): bool
{
    if ((string)($student['gruppo_origine'] ?? '') === 'bocciato') {
        return !formazioneClassiStudentIsDigitalScience($student)
            && intval($student['classe_origine_is_tablet'] ?? 0) === 1;
    }

    return (string)($student['fonte_valori'] ?? '') === 'iscrizioni'
        && intval($student['richiesta_tablet'] ?? 0) === 1;
}

function formazioneClassiStudentTabletInfo(array $student): array
{
    if (formazioneClassiStudentIsDigitalScience($student)) {
        return [
            'is_tablet' => false,
            'source' => 'Digital Science gestito separatamente',
        ];
    }

    $positiveSources = [];
    $knownSources = [];
    if (trim((string)($student['classe_origine'] ?? '')) !== '' && ($student['classe_origine_is_tablet'] ?? null) !== null) {
        if (intval($student['classe_origine_is_tablet'] ?? 0) === 1) {
            $positiveSources[] = 'classe origine tablet';
        } else {
            $knownSources[] = 'classe origine non tablet';
        }
    }
    if (($student['classe_provvisoria_is_tablet'] ?? null) !== null) {
        if (intval($student['classe_provvisoria_is_tablet'] ?? 0) === 1) {
            $positiveSources[] = 'classe futura tablet';
        } else {
            $knownSources[] = 'classe futura non tablet';
        }
    }
    if (($student['richiesta_tablet'] ?? null) !== null) {
        if (intval($student['richiesta_tablet'] ?? 0) === 1) {
            $positiveSources[] = 'scelta tablet iscrizione';
        } else {
            $knownSources[] = 'scelta non tablet iscrizione';
        }
    }

    $isTablet = !empty($positiveSources);
    return [
        'is_tablet' => $isTablet,
        'source' => $isTablet ? implode(' / ', $positiveSources) : (implode(' / ', $knownSources) ?: 'dato tablet non presente'),
    ];
}

function formazioneClassiStudentIsDigitalScience(array $student): bool
{
    $labels = [
        (string)($student['classe_provvisoria'] ?? ''),
        (string)($student['classe_origine'] ?? ''),
    ];
    foreach ($labels as $label) {
        if (preg_match('/^[1-5]DS\b/u', formazioneClassiNorm($label)) === 1) {
            return true;
        }
    }
    $note = formazioneClassiNorm((string)($student['note_formazione'] ?? ''));
    return strpos($note, 'DIGITAL SCIENCE') !== false;
}

function formazioneClassiCleanDisplayNote(string $note): string
{
    if ($note === '') {
        return '';
    }
    $lines = preg_split('/\r?\n/u', $note) ?: [];
    $lines = array_values(array_filter($lines, static function ($line): bool {
        $line = trim((string)$line);
        return stripos($line, 'Genitori/responsabili:') !== 0
            && stripos($line, 'Responsabili:') !== 0;
    }));
    return trim(implode("\n", $lines));
}

function formazioneClassiExtractCurvatureFromNote(string $note): string
{
    if (preg_match('/Curvatura\s+CAT\s*:\s*(Design|Design e riqualificazione ambientale)/iu', $note)) {
        return 'design';
    }
    if (preg_match('/Curvatura\s+CAT\s*:\s*Normale/iu', $note)) {
        return 'normale';
    }
    return '';
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

function formazioneClassiSaveStudentAttrs(int $rowId, array $attrs): array
{
    formazioneClassiEnsureTables();
    $row = dbGetFirst("
        SELECT f.id, f.id_studente, COALESCE(s.cognome, '') AS cognome, COALESCE(s.nome, '') AS nome, f.studente_nome
        FROM formazione_classi_studenti f
        LEFT JOIN studente s ON s.id = f.id_studente
        WHERE f.id = " . dbI($rowId) . "
        LIMIT 1
    ");
    if (!$row) {
        return ['ok' => false, 'message' => 'Studente non trovato nella formazione classi.'];
    }

    $studentId = intval($row['id_studente'] ?? 0);
    if ($studentId <= 0) {
        return ['ok' => false, 'message' => 'Anagrafica studente non collegata.'];
    }

    $allowed = array_keys(studentiAttrMap());
    foreach ($allowed as $code) {
        studentiAttrUpsert($studentId, (string)$code, !empty($attrs[$code]), 'formazione_classi', 'row:' . $rowId);
    }

    $active = studentiAttrActiveForStudentWithSource($studentId);
    $name = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
    if ($name === '') {
        $name = trim((string)($row['studente_nome'] ?? ''));
    }

    return [
        'ok' => true,
        'message' => 'Attributi aggiornati per ' . ($name !== '' ? $name : 'lo studente') . '.',
        'row_id' => $rowId,
        'id_studente' => $studentId,
        'attributi' => $active,
    ];
}

function formazioneClassiSaveParentNote(int $rowId, string $note): array
{
    formazioneClassiEnsureTables();
    $row = dbGetFirst("
        SELECT
            f.id,
            f.id_sessione,
            f.id_studente,
            f.studente_nome,
            COALESCE(s.cognome, '') AS cognome,
            COALESCE(s.nome, '') AS nome,
            COALESCE(s.codice_fiscale, '') AS codice_fiscale,
            sess.tipo_formazione,
            sess.id_anno_scolastico_target
        FROM formazione_classi_studenti f
        LEFT JOIN studente s ON s.id = f.id_studente
        LEFT JOIN formazione_classi_sessioni sess ON sess.id = f.id_sessione
        WHERE f.id = " . dbI($rowId) . "
        LIMIT 1
    ");
    if (!$row) {
        return ['ok' => false, 'message' => 'Studente non trovato nella formazione classi.'];
    }

    $targetClassYear = formazioneClassiAnnoDaTipo((string)($row['tipo_formazione'] ?? ''));
    $tipoIscrizione = $targetClassYear === 3 ? 'terze' : 'prime';
    $targetYearId = intval($row['id_anno_scolastico_target'] ?? 0);
    $targetYear = $targetYearId > 0
        ? trim((string)dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($targetYearId) . " LIMIT 1"))
        : '';
    $cf = strtoupper(trim((string)($row['codice_fiscale'] ?? '')));
    if ($cf === '' || $targetYear === '' || !formazioneClassiIscrizioniTableAvailable()) {
        return ['ok' => false, 'message' => 'Pratica iscrizione non trovata per questo studente.'];
    }

    $practice = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche p
        WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND (p.stato IS NULL OR p.stato <> 'annullata')
          AND " . iscrizioniPrimeSchoolYearWhere('p.anno_scolastico', $targetYear) . "
          AND UPPER(TRIM(p.codice_fiscale)) = " . dbQ($cf) . "
        ORDER BY p.updated_at DESC, p.id DESC
        LIMIT 1
    ");
    if (!$practice) {
        return ['ok' => false, 'message' => 'Pratica iscrizione non trovata per questo studente.'];
    }

    $note = trim($note);
    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET note_genitori_iscrizione = " . dbQ($note !== '' ? $note : null) . ",
            updated_at = NOW()
        WHERE id = " . dbI($practice['id'] ?? 0) . "
        LIMIT 1
    ");

    $practice['note_genitori_iscrizione'] = $note;
    $formationNote = formazioneClassiPracticeNotes($practice);
    dbExec("
        UPDATE formazione_classi_studenti
        SET note = " . dbQ($formationNote !== '' ? $formationNote : null) . ",
            updated_at = NOW()
        WHERE id = " . dbI($rowId) . "
        LIMIT 1
    ");

    $name = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
    if ($name === '') {
        $name = trim((string)($row['studente_nome'] ?? ''));
    }

    return [
        'ok' => true,
        'message' => 'Nota genitori aggiornata' . ($name !== '' ? ' per ' . $name : '') . '.',
        'row_id' => $rowId,
        'pratica_id' => intval($practice['id'] ?? 0),
        'note' => $note,
    ];
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
    $activeStudents = array_values(array_filter($students, static function (array $student): bool {
        return empty($student['in_uscita']);
    }));
    $stats = [
        'count' => count($activeStudents),
        'maschi' => 0,
        'femmine' => 0,
        'media_generale' => null,
        'voto_matematica' => null,
        'voto_italiano' => null,
        'voto_capacita_relazionale' => null,
        'dsa' => 0,
        'fascia_c' => 0,
        'legge_104' => 0,
        'bocciati' => 0,
    ];
    foreach (['media_generale', 'voto_matematica', 'voto_italiano', 'voto_capacita_relazionale'] as $field) {
        $stats[$field . '_bins'] = array_fill_keys([6, 7, 8, 9, 10], 0);
    }
    foreach ($activeStudents as $student) {
        $sesso = strtoupper(trim((string)($student['sesso'] ?? '')));
        if ($sesso === 'M') {
            $stats['maschi']++;
        } elseif ($sesso === 'F') {
            $stats['femmine']++;
        }
        if (formazioneClassiStudentHasAttr($student, STUD_ATTR_R7A2)) {
            $stats['dsa']++;
        }
        if (formazioneClassiStudentHasAttr($student, STUD_ATTR_Z8C3)) {
            $stats['fascia_c']++;
        }
        if (formazioneClassiStudentHasAttr($student, STUD_ATTR_Q4M9)) {
            $stats['legge_104']++;
        }
        if ((string)($student['gruppo_origine'] ?? '') === 'bocciato'
            || !empty($student['bocciato_altra_scuola'])
            || !empty($student['doppio_bocciato_non_consecutivo'])) {
            $stats['bocciati']++;
        }
    }
    foreach (['media_generale', 'voto_matematica', 'voto_italiano', 'voto_capacita_relazionale'] as $field) {
        $sum = 0.0;
        $count = 0;
        foreach ($activeStudents as $student) {
            $isFailedStudent = (string)($student['gruppo_origine'] ?? '') === 'bocciato'
                || !empty($student['bocciato_altra_scuola'])
                || !empty($student['doppio_bocciato_non_consecutivo']);
            if ($isFailedStudent && in_array($field, ['media_generale', 'voto_matematica', 'voto_italiano'], true)) {
                continue;
            }
            if (($student[$field] ?? null) === null) {
                continue;
            }
            $value = (float)$student[$field];
            $sum += $value;
            $count++;
            $bin = (int)floor($value);
            if ($bin < 6) {
                $bin = 6;
            } elseif ($bin > 10) {
                $bin = 10;
            }
            $stats[$field . '_bins'][$bin]++;
        }
        $stats[$field] = $count > 0 ? $sum / $count : null;
    }
    return $stats;
}

function formazioneClassiStudentHasAttr(array $student, string $code): bool
{
    foreach (($student['attributi_riservati'] ?? []) as $attr) {
        if ((string)($attr['codice'] ?? $attr['codice_attributo'] ?? '') === $code) {
            return true;
        }
    }
    return false;
}

function formazioneClassiUndoCaptureRows(int $sessionId, array $rowIds): array
{
    $rowIds = array_values(array_unique(array_filter(array_map('intval', $rowIds), static function (int $id): bool {
        return $id > 0;
    })));
    if ($sessionId <= 0 || empty($rowIds)) {
        return [];
    }
    $idList = implode(',', array_map('dbI', $rowIds));
    return dbGetAll("
        SELECT
            id,
            id_studente,
            studente_nome,
            id_classe_provvisoria,
            classe_provvisoria_label,
            bloccato,
            blocco_individuale,
            blocco_classe,
            assegnazione_manuale,
            ordine
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND id IN ($idList)
        ORDER BY id ASC
    ") ?: [];
}

function formazioneClassiUndoStudentNames(array $savedRows): array
{
    $names = [];
    $rowIds = [];
    foreach ($savedRows as $row) {
        $name = trim((string)($row['studente_nome'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
            continue;
        }
        $rowId = intval($row['id'] ?? 0);
        if ($rowId > 0) {
            $rowIds[] = $rowId;
        }
    }
    $rowIds = array_values(array_unique($rowIds));
    if ($rowIds) {
        $idList = implode(',', array_map('dbI', $rowIds));
        $currentRows = dbGetAll("
            SELECT f.id, COALESCE(NULLIF(f.studente_nome, ''), TRIM(CONCAT(COALESCE(s.cognome, ''), ' ', COALESCE(s.nome, '')))) AS nome
            FROM formazione_classi_studenti f
            LEFT JOIN studente s ON s.id = f.id_studente
            WHERE f.id IN ($idList)
        ") ?: [];
        foreach ($currentRows as $currentRow) {
            $name = trim((string)($currentRow['nome'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }
    }
    $names = array_values(array_unique(array_filter($names, 'strlen')));
    natcasesort($names);
    return array_values($names);
}

function formazioneClassiUndoDescriptionWithNames(string $description, array $names): string
{
    if (empty($names)) {
        return $description;
    }
    if (count($names) === 1) {
        $name = $names[0];
        $generic = [
            'Blocca studente' => 'Blocca ' . $name,
            'Sblocca studente' => 'Sblocca ' . $name,
            'Rimuovi studente dalla classe' => 'Rimuovi ' . $name . ' dalla classe',
        ];
        if (isset($generic[$description])) {
            return $generic[$description];
        }
        if (strpos($description, $name) === false && preg_match('/\bstudente\b/i', $description)) {
            return preg_replace('/\bstudente\b/i', $name, $description, 1) ?? $description;
        }
        return $description;
    }
    $preview = implode(', ', array_slice($names, 0, 3));
    if (count($names) > 3) {
        $preview .= ' +' . (count($names) - 3);
    }
    return $description . ' - ' . $preview;
}

function formazioneClassiUndoMoveDetail(array $savedRows, array $meta): string
{
    $fromLabels = [];
    foreach ($savedRows as $row) {
        $label = trim((string)($row['classe_provvisoria_label'] ?? ''));
        $fromLabels[] = $label !== '' ? $label : 'da destra';
    }
    $fromLabels = array_values(array_unique($fromLabels));
    natcasesort($fromLabels);
    $from = implode(', ', $fromLabels);
    $to = trim((string)($meta['target_label'] ?? ''));
    if ($to === '') {
        $to = 'destra / da piazzare';
    }
    if ($from === '') {
        return 'Spostamento verso ' . $to;
    }
    return 'Spostamento da ' . $from . ' a ' . $to;
}

function formazioneClassiUndoDetail(string $action, array $savedRows, array $meta): string
{
    if ($action === 'move') {
        return formazioneClassiUndoMoveDetail($savedRows, $meta);
    }
    if ($action === 'auto_assign') {
        $assignments = isset($meta['assignments']) && is_array($meta['assignments']) ? $meta['assignments'] : [];
        $targetLabels = array_values(array_unique(array_map(static function ($label): string {
            return trim((string)$label);
        }, array_values($assignments))));
        $targetLabels = array_values(array_filter($targetLabels, 'strlen'));
        natcasesort($targetLabels);
        return $targetLabels ? 'Distribuiti verso: ' . implode(', ', $targetLabels) : '';
    }
    return '';
}

function formazioneClassiUndoPush(int $sessionId, string $action, string $description, array $rows, array $meta = []): void
{
    if ($sessionId <= 0 || empty($rows)) {
        return;
    }
    $payload = [
        'rows' => array_values($rows),
        'meta' => $meta,
    ];
    dbExec("
        INSERT INTO formazione_classi_undo (id_sessione, azione, descrizione, payload_json, created_at)
        VALUES (
            " . dbI($sessionId) . ",
            " . dbQ($action) . ",
            " . dbQ($description) . ",
            " . dbQ(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . ",
            NOW()
        )
    ");
    dbExec("
        DELETE FROM formazione_classi_undo
        WHERE id_sessione = " . dbI($sessionId) . "
          AND id NOT IN (
              SELECT id FROM (
                  SELECT id
                  FROM formazione_classi_undo
                  WHERE id_sessione = " . dbI($sessionId) . "
                  ORDER BY id DESC
                  LIMIT 50
              ) keep_rows
          )
    ");
}

function formazioneClassiUndoList(int $sessionId, int $limit = 50): array
{
    if ($sessionId <= 0) {
        return [];
    }
    $limit = max(1, min(50, $limit));
    $rows = dbGetAll("
        SELECT id, azione, descrizione, payload_json, created_at
        FROM formazione_classi_undo
        WHERE id_sessione = " . dbI($sessionId) . "
        ORDER BY id DESC
        LIMIT " . dbI($limit) . "
    ") ?: [];
    $items = [];
    foreach ($rows as $row) {
        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        $savedRows = is_array($payload) && isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
        $meta = is_array($payload) && isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];
        $names = formazioneClassiUndoStudentNames($savedRows);
        $description = (string)(($row['descrizione'] ?? '') !== '' ? $row['descrizione'] : ($row['azione'] ?? 'Operazione'));
        $action = (string)($row['azione'] ?? '');
        $items[] = [
            'id' => intval($row['id'] ?? 0),
            'azione' => $action,
            'descrizione' => formazioneClassiUndoDescriptionWithNames($description, $names),
            'dettaglio' => formazioneClassiUndoDetail($action, $savedRows, $meta),
            'created_at' => (string)($row['created_at'] ?? ''),
            'studenti' => count($savedRows),
            'nomi_studenti' => $names,
            'meta' => $meta,
        ];
    }
    return $items;
}

function formazioneClassiUndoApplyRecord(int $sessionId, array $undo): array
{
    $payload = json_decode((string)($undo['payload_json'] ?? ''), true);
    $rows = is_array($payload) && isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
    if (empty($rows)) {
        return ['ok' => false, 'restored' => 0, 'message' => 'Operazione undo non valida.'];
    }
    $restored = 0;
    foreach ($rows as $row) {
        $rowId = intval($row['id'] ?? 0);
        if ($rowId <= 0) {
            continue;
        }
        dbExec("
            UPDATE formazione_classi_studenti
            SET id_classe_provvisoria = " . dbI($row['id_classe_provvisoria'] ?? null) . ",
                classe_provvisoria_label = " . dbQ($row['classe_provvisoria_label'] ?? null) . ",
                bloccato = " . dbI($row['bloccato'] ?? 0) . ",
                blocco_individuale = " . dbI($row['blocco_individuale'] ?? 0) . ",
                blocco_classe = " . dbI($row['blocco_classe'] ?? 0) . ",
                assegnazione_manuale = " . dbI($row['assegnazione_manuale'] ?? 0) . ",
                ordine = " . dbI($row['ordine'] ?? 0) . ",
                updated_at = NOW()
            WHERE id = " . dbI($rowId) . "
              AND id_sessione = " . dbI($sessionId) . "
            LIMIT 1
        ");
        $restored++;
    }
    return ['ok' => true, 'restored' => $restored];
}

function formazioneClassiUndoLast(int $sessionId): array
{
    if ($sessionId <= 0) {
        return ['ok' => false, 'message' => 'Sessione non valida.'];
    }
    $undo = dbGetFirst("
        SELECT *
        FROM formazione_classi_undo
        WHERE id_sessione = " . dbI($sessionId) . "
        ORDER BY id DESC
        LIMIT 1
    ");
    if (!$undo) {
        return ['ok' => false, 'message' => 'Nessuna operazione da annullare.'];
    }
    $result = formazioneClassiUndoApplyRecord($sessionId, $undo);
    if (empty($result['ok'])) {
        dbExec("DELETE FROM formazione_classi_undo WHERE id = " . dbI($undo['id'] ?? 0) . " LIMIT 1");
        return ['ok' => false, 'message' => (string)($result['message'] ?? 'Operazione undo non valida.')];
    }
    dbExec("DELETE FROM formazione_classi_undo WHERE id = " . dbI($undo['id'] ?? 0) . " LIMIT 1");
    dbExec("UPDATE formazione_classi_sessioni SET updated_at = NOW() WHERE id = " . dbI($sessionId) . " LIMIT 1");

    return [
        'ok' => true,
        'message' => 'Annullata operazione: ' . (string)($undo['descrizione'] ?: $undo['azione']) . ' (' . intval($result['restored'] ?? 0) . ' studenti ripristinati).',
        'restored' => intval($result['restored'] ?? 0),
    ];
}

function formazioneClassiUndoTo(int $sessionId, int $undoId): array
{
    if ($sessionId <= 0 || $undoId <= 0) {
        return ['ok' => false, 'message' => 'Operazione undo non valida.'];
    }
    $undos = dbGetAll("
        SELECT *
        FROM formazione_classi_undo
        WHERE id_sessione = " . dbI($sessionId) . "
          AND id >= " . dbI($undoId) . "
        ORDER BY id DESC
    ") ?: [];
    if (empty($undos)) {
        return ['ok' => false, 'message' => 'Operazione non trovata nello storico undo.'];
    }

    $restored = 0;
    $operations = 0;
    foreach ($undos as $undo) {
        $result = formazioneClassiUndoApplyRecord($sessionId, $undo);
        if (!empty($result['ok'])) {
            $restored += intval($result['restored'] ?? 0);
            $operations++;
        }
    }
    dbExec("
        DELETE FROM formazione_classi_undo
        WHERE id_sessione = " . dbI($sessionId) . "
          AND id >= " . dbI($undoId) . "
    ");
    dbExec("UPDATE formazione_classi_sessioni SET updated_at = NOW() WHERE id = " . dbI($sessionId) . " LIMIT 1");

    return [
        'ok' => true,
        'message' => 'Annullate ' . $operations . ' operazioni (' . $restored . ' studenti ripristinati).',
        'operations' => $operations,
        'restored' => $restored,
    ];
}

function formazioneClassiMoveStudent(int $sessionId, int $rowId, string $targetLabel): array
{
    $session = dbGetFirst("
        SELECT *
        FROM formazione_classi_sessioni
        WHERE id = " . dbI($sessionId) . "
        LIMIT 1
    ") ?: [];
    $targetClassYear = formazioneClassiAnnoDaTipo((string)($session['tipo_formazione'] ?? ''));
    $targetYearId = intval($session['id_anno_scolastico_target'] ?? 0);
    $indirizzo = (string)($session['indirizzo'] ?? '');

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
    $blocked = intval($row['bloccato'] ?? 0) === 1;
    $classBlockedOnly = intval($row['blocco_classe'] ?? 0) === 1
        && intval($row['blocco_individuale'] ?? 0) === 0;
    if ($blocked && !$classBlockedOnly) {
        return ['ok' => false, 'message' => 'Studente bloccato: sbloccalo prima di spostarlo.'];
    }
    $blockingOutgoingStates = implode(',', array_map('dbQ', formazioneClassiBlockingOutgoingStates()));
    $openOutgoing = intval(dbGetValue("
        SELECT COUNT(*)
        FROM studenti_movimenti_pratiche
        WHERE id_studente = " . dbI($row['id_studente'] ?? 0) . "
          AND tipo_pratica IN ('uscita', 'ritiro')
          AND stato_pratica IN ($blockingOutgoingStates)
          AND stato_pratica <> 'annullata'
    ") ?? 0);
    if ($openOutgoing > 0) {
        return ['ok' => false, 'message' => 'Studente con uscita/cambio scuola da verificare: non puo essere spostato in una classe.'];
    }

    $targetLabel = trim($targetLabel);
    $currentLabel = trim((string)($row['classe_provvisoria_label'] ?? ''));
    if ($blocked && $classBlockedOnly && $targetLabel === $currentLabel) {
        return ['ok' => false, 'message' => 'Studente bloccato dalla classe: sblocca la classe prima di spostarlo nella stessa classe.'];
    }
    $studentName = trim((string)($row['studente_nome'] ?? ''));
    if ($studentName === '') {
        $studentName = 'studente';
    }
    $sourceLabel = $currentLabel !== '' ? $currentLabel : 'destra / da piazzare';
    $destinationLabel = $targetLabel !== '' ? $targetLabel : 'destra / da piazzare';
    $classId = null;
    if ($targetLabel !== '') {
        $classLocked = intval(dbGetValue("
            SELECT COUNT(*)
            FROM formazione_classi_studenti
            WHERE id_sessione = " . dbI($sessionId) . "
              AND classe_provvisoria_label = " . dbQ($targetLabel) . "
              AND COALESCE(blocco_classe, 0) = 1
        "));
        if ($classLocked > 0) {
            return ['ok' => false, 'message' => 'Classe bloccata: sblocca la classe prima di spostare studenti.'];
        }
        if ($targetClassYear > 0 && $targetYearId > 0 && !formazioneClassiIsActiveTargetClassLabel($targetLabel, $targetClassYear, $indirizzo, $targetYearId)) {
            return ['ok' => false, 'message' => 'Classe non attiva per l\'anno scolastico selezionato.'];
        }
        $targetClass = formazioneClassiLocalClassByLabel($targetLabel);
        $classId = $targetClass ? intval($targetClass['id']) : null;
    }
    formazioneClassiUndoPush(
        $sessionId,
        'move',
        'Sposta ' . $studentName . ' da ' . $sourceLabel . ' a ' . $destinationLabel,
        formazioneClassiUndoCaptureRows($sessionId, [$rowId]),
        ['row_id' => $rowId, 'target_label' => $targetLabel]
    );
    dbExec("
        UPDATE formazione_classi_studenti
        SET id_classe_provvisoria = " . dbI($classId) . ",
            classe_provvisoria_label = " . dbQ($targetLabel) . ",
            blocco_classe = 0,
            bloccato = CASE WHEN COALESCE(blocco_individuale, 0) = 1 THEN 1 ELSE 0 END,
            assegnazione_manuale = " . dbI($targetLabel !== '' ? 1 : 0) . ",
            updated_at = NOW()
        WHERE id = " . dbI($rowId) . "
          AND id_sessione = " . dbI($sessionId) . "
        LIMIT 1
    ");
    dbExec("UPDATE formazione_classi_sessioni SET updated_at = NOW() WHERE id = " . dbI($sessionId) . " LIMIT 1");

    return ['ok' => true, 'message' => 'Spostamento salvato.'];
}

function formazioneClassiSetStudentLock(int $sessionId, int $rowId, bool $locked): array
{
    if ($sessionId <= 0 || $rowId <= 0) {
        return ['ok' => false, 'message' => 'Studente non valido.'];
    }
    $row = dbGetFirst("
        SELECT id, blocco_classe, studente_nome
        FROM formazione_classi_studenti
        WHERE id = " . dbI($rowId) . "
          AND id_sessione = " . dbI($sessionId) . "
        LIMIT 1
    ");
    if (!$row) {
        return ['ok' => false, 'message' => 'Studente non trovato nella bozza.'];
    }
    $studentName = trim((string)($row['studente_nome'] ?? ''));
    if ($studentName === '') {
        $studentName = 'studente';
    }
    formazioneClassiUndoPush(
        $sessionId,
        $locked ? 'student_lock' : 'student_unlock',
        ($locked ? 'Blocca ' : 'Sblocca ') . $studentName,
        formazioneClassiUndoCaptureRows($sessionId, [$rowId]),
        ['row_id' => $rowId, 'locked' => $locked ? 1 : 0]
    );
    $effective = $locked || intval($row['blocco_classe'] ?? 0) === 1;
    dbExec("
        UPDATE formazione_classi_studenti
        SET blocco_individuale = " . dbI($locked ? 1 : 0) . ",
            bloccato = " . dbI($effective ? 1 : 0) . ",
            updated_at = NOW()
        WHERE id = " . dbI($rowId) . "
          AND id_sessione = " . dbI($sessionId) . "
        LIMIT 1
    ");
    dbExec("UPDATE formazione_classi_sessioni SET updated_at = NOW() WHERE id = " . dbI($sessionId) . " LIMIT 1");
    return ['ok' => true, 'message' => $locked ? 'Studente bloccato.' : 'Blocco studente rimosso.'];
}

function formazioneClassiSetClassLock(int $sessionId, string $classLabel, bool $locked): array
{
    $classLabel = trim($classLabel);
    if ($sessionId <= 0 || $classLabel === '') {
        return ['ok' => false, 'message' => 'Classe non valida.'];
    }
    $classRows = dbGetAll("
        SELECT id
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND classe_provvisoria_label = " . dbQ($classLabel) . "
    ") ?: [];
    formazioneClassiUndoPush(
        $sessionId,
        $locked ? 'class_lock' : 'class_unlock',
        ($locked ? 'Blocca classe ' : 'Sblocca classe ') . $classLabel,
        formazioneClassiUndoCaptureRows($sessionId, array_map(static function ($row): int {
            return intval($row['id'] ?? 0);
        }, $classRows)),
        ['class_label' => $classLabel, 'locked' => $locked ? 1 : 0]
    );
    if ($locked) {
        dbExec("
            UPDATE formazione_classi_studenti
            SET blocco_classe = 1,
                bloccato = 1,
                updated_at = NOW()
            WHERE id_sessione = " . dbI($sessionId) . "
              AND classe_provvisoria_label = " . dbQ($classLabel) . "
        ");
        $message = 'Classe bloccata.';
    } else {
        dbExec("
            UPDATE formazione_classi_studenti
            SET blocco_classe = 0,
                bloccato = CASE WHEN COALESCE(blocco_individuale, 0) = 1 THEN 1 ELSE 0 END,
                updated_at = NOW()
            WHERE id_sessione = " . dbI($sessionId) . "
              AND classe_provvisoria_label = " . dbQ($classLabel) . "
        ");
        $message = 'Blocco classe rimosso.';
    }
    dbExec("UPDATE formazione_classi_sessioni SET updated_at = NOW() WHERE id = " . dbI($sessionId) . " LIMIT 1");
    return ['ok' => true, 'message' => $message];
}

function formazioneClassiSnapshots(int $sessionId): array
{
    if ($sessionId <= 0) {
        return [];
    }
    return dbGetAll("
        SELECT s.*, COUNT(ss.id_studente) AS studenti
        FROM formazione_classi_snapshot s
        LEFT JOIN formazione_classi_snapshot_studenti ss ON ss.id_snapshot = s.id
        WHERE s.id_sessione = " . dbI($sessionId) . "
        GROUP BY s.id
        ORDER BY s.created_at DESC, s.id DESC
    ") ?: [];
}

function formazioneClassiSaveSnapshot(int $sessionId, string $name, string $createdBy = ''): array
{
    $name = trim($name);
    if ($sessionId <= 0 || $name === '') {
        return ['ok' => false, 'message' => 'Nome salvataggio obbligatorio.'];
    }
    $exists = intval(dbGetValue("SELECT id FROM formazione_classi_sessioni WHERE id = " . dbI($sessionId) . " LIMIT 1") ?? 0);
    if ($exists <= 0) {
        return ['ok' => false, 'message' => 'Sessione formazione non trovata.'];
    }
    dbExec("
        INSERT INTO formazione_classi_snapshot (id_sessione, nome, created_by, created_at)
        VALUES (" . dbI($sessionId) . ", " . dbQ($name) . ", " . dbQ($createdBy) . ", NOW())
    ");
    $snapshotId = intval(dblastId());
    dbExec("
        INSERT INTO formazione_classi_snapshot_studenti
            (id_snapshot, id_studente, classe_provvisoria_label, bloccato, blocco_individuale, blocco_classe, assegnazione_manuale)
        SELECT
            " . dbI($snapshotId) . ",
            id_studente,
            classe_provvisoria_label,
            bloccato,
            blocco_individuale,
            blocco_classe,
            assegnazione_manuale
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
    ");
    return ['ok' => true, 'message' => 'Fotografia salvata.', 'snapshot_id' => $snapshotId];
}

function formazioneClassiApplySnapshot(int $sessionId, int $snapshotId): array
{
    if ($sessionId <= 0 || $snapshotId <= 0) {
        return ['ok' => false, 'message' => 'Salvataggio non valido.'];
    }
    $snapshot = dbGetFirst("
        SELECT *
        FROM formazione_classi_snapshot
        WHERE id = " . dbI($snapshotId) . "
          AND id_sessione = " . dbI($sessionId) . "
        LIMIT 1
    ");
    if (!$snapshot) {
        return ['ok' => false, 'message' => 'Fotografia non trovata per questa formazione.'];
    }
    dbExec("
        UPDATE formazione_classi_studenti f
        INNER JOIN formazione_classi_snapshot_studenti ss
            ON ss.id_snapshot = " . dbI($snapshotId) . "
           AND ss.id_studente = f.id_studente
        LEFT JOIN classi c ON c.classe = ss.classe_provvisoria_label
        SET f.classe_provvisoria_label = ss.classe_provvisoria_label,
            f.id_classe_provvisoria = c.id,
            f.bloccato = ss.bloccato,
            f.blocco_individuale = ss.blocco_individuale,
            f.blocco_classe = ss.blocco_classe,
            f.assegnazione_manuale = ss.assegnazione_manuale,
            f.updated_at = NOW()
        WHERE f.id_sessione = " . dbI($sessionId) . "
    ");
    dbExec("UPDATE formazione_classi_sessioni SET updated_at = NOW() WHERE id = " . dbI($sessionId) . " LIMIT 1");
    return ['ok' => true, 'message' => 'Fotografia applicata.'];
}

function formazioneClassiSyncTerzaStudentAddressChange(string $schoolYear, string $codiceFiscale, string $newAddress): void
{
    $schoolYear = trim($schoolYear);
    $codiceFiscale = strtoupper(trim($codiceFiscale));
    $newAddress = trim($newAddress);
    if ($schoolYear === '' || $codiceFiscale === '') {
        return;
    }

    $studentId = intval(dbGetValue("
        SELECT id
        FROM studente
        WHERE UPPER(TRIM(codice_fiscale)) = " . dbQ($codiceFiscale) . "
        ORDER BY id DESC
        LIMIT 1
    ") ?? 0);
    if ($studentId <= 0) {
        return;
    }

    $rows = dbGetAll("
        SELECT
            f.id,
            f.id_classe_provvisoria,
            f.classe_provvisoria_label,
            f.fonte_valori,
            f.gruppo_origine,
            s.id AS session_id,
            s.indirizzo AS session_address
        FROM formazione_classi_studenti f
        INNER JOIN formazione_classi_sessioni s ON s.id = f.id_sessione
        INNER JOIN anno_scolastico a ON a.id = s.id_anno_scolastico_target
        WHERE f.id_studente = " . dbI($studentId) . "
          AND s.tipo_formazione = 'terze'
          AND a.anno = " . dbQ($schoolYear) . "
    ") ?: [];

    foreach ($rows as $row) {
        $sessionAddress = trim((string)($row['session_address'] ?? ''));
        $sessionMatches = $newAddress !== '' && formazioneClassiAddressKeysMatchStrict($sessionAddress, $newAddress);
        if (!$sessionMatches) {
            dbExec("DELETE FROM formazione_classi_studenti WHERE id = " . dbI($row['id'] ?? 0) . " LIMIT 1");
            continue;
        }

        $classLabel = trim((string)($row['classe_provvisoria_label'] ?? ''));
        if ($classLabel === '') {
            continue;
        }
        $classAddress = formazioneClassiClassLabelAddressKey($classLabel);
        if ($classAddress !== '' && !formazioneClassiAddressKeysMatchStrict($classAddress, $newAddress)) {
            dbExec("
                UPDATE formazione_classi_studenti
                SET id_classe_provvisoria = NULL,
                    classe_provvisoria_label = NULL,
                    assegnazione_manuale = 0,
                    updated_at = NOW()
                WHERE id = " . dbI($row['id'] ?? 0) . "
                LIMIT 1
            ");
        }
    }
}

function formazioneClassiAutoAssign(int $sessionId, array $rowIds, array $targetLabels, array $weights = [], string $tabletFilter = 'all', array $targetCounts = []): array
{
    $rowIds = array_values(array_unique(array_filter(array_map('intval', $rowIds), static function (int $id): bool {
        return $id > 0;
    })));
    $targetLabels = array_values(array_unique(array_filter(array_map(static function ($label): string {
        return trim((string)$label);
    }, $targetLabels), static function (string $label): bool {
        return $label !== '';
    })));

    if ($sessionId <= 0 || empty($targetLabels)) {
        return ['ok' => false, 'message' => 'Dati insufficienti per la distribuzione automatica.'];
    }

    $session = dbGetFirst("
        SELECT *
        FROM formazione_classi_sessioni
        WHERE id = " . dbI($sessionId) . "
        LIMIT 1
    ");
    $targetClassYear = formazioneClassiAnnoDaTipo((string)($session['tipo_formazione'] ?? ''));
    if (!formazioneClassiAutoDistributionAllowedForYear($targetClassYear)) {
        return ['ok' => false, 'message' => 'Distribuzione automatica disponibile solo per future prime e future terze.'];
    }
    $targetYearId = intval($session['id_anno_scolastico_target'] ?? 0);
    $indirizzo = (string)($session['indirizzo'] ?? '');
    $tabletFilter = in_array($targetClassYear, [1, 2], true) ? formazioneClassiNormalizeTabletFilter($tabletFilter) : 'all';
    if ($targetClassYear > 0 && $targetYearId > 0) {
        $targetLabels = array_values(array_filter($targetLabels, static function (string $label) use ($targetClassYear, $indirizzo, $targetYearId): bool {
            return formazioneClassiIsActiveTargetClassLabel($label, $targetClassYear, $indirizzo, $targetYearId);
        }));
    }
    if (empty($targetLabels)) {
        return ['ok' => false, 'message' => 'Nessuna classe attiva disponibile per la distribuzione automatica.'];
    }
    formazioneClassiPruneCatCurvatureAssignments($sessionId, $targetClassYear);
    $targetCountLimits = [];
    foreach ($targetCounts as $label => $value) {
        $label = trim((string)$label);
        $limit = intval($value);
        if ($label !== '' && $limit > 0 && in_array($label, $targetLabels, true)) {
            $targetCountLimits[$label] = $limit;
        }
    }

    $metricKeys = ['media_generale', 'voto_matematica', 'voto_italiano', 'voto_capacita_relazionale'];
    $defaultWeights = [
        'media_generale' => 4.0,
        'voto_matematica' => 3.0,
        'voto_italiano' => 2.0,
        'voto_capacita_relazionale' => 2.0,
        'rapporto_mf' => 2.0,
    ];
    if ($targetClassYear === 1) {
        $defaultWeights['voto_capacita_relazionale'] = 0.0;
    }
    foreach ($defaultWeights as $key => $default) {
        $value = str_replace(',', '.', trim((string)($weights[$key] ?? $default)));
        $weights[$key] = max(0.0, (float)$value);
    }

    $allowedCandidateIds = array_fill_keys($rowIds, true);
    $candidateIdList = !empty($rowIds) ? implode(',', array_map('dbI', $rowIds)) : '0';
    $labelList = implode(',', array_map('dbQ', $targetLabels));

    $lockedLabels = dbGetAll("
        SELECT DISTINCT classe_provvisoria_label
        FROM formazione_classi_studenti
        WHERE id_sessione = " . dbI($sessionId) . "
          AND classe_provvisoria_label IN ($labelList)
          AND COALESCE(blocco_classe, 0) = 1
    ") ?: [];
    if (!empty($lockedLabels)) {
        $locked = array_fill_keys(array_map(static function ($row): string {
            return trim((string)($row['classe_provvisoria_label'] ?? ''));
        }, $lockedLabels), true);
        $targetLabels = array_values(array_filter($targetLabels, static function (string $label) use ($locked): bool {
            return !isset($locked[$label]);
        }));
        if (empty($targetLabels)) {
            return ['ok' => false, 'message' => 'Tutte le classi visibili sono bloccate.'];
        }
        $labelList = implode(',', array_map('dbQ', $targetLabels));
    }

    $tabletCandidateCondition = '';
    if ($targetClassYear === 1 && $tabletFilter === 'tablet') {
        $tabletCandidateCondition = " AND f.fonte_valori = 'iscrizioni' AND COALESCE(f.richiesta_tablet, 0) = 1";
    } elseif ($targetClassYear === 1 && $tabletFilter === 'non_tablet') {
        $tabletCandidateCondition = " AND (f.fonte_valori <> 'iscrizioni' OR COALESCE(f.richiesta_tablet, 0) <> 1)";
    }
    $candidateBlockCondition = "(COALESCE(f.bloccato, 0) = 0 OR (COALESCE(f.blocco_classe, 0) = 1 AND COALESCE(f.blocco_individuale, 0) = 0 AND COALESCE(f.classe_provvisoria_label, '') NOT IN ($labelList)))";
    $candidateCondition = $targetClassYear === 1
        ? "f.gruppo_origine IN ('neo_iscritto', 'promosso')" . $tabletCandidateCondition
        : "f.gruppo_origine IN ('promosso', 'neo_iscritto')";
    $blockingOutgoingStateList = implode(',', array_map('dbQ', formazioneClassiBlockingOutgoingStates()));
    $candidateOutgoingCondition = "NOT EXISTS (
        SELECT 1
        FROM studenti_movimenti_pratiche mp_block
        WHERE mp_block.id_studente = f.id_studente
          AND mp_block.tipo_pratica IN ('uscita', 'ritiro')
          AND mp_block.stato_pratica IN ($blockingOutgoingStateList)
          AND mp_block.stato_pratica <> 'annullata'
        LIMIT 1
    )";

    $allRows = dbGetAll("
        SELECT f.*, s.cognome, s.nome, s.sesso, attr.attributi_riservati_raw
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
        WHERE f.id_sessione = " . dbI($sessionId) . "
          AND (
              (
                  f.id IN ($candidateIdList)
                  AND $candidateCondition
                  AND $candidateBlockCondition
                  AND $candidateOutgoingCondition
              )
              OR COALESCE(f.classe_provvisoria_label, '') IN ($labelList)
          )
    ") ?: [];

    $candidateById = [];
    $buckets = [];
    foreach ($targetLabels as $label) {
        $buckets[$label] = [
            'label' => $label,
            'students' => [],
            'count' => 0,
            'maschi' => 0,
            'femmine' => 0,
            'dsa' => 0,
            'legge_104' => 0,
            'sums' => array_fill_keys($metricKeys, 0.0),
            'counts' => array_fill_keys($metricKeys, 0),
        ];
    }

    foreach ($allRows as $row) {
        $id = intval($row['id'] ?? 0);
        if ((string)($row['gruppo_origine'] ?? '') === 'bocciato') {
            continue;
        }
        $currentLabel = trim((string)($row['classe_provvisoria_label'] ?? ''));
        $classBlockedOnlyOutsideVisibleTargets = intval($row['blocco_classe'] ?? 0) === 1
            && intval($row['blocco_individuale'] ?? 0) === 0
            && !in_array($currentLabel, $targetLabels, true);
        $isCandidate = (intval($row['bloccato'] ?? 0) === 0 || $classBlockedOnlyOutsideVisibleTargets)
            && (
                ($targetClassYear === 1
                    && in_array((string)($row['gruppo_origine'] ?? ''), ['neo_iscritto', 'promosso'], true)
                    && ($tabletFilter === 'all'
                        || ($tabletFilter === 'tablet' && (string)($row['fonte_valori'] ?? '') === 'iscrizioni' && intval($row['richiesta_tablet'] ?? 0) === 1)
                        || ($tabletFilter === 'non_tablet' && ((string)($row['fonte_valori'] ?? '') !== 'iscrizioni' || intval($row['richiesta_tablet'] ?? 0) !== 1))))
                || ($targetClassYear !== 1
                    && in_array((string)($row['gruppo_origine'] ?? ''), ['promosso', 'neo_iscritto'], true))
            );
        if ($isCandidate && !formazioneClassiAutoRowHasNoBlockingOutgoing(intval($row['id_studente'] ?? 0))) {
            $isCandidate = false;
        }
        if ($isCandidate && isset($allowedCandidateIds[$id])) {
            $candidateById[$id] = $row;
            continue;
        }
        $label = trim((string)($row['classe_provvisoria_label'] ?? ''));
        if (isset($buckets[$label])) {
            formazioneClassiAutoBucketAdd($buckets[$label], $row, $metricKeys);
        }
    }

    if (empty($candidateById)) {
        return ['ok' => false, 'message' => 'Nessuno studente spostabile trovato.'];
    }

    $global = [
        'count' => 0,
        'maschi' => 0,
        'femmine' => 0,
        'dsa' => 0,
        'legge_104' => 0,
        'sums' => array_fill_keys($metricKeys, 0.0),
        'counts' => array_fill_keys($metricKeys, 0),
    ];
    foreach ($buckets as $bucket) {
        formazioneClassiAutoBucketMerge($global, $bucket, $metricKeys);
    }
    foreach ($candidateById as $row) {
        formazioneClassiAutoBucketAdd($global, $row, $metricKeys);
    }

    $globalAverages = [];
    foreach ($metricKeys as $key) {
        $globalAverages[$key] = $global['counts'][$key] > 0 ? $global['sums'][$key] / $global['counts'][$key] : null;
    }
    $genderTotal = max(1, intval($global['maschi']) + intval($global['femmine']));
    $globalMaleRatio = intval($global['maschi']) / $genderTotal;
    $totalStudentsForDistribution = count($candidateById) + array_sum(array_map(static function ($bucket) {
        return intval($bucket['count'] ?? 0);
    }, $buckets));
    $classCount = count($targetLabels);
    $idealCount = $classCount > 0 ? $totalStudentsForDistribution / $classCount : 0;
    $minTargetCount = $classCount > 0 ? intdiv($totalStudentsForDistribution, $classCount) : 0;
    $maxCount = $classCount > 0 ? (int)ceil($idealCount) : 0;
    $totalFemales = intval($global['femmine'] ?? 0);
    $femalePairTarget = $classCount > 0 ? min($classCount, intdiv($totalFemales, 2)) : 0;
    $femaleSingleRemainder = $classCount > $femalePairTarget ? min($classCount - $femalePairTarget, $totalFemales - ($femalePairTarget * 2)) : 0;
    $minFemalesPerClass = $totalFemales >= ($classCount * 2) ? 2 : 0;

    $candidates = array_values($candidateById);

    if (!empty($targetCountLimits)) {
        $availableSlots = 0;
        foreach ($targetLabels as $label) {
            $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
            if ($labelMaxCount <= 0) {
                $availableSlots = PHP_INT_MAX;
                break;
            }
            $availableSlots += max(0, $labelMaxCount - intval($buckets[$label]['count'] ?? 0));
        }
        if ($availableSlots !== PHP_INT_MAX && count($candidateById) > $availableSlots) {
            return [
                'ok' => false,
                'message' => 'Gli obiettivi impostati non bastano: posti disponibili ' . $availableSlots . ', studenti da distribuire ' . count($candidateById) . '.',
            ];
        }
    }

    usort($candidates, static function (array $a, array $b) use ($weights, $metricKeys): int {
        $priorityA = formazioneClassiAutoPriority($a);
        $priorityB = formazioneClassiAutoPriority($b);
        if ($priorityA !== $priorityB) {
            return $priorityA <=> $priorityB;
        }
        $scoreA = formazioneClassiAutoScore($a, $weights, $metricKeys);
        $scoreB = formazioneClassiAutoScore($b, $weights, $metricKeys);
        if (abs($scoreA - $scoreB) > 0.0001) {
            return $scoreA < $scoreB ? 1 : -1;
        }
        $nameA = trim((string)($a['cognome'] ?? '') . ' ' . (string)($a['nome'] ?? '') . ' ' . (string)($a['studente_nome'] ?? ''));
        $nameB = trim((string)($b['cognome'] ?? '') . ' ' . (string)($b['nome'] ?? '') . ' ' . (string)($b['studente_nome'] ?? ''));
        return strcasecmp($nameA, $nameB);
    });

    $assignments = [];
    $assignedRows = [];
    $remainingRows = $candidates;
    while (!empty($remainingRows)) {
        $row = array_shift($remainingRows);
        $bestLabel = '';
        $bestCost = null;
        $minCount = null;
        foreach ($targetLabels as $label) {
            $count = intval($buckets[$label]['count'] ?? 0);
            if ($minCount === null || $count < $minCount) {
                $minCount = $count;
            }
        }
        $eligibleLabels = formazioneClassiAutoEligibleLabels($targetLabels, $buckets, $row, $remainingRows, $minCount, $maxCount, $femalePairTarget, $femaleSingleRemainder, $targetCountLimits);
        $curvatureLabels = formazioneClassiAutoCurvatureEligibleLabels($eligibleLabels, $row, $targetClassYear);
        if (empty($curvatureLabels)) {
            $curvatureLabels = formazioneClassiAutoCurvatureEligibleLabels($targetLabels, $row, $targetClassYear);
        }
        if (!empty($curvatureLabels)) {
            $eligibleLabels = $curvatureLabels;
        }
        foreach ($eligibleLabels as $label) {
            $cost = formazioneClassiAutoClassCost(
                $buckets,
                $targetLabels,
                $label,
                $row,
                $weights,
                $metricKeys,
                $globalAverages,
                $globalMaleRatio,
                $idealCount,
                $minTargetCount,
                $maxCount,
                $minFemalesPerClass,
                $femalePairTarget,
                $femaleSingleRemainder
            );
            if ($bestCost === null || $cost < $bestCost || (abs($cost - $bestCost) < 0.0001 && intval($buckets[$label]['count']) < intval($buckets[$bestLabel]['count'] ?? 999999))) {
                $bestCost = $cost;
                $bestLabel = $label;
            }
        }
        if ($bestLabel === '') {
            $bestLabel = formazioneClassiAutoLeastFilledLabelForRow($targetLabels, $buckets, $row, $targetClassYear, $maxCount, $targetCountLimits);
            if ($bestLabel === '') {
                continue;
            }
        }
        $assignments[intval($row['id'])] = $bestLabel;
        $assignedRows[intval($row['id'])] = $row;
        formazioneClassiAutoBucketAdd($buckets[$bestLabel], $row, $metricKeys);
    }

    formazioneClassiAutoOptimizeAssignments(
        $assignments,
        $assignedRows,
        $buckets,
        $targetLabels,
        $weights,
        $metricKeys,
        $globalAverages,
        $globalMaleRatio,
        $idealCount,
        $minTargetCount,
        $maxCount,
        $minFemalesPerClass,
        $femalePairTarget,
        $femaleSingleRemainder,
        $targetClassYear
    );
    formazioneClassiAutoBalanceCounts($assignments, $assignedRows, $buckets, $targetLabels, $metricKeys, $minTargetCount, $maxCount, 0, $targetCountLimits, $targetClassYear);
    formazioneClassiAutoBalanceFemales($assignments, $assignedRows, $buckets, $targetLabels, $metricKeys, $minFemalesPerClass, $targetClassYear);
    formazioneClassiAutoBalanceCounts($assignments, $assignedRows, $buckets, $targetLabels, $metricKeys, $minTargetCount, $maxCount, $minFemalesPerClass, $targetCountLimits, $targetClassYear);

    foreach ($assignments as $rowId => $label) {
        $id = intval($rowId);
        if (!isset($assignedRows[$id]) || !formazioneClassiAutoRowCompatibleWithLabel($assignedRows[$id], (string)$label, $targetClassYear)) {
            unset($assignments[$rowId]);
        }
    }

    if (!empty($assignments)) {
        formazioneClassiUndoPush(
            $sessionId,
            'auto_assign',
            'Distribuzione automatica di ' . count($assignments) . ' studenti',
            formazioneClassiUndoCaptureRows($sessionId, array_map('intval', array_keys($assignments))),
            ['assignments' => $assignments]
        );
    }

    foreach ($assignments as $rowId => $label) {
        $targetClass = formazioneClassiLocalClassByLabel($label);
        $classId = $targetClass ? intval($targetClass['id']) : null;
        $updateCandidateWhere = $targetClassYear === 1
            ? "AND gruppo_origine IN ('neo_iscritto', 'promosso')"
            : "AND gruppo_origine IN ('promosso', 'neo_iscritto')";
        dbExec("
            UPDATE formazione_classi_studenti
            SET id_classe_provvisoria = " . dbI($classId) . ",
                classe_provvisoria_label = " . dbQ($label) . ",
                blocco_classe = 0,
                bloccato = CASE WHEN COALESCE(blocco_individuale, 0) = 1 THEN 1 ELSE 0 END,
                assegnazione_manuale = 1,
                updated_at = NOW()
            WHERE id = " . dbI($rowId) . "
              AND id_sessione = " . dbI($sessionId) . "
              AND (
                  bloccato = 0
                  OR (COALESCE(blocco_classe, 0) = 1 AND COALESCE(blocco_individuale, 0) = 0 AND COALESCE(classe_provvisoria_label, '') NOT IN ($labelList))
              )
              $updateCandidateWhere
            LIMIT 1
        ");
    }
    formazioneClassiPruneCatCurvatureAssignments($sessionId, $targetClassYear);
    dbExec("UPDATE formazione_classi_sessioni SET updated_at = NOW() WHERE id = " . dbI($sessionId) . " LIMIT 1");

    return [
        'ok' => true,
        'message' => count($assignments) . ' studenti distribuiti automaticamente.',
        'assignments' => $assignments,
    ];
}

function formazioneClassiAutoBucketAdd(array &$bucket, array $row, array $metricKeys): void
{
    $bucket['count'] = intval($bucket['count'] ?? 0) + 1;
    $sesso = strtoupper(trim((string)($row['sesso'] ?? '')));
    if ($sesso === 'M') {
        $bucket['maschi'] = intval($bucket['maschi'] ?? 0) + 1;
    } elseif ($sesso === 'F') {
        $bucket['femmine'] = intval($bucket['femmine'] ?? 0) + 1;
    }
    if (formazioneClassiAutoRowHasAttr($row, STUD_ATTR_R7A2)) {
        $bucket['dsa'] = intval($bucket['dsa'] ?? 0) + 1;
    }
    if (formazioneClassiAutoRowHasAttr($row, STUD_ATTR_Q4M9)) {
        $bucket['legge_104'] = intval($bucket['legge_104'] ?? 0) + 1;
    }
    foreach ($metricKeys as $key) {
        $value = formazioneClassiNullableFloat($row[$key] ?? null);
        if ($value === null) {
            continue;
        }
        $bucket['sums'][$key] = (float)($bucket['sums'][$key] ?? 0) + $value;
        $bucket['counts'][$key] = intval($bucket['counts'][$key] ?? 0) + 1;
    }
}

function formazioneClassiAutoBucketRemove(array &$bucket, array $row, array $metricKeys): void
{
    $bucket['count'] = max(0, intval($bucket['count'] ?? 0) - 1);
    $sesso = strtoupper(trim((string)($row['sesso'] ?? '')));
    if ($sesso === 'M') {
        $bucket['maschi'] = max(0, intval($bucket['maschi'] ?? 0) - 1);
    } elseif ($sesso === 'F') {
        $bucket['femmine'] = max(0, intval($bucket['femmine'] ?? 0) - 1);
    }
    if (formazioneClassiAutoRowHasAttr($row, STUD_ATTR_R7A2)) {
        $bucket['dsa'] = max(0, intval($bucket['dsa'] ?? 0) - 1);
    }
    if (formazioneClassiAutoRowHasAttr($row, STUD_ATTR_Q4M9)) {
        $bucket['legge_104'] = max(0, intval($bucket['legge_104'] ?? 0) - 1);
    }
    foreach ($metricKeys as $key) {
        $value = formazioneClassiNullableFloat($row[$key] ?? null);
        if ($value === null) {
            continue;
        }
        $bucket['sums'][$key] = (float)($bucket['sums'][$key] ?? 0) - $value;
        $bucket['counts'][$key] = max(0, intval($bucket['counts'][$key] ?? 0) - 1);
        if (abs((float)$bucket['sums'][$key]) < 0.0001) {
            $bucket['sums'][$key] = 0.0;
        }
    }
}

function formazioneClassiAutoBucketMerge(array &$target, array $source, array $metricKeys): void
{
    $target['count'] = intval($target['count'] ?? 0) + intval($source['count'] ?? 0);
    $target['maschi'] = intval($target['maschi'] ?? 0) + intval($source['maschi'] ?? 0);
    $target['femmine'] = intval($target['femmine'] ?? 0) + intval($source['femmine'] ?? 0);
    foreach ($metricKeys as $key) {
        $target['sums'][$key] = (float)($target['sums'][$key] ?? 0) + (float)($source['sums'][$key] ?? 0);
        $target['counts'][$key] = intval($target['counts'][$key] ?? 0) + intval($source['counts'][$key] ?? 0);
    }
}

function formazioneClassiAutoScore(array $row, array $weights, array $metricKeys): float
{
    $score = 0.0;
    foreach ($metricKeys as $key) {
        $value = formazioneClassiNullableFloat($row[$key] ?? null);
        if ($value === null) {
            continue;
        }
        $score += $value * (float)($weights[$key] ?? 0);
    }
    return $score;
}

function formazioneClassiAutoEffectiveMaxForLabel(string $label, array $targetCountLimits, int $defaultMaxCount): int
{
    $limit = intval($targetCountLimits[$label] ?? 0);
    if ($limit > 0) {
        return $limit;
    }
    if (!empty($targetCountLimits)) {
        return 0;
    }
    return max(0, $defaultMaxCount);
}

function formazioneClassiAutoPriority(array $row): int
{
    $sesso = strtoupper(trim((string)($row['sesso'] ?? '')));
    if ($sesso === 'F') {
        return 10;
    }
    if (formazioneClassiAutoRowHasAttr($row, STUD_ATTR_R7A2)) {
        return 20;
    }
    if (formazioneClassiAutoRowHasAttr($row, STUD_ATTR_Q4M9)) {
        return 30;
    }
    return 40;
}

function formazioneClassiAutoRowHasAttr(array $row, string $code): bool
{
    $raw = (string)($row['attributi_riservati_raw'] ?? '');
    if ($raw === '') {
        return false;
    }
    foreach (explode('||', $raw) as $part) {
        $attrCode = strtok((string)$part, '|');
        if ($attrCode === $code) {
            return true;
        }
    }
    return false;
}

function formazioneClassiAutoLabelsWithMinCounter(array $labels, array $buckets, string $counter, int $maxCount, array $targetCountLimits = []): array
{
    $best = [];
    $min = null;
    foreach ($labels as $label) {
        $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
        if ($labelMaxCount > 0 && intval($buckets[$label]['count'] ?? 0) >= $labelMaxCount) {
            continue;
        }
        $value = intval($buckets[$label][$counter] ?? 0);
        if ($min === null || $value < $min) {
            $min = $value;
            $best = [$label];
        } elseif ($value === $min) {
            $best[] = $label;
        }
    }
    return $best;
}

function formazioneClassiAutoEligibleLabels(array $targetLabels, array $buckets, array $row, array $remainingRows, ?int $minCount, int $maxCount, int $femalePairTarget, int $femaleSingleRemainder, array $targetCountLimits = []): array
{
    $underMin = array_values(array_filter($targetLabels, static function (string $label) use ($buckets, $minCount): bool {
        return intval($buckets[$label]['count'] ?? 0) === intval($minCount);
    }));
    $labels = $underMin ?: $targetLabels;

    $sesso = strtoupper(trim((string)($row['sesso'] ?? '')));
    if ($sesso === 'F' && $femalePairTarget > 0) {
        $pairedClasses = 0;
        $singleClasses = 0;
        foreach ($targetLabels as $label) {
            $femaleCount = intval($buckets[$label]['femmine'] ?? 0);
            if ($femaleCount >= 2) {
                $pairedClasses++;
            } elseif ($femaleCount === 1) {
                $singleClasses++;
            }
        }

        if ($pairedClasses < $femalePairTarget) {
            $completePairLabels = array_values(array_filter($targetLabels, static function (string $label) use ($buckets, $maxCount, $targetCountLimits): bool {
                $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
                return intval($buckets[$label]['femmine'] ?? 0) === 1
                    && ($labelMaxCount <= 0 || intval($buckets[$label]['count'] ?? 0) < $labelMaxCount);
            }));
            if ($completePairLabels) {
                return $completePairLabels;
            }
            $emptyFemaleLabels = array_values(array_filter($targetLabels, static function (string $label) use ($buckets, $maxCount, $targetCountLimits): bool {
                $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
                return intval($buckets[$label]['femmine'] ?? 0) === 0
                    && ($labelMaxCount <= 0 || intval($buckets[$label]['count'] ?? 0) < $labelMaxCount);
            }));
            if ($emptyFemaleLabels) {
                return $emptyFemaleLabels;
            }
        } elseif ($femaleSingleRemainder > 0 && $singleClasses < $femaleSingleRemainder) {
            $emptyFemaleLabels = array_values(array_filter($targetLabels, static function (string $label) use ($buckets, $maxCount, $targetCountLimits): bool {
                $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
                return intval($buckets[$label]['femmine'] ?? 0) === 0
                    && ($labelMaxCount <= 0 || intval($buckets[$label]['count'] ?? 0) < $labelMaxCount);
            }));
            if ($emptyFemaleLabels) {
                return $emptyFemaleLabels;
            }
        }
    }

    if (formazioneClassiAutoRowHasAttr($row, STUD_ATTR_R7A2)) {
        $leastDsa = formazioneClassiAutoLabelsWithMinCounter($labels, $buckets, 'dsa', $maxCount, $targetCountLimits);
        if ($leastDsa) {
            return $leastDsa;
        }
    }

    if (formazioneClassiAutoRowHasAttr($row, STUD_ATTR_Q4M9)) {
        $least104 = formazioneClassiAutoLabelsWithMinCounter($labels, $buckets, 'legge_104', $maxCount, $targetCountLimits);
        if ($least104) {
            return $least104;
        }
    }

    if ($maxCount > 0 || !empty($targetCountLimits)) {
        $notFull = array_values(array_filter($labels, static function (string $label) use ($buckets, $maxCount, $targetCountLimits): bool {
            $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
            return $labelMaxCount <= 0 || intval($buckets[$label]['count'] ?? 0) < $labelMaxCount;
        }));
        if ($notFull) {
            return $notFull;
        }
        if ($labels !== $targetLabels) {
            $notFull = array_values(array_filter($targetLabels, static function (string $label) use ($buckets, $maxCount, $targetCountLimits): bool {
                $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
                return $labelMaxCount <= 0 || intval($buckets[$label]['count'] ?? 0) < $labelMaxCount;
            }));
            if ($notFull) {
                return $notFull;
            }
        }
    }

    return $labels ?: $targetLabels;
}

function formazioneClassiAutoCurvatureEligibleLabels(array $labels, array $row, int $targetClassYear): array
{
    if (!in_array($targetClassYear, [3, 4], true)) {
        return $labels;
    }
    $curvature = formazioneClassiExtractCurvatureFromNote((string)($row['note'] ?? ''));
    if ($curvature === '') {
        return $labels;
    }
    $filtered = array_values(array_filter($labels, static function (string $label) use ($curvature): bool {
        $norm = formazioneClassiNorm($label);
        $isCatClass = preg_match('/^[34]CT[A-D]/', $norm) === 1;
        if (!$isCatClass) {
            return true;
        }
        if ($curvature === 'design') {
            return preg_match('/^[34]CT[CD]/', $norm) === 1;
        }
        if ($curvature === 'normale') {
            return preg_match('/^[34]CT[AB]/', $norm) === 1;
        }
        return true;
    }));
    return $filtered;
}

function formazioneClassiAutoRowCompatibleWithLabel(array $row, string $label, int $targetClassYear): bool
{
    if (!in_array($targetClassYear, [3, 4], true)) {
        return true;
    }
    $norm = formazioneClassiNorm($label);
    if (preg_match('/^[34]CT[A-D]/', $norm) !== 1) {
        return true;
    }
    $curvature = formazioneClassiExtractCurvatureFromNote((string)($row['note'] ?? ''));
    if ($curvature === 'design') {
        return preg_match('/^[34]CT[CD]/', $norm) === 1;
    }
    if ($curvature === 'normale') {
        return preg_match('/^[34]CT[AB]/', $norm) === 1;
    }
    return true;
}

function formazioneClassiAutoClassCost(array $buckets, array $targetLabels, string $label, array $row, array $weights, array $metricKeys, array $globalAverages, float $globalMaleRatio, float $idealCount, int $minCount, int $maxCount, int $minFemalesPerClass = 0, int $femalePairTarget = 0, int $femaleSingleRemainder = 0): float
{
    if (!isset($buckets[$label])) {
        return 999999999.0;
    }
    formazioneClassiAutoBucketAdd($buckets[$label], $row, $metricKeys);
    return formazioneClassiAutoPlanCost($buckets, $targetLabels, $weights, $metricKeys, $globalAverages, $globalMaleRatio, $idealCount, $minCount, $maxCount, $minFemalesPerClass, $femalePairTarget, $femaleSingleRemainder);
}

function formazioneClassiAutoPlanCost(array $buckets, array $targetLabels, array $weights, array $metricKeys, array $globalAverages, float $globalMaleRatio, float $idealCount, int $minCount, int $maxCount, int $minFemalesPerClass = 0, int $femalePairTarget = 0, int $femaleSingleRemainder = 0): float
{
    $cost = 0.0;
    $femaleCounts = [];
    foreach ($targetLabels as $label) {
        $bucket = $buckets[$label] ?? [];
        $count = intval($bucket['count'] ?? 0);
        $deltaCount = $count - $idealCount;
        $cost += ($deltaCount * $deltaCount) * 1000000000.0;
        if ($count < $minCount) {
            $cost += ($minCount - $count) * 10000000000.0;
        }
        if ($maxCount > 0 && $count > $maxCount) {
            $cost += ($count - $maxCount) * 10000000000.0;
        }
        $femaleCounts[] = intval($bucket['femmine'] ?? 0);
        $genderWeight = (float)($weights['rapporto_mf'] ?? 0);
        $knownGender = intval($bucket['maschi'] ?? 0) + intval($bucket['femmine'] ?? 0);
        if ($genderWeight > 0 && $knownGender > 0) {
            $cost += abs((intval($bucket['maschi'] ?? 0) / $knownGender) - $globalMaleRatio) * $genderWeight * 1000.0;
        }
    }

    rsort($femaleCounts, SORT_NUMERIC);
    foreach ($femaleCounts as $index => $femaleCount) {
        if ($index < $femalePairTarget) {
            $pairDeficit = max(0, 2 - $femaleCount);
            if ($pairDeficit > 0) {
                $cost += $pairDeficit * 500000000.0;
            }
            continue;
        }
        if ($index < ($femalePairTarget + $femaleSingleRemainder)) {
            $singleDeficit = max(0, 1 - $femaleCount);
            if ($singleDeficit > 0) {
                $cost += $singleDeficit * 250000000.0;
            }
            if ($femaleCount > 1) {
                $cost += ($femaleCount - 1) * 50000000.0;
            }
            continue;
        }
        if ($femaleCount > 0) {
            $cost += $femaleCount * 250000000.0;
        }
    }

    foreach ($metricKeys as $key) {
        $weight = (float)($weights[$key] ?? 0);
        if ($weight <= 0 || $globalAverages[$key] === null) {
            continue;
        }
        $averages = [];
        foreach ($targetLabels as $label) {
            $bucket = $buckets[$label] ?? [];
            $metricCount = intval($bucket['counts'][$key] ?? 0);
            if ($metricCount <= 0) {
                continue;
            }
            $averages[] = (float)($bucket['sums'][$key] ?? 0) / $metricCount;
        }
        if (empty($averages)) {
            continue;
        }
        $minAvg = min($averages);
        $maxAvg = max($averages);
        $spread = $maxAvg - $minAvg;
        $multiplier = 50000.0;
        if ($key === 'media_generale') {
            $multiplier = 1000000.0;
        } elseif ($key === 'voto_matematica') {
            $multiplier = 250000.0;
        } elseif ($key === 'voto_italiano') {
            $multiplier = 125000.0;
        } elseif ($key === 'voto_capacita_relazionale') {
            $multiplier = 75000.0;
        }
        $cost += ($spread * $spread) * $weight * $multiplier;
        foreach ($averages as $avg) {
            $delta = $avg - (float)$globalAverages[$key];
            $cost += ($delta * $delta) * $weight * ($multiplier / 3.0);
        }
    }
    return $cost;
}

function formazioneClassiAutoOptimizeAssignments(array &$assignments, array $assignedRows, array &$buckets, array $targetLabels, array $weights, array $metricKeys, array $globalAverages, float $globalMaleRatio, float $idealCount, int $minCount, int $maxCount, int $minFemalesPerClass = 0, int $femalePairTarget = 0, int $femaleSingleRemainder = 0, int $targetClassYear = 0): void
{
    if (count($assignments) < 2 || count($targetLabels) < 2) {
        return;
    }

    $currentCost = formazioneClassiAutoPlanCost($buckets, $targetLabels, $weights, $metricKeys, $globalAverages, $globalMaleRatio, $idealCount, $minCount, $maxCount, $minFemalesPerClass, $femalePairTarget, $femaleSingleRemainder);
    $ids = array_keys($assignments);
    $maxPasses = 80;
    for ($pass = 0; $pass < $maxPasses; $pass++) {
        $bestSwap = null;
        $bestCost = $currentCost;
        $countIds = count($ids);
        for ($i = 0; $i < $countIds; $i++) {
            $idA = intval($ids[$i]);
            $labelA = (string)($assignments[$idA] ?? '');
            if ($labelA === '' || !isset($assignedRows[$idA])) {
                continue;
            }
            for ($j = $i + 1; $j < $countIds; $j++) {
                $idB = intval($ids[$j]);
                $labelB = (string)($assignments[$idB] ?? '');
                if ($labelB === '' || $labelA === $labelB || !isset($assignedRows[$idB])) {
                    continue;
                }
                if (!formazioneClassiAutoRowCompatibleWithLabel($assignedRows[$idA], $labelB, $targetClassYear)
                    || !formazioneClassiAutoRowCompatibleWithLabel($assignedRows[$idB], $labelA, $targetClassYear)) {
                    continue;
                }

                $trialBuckets = $buckets;
                formazioneClassiAutoBucketRemove($trialBuckets[$labelA], $assignedRows[$idA], $metricKeys);
                formazioneClassiAutoBucketRemove($trialBuckets[$labelB], $assignedRows[$idB], $metricKeys);
                formazioneClassiAutoBucketAdd($trialBuckets[$labelA], $assignedRows[$idB], $metricKeys);
                formazioneClassiAutoBucketAdd($trialBuckets[$labelB], $assignedRows[$idA], $metricKeys);
                $trialCost = formazioneClassiAutoPlanCost($trialBuckets, $targetLabels, $weights, $metricKeys, $globalAverages, $globalMaleRatio, $idealCount, $minCount, $maxCount, $minFemalesPerClass, $femalePairTarget, $femaleSingleRemainder);
                if ($trialCost + 0.0001 < $bestCost) {
                    $bestCost = $trialCost;
                    $bestSwap = [$idA, $idB, $labelA, $labelB];
                }
            }
        }

        if ($bestSwap === null) {
            break;
        }

        $idA = intval($bestSwap[0]);
        $idB = intval($bestSwap[1]);
        $labelA = (string)$bestSwap[2];
        $labelB = (string)$bestSwap[3];
        formazioneClassiAutoBucketRemove($buckets[$labelA], $assignedRows[$idA], $metricKeys);
        formazioneClassiAutoBucketRemove($buckets[$labelB], $assignedRows[$idB], $metricKeys);
        formazioneClassiAutoBucketAdd($buckets[$labelA], $assignedRows[$idB], $metricKeys);
        formazioneClassiAutoBucketAdd($buckets[$labelB], $assignedRows[$idA], $metricKeys);
        $assignments[$idA] = $labelB;
        $assignments[$idB] = $labelA;
        $currentCost = $bestCost;
    }
}

function formazioneClassiAutoLeastFilledLabel(array $targetLabels, array $buckets, int $maxCount = 0, array $targetCountLimits = []): string
{
    $bestLabel = '';
    $bestCount = null;
    foreach ($targetLabels as $label) {
        $count = intval($buckets[$label]['count'] ?? 0);
        $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
        if ($labelMaxCount > 0 && $count >= $labelMaxCount) {
            continue;
        }
        if ($bestCount === null || $count < $bestCount || ($count === $bestCount && strnatcasecmp($label, $bestLabel) < 0)) {
            $bestCount = $count;
            $bestLabel = $label;
        }
    }
    return $bestLabel;
}

function formazioneClassiAutoLeastFilledLabelForRow(array $targetLabels, array $buckets, array $row, int $targetClassYear, int $maxCount = 0, array $targetCountLimits = []): string
{
    $compatibleLabels = array_values(array_filter($targetLabels, static function (string $label) use ($row, $targetClassYear): bool {
        return formazioneClassiAutoRowCompatibleWithLabel($row, $label, $targetClassYear);
    }));
    return formazioneClassiAutoLeastFilledLabel($compatibleLabels ?: $targetLabels, $buckets, $maxCount, $targetCountLimits);
}

function formazioneClassiAutoBalanceCounts(array &$assignments, array $assignedRows, array &$buckets, array $targetLabels, array $metricKeys, int $minCount, int $maxCount, int $minFemalesPerClass = 0, array $targetCountLimits = [], int $targetClassYear = 0): void
{
    if (count($assignments) < 2 || count($targetLabels) < 2 || $maxCount <= 0) {
        return;
    }

    $maxPasses = max(50, count($assignments) * 2);
    for ($pass = 0; $pass < $maxPasses; $pass++) {
        $donorLabel = '';
        $needLabel = '';
        foreach ($targetLabels as $label) {
            $count = intval($buckets[$label]['count'] ?? 0);
            $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
            if ($labelMaxCount > 0 && $count > $labelMaxCount && ($donorLabel === '' || $count > intval($buckets[$donorLabel]['count'] ?? 0))) {
                $donorLabel = $label;
            }
            if ($count < $minCount && ($labelMaxCount <= 0 || $count < $labelMaxCount) && ($needLabel === '' || $count < intval($buckets[$needLabel]['count'] ?? 0))) {
                $needLabel = $label;
            }
        }

        if ($donorLabel !== '' && $needLabel === '') {
            foreach ($targetLabels as $label) {
                $count = intval($buckets[$label]['count'] ?? 0);
                $labelMaxCount = formazioneClassiAutoEffectiveMaxForLabel($label, $targetCountLimits, $maxCount);
                if ($label !== $donorLabel && ($labelMaxCount <= 0 || $count < $labelMaxCount) && ($needLabel === '' || $count < intval($buckets[$needLabel]['count'] ?? 0))) {
                    $needLabel = $label;
                }
            }
        }

        if ($donorLabel === '' || $needLabel === '') {
            return;
        }

        $bestId = 0;
        $bestPenalty = null;
        foreach ($assignments as $rowId => $label) {
            $id = intval($rowId);
            if ((string)$label !== $donorLabel || !isset($assignedRows[$id])) {
                continue;
            }
            $rowGender = strtoupper(trim((string)($assignedRows[$id]['sesso'] ?? '')));
            if ($minFemalesPerClass > 0 && $rowGender === 'F' && intval($buckets[$donorLabel]['femmine'] ?? 0) <= $minFemalesPerClass) {
                continue;
            }
            if (!formazioneClassiAutoRowCompatibleWithLabel($assignedRows[$id], $needLabel, $targetClassYear)) {
                continue;
            }
            $trialBuckets = $buckets;
            formazioneClassiAutoBucketRemove($trialBuckets[$donorLabel], $assignedRows[$id], $metricKeys);
            formazioneClassiAutoBucketAdd($trialBuckets[$needLabel], $assignedRows[$id], $metricKeys);
            $penalty = 0.0;
            foreach ($metricKeys as $key) {
                $donorMetricCount = intval($trialBuckets[$donorLabel]['counts'][$key] ?? 0);
                $needMetricCount = intval($trialBuckets[$needLabel]['counts'][$key] ?? 0);
                if ($donorMetricCount <= 0 || $needMetricCount <= 0) {
                    continue;
                }
                $donorAvg = (float)($trialBuckets[$donorLabel]['sums'][$key] ?? 0) / $donorMetricCount;
                $needAvg = (float)($trialBuckets[$needLabel]['sums'][$key] ?? 0) / $needMetricCount;
                $penalty += abs($donorAvg - $needAvg);
            }
            if ($bestPenalty === null || $penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestId = $id;
            }
        }

        if ($bestId <= 0) {
            return;
        }

        formazioneClassiAutoBucketRemove($buckets[$donorLabel], $assignedRows[$bestId], $metricKeys);
        formazioneClassiAutoBucketAdd($buckets[$needLabel], $assignedRows[$bestId], $metricKeys);
        $assignments[$bestId] = $needLabel;
    }
}

function formazioneClassiAutoBalanceFemales(array &$assignments, array $assignedRows, array &$buckets, array $targetLabels, array $metricKeys, int $minFemalesPerClass, int $targetClassYear = 0): void
{
    if ($minFemalesPerClass <= 0 || count($targetLabels) < 2) {
        return;
    }

    $maxPasses = 200;
    for ($pass = 0; $pass < $maxPasses; $pass++) {
        $needLabel = '';
        foreach ($targetLabels as $label) {
            if (intval($buckets[$label]['femmine'] ?? 0) < $minFemalesPerClass) {
                $needLabel = $label;
                break;
            }
        }
        if ($needLabel === '') {
            return;
        }

        $donorLabel = '';
        foreach ($targetLabels as $label) {
            if (intval($buckets[$label]['femmine'] ?? 0) > $minFemalesPerClass) {
                $donorLabel = $label;
                break;
            }
        }
        if ($donorLabel === '') {
            return;
        }

        $maleId = formazioneClassiAutoFindAssignedByGender($assignments, $assignedRows, $needLabel, 'M');
        $femaleId = formazioneClassiAutoFindAssignedByGender($assignments, $assignedRows, $donorLabel, 'F');
        if ($maleId <= 0 || $femaleId <= 0) {
            return;
        }
        if (!formazioneClassiAutoRowCompatibleWithLabel($assignedRows[$maleId], $donorLabel, $targetClassYear)
            || !formazioneClassiAutoRowCompatibleWithLabel($assignedRows[$femaleId], $needLabel, $targetClassYear)) {
            return;
        }

        formazioneClassiAutoBucketRemove($buckets[$needLabel], $assignedRows[$maleId], $metricKeys);
        formazioneClassiAutoBucketRemove($buckets[$donorLabel], $assignedRows[$femaleId], $metricKeys);
        formazioneClassiAutoBucketAdd($buckets[$needLabel], $assignedRows[$femaleId], $metricKeys);
        formazioneClassiAutoBucketAdd($buckets[$donorLabel], $assignedRows[$maleId], $metricKeys);
        $assignments[$maleId] = $donorLabel;
        $assignments[$femaleId] = $needLabel;
    }
}

function formazioneClassiAutoFindAssignedByGender(array $assignments, array $assignedRows, string $label, string $gender): int
{
    foreach ($assignments as $rowId => $rowLabel) {
        $id = intval($rowId);
        if ((string)$rowLabel !== $label || !isset($assignedRows[$id])) {
            continue;
        }
        if (strtoupper(trim((string)($assignedRows[$id]['sesso'] ?? ''))) === $gender) {
            return $id;
        }
    }
    return 0;
}

function formazioneClassiFormatAvg($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    return number_format((float)$value, 2, ',', '');
}

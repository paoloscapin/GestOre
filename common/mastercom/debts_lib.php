<?php

/**
 * MasterCom debts/carenze integration.
 *
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/admin_lib.php';

function mastercomDebtsEnsureTables(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_carenze` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `mastercom_id_classe` INT NOT NULL,
            `id_classe_gestore` INT NULL,
            `classe` VARCHAR(100) NULL,
            `mastercom_id_studente` INT NOT NULL,
            `id_studente_gestore` INT NULL,
            `studente_nome` VARCHAR(255) NOT NULL,
            `anno_label` VARCHAR(20) NOT NULL,
            `id_anno_scolastico` INT NULL,
            `materia` VARCHAR(255) NOT NULL,
            `id_materia_gestore` INT NULL,
            `recuperato_mastercom` TINYINT(1) NOT NULL DEFAULT 0,
            `tipo_debito` VARCHAR(255) NULL,
            `raw_text` TEXT NULL,
            `raw_json` MEDIUMTEXT NULL,
            `imported_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mastercom_carenze` (`mastercom_id_classe`, `mastercom_id_studente`, `anno_label`, `materia`, `tipo_debito`),
            KEY `idx_mastercom_carenze_classe` (`mastercom_id_classe`),
            KEY `idx_mastercom_carenze_anno` (`id_anno_scolastico`),
            KEY `idx_mastercom_carenze_studente` (`id_studente_gestore`),
            KEY `idx_mastercom_carenze_materia` (`id_materia_gestore`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    mastercomDebtsEnsureCarenzeColumns();
}

function mastercomDebtsEnsureCarenzeColumns(): void
{
    $columns = [
        'mastercom_recuperato' => "ALTER TABLE `carenze` ADD COLUMN `mastercom_recuperato` TINYINT(1) NULL AFTER `stato`",
        'mastercom_tipo_debito' => "ALTER TABLE `carenze` ADD COLUMN `mastercom_tipo_debito` VARCHAR(255) NULL AFTER `mastercom_recuperato`",
        'mastercom_last_sync_at' => "ALTER TABLE `carenze` ADD COLUMN `mastercom_last_sync_at` DATETIME NULL AFTER `mastercom_tipo_debito`",
        'mastercom_raw_text' => "ALTER TABLE `carenze` ADD COLUMN `mastercom_raw_text` TEXT NULL AFTER `mastercom_last_sync_at`",
    ];

    foreach ($columns as $column => $query) {
        if (!mastercomAdminTableColumnExists('carenze', $column)) {
            dbExec($query);
        }
    }
}

function mastercomDebtsNormalizeSubject(string $value): string
{
    $value = mastercomAdminNorm($value);
    $value = str_replace([' E ', ' ED ', '&'], ' ', $value);
    $value = preg_replace('/[^A-Z0-9]+/u', '', $value);
    return trim((string)$value);
}

function mastercomDebtsNormalizeSubjectBase(string $value): string
{
    $value = mastercomAdminNorm($value);
    $value = preg_replace('/\([^)]*\)/u', ' ', $value);
    $value = str_replace([' E ', ' ED ', '&'], ' ', $value);
    $value = preg_replace('/[^A-Z0-9]+/u', '', $value);
    return trim((string)$value);
}

function mastercomDebtsSubjectHint(string $value): string
{
    if (preg_match('/\(([^)]*)\)/u', $value, $match)) {
        return mastercomDebtsNormalizeSubject((string)$match[1]);
    }

    return '';
}

function mastercomDebtsCanonicalSubject(string $value): string
{
    $value = mastercomAdminNorm($value);
    $value = str_replace(['/', '\\', '-', '_', '.', ':', ';', ',', '(', ')', '&'], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $words = preg_split('/\s+/u', trim((string)$value)) ?: [];

    $aliases = [
        'MATEM' => 'MATEMATICA',
        'MAT' => 'MATEMATICA',
        'CHIM' => 'CHIMICA',
        'SC' => 'SCIENZE',
        'SCIENZ' => 'SCIENZE',
        'ANALIT' => 'ANALITICA',
        'STRUM' => 'STRUMENTALE',
        'BIOL' => 'BIOLOGIA',
        'BIO' => 'BIOLOGIA',
        'BIOL.' => 'BIOLOGIA',
        'ORG' => 'ORGANICA',
        'ORGAN' => 'ORGANICA',
        'BIOCHIM' => 'BIOCHIMICA',
        'TEC' => 'TECNICHE',
        'TECN' => 'TECNICHE',
        'SIST' => 'SISTEMI',
        'INF' => 'INFORMATICI',
        'TEL' => 'TELECOMUNICAZIONI',
        'RAPPR' => 'RAPPRESENTAZIONE',
        'GRAF' => 'GRAFICA',
        'PROG' => 'PROGETTAZIONE',
        'COSTR' => 'COSTRUZIONI',
        'IMP' => 'IMPIANTI',
        'IND' => 'INDUSTRIALE',
        'ELETTROT' => 'ELETTROTECNICA',
        'MEC' => 'MECCANICA',
        'MAC' => 'MACCHINE',
        'FISIOL' => 'FISIOLOGIA',
        'PATOL' => 'PATOLOGIA',
        'GEOL' => 'GEOLOGIA',
    ];
    $skip = ['E', 'ED', 'DI', 'DELLA', 'DEL', 'DEI', 'DELLE', 'DEGLI'];
    $normalizedWords = [];
    foreach ($words as $word) {
        $word = trim((string)$word);
        if ($word === '' || in_array($word, $skip, true)) {
            continue;
        }
        $normalizedWords[] = $aliases[$word] ?? $word;
    }

    return preg_replace('/[^A-Z0-9]+/u', '', implode('', $normalizedWords));
}

function mastercomDebtsCanonicalSubjectTokens(string $value): array
{
    $value = mastercomAdminNorm($value);
    $value = str_replace(['/', '\\', '-', '_', '.', ':', ';', ',', '(', ')', '&'], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $words = preg_split('/\s+/u', trim((string)$value)) ?: [];

    $aliases = [
        'MATEM' => 'MATEMATICA',
        'MAT' => 'MATEMATICA',
        'CHIM' => 'CHIMICA',
        'SC' => 'SCIENZE',
        'SCIENZ' => 'SCIENZE',
        'ANALIT' => 'ANALITICA',
        'STRUM' => 'STRUMENTALE',
        'BIOL' => 'BIOLOGIA',
        'BIO' => 'BIOLOGIA',
        'ORG' => 'ORGANICA',
        'ORGAN' => 'ORGANICA',
        'BIOCHIM' => 'BIOCHIMICA',
        'TEC' => 'TECNICHE',
        'TECN' => 'TECNICHE',
        'SIST' => 'SISTEMI',
        'INF' => 'INFORMATICI',
        'TEL' => 'TELECOMUNICAZIONI',
        'RAPPR' => 'RAPPRESENTAZIONE',
        'GRAF' => 'GRAFICA',
        'PROG' => 'PROGETTAZIONE',
        'COSTR' => 'COSTRUZIONI',
        'IMP' => 'IMPIANTI',
        'IND' => 'INDUSTRIALE',
        'ELETTROT' => 'ELETTROTECNICA',
        'MEC' => 'MECCANICA',
        'MAC' => 'MACCHINE',
        'FISIOL' => 'FISIOLOGIA',
        'PATOL' => 'PATOLOGIA',
        'GEOL' => 'GEOLOGIA',
    ];
    $skip = ['E', 'ED', 'DI', 'DELLA', 'DEL', 'DEI', 'DELLE', 'DEGLI'];
    $tokens = [];
    foreach ($words as $word) {
        $word = trim((string)$word);
        if ($word === '' || in_array($word, $skip, true)) {
            continue;
        }
        $tokens[] = $aliases[$word] ?? $word;
    }

    return array_values(array_unique(array_filter($tokens)));
}

function mastercomDebtsInformativeSubjectTokens(string $value): array
{
    $tokens = mastercomDebtsCanonicalSubjectTokens($value);
    $specific = array_values(array_diff($tokens, ['SCIENZE', 'INTEGRATE', 'INTEGRATA', 'INTEGRATI']));

    return count($specific) >= 2 ? $specific : $tokens;
}

function mastercomDebtsResolveSubjectIdFromAlias(string $subjectName): ?int
{
    if (!mastercomAdminTableExists('orario_import_materia_alias')) {
        return null;
    }

    $target = mastercomDebtsNormalizeSubject($subjectName);
    $targetCanonical = mastercomDebtsCanonicalSubject($subjectName);
    $rows = dbGetAll("
        SELECT alias_materia, id_materia
        FROM orario_import_materia_alias
        WHERE id_materia IS NOT NULL
    ") ?: [];

    foreach ($rows as $row) {
        $alias = (string)($row['alias_materia'] ?? '');
        if (
            mastercomDebtsNormalizeSubject($alias) === $target
            || mastercomDebtsCanonicalSubject($alias) === $targetCanonical
        ) {
            return intval($row['id_materia']);
        }
    }

    return null;
}

function mastercomDebtsFindSubjectIdByNames(array $names): ?int
{
    $rows = dbGetAll("SELECT id, nome FROM materia") ?: [];
    foreach ($names as $name) {
        $name = mastercomAdminCleanText((string)$name) ?? '';
        if ($name === '') {
            continue;
        }

        $exact = dbGetValue("SELECT id FROM materia WHERE nome = " . dbQ($name) . " LIMIT 1");
        if ($exact !== null) {
            return intval($exact);
        }

        $targetCanonical = mastercomDebtsCanonicalSubject($name);
        $matches = [];
        foreach ($rows as $row) {
            if (mastercomDebtsCanonicalSubject((string)($row['nome'] ?? '')) === $targetCanonical) {
                $matches[] = intval($row['id']);
            }
        }
        if (count(array_unique($matches)) === 1) {
            return $matches[0];
        }
    }

    return null;
}

function mastercomDebtsResolveSubjectIdByClassRule(string $subjectName, string $className): ?int
{
    $subjectKey = mastercomDebtsCanonicalSubject($subjectName);
    if ($subjectKey !== 'AREAAUTONOMIA') {
        return null;
    }

    $classKey = strtoupper(preg_replace('/[^A-Z0-9]+/u', '', mastercomAdminNorm($className)));
    if (preg_match('/^[345]CMA/u', $classKey)) {
        return mastercomDebtsFindSubjectIdByNames([
            'Chimica fisica dei materiali innovativi',
        ]);
    }

    if (preg_match('/^[345]CT[A-Z]?/u', $classKey)) {
        return mastercomDebtsFindSubjectIdByNames([
            'Tecnica Professionale',
            'Tecniche professionali',
        ]);
    }

    return null;
}

function mastercomDebtsResolveSubjectId(string $subjectName, string $className = ''): ?int
{
    $subjectName = mastercomAdminCleanText($subjectName) ?? '';
    if ($subjectName === '') {
        return null;
    }

    if ($className !== '') {
        $classSubjectId = mastercomDebtsResolveSubjectIdByClassRule($subjectName, $className);
        if ($classSubjectId !== null && $classSubjectId > 0) {
            return $classSubjectId;
        }
    }

    $exact = dbGetValue("SELECT id FROM materia WHERE nome = " . dbQ($subjectName) . " LIMIT 1");
    if ($exact !== null) {
        return intval($exact);
    }

    $aliasId = mastercomDebtsResolveSubjectIdFromAlias($subjectName);
    if ($aliasId !== null && $aliasId > 0) {
        return $aliasId;
    }

    $target = mastercomDebtsNormalizeSubject($subjectName);
    $targetBase = mastercomDebtsNormalizeSubjectBase($subjectName);
    $targetHint = mastercomDebtsSubjectHint($subjectName);
    $targetCanonical = mastercomDebtsCanonicalSubject($subjectName);
    $targetTokens = mastercomDebtsInformativeSubjectTokens($subjectName);
    if ($target === '') {
        return null;
    }

    $rows = dbGetAll("SELECT id, nome FROM materia") ?: [];
    foreach ($rows as $row) {
        if (mastercomDebtsNormalizeSubject((string)($row['nome'] ?? '')) === $target) {
            return intval($row['id']);
        }
    }

    $canonicalMatches = [];
    foreach ($rows as $row) {
        if (mastercomDebtsCanonicalSubject((string)($row['nome'] ?? '')) === $targetCanonical) {
            $canonicalMatches[] = intval($row['id']);
        }
    }
    if (count($canonicalMatches) === 1) {
        return $canonicalMatches[0];
    }

    $canonicalContainsMatches = [];
    if (strlen($targetCanonical) >= 8) {
        foreach ($rows as $row) {
            $candidateCanonical = mastercomDebtsCanonicalSubject((string)($row['nome'] ?? ''));
            if (
                strlen($candidateCanonical) >= 8
                && (strpos($candidateCanonical, $targetCanonical) !== false || strpos($targetCanonical, $candidateCanonical) !== false)
            ) {
                $canonicalContainsMatches[] = intval($row['id']);
            }
        }
    }
    if (count(array_unique($canonicalContainsMatches)) === 1) {
        return $canonicalContainsMatches[0];
    }

    if (count($targetTokens) >= 2) {
        $tokenMatches = [];
        foreach ($rows as $row) {
            $candidateTokens = mastercomDebtsCanonicalSubjectTokens((string)($row['nome'] ?? ''));
            if (empty(array_diff($targetTokens, $candidateTokens))) {
                $tokenMatches[] = intval($row['id']);
            }
        }
        if (count(array_unique($tokenMatches)) === 1) {
            return $tokenMatches[0];
        }
    }

    if ($targetHint !== '') {
        $hintMatches = [];
        foreach ($rows as $row) {
            $candidate = mastercomDebtsNormalizeSubject((string)($row['nome'] ?? ''));
            if (strpos($candidate, $targetHint) !== false) {
                $candidateBase = mastercomDebtsNormalizeSubjectBase((string)($row['nome'] ?? ''));
                if ($targetBase === '' || $candidateBase === $targetBase || strpos($candidateBase, $targetBase) !== false || strpos($targetBase, $candidateBase) !== false) {
                    $hintMatches[] = intval($row['id']);
                }
            }
        }
        if (count($hintMatches) === 1) {
            return $hintMatches[0];
        }
    }

    $baseMatches = [];
    foreach ($rows as $row) {
        $candidateBase = mastercomDebtsNormalizeSubjectBase((string)($row['nome'] ?? ''));
        if ($candidateBase !== '' && $targetBase !== '' && (strpos($candidateBase, $targetBase) !== false || strpos($targetBase, $candidateBase) !== false)) {
            $baseMatches[] = intval($row['id']);
        }
    }
    if (count($baseMatches) === 1) {
        return $baseMatches[0];
    }

    return null;
}

function mastercomDebtsResolveSchoolYearId(string $yearLabel): ?int
{
    $yearLabel = trim(str_replace(' ', '', $yearLabel));
    if ($yearLabel === '') {
        return null;
    }

    $value = dbGetValue("SELECT id FROM anno_scolastico WHERE anno = " . dbQ($yearLabel) . " LIMIT 1");
    return $value !== null ? intval($value) : null;
}

function mastercomDebtsClassNameById(int $mastercomClassId): string
{
    if ($mastercomClassId <= 0 || !mastercomAdminTableExists('mastercom_classi')) {
        return '';
    }

    return trim((string)(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . dbI($mastercomClassId) . " LIMIT 1") ?? ''));
}

function mastercomDebtsLocalClassIdByMastercom(int $mastercomClassId, string $className): ?int
{
    if ($mastercomClassId > 0 && mastercomAdminTableExists('mastercom_classi')) {
        $value = dbGetValue("SELECT id_classe_gestore FROM mastercom_classi WHERE mastercom_id_classe = " . dbI($mastercomClassId) . " LIMIT 1");
        if ($value !== null && intval($value) > 0) {
            return intval($value);
        }
    }

    return mastercomAdminFindLocalClassIdByName($className);
}

function mastercomDebtsLocalStudentByMastercom(int $mastercomStudentId, string $studentName, ?int $localClassId): ?array
{
    if ($mastercomStudentId > 0 && mastercomAdminTableExists('mastercom_studenti')) {
        $row = dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . dbI($mastercomStudentId) . " LIMIT 1");
        if (is_array($row)) {
            $resolved = mastercomAdminResolveLocalStudent($row);
            if ($resolved != null) {
                return $resolved;
            }
        }
    }

    $name = mastercomAdminCleanText($studentName) ?? '';
    if ($name === '') {
        return null;
    }

    $whereClass = $localClassId !== null && $localClassId > 0 ? " AND sf.id_classe = " . dbI($localClassId) : '';
    return dbGetFirst("
        SELECT s.*, sf.id_classe AS id_classe_corrente
        FROM studente s
        LEFT JOIN studente_frequenta sf ON sf.id = (
            SELECT sf2.id
            FROM studente_frequenta sf2
            WHERE sf2.id_studente = s.id
            ORDER BY sf2.id_anno_scolastico DESC, sf2.id DESC
            LIMIT 1
        )
        WHERE UPPER(CONCAT(TRIM(s.cognome), ' ', TRIM(s.nome))) = " . dbQ(mastercomAdminNorm($name)) . "
        $whereClass
        ORDER BY s.attivo DESC, s.id DESC
        LIMIT 1
    ");
}

function mastercomDebtsClassIdForStudentYear(int $studentId, ?int $schoolYearId, ?int $fallbackClassId): ?int
{
    if ($studentId > 0 && $schoolYearId !== null && $schoolYearId > 0) {
        $value = dbGetValue("
            SELECT id_classe
            FROM studente_frequenta
            WHERE id_studente = " . dbI($studentId) . "
              AND id_anno_scolastico = " . dbI($schoolYearId) . "
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($value !== null && intval($value) > 0) {
            return intval($value);
        }
    }

    return $fallbackClassId !== null && $fallbackClassId > 0 ? $fallbackClassId : null;
}

function mastercomDebtsFetchClassHtml(int $mastercomClassId, string $className = ''): array
{
    if ($mastercomClassId <= 0) {
        return ['ok' => false, 'message' => 'Classe MasterCom non valida.', 'html' => ''];
    }

    $auth = mastercomAuthenticateService([
        'profile' => 'MasterComAuth',
        'method' => 'POST',
        'timeout' => 120,
    ]);
    if (empty($auth['ok'])) {
        return ['ok' => false, 'message' => 'Autenticazione MasterCom fallita: ' . ($auth['error'] ?? ''), 'html' => ''];
    }

    $currentUser = mastercomCurrentUser($auth);
    $currentKey = mastercomCurrentKey($auth);
    if ($currentUser === null || trim((string)$currentKey) === '') {
        return ['ok' => false, 'message' => 'Autenticazione MasterCom incompleta.', 'html' => ''];
    }

    $className = $className !== '' ? $className : mastercomDebtsClassNameById($mastercomClassId);
    $response = mastercomRawRequest([
        'form_stato' => 'amministratore',
        'stato_principale' => 'pagelle_principale',
        'stato_secondario' => 'gestione_debiti',
        'indirizzo' => '',
        'id_indirizzo' => '',
        'classe' => $className,
        'id_classe' => $mastercomClassId,
        'current_user' => $currentUser,
        'current_key' => $currentKey,
    ], [
        'base_url' => mastercomIndexUrl(),
        'method' => 'POST',
        'send_in_body' => true,
        'timeout' => 300,
        'cookie' => implode('; ', array_filter($auth['cookies'] ?? [])),
    ]);

    if (empty($response['ok'])) {
        return ['ok' => false, 'message' => 'Lettura debiti MasterCom fallita: ' . ($response['error'] ?? ''), 'html' => ''];
    }

    return ['ok' => true, 'message' => 'HTML MasterCom letto.', 'html' => (string)($response['body'] ?? '')];
}

function mastercomDebtsParseHtml(string $html, int $mastercomClassId, string $className = ''): array
{
    $rows = [];
    if (trim($html) === '') {
        return $rows;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    if (!$loaded) {
        return $rows;
    }

    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//tr') as $tr) {
        $cells = $xpath->query('./td', $tr);
        if ($cells->length < 4) {
            continue;
        }

        $numberText = trim(preg_replace('/\s+/', ' ', $cells->item(0)->textContent));
        if (!preg_match('/^\d+$/', $numberText)) {
            continue;
        }

        $hidden = $xpath->query('.//input[@name="id_studente"]', $tr);
        if ($hidden->length === 0) {
            continue;
        }

        $studentId = intval($hidden->item(0)->getAttribute('value'));
        $studentName = mastercomAdminCleanText($cells->item(1)->textContent) ?? '';
        $debtText = html_entity_decode($cells->item(2)->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $debtText = str_replace("\xc2\xa0", ' ', $debtText);
        $debtText = preg_replace('/\s+/u', ' ', $debtText);

        preg_match_all('/A\.S\.\s*(\d{4}\s*\/\s*\d{4})\s*--\s*(.*?)\s*Debito\s+(non\s+recuperato|recuperato)\s*--\s*\(Tipo\s+debito\s*:\s*([^)]+)\)/iu', $debtText, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $yearLabel = str_replace(' ', '', (string)$match[1]);
            $subject = mastercomAdminCleanText((string)$match[2]) ?? '';
            $status = mastercomAdminNorm((string)$match[3]);
            $type = mastercomAdminCleanText((string)$match[4]) ?? '';
            if ($yearLabel === '' || $subject === '') {
                continue;
            }

            $rows[] = [
                'mastercom_id_classe' => $mastercomClassId,
                'classe' => $className,
                'mastercom_id_studente' => $studentId,
                'studente_nome' => $studentName,
                'anno_label' => $yearLabel,
                'materia' => $subject,
                'recuperato_mastercom' => $status === 'RECUPERATO' ? 1 : 0,
                'tipo_debito' => $type,
                'raw_text' => mastercomAdminCleanText((string)$match[0]) ?? '',
            ];
        }
    }

    return $rows;
}

function mastercomDebtsUpsertRows(array $rows, int $mastercomClassId, string $className = ''): array
{
    mastercomDebtsEnsureTables();

    $localClassId = mastercomDebtsLocalClassIdByMastercom($mastercomClassId, $className);
    $stats = [
        'parsed' => count($rows),
        'saved' => 0,
        'without_student' => 0,
        'without_subject' => 0,
        'without_year' => 0,
    ];

    foreach ($rows as $row) {
        $localStudent = mastercomDebtsLocalStudentByMastercom(intval($row['mastercom_id_studente'] ?? 0), (string)($row['studente_nome'] ?? ''), $localClassId);
        $localStudentId = $localStudent != null ? intval($localStudent['id']) : null;
        $yearId = mastercomDebtsResolveSchoolYearId((string)($row['anno_label'] ?? ''));
        $rowClassId = $localStudentId !== null ? mastercomDebtsClassIdForStudentYear($localStudentId, $yearId, $localClassId) : $localClassId;
        $localSubjectId = mastercomDebtsResolveSubjectId((string)($row['materia'] ?? ''), $className);

        if ($localStudentId === null) {
            $stats['without_student']++;
        }
        if ($localSubjectId === null) {
            $stats['without_subject']++;
        }
        if ($yearId === null) {
            $stats['without_year']++;
        }

        dbExec("
            INSERT INTO mastercom_carenze (
                mastercom_id_classe, id_classe_gestore, classe,
                mastercom_id_studente, id_studente_gestore, studente_nome,
                anno_label, id_anno_scolastico, materia, id_materia_gestore,
                recuperato_mastercom, tipo_debito, raw_text, raw_json, imported_at
            ) VALUES (
                " . dbI($mastercomClassId) . ",
                " . dbI($rowClassId) . ",
                " . dbQ($className) . ",
                " . dbI($row['mastercom_id_studente'] ?? null) . ",
                " . dbI($localStudentId) . ",
                " . dbQ($row['studente_nome'] ?? '') . ",
                " . dbQ($row['anno_label'] ?? '') . ",
                " . dbI($yearId) . ",
                " . dbQ($row['materia'] ?? '') . ",
                " . dbI($localSubjectId) . ",
                " . dbI($row['recuperato_mastercom'] ?? 0) . ",
                " . dbQ($row['tipo_debito'] ?? '') . ",
                " . dbQ($row['raw_text'] ?? '') . ",
                " . dbQ(mastercomAdminJson($row)) . ",
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                id_classe_gestore = VALUES(id_classe_gestore),
                classe = VALUES(classe),
                id_studente_gestore = VALUES(id_studente_gestore),
                studente_nome = VALUES(studente_nome),
                id_anno_scolastico = VALUES(id_anno_scolastico),
                id_materia_gestore = VALUES(id_materia_gestore),
                recuperato_mastercom = VALUES(recuperato_mastercom),
                tipo_debito = VALUES(tipo_debito),
                raw_text = VALUES(raw_text),
                raw_json = VALUES(raw_json),
                imported_at = NOW()
        ");
        $stats['saved']++;
    }

    return $stats;
}

function mastercomDebtsFetchAndStoreClass(int $mastercomClassId): array
{
    $className = mastercomDebtsClassNameById($mastercomClassId);
    $fetch = mastercomDebtsFetchClassHtml($mastercomClassId, $className);
    if (empty($fetch['ok'])) {
        return ['ok' => false, 'message' => $fetch['message'] ?? 'Lettura MasterCom non riuscita.', 'stats' => []];
    }

    $rows = mastercomDebtsParseHtml((string)$fetch['html'], $mastercomClassId, $className);
    $stats = mastercomDebtsUpsertRows($rows, $mastercomClassId, $className);

    return [
        'ok' => true,
        'message' => 'Lette e salvate ' . intval($stats['saved']) . ' carenze da MasterCom.',
        'stats' => $stats,
    ];
}

function mastercomDebtsFetchAndStoreAllClasses(): array
{
    $classRows = mastercomAdminOperationalClassRows('mastercom_id_classe, nome');
    $stats = [
        'classes' => 0,
        'saved' => 0,
        'without_student' => 0,
        'without_subject' => 0,
        'without_year' => 0,
        'errors' => 0,
    ];
    $messages = [];

    foreach ($classRows as $classRow) {
        $classId = intval($classRow['mastercom_id_classe'] ?? 0);
        if ($classId <= 0) {
            continue;
        }

        $result = mastercomDebtsFetchAndStoreClass($classId);
        if (empty($result['ok'])) {
            $stats['errors']++;
            $messages[] = trim((string)($classRow['nome'] ?? ('classe ' . $classId))) . ': ' . ($result['message'] ?? 'errore lettura');
            continue;
        }

        $classStats = $result['stats'] ?? [];
        $stats['classes']++;
        $stats['saved'] += intval($classStats['saved'] ?? 0);
        $stats['without_student'] += intval($classStats['without_student'] ?? 0);
        $stats['without_subject'] += intval($classStats['without_subject'] ?? 0);
        $stats['without_year'] += intval($classStats['without_year'] ?? 0);
    }

    return [
        'ok' => $stats['classes'] > 0,
        'message' => 'Lettura globale completata: classi ' . intval($stats['classes'])
            . ', carenze salvate ' . intval($stats['saved'])
            . ', errori classi ' . intval($stats['errors']) . '.',
        'stats' => $stats,
        'errors' => $messages,
    ];
}

function mastercomDebtsRefreshMissingSubjectMatches(): int
{
    mastercomDebtsEnsureTables();

    $rows = dbGetAll("
        SELECT id, materia, classe
        FROM mastercom_carenze
        WHERE id_materia_gestore IS NULL OR id_materia_gestore <= 0
    ") ?: [];

    $updated = 0;
    foreach ($rows as $row) {
        $subjectId = mastercomDebtsResolveSubjectId((string)($row['materia'] ?? ''), (string)($row['classe'] ?? ''));
        if ($subjectId === null || $subjectId <= 0) {
            continue;
        }

        dbExec("
            UPDATE mastercom_carenze
            SET id_materia_gestore = " . dbI($subjectId) . "
            WHERE id = " . dbI($row['id'] ?? 0) . "
        ");
        $updated++;
    }

    return $updated;
}

function mastercomDebtsRefreshCachedClassMatches(): int
{
    mastercomDebtsEnsureTables();

    $rows = dbGetAll("
        SELECT id, id_studente_gestore, id_anno_scolastico, id_classe_gestore
        FROM mastercom_carenze
        WHERE id_studente_gestore IS NOT NULL
          AND id_studente_gestore > 0
          AND id_anno_scolastico IS NOT NULL
          AND id_anno_scolastico > 0
    ") ?: [];

    $updated = 0;
    foreach ($rows as $row) {
        $classId = mastercomDebtsClassIdForStudentYear(
            intval($row['id_studente_gestore'] ?? 0),
            intval($row['id_anno_scolastico'] ?? 0),
            intval($row['id_classe_gestore'] ?? 0)
        );
        if ($classId === null || $classId <= 0 || intval($row['id_classe_gestore'] ?? 0) === $classId) {
            continue;
        }

        dbExec("
            UPDATE mastercom_carenze
            SET id_classe_gestore = " . dbI($classId) . "
            WHERE id = " . dbI($row['id'] ?? 0) . "
        ");
        $updated++;
    }

    return $updated;
}

function mastercomDebtsSaveToGestoreCarenze(int $schoolYearId, int $mastercomClassId = 0): array
{
    mastercomDebtsEnsureTables();
    mastercomDebtsRefreshMissingSubjectMatches();
    mastercomDebtsRefreshCachedClassMatches();

    $where = ["id_anno_scolastico = " . dbI($schoolYearId)];
    if ($mastercomClassId > 0) {
        $where[] = "mastercom_id_classe = " . dbI($mastercomClassId);
    }

    $rows = dbGetAll("
        SELECT *
        FROM mastercom_carenze
        WHERE " . implode(' AND ', $where) . "
        ORDER BY studente_nome ASC, materia ASC
    ") ?: [];

    $stats = [
        'source' => count($rows),
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
    ];

    foreach ($rows as $row) {
        $studentId = intval($row['id_studente_gestore'] ?? 0);
        $subjectId = intval($row['id_materia_gestore'] ?? 0);
        $classId = intval($row['id_classe_gestore'] ?? 0);
        if ($studentId <= 0 || $subjectId <= 0 || $classId <= 0) {
            $stats['skipped']++;
            continue;
        }

        $existingId = dbGetValue("
            SELECT id
            FROM carenze
            WHERE id_studente = " . dbI($studentId) . "
              AND id_materia = " . dbI($subjectId) . "
              AND id_classe = " . dbI($classId) . "
              AND id_anno_scolastico = " . dbI($schoolYearId) . "
            LIMIT 1
        ");

        if ($existingId !== null) {
            dbExec("
                UPDATE carenze
                SET mastercom_recuperato = " . dbI($row['recuperato_mastercom'] ?? 0) . ",
                    mastercom_tipo_debito = " . dbQ($row['tipo_debito'] ?? '') . ",
                    mastercom_last_sync_at = NOW(),
                    mastercom_raw_text = " . dbQ($row['raw_text'] ?? '') . "
                WHERE id = " . dbI($existingId) . "
            ");
            $stats['updated']++;
            continue;
        }

        dbExec("
            INSERT INTO carenze (
                id_studente, id_materia, id_classe, id_docente, id_anno_scolastico,
                stato, mastercom_recuperato, mastercom_tipo_debito, mastercom_last_sync_at,
                mastercom_raw_text, data_inserimento, data_validazione, data_invio
            ) VALUES (
                " . dbI($studentId) . ",
                " . dbI($subjectId) . ",
                " . dbI($classId) . ",
                0,
                " . dbI($schoolYearId) . ",
                0,
                " . dbI($row['recuperato_mastercom'] ?? 0) . ",
                " . dbQ($row['tipo_debito'] ?? '') . ",
                NOW(),
                " . dbQ($row['raw_text'] ?? '') . ",
                NOW(),
                '',
                ''
            )
        ");
        $stats['inserted']++;
    }

    return $stats;
}

function mastercomDebtsEquivalentCourseSubjectIds(int $subjectId): array
{
    if ($subjectId <= 0) {
        return [];
    }

    $subjectName = trim((string)(dbGetValue("SELECT nome FROM materia WHERE id = " . dbI($subjectId) . " LIMIT 1") ?? ''));
    if ($subjectName === '') {
        return [$subjectId];
    }

    $targetCanonical = mastercomDebtsCanonicalSubject($subjectName);
    $targetTokens = mastercomDebtsInformativeSubjectTokens($subjectName);
    $ids = [$subjectId];
    $rows = dbGetAll("SELECT id, nome FROM materia") ?: [];

    foreach ($rows as $row) {
        $candidateId = intval($row['id'] ?? 0);
        if ($candidateId <= 0 || $candidateId === $subjectId) {
            continue;
        }

        $candidateName = (string)($row['nome'] ?? '');
        $candidateCanonical = mastercomDebtsCanonicalSubject($candidateName);
        $candidateTokens = mastercomDebtsCanonicalSubjectTokens($candidateName);
        if (
            $targetCanonical !== ''
            && strlen($targetCanonical) >= 8
            && (strpos($candidateCanonical, $targetCanonical) !== false || strpos($targetCanonical, $candidateCanonical) !== false)
        ) {
            $ids[] = $candidateId;
            continue;
        }

        if (count($targetTokens) >= 2 && empty(array_diff($targetTokens, $candidateTokens))) {
            $ids[] = $candidateId;
        }
    }

    return array_values(array_unique($ids));
}

function mastercomDebtsRecoveryAttemptLabel(int $attempt): string
{
    if ($attempt === 2) {
        return 'secondo appello';
    }
    if ($attempt === 1) {
        return 'primo appello';
    }

    return 'appello non indicato';
}

function mastercomDebtsCourseRecoveryStatus(int $studentId, int $subjectId, int $schoolYearId): array
{
    if ($studentId <= 0 || $subjectId <= 0 || $schoolYearId <= 0) {
        return ['has_esito' => false, 'recuperato' => null, 'label' => 'Non confrontabile'];
    }

    global $__anno_scolastico_corrente_id;

    $courseYearId = intval($__anno_scolastico_corrente_id ?? $schoolYearId);
    $subjectIds = mastercomDebtsEquivalentCourseSubjectIds($subjectId);
    $subjectIdsSql = implode(',', array_map('dbI', $subjectIds));
    if ($subjectIdsSql === '') {
        return ['has_esito' => false, 'recuperato' => null, 'label' => 'Non confrontabile'];
    }

    $rows = dbGetAll("
        SELECT
            ce.id AS esito_id,
            COALESCE(ce.recuperato, 0) AS recuperato,
            GREATEST(COALESCE(NULLIF(co.carenza_sessione, 0), 1), COALESCE(NULLIF(ced.tentativo, 0), 1)) AS appello,
            ced.tentativo AS tentativo_esame,
            ced.data_inizio_esame,
            co.titolo,
            m.nome AS materia_corso
        FROM corso co
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id AND ci.id_studente = " . dbI($studentId) . "
        INNER JOIN corso_esiti ce ON ce.id_corso = co.id AND ce.id_studente = ci.id_studente
        LEFT JOIN corso_esami_date ced ON ced.id = ce.id_esame_data
        LEFT JOIN materia m ON m.id = co.id_materia
        WHERE co.carenza = 1
          AND co.id_materia IN ($subjectIdsSql)
          AND co.id_anno_scolastico IN (" . dbI($schoolYearId) . ", " . dbI($courseYearId) . ")
        ORDER BY COALESCE(ce.recuperato, 0) DESC,
                 GREATEST(COALESCE(NULLIF(co.carenza_sessione, 0), 1), COALESCE(NULLIF(ced.tentativo, 0), 1)) DESC,
                 ced.data_inizio_esame DESC,
                 ce.id DESC
    ") ?: [];

    if (empty($rows)) {
        return ['has_esito' => false, 'recuperato' => null, 'label' => 'Nessun esito corso'];
    }

    $row = $rows[0];
    $recovered = intval($row['recuperato'] ?? 0) === 1;
    $attempt = intval($row['appello'] ?? 0);
    $attemptLabel = mastercomDebtsRecoveryAttemptLabel($attempt);
    $debtSubjectName = trim((string)(dbGetValue("SELECT nome FROM materia WHERE id = " . dbI($subjectId) . " LIMIT 1") ?? ''));
    $courseLabels = [];
    foreach ($rows as $courseRow) {
        $courseLabel = trim((string)($courseRow['titolo'] ?? ''));
        $courseSubject = trim((string)($courseRow['materia_corso'] ?? ''));
        if ($courseSubject !== '' && $courseSubject !== $debtSubjectName) {
            $courseLabel .= ($courseLabel !== '' ? ' - ' : '') . $courseSubject;
        }
        if ($courseLabel !== '') {
            $courseLabels[] = $courseLabel;
        }
    }

    return [
        'has_esito' => true,
        'recuperato' => $recovered ? 1 : 0,
        'appello' => $attempt,
        'label' => ($recovered ? 'Recuperato' : 'Non recuperato') . ' al ' . $attemptLabel,
        'corsi' => implode(' | ', array_values(array_unique($courseLabels))),
    ];
}

function mastercomDebtsReportRows(int $schoolYearId, int $mastercomClassId = 0): array
{
    mastercomDebtsEnsureTables();

    $where = [];
    if ($schoolYearId > 0) {
        $where[] = "mc.id_anno_scolastico = " . dbI($schoolYearId);
    }
    if ($mastercomClassId > 0) {
        $where[] = "mc.mastercom_id_classe = " . dbI($mastercomClassId);
    }
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $rows = dbGetAll("
        SELECT
            mc.*,
            c.id AS carenza_id,
            c.stato AS carenza_stato,
            c.data_validazione,
            c.data_invio,
            m.nome AS materia_gestore,
            cl.classe AS classe_gestore,
            CONCAT(COALESCE(s.cognome, ''), ' ', COALESCE(s.nome, '')) AS studente_gestore
        FROM mastercom_carenze mc
        LEFT JOIN carenze c
            ON c.id_studente = mc.id_studente_gestore
           AND c.id_materia = mc.id_materia_gestore
           AND c.id_classe = mc.id_classe_gestore
           AND c.id_anno_scolastico = mc.id_anno_scolastico
        LEFT JOIN materia m ON m.id = mc.id_materia_gestore
        LEFT JOIN classi cl ON cl.id = mc.id_classe_gestore
        LEFT JOIN studente s ON s.id = mc.id_studente_gestore
        $whereSql
        ORDER BY mc.anno_label DESC, mc.classe ASC, mc.studente_nome ASC, mc.materia ASC
    ") ?: [];

    foreach ($rows as &$row) {
        $course = mastercomDebtsCourseRecoveryStatus(
            intval($row['id_studente_gestore'] ?? 0),
            intval($row['id_materia_gestore'] ?? 0),
            intval($row['id_anno_scolastico'] ?? 0)
        );
        $row['corso_label'] = $course['label'];
        $row['corso_recuperato'] = $course['recuperato'];
        $row['corso_appello'] = $course['appello'] ?? null;
        $row['corso_corsi'] = $course['corsi'] ?? '';

        if (intval($row['id_studente_gestore'] ?? 0) <= 0 || intval($row['id_materia_gestore'] ?? 0) <= 0) {
            $row['confronto'] = 'Da abbinare';
        } elseif ($course['recuperato'] === null) {
            $row['confronto'] = 'Senza esito corso';
        } elseif (intval($row['recuperato_mastercom'] ?? 0) === intval($course['recuperato'])) {
            $row['confronto'] = 'OK';
        } else {
            $row['confronto'] = 'Da verificare';
        }
    }
    unset($row);

    return $rows;
}

function mastercomDebtsSchoolYears(): array
{
    return dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY anno DESC") ?: [];
}

function mastercomDebtsExportFileName(string $base, string $extension): string
{
    $timestamp = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d_H-i');
    return preg_replace('/[^A-Za-z0-9_\\-]+/', '_', $base) . '_' . $timestamp . '.' . $extension;
}

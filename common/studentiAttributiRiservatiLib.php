<?php

/**
 * Attributi riservati studente.
 *
 * I codici attributo sono volutamente opachi: il significato resta nel codice applicativo,
 * non nel nome delle colonne o nei valori salvati nel database.
 *
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/connect.php';

const STUD_ATTR_R7A2 = 'R7A2';
const STUD_ATTR_Q4M9 = 'Q4M9';
const STUD_ATTR_Z8C3 = 'Z8C3';

function studentiAttrEnsureTables(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS `studente_attributi_riservati` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_studente` INT NOT NULL,
            `codice_attributo` VARCHAR(16) NOT NULL,
            `attivo` TINYINT(1) NOT NULL DEFAULT 1,
            `fonte` VARCHAR(30) NOT NULL DEFAULT 'manuale',
            `source_ref` VARCHAR(120) NULL,
            `source_hash` CHAR(64) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_studente_attr_riservato` (`id_studente`, `codice_attributo`),
            KEY `idx_studente_attr_codice` (`codice_attributo`),
            KEY `idx_studente_attr_attivo` (`attivo`),
            KEY `idx_studente_attr_fonte` (`fonte`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function studentiAttrMap(): array
{
    return [
        STUD_ATTR_R7A2 => [
            'label' => 'DSA',
            'patterns' => ['/\\bDSA\\b/u'],
        ],
        STUD_ATTR_Q4M9 => [
            'label' => '104',
            'patterns' => ['/\\b104\\b/u', '/\\bL\\.?\\s*104\\b/u', '/LEGGE\\s*104/u'],
        ],
        STUD_ATTR_Z8C3 => [
            'label' => 'Fascia C',
            'patterns' => ['/\\bFASCIA\\s*C\\b/u'],
        ],
    ];
}

function studentiAttrParseNote(string $note): array
{
    $note = strtoupper(trim($note));
    $result = [];
    foreach (studentiAttrMap() as $code => $config) {
        $found = false;
        foreach ((array)($config['patterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $note)) {
                $found = true;
                break;
            }
        }
        $result[$code] = $found;
    }
    return $result;
}

function studentiAttrLabelsForCodes(array $codes): array
{
    $map = studentiAttrMap();
    $result = [];
    foreach ($codes as $code) {
        $code = (string)$code;
        if ($code === '' || !isset($map[$code])) {
            continue;
        }
        $result[$code] = [
            'codice' => $code,
            'label' => (string)($map[$code]['label'] ?? $code),
        ];
    }
    return array_values($result);
}

function studentiAttrUpsert(int $studentId, string $code, bool $active, string $source, string $sourceRef = '', string $sourceHash = ''): void
{
    if ($studentId <= 0 || $code === '') {
        return;
    }

    dbExec("
        INSERT INTO studente_attributi_riservati (
            id_studente, codice_attributo, attivo, fonte, source_ref, source_hash, created_at, updated_at
        ) VALUES (
            " . dbI($studentId) . ",
            " . dbQ($code) . ",
            " . dbI($active ? 1 : 0) . ",
            " . dbQ($source) . ",
            " . dbQ($sourceRef) . ",
            " . dbQ($sourceHash) . ",
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            attivo = VALUES(attivo),
            fonte = VALUES(fonte),
            source_ref = VALUES(source_ref),
            source_hash = VALUES(source_hash),
            updated_at = NOW()
    ");
}

function studentiAttrFindStudentByMbappId(string $mbappId): ?array
{
    $mbappId = trim($mbappId);
    if ($mbappId === '') {
        return null;
    }

    $row = dbGetFirst("
        SELECT id, cognome, nome, username, email, codice_fiscale
        FROM studente
        WHERE username = " . dbQ($mbappId) . "
           OR email = " . dbQ($mbappId . '@buonarroti.tn.it') . "
           OR SUBSTRING_INDEX(email, '@', 1) = " . dbQ($mbappId) . "
        ORDER BY attivo DESC, id DESC
        LIMIT 1
    ");

    return $row ?: null;
}

function studentiAttrFindStudentByFiscalCode(string $fiscalCode): ?array
{
    $fiscalCode = strtoupper(trim($fiscalCode));
    if ($fiscalCode === '') {
        return null;
    }

    $row = dbGetFirst("
        SELECT id, cognome, nome, username, email, codice_fiscale
        FROM studente
        WHERE UPPER(TRIM(codice_fiscale)) = " . dbQ($fiscalCode) . "
        ORDER BY attivo DESC, id DESC
        LIMIT 1
    ");

    return $row ?: null;
}

function studentiAttrSyncFromMbapp(): array
{
    studentiAttrEnsureTables();
    if (file_exists(__DIR__ . '/connectmbapp.php')) {
        require_once __DIR__ . '/connectmbapp.php';
    } else {
        require_once __DIR__ . '/connectMBApp.php';
    }

    $rows = mb_dbGetAll("
        SELECT idStudente, note
        FROM studente
        WHERE idStudente IS NOT NULL
          AND idStudente <> ''
    ") ?: [];

    $stats = [
        'mbapp_rows' => count($rows),
        'matched_students' => 0,
        'unmatched_students' => 0,
        'updated_attributes' => 0,
        'active_by_code' => array_fill_keys(array_keys(studentiAttrMap()), 0),
        'unmatched_examples' => [],
    ];

    foreach ($rows as $row) {
        $mbappId = trim((string)($row['idStudente'] ?? ''));
        if ($mbappId === '') {
            continue;
        }

        $student = studentiAttrFindStudentByMbappId($mbappId);
        if (!$student) {
            $stats['unmatched_students']++;
            if (count($stats['unmatched_examples']) < 20) {
                $stats['unmatched_examples'][] = $mbappId;
            }
            continue;
        }

        $stats['matched_students']++;
        $note = (string)($row['note'] ?? '');
        $sourceHash = hash('sha256', $note);
        $parsed = studentiAttrParseNote($note);
        foreach ($parsed as $code => $active) {
            studentiAttrUpsert((int)$student['id'], (string)$code, (bool)$active, 'mbapp', $mbappId, $sourceHash);
            $stats['updated_attributes']++;
            if ($active) {
                $stats['active_by_code'][$code]++;
            }
        }
    }

    return $stats;
}

function studentiAttrActiveForStudent(int $studentId): array
{
    studentiAttrEnsureTables();
    $rows = dbGetAll("
        SELECT codice_attributo
        FROM studente_attributi_riservati
        WHERE id_studente = " . dbI($studentId) . "
          AND attivo = 1
    ") ?: [];
    return array_map(static function ($row) {
        return (string)($row['codice_attributo'] ?? '');
    }, $rows);
}

function studentiAttrActiveForStudentWithSource(int $studentId): array
{
    studentiAttrEnsureTables();
    if ($studentId <= 0) {
        return [];
    }
    $rows = dbGetAll("
        SELECT codice_attributo, fonte
        FROM studente_attributi_riservati
        WHERE id_studente = " . dbI($studentId) . "
          AND attivo = 1
        ORDER BY codice_attributo ASC
    ") ?: [];

    return studentiAttrRowsToDisplay($rows);
}

function studentiAttrActiveForFiscalCode(string $fiscalCode): array
{
    $student = studentiAttrFindStudentByFiscalCode($fiscalCode);
    if (!$student) {
        return [];
    }
    return studentiAttrActiveForStudentWithSource((int)$student['id']);
}

function studentiAttrRowsToDisplay(array $rows): array
{
    $map = studentiAttrMap();
    $result = [];
    foreach ($rows as $row) {
        $code = (string)($row['codice_attributo'] ?? $row['codice'] ?? '');
        if ($code === '' || !isset($map[$code])) {
            continue;
        }
        $source = trim((string)($row['fonte'] ?? $row['source'] ?? ''));
        $result[$code] = [
            'codice' => $code,
            'label' => (string)($map[$code]['label'] ?? $code),
            'fonte' => $source,
        ];
    }
    return array_values($result);
}

function studentiAttrActiveFromDsaCsvRow(?array $dsa): array
{
    if (empty($dsa)) {
        return [];
    }

    $textParts = [];
    foreach ($dsa as $key => $value) {
        $textParts[] = (string)$key . ' ' . (string)$value;
    }
    $parsed = studentiAttrParseNote(implode(' ', $textParts));
    $parsed[STUD_ATTR_R7A2] = true;

    $rows = [];
    foreach ($parsed as $code => $active) {
        if ($active) {
            $rows[] = [
                'codice_attributo' => $code,
                'fonte' => 'csv_dsa',
            ];
        }
    }
    return studentiAttrRowsToDisplay($rows);
}

function studentiAttrSyncFromDsaCsvRow(string $fiscalCode, ?array $dsa, string $sourceRef = ''): void
{
    if (empty($dsa)) {
        return;
    }
    studentiAttrEnsureTables();
    $student = studentiAttrFindStudentByFiscalCode($fiscalCode);
    if (!$student) {
        return;
    }

    $sourceHash = hash('sha256', json_encode($dsa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    foreach (studentiAttrActiveFromDsaCsvRow($dsa) as $attr) {
        studentiAttrUpsert((int)$student['id'], (string)$attr['codice'], true, 'csv_dsa', $sourceRef, $sourceHash);
    }
}

function studentiAttrForIscrizionePratica(array $pratica): array
{
    $result = [];
    foreach (studentiAttrActiveForFiscalCode((string)($pratica['codice_fiscale'] ?? '')) as $attr) {
        $result[(string)$attr['codice']] = $attr;
    }

    $dsa = null;
    $rawDsa = trim((string)($pratica['raw_dsa_json'] ?? ''));
    if ($rawDsa !== '') {
        $decoded = json_decode($rawDsa, true);
        if (is_array($decoded)) {
            $dsa = $decoded;
        }
    }
    foreach (studentiAttrActiveFromDsaCsvRow($dsa) as $attr) {
        $code = (string)$attr['codice'];
        if (isset($result[$code]) && trim((string)($result[$code]['fonte'] ?? '')) !== '') {
            if (strpos((string)$result[$code]['fonte'], 'csv_dsa') === false) {
                $result[$code]['fonte'] .= '+csv_dsa';
            }
        } else {
            $result[$code] = $attr;
        }
    }

    return array_values($result);
}

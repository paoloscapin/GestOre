<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/admin_lib.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

function mastercomTagReportEnsureTables(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_tag_stampe` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `anno_scolastico_id` INT NULL,
          `data_inizio` DATE NULL,
          `data_fine` DATE NULL,
          `classi_label` VARCHAR(255) NULL,
          `docente_label` VARCHAR(255) NULL,
          `tag_label` TEXT NULL,
          `source_filename` VARCHAR(255) NULL,
          `source_hash` CHAR(40) NULL,
          `created_by` INT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_mastercom_tag_stampe_created_at` (`created_at`),
          KEY `idx_mastercom_tag_stampe_anno` (`anno_scolastico_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_tag_righe` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `stampa_id` INT NOT NULL,
          `data_ora` DATETIME NULL,
          `tag` VARCHAR(120) NULL,
          `docente` VARCHAR(255) NULL,
          `materia` VARCHAR(255) NULL,
          `classe` VARCHAR(255) NULL,
          `argomento` TEXT NULL,
          `modulo` TEXT NULL,
          `row_hash` CHAR(40) NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_mastercom_tag_righe_stampa_hash` (`stampa_id`, `row_hash`),
          KEY `idx_mastercom_tag_righe_stampa` (`stampa_id`),
          KEY `idx_mastercom_tag_righe_data` (`data_ora`),
          KEY `idx_mastercom_tag_righe_tag` (`tag`),
          CONSTRAINT `fk_mastercom_tag_righe_stampa`
            FOREIGN KEY (`stampa_id`) REFERENCES `mastercom_tag_stampe` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function mastercomTagReportCleanText($value): string
{
    $text = trim((string)($value ?? ''));
    $text = str_replace("\xc2\xa0", ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

function mastercomTagReportParseItalianDateTime($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        try {
            return SpreadsheetDate::excelToDateTimeObject((float)$value)->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    $text = mastercomTagReportCleanText($value);
    $formats = ['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!' . $format, $text, new DateTimeZone('Europe/Rome'));
        $errors = DateTime::getLastErrors();
        $hasErrors = is_array($errors) && (intval($errors['warning_count'] ?? 0) > 0 || intval($errors['error_count'] ?? 0) > 0);
        if ($date instanceof DateTime && !$hasErrors) {
            return $format === 'd/m/Y'
                ? $date->format('Y-m-d') . ' 00:00:00'
                : $date->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function mastercomTagReportFormatDateTime(?string $value): string
{
    if (empty($value)) {
        return '';
    }

    try {
        return (new DateTime((string)$value))->format('d/m/Y H:i');
    } catch (Exception $e) {
        return (string)$value;
    }
}

function mastercomTagReportSafeFilename(string $value, string $fallback = 'stampa_tag'): string
{
    $value = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $value);
    $value = trim((string)$value, '._-');
    return $value !== '' ? $value : $fallback;
}

function mastercomTagReportMetadataFromRows(array $rows): array
{
    $metadata = [
        'periodo' => '',
        'classi_label' => '',
        'docente_label' => '',
        'tag_label' => '',
    ];

    foreach (array_slice($rows, 0, 4) as $row) {
        $text = mastercomTagReportCleanText($row[0] ?? '');
        if (stripos($text, 'Periodo:') === 0) {
            $metadata['periodo'] = trim(substr($text, strlen('Periodo:')));
        } elseif (stripos($text, 'Classi:') === 0) {
            $metadata['classi_label'] = trim(substr($text, strlen('Classi:')));
        } elseif (stripos($text, 'Docente:') === 0) {
            $metadata['docente_label'] = trim(substr($text, strlen('Docente:')));
        } elseif (stripos($text, 'Tag:') === 0) {
            $metadata['tag_label'] = trim(substr($text, strlen('Tag:')));
        }
    }

    return $metadata;
}

function mastercomTagReportParsedRowsFromWorksheet($sheet): array
{
    $highestRow = $sheet->getHighestRow();
    $rawRows = [];
    for ($row = 1; $row <= $highestRow; $row++) {
        $values = [];
        for ($col = 1; $col <= 7; $col++) {
            $cellAddress = Coordinate::stringFromColumnIndex($col) . $row;
            $cell = $sheet->getCell($cellAddress);
            $values[] = mastercomTagReportCleanText($cell->getFormattedValue());
        }
        $rawRows[] = $values;
    }

    $dataRows = [];
    for ($idx = 5; $idx < count($rawRows); $idx++) {
        $row = $rawRows[$idx];
        $nonEmpty = array_values(array_filter($row, function ($value) {
            return mastercomTagReportCleanText($value) !== '';
        }));
        if (empty($nonEmpty)) {
            continue;
        }

        $dateTime = mastercomTagReportParseItalianDateTime($row[0] ?? '');
        if ($dateTime === null) {
            if (!empty($dataRows)) {
                $tail = implode(' ', $nonEmpty);
                $lastIndex = count($dataRows) - 1;
                $dataRows[$lastIndex]['argomento'] = trim($dataRows[$lastIndex]['argomento'] . ' ' . $tail);
                $dataRows[$lastIndex]['note_importazione'][] = 'Riga ' . ($idx + 1) . ' riagganciata ad argomento';
            }
            continue;
        }

        $dataRows[] = [
            'data_ora' => $dateTime,
            'tag' => $row[1] ?? '',
            'docente' => $row[2] ?? '',
            'materia' => $row[3] ?? '',
            'classe' => $row[4] ?? '',
            'argomento' => $row[5] ?? '',
            'modulo' => $row[6] ?? '',
            'note_importazione' => [],
        ];
    }

    return [$rawRows, $dataRows];
}

function mastercomTagReportImportFromBinary(string $body, string $filename, array $metadata = []): array
{
    global $__anno_scolastico_corrente_id, $__utente_id;

    mastercomTagReportEnsureTables();

    $safeFilename = mastercomTagReportSafeFilename($filename, 'elenco_tag.xlsx');
    $extension = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['xls', 'xlsx', 'csv'], true)) {
        $extension = 'xlsx';
    }

    $tmpBase = tempnam(sys_get_temp_dir(), 'gestore_tag_');
    $tmpFile = $tmpBase . '.' . $extension;
    file_put_contents($tmpFile, $body);

    try {
        $spreadsheet = IOFactory::load($tmpFile);
        $sheet = $spreadsheet->getActiveSheet();
        [$rawRows, $rows] = mastercomTagReportParsedRowsFromWorksheet($sheet);
        $fileMetadata = mastercomTagReportMetadataFromRows($rawRows);
    } finally {
        if (is_file($tmpFile)) {
            unlink($tmpFile);
        }
        if (is_file($tmpBase)) {
            unlink($tmpBase);
        }
    }

    $dataInizio = $metadata['data_inizio'] ?? null;
    $dataFine = $metadata['data_fine'] ?? null;
    if (($dataInizio === null || $dataFine === null) && !empty($fileMetadata['periodo'])) {
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})/', $fileMetadata['periodo'], $matches)) {
            $dataInizio = DateTime::createFromFormat('!d/m/Y', $matches[1])->format('Y-m-d');
            $dataFine = DateTime::createFromFormat('!d/m/Y', $matches[2])->format('Y-m-d');
        }
    }

    $classiLabel = $metadata['classi_label'] ?? $fileMetadata['classi_label'];
    $docenteLabel = $metadata['docente_label'] ?? $fileMetadata['docente_label'];
    $tagLabel = $metadata['tag_label'] ?? $fileMetadata['tag_label'];
    $sourceHash = sha1($body);

    dbExec("
        INSERT INTO mastercom_tag_stampe
          (anno_scolastico_id, data_inizio, data_fine, classi_label, docente_label, tag_label, source_filename, source_hash, created_by, created_at)
        VALUES
          (" . dbI($__anno_scolastico_corrente_id ?? null) . ",
           " . dbQ($dataInizio) . ",
           " . dbQ($dataFine) . ",
           " . dbQ($classiLabel) . ",
           " . dbQ($docenteLabel) . ",
           " . dbQ($tagLabel) . ",
           " . dbQ($safeFilename) . ",
           " . dbQ($sourceHash) . ",
           " . dbI($__utente_id ?? null) . ",
           NOW())
    ");
    $stampaId = intval(dblastId());

    foreach ($rows as $row) {
        $rowHash = sha1(implode('|', [
            $row['data_ora'],
            $row['tag'],
            $row['docente'],
            $row['materia'],
            $row['classe'],
            $row['argomento'],
            $row['modulo'],
        ]));

        dbExec("
            INSERT IGNORE INTO mastercom_tag_righe
              (stampa_id, data_ora, tag, docente, materia, classe, argomento, modulo, row_hash)
            VALUES
              (" . dbI($stampaId) . ",
               " . dbQ($row['data_ora']) . ",
               " . dbQ($row['tag']) . ",
               " . dbQ($row['docente']) . ",
               " . dbQ($row['materia']) . ",
               " . dbQ($row['classe']) . ",
               " . dbQ($row['argomento']) . ",
               " . dbQ($row['modulo']) . ",
               " . dbQ($rowHash) . ")
        ");
    }

    return [
        'ok' => true,
        'stampa_id' => $stampaId,
        'rows' => count($rows),
        'source_filename' => $safeFilename,
    ];
}

function mastercomTagReportLoadStampa(int $stampaId): ?array
{
    mastercomTagReportEnsureTables();
    return dbGetFirst("
        SELECT s.*, CONCAT(u.cognome, ' ', u.nome) AS creato_da_nome
        FROM mastercom_tag_stampe s
        LEFT JOIN utente u ON u.id = s.created_by
        WHERE s.id = " . dbI($stampaId) . "
        LIMIT 1
    ");
}

function mastercomTagReportFilterSql(array $filters): string
{
    $where = [];
    foreach (['tag', 'docente', 'materia', 'classe'] as $field) {
        $value = trim((string)($filters[$field] ?? ''));
        if ($value !== '') {
            $where[] = "`$field` = " . dbQ($value);
        }
    }

    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $like = "'%" . dbEscape($q) . "%'";
        $where[] = "(argomento LIKE $like OR modulo LIKE $like OR docente LIKE $like OR materia LIKE $like OR tag LIKE $like)";
    }

    return empty($where) ? '' : ' AND ' . implode(' AND ', $where);
}

function mastercomTagReportLoadRows(int $stampaId, array $filters = []): array
{
    mastercomTagReportEnsureTables();
    return dbGetAll("
        SELECT *
        FROM mastercom_tag_righe
        WHERE stampa_id = " . dbI($stampaId) . mastercomTagReportFilterSql($filters) . "
        ORDER BY data_ora ASC, id ASC
    ") ?: [];
}

function mastercomTagReportDistinctValues(int $stampaId, string $field): array
{
    if (!in_array($field, ['tag', 'docente', 'materia', 'classe'], true)) {
        return [];
    }

    return dbGetAllValues("
        SELECT DISTINCT `$field`
        FROM mastercom_tag_righe
        WHERE stampa_id = " . dbI($stampaId) . "
          AND `$field` IS NOT NULL
          AND `$field` <> ''
        ORDER BY `$field` ASC
    ") ?: [];
}

function mastercomTagReportSummary(int $stampaId): array
{
    $summary = [
        'totale' => intval(dbGetValue("SELECT COUNT(*) FROM mastercom_tag_righe WHERE stampa_id = " . dbI($stampaId))),
        'tag' => dbGetAll("SELECT tag AS label, COUNT(*) AS totale FROM mastercom_tag_righe WHERE stampa_id = " . dbI($stampaId) . " GROUP BY tag ORDER BY totale DESC, tag ASC") ?: [],
        'docenti' => dbGetAll("SELECT docente AS label, COUNT(*) AS totale FROM mastercom_tag_righe WHERE stampa_id = " . dbI($stampaId) . " GROUP BY docente ORDER BY totale DESC, docente ASC LIMIT 8") ?: [],
        'materie' => dbGetAll("SELECT materia AS label, COUNT(*) AS totale FROM mastercom_tag_righe WHERE stampa_id = " . dbI($stampaId) . " GROUP BY materia ORDER BY totale DESC, materia ASC LIMIT 8") ?: [],
    ];
    return $summary;
}

?>

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
require_once __DIR__ . '/../student_gender.php';

function mastercomAdminTableExists(string $tableName): bool
{
    $tableName = trim($tableName);
    if ($tableName === '') {
        return false;
    }

    $value = dbGetValue("SHOW TABLES LIKE " . dbQ($tableName));
    return $value !== null;
}

function mastercomAdminTableColumnExists(string $tableName, string $columnName): bool
{
    $tableName = trim($tableName);
    $columnName = trim($columnName);
    if ($tableName === '' || $columnName === '') {
        return false;
    }

    $row = dbGetFirst("SHOW COLUMNS FROM `$tableName` LIKE " . dbQ($columnName));
    return is_array($row) && !empty($row);
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

function mastercomAdminNormalizeCsvHeader(string $header): string
{
    $header = mastercomAdminNorm($header);
    $header = str_replace(['/', '\\', '-', '_', '.', ':', ';', ','], ' ', $header);
    $header = preg_replace('/\s+/', ' ', $header);
    return trim((string)$header);
}

function mastercomAdminDetectCsvDelimiter(string $text): string
{
    $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
    $lines = array_values(array_filter($lines, function ($line) {
        return trim((string)$line) !== '';
    }));
    if (empty($lines)) {
        return ';';
    }

    $delimiters = [';', ',', "\t"];
    $bestDelimiter = ';';
    $bestCount = -1;
    $probeLines = array_slice($lines, 0, 12);
    foreach ($delimiters as $delimiter) {
        $count = 0;
        foreach ($probeLines as $line) {
            $count = max($count, count(str_getcsv($line, $delimiter)));
        }
        if ($count > $bestCount) {
            $bestCount = $count;
            $bestDelimiter = $delimiter;
        }
    }

    return $bestDelimiter;
}

function mastercomAdminParseCsvRows(string $csvBody): array
{
    $csvBody = str_replace("\r\n", "\n", str_replace("\r", "\n", $csvBody));
    if (substr($csvBody, 0, 3) === "\xEF\xBB\xBF") {
        $csvBody = substr($csvBody, 3);
    }

    if (!mb_check_encoding($csvBody, 'UTF-8')) {
        $csvBody = mb_convert_encoding($csvBody, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    }

    $delimiter = mastercomAdminDetectCsvDelimiter($csvBody);
    $lines = array_values(array_filter(explode("\n", $csvBody), function ($line) {
        return trim((string)$line) !== '';
    }));

    if (empty($lines)) {
        return [];
    }

    $headerLineIndex = null;
    $headers = [];
    foreach ($lines as $index => $line) {
        $candidateHeaders = array_map(function ($value) {
            return mastercomAdminNormalizeCsvHeader((string)$value);
        }, str_getcsv($line, $delimiter));

        $hasSurname = in_array('COGNOME', $candidateHeaders, true);
        $hasName = in_array('NOME', $candidateHeaders, true);
        if ($hasSurname && $hasName) {
            $headerLineIndex = $index;
            $headers = $candidateHeaders;
            break;
        }
    }

    if ($headerLineIndex === null) {
        $headers = array_map(function ($value) {
            return mastercomAdminNormalizeCsvHeader((string)$value);
        }, str_getcsv(array_shift($lines), $delimiter));
    } else {
        $lines = array_slice($lines, $headerLineIndex + 1);
    }

    $rows = [];
    foreach ($lines as $line) {
        $values = str_getcsv($line, $delimiter);
        if (empty($values)) {
            continue;
        }

        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = mastercomAdminCleanText($values[$index] ?? null);
        }
        if (!empty($row)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function mastercomAdminFindCsvValue(array $row, array $aliases): ?string
{
    foreach ($aliases as $alias) {
        $normalizedAlias = mastercomAdminNormalizeCsvHeader($alias);
        if (array_key_exists($normalizedAlias, $row)) {
            return mastercomAdminCleanText($row[$normalizedAlias]);
        }
    }
    return null;
}

function mastercomAdminMapReligionExemptionValue($value): ?int
{
    $normalized = mastercomAdminNorm((string)$value);
    if ($normalized === '') {
        return null;
    }

    if (in_array($normalized, ['1', 'NO', 'N', 'FALSE'], true)) {
        return 1;
    }

    if (in_array($normalized, ['0', 'SI', 'S', 'TRUE'], true)) {
        return 0;
    }

    return null;
}

function mastercomAdminBuildStudentSupplementalMapForClass(int $classId): array
{
    if ($classId <= 0) {
        return ['ok' => false, 'message' => 'class_id non valido', 'map' => []];
    }

    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$authResult['ok']) {
        return ['ok' => false, 'message' => 'Autenticazione admin MasterCom fallita', 'map' => []];
    }

    $schoolYear = mastercomAdminCurrentSchoolYear() ?? '';
    $payload = [
        'stato_secondario' => 'stampa_elenchi_particolari_update',
        'form_stato' => 'amministratore',
        'stato_principale' => 'stampe_principale',
        'tipo_stampa' => 'elenchi_studenti',
        'nome_template' => '',
        'testo_libero_inserito' => '',
        'nome_campo_personalizzato1' => '',
        'nome_campo_personalizzato2' => '',
        'nome_campo_personalizzato3' => '',
        'cognome' => 1,
        'nome' => 1,
        'codice_fiscale' => 1,
        'sesso' => 1,
        'esonero_religione' => 1,
        'descrizione_materia_integrativa' => 1,
        'voto_condotta_value' => 0,
        'anno_controllo_esito' => $schoolYear,
        'filtro_esito_storico' => 'tutte',
        'periodo_media' => 9,
        'filtro_media_voti' => 0,
        'cifre_significative' => 0,
        'filtro_nazionalita' => 'tutte',
        'filtro_cittadinanza' => 'tutte',
        'filtro_sesso' => 'tutte',
        'filtro_esito' => 'tutte',
        'filtro_dsa' => 'tutti',
        'filtro_bes' => 'tutti',
        'filtro_pei' => 'tutti',
        'filtro_necessita_alfabetizzazione' => 'tutti',
        'filtro_qualifica_iefp' => 'tutti',
        'filtro_religione' => 'tutti',
        'confronto_data_iscrizione' => 'min',
        'data_ricerca_iscrizione_Day' => date('d'),
        'data_ricerca_iscrizione_Month' => date('m'),
        'data_ricerca_iscrizione_Year' => date('Y'),
        'filtro_convittore' => 'tutte',
        'filtro_data_dopo_Day' => 0,
        'filtro_data_dopo_Month' => 0,
        'filtro_data_dopo_Year' => 0,
        'filtro_data_prima_Day' => 0,
        'filtro_data_prima_Month' => 0,
        'filtro_data_prima_Year' => 0,
        'filtro_maggiore_eta' => 'tutti',
        'confronto_data_ritiro' => 'min',
        'data_ricerca_ritiro_Day' => date('d'),
        'data_ricerca_ritiro_Month' => date('m'),
        'data_ricerca_ritiro_Year' => date('Y'),
        'filtro_validita' => 'tutti',
        'permesso_entrata_ritardo' => '',
        'permesso_uscita_anticipo' => '',
        'permesso_assenza_pomeriggio' => '',
        'filtro_non_viventi' => 'SI',
        'filtro_fratelli' => 'tutti',
        'nome_campo1' => '',
        'nome_campo2' => '',
        'nome_campo3' => '',
        'mat_classi[]' => $classId,
        'tipo_file_esportato' => 'csv',
        'orientamento_pagina' => 'P',
        'formato_pagina_selezionato' => 'A4',
        'stampa_intestazione' => 'SI',
        'anno_scolastico_intestazione' => 'NO',
        'tipo_stampa_elenco' => 'TABELLA',
        'altezza_desiderata' => 4,
        'data_stampa_intestazione' => 'NO',
        'dimensione_font' => 8,
        'stampa_statistiche' => 'NO',
        'bottone.x' => 20,
        'bottone.y' => 29,
    ];

    $startedAt = microtime(true);
    info('MasterCom export CSV classe ' . $classId . ' avvio');

    $csvResult = mastercomSubmitAdminAbsenceAction($authResult, $payload, [
        'base_url' => mastercomIndexUrl(),
        'method' => 'POST',
        'timeout' => 180,
        'send_in_body' => true,
    ]);
    $elapsedSeconds = round(microtime(true) - $startedAt, 2);

    $body = (string)($csvResult['body'] ?? '');
    if (preg_match("/tmp_xls\/[^'\"<>]+\.csv/i", $body, $matches)) {
        $csvRelativePath = $matches[0];
        $csvDownloadUrl = rtrim(dirname(mastercomBaseUrl()), '/') . '/' . ltrim($csvRelativePath, '/');
        info('MasterCom export CSV classe ' . $classId . ' file generato: ' . $csvRelativePath);

        $downloadStartedAt = microtime(true);
        $downloadResult = mastercomRawRequest([], [
            'base_url' => $csvDownloadUrl,
            'cookie' => implode('; ', array_filter($authResult['cookies'] ?? [])),
            'method' => 'GET',
            'timeout' => 180,
        ]);
        $downloadElapsedSeconds = round(microtime(true) - $downloadStartedAt, 2);

        if ($downloadResult['ok'] && trim((string)($downloadResult['body'] ?? '')) !== '') {
            $body = (string)$downloadResult['body'];
            $csvResult['content_type'] = $downloadResult['content_type'] ?? ($csvResult['content_type'] ?? '');
            $csvResult['http_code'] = $downloadResult['http_code'] ?? ($csvResult['http_code'] ?? 0);
            $elapsedSeconds = round($elapsedSeconds + $downloadElapsedSeconds, 2);
            info('MasterCom export CSV classe ' . $classId . ' download file completato'
                . ' | elapsed=' . $downloadElapsedSeconds . 's'
                . ' | http=' . intval($downloadResult['http_code'] ?? 0)
                . ' | content_type=' . trim((string)($downloadResult['content_type'] ?? '')));
        } else {
            $preview = preg_replace('/\s+/u', ' ', trim(substr((string)($downloadResult['body'] ?? ''), 0, 500)));
            warning('MasterCom export CSV classe ' . $classId . ' download file fallito'
                . ' | elapsed=' . $downloadElapsedSeconds . 's'
                . ' | http=' . intval($downloadResult['http_code'] ?? 0)
                . ' | content_type=' . trim((string)($downloadResult['content_type'] ?? ''))
                . ($preview !== '' ? ' | body=' . $preview : ''));
        }
    }

    if (!$csvResult['ok'] || trim($body) === '') {
        $preview = preg_replace('/\s+/u', ' ', trim(substr($body, 0, 500)));
        warning('MasterCom export CSV classe ' . $classId . ' fallito'
            . ' | elapsed=' . $elapsedSeconds . 's'
            . ' | http=' . intval($csvResult['http_code'] ?? 0)
            . ' | content_type=' . trim((string)($csvResult['content_type'] ?? ''))
            . ($preview !== '' ? ' | body=' . $preview : '')
        );
        return [
            'ok' => false,
            'message' => 'Esportazione CSV studenti MasterCom fallita',
            'map' => [],
            'rows_count' => 0,
            'elapsed_seconds' => $elapsedSeconds,
            'http_code' => intval($csvResult['http_code'] ?? 0),
            'content_type' => trim((string)($csvResult['content_type'] ?? '')),
            'preview' => $preview,
        ];
    }

    $rows = mastercomAdminParseCsvRows($body);
    if (empty($rows)) {
        $preview = preg_replace('/\s+/u', ' ', trim(substr($body, 0, 500)));
        warning('MasterCom export CSV classe ' . $classId . ' senza righe utili'
            . ' | elapsed=' . $elapsedSeconds . 's'
            . ' | http=' . intval($csvResult['http_code'] ?? 0)
            . ' | content_type=' . trim((string)($csvResult['content_type'] ?? ''))
            . ($preview !== '' ? ' | body=' . $preview : '')
        );
    } else {
        info('MasterCom export CSV classe ' . $classId . ' righe lette: ' . count($rows)
            . ' | elapsed=' . $elapsedSeconds . 's'
            . ' | http=' . intval($csvResult['http_code'] ?? 0)
            . ' | content_type=' . trim((string)($csvResult['content_type'] ?? '')));
    }
    $map = [];
    foreach ($rows as $row) {
        $surname = mastercomAdminFindCsvValue($row, ['cognome']);
        $name = mastercomAdminFindCsvValue($row, ['nome']);
        $cf = mastercomAdminFindCsvValue($row, ['codice fiscale', 'codice_fiscale', 'cf', 'cod fis', 'cod. fis.']);
        $religionRaw = mastercomAdminFindCsvValue($row, ['esonero religione', 'esonero_religione', 'relig', 'relig.']);
        $alternativeSubject = mastercomAdminFindCsvValue($row, ['descrizione materia integrativa', 'descrizione_materia_integrativa', 'materia integrativa', 'mat alternativa rel', 'mat. alternativa rel.']);

        $extra = [
            'cognome' => $surname,
            'nome' => $name,
            'codice_fiscale' => $cf,
            'esonero_religione' => mastercomAdminMapReligionExemptionValue($religionRaw),
            'descrizione_materia_integrativa' => $alternativeSubject,
            'raw_csv' => $row,
        ];

        $cfKey = mastercomAdminNormCompact($cf);
        if ($cfKey !== '') {
            $map['CF:' . $cfKey] = $extra;
        }

        $nameKey = mastercomAdminNormCompact(($surname ?? '') . ' ' . ($name ?? ''));
        if ($nameKey !== '') {
            $map['NAME:' . $nameKey] = $extra;
        }
    }

    return [
        'ok' => true,
        'message' => 'CSV studenti classe caricato',
        'map' => $map,
        'rows_count' => count($rows),
        'elapsed_seconds' => $elapsedSeconds,
        'http_code' => intval($csvResult['http_code'] ?? 0),
        'content_type' => trim((string)($csvResult['content_type'] ?? '')),
        'preview' => preg_replace('/\s+/u', ' ', trim(substr($body, 0, 250))),
    ];
}

function mastercomAdminFindStudentSupplementalData(array $supplementalMap, array $masterStudent, array $detail = []): array
{
    $cfKey = mastercomAdminNormCompact($detail['cf'] ?? '');
    if ($cfKey !== '' && isset($supplementalMap['CF:' . $cfKey]) && is_array($supplementalMap['CF:' . $cfKey])) {
        return $supplementalMap['CF:' . $cfKey];
    }

    $cfKey = mastercomAdminNormCompact($masterStudent['codice_fiscale'] ?? '');
    if ($cfKey !== '' && isset($supplementalMap['CF:' . $cfKey]) && is_array($supplementalMap['CF:' . $cfKey])) {
        return $supplementalMap['CF:' . $cfKey];
    }

    $nameKey = mastercomAdminNormCompact(
        ($masterStudent['cognome'] ?? $detail['surname'] ?? '') . ' ' . ($masterStudent['nome'] ?? $detail['first_name'] ?? '')
    );
    if ($nameKey !== '' && isset($supplementalMap['NAME:' . $nameKey]) && is_array($supplementalMap['NAME:' . $nameKey])) {
        return $supplementalMap['NAME:' . $nameKey];
    }

    return [];
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

function mastercomAdminNormalizeJsonKey(string $key): string
{
    $key = html_entity_decode($key, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
    $key = strtr($key, [
        'à' => 'a',
        'è' => 'e',
        'é' => 'e',
        'ì' => 'i',
        'ò' => 'o',
        'ù' => 'u',
    ]);
    return preg_replace('/[^a-z0-9]/', '', $key);
}

function mastercomAdminFindValueRecursive($node, array $normalizedKeys)
{
    if (!is_array($node)) {
        return null;
    }

    foreach ($node as $key => $value) {
        if (is_array($value)) {
            continue;
        }
        if (in_array(mastercomAdminNormalizeJsonKey((string)$key), $normalizedKeys, true)) {
            $clean = mastercomAdminCleanText($value);
            if ($clean !== null && $clean !== '') {
                return $clean;
            }
        }
    }

    foreach ($node as $value) {
        if (!is_array($value)) {
            continue;
        }
        $found = mastercomAdminFindValueRecursive($value, $normalizedKeys);
        if ($found !== null && $found !== '') {
            return $found;
        }
    }

    return null;
}

function mastercomAdminPickMastercomValue(array $sources, array $keys): ?string
{
    $normalizedKeys = array_values(array_unique(array_map('mastercomAdminNormalizeJsonKey', $keys)));
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }
        $value = mastercomAdminFindValueRecursive($source, $normalizedKeys);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return null;
}

function mastercomAdminPickMastercomTimestamp(array $sources, array $keys): ?int
{
    $value = mastercomAdminPickMastercomValue($sources, $keys);
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        return intval($value);
    }

    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
        $date = DateTime::createFromFormat($format, $value, new DateTimeZone('Europe/Rome'));
        if ($date instanceof DateTime) {
            return $date->getTimestamp();
        }
    }

    return null;
}

function mastercomAdminHtmlAttribute(string $tag, string $attribute): ?string
{
    $pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*([\'"])(.*?)\1/is';
    if (!preg_match($pattern, $tag, $matches)) {
        return null;
    }

    return mastercomAdminCleanText($matches[2] ?? '');
}

function mastercomAdminExtractInputValuesByName(string $html): array
{
    $values = [];
    if (preg_match_all('/<input\b[^>]*>/is', $html, $matches)) {
        foreach ($matches[0] as $tag) {
            $name = mastercomAdminHtmlAttribute($tag, 'name');
            if ($name === null || $name === '') {
                continue;
            }
            $value = mastercomAdminHtmlAttribute($tag, 'value');
            $values[$name] = $value ?? '';
        }
    }

    return $values;
}

function mastercomAdminExtractFormValuesByName(string $html): array
{
    $values = mastercomAdminExtractInputValuesByName($html);

    if (preg_match_all('/<textarea\b([^>]*)>(.*?)<\/textarea>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $name = mastercomAdminHtmlAttribute($match[1] ?? '', 'name');
            if ($name === null || $name === '') {
                continue;
            }
            $values[$name] = mastercomAdminCleanText(html_entity_decode($match[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
        }
    }

    if (preg_match_all('/<select\b([^>]*)>(.*?)<\/select>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $name = mastercomAdminHtmlAttribute($match[1] ?? '', 'name');
            if ($name === null || $name === '') {
                continue;
            }

            $selected = '';
            if (preg_match('/<option\b([^>]*\bselected\b[^>]*)>(.*?)<\/option>/is', $match[2] ?? '', $optionMatch)) {
                $selected = mastercomAdminHtmlAttribute($optionMatch[1] ?? '', 'value');
                if ($selected === null) {
                    $selected = mastercomAdminCleanText($optionMatch[2] ?? '') ?? '';
                }
            } elseif (preg_match('/<option\b([^>]*)>(.*?)<\/option>/is', $match[2] ?? '', $optionMatch)) {
                $selected = mastercomAdminHtmlAttribute($optionMatch[1] ?? '', 'value');
                if ($selected === null) {
                    $selected = mastercomAdminCleanText($optionMatch[2] ?? '') ?? '';
                }
            }

            $values[$name] = $selected;
        }
    }

    return $values;
}

function mastercomAdminParseCityProvince(?string $description): array
{
    $description = mastercomAdminCleanText($description);
    if ($description === null || $description === '') {
        return ['citta' => null, 'provincia' => null];
    }

    if (preg_match('/^(.*?)\s*\(([A-Z]{2})\)\s*$/u', $description, $matches)) {
        return [
            'citta' => mastercomAdminCleanText($matches[1] ?? ''),
            'provincia' => mastercomAdminCleanText($matches[2] ?? ''),
        ];
    }

    return ['citta' => $description, 'provincia' => null];
}

function mastercomAdminParseParentDetailsFromStudentHtml(string $html): array
{
    $inputs = mastercomAdminExtractInputValuesByName($html);
    $parents = [];

    foreach ($inputs as $name => $value) {
        if (!preg_match('/^parente_(\d+)_(.+)$/', (string)$name, $matches)) {
            continue;
        }

        $parentId = intval($matches[1]);
        $field = (string)$matches[2];
        if ($parentId <= 0 || $field === '') {
            continue;
        }

        if (!isset($parents[$parentId])) {
            $parents[$parentId] = ['mastercom_id_parente' => $parentId];
        }
        $parents[$parentId][$field] = mastercomAdminCleanText($value);
    }

    foreach ($parents as $parentId => $fields) {
        $residenza = mastercomAdminParseCityProvince($fields['residenza_description'] ?? null);
        $domicilio = mastercomAdminParseCityProvince($fields['domicilio_description'] ?? null);

        $parents[$parentId]['codice_fiscale'] = $fields['codice_fiscale'] ?? null;
        $parents[$parentId]['cognome'] = $fields['cognome'] ?? null;
        $parents[$parentId]['nome'] = $fields['nome'] ?? null;
        $parents[$parentId]['email'] = $fields['email'] ?? null;
        $parents[$parentId]['telefono'] = $fields['telefono_abitazione'] ?? $fields['telefono_lavoro'] ?? null;
        $parents[$parentId]['cellulare'] = $fields['telefono_cellulare'] ?? null;
        $parents[$parentId]['indirizzo'] = $fields['indirizzo'] ?? $fields['domicilio'] ?? null;
        $parents[$parentId]['cap'] = $fields['cap_residenza'] ?? $fields['cap_domicilio'] ?? null;
        $parents[$parentId]['citta'] = $residenza['citta'] ?? $domicilio['citta'] ?? null;
        $parents[$parentId]['provincia'] = $residenza['provincia'] ?? $domicilio['provincia'] ?? null;
    }

    return $parents;
}

function mastercomAdminParentDetailsFromLinkedStudents(array $authResult, array $parent, int $parentId, array &$studentAdminParentsCache): array
{
    foreach (($parent['studenti_abbinati'] ?? []) as $child) {
        $studentMcId = intval($child['id_studente'] ?? 0);
        if ($studentMcId <= 0) {
            continue;
        }

        if (!array_key_exists($studentMcId, $studentAdminParentsCache)) {
            $studentMirror = dbGetFirst("
                SELECT s.*, c.nome AS mastercom_classe_nome, c.raw_json AS mastercom_classe_raw_json
                FROM mastercom_studenti s
                LEFT JOIN mastercom_classi c ON c.mastercom_id_classe = s.mastercom_id_classe_corrente
                WHERE s.mastercom_id_studente = " . intval($studentMcId) . "
                LIMIT 1
            ");
            $classId = intval($studentMirror['mastercom_id_classe_corrente'] ?? ($child['id_classe'] ?? 0));
            $className = mastercomAdminCleanText($studentMirror['mastercom_classe_nome'] ?? ($child['classe'] ?? '')) ?? '';
            $classRaw = json_decode((string)($studentMirror['mastercom_classe_raw_json'] ?? ''), true);
            $classRaw = is_array($classRaw) ? $classRaw : [];
            $idIndirizzo = $child['id_indirizzo']
                ?? $studentMirror['id_indirizzo']
                ?? $studentMirror['mastercom_id_indirizzo']
                ?? $classRaw['id_indirizzo']
                ?? $classRaw['id_indirizzi']
                ?? '';

            $studentAdminParentsCache[$studentMcId] = [];
            if ($classId > 0) {
                $htmlResult = mastercomLoadStudentAdminProfileHtml($authResult, [
                    'id_studente' => $studentMcId,
                    'id_classe' => $classId,
                    'classe' => $className,
                    'id_indirizzo' => $idIndirizzo,
                ], [
                    'timeout' => 120,
                ]);
                if (!empty($htmlResult['ok']) && is_string($htmlResult['body'] ?? null)) {
                    $studentAdminParentsCache[$studentMcId] = mastercomAdminParseParentDetailsFromStudentHtml($htmlResult['body']);
                } else {
                    warning('mastercomAdminSyncParents: scheda studente MasterCom non disponibile student_id=' . $studentMcId . ' class_id=' . $classId . ' http=' . intval($htmlResult['http_code'] ?? 0) . ' error=' . (string)($htmlResult['error'] ?? ''));
                }
            }
        }

        if (isset($studentAdminParentsCache[$studentMcId][$parentId]) && is_array($studentAdminParentsCache[$studentMcId][$parentId])) {
            return $studentAdminParentsCache[$studentMcId][$parentId];
        }
    }

    return [];
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

    if (isset($parts[0], $parts[1]) && preg_match('/^\d+$/', $parts[0]) && preg_match('/^[A-Z]+$/u', $parts[1])) {
        $parsed['classe_label'] = $parts[0] . $parts[1];
        $parsed['classe_numero'] = intval($parts[0]);
        $parsed['sezione'] = $parts[1];
        if (isset($parts[2])) {
            $parsed['codice_indirizzo'] = $parts[2];
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

function mastercomAdminIsWithdrawnClassName(?string $className): bool
{
    $normalized = mastercomAdminNorm((string)$className);
    $normalized = str_replace(['.', '-'], ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = trim((string)$normalized);

    return preg_match('/^1RIT\b/', $normalized) === 1
        || preg_match('/^1RR\b/', $normalized) === 1;
}

function mastercomAdminStudentExpectedActive(array $mirrorRow): int
{
    return mastercomAdminIsWithdrawnClassName($mirrorRow['classe_mastercom'] ?? '') ? 0 : 1;
}

function mastercomAdminIsOperationalClassId(int $classId): bool
{
    if ($classId <= 0 || !mastercomAdminTableExists('mastercom_classi')) {
        return false;
    }

    $row = dbGetFirst("SELECT * FROM mastercom_classi WHERE mastercom_id_classe = " . dbI($classId) . " LIMIT 1");
    if (is_array($row) && mastercomAdminTableColumnExists('mastercom_classi', 'attiva_mastercom') && intval($row['attiva_mastercom'] ?? 1) !== 1) {
        return false;
    }
    return is_array($row) && mastercomAdminResolveLocalClass($row) !== null;
}

function mastercomAdminOperationalClassRows(string $fields = '*'): array
{
    if (!mastercomAdminTableExists('mastercom_classi')) {
        return [];
    }

    $activeWhere = mastercomAdminTableColumnExists('mastercom_classi', 'attiva_mastercom')
        ? 'WHERE COALESCE(attiva_mastercom, 1) = 1'
        : '';
    $rows = dbGetAll("SELECT * FROM mastercom_classi $activeWhere ORDER BY nome ASC") ?: [];
    $operational = [];
    foreach ($rows as $row) {
        if (mastercomAdminResolveLocalClass($row) === null) {
            continue;
        }

        if ($fields === '*') {
            $operational[] = $row;
        } else {
            $item = [];
            foreach (array_map('trim', explode(',', $fields)) as $field) {
                if ($field !== '') {
                    $item[$field] = $row[$field] ?? null;
                }
            }
            $operational[] = $item;
        }
    }

    return $operational;
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

function mastercomAdminGetLocalStudentById(int $studentId): ?array
{
    global $__anno_scolastico_corrente_id;

    if ($studentId <= 0) {
        return null;
    }

    $query = "
        SELECT
            s.*,
            sf.id_classe AS id_classe_corrente,
            c.classe AS classe_corrente
        FROM studente s
        LEFT JOIN studente_frequenta sf
            ON sf.id = (
                SELECT sf2.id
                FROM studente_frequenta sf2
                WHERE sf2.id_studente = s.id
                ORDER BY
                    CASE
                        WHEN sf2.id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . " THEN 0
                        ELSE 1
                    END,
                    sf2.id_anno_scolastico DESC,
                    sf2.id DESC
                LIMIT 1
            )
        LEFT JOIN classi c
            ON c.id = sf.id_classe
        WHERE s.id = " . intval($studentId) . "
        LIMIT 1
    ";

    return dbGetFirst($query);
}

function mastercomAdminExpectedLocalClassId(array $masterStudent): ?int
{
    $mirrorClassId = intval($masterStudent['mastercom_id_classe_corrente'] ?? 0);
    if ($mirrorClassId > 0) {
        $mirrorClass = dbGetFirst("SELECT id_classe_gestore, nome FROM mastercom_classi WHERE mastercom_id_classe = " . $mirrorClassId . " LIMIT 1");
        if ($mirrorClass != null) {
            $localClassId = intval($mirrorClass['id_classe_gestore'] ?? 0);
            if ($localClassId > 0) {
                return $localClassId;
            }

            $mirrorClassName = trim((string)($mirrorClass['nome'] ?? ''));
            if ($mirrorClassName !== '') {
                return mastercomAdminFindLocalClassIdByName($mirrorClassName);
            }
        }
    }

    $className = trim((string)($masterStudent['classe_mastercom'] ?? $masterStudent['nome'] ?? $masterStudent['classe_label'] ?? ''));
    if ($className !== '') {
        return mastercomAdminFindLocalClassIdByName($className);
    }

    $classeNumero = trim((string)($masterStudent['classe_numero'] ?? $masterStudent['classe'] ?? ''));
    $sezione = trim((string)($masterStudent['sezione'] ?? ''));
    if ($classeNumero !== '') {
        $classLabel = $classeNumero . $sezione;
        return mastercomAdminFindLocalClassIdByName($classLabel);
    }

    return null;
}

function mastercomAdminFindLocalStudent(array $masterStudent): ?array
{
    global $__anno_scolastico_corrente_id;

    $cf = trim((string)($masterStudent['codice_fiscale'] ?? ''));
    $expectedClassId = mastercomAdminExpectedLocalClassId($masterStudent);

    if ($cf === '') {
        return null;
    }

    $query = "
        SELECT
            s.*,
            sf.id_classe AS id_classe_corrente,
            c.classe AS classe_corrente
        FROM studente s
        LEFT JOIN studente_frequenta sf
            ON sf.id = (
                SELECT sf2.id
                FROM studente_frequenta sf2
                WHERE sf2.id_studente = s.id
                ORDER BY
                    CASE
                        WHEN sf2.id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . " THEN 0
                        ELSE 1
                    END,
                    sf2.id_anno_scolastico DESC,
                    sf2.id DESC
                LIMIT 1
            )
        LEFT JOIN classi c
            ON c.id = sf.id_classe
        WHERE LOWER(s.codice_fiscale) = LOWER(" . dbQ($cf) . ")
        ORDER BY s.attivo DESC, s.id DESC
    ";
    $rows = dbGetAll($query) ?: [];
    if ($expectedClassId !== null) {
        foreach ($rows as $row) {
            if (intval($row['id_classe_corrente'] ?? 0) === $expectedClassId) {
                return $row;
            }
        }
    }

    return count($rows) === 1 ? $rows[0] : null;
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

function mastercomAdminResolveLocalStudent(array $mirrorRow): ?array
{
    $linked = null;
    if (!empty($mirrorRow['id_studente_gestore'])) {
        $linked = mastercomAdminGetLocalStudentById(intval($mirrorRow['id_studente_gestore']));
    }

    $matched = mastercomAdminFindLocalStudent($mirrorRow);
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

    if ($linked != null) {
        $expectedClassId = mastercomAdminExpectedLocalClassId($mirrorRow);
        $sameClass = $expectedClassId === null || intval($linked['id_classe_corrente'] ?? 0) === $expectedClassId;
        $sameName = mastercomAdminNorm($linked['cognome'] ?? '') === mastercomAdminNorm($mirrorRow['cognome'] ?? '')
            && mastercomAdminNorm($linked['nome'] ?? '') === mastercomAdminNorm($mirrorRow['nome'] ?? '');
        if ($sameClass && $sameName) {
            return $linked;
        }
    }

    return null;
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

function mastercomAdminStudentSyncErrorMessage(int $classId, int $studentId, array $masterStudent, string $message): string
{
    $label = trim((string)(($masterStudent['cognome'] ?? '') . ' ' . ($masterStudent['nome'] ?? '')));
    if ($label === '') {
        $label = 'studente ' . $studentId;
    }

    return 'Errore sync studenti su classe ' . $classId
        . ', studente ' . $studentId
        . ' (' . $label . '): '
        . $message;
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
    $seenClassIds = [];
    foreach ($classes as $class) {
        if (!is_array($class)) {
            continue;
        }
        $mastercomClassId = intval($class['valore'] ?? 0);
        if ($mastercomClassId <= 0) {
            continue;
        }
        $seenClassIds[] = $mastercomClassId;
        mastercomAdminProgress($progress, 'classes', $updated + 1, $total, 'Sincronizzazione classe ' . (($class['nome'] ?? '')));

        $className = trim((string)($class['nome'] ?? ''));
        $parsed = mastercomAdminParseClassName($className);
        $localClassId = mastercomAdminFindLocalClassIdByName($className);

        mastercomAdminUpsertByField('mastercom_classi', 'mastercom_id_classe', $mastercomClassId, [
            'id_classe_gestore' => $localClassId,
            'mastercom_id_classe' => $mastercomClassId,
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

    $deactivated = 0;
    if (!empty($seenClassIds) && mastercomAdminTableColumnExists('mastercom_classi', 'attiva_mastercom')) {
        $seenSql = implode(',', array_map('intval', array_values(array_unique($seenClassIds))));
        $yearWhere = trim((string)$year) !== ''
            ? " AND (anno_scolastico = " . dbQ(mastercomAdminCleanText($year)) . " OR anno_scolastico IS NULL OR anno_scolastico = '')"
            : '';

        $classesToDeactivate = dbGetAll("
            SELECT *
            FROM mastercom_classi
            WHERE COALESCE(attiva_mastercom, 1) = 1
              AND mastercom_id_classe NOT IN ($seenSql)
              $yearWhere
        ") ?: [];

        $deactivated = intval(dbGetValue("
            SELECT COUNT(*)
            FROM mastercom_classi
            WHERE COALESCE(attiva_mastercom, 1) = 1
              AND mastercom_id_classe NOT IN ($seenSql)
              $yearWhere
        ") ?? 0);

        if ($deactivated > 0) {
            dbExec("
                UPDATE mastercom_classi
                SET attiva_mastercom = 0,
                    last_sync_at = " . dbQ(mastercomAdminNow()) . "
                WHERE COALESCE(attiva_mastercom, 1) = 1
                  AND mastercom_id_classe NOT IN ($seenSql)
                  $yearWhere
            ");

            if (mastercomAdminTableExists('mastercom_carenze')) {
                dbExec("
                    DELETE mc
                    FROM mastercom_carenze mc
                    INNER JOIN mastercom_classi c
                        ON c.mastercom_id_classe = mc.mastercom_id_classe
                    WHERE COALESCE(c.attiva_mastercom, 0) = 0
                ");
            }

            if (mastercomAdminTableExists('carenze') && mastercomAdminTableExists('mastercom_carenze')) {
                $localClassIds = [];
                foreach ($classesToDeactivate as $classToDeactivate) {
                    $localClassId = intval($classToDeactivate['id_classe_gestore'] ?? 0);
                    if ($localClassId <= 0) {
                        $localClass = mastercomAdminResolveLocalClass($classToDeactivate);
                        $localClassId = intval($localClass['id'] ?? 0);
                    }
                    if ($localClassId > 0) {
                        $localClassIds[$localClassId] = true;
                    }
                }

                if (!empty($localClassIds)) {
                    $localClassIdsSql = implode(',', array_map('intval', array_keys($localClassIds)));
                    dbExec("
                        DELETE c
                        FROM carenze c
                        WHERE c.mastercom_last_sync_at IS NOT NULL
                          AND c.id_classe IN ($localClassIdsSql)
                          AND NOT EXISTS (
                              SELECT 1
                              FROM mastercom_carenze mc
                              WHERE mc.id_studente_gestore = c.id_studente
                                AND mc.id_materia_gestore = c.id_materia
                                AND mc.id_classe_gestore = c.id_classe
                                AND mc.id_anno_scolastico = c.id_anno_scolastico
                              LIMIT 1
                          )
                    ");
                }
            }
        }
    }

    return ['ok' => true, 'message' => "Classi sincronizzate: $updated, disattivate: $deactivated"];
}

function mastercomAdminSyncStudentsForClass(int $classId, callable $progress = null): array
{
    if (!mastercomAdminIsOperationalClassId($classId)) {
        return ['ok' => false, 'message' => 'Classe MasterCom non collegata a GestOre: ignorata nella sincronizzazione studenti'];
    }

    $loadResult = mastercomAdminLoadStudentsListForClass($classId);
    if (!$loadResult['ok']) {
        return $loadResult;
    }

    $supplementalResult = mastercomAdminBuildStudentSupplementalMapForClass($classId);
    if (!$supplementalResult['ok']) {
        warning('MasterCom export CSV studenti supplementare non disponibile per classe ' . $classId . ': ' . ($supplementalResult['message'] ?? ''));
    }
    $supplementalMap = $supplementalResult['ok'] ? ($supplementalResult['map'] ?? []) : [];

    return mastercomAdminSyncStudentsChunk($classId, $loadResult['records'], 0, count($loadResult['records']), $progress, $supplementalMap);
}

function mastercomAdminSyncStudentsChunk(int $classId, array $masterStudents, int $baseOffset = 0, int $overallTotal = 0, callable $progress = null, array $supplementalMap = []): array
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
    if (!mastercomAdminIsOperationalClassId($classId)) {
        return ['ok' => false, 'message' => 'Classe MasterCom non collegata a GestOre: ignorata nella sincronizzazione studenti'];
    }
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
        try {
            $detailResult = mastercomLoadStudentDetails($authResult, $studentId, [
                'method' => 'GET',
                'timeout' => 120,
            ]);

            if (!$detailResult['ok']) {
                $rawPreview = trim(substr((string)($detailResult['raw'] ?? ''), 0, 300));
                error(
                    mastercomAdminStudentSyncErrorMessage(
                        $classId,
                        $studentId,
                        $masterStudent,
                        'dettaglio MasterCom non valido'
                    ) . ' | http=' . intval($detailResult['http_code'] ?? 0)
                    . ' | error=' . trim((string)($detailResult['error'] ?? ''))
                    . ($rawPreview !== '' ? ' | raw=' . preg_replace('/\s+/', ' ', $rawPreview) : '')
                );
                $detail = [];
            } else {
                $detail = mastercomAdminFirstRecord($detailResult['response'] ?? null) ?? [];
            }

            $extraData = mastercomAdminFindStudentSupplementalData($supplementalMap, $masterStudent, $detail);
            $merged = array_merge($detail, $masterStudent, [
                '_csv_export' => $extraData,
            ]);
            $codiceFiscale = mastercomAdminCleanText($detail['cf'] ?? null);
            $studentSesso = gestoreSessoDaInputOCodiceFiscale(
                $extraData['sesso'] ?? $masterStudent['sesso'] ?? $detail['sesso'] ?? $detail['gender'] ?? null,
                $codiceFiscale
            );
            $localStudent = mastercomAdminFindLocalStudent([
                'codice_fiscale' => $codiceFiscale ?? '',
                'email1' => $masterStudent['email1'] ?? $detail['email'] ?? '',
                'cognome' => $masterStudent['cognome'] ?? $detail['surname'] ?? '',
                'nome' => $masterStudent['nome'] ?? $detail['first_name'] ?? '',
                'mastercom_id_classe_corrente' => $classId,
                'classe_numero' => $masterStudent['classe'] ?? null,
                'sezione' => $masterStudent['sezione'] ?? null,
            ]);

            $studentData = [
                'id_studente_gestore' => $localStudent['id'] ?? null,
                'mastercom_id_studente' => $studentId,
                'mastercom_id_classe_corrente' => $classId,
                'registro_numero' => isset($masterStudent['registro']) ? intval($masterStudent['registro']) : null,
                'cognome' => mastercomAdminCleanText($masterStudent['cognome'] ?? $detail['surname'] ?? null),
                'nome' => mastercomAdminCleanText($masterStudent['nome'] ?? $detail['first_name'] ?? null),
                'codice_fiscale' => $codiceFiscale,
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
                'esonero_religione' => array_key_exists('esonero_religione', $masterStudent)
                    ? mastercomAdminMapReligionExemptionValue($masterStudent['esonero_religione'])
                    : ($extraData['esonero_religione'] ?? null),
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
            ];
            if (mastercomAdminTableColumnExists('mastercom_studenti', 'descrizione_materia_integrativa')) {
                $studentData['descrizione_materia_integrativa'] = mastercomAdminCleanText($extraData['descrizione_materia_integrativa'] ?? null);
            }
            if (mastercomAdminTableColumnExists('mastercom_studenti', 'sesso')) {
                $studentData['sesso'] = $studentSesso;
            }

            mastercomAdminUpsertByField('mastercom_studenti', 'mastercom_id_studente', $studentId, $studentData);

            if (!empty($localStudent['id'])) {
                gestoreEnsureStudenteSessoColumn();
                dbExec("
                    UPDATE studente
                    SET sesso = COALESCE(" . dbQ($studentSesso) . ", sesso)
                    WHERE id = " . intval($localStudent['id']) . "
                ");
            }

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
        } catch (Throwable $e) {
            $errorMessage = mastercomAdminStudentSyncErrorMessage($classId, $studentId, $masterStudent, $e->getMessage());
            error($errorMessage);
            return ['ok' => false, 'message' => $errorMessage];
        }

        $updated++;
    }

    return ['ok' => true, 'message' => "Studenti sincronizzati per classe $classId: $updated"];
}

function mastercomAdminSyncStudentsForAllClasses(callable $progress = null): array
{
    $classIds = array_map(function ($row) {
        return intval($row['mastercom_id_classe'] ?? 0);
    }, mastercomAdminOperationalClassRows('mastercom_id_classe'));
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
    $studentAdminParentsCache = [];
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
        $htmlDetail = mastercomAdminParentDetailsFromLinkedStudents($authResult, $parent, $parentId, $studentAdminParentsCache);
        $merged = array_merge($detail, $parent, $htmlDetail);
        $parentSources = [$htmlDetail, $detail, $parent, $merged];
        $codiceFiscale = mastercomAdminPickMastercomValue($parentSources, ['codice_fiscale', 'codicefiscale', 'cf', 'fiscal_code', 'tax_code']);
        $email = mastercomAdminPickMastercomValue($parentSources, ['email', 'mail', 'e_mail', 'indirizzo_email', 'email1']);
        $cognome = mastercomAdminPickMastercomValue($parentSources, ['cognome', 'surname', 'last_name', 'lastname']);
        $nome = mastercomAdminPickMastercomValue($parentSources, ['nome', 'first_name', 'firstname']);
        $telefono = mastercomAdminPickMastercomValue($parentSources, [
            'telephone',
            'telefono',
            'phone',
            'tel',
            'telefono_fisso',
            'telefono_residenza',
            'numero_telefono',
            'recapito_telefonico',
        ]);
        $cellulare = mastercomAdminPickMastercomValue($parentSources, [
            'cellphone',
            'cellulare',
            'mobile',
            'cell',
            'telefono_cellulare',
            'telefono_mobile',
            'mobile_phone',
            'cellulare1',
            'cell1',
        ]);
        $indirizzo = mastercomAdminPickMastercomValue($parentSources, [
            'address',
            'indirizzo',
            'via',
            'indirizzo_residenza',
            'residenza_indirizzo',
            'indirizzo_domicilio',
            'domicilio_indirizzo',
            'street',
            'indirizzo_completo',
        ]);
        $cap = mastercomAdminPickMastercomValue($parentSources, [
            'postal_code',
            'postalcode',
            'cap',
            'zip',
            'zipcode',
            'codice_postale',
            'cap_residenza',
            'residenza_cap',
        ]);
        $citta = mastercomAdminPickMastercomValue($parentSources, [
            'city',
            'citta',
            'città',
            'comune_residenza',
            'residenza_comune',
            'localita',
            'località',
        ]);
        $provincia = mastercomAdminPickMastercomValue($parentSources, [
            'province',
            'provincia',
            'prov',
            'sigla_provincia',
            'provincia_residenza',
            'residenza_provincia',
        ]);
        $comuneNascita = mastercomAdminPickMastercomValue($parentSources, [
            'birth_place',
            'birthplace',
            'comune_nascita',
            'luogo_nascita',
            'nato_a',
        ]);
        $birthTs = mastercomAdminPickMastercomTimestamp($parentSources, [
            'birth_date',
            'birthdate',
            'data_nascita',
            'nato_il',
        ]);

        $localParent = mastercomAdminFindLocalParent([
            'codice_fiscale' => $codiceFiscale ?? '',
            'email' => $email ?? '',
            'cognome' => $cognome ?? '',
            'nome' => $nome ?? '',
        ]);

        mastercomAdminUpsertByField('mastercom_genitori', 'mastercom_id_parente', $parentId, [
            'id_genitore_gestore' => $localParent['id'] ?? null,
            'mastercom_id_parente' => $parentId,
            'cognome' => $cognome,
            'nome' => $nome,
            'codice_fiscale' => $codiceFiscale,
            'email' => $email,
            'telefono' => $telefono,
            'cellulare' => $cellulare,
            'indirizzo' => $indirizzo,
            'cap' => $cap,
            'citta' => $citta,
            'provincia' => $provincia,
            'comune_nascita' => $comuneNascita,
            'data_nascita_ts' => $birthTs,
            'data_nascita' => $birthTs !== null ? date('Y-m-d', $birthTs) : null,
            'attivo_mastercom' => 1,
            'last_sync_at' => mastercomAdminNow(),
            'last_seen_at' => mastercomAdminNow(),
            'raw_json' => mastercomAdminJson([
                'detail' => $detail,
                'list' => $parent,
                'student_admin_parent_detail' => $htmlDetail,
                'merged' => $merged,
            ]),
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

function mastercomAdminRebuildParentStudentLinks(callable $progress = null): array
{
    $parentRows = dbGetAll("SELECT * FROM mastercom_genitori ORDER BY cognome ASC, nome ASC, id ASC");
    if (empty($parentRows)) {
        return ['ok' => false, 'message' => 'Nessun genitore MasterCom disponibile. Sincronizza prima i genitori.'];
    }

    $total = count($parentRows);
    $processed = 0;
    $linked = 0;
    $skippedMissingStudents = 0;
    $parentsWithoutChildren = 0;

    foreach ($parentRows as $parentRow) {
        $processed++;
        $parentId = intval($parentRow['mastercom_id_parente'] ?? 0);
        $label = trim((string)(($parentRow['cognome'] ?? '') . ' ' . ($parentRow['nome'] ?? '')));
        if ($label === '') {
            $label = 'genitore ' . $parentId;
        }

        mastercomAdminProgress($progress, 'parents', $processed, $total, 'Ricalcolo collegamenti ' . $label);

        if ($parentId <= 0) {
            continue;
        }

        $decoded = json_decode((string)($parentRow['raw_json'] ?? ''), true);
        if (!is_array($decoded)) {
            continue;
        }

        $children = $decoded['studenti_abbinati'] ?? [];
        if (!is_array($children) || empty($children)) {
            $parentsWithoutChildren++;
            continue;
        }

        $localParent = mastercomAdminResolveLocalParent($parentRow);
        $localParentId = intval($localParent['id'] ?? 0);
        if ($localParentId > 0 && intval($parentRow['id_genitore_gestore'] ?? 0) !== $localParentId) {
            dbExec("
                UPDATE mastercom_genitori
                SET
                    id_genitore_gestore = " . $localParentId . ",
                    last_sync_at = " . dbQ(mastercomAdminNow()) . "
                WHERE id = " . intval($parentRow['id'])
            );
        }

        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $studentMcId = intval($child['id_studente'] ?? 0);
            if ($studentMcId <= 0) {
                continue;
            }

            $studentMirror = dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . $studentMcId . " LIMIT 1");
            if ($studentMirror == null) {
                $skippedMissingStudents++;
                continue;
            }

            $existingLinkId = dbGetValue("SELECT id FROM mastercom_genitori_studenti WHERE mastercom_id_parente = " . $parentId . " AND mastercom_id_studente = " . $studentMcId . " LIMIT 1");
            $studentGestoreId = intval($studentMirror['id_studente_gestore'] ?? 0);

            if ($existingLinkId !== null) {
                mastercomAdminExec("
                    UPDATE mastercom_genitori_studenti
                    SET
                        id_genitore_gestore = " . dbI($localParentId > 0 ? $localParentId : null) . ",
                        id_studente_gestore = " . dbI($studentGestoreId > 0 ? $studentGestoreId : null) . ",
                        source_mastercom = 'mastercom',
                        last_sync_at = " . dbQ(mastercomAdminNow()) . ",
                        raw_json = " . dbQ(mastercomAdminJson($child)) . "
                    WHERE id = " . intval($existingLinkId),
                    'rebuild parent link update parent_id=' . $parentId . ' student_id=' . $studentMcId
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
                        " . $parentId . ",
                        " . $studentMcId . ",
                        " . dbI($localParentId > 0 ? $localParentId : null) . ",
                        " . dbI($studentGestoreId > 0 ? $studentGestoreId : null) . ",
                        'mastercom',
                        " . dbQ(mastercomAdminNow()) . ",
                        " . dbQ(mastercomAdminJson($child)) . "
                    )",
                    'rebuild parent link insert parent_id=' . $parentId . ' student_id=' . $studentMcId
                );
            }

            $linked++;
        }
    }

    return [
        'ok' => true,
        'message' => 'Collegamenti ricalcolati: ' . $linked
            . ' link aggiornati/inseriti, '
            . $skippedMissingStudents . ' link saltati per studenti MasterCom assenti, '
            . $parentsWithoutChildren . ' genitori senza figli nel dato MasterCom',
    ];
}

function mastercomAdminStudentDiffs(array $mirrorRow): array
{
    $local = mastercomAdminResolveLocalStudent($mirrorRow);
    $expectedActive = mastercomAdminStudentExpectedActive($mirrorRow);

    $diffs = [];
    if ($local == null) {
        if ($expectedActive === 1) {
            $diffs['studente_gestore'] = 'non collegato';
        }
        return ['local' => null, 'diffs' => $diffs];
    }

    if (intval($local['attivo'] ?? 0) !== $expectedActive) {
        $diffs['attivo'] = [
            'gestore' => intval($local['attivo'] ?? 0) === 1 ? 'attivo' : 'non attivo',
            'mastercom' => $expectedActive === 1 ? 'deve essere attivo' : 'ritirato: deve essere disattivato',
        ];
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
    $expectedClassId = mastercomAdminExpectedLocalClassId($mirrorRow);
    if ($expectedClassId !== null && intval($local['id_classe_corrente'] ?? 0) !== $expectedClassId) {
        $expectedClass = dbGetValue("SELECT classe FROM classi WHERE id = " . intval($expectedClassId) . " LIMIT 1");
        $diffs['classe'] = ['gestore' => $local['classe_corrente'] ?? '', 'mastercom' => $expectedClass ?? ($mirrorRow['classe_mastercom'] ?? '')];
    }

    return ['local' => $local, 'diffs' => $diffs];
}

function mastercomAdminParentDiffs(array $mirrorRow): array
{
    $local = mastercomAdminResolveLocalParent($mirrorRow);
    $expectedActive = intval($mirrorRow['expected_gestore_attivo'] ?? 1);

    $diffs = [];
    if ($local == null) {
        if ($expectedActive === 1) {
            $diffs['genitore_gestore'] = 'non collegato';
        }
        return ['local' => null, 'diffs' => $diffs];
    }

    if (intval($local['attivo'] ?? 0) !== $expectedActive) {
        $diffs['attivo'] = [
            'gestore' => intval($local['attivo'] ?? 0) === 1 ? 'attivo' : 'non attivo',
            'mastercom' => $expectedActive === 1 ? 'deve essere attivo' : 'solo figli ritirati: deve essere disattivato',
        ];
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

    if ($local == null && $count === 0) {
        return [
            'key' => 'aligned',
            'label' => 'assente in GestOre (ok)',
            'class' => 'success',
        ];
    }

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
    if ($filter === 'active_mismatch') {
        return isset($compareResult['diffs']['attivo']);
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

    $local = mastercomAdminResolveLocalStudent($mirror);

    if ($local == null) {
        return ['ok' => false, 'message' => 'Studente GestOre non trovato'];
    }

    gestoreEnsureStudenteSessoColumn();
    $sesso = gestoreSessoDaInputOCodiceFiscale($mirror['sesso'] ?? null, $mirror['codice_fiscale'] ?? '');
    dbExec("
        UPDATE studente
        SET
            cognome = " . dbQ($mirror['cognome']) . ",
            nome = " . dbQ($mirror['nome']) . ",
            email = " . dbQ($mirror['email1']) . ",
            codice_fiscale = " . dbQ($mirror['codice_fiscale']) . ",
            sesso = COALESCE(" . dbQ($sesso) . ", sesso)
        WHERE id = " . intval($local['id'])
    );

    $classUpdateMessage = 'classe GestOre non aggiornata';
    if (!empty($mirror['mastercom_id_classe_corrente'])) {
        $classMirror = dbGetFirst("SELECT * FROM mastercom_classi WHERE mastercom_id_classe = " . intval($mirror['mastercom_id_classe_corrente']) . " LIMIT 1");
        $localClassId = intval($classMirror['id_classe_gestore'] ?? 0);
        if ($localClassId <= 0) {
            $localClassId = intval(mastercomAdminExpectedLocalClassId($mirror) ?? 0);
        }
        if ($localClassId > 0) {
            $oldClassLabel = trim((string)($local['classe_corrente'] ?? ''));
            $newClassLabel = trim((string)(dbGetValue("SELECT classe FROM classi WHERE id = " . $localClassId . " LIMIT 1") ?? ''));

            $freqIds = dbGetAllValues("
                SELECT id
                FROM studente_frequenta
                WHERE id_studente = " . intval($local['id']) . "
                  AND id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
            ");

            if (!empty($freqIds)) {
                foreach ($freqIds as $freqId) {
                    dbExec("UPDATE studente_frequenta SET id_classe = " . $localClassId . " WHERE id = " . intval($freqId));
                }
            } else {
                dbExec("
                    INSERT INTO studente_frequenta (id_studente, id_classe, id_anno_scolastico)
                    VALUES (" . intval($local['id']) . ", " . $localClassId . ", " . intval($__anno_scolastico_corrente_id) . ")
                ");
            }

            if ($newClassLabel !== '') {
                $classUpdateMessage = 'classe GestOre aggiornata'
                    . ($oldClassLabel !== '' ? ' da ' . $oldClassLabel : '')
                    . ' a ' . $newClassLabel;
            } else {
                $classUpdateMessage = 'classe GestOre aggiornata';
            }
        } else {
            $classUpdateMessage = 'classe MasterCom non collegata a GestOre';
        }
    }

    dbExec("UPDATE mastercom_studenti SET id_studente_gestore = " . intval($local['id']) . " WHERE id = " . intval($mirror['id']));

    return ['ok' => true, 'message' => 'Studente GestOre allineato da MasterCom | ' . $classUpdateMessage];
}

function mastercomAdminCreateGestoreStudentFromMastercom(int $mastercomStudentId, int $localClassId = 0): array
{
    global $__anno_scolastico_corrente_id;

    $mirror = dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . intval($mastercomStudentId) . " LIMIT 1");
    if ($mirror == null) {
        return ['ok' => false, 'message' => 'Studente MasterCom non trovato'];
    }

    $local = mastercomAdminResolveLocalStudent($mirror);
    if ($local != null) {
        return mastercomAdminAlignGestoreStudentFromMastercom($mastercomStudentId);
    }

    if ($localClassId <= 0) {
        $localClassId = intval(mastercomAdminExpectedLocalClassId($mirror) ?? 0);
    }
    if ($localClassId <= 0) {
        return ['ok' => false, 'needs_class' => true, 'message' => 'Classe GestOre non determinata: selezionare la classe di destinazione'];
    }

    $classExists = dbGetValue("SELECT id FROM classi WHERE id = " . intval($localClassId) . " LIMIT 1");
    if ($classExists === null) {
        return ['ok' => false, 'needs_class' => true, 'message' => 'Classe GestOre selezionata non valida'];
    }

    $cognome = mastercomAdminCleanText($mirror['cognome'] ?? '') ?? '';
    $nome = mastercomAdminCleanText($mirror['nome'] ?? '') ?? '';
    if ($cognome === '' || $nome === '') {
        return ['ok' => false, 'message' => 'Cognome e nome MasterCom non sufficienti per creare lo studente'];
    }

    $email = mastercomAdminCleanText($mirror['email1'] ?? '') ?? '';
    $codiceFiscale = mastercomAdminCleanText($mirror['codice_fiscale'] ?? '') ?? '';
    $sesso = gestoreSessoDaInputOCodiceFiscale($mirror['sesso'] ?? null, $codiceFiscale);
    $username = $email;
    gestoreEnsureStudenteSessoColumn();

    dbExec("
        INSERT INTO studente (cognome, nome, email, username, codice_fiscale, sesso, attivo)
        VALUES (" . dbQ($cognome) . ", " . dbQ($nome) . ", " . dbQ($email) . ", " . dbQ($username) . ", " . dbQ($codiceFiscale) . ", " . dbQ($sesso) . ", 1)
    ");
    $newStudentId = intval(dblastId());

    $existingFreqId = dbGetValue("
        SELECT id
        FROM studente_frequenta
        WHERE id_studente = " . intval($newStudentId) . "
          AND id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
        LIMIT 1
    ");
    if ($existingFreqId !== null) {
        dbExec("UPDATE studente_frequenta SET id_classe = " . intval($localClassId) . " WHERE id = " . intval($existingFreqId));
    } else {
        dbExec("
            INSERT INTO studente_frequenta (id_studente, id_anno_scolastico, id_classe)
            VALUES (" . intval($newStudentId) . ", " . intval($__anno_scolastico_corrente_id) . ", " . intval($localClassId) . ")
        ");
    }

    dbExec("
        UPDATE mastercom_studenti
        SET id_studente_gestore = " . intval($newStudentId) . ",
            last_sync_at = " . dbQ(mastercomAdminNow()) . "
        WHERE id = " . intval($mirror['id']) . "
    ");

    $classLabel = trim((string)(dbGetValue("SELECT classe FROM classi WHERE id = " . intval($localClassId) . " LIMIT 1") ?? ''));
    return ['ok' => true, 'message' => 'Studente creato in GestOre e collegato a MasterCom' . ($classLabel !== '' ? ' | classe ' . $classLabel : '')];
}

function mastercomAdminAlignMirrorStudentFromGestore(int $mastercomStudentId): array
{
    global $__anno_scolastico_corrente_id;

    $mirror = dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . intval($mastercomStudentId) . " LIMIT 1");
    if ($mirror == null) {
        return ['ok' => false, 'message' => 'Studente MasterCom non trovato'];
    }

    $local = mastercomAdminResolveLocalStudent($mirror);

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

    $sessoUpdate = mastercomAdminTableColumnExists('mastercom_studenti', 'sesso')
        ? "sesso = " . dbQ(gestoreSessoDaInputOCodiceFiscale($local['sesso'] ?? null, $local['codice_fiscale'] ?? '')) . ","
        : "";
    dbExec("
        UPDATE mastercom_studenti
        SET
            id_studente_gestore = " . intval($local['id']) . ",
            mastercom_id_classe_corrente = " . dbI($classMirrorId) . ",
            cognome = " . dbQ($local['cognome'] ?? '') . ",
            nome = " . dbQ($local['nome'] ?? '') . ",
            email1 = " . dbQ($local['email'] ?? '') . ",
            codice_fiscale = " . dbQ($local['codice_fiscale'] ?? '') . ",
            $sessoUpdate
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

<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/admin_lib.php';

function mastercomTagPrintTags(): array
{
    return [
        99 => 'ECC+ASL',
        100 => 'ECC+Orientamento',
        16 => 'Orientamento',
        25 => 'PCTO+Orientamento',
        60 => 'Attività_Recupero+CLIL',
        2 => 'PCTO',
        6 => 'Ore CLIL',
        7 => 'Ore ECC con CLIL',
        1 => 'Ed. Civica',
    ];
}

function mastercomTagPrintSchoolYearRange(): array
{
    $year = mastercomAdminCurrentSchoolYear() ?? '';
    if (preg_match('/(\d{4}).*?(\d{4})/', $year, $matches)) {
        return [
            'start' => $matches[1] . '-09-01',
            'end' => $matches[2] . '-08-31',
        ];
    }

    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
    $startYear = intval($now->format('n')) >= 9 ? intval($now->format('Y')) : intval($now->format('Y')) - 1;
    return [
        'start' => $startYear . '-09-01',
        'end' => ($startYear + 1) . '-08-31',
    ];
}

function mastercomTagPrintToday(): string
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
}

function mastercomTagPrintValidDate(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Rome'));
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function mastercomTagPrintDateParts(string $value): array
{
    $date = DateTime::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Rome'));
    return [
        'Day' => $date->format('d'),
        'Month' => $date->format('m'),
        'Year' => $date->format('Y'),
    ];
}

function mastercomTagPrintClassRowsForUser(int $docenteId, bool $adminMode): array
{
    global $__anno_scolastico_corrente_id;

    if (!mastercomAdminTableExists('mastercom_classi')) {
        return [];
    }

    if ($adminMode) {
        $rows = mastercomAdminOperationalClassRows('*');
        return array_map(function ($row) {
            return [
                'mastercom_id_classe' => intval($row['mastercom_id_classe'] ?? 0),
                'nome' => trim((string)($row['nome'] ?? '')),
                'gestore_classe' => trim((string)(mastercomAdminResolveLocalClass($row)['classe'] ?? '')),
            ];
        }, $rows);
    }

    if ($docenteId <= 0 || !mastercomAdminTableExists('docente_insegna')) {
        return [];
    }

    $annoId = intval($__anno_scolastico_corrente_id ?? 0);
    $localClassIds = array_map('intval', dbGetAllValues("
        SELECT DISTINCT di.id_classe
        FROM docente_insegna di
        WHERE di.id_docente = " . dbI($docenteId) . "
          AND di.id_anno_scolastico = " . dbI($annoId) . "
    ") ?: []);

    if (empty($localClassIds)) {
        return [];
    }

    $localClassSet = array_fill_keys($localClassIds, true);
    $rows = [];
    foreach (mastercomAdminOperationalClassRows('*') as $row) {
        $localClass = mastercomAdminResolveLocalClass($row);
        $localClassId = intval($localClass['id'] ?? 0);
        if ($localClassId <= 0 || !isset($localClassSet[$localClassId])) {
            continue;
        }

        $rows[] = [
            'mastercom_id_classe' => intval($row['mastercom_id_classe'] ?? 0),
            'nome' => trim((string)($row['nome'] ?? '')),
            'gestore_classe' => trim((string)($localClass['classe'] ?? '')),
        ];
    }

    usort($rows, function ($a, $b) {
        return strcmp((string)($a['gestore_classe'] ?? ''), (string)($b['gestore_classe'] ?? ''));
    });

    return $rows;
}

function mastercomTagPrintClassMap(array $classRows): array
{
    $map = [];
    foreach ($classRows as $row) {
        $classId = intval($row['mastercom_id_classe'] ?? 0);
        if ($classId > 0) {
            $map[$classId] = $row;
        }
    }
    return $map;
}

function mastercomTagPrintExport(string $startDate, string $endDate, array $tagIds, array $classIds): array
{
    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$authResult['ok']) {
        return ['ok' => false, 'message' => 'Autenticazione admin MasterCom fallita'];
    }

    $start = mastercomTagPrintDateParts($startDate);
    $end = mastercomTagPrintDateParts($endDate);
    $payload = [
        'stato_secondario' => 'stampa_elenchi_particolari_update',
        'form_stato' => 'amministratore',
        'stato_principale' => 'stampe_principale',
        'tipo_stampa' => 'elenco_tag',
        'data_inizio_Day' => $start['Day'],
        'data_inizio_Month' => $start['Month'],
        'data_inizio_Year' => $start['Year'],
        'data_fine_Day' => $end['Day'],
        'data_fine_Month' => $end['Month'],
        'data_fine_Year' => $end['Year'],
        'tag_docente' => 'TUTTI',
        'stampa_tag[]' => array_values($tagIds),
        'mat_classi[]' => array_values($classIds),
        'bottone.x' => 26,
        'bottone.y' => 11,
    ];

    $submitResult = mastercomSubmitAdminAbsenceAction($authResult, $payload, [
        'base_url' => mastercomIndexUrl(),
        'method' => 'POST',
        'timeout' => 180,
        'send_in_body' => true,
    ]);

    $body = (string)($submitResult['body'] ?? '');
    if (!$submitResult['ok'] || $body === '') {
        return [
            'ok' => false,
            'message' => 'Esportazione tag MasterCom fallita',
            'http_code' => intval($submitResult['http_code'] ?? 0),
            'preview' => trim(strip_tags(substr($body, 0, 500))),
        ];
    }

    if (preg_match("/tmp_xls\/[^'\"<>]+\.(xls|xlsx|csv)/i", $body, $matches)) {
        $relativePath = $matches[0];
        $downloadUrl = rtrim(dirname(mastercomBaseUrl()), '/') . '/' . ltrim($relativePath, '/');
        $downloadResult = mastercomRawRequest([], [
            'base_url' => $downloadUrl,
            'cookie' => implode('; ', array_filter($authResult['cookies'] ?? [])),
            'method' => 'GET',
            'timeout' => 180,
        ]);

        if (!empty($downloadResult['ok']) && trim((string)($downloadResult['body'] ?? '')) !== '') {
            $body = (string)$downloadResult['body'];
            $submitResult['content_type'] = $downloadResult['content_type'] ?? ($submitResult['content_type'] ?? '');
        }
    }

    if (!empty($submitResult['html_warnings']) || preg_match('/<html|<form/i', $body)) {
        return [
            'ok' => false,
            'message' => 'MasterCom non ha restituito il file Excel della stampa TAG',
            'http_code' => intval($submitResult['http_code'] ?? 0),
            'preview' => trim(strip_tags(substr($body, 0, 500))),
        ];
    }

    return [
        'ok' => true,
        'body' => $body,
        'content_type' => trim((string)($submitResult['content_type'] ?? 'application/vnd.ms-excel')),
        'filename' => 'elenco_tag_' . date('Y-m-d_H-i-s') . '.xls',
    ];
}

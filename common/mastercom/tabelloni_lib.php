<?php

/**
 * MasterCom tabelloni scrutini integration.
 *
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/admin_lib.php';
require_once __DIR__ . '/debts_lib.php';
require_once __DIR__ . '/tag_print_lib.php';

function mastercomTabelloniEnsureTables(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_tabelloni_scrutini` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `mastercom_id_classe` INT NOT NULL,
            `id_classe_gestore` INT NULL,
            `classe` VARCHAR(100) NULL,
            `classe_tabellone` VARCHAR(255) NULL,
            `anno_label` VARCHAR(20) NOT NULL,
            `id_anno_scolastico` INT NULL,
            `periodo` VARCHAR(50) NOT NULL,
            `periodo_label` VARCHAR(100) NULL,
            `data_finale` DATE NULL,
            `source_hash` CHAR(40) NOT NULL,
            `raw_xls` MEDIUMTEXT NULL,
            `raw_json` MEDIUMTEXT NULL,
            `imported_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mastercom_tabellone_scrutinio` (`mastercom_id_classe`, `anno_label`, `periodo`),
            KEY `idx_mastercom_tabelloni_classe` (`mastercom_id_classe`),
            KEY `idx_mastercom_tabelloni_anno` (`id_anno_scolastico`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_tabelloni_scrutini_colonne` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `tabellone_id` INT NOT NULL,
            `col_index` INT NOT NULL,
            `codice` VARCHAR(100) NOT NULL,
            `descrizione` VARCHAR(255) NULL,
            `tipo` VARCHAR(50) NULL,
            `raw_json` MEDIUMTEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mastercom_tabellone_colonna` (`tabellone_id`, `col_index`),
            KEY `idx_mastercom_tabellone_colonna_codice` (`codice`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_tabelloni_scrutini_studenti` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `tabellone_id` INT NOT NULL,
            `row_index` INT NOT NULL,
            `numero` INT NULL,
            `mastercom_id_studente` INT NULL,
            `id_studente_gestore` INT NULL,
            `studente_nome` VARCHAR(255) NOT NULL,
            `media` DECIMAL(5,2) NULL,
            `crediti_3` INT NULL,
            `crediti_4` INT NULL,
            `crediti_5` INT NULL,
            `crediti_totale` INT NULL,
            `risultato` VARCHAR(255) NULL,
            `raw_json` MEDIUMTEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mastercom_tabellone_studente` (`tabellone_id`, `row_index`),
            KEY `idx_mastercom_tabellone_studente_gestore` (`id_studente_gestore`),
            KEY `idx_mastercom_tabellone_studente_mastercom` (`mastercom_id_studente`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS `mastercom_tabelloni_scrutini_voti` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `tabellone_id` INT NOT NULL,
            `tabellone_studente_id` INT NOT NULL,
            `col_index` INT NOT NULL,
            `materia_codice` VARCHAR(100) NOT NULL,
            `materia_descrizione` VARCHAR(255) NULL,
            `tipo_colonna` VARCHAR(50) NULL,
            `valore` VARCHAR(100) NULL,
            `valore_num` DECIMAL(5,2) NULL,
            `raw_value` VARCHAR(100) NULL,
            `insufficiente` TINYINT(1) NOT NULL DEFAULT 0,
            `raw_json` MEDIUMTEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mastercom_tabellone_voto` (`tabellone_studente_id`, `col_index`),
            KEY `idx_mastercom_tabellone_voti_tabellone` (`tabellone_id`),
            KEY `idx_mastercom_tabellone_voti_materia` (`materia_codice`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function mastercomTabelloniSchoolYears(): array
{
    return dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY anno DESC") ?: [];
}

function mastercomTabelloniClassNameById(int $mastercomClassId): string
{
    return mastercomDebtsClassNameById($mastercomClassId);
}

function mastercomTabelloniLocalClassIdByMastercom(int $mastercomClassId, string $className): ?int
{
    return mastercomDebtsLocalClassIdByMastercom($mastercomClassId, $className);
}

function mastercomTabelloniResolveSchoolYearId(string $yearLabel): ?int
{
    return mastercomDebtsResolveSchoolYearId($yearLabel);
}

function mastercomTabelloniDefaultParams(): array
{
    return [
        'param_tabellone_periodo' => '9',
        'param_tabellone_esposizione_archivio' => 'archivio',
        'param_tabellone_codice_descrizione' => 'C',
        'param_tabellone_verticale_orizzontale' => 'VERT',
        'param_tabellone_formato_pagina' => 'A3',
        'param_tabellone_dimensione_font_materie' => '12',
        'param_tabellone_dimensione_font_nomi' => '12',
        'param_tabellone_dimensione_font' => '12',
        'param_tabellone_valore_margine_cella_voti' => '0.2',
        'param_tabellone_stampa_voti_in_rosso' => 'SI',
        'param_tabellone_orientamento_pagina' => 'P',
        'param_tabellone_tipo_file_esportato' => 'xls',
        'param_tabellone_celle_colorate' => 'NO',
        'param_tabellone_stampa_medie_provenienza' => 'NO',
        'param_tabellone_visualizza_assenze' => 'NO',
        'param_tabellone_stampa_medie' => 'SI',
        'param_tabellone_stampa_crediti' => 'SI',
        'param_tabellone_stampa_crediti_totali' => 'SI',
        'param_tabellone_stampa_tutti_voti' => 'SI',
        'param_tabellone_stampa_tipo_assenze' => 'default',
        'param_tabellone_visualizza_studenti_senza_voti' => 'SI',
        'param_tabellone_visualizza_ritirati' => 'SI',
        'param_tabellone_tipo_voto_stampa' => 'scheda',
        'param_tabellone_stampo_debiti_sui_voti' => 'NO',
        'param_tabellone_ammesso_con_debito' => 'SI',
        'param_tabellone_stampo_religione' => 'SI',
        'param_tabellone_attiva_stampa_pei' => 'NO',
        'param_tabellone_stampa_debiti_4a' => 'NO',
        'param_tabellone_stampa_debiti_4b' => 'NO',
        'param_tabellone_stampa_risultato' => 'SI_CON_VALORI',
        'param_tabellone_stampa_crediti_anno_corrente' => 'NO',
        'param_tabellone_stampa_crediti_integrativi' => 'NO',
        'param_tabellone_visualizza_bocciati' => 'SI',
        'param_tabellone_visualizza_nomi_bocciati' => 'SI',
        'param_tabellone_visualizza_sospesi' => 'NO',
        'param_tabellone_visualizza_nomi_sospesi' => 'NO',
        'param_tabellone_visualizza_ammesso_con_insufficienze' => 'NO',
        'param_tabellone_stampa_tutti_studenti' => 'SI',
        'param_tabellone_parametro_debiti' => 'NO',
        'param_tabellone_stampa_annotazioni' => 'NO',
        'param_tabellone_stampa_firme' => 'NO',
        'param_tabellone_stampa_presenze' => 'NO',
        'param_tabellone_firme_materie_NV' => 'NO',
        'param_tabellone_visualizza_data' => 'SI_D_DS',
        'param_pagellina_firma_digitale' => 'NO',
        'param_pagellina_firma_omessa' => 'NO',
    ];
}

function mastercomTabelloniPeriodLabels(): array
{
    return [
        '1' => '1a pagellina infraquadrimestrale',
        '2' => '2a pagellina infraquadrimestrale',
        '3' => '3a pagellina infraquadrimestrale',
        '4' => '4a pagellina infraquadrimestrale',
        '5' => '5a pagellina infraquadrimestrale',
        '6' => '6a pagellina infraquadrimestrale',
        '7' => 'Pagella fine 1o quadrimestre/trimestre',
        '8' => 'Pagella fine 2o trimestre',
        '9' => 'Pagella fine anno',
    ];
}

function mastercomTabelloniPeriodLabel(string $period): string
{
    $labels = mastercomTabelloniPeriodLabels();
    return $labels[$period] ?? ('Tabellone ' . $period);
}

function mastercomTabelloniDebugPayload(array $payload): array
{
    $debug = $payload;
    if (isset($debug['current_key'])) {
        $debug['current_key'] = '[redatto]';
    }
    return $debug;
}

function mastercomTabelloniResponsePreview(string $body, int $maxLines = 12): string
{
    $text = mastercomTabelloniNormalizeText($body);
    $text = preg_replace("/current_key\s*=\s*'[^']*'/i", "current_key='[redatto]'", $text);
    $text = preg_replace('/("current_key"\s*:\s*")[^"]*(")/i', '$1[redatto]$2', $text);
    $text = preg_replace('/eyJ[A-Za-z0-9_.-]{40,}/', '[jwt redatto]', $text);
    $text = strip_tags($text);
    $lines = preg_split('/\n/', $text) ?: [];
    $lines = array_values(array_filter(array_map('mastercomTabelloniCleanCell', $lines), function ($line) {
        return $line !== '';
    }));
    return implode("\n", array_slice($lines, 0, $maxLines));
}

function mastercomTabelloniFetchClassXls(int $mastercomClassId, string $className = '', array $params = []): array
{
    if ($mastercomClassId <= 0) {
        return ['ok' => false, 'message' => 'Classe MasterCom non valida.', 'xls' => ''];
    }

    $auth = mastercomAuthenticateService([
        'profile' => 'MasterComAuth',
        'method' => 'POST',
        'timeout' => 120,
    ]);
    if (empty($auth['ok'])) {
        return ['ok' => false, 'message' => 'Autenticazione MasterCom fallita: ' . ($auth['error'] ?? ''), 'xls' => ''];
    }

    $currentUser = mastercomCurrentUser($auth);
    $currentKey = mastercomCurrentKey($auth);
    if ($currentUser === null || trim((string)$currentKey) === '') {
        return ['ok' => false, 'message' => 'Autenticazione MasterCom incompleta.', 'xls' => ''];
    }

    $className = $className !== '' ? $className : mastercomTabelloniClassNameById($mastercomClassId);
    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
    $payload = array_merge(mastercomTabelloniDefaultParams(), $params, [
        'data_finale_Day' => $params['data_finale_Day'] ?? $now->format('d'),
        'data_finale_Month' => $params['data_finale_Month'] ?? $now->format('m'),
        'data_finale_Year' => $params['data_finale_Year'] ?? $now->format('Y'),
        'form_stato' => 'amministratore',
        'stato_principale' => 'pagelle_principale',
        'stato_secondario' => 'stampa_tabellone_pagelline_update',
        'indirizzo' => '',
        'id_indirizzo' => '',
        'classe' => $className,
        'id_classe' => $mastercomClassId,
        'form_target' => 'blank',
        'current_user' => $currentUser,
        'current_key' => $currentKey,
    ]);

    $response = mastercomRawRequest($payload, [
        'base_url' => mastercomIndexUrl(),
        'method' => 'POST',
        'send_in_body' => true,
        'timeout' => 300,
        'cookie' => implode('; ', array_filter($auth['cookies'] ?? [])),
    ]);

    if (empty($response['ok'])) {
        return [
            'ok' => false,
            'message' => 'Lettura tabellone MasterCom fallita: ' . ($response['error'] ?? ''),
            'xls' => '',
            'debug' => [
                'payload' => mastercomTabelloniDebugPayload($payload),
                'http_code' => intval($response['http_code'] ?? 0),
                'content_type' => $response['content_type'] ?? null,
                'response_length' => strlen((string)($response['body'] ?? '')),
                'response_preview' => mastercomTabelloniResponsePreview((string)($response['body'] ?? '')),
            ],
        ];
    }

    $body = (string)($response['body'] ?? '');
    $debug = [
        'payload' => mastercomTabelloniDebugPayload($payload),
        'http_code' => intval($response['http_code'] ?? 0),
        'content_type' => $response['content_type'] ?? null,
        'response_length' => strlen($body),
        'response_preview' => mastercomTabelloniResponsePreview($body),
    ];

    $generatedPath = mastercomTagPrintExtractExportPath($body);
    if ($generatedPath !== null) {
        $downloadUrls = mastercomTagPrintBuildDownloadUrls($generatedPath);
        $download = mastercomTagPrintDownloadGeneratedFile($downloadUrls, $auth);
        $debug['generated_path'] = $generatedPath;
        $debug['download_url'] = $download['url'] ?? '';
        $debug['download_attempts'] = $download['attempts'] ?? [];

        if (!empty($download['ok'])) {
            $downloadResult = $download['result'] ?? [];
            $body = (string)($downloadResult['body'] ?? '');
            $debug['download_http_code'] = intval($downloadResult['http_code'] ?? 0);
            $debug['download_content_type'] = $downloadResult['content_type'] ?? null;
            $debug['download_response_length'] = strlen($body);
            $debug['download_response_preview'] = mastercomTabelloniResponsePreview($body);
        } else {
            $downloadResult = $download['result'] ?? [];
            return [
                'ok' => false,
                'message' => 'MasterCom ha generato il file XLS, ma GestOre non e riuscito a scaricarlo.',
                'xls' => $body,
                'debug' => array_merge($debug, [
                    'download_http_code' => intval($downloadResult['http_code'] ?? 0),
                    'download_content_type' => $downloadResult['content_type'] ?? null,
                    'download_response_length' => strlen((string)($downloadResult['body'] ?? '')),
                    'download_response_preview' => mastercomTabelloniResponsePreview((string)($downloadResult['body'] ?? '')),
                ]),
            ];
        }
    }

    if (preg_match('/<form[^>]+login|name=["\']form_user["\']|name=["\']form_password["\']/i', $body) === 1) {
        return [
            'ok' => false,
            'message' => 'MasterCom ha restituito la pagina di login invece del tabellone.',
            'xls' => $body,
            'debug' => $debug,
        ];
    }

    return [
        'ok' => true,
        'message' => 'Tabellone MasterCom letto.',
        'xls' => $body,
        'payload' => $payload,
        'content_type' => $response['content_type'] ?? null,
        'debug' => $debug,
    ];
}

function mastercomTabelloniNormalizeText(string $text): string
{
    if (!mb_check_encoding($text, 'UTF-8')) {
        $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
    }

    $text = str_replace("\xEF\xBB\xBF", '', $text);
    $text = str_replace("\xc2\xa0", ' ', $text);
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    return (string)$text;
}

function mastercomTabelloniCleanCell($value): string
{
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace("\xc2\xa0", ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim((string)$value);
}

function mastercomTabelloniDecimal($value): ?float
{
    $value = mastercomTabelloniCleanCell($value);
    if ($value === '' || !preg_match('/^-?\d+(?:[,.]\d+)?$/', $value)) {
        return null;
    }
    return floatval(str_replace(',', '.', $value));
}

function mastercomTabelloniInt($value): ?int
{
    $value = mastercomTabelloniCleanCell($value);
    return preg_match('/^-?\d+$/', $value) ? intval($value) : null;
}

function mastercomTabelloniColumnType(string $code, string $subHeader = ''): string
{
    $norm = mastercomAdminNorm($code);
    if ($norm === 'MEDIA') {
        return 'media';
    }
    if (strpos($norm, 'CREDITI') === 0) {
        return 'credito';
    }
    if ($norm === 'TOT') {
        return 'credito_totale';
    }
    if ($norm === 'RISULTATO') {
        return 'risultato';
    }
    return mastercomAdminNorm($subHeader) === 'VOTO' ? 'voto' : 'dato';
}

function mastercomTabelloniParseXls(string $xls, int $mastercomClassId = 0, string $className = ''): array
{
    $text = mastercomTabelloniNormalizeText($xls);
    $lines = preg_split('/\n/', $text) ?: [];
    $lines = array_values(array_filter($lines, function ($line) {
        return trim((string)$line) !== '';
    }));

    $parsed = [
        'mastercom_id_classe' => $mastercomClassId,
        'classe' => $className,
        'classe_tabellone' => '',
        'anno_label' => '',
        'periodo' => '9',
        'periodo_label' => mastercomTabelloniPeriodLabel('9'),
        'header_index' => -1,
        'columns' => [],
        'students' => [],
        'raw_lines' => $lines,
    ];

    foreach ($lines as $line) {
        $cleanLine = mastercomTabelloniCleanCell($line);
        if (preg_match('/TABELLONE.*--\s*(.+)$/iu', $cleanLine, $matches)) {
            $parsed['classe_tabellone'] = mastercomTabelloniCleanCell($matches[1]);
        }
        if (preg_match('/A\.S\.\s*(\d{4}\s*\/\s*\d{4})/iu', $cleanLine, $matches)) {
            $parsed['anno_label'] = str_replace(' ', '', $matches[1]);
        }
    }

    $headerIndex = -1;
    foreach ($lines as $index => $line) {
        $cells = array_map('mastercomTabelloniCleanCell', explode("\t", $line));
        $normCells = array_map('mastercomAdminNorm', $cells);
        if (in_array('NOMINATIVO', $normCells, true)) {
            $headerIndex = $index;
            break;
        }
    }

    if ($headerIndex < 0) {
        return $parsed;
    }
    $parsed['header_index'] = $headerIndex;

    $headers = array_map('mastercomTabelloniCleanCell', explode("\t", $lines[$headerIndex]));
    $subHeaders = isset($lines[$headerIndex + 1])
        ? array_map('mastercomTabelloniCleanCell', explode("\t", $lines[$headerIndex + 1]))
        : [];

    foreach ($headers as $colIndex => $header) {
        if ($colIndex < 2 || $header === '') {
            continue;
        }
        $type = mastercomTabelloniColumnType($header, $subHeaders[$colIndex] ?? '');
        $parsed['columns'][] = [
            'col_index' => $colIndex,
            'codice' => $header,
            'descrizione' => $header,
            'tipo' => $type,
            'sub_header' => $subHeaders[$colIndex] ?? '',
        ];
    }

    for ($i = $headerIndex + 2; $i < count($lines); $i++) {
        $cells = array_map('mastercomTabelloniCleanCell', explode("\t", $lines[$i]));
        if (count($cells) < 3 || !preg_match('/^\d+$/', $cells[0] ?? '')) {
            continue;
        }

        $student = [
            'row_index' => $i - $headerIndex - 1,
            'numero' => intval($cells[0]),
            'studente_nome' => $cells[1] ?? '',
            'media' => null,
            'crediti_3' => null,
            'crediti_4' => null,
            'crediti_5' => null,
            'crediti_totale' => null,
            'risultato' => '',
            'values' => [],
            'raw_cells' => $cells,
        ];

        foreach ($parsed['columns'] as $column) {
            $colIndex = intval($column['col_index']);
            $value = $cells[$colIndex] ?? '';
            $type = (string)$column['tipo'];
            if ($type === 'media') {
                $student['media'] = mastercomTabelloniDecimal($value);
            } elseif ($type === 'credito') {
                if (preg_match('/3/u', (string)$column['codice'])) {
                    $student['crediti_3'] = mastercomTabelloniInt($value);
                } elseif (preg_match('/4/u', (string)$column['codice'])) {
                    $student['crediti_4'] = mastercomTabelloniInt($value);
                } elseif (preg_match('/5/u', (string)$column['codice'])) {
                    $student['crediti_5'] = mastercomTabelloniInt($value);
                }
            } elseif ($type === 'credito_totale') {
                $student['crediti_totale'] = mastercomTabelloniInt($value);
            } elseif ($type === 'risultato') {
                $student['risultato'] = $value;
            }

            $numeric = mastercomTabelloniDecimal($value);
            $student['values'][] = [
                'col_index' => $colIndex,
                'materia_codice' => (string)$column['codice'],
                'materia_descrizione' => (string)($column['descrizione'] ?? $column['codice']),
                'tipo_colonna' => $type,
                'valore' => $value,
                'valore_num' => $numeric,
                'raw_value' => $value,
                'insufficiente' => $type === 'voto' && $numeric !== null && $numeric < 6 ? 1 : 0,
            ];
        }

        $parsed['students'][] = $student;
    }

    return $parsed;
}

function mastercomTabelloniStudentLookupMap(int $mastercomClassId, ?int $localClassId, ?int $schoolYearId = null): array
{
    $map = [];
    if (mastercomAdminTableExists('mastercom_studenti')) {
        $where = [];
        if ($mastercomClassId > 0) {
            $where[] = "mastercom_id_classe_corrente = " . dbI($mastercomClassId);
        }
        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $rows = dbGetAll("
            SELECT mastercom_id_studente, id_studente_gestore, cognome, nome
            FROM mastercom_studenti
            $whereSql
        ") ?: [];
        foreach ($rows as $row) {
            $label = trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
            $key = mastercomAdminNormCompact($label);
            if ($key !== '') {
                $map[$key] = [
                    'mastercom_id_studente' => intval($row['mastercom_id_studente'] ?? 0) ?: null,
                    'id_studente_gestore' => intval($row['id_studente_gestore'] ?? 0) ?: null,
                ];
            }
        }
    }

    if ($localClassId !== null && $localClassId > 0) {
        $yearWhere = $schoolYearId !== null && $schoolYearId > 0
            ? " AND sf.id_anno_scolastico = " . dbI($schoolYearId)
            : "";
        $rows = dbGetAll("
            SELECT s.id, s.cognome, s.nome
            FROM studente s
            INNER JOIN studente_frequenta sf ON sf.id_studente = s.id
            WHERE sf.id_classe = " . dbI($localClassId) . "
            $yearWhere
        ") ?: [];
        foreach ($rows as $row) {
            $key = mastercomAdminNormCompact(trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''))));
            if ($key !== '' && empty($map[$key]['id_studente_gestore'])) {
                $map[$key] = [
                    'mastercom_id_studente' => $map[$key]['mastercom_id_studente'] ?? null,
                    'id_studente_gestore' => intval($row['id'] ?? 0) ?: null,
                ];
            }
        }
    }

    return $map;
}

function mastercomTabelloniDeleteDetails(int $tabelloneId): void
{
    dbExec("DELETE FROM mastercom_tabelloni_scrutini_voti WHERE tabellone_id = " . dbI($tabelloneId));
    dbExec("DELETE FROM mastercom_tabelloni_scrutini_studenti WHERE tabellone_id = " . dbI($tabelloneId));
    dbExec("DELETE FROM mastercom_tabelloni_scrutini_colonne WHERE tabellone_id = " . dbI($tabelloneId));
}

function mastercomTabelloniSaveParsed(array $parsed, string $rawXls = ''): array
{
    mastercomTabelloniEnsureTables();

    $mastercomClassId = intval($parsed['mastercom_id_classe'] ?? 0);
    $className = trim((string)($parsed['classe'] ?? ''));
    $yearLabel = trim((string)($parsed['anno_label'] ?? ''));
    $period = trim((string)($parsed['periodo'] ?? '9'));
    if ($mastercomClassId <= 0 || $yearLabel === '' || $period === '') {
        return ['ok' => false, 'message' => 'Tabellone incompleto: classe, anno o periodo mancanti.', 'stats' => []];
    }

    $localClassId = mastercomTabelloniLocalClassIdByMastercom($mastercomClassId, $className);
    $yearId = mastercomTabelloniResolveSchoolYearId($yearLabel);
    $hash = sha1($rawXls !== '' ? $rawXls : mastercomAdminJson($parsed));

    dbExec("
        INSERT INTO mastercom_tabelloni_scrutini (
            mastercom_id_classe, id_classe_gestore, classe, classe_tabellone,
            anno_label, id_anno_scolastico, periodo, periodo_label,
            data_finale, source_hash, raw_xls, raw_json, imported_at
        ) VALUES (
            " . dbI($mastercomClassId) . ",
            " . dbI($localClassId) . ",
            " . dbQ($className) . ",
            " . dbQ($parsed['classe_tabellone'] ?? '') . ",
            " . dbQ($yearLabel) . ",
            " . dbI($yearId) . ",
            " . dbQ($period) . ",
            " . dbQ($parsed['periodo_label'] ?? '') . ",
            NULL,
            " . dbQ($hash) . ",
            " . dbQ($rawXls) . ",
            " . dbQ(mastercomAdminJson($parsed)) . ",
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            id_classe_gestore = VALUES(id_classe_gestore),
            classe = VALUES(classe),
            classe_tabellone = VALUES(classe_tabellone),
            id_anno_scolastico = VALUES(id_anno_scolastico),
            periodo_label = VALUES(periodo_label),
            source_hash = VALUES(source_hash),
            raw_xls = VALUES(raw_xls),
            raw_json = VALUES(raw_json),
            imported_at = NOW()
    ");

    $tabelloneId = intval(dbGetValue("
        SELECT id
        FROM mastercom_tabelloni_scrutini
        WHERE mastercom_id_classe = " . dbI($mastercomClassId) . "
          AND anno_label = " . dbQ($yearLabel) . "
          AND periodo = " . dbQ($period) . "
        LIMIT 1
    ") ?? 0);
    if ($tabelloneId <= 0) {
        return ['ok' => false, 'message' => 'Tabellone salvato ma non riletto dal database.', 'stats' => []];
    }

    mastercomTabelloniDeleteDetails($tabelloneId);
    foreach (($parsed['columns'] ?? []) as $column) {
        dbExec("
            INSERT INTO mastercom_tabelloni_scrutini_colonne (
                tabellone_id, col_index, codice, descrizione, tipo, raw_json
            ) VALUES (
                " . dbI($tabelloneId) . ",
                " . dbI($column['col_index'] ?? 0) . ",
                " . dbQ($column['codice'] ?? '') . ",
                " . dbQ($column['descrizione'] ?? '') . ",
                " . dbQ($column['tipo'] ?? '') . ",
                " . dbQ(mastercomAdminJson($column)) . "
            )
        ");
    }

    $studentMap = mastercomTabelloniStudentLookupMap($mastercomClassId, $localClassId, $yearId);
    $stats = [
        'columns' => count($parsed['columns'] ?? []),
        'students' => 0,
        'votes' => 0,
        'without_student' => 0,
    ];

    foreach (($parsed['students'] ?? []) as $student) {
        $studentName = (string)($student['studente_nome'] ?? '');
        $lookup = $studentMap[mastercomAdminNormCompact($studentName)] ?? [];
        $mastercomStudentId = intval($lookup['mastercom_id_studente'] ?? 0) ?: null;
        $localStudentId = intval($lookup['id_studente_gestore'] ?? 0) ?: null;
        if ($localStudentId === null) {
            $stats['without_student']++;
        }

        dbExec("
            INSERT INTO mastercom_tabelloni_scrutini_studenti (
                tabellone_id, row_index, numero, mastercom_id_studente, id_studente_gestore,
                studente_nome, media, crediti_3, crediti_4, crediti_5, crediti_totale,
                risultato, raw_json
            ) VALUES (
                " . dbI($tabelloneId) . ",
                " . dbI($student['row_index'] ?? 0) . ",
                " . dbI($student['numero'] ?? null) . ",
                " . dbI($mastercomStudentId) . ",
                " . dbI($localStudentId) . ",
                " . dbQ($studentName) . ",
                " . dbF($student['media'] ?? null) . ",
                " . dbI($student['crediti_3'] ?? null) . ",
                " . dbI($student['crediti_4'] ?? null) . ",
                " . dbI($student['crediti_5'] ?? null) . ",
                " . dbI($student['crediti_totale'] ?? null) . ",
                " . dbQ($student['risultato'] ?? '') . ",
                " . dbQ(mastercomAdminJson($student)) . "
            )
        ");

        $tabelloneStudentId = dblastId();
        foreach (($student['values'] ?? []) as $value) {
            dbExec("
                INSERT INTO mastercom_tabelloni_scrutini_voti (
                    tabellone_id, tabellone_studente_id, col_index, materia_codice,
                    materia_descrizione, tipo_colonna, valore, valore_num, raw_value,
                    insufficiente, raw_json
                ) VALUES (
                    " . dbI($tabelloneId) . ",
                    " . dbI($tabelloneStudentId) . ",
                    " . dbI($value['col_index'] ?? 0) . ",
                    " . dbQ($value['materia_codice'] ?? '') . ",
                    " . dbQ($value['materia_descrizione'] ?? '') . ",
                    " . dbQ($value['tipo_colonna'] ?? '') . ",
                    " . dbQ($value['valore'] ?? '') . ",
                    " . dbF($value['valore_num'] ?? null) . ",
                    " . dbQ($value['raw_value'] ?? '') . ",
                    " . dbI($value['insufficiente'] ?? 0) . ",
                    " . dbQ(mastercomAdminJson($value)) . "
                )
            ");
            $stats['votes']++;
        }
        $stats['students']++;
    }

    return [
        'ok' => true,
        'message' => 'Tabellone salvato: studenti ' . intval($stats['students']) . ', colonne ' . intval($stats['columns']) . '.',
        'tabellone_id' => $tabelloneId,
        'stats' => $stats,
    ];
}

function mastercomTabelloniFetchAndStoreClass(int $mastercomClassId, array $params = []): array
{
    $className = mastercomTabelloniClassNameById($mastercomClassId);
    $fetch = mastercomTabelloniFetchClassXls($mastercomClassId, $className, $params);
    if (empty($fetch['ok'])) {
        return ['ok' => false, 'message' => $fetch['message'] ?? 'Lettura MasterCom non riuscita.', 'stats' => []];
    }

    $parsed = mastercomTabelloniParseXls((string)$fetch['xls'], $mastercomClassId, $className);
    $period = trim((string)($params['param_tabellone_periodo'] ?? '9'));
    $parsed['periodo'] = $period !== '' ? $period : '9';
    $parsed['periodo_label'] = mastercomTabelloniPeriodLabel($parsed['periodo']);
    $debug = $fetch['debug'] ?? [];
    $debug['parse'] = [
        'anno_label' => $parsed['anno_label'] ?? '',
        'classe_tabellone' => $parsed['classe_tabellone'] ?? '',
        'header_index' => intval($parsed['header_index'] ?? -1),
        'columns' => count($parsed['columns'] ?? []),
        'students' => count($parsed['students'] ?? []),
        'raw_lines' => count($parsed['raw_lines'] ?? []),
    ];
    if (count($parsed['columns'] ?? []) === 0 || count($parsed['students'] ?? []) === 0) {
        return [
            'ok' => false,
            'message' => 'MasterCom ha risposto, ma non ho trovato righe studente nel tabellone.',
            'stats' => ['columns' => count($parsed['columns'] ?? []), 'students' => count($parsed['students'] ?? []), 'votes' => 0],
            'debug' => $debug,
        ];
    }
    $save = mastercomTabelloniSaveParsed($parsed, (string)$fetch['xls']);
    if (empty($save['ok'])) {
        $save['debug'] = $debug;
        return $save;
    }

    return [
        'ok' => true,
        'message' => 'Letto e salvato il tabellone ' . $className . ': ' . intval($save['stats']['students'] ?? 0) . ' studenti.',
        'stats' => $save['stats'],
        'tabellone_id' => $save['tabellone_id'] ?? 0,
        'debug' => $debug,
    ];
}

function mastercomTabelloniIsSeraleToken(string $token): bool
{
    $token = mastercomDebtsNormalizeClassToken($token);
    return $token !== '' && preg_match('/^\d+[A-Z0-9]*S$/u', $token) === 1;
}

function mastercomTabelloniIsSeraleClassRow(array $row): bool
{
    $texts = [
        $row['nome'] ?? '',
        $row['classe'] ?? '',
        $row['codice_indirizzo'] ?? '',
        $row['descrizione_indirizzo'] ?? '',
    ];
    foreach ($texts as $text) {
        if (strpos(mastercomAdminNorm((string)$text), 'SERALE') !== false) {
            return true;
        }
    }

    $components = mastercomDebtsClassComponentsFromName((string)($row['nome'] ?? ''));
    if (empty($components)) {
        $components = [mastercomDebtsNormalizeClassToken((string)($row['nome'] ?? ''))];
    }
    foreach ($components as $component) {
        if (mastercomTabelloniIsSeraleToken($component)) {
            return true;
        }
    }

    return false;
}

function mastercomTabelloniImportClassRows(string $fields = '*'): array
{
    $rows = mastercomAdminOperationalClassRows('*');
    $articulatedSkipMap = mastercomDebtsArticulatedClassSkipMap($rows);
    $filtered = [];

    foreach ($rows as $row) {
        $classId = intval($row['mastercom_id_classe'] ?? 0);
        if ($classId <= 0) {
            continue;
        }

        // Do not import component classes such as 3CBA or 3MEA when MasterCom also exposes 3CBA MEA ART.
        if (isset($articulatedSkipMap[$classId])) {
            continue;
        }

        // Exclude evening-course classes and articulated classes containing an evening-course component.
        if (mastercomTabelloniIsSeraleClassRow($row)) {
            continue;
        }

        if ($fields === '*') {
            $filtered[] = $row;
            continue;
        }

        $item = [];
        foreach (array_map('trim', explode(',', $fields)) as $field) {
            if ($field !== '') {
                $item[$field] = $row[$field] ?? null;
            }
        }
        $filtered[] = $item;
    }

    return $filtered;
}

function mastercomTabelloniImportClassMap(): array
{
    $map = [];
    foreach (mastercomTabelloniImportClassRows('mastercom_id_classe, nome') as $row) {
        $classId = intval($row['mastercom_id_classe'] ?? 0);
        if ($classId > 0) {
            $map[$classId] = $row;
        }
    }
    return $map;
}

function mastercomTabelloniFetchAndStoreAllClasses(array $params = []): array
{
    $classRows = mastercomTabelloniImportClassRows('mastercom_id_classe, nome');
    $stats = [
        'classes' => 0,
        'students' => 0,
        'votes' => 0,
        'without_student' => 0,
        'errors' => 0,
    ];
    $messages = [];

    foreach ($classRows as $classRow) {
        $classId = intval($classRow['mastercom_id_classe'] ?? 0);
        if ($classId <= 0) {
            continue;
        }
        $result = mastercomTabelloniFetchAndStoreClass($classId, $params);
        if (empty($result['ok'])) {
            $stats['errors']++;
            $messages[] = trim((string)($classRow['nome'] ?? ('classe ' . $classId))) . ': ' . ($result['message'] ?? 'errore lettura');
            continue;
        }

        $classStats = $result['stats'] ?? [];
        $stats['classes']++;
        $stats['students'] += intval($classStats['students'] ?? 0);
        $stats['votes'] += intval($classStats['votes'] ?? 0);
        $stats['without_student'] += intval($classStats['without_student'] ?? 0);
    }

    return [
        'ok' => $stats['classes'] > 0,
        'message' => 'Lettura tabelloni completata: classi ' . intval($stats['classes'])
            . ', studenti ' . intval($stats['students'])
            . ', celle salvate ' . intval($stats['votes'])
            . ', non abbinati ' . intval($stats['without_student'])
            . ', errori ' . intval($stats['errors']) . '.',
        'stats' => $stats,
        'errors' => $messages,
    ];
}

function mastercomTabelloniRecentRows(int $limit = 80): array
{
    mastercomTabelloniEnsureTables();
    return dbGetAll("
        SELECT t.*,
               COUNT(DISTINCT s.id) AS studenti_count,
               COUNT(v.id) AS celle_count
        FROM mastercom_tabelloni_scrutini t
        LEFT JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
        LEFT JOIN mastercom_tabelloni_scrutini_voti v ON v.tabellone_id = t.id
        GROUP BY t.id
        ORDER BY t.imported_at DESC, t.classe ASC
        LIMIT " . dbI($limit) . "
    ") ?: [];
}

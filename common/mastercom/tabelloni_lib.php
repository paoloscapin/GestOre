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
            `id_materia_gestore` INT NULL,
            `tipo_colonna` VARCHAR(50) NULL,
            `valore` VARCHAR(100) NULL,
            `valore_num` DECIMAL(5,2) NULL,
            `raw_value` VARCHAR(100) NULL,
            `insufficiente` TINYINT(1) NOT NULL DEFAULT 0,
            `raw_json` MEDIUMTEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mastercom_tabellone_voto` (`tabellone_studente_id`, `col_index`),
            KEY `idx_mastercom_tabellone_voti_tabellone` (`tabellone_id`),
            KEY `idx_mastercom_tabellone_voti_materia` (`materia_codice`),
            KEY `idx_mastercom_tabellone_voti_materia_gestore` (`id_materia_gestore`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    mastercomTabelloniEnsureColumns();
}

function mastercomTabelloniEnsureColumns(): void
{
    if (!mastercomAdminTableColumnExists('mastercom_tabelloni_scrutini_studenti', 'esito_key')) {
        dbExec("
            ALTER TABLE `mastercom_tabelloni_scrutini_studenti`
            ADD COLUMN `esito_key` VARCHAR(30) NULL AFTER `risultato`
        ");
    }
    if (!mastercomAdminTableColumnExists('mastercom_tabelloni_scrutini_colonne', 'sub_header')) {
        dbExec("
            ALTER TABLE `mastercom_tabelloni_scrutini_colonne`
            ADD COLUMN `sub_header` VARCHAR(100) NULL AFTER `tipo`
        ");
    }
    if (!mastercomAdminTableColumnExists('mastercom_tabelloni_scrutini_voti', 'id_materia_gestore')) {
        dbExec("
            ALTER TABLE `mastercom_tabelloni_scrutini_voti`
            ADD COLUMN `id_materia_gestore` INT NULL AFTER `materia_descrizione`
        ");
    }
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

function mastercomTabelloniNormalizeOutcome(string $result): string
{
    $norm = mastercomAdminNorm($result);
    if ($norm === '') {
        return 'sconosciuto';
    }
    if (strpos($norm, 'ANNO ESTERO') !== false || strpos($norm, 'ESTERO') !== false) {
        return 'anno_estero';
    }
    if (strpos($norm, 'IN CORSO') !== false) {
        return 'in_corso';
    }
    if (strpos($norm, 'NON AMMESS') !== false) {
        return 'non_ammesso';
    }
    if (strpos($norm, 'AMMESS') !== false) {
        return 'ammesso';
    }
    return 'sconosciuto';
}

function mastercomTabelloniOutcomeCell(array $cells): string
{
    for ($i = count($cells) - 1; $i >= 0; $i--) {
        $value = mastercomTabelloniCleanCell($cells[$i] ?? '');
        if ($value === '') {
            continue;
        }
        $outcome = mastercomTabelloniNormalizeOutcome($value);
        if ($outcome !== 'sconosciuto') {
            return $value;
        }
    }

    return '';
}

function mastercomTabelloniResolveSubjectId(string $subjectCode, string $className = ''): ?int
{
    $subjectCode = mastercomTabelloniCleanCell($subjectCode);
    if ($subjectCode === '') {
        return null;
    }

    $directCode = dbGetValue("SELECT id FROM materia WHERE codice = " . dbQ($subjectCode) . " LIMIT 1");
    if ($directCode !== null && intval($directCode) > 0) {
        return intval($directCode);
    }

    $resolved = mastercomDebtsResolveSubjectId($subjectCode, $className);
    if ($resolved !== null && $resolved > 0) {
        return $resolved;
    }

    $fallbackNames = [
        'ITA' => 'Lingua e letteratura italiana',
        'ING' => 'Lingua inglese',
        'STO' => 'Storia',
        'MAT' => 'Matematica',
        'DIR' => 'Diritto ed economia',
        'INF' => 'Informatica',
        'TPSI' => 'Tecnologie e progettazione di sistemi informatici e di telecomunicazioni',
        'SIA' => 'Scienze motorie e sportive',
        'IRC' => 'Religione cattolica',
        'ECC' => 'Educazione civica e alla cittadinanza',
    ];
    $key = mastercomDebtsNormalizeSubject($subjectCode);
    if (isset($fallbackNames[$key])) {
        return mastercomDebtsFindSubjectIdByNames([$fallbackNames[$key]]);
    }

    return null;
}

function mastercomTabelloniClassYearFromName(string $className): int
{
    $components = mastercomDebtsClassComponentsFromName($className);
    $tokens = !empty($components) ? $components : [mastercomDebtsNormalizeClassToken($className)];
    foreach ($tokens as $token) {
        if (preg_match('/^([1-5])/u', (string)$token, $matches)) {
            return intval($matches[1]);
        }
    }
    return 0;
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
            'esito_key' => '',
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
                $student['esito_key'] = mastercomTabelloniNormalizeOutcome($value);
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
        if (($student['risultato'] ?? '') === '' || ($student['esito_key'] ?? '') === 'sconosciuto') {
            $fallbackOutcome = mastercomTabelloniOutcomeCell($cells);
            if ($fallbackOutcome !== '') {
                $student['risultato'] = $fallbackOutcome;
                $student['esito_key'] = mastercomTabelloniNormalizeOutcome($fallbackOutcome);
            }
        }

        $parsed['students'][] = $student;
    }

    return $parsed;
}

function mastercomTabelloniLocalClassIdsForName(?int $localClassId, string $className): array
{
    $ids = [];
    if ($localClassId !== null && $localClassId > 0) {
        $ids[$localClassId] = $localClassId;
    }

    foreach (mastercomDebtsClassComponentsFromName($className) as $component) {
        $componentId = mastercomAdminFindLocalClassIdByName($component);
        if ($componentId !== null && $componentId > 0) {
            $ids[$componentId] = $componentId;
        }
    }

    return array_values($ids);
}

function mastercomTabelloniMastercomClassIdsForName(int $mastercomClassId, string $className): array
{
    $ids = [];
    if ($mastercomClassId > 0) {
        $ids[$mastercomClassId] = $mastercomClassId;
    }

    $components = mastercomDebtsClassComponentsFromName($className);
    if (empty($components) || !mastercomAdminTableExists('mastercom_classi')) {
        return array_values($ids);
    }

    $rows = dbGetAll("SELECT mastercom_id_classe, nome FROM mastercom_classi") ?: [];
    foreach ($rows as $row) {
        $rowId = intval($row['mastercom_id_classe'] ?? 0);
        $rowComponents = mastercomDebtsClassComponentsFromName((string)($row['nome'] ?? ''));
        if ($rowId <= 0 || count($rowComponents) !== 1) {
            continue;
        }
        if (in_array($rowComponents[0], $components, true)) {
            $ids[$rowId] = $rowId;
        }
    }

    return array_values($ids);
}

function mastercomTabelloniStudentLookupMap(int $mastercomClassId, ?int $localClassId, ?int $schoolYearId = null, string $className = ''): array
{
    $map = [];
    if (mastercomAdminTableExists('mastercom_studenti')) {
        $where = [];
        $mastercomClassIds = mastercomTabelloniMastercomClassIdsForName($mastercomClassId, $className);
        if (!empty($mastercomClassIds)) {
            $where[] = "mastercom_id_classe_corrente IN (" . implode(',', array_map('intval', $mastercomClassIds)) . ")";
        }
        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $rows = dbGetAll("
            SELECT *
            FROM mastercom_studenti
            $whereSql
        ") ?: [];
        foreach ($rows as $row) {
            $label = trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
            $key = mastercomAdminNormCompact($label);
            if ($key !== '') {
                $localStudentId = intval($row['id_studente_gestore'] ?? 0) ?: null;
                if ($localStudentId === null) {
                    $localStudent = mastercomAdminResolveLocalStudent($row);
                    $localStudentId = intval($localStudent['id'] ?? 0) ?: null;
                    if ($localStudentId !== null && intval($row['id'] ?? 0) > 0) {
                        dbExec("
                            UPDATE mastercom_studenti
                            SET id_studente_gestore = " . dbI($localStudentId) . "
                            WHERE id = " . dbI($row['id']) . "
                            LIMIT 1
                        ");
                    }
                }
                $map[$key] = [
                    'mastercom_id_studente' => intval($row['mastercom_id_studente'] ?? 0) ?: null,
                    'id_studente_gestore' => $localStudentId,
                    'label' => $label,
                ];
            }
        }
    }

    $localClassIds = mastercomTabelloniLocalClassIdsForName($localClassId, $className);
    if (!empty($localClassIds)) {
        $yearWhere = $schoolYearId !== null && $schoolYearId > 0
            ? " AND sf.id_anno_scolastico = " . dbI($schoolYearId)
            : "";
        $rows = dbGetAll("
            SELECT s.id, s.cognome, s.nome
            FROM studente s
            INNER JOIN studente_frequenta sf ON sf.id_studente = s.id
            WHERE sf.id_classe IN (" . implode(',', array_map('intval', $localClassIds)) . ")
            $yearWhere
        ") ?: [];
        foreach ($rows as $row) {
            $key = mastercomAdminNormCompact(trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''))));
            if ($key !== '' && empty($map[$key]['id_studente_gestore'])) {
                $map[$key] = [
                    'mastercom_id_studente' => $map[$key]['mastercom_id_studente'] ?? null,
                    'id_studente_gestore' => intval($row['id'] ?? 0) ?: null,
                    'label' => trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''))),
                ];
            }
        }
    }

    return $map;
}

function mastercomTabelloniStudentNameKeysCompatible(string $leftKey, string $rightKey): bool
{
    $leftKey = trim($leftKey);
    $rightKey = trim($rightKey);
    if ($leftKey === '' || $rightKey === '') {
        return false;
    }
    if ($leftKey === $rightKey) {
        return true;
    }

    $minLength = min(strlen($leftKey), strlen($rightKey));
    if ($minLength < 6) {
        return false;
    }

    return strpos($leftKey, $rightKey) === 0 || strpos($rightKey, $leftKey) === 0;
}

function mastercomTabelloniNameTokens(string $name): array
{
    $tokens = preg_split('/\s+/u', mastercomAdminNorm($name)) ?: [];
    $tokens = array_values(array_unique(array_filter($tokens, function ($token) {
        return strlen((string)$token) >= 2;
    })));

    return $tokens;
}

function mastercomTabelloniStudentNamesCompatible(string $leftName, string $rightName): bool
{
    if (mastercomTabelloniStudentNameKeysCompatible(
        mastercomAdminNormCompact($leftName),
        mastercomAdminNormCompact($rightName)
    )) {
        return true;
    }

    $leftTokens = mastercomTabelloniNameTokens($leftName);
    $rightTokens = mastercomTabelloniNameTokens($rightName);
    if (count($leftTokens) < 2 || count($rightTokens) < 2) {
        return false;
    }
    if ($leftTokens[0] !== $rightTokens[0]) {
        return false;
    }

    $leftMissing = array_diff($leftTokens, $rightTokens);
    $rightMissing = array_diff($rightTokens, $leftTokens);

    return empty($leftMissing) || empty($rightMissing);
}

function mastercomTabelloniFindCompatibleStudentLookup(string $studentName, array $studentMap): array
{
    $studentKey = mastercomAdminNormCompact($studentName);
    if ($studentKey === '') {
        return [];
    }
    if (!empty($studentMap[$studentKey])) {
        return $studentMap[$studentKey];
    }

    $matches = [];
    foreach ($studentMap as $candidateKey => $candidate) {
        $candidateName = (string)($candidate['label'] ?? $candidateKey);
        if (
            mastercomTabelloniStudentNameKeysCompatible($studentKey, (string)$candidateKey)
            || mastercomTabelloniStudentNamesCompatible($studentName, $candidateName)
        ) {
            $matchId = intval($candidate['id_studente_gestore'] ?? 0);
            $matches[$matchId > 0 ? (string)$matchId : (string)$candidateKey] = $candidate;
        }
    }

    $matches = array_values($matches);
    return count($matches) === 1 ? $matches[0] : [];
}

function mastercomTabelloniFindExpectedByCompatibleStudentName(array $expectedRows, int $tabelloneId, int $subjectId, string $studentName): ?array
{
    $studentKey = mastercomAdminNormCompact($studentName);
    if ($tabelloneId <= 0 || $subjectId <= 0 || $studentKey === '') {
        return null;
    }

    $matches = [];
    foreach ($expectedRows as $row) {
        if (intval($row['tabellone_id'] ?? 0) !== $tabelloneId) {
            continue;
        }
        if (intval($row['id_materia_gestore'] ?? 0) !== $subjectId) {
            continue;
        }
        $candidateName = (string)($row['studente_nome'] ?? '');
        $candidateKey = mastercomAdminNormCompact($candidateName);
        if (
            mastercomTabelloniStudentNameKeysCompatible($studentKey, $candidateKey)
            || mastercomTabelloniStudentNamesCompatible($studentName, $candidateName)
        ) {
            $matches[] = $row;
        }
    }

    return count($matches) === 1 ? $matches[0] : null;
}

function mastercomTabelloniFindExpectedForDebt(array $expectedRows, array $debtRow, int $subjectId): ?array
{
    if ($subjectId <= 0) {
        return null;
    }

    $yearId = intval($debtRow['id_anno_scolastico'] ?? 0);
    $studentId = intval($debtRow['id_studente_gestore'] ?? 0);
    $studentName = (string)($debtRow['studente_nome'] ?? '');
    $debtClassId = intval($debtRow['id_classe_gestore'] ?? 0) ?: null;
    $debtClassName = (string)($debtRow['classe'] ?? '');
    $matches = [];

    foreach ($expectedRows as $row) {
        if (intval($row['id_materia_gestore'] ?? 0) !== $subjectId) {
            continue;
        }
        if ($yearId > 0 && intval($row['id_anno_scolastico'] ?? 0) !== $yearId) {
            continue;
        }
        if (!mastercomTabelloniClassesCompatible(
            intval($row['id_classe_gestore'] ?? 0) ?: null,
            (string)(($row['classe_tabellone'] ?? '') !== '' ? $row['classe_tabellone'] : ($row['classe'] ?? '')),
            $debtClassId,
            $debtClassName
        )) {
            continue;
        }

        $rowStudentId = intval($row['id_studente_gestore'] ?? 0);
        $sameStudent = $studentId > 0 && $rowStudentId > 0 && $studentId === $rowStudentId;
        if (!$sameStudent) {
            $sameStudent = mastercomTabelloniStudentNamesCompatible($studentName, (string)($row['studente_nome'] ?? ''));
        }
        if ($sameStudent) {
            $matches[] = $row;
        }
    }

    return count($matches) >= 1 ? $matches[0] : null;
}

function mastercomTabelloniClassesCompatible(?int $tabelloneClassId, string $tabelloneClassName, ?int $debtClassId, string $debtClassName): bool
{
    if ($tabelloneClassId !== null && $tabelloneClassId > 0 && $debtClassId !== null && $debtClassId > 0 && $tabelloneClassId === $debtClassId) {
        return true;
    }

    $tabelloneComponents = mastercomDebtsClassComponentsFromName($tabelloneClassName);
    $debtComponents = mastercomDebtsClassComponentsFromName($debtClassName);
    if (empty($tabelloneComponents) || empty($debtComponents)) {
        return false;
    }

    return count(array_intersect($tabelloneComponents, $debtComponents)) > 0;
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
                tabellone_id, col_index, codice, descrizione, tipo, sub_header, raw_json
            ) VALUES (
                " . dbI($tabelloneId) . ",
                " . dbI($column['col_index'] ?? 0) . ",
                " . dbQ($column['codice'] ?? '') . ",
                " . dbQ($column['descrizione'] ?? '') . ",
                " . dbQ($column['tipo'] ?? '') . ",
                " . dbQ($column['sub_header'] ?? '') . ",
                " . dbQ(mastercomAdminJson($column)) . "
            )
        ");
    }

    $studentMap = mastercomTabelloniStudentLookupMap(
        $mastercomClassId,
        $localClassId,
        $yearId,
        (string)($parsed['classe_tabellone'] ?? $className)
    );
    $stats = [
        'columns' => count($parsed['columns'] ?? []),
        'students' => 0,
        'votes' => 0,
        'without_student' => 0,
        'without_student_names' => [],
    ];

    foreach (($parsed['students'] ?? []) as $student) {
        $studentName = (string)($student['studente_nome'] ?? '');
        $lookup = mastercomTabelloniFindCompatibleStudentLookup($studentName, $studentMap);
        $mastercomStudentId = intval($lookup['mastercom_id_studente'] ?? 0) ?: null;
        $localStudentId = intval($lookup['id_studente_gestore'] ?? 0) ?: null;
        if ($localStudentId === null) {
            $stats['without_student']++;
            $stats['without_student_names'][] = $studentName;
        }

        $studentOutcome = (string)($student['esito_key'] ?? '');
        if ($studentOutcome === '') {
            $studentOutcome = mastercomTabelloniNormalizeOutcome((string)($student['risultato'] ?? ''));
        }

        dbExec("
            INSERT INTO mastercom_tabelloni_scrutini_studenti (
                tabellone_id, row_index, numero, mastercom_id_studente, id_studente_gestore,
                studente_nome, media, crediti_3, crediti_4, crediti_5, crediti_totale,
                risultato, esito_key, raw_json
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
                " . dbQ($studentOutcome) . ",
                " . dbQ(mastercomAdminJson($student)) . "
            )
        ");

        $tabelloneStudentId = dblastId();
        foreach (($student['values'] ?? []) as $value) {
            dbExec("
                INSERT INTO mastercom_tabelloni_scrutini_voti (
                    tabellone_id, tabellone_studente_id, col_index, materia_codice,
                    materia_descrizione, id_materia_gestore, tipo_colonna, valore, valore_num, raw_value,
                    insufficiente, raw_json
                ) VALUES (
                    " . dbI($tabelloneId) . ",
                    " . dbI($tabelloneStudentId) . ",
                    " . dbI($value['col_index'] ?? 0) . ",
                    " . dbQ($value['materia_codice'] ?? '') . ",
                    " . dbQ($value['materia_descrizione'] ?? '') . ",
                    " . dbI(mastercomTabelloniResolveSubjectId((string)($value['materia_codice'] ?? ''), $className)) . ",
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

function mastercomTabelloniRefreshDerivedFields(): array
{
    mastercomTabelloniEnsureTables();

    $stats = ['subjects' => 0, 'outcomes' => 0, 'students' => 0];
    $subjectRows = dbGetAll("
        SELECT DISTINCT t.classe, v.materia_codice
        FROM mastercom_tabelloni_scrutini_voti v
        INNER JOIN mastercom_tabelloni_scrutini t ON t.id = v.tabellone_id
        WHERE (v.id_materia_gestore IS NULL OR v.id_materia_gestore <= 0)
          AND v.materia_codice IS NOT NULL
          AND v.materia_codice <> ''
    ") ?: [];
    foreach ($subjectRows as $row) {
        $subjectId = mastercomTabelloniResolveSubjectId((string)($row['materia_codice'] ?? ''), (string)($row['classe'] ?? ''));
        if ($subjectId === null || $subjectId <= 0) {
            continue;
        }
        dbExec("
            UPDATE mastercom_tabelloni_scrutini_voti v
            INNER JOIN mastercom_tabelloni_scrutini t ON t.id = v.tabellone_id
            SET v.id_materia_gestore = " . dbI($subjectId) . "
            WHERE (v.id_materia_gestore IS NULL OR v.id_materia_gestore <= 0)
              AND v.materia_codice = " . dbQ($row['materia_codice'] ?? '') . "
              AND t.classe = " . dbQ($row['classe'] ?? '') . "
        ");
        $stats['subjects']++;
    }

    $outcomeRows = dbGetAll("
        SELECT id, risultato, esito_key, raw_json
        FROM mastercom_tabelloni_scrutini_studenti
        WHERE esito_key IS NULL OR esito_key = '' OR esito_key = 'sconosciuto'
    ") ?: [];
    foreach ($outcomeRows as $row) {
        $result = (string)($row['risultato'] ?? '');
        $outcome = mastercomTabelloniNormalizeOutcome($result);
        if ($outcome === 'sconosciuto') {
            $raw = json_decode((string)($row['raw_json'] ?? ''), true);
            if (is_array($raw)) {
                $fallbackOutcome = mastercomTabelloniOutcomeCell($raw['raw_cells'] ?? []);
                if ($fallbackOutcome !== '') {
                    $result = $fallbackOutcome;
                    $outcome = mastercomTabelloniNormalizeOutcome($fallbackOutcome);
                }
            }
        }
        dbExec("
            UPDATE mastercom_tabelloni_scrutini_studenti
            SET risultato = " . dbQ($result) . ",
                esito_key = " . dbQ($outcome) . "
            WHERE id = " . dbI($row['id'] ?? 0) . "
            LIMIT 1
        ");
        $stats['outcomes']++;
    }

    $tabelloni = dbGetAll("
        SELECT DISTINCT t.id, t.mastercom_id_classe, t.id_classe_gestore, t.id_anno_scolastico, t.classe, t.classe_tabellone
        FROM mastercom_tabelloni_scrutini t
        INNER JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
        WHERE s.id_studente_gestore IS NULL OR s.id_studente_gestore <= 0
    ") ?: [];
    foreach ($tabelloni as $tabellone) {
        $map = mastercomTabelloniStudentLookupMap(
            intval($tabellone['mastercom_id_classe'] ?? 0),
            intval($tabellone['id_classe_gestore'] ?? 0) ?: null,
            intval($tabellone['id_anno_scolastico'] ?? 0) ?: null,
            (string)(($tabellone['classe_tabellone'] ?? '') !== '' ? $tabellone['classe_tabellone'] : ($tabellone['classe'] ?? ''))
        );
        if (empty($map)) {
            continue;
        }
        $studentRows = dbGetAll("
            SELECT id, studente_nome
            FROM mastercom_tabelloni_scrutini_studenti
            WHERE tabellone_id = " . dbI($tabellone['id'] ?? 0) . "
              AND (id_studente_gestore IS NULL OR id_studente_gestore <= 0)
        ") ?: [];
        foreach ($studentRows as $studentRow) {
            $key = mastercomAdminNormCompact((string)($studentRow['studente_nome'] ?? ''));
            $lookup = mastercomTabelloniFindCompatibleStudentLookup((string)($studentRow['studente_nome'] ?? ''), $map);
            $localStudentId = intval($lookup['id_studente_gestore'] ?? 0);
            if ($key === '' || $localStudentId <= 0) {
                continue;
            }
            dbExec("
                UPDATE mastercom_tabelloni_scrutini_studenti
                SET id_studente_gestore = " . dbI($localStudentId) . "
                WHERE id = " . dbI($studentRow['id'] ?? 0) . "
                LIMIT 1
            ");
            $stats['students']++;
        }
    }

    return $stats;
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
    if ($token === '') {
        return false;
    }

    $seraleTokens = [
        'AUS',
        'INS',
        'CTS',
    ];
    foreach ($seraleTokens as $seraleToken) {
        if (preg_match('/^\d+' . preg_quote($seraleToken, '/') . '$/u', $token) === 1) {
            return true;
        }
    }

    return false;
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
        'without_student_names' => [],
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
        foreach ((array)($classStats['without_student_names'] ?? []) as $missingStudentName) {
            $missingStudentName = trim((string)$missingStudentName);
            if ($missingStudentName !== '') {
                $stats['without_student_names'][] = trim((string)($classRow['nome'] ?? '')) . ': ' . $missingStudentName;
            }
        }
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
        ORDER BY t.classe ASC, t.classe_tabellone ASC, t.imported_at DESC
        LIMIT " . dbI($limit) . "
    ") ?: [];
}

function mastercomTabelloniDetail(int $tabelloneId): array
{
    mastercomTabelloniEnsureTables();
    if ($tabelloneId <= 0) {
        return ['ok' => false, 'message' => 'Tabellone non valido.'];
    }

    $tabellone = dbGetFirst("
        SELECT *
        FROM mastercom_tabelloni_scrutini
        WHERE id = " . dbI($tabelloneId) . "
        LIMIT 1
    ");
    if ($tabellone == null) {
        return ['ok' => false, 'message' => 'Tabellone non trovato.'];
    }

    $columns = dbGetAll("
        SELECT col_index, codice, descrizione, tipo, sub_header
        FROM mastercom_tabelloni_scrutini_colonne
        WHERE tabellone_id = " . dbI($tabelloneId) . "
        ORDER BY col_index ASC
    ") ?: [];
    $students = dbGetAll("
        SELECT id, row_index, numero, studente_nome, media, crediti_3, crediti_4, crediti_5, crediti_totale, risultato, esito_key
        FROM mastercom_tabelloni_scrutini_studenti
        WHERE tabellone_id = " . dbI($tabelloneId) . "
        ORDER BY numero ASC, row_index ASC
    ") ?: [];
    $votes = dbGetAll("
        SELECT tabellone_studente_id, col_index, tipo_colonna, valore, valore_num, insufficiente
        FROM mastercom_tabelloni_scrutini_voti
        WHERE tabellone_id = " . dbI($tabelloneId) . "
        ORDER BY tabellone_studente_id ASC, col_index ASC
    ") ?: [];

    $voteMap = [];
    foreach ($votes as $vote) {
        $studentId = intval($vote['tabellone_studente_id'] ?? 0);
        $colIndex = intval($vote['col_index'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }
        if (!isset($voteMap[$studentId])) {
            $voteMap[$studentId] = [];
        }
        $voteMap[$studentId][$colIndex] = [
            'value' => (string)($vote['valore'] ?? ''),
            'type' => (string)($vote['tipo_colonna'] ?? ''),
            'num' => $vote['valore_num'] !== null ? floatval($vote['valore_num']) : null,
            'insufficiente' => intval($vote['insufficiente'] ?? 0) === 1,
        ];
    }

    $studentRows = [];
    foreach ($students as $student) {
        $studentId = intval($student['id'] ?? 0);
        $studentRows[] = [
            'id' => $studentId,
            'numero' => intval($student['numero'] ?? 0),
            'nome' => (string)($student['studente_nome'] ?? ''),
            'risultato' => (string)($student['risultato'] ?? ''),
            'esito_key' => (string)($student['esito_key'] ?? ''),
            'values' => $voteMap[$studentId] ?? [],
        ];
    }

    return [
        'ok' => true,
        'tabellone' => [
            'id' => intval($tabellone['id'] ?? 0),
            'classe' => (string)($tabellone['classe'] ?? ''),
            'classe_tabellone' => (string)($tabellone['classe_tabellone'] ?? ''),
            'anno_label' => (string)($tabellone['anno_label'] ?? ''),
            'periodo' => (string)($tabellone['periodo'] ?? ''),
            'periodo_label' => (string)($tabellone['periodo_label'] ?? ''),
            'imported_at' => (string)($tabellone['imported_at'] ?? ''),
        ],
        'columns' => array_map(function ($column) {
            return [
                'col_index' => intval($column['col_index'] ?? 0),
                'codice' => (string)($column['codice'] ?? ''),
                'descrizione' => (string)($column['descrizione'] ?? ''),
                'tipo' => (string)($column['tipo'] ?? ''),
                'sub_header' => (string)($column['sub_header'] ?? ''),
            ];
        }, $columns),
        'students' => $studentRows,
    ];
}

function mastercomTabelloniSummaryClassLabel(array $row): string
{
    $label = trim((string)($row['classe_tabellone'] ?? ''));
    if ($label === '') {
        $label = trim((string)($row['classe'] ?? ''));
    }

    $norm = mastercomAdminNorm($label);
    $parts = preg_split('/[^A-Z0-9]+/u', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!empty($parts) && mastercomTabelloniClassYearFromName($label) <= 2 && !in_array('ART', $parts, true)) {
        return (string)$parts[0];
    }

    return $label;
}

function mastercomTabelloniSummaryIsArticulatedLabel(string $label): bool
{
    $parts = preg_split('/[^A-Z0-9]+/u', mastercomAdminNorm($label), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return in_array('ART', $parts, true);
}

function mastercomTabelloniSummaryEffectiveClassLabel(array $row): string
{
    $tabelloneLabel = mastercomTabelloniSummaryClassLabel($row);
    $components = mastercomDebtsClassComponentsFromName($tabelloneLabel);
    if (count($components) <= 1 || !mastercomTabelloniSummaryIsArticulatedLabel($tabelloneLabel)) {
        return $tabelloneLabel;
    }

    foreach (['classe_gestore_studente', 'classe_mastercom_studente'] as $field) {
        $studentClass = trim((string)($row[$field] ?? ''));
        if ($studentClass === '') {
            continue;
        }

        $studentComponents = mastercomDebtsClassComponentsFromName($studentClass);
        foreach ($studentComponents as $studentComponent) {
            if (in_array($studentComponent, $components, true)) {
                return $studentClass;
            }
        }
    }

    return $tabelloneLabel;
}

function mastercomTabelloniSummaryAddress(string $className): string
{
    $className = mastercomAdminNorm($className);
    $parts = preg_split('/[^A-Z0-9]+/u', $className, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (empty($parts)) {
        return 'n/d';
    }

    if (in_array('ART', $parts, true)) {
        $componentLabels = [];
        foreach (mastercomDebtsClassComponentsFromName($className) as $component) {
            $component = preg_replace('/^[1-5]/u', '', $component);
            if ($component === '' || $component === 'ART') {
                continue;
            }
            $componentLabels[] = $component;
        }
        if (!empty($componentLabels)) {
            return implode(' / ', array_values(array_unique($componentLabels)));
        }
    }

    $addressParts = [];
    foreach ($parts as $index => $part) {
        if ($index === 0) {
            continue;
        }
        if ($part === 'ART') {
            continue;
        }
        if (preg_match('/^[1-5][A-Z0-9]+$/u', $part)) {
            continue;
        }
        $addressParts[] = $part;
    }

    if (empty($addressParts)) {
        $first = mastercomDebtsNormalizeClassToken((string)$parts[0]);
        if (preg_match('/^[3-5]([A-Z0-9]{2,})$/u', $first, $matches)) {
            return $matches[1];
        }
        return 'n/d';
    }

    return implode(' / ', array_values(array_unique($addressParts)));
}

function mastercomTabelloniEmptyOutcomeSummaryRow(string $label, string $type = 'classe'): array
{
    return [
        'type' => $type,
        'label' => $label,
        'classes' => 0,
        'students' => 0,
        'promossi' => 0,
        'bocciati' => 0,
        'promossi_con_carenze' => 0,
        'promossi_una_carenza' => 0,
        'promossi_due_o_piu_carenze' => 0,
    ];
}

function mastercomTabelloniAddOutcomeSummaryRow(array &$target, array $source): void
{
    $target['classes'] += intval($source['classes'] ?? 0);
    $target['students'] += intval($source['students'] ?? 0);
    $target['promossi'] += intval($source['promossi'] ?? 0);
    $target['bocciati'] += intval($source['bocciati'] ?? 0);
    $target['promossi_con_carenze'] += intval($source['promossi_con_carenze'] ?? 0);
    $target['promossi_una_carenza'] += intval($source['promossi_una_carenza'] ?? 0);
    $target['promossi_due_o_piu_carenze'] += intval($source['promossi_due_o_piu_carenze'] ?? 0);
}

function mastercomTabelloniAverageSubjects(): array
{
    return [
        'matematica' => [
            'label' => 'Matematica',
            'codes' => ['MAT', 'MCM'],
        ],
        'italiano' => [
            'label' => 'Lingua italiana',
            'codes' => ['ITA'],
        ],
        'inglese' => [
            'label' => 'Lingua inglese',
            'codes' => ['ING'],
        ],
        'tedesco' => [
            'label' => 'Lingua tedesca',
            'codes' => ['TED'],
        ],
        'crel' => [
            'label' => 'Capacita relazionale',
            'codes' => ['CREL'],
        ],
        'media' => [
            'label' => 'Media generale',
            'codes' => ['MEDIA'],
        ],
    ];
}

function mastercomTabelloniAverageCategory(string $subjectCode, string $columnType): string
{
    $code = mastercomAdminNorm($subjectCode);
    if ($columnType === 'media' || $code === 'MEDIA') {
        return 'media';
    }

    foreach (mastercomTabelloniAverageSubjects() as $key => $config) {
        if ($key === 'media') {
            continue;
        }
        if (in_array($code, (array)($config['codes'] ?? []), true)) {
            return $key;
        }
    }

    return '';
}

function mastercomTabelloniEmptyAveragesSummaryRow(string $label, string $type = 'classe'): array
{
    $row = [
        'type' => $type,
        'label' => $label,
        'classes' => 0,
        'students' => 0,
        'class_year' => 0,
        'address' => '',
    ];
    foreach (mastercomTabelloniAverageSubjects() as $key => $config) {
        $row[$key . '_sum'] = 0.0;
        $row[$key . '_count'] = 0;
        $row[$key . '_avg'] = null;
    }

    return $row;
}

function mastercomTabelloniAddAverageValue(array &$row, string $category, ?float $value): void
{
    if ($category === '' || $value === null) {
        return;
    }
    if (!array_key_exists($category . '_sum', $row)) {
        return;
    }

    $row[$category . '_sum'] += $value;
    $row[$category . '_count']++;
    $row[$category . '_avg'] = $row[$category . '_count'] > 0
        ? $row[$category . '_sum'] / $row[$category . '_count']
        : null;
}

function mastercomTabelloniAddAveragesSummaryRow(array &$target, array $source): void
{
    $target['classes'] += intval($source['classes'] ?? 0);
    $target['students'] += intval($source['students'] ?? 0);
    foreach (mastercomTabelloniAverageSubjects() as $key => $config) {
        $target[$key . '_sum'] += floatval($source[$key . '_sum'] ?? 0);
        $target[$key . '_count'] += intval($source[$key . '_count'] ?? 0);
        $target[$key . '_avg'] = $target[$key . '_count'] > 0
            ? $target[$key . '_sum'] / $target[$key . '_count']
            : null;
    }
}

function mastercomTabelloniAveragesSummary(int $schoolYearId = 0, string $period = '9'): array
{
    mastercomTabelloniEnsureTables();

    $where = ["t.periodo = " . dbQ($period !== '' ? $period : '9')];
    if ($schoolYearId > 0) {
        $where[] = "t.id_anno_scolastico = " . dbI($schoolYearId);
    }
    $whereSql = implode(' AND ', $where);

    $rows = dbGetAll("
        SELECT
            t.id AS tabellone_id,
            t.classe,
            t.classe_tabellone,
            t.anno_label,
            t.id_anno_scolastico,
            s.id AS tabellone_studente_id,
            s.mastercom_id_studente,
            s.id_studente_gestore,
            s.studente_nome,
            cls_summary.classe AS classe_gestore_studente,
            mcls_summary.nome AS classe_mastercom_studente,
            v.materia_codice,
            v.tipo_colonna,
            v.valore_num
        FROM mastercom_tabelloni_scrutini t
        INNER JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
        INNER JOIN mastercom_tabelloni_scrutini_voti v ON v.tabellone_studente_id = s.id
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
        WHERE $whereSql
          AND v.valore_num IS NOT NULL
          AND v.tipo_colonna IN ('voto', 'media')
        ORDER BY t.classe ASC, s.studente_nome ASC, v.materia_codice ASC
    ") ?: [];

    $classes = [];
    foreach ($rows as $row) {
        $category = mastercomTabelloniAverageCategory((string)($row['materia_codice'] ?? ''), (string)($row['tipo_colonna'] ?? ''));
        if ($category === '') {
            continue;
        }

        $classLabel = mastercomTabelloniSummaryEffectiveClassLabel($row);
        if ($classLabel === '') {
            $classLabel = trim((string)($row['classe'] ?? ''));
        }
        if ($classLabel === '') {
            $classLabel = 'Classe n/d';
        }

        $classYear = mastercomTabelloniClassYearFromName($classLabel);
        $classKey = mastercomAdminNormCompact($classLabel);
        if ($classKey === '') {
            $classKey = 'CLASS_' . intval($row['tabellone_id'] ?? 0);
        }
        if (!isset($classes[$classKey])) {
            $classes[$classKey] = mastercomTabelloniEmptyAveragesSummaryRow($classLabel, 'classe');
            $classes[$classKey]['class_year'] = $classYear;
            $classes[$classKey]['address'] = $classYear >= 3 ? mastercomTabelloniSummaryAddress($classLabel) : '';
            $classes[$classKey]['classes'] = 1;
        }

        $studentKey = 'student_' . intval($row['tabellone_studente_id'] ?? 0);
        if (empty($classes[$classKey]['_students'][$studentKey])) {
            $classes[$classKey]['_students'][$studentKey] = true;
            $classes[$classKey]['students']++;
        }

        mastercomTabelloniAddAverageValue($classes[$classKey], $category, floatval($row['valore_num']));
    }

    foreach ($classes as &$classRow) {
        unset($classRow['_students']);
    }
    unset($classRow);

    $totals = [
        1 => [],
        2 => [],
        3 => [],
        4 => [],
        5 => [],
    ];
    foreach ($classes as $classRow) {
        $classYear = intval($classRow['class_year'] ?? 0);
        if ($classYear <= 0 || $classYear > 5) {
            continue;
        }
        if ($classYear <= 2) {
            $key = 'totale';
            if (!isset($totals[$classYear][$key])) {
                $totals[$classYear][$key] = mastercomTabelloniEmptyAveragesSummaryRow('Totale classi ' . $classYear . 'e', 'totale');
            }
        } else {
            $key = (string)($classRow['address'] ?? 'n/d');
            if ($key === '') {
                $key = 'n/d';
            }
            if (!isset($totals[$classYear][$key])) {
                $totals[$classYear][$key] = mastercomTabelloniEmptyAveragesSummaryRow($key, 'indirizzo');
            }
        }
        mastercomTabelloniAddAveragesSummaryRow($totals[$classYear][$key], $classRow);
    }

    return [
        'subjects' => mastercomTabelloniAverageSubjects(),
        'classes' => array_values($classes),
        'totals' => $totals,
    ];
}

function mastercomTabelloniOutcomeSummary(int $schoolYearId = 0, string $period = '9'): array
{
    mastercomTabelloniEnsureTables();

    $where = ["t.periodo = " . dbQ($period !== '' ? $period : '9')];
    if ($schoolYearId > 0) {
        $where[] = "t.id_anno_scolastico = " . dbI($schoolYearId);
    }
    $whereSql = implode(' AND ', $where);

    $rows = dbGetAll("
        SELECT
            t.id AS tabellone_id,
            t.classe,
            t.classe_tabellone,
            t.anno_label,
            t.id_anno_scolastico,
            s.id AS tabellone_studente_id,
            s.mastercom_id_studente,
            s.id_studente_gestore,
            s.esito_key,
            s.studente_nome,
            cls_summary.classe AS classe_gestore_studente,
            mcls_summary.nome AS classe_mastercom_studente,
            MAX(CASE
                WHEN v.tipo_colonna = 'voto'
                 AND v.valore_num IN (4, 5)
                THEN 1 ELSE 0
            END) AS has_raw_carenza,
            GROUP_CONCAT(DISTINCT
                CASE
                    WHEN v.tipo_colonna = 'voto'
                     AND v.valore_num IN (4, 5)
                    THEN CONCAT(COALESCE(v.materia_codice, ''), '||', COALESCE(v.id_materia_gestore, 0), '||', COALESCE(m.nome, ''))
                    ELSE NULL
                END
                SEPARATOR '\n'
            ) AS carenze_subjects
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
        LEFT JOIN mastercom_tabelloni_scrutini_voti v ON v.tabellone_studente_id = s.id
        LEFT JOIN materia m ON m.id = v.id_materia_gestore
        WHERE $whereSql
        GROUP BY t.id, t.classe, t.classe_tabellone, t.anno_label, t.id_anno_scolastico,
                 s.id, s.mastercom_id_studente, s.id_studente_gestore, s.esito_key, s.studente_nome,
                 cls_summary.classe, mcls_summary.nome
        ORDER BY t.classe ASC, s.studente_nome ASC
    ") ?: [];

    $classes = [];
    foreach ($rows as $row) {
        $classLabel = mastercomTabelloniSummaryEffectiveClassLabel($row);
        if ($classLabel === '') {
            $classLabel = trim((string)($row['classe'] ?? ''));
        }
        if ($classLabel === '') {
            $classLabel = 'Classe n/d';
        }

        $classYear = mastercomTabelloniClassYearFromName($classLabel);
        $classKey = mastercomAdminNormCompact($classLabel);
        if ($classKey === '') {
            $classKey = 'CLASS_' . intval($row['tabellone_id'] ?? 0);
        }
        if (!isset($classes[$classKey])) {
            $classes[$classKey] = mastercomTabelloniEmptyOutcomeSummaryRow($classLabel, 'classe');
            $classes[$classKey]['class_year'] = $classYear;
            $classes[$classKey]['address'] = $classYear >= 3 ? mastercomTabelloniSummaryAddress($classLabel) : '';
            $classes[$classKey]['classes'] = 1;
        }

        $outcome = (string)($row['esito_key'] ?? '');
        $classes[$classKey]['students']++;
        if (in_array($outcome, ['ammesso', 'anno_estero'], true)) {
            $classes[$classKey]['promossi']++;
            $carenzeCount = 0;
            foreach (preg_split('/\n/u', (string)($row['carenze_subjects'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $subjectInfo) {
                [$subjectCode, $subjectId, $subjectName] = array_pad(explode('||', $subjectInfo, 3), 3, '');
                $subjectId = intval($subjectId);
                if (
                    ($subjectId > 0 && mastercomDebtsIsNoCourseExpectedSubjectId($subjectId))
                    || mastercomDebtsIsNoCourseExpectedSubjectName($subjectCode)
                    || mastercomDebtsIsNoCourseExpectedSubjectName($subjectName)
                ) {
                    continue;
                }
                $carenzeCount++;
            }
            if ($carenzeCount > 0) {
                $classes[$classKey]['promossi_con_carenze']++;
                if ($carenzeCount === 1) {
                    $classes[$classKey]['promossi_una_carenza']++;
                } else {
                    $classes[$classKey]['promossi_due_o_piu_carenze']++;
                }
            }
        } elseif (in_array($outcome, ['non_ammesso', 'in_corso'], true)) {
            $classes[$classKey]['bocciati']++;
        }
    }

    $totals = [
        1 => [],
        2 => [],
        3 => [],
        4 => [],
        5 => [],
    ];
    foreach ($classes as $classRow) {
        $classYear = intval($classRow['class_year'] ?? 0);
        if ($classYear <= 0 || $classYear > 5) {
            continue;
        }
        if ($classYear <= 2) {
            $key = 'totale';
            if (!isset($totals[$classYear][$key])) {
                $totals[$classYear][$key] = mastercomTabelloniEmptyOutcomeSummaryRow('Totale classi ' . $classYear . 'e', 'totale');
            }
        } else {
            $key = (string)($classRow['address'] ?? 'n/d');
            if ($key === '') {
                $key = 'n/d';
            }
            if (!isset($totals[$classYear][$key])) {
                $totals[$classYear][$key] = mastercomTabelloniEmptyOutcomeSummaryRow($key, 'indirizzo');
            }
        }
        mastercomTabelloniAddOutcomeSummaryRow($totals[$classYear][$key], $classRow);
    }

    return [
        'classes' => array_values($classes),
        'totals' => $totals,
    ];
}

function mastercomTabelloniAuditRows(int $schoolYearId = 0, int $mastercomClassId = 0, int $limit = 300): array
{
    mastercomTabelloniEnsureTables();
    mastercomDebtsEnsureTables();

    $where = ["t.periodo = '9'"];
    if ($schoolYearId > 0) {
        $where[] = "t.id_anno_scolastico = " . dbI($schoolYearId);
    }
    if ($mastercomClassId > 0) {
        $where[] = "t.mastercom_id_classe = " . dbI($mastercomClassId);
    }
    $whereSql = implode(' AND ', $where);

    $issues = [];
    $expectedRows = dbGetAll("
        SELECT t.id AS tabellone_id,
               t.mastercom_id_classe,
               t.id_classe_gestore,
               t.classe,
               t.classe_tabellone,
               t.anno_label,
               t.id_anno_scolastico,
               s.id AS tabellone_studente_id,
               s.id_studente_gestore,
               s.studente_nome,
               s.esito_key,
               v.id AS voto_id,
               v.materia_codice,
               v.id_materia_gestore,
               v.valore,
               v.valore_num,
               m.nome AS materia_gestore
        FROM mastercom_tabelloni_scrutini t
        INNER JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
        INNER JOIN mastercom_tabelloni_scrutini_voti v ON v.tabellone_studente_id = s.id
        LEFT JOIN materia m ON m.id = v.id_materia_gestore
        WHERE $whereSql
          AND s.esito_key = 'ammesso'
          AND v.tipo_colonna = 'voto'
          AND v.valore_num IN (4, 5)
        ORDER BY t.classe ASC, s.studente_nome ASC, v.materia_codice ASC
    ") ?: [];

    $expectedByStudentId = [];
    $expectedByStudentName = [];
    foreach ($expectedRows as $expectedRow) {
        $tabelloneId = intval($expectedRow['tabellone_id'] ?? 0);
        $subjectId = intval($expectedRow['id_materia_gestore'] ?? 0);
        $studentId = intval($expectedRow['id_studente_gestore'] ?? 0);
        if ($tabelloneId <= 0 || $subjectId <= 0) {
            continue;
        }
        if ($studentId > 0) {
            $expectedByStudentId[$tabelloneId . '|' . $studentId . '|' . $subjectId] = $expectedRow;
        }
        $studentKey = mastercomAdminNormCompact((string)($expectedRow['studente_nome'] ?? ''));
        if ($studentKey !== '') {
            $expectedByStudentName[$tabelloneId . '|' . $studentKey . '|' . $subjectId] = $expectedRow;
        }
    }

    foreach ($expectedRows as $row) {
        if (mastercomTabelloniClassYearFromName((string)($row['classe'] ?? '')) >= 5) {
            continue;
        }
        $studentId = intval($row['id_studente_gestore'] ?? 0);
        $subjectId = intval($row['id_materia_gestore'] ?? 0);
        if (
            ($subjectId > 0 && mastercomDebtsIsNoCourseExpectedSubjectId($subjectId))
            || mastercomDebtsIsNoCourseExpectedSubjectName((string)($row['materia_codice'] ?? ''))
            || mastercomDebtsIsNoCourseExpectedSubjectName((string)($row['materia_gestore'] ?? ''))
        ) {
            continue;
        }
        $yearId = intval($row['id_anno_scolastico'] ?? 0);
        if ($studentId <= 0) {
            $issues[] = array_merge($row, [
                'tipo' => 'STUDENTE_NON_ABBINATO',
                'messaggio' => 'Studente del tabellone non abbinato a GestOre.',
            ]);
            continue;
        }
        if ($subjectId <= 0) {
            $issues[] = array_merge($row, [
                'tipo' => 'MATERIA_NON_ABBINATA',
                'messaggio' => 'Sigla materia del tabellone non abbinata a una materia GestOre.',
            ]);
            continue;
        }

        $mcDebtId = dbGetValue("
            SELECT id
            FROM mastercom_carenze
            WHERE id_studente_gestore = " . dbI($studentId) . "
              AND id_materia_gestore = " . dbI($subjectId) . "
              AND id_anno_scolastico = " . dbI($yearId) . "
            LIMIT 1
        ");
        if ($mcDebtId === null) {
            $gestoreDebtId = dbGetValue("
                SELECT id
                FROM carenze
                WHERE id_studente = " . dbI($studentId) . "
                  AND id_materia = " . dbI($subjectId) . "
                  AND id_anno_scolastico = " . dbI($yearId) . "
                LIMIT 1
            ");
            $issues[] = array_merge($row, [
                'tipo' => 'MANCA_IMPORT_CARENZE',
                'messaggio' => 'Il tabellone mostra insufficienza da carenza, ma l import carenze MasterCom non la contiene.',
                'carenza_id' => $gestoreDebtId !== null ? intval($gestoreDebtId) : null,
            ]);
        }
    }

    $extraRows = dbGetAll("
        SELECT DISTINCT
               t.id AS tabellone_id,
               t.id_classe_gestore AS tabellone_id_classe_gestore,
               t.classe AS tabellone_classe,
               t.classe_tabellone,
               mc.id AS mastercom_carenza_id,
               mc.mastercom_id_classe,
               mc.id_classe_gestore,
               mc.classe,
               mc.anno_label,
               mc.id_anno_scolastico,
               mc.id_studente_gestore,
               mc.studente_nome,
               mc.materia AS materia_codice,
               mc.id_materia_gestore,
               m.nome AS materia_gestore,
               s.esito_key,
               s.risultato,
               c.id AS carenza_id
        FROM mastercom_carenze mc
        INNER JOIN mastercom_tabelloni_scrutini t
            ON t.id_anno_scolastico = mc.id_anno_scolastico
           AND t.periodo = '9'
        LEFT JOIN mastercom_tabelloni_scrutini_studenti s
            ON s.tabellone_id = t.id
           AND s.id_studente_gestore = mc.id_studente_gestore
        LEFT JOIN materia m ON m.id = mc.id_materia_gestore
        LEFT JOIN carenze c
            ON c.id_studente = mc.id_studente_gestore
           AND c.id_materia = mc.id_materia_gestore
           AND c.id_anno_scolastico = mc.id_anno_scolastico
        WHERE " . ($schoolYearId > 0 ? "mc.id_anno_scolastico = " . dbI($schoolYearId) . " AND " : "") . "
              " . ($mastercomClassId > 0 ? "mc.mastercom_id_classe = " . dbI($mastercomClassId) . " AND " : "") . "
              1 = 1
        ORDER BY mc.classe ASC, mc.studente_nome ASC, mc.materia ASC
    ") ?: [];

    foreach ($extraRows as $row) {
        if (mastercomTabelloniClassYearFromName((string)($row['classe'] ?? '')) >= 5) {
            continue;
        }
        if (!mastercomTabelloniClassesCompatible(
            intval($row['tabellone_id_classe_gestore'] ?? 0) ?: null,
            (string)(($row['classe_tabellone'] ?? '') !== '' ? $row['classe_tabellone'] : ($row['tabellone_classe'] ?? '')),
            intval($row['id_classe_gestore'] ?? 0) ?: null,
            (string)($row['classe'] ?? '')
        )) {
            continue;
        }
        $subjectId = intval($row['id_materia_gestore'] ?? 0);
        if (
            ($subjectId > 0 && mastercomDebtsIsNoCourseExpectedSubjectId($subjectId))
            || mastercomDebtsIsNoCourseExpectedSubjectName((string)($row['materia_codice'] ?? ''))
            || mastercomDebtsIsNoCourseExpectedSubjectName((string)($row['materia_gestore'] ?? ''))
        ) {
            continue;
        }

        $tabelloneId = intval($row['tabellone_id'] ?? 0);
        $studentId = intval($row['id_studente_gestore'] ?? 0);
        $matchedExpected = null;
        if ($tabelloneId > 0 && $studentId > 0 && $subjectId > 0) {
            $matchedExpected = $expectedByStudentId[$tabelloneId . '|' . $studentId . '|' . $subjectId] ?? null;
        }
        if ($matchedExpected === null && $tabelloneId > 0 && $subjectId > 0) {
            $studentKey = mastercomAdminNormCompact((string)($row['studente_nome'] ?? ''));
            if ($studentKey !== '') {
                $matchedExpected = $expectedByStudentName[$tabelloneId . '|' . $studentKey . '|' . $subjectId] ?? null;
            }
        }
        if ($matchedExpected === null) {
            $matchedExpected = mastercomTabelloniFindExpectedByCompatibleStudentName(
                $expectedRows,
                $tabelloneId,
                $subjectId,
                (string)($row['studente_nome'] ?? '')
            );
        }
        if ($matchedExpected === null) {
            $matchedExpected = mastercomTabelloniFindExpectedForDebt($expectedRows, $row, $subjectId);
        }
        if ($matchedExpected !== null) {
            continue;
        }

        $issues[] = array_merge($row, [
            'tipo' => 'CARENZA_IMPORTATA_NON_NEL_TABELLONE',
            'valore' => '',
            'valore_num' => null,
            'messaggio' => 'L import carenze MasterCom contiene questa carenza, ma il tabellone finale non mostra un 4 o 5 per studente promosso.',
        ]);
    }

    return array_slice($issues, 0, max(1, $limit));
}

function mastercomTabelloniAuditStats(int $schoolYearId = 0, int $mastercomClassId = 0): array
{
    $rows = mastercomTabelloniAuditRows($schoolYearId, $mastercomClassId, 10000);
    $stats = [
        'totale' => count($rows),
        'manca_import_carenze' => 0,
        'carenza_importata_non_nel_tabellone' => 0,
        'materia_non_abbinata' => 0,
        'studente_non_abbinato' => 0,
    ];
    foreach ($rows as $row) {
        $key = strtolower((string)($row['tipo'] ?? ''));
        if (isset($stats[$key])) {
            $stats[$key]++;
        }
    }
    return $stats;
}

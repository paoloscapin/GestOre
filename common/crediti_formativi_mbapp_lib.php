<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/connectMBApp.php';

function cfm_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function cfm_mb_escape($value)
{
    global $__conMBApp;
    return mysqli_real_escape_string($__conMBApp, (string)$value);
}

function cfm_mb_q($value)
{
    return "'" . cfm_mb_escape($value) . "'";
}

function cfm_table_exists($table)
{
    $table = preg_replace('/[^A-Za-z0-9_]+/', '', (string)$table);
    if ($table === '') {
        return false;
    }

    $row = mb_dbGetFirst("SHOW TABLES LIKE " . cfm_mb_q($table));
    return is_array($row) && !empty($row);
}

function cfm_columns_map($table = 'studente')
{
    static $columnsByTable = [];

    $table = preg_replace('/[^A-Za-z0-9_]+/', '', (string)$table);
    if ($table === '') {
        $table = 'studente';
    }

    if (!isset($columnsByTable[$table])) {
        $columns = [];
        $rows = mb_dbGetAll("SHOW COLUMNS FROM `$table`") ?: [];
        foreach ($rows as $row) {
            $field = (string)($row['Field'] ?? '');
            if ($field !== '') {
                $columns[strtolower($field)] = $field;
            }
        }
        $columnsByTable[$table] = $columns;
    }

    return $columnsByTable[$table];
}

function cfm_actual_column($column, $table = 'studente')
{
    $columns = cfm_columns_map($table);
    $key = strtolower((string)$column);
    return $columns[$key] ?? null;
}

function cfm_column_exists($column, $table = 'studente')
{
    return cfm_actual_column($column, $table) !== null;
}

function cfm_select_expr($column, $alias = null, $table = 'studente', $tableAlias = 's')
{
    $alias = $alias ?: $column;
    $actual = cfm_actual_column($column, $table);
    if ($actual !== null) {
        return "`$tableAlias`.`$actual` AS `$alias`";
    }
    return "'' AS `$alias`";
}

function cfm_is_triennio_class($class)
{
    return preg_match('/^\s*[345]/', (string)$class) === 1;
}

function cfm_natural_class_sort($a, $b)
{
    return strnatcasecmp((string)$a, (string)$b);
}

function cfm_gestore_year_id($year)
{
    if (!function_exists('dbGetValue') || !function_exists('dbEscape')) {
        return 0;
    }

    $year = cfm_normalize_year_label($year);
    if ($year === '') {
        return 0;
    }

    return intval(dbGetValue("SELECT id FROM anno_scolastico WHERE anno = '" . dbEscape($year) . "' LIMIT 1"));
}

function cfm_gestore_classes($year)
{
    if (!function_exists('dbGetAll')) {
        return [];
    }

    $yearId = cfm_gestore_year_id($year);
    if ($yearId <= 0) {
        return [];
    }

    $rows = dbGetAll("
        SELECT DISTINCT c.classe
        FROM studente_frequenta sf
        INNER JOIN classi c ON c.id = sf.id_classe
        WHERE sf.id_anno_scolastico = " . intval($yearId) . "
          AND c.classe IS NOT NULL
          AND TRIM(c.classe) <> ''
        ORDER BY c.classe ASC
    ") ?: [];

    $classes = [];
    foreach ($rows as $row) {
        $class = trim((string)($row['classe'] ?? ''));
        if ($class !== '' && cfm_is_triennio_class($class)) {
            $classes[] = $class;
        }
    }

    $classes = array_values(array_unique($classes));
    usort($classes, 'cfm_natural_class_sort');
    return $classes;
}

function cfm_current_year_label()
{
    global $__anno_scolastico_corrente_anno;
    return trim((string)($__anno_scolastico_corrente_anno ?? ''));
}

function cfm_normalize_year_label($year)
{
    $year = trim((string)$year);
    if (preg_match('/^(\d{4})[\/-](\d{4})$/', $year, $m)) {
        return $m[1] . '/' . $m[2];
    }
    if (preg_match('/^(\d{2})[\/-](\d{2})$/', $year, $m)) {
        return '20' . $m[1] . '/20' . $m[2];
    }
    return $year;
}

function cfm_history_code_from_year($year)
{
    $year = cfm_normalize_year_label($year);
    if (preg_match('/^(\d{4})\/(\d{4})$/', $year, $m)) {
        return substr($m[1], -2) . '-' . substr($m[2], -2);
    }
    return '';
}

function cfm_year_source($year)
{
    $year = cfm_normalize_year_label($year);
    $current = cfm_current_year_label();
    if ($year === '' || ($current !== '' && $year === $current)) {
        return ['table' => 'studente', 'history_code' => '', 'current' => true, 'enabled' => true];
    }

    $code = cfm_history_code_from_year($year);
    $enabled = $code !== '' && cfm_table_exists('storicostudenti');
    if ($enabled) {
        $annoColumn = cfm_actual_column('anno_scolastico', 'storicostudenti');
        $enabled = $annoColumn !== null && intval(mb_dbGetValue("
            SELECT COUNT(*)
            FROM storicostudenti
            WHERE `$annoColumn` = " . cfm_mb_q($code) . "
        ")) > 0;
    }

    return ['table' => 'storicostudenti', 'history_code' => $code, 'current' => false, 'enabled' => $enabled];
}

function cfm_year_options()
{
    $current = cfm_current_year_label();
    $labels = [];

    if (function_exists('dbGetAll')) {
        foreach (dbGetAll("SELECT anno FROM anno_scolastico ORDER BY id DESC") ?: [] as $row) {
            $label = cfm_normalize_year_label($row['anno'] ?? '');
            if ($label !== '') {
                $labels[] = $label;
            }
        }
    }

    if ($current !== '') {
        array_unshift($labels, $current);
    }

    if (cfm_table_exists('storicostudenti')) {
        $annoColumn = cfm_actual_column('anno_scolastico', 'storicostudenti');
        if ($annoColumn !== null) {
            foreach (mb_dbGetAll("SELECT DISTINCT `$annoColumn` AS anno_scolastico FROM storicostudenti ORDER BY `$annoColumn` DESC") ?: [] as $row) {
                $label = cfm_normalize_year_label($row['anno_scolastico'] ?? '');
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }
    }

    $labels = array_values(array_unique($labels));
    $options = [];
    foreach ($labels as $label) {
        $source = cfm_year_source($label);
        $isCurrent = $source['current'];
        $options[] = [
            'value' => $label,
            'label' => 'A.S. ' . $label . ($source['enabled'] ? '' : ' (dati non disponibili)'),
            'enabled' => $source['enabled'],
            'current' => $isCurrent,
        ];
    }

    return $options;
}

function cfm_year_enabled($year)
{
    $source = cfm_year_source($year);
    return !empty($source['enabled']);
}

function cfm_classes($year = null)
{
    $gestoreClasses = cfm_gestore_classes($year);
    if (!empty($gestoreClasses)) {
        return $gestoreClasses;
    }

    $source = cfm_year_source($year);
    if (!$source['enabled']) {
        return [];
    }

    $table = $source['table'];
    $classeColumn = cfm_actual_column('classe', $table);
    if ($classeColumn === null) {
        return [];
    }

    $where = '';
    if (!$source['current']) {
        $annoColumn = cfm_actual_column('anno_scolastico', $table);
        if ($annoColumn === null || $source['history_code'] === '') {
            return [];
        }
        $where = " AND `$annoColumn` = " . cfm_mb_q($source['history_code']);
    }

    $rows = mb_dbGetAll("
        SELECT DISTINCT `$classeColumn` AS classe
        FROM `$table`
        WHERE `$classeColumn` IS NOT NULL
          AND TRIM(`$classeColumn`) <> ''
          $where
        ORDER BY `$classeColumn` ASC
    ") ?: [];

    $classes = [];
    foreach ($rows as $row) {
        $class = trim((string)($row['classe'] ?? ''));
        if ($class !== '' && cfm_is_triennio_class($class)) {
            $classes[] = $class;
        }
    }

    $classes = array_values(array_unique($classes));
    usort($classes, 'cfm_natural_class_sort');
    return $classes;
}

function cfm_all_columns()
{
    return [
        'cognome' => 'Cognome',
        'nome' => 'Nome',
        'esito' => 'Esito',
        'media' => 'Media',
        'assenze' => 'Assenze',
        'interesse' => 'Interesse',
        'crediti_formativi' => 'Crediti formativi',
        'IRC' => 'IRC',
        'ASL_positivo' => 'ASL positivo',
        'credito' => 'Credito',
        'credito_precedente' => 'Credito precedente',
        'integrazione' => 'Integrazione',
    ];
}

function cfm_main_columns()
{
    return [
        'rownum' => 'N.',
    ] + cfm_all_columns();
}

function cfm_format_date($value)
{
    $value = trim((string)($value ?? ''));
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }

    try {
        return (new DateTime($value))->format('d/m/Y');
    } catch (Exception $e) {
        return $value;
    }
}

function cfm_row_value(array $row, $key)
{
    if ($key === 'studente') {
        return trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
    }
    if ($key === 'tirocinio') {
        $parts = [];
        if (trim((string)($row['idTirocinio'] ?? '')) !== '') {
            $parts[] = 'ID ' . trim((string)$row['idTirocinio']);
        }
        if (trim((string)($row['tutorScolastico'] ?? '')) !== '') {
            $parts[] = 'Scolastico: ' . trim((string)$row['tutorScolastico']);
        }
        if (trim((string)($row['tutorAziendale'] ?? '')) !== '') {
            $parts[] = 'Aziendale: ' . trim((string)$row['tutorAziendale']);
        }
        return implode("\n", $parts);
    }
    if ($key === 'periodo') {
        $inizio = cfm_format_date($row['dataInizio'] ?? '');
        $fine = cfm_format_date($row['dataFine'] ?? '');
        if ($inizio !== '' && $fine !== '') {
            return $inizio . ' - ' . $fine;
        }
        return $inizio ?: $fine;
    }
    if ($key === 'dataInizio' || $key === 'dataFine') {
        return cfm_format_date($row[$key] ?? '');
    }
    return (string)($row[$key] ?? '');
}

function cfm_rows($class, $year = null)
{
    $class = trim((string)$class);
    $source = cfm_year_source($year);
    if ($class === '' || !$source['enabled']) {
        return [];
    }

    $table = $source['table'];
    $classeColumn = cfm_actual_column('classe', $table);
    if ($classeColumn === null) {
        return [];
    }

    $select = [];
    foreach (array_keys(cfm_all_columns()) as $column) {
        if ($column === 'cognome' || $column === 'nome') {
            $select[] = cfm_select_expr($column, $column, 'utente', 'u');
        } else {
            $select[] = cfm_select_expr($column, $column, $table, 's');
        }
    }

    $idStudenteColumn = cfm_actual_column('idStudente', $table);
    $utenteUsernameColumn = cfm_actual_column('username', 'utente');
    $order = [];
    foreach (['cognome', 'nome'] as $column) {
        $actual = cfm_actual_column($column, 'utente');
        if ($actual !== null) {
            $order[] = "`u`.`$actual` ASC";
        }
    }
    if ($idStudenteColumn !== null) {
        $order[] = "`s`.`$idStudenteColumn` ASC";
    }
    if (empty($order)) {
        $order[] = "`s`.`$classeColumn` ASC";
    }

    $joinUtente = '';
    if ($idStudenteColumn !== null && $utenteUsernameColumn !== null) {
        $joinUtente = "LEFT JOIN utente u ON u.`$utenteUsernameColumn` = CAST(s.`$idStudenteColumn` AS CHAR)";
    } else {
        $joinUtente = "LEFT JOIN utente u ON 1=0";
    }

    $where = "s.`$classeColumn` = " . cfm_mb_q($class);
    if (!$source['current']) {
        $annoColumn = cfm_actual_column('anno_scolastico', $table);
        if ($annoColumn === null || $source['history_code'] === '') {
            return [];
        }
        $where .= " AND s.`$annoColumn` = " . cfm_mb_q($source['history_code']);
    }

    return mb_dbGetAll("
        SELECT " . implode(",\n               ", $select) . "
        FROM `$table` s
        $joinUtente
        WHERE $where
        ORDER BY " . implode(', ', $order) . "
    ") ?: [];
}

function cfm_clean_filename($value)
{
    $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$value);
    $value = trim($value, '_');
    return $value !== '' ? $value : 'classe';
}

?>

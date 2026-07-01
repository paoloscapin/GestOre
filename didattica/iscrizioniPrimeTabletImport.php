<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function iscrizioniPrimeTabletImportFail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function ipt_xlsx_column_index(string $cellRef): int
{
    if (!preg_match('/^([A-Z]+)/i', $cellRef, $matches)) {
        return 0;
    }
    $letters = strtoupper($matches[1]);
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }
    return $index;
}

function ipt_xlsx_text($node): string
{
    $node->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $parts = [];
    foreach ($node->xpath('.//main:t') ?: [] as $textNode) {
        $parts[] = (string)$textNode;
    }
    return implode('', $parts);
}

function ipt_xlsx_read_grid(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Estensione PHP ZipArchive non disponibile.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('File Excel non leggibile.');
    }

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml !== false) {
            $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xml->si as $si) {
                $shared[] = ipt_xlsx_text($si);
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        throw new RuntimeException('Foglio Excel principale non trovato.');
    }
    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('Foglio Excel non leggibile.');
    }

    $grid = [];
    foreach ($sheet->sheetData->row as $row) {
        $rowIndex = intval((string)$row['r']);
        foreach ($row->c as $cell) {
            $cellRef = (string)$cell['r'];
            $colIndex = ipt_xlsx_column_index($cellRef);
            if ($rowIndex <= 0 || $colIndex <= 0) {
                continue;
            }
            $type = (string)$cell['t'];
            if ($type === 's') {
                $value = $shared[intval((string)$cell->v)] ?? '';
            } elseif ($type === 'inlineStr') {
                $cell->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $value = ipt_xlsx_text($cell);
            } else {
                $value = (string)$cell->v;
            }
            $grid[$rowIndex][$colIndex] = trim($value);
        }
    }
    $zip->close();
    return $grid;
}

function ipt_cell_text(array $grid, int $row, int $col): string
{
    $value = $grid[$row][$col] ?? '';
    if ($value === null) {
        return '';
    }
    return trim((string)$value);
}

function ipt_row_record(array $grid, int $row, int $offset, string $status, string $group): ?array
{
    $position = ipt_cell_text($grid, $row, $offset);
    $code = ipt_cell_text($grid, $row, $offset + 1);
    $surname = ipt_cell_text($grid, $row, $offset + 3);
    $name = ipt_cell_text($grid, $row, $offset + 4);
    $cf = strtoupper(ipt_cell_text($grid, $row, $offset + 5));
    if ($cf === '' || !preg_match('/^[A-Z0-9]{16}$/', $cf)) {
        return null;
    }
    return [
        'position' => $position !== '' ? intval($position) : null,
        'code' => $code,
        'surname' => $surname,
        'name' => $name,
        'cf' => $cf,
        'status' => $status,
        'group' => $group,
    ];
}

if (empty($_FILES['tablet_xlsx']['tmp_name']) || !is_uploaded_file($_FILES['tablet_xlsx']['tmp_name'])) {
    iscrizioniPrimeTabletImportFail('Caricare il file Excel TABLET.xlsx.');
}

try {
    iscrizioniPrimeEnsureSchema();
    $grid = ipt_xlsx_read_grid($_FILES['tablet_xlsx']['tmp_name']);
    $maxRow = max(array_keys($grid));
    $records = [];

    for ($row = 4; $row <= min($maxRow, 90); $row++) {
        $confirmed = ipt_row_record($grid, $row, 1, 'confermato', 'ipad');
        if ($confirmed !== null) {
            $records[] = $confirmed;
        }
        $excluded = ipt_row_record($grid, $row, 8, 'escluso', 'ipad');
        if ($excluded !== null) {
            $records[] = $excluded;
        }
    }
    for ($row = 95; $row <= $maxRow; $row++) {
        $digital = ipt_row_record($grid, $row, 1, 'confermato', 'digital_science');
        if ($digital !== null) {
            $records[] = $digital;
        }
    }

    if (empty($records)) {
        iscrizioniPrimeTabletImportFail('Nessuno studente tablet riconosciuto nel file.');
    }

    $seen = [];
    $matched = 0;
    $unmatched = [];
    $confirmed = 0;
    $excluded = 0;
    $digitalScience = 0;

    dbExec("START TRANSACTION");
    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET tablet_scelto = 0,
            tablet_stato = '',
            tablet_gruppo = NULL,
            tablet_posizione = NULL,
            tablet_ripescato_da_pratica_id = NULL,
            updated_at = NOW()
        WHERE tipo_iscrizione = 'prime'
          AND tablet_stato <> 'rinuncia'
          AND tablet_acquistato = 0
          AND tablet_ripescato_da_pratica_id IS NULL
    ");

    foreach ($records as $record) {
        if (isset($seen[$record['cf']])) {
            continue;
        }
        $seen[$record['cf']] = true;
        $pratica = dbGetFirst("
            SELECT id, cognome, nome
            FROM iscrizioni_prime_pratiche
            WHERE tipo_iscrizione = 'prime'
              AND UPPER(TRIM(codice_fiscale)) = " . dbQ($record['cf']) . "
            LIMIT 1
        ");
        if (!$pratica) {
            $unmatched[] = trim($record['surname'] . ' ' . $record['name']) . ' - ' . $record['cf'];
            continue;
        }
        $manualState = dbGetFirst("
            SELECT tablet_stato, tablet_acquistato, tablet_ripescato_da_pratica_id
            FROM iscrizioni_prime_pratiche
            WHERE id = " . dbI($pratica['id']) . "
            LIMIT 1
        ") ?: [];
        if (
            (string)($manualState['tablet_stato'] ?? '') === 'rinuncia'
            || intval($manualState['tablet_acquistato'] ?? 0) === 1
            || intval($manualState['tablet_ripescato_da_pratica_id'] ?? 0) > 0
        ) {
            $matched++;
            continue;
        }
        dbExec("
            UPDATE iscrizioni_prime_pratiche
            SET tablet_scelto = 1,
                tablet_stato = " . dbQ($record['status']) . ",
                tablet_gruppo = " . dbQ($record['group']) . ",
                tablet_posizione = " . dbI($record['position']) . ",
                updated_at = NOW()
            WHERE id = " . dbI($pratica['id']) . "
        ");
        iscrizioniPrimeTabletRecordEvent((int)$pratica['id'], 'Stato tablet importato da Excel', [
            'stato' => $record['status'],
            'gruppo' => $record['group'],
            'posizione' => $record['position'],
            'codice_domanda_excel' => $record['code'],
        ]);
        $matched++;
        if ($record['status'] === 'confermato') {
            $confirmed++;
        } else {
            $excluded++;
        }
        if ($record['group'] === 'digital_science') {
            $digitalScience++;
        }
    }
    dbExec("COMMIT");

    echo json_encode([
        'ok' => true,
        'message' => 'Import tablet completato.',
        'rows' => count($records),
        'matched' => $matched,
        'confirmed' => $confirmed,
        'excluded' => $excluded,
        'digital_science' => $digitalScience,
        'unmatched' => $unmatched,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    try {
        dbExec("ROLLBACK");
    } catch (Throwable $ignored) {
    }
    iscrizioniPrimeTabletImportFail($e->getMessage(), 500);
}

<?php

require_once __DIR__ . '/connect.php';

function scuoleIstitutiEnsureTable(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS scuole_istituti (
            id INT NOT NULL AUTO_INCREMENT,
            codice_esterno INT NULL,
            nome VARCHAR(255) NOT NULL,
            attivo TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_scuole_istituti_codice (codice_esterno),
            KEY idx_scuole_istituti_nome (nome),
            KEY idx_scuole_istituti_attivo (attivo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    scuoleIstitutiImportSeed();
}

function scuoleIstitutiImportSeed(): void
{
    $path = __DIR__ . '/../data/elenchi/elenco_istituti.csv';
    if (!is_file($path)) {
        return;
    }

    $handle = fopen($path, 'r');
    if (!$handle) {
        return;
    }

    $first = true;
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        if ($first) {
            $first = false;
            continue;
        }
        $codice = intval($row[0] ?? 0);
        $nome = trim((string)($row[1] ?? ''));
        if ($codice <= 0 || $nome === '') {
            continue;
        }
        dbExec("
            INSERT INTO scuole_istituti (codice_esterno, nome, attivo, created_at, updated_at)
            VALUES (" . dbI($codice) . ", " . dbQ($nome) . ", 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                nome = VALUES(nome),
                attivo = 1,
                updated_at = NOW()
        ");
    }
    fclose($handle);
}

function scuoleIstitutiAll(): array
{
    scuoleIstitutiEnsureTable();
    return dbGetAll("
        SELECT id, codice_esterno, nome
        FROM scuole_istituti
        WHERE attivo = 1
        ORDER BY nome ASC
    ") ?: [];
}

function scuoleIstitutiNameById($id): string
{
    $id = intval($id);
    if ($id <= 0) {
        return '';
    }
    scuoleIstitutiEnsureTable();
    return trim((string)(dbGetValue("
        SELECT nome
        FROM scuole_istituti
        WHERE id = " . dbI($id) . "
          AND attivo = 1
        LIMIT 1
    ") ?? ''));
}

function scuoleIstitutiSelectOptionsHtml($selectedId, string $fallbackName = ''): string
{
    $selectedId = intval($selectedId);
    $fallbackName = trim($fallbackName);
    $html = '<option value="">Seleziona istituto</option>';
    $hasSelected = $selectedId <= 0;
    foreach (scuoleIstitutiAll() as $istituto) {
        $id = intval($istituto['id'] ?? 0);
        $selected = $id === $selectedId ? ' selected' : '';
        if ($selected !== '') {
            $hasSelected = true;
        }
        $html .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars((string)$istituto['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    if (!$hasSelected && $fallbackName !== '') {
        $html .= '<option value="" selected>' . htmlspecialchars($fallbackName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' (non in elenco)</option>';
    }
    return $html;
}

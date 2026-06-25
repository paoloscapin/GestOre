<?php

require_once __DIR__ . '/connect.php';

function programmiSvoltiCompletezzaConfigArray(string $key): array
{
    global $__settings;

    $cfg = $__settings->programmiSvolti ?? null;
    if (!$cfg || !isset($cfg->$key)) {
        return [];
    }

    $value = $cfg->$key;
    if (is_array($value)) {
        return $value;
    }
    if ($value instanceof Traversable) {
        return iterator_to_array($value);
    }
    if (is_object($value)) {
        return (array)$value;
    }

    return [];
}

function programmiSvoltiCompletezzaFlatList($value): array
{
    if ($value === null) {
        return [];
    }
    if (!is_array($value)) {
        $value = [$value];
    }

    $out = [];
    foreach ($value as $item) {
        $item = trim((string)$item);
        if ($item !== '') {
            $out[] = $item;
        }
    }
    return array_values(array_unique($out));
}

function programmiSvoltiCompletezzaDocentiEsclusiWhere(string $alias = 'd'): string
{
    $cfg = programmiSvoltiCompletezzaConfigArray('docenti_esclusi_completezza');
    $legacy = programmiSvoltiCompletezzaConfigArray('docenti_sostegno_ignora');
    if (!$cfg && $legacy) {
        $cfg = $legacy;
    }

    $ids = array_values(array_filter(array_map('intval', programmiSvoltiCompletezzaFlatList($cfg['ids'] ?? $cfg['id'] ?? [])), fn($id) => $id > 0));
    $emails = programmiSvoltiCompletezzaFlatList($cfg['emails'] ?? $cfg['email'] ?? []);
    $usernames = programmiSvoltiCompletezzaFlatList($cfg['usernames'] ?? $cfg['username'] ?? []);
    $nomi = programmiSvoltiCompletezzaFlatList($cfg['nomi'] ?? $cfg['names'] ?? $cfg['docenti'] ?? []);

    $conditions = [];
    if ($ids) {
        $conditions[] = "$alias.id NOT IN (" . implode(',', $ids) . ")";
    }
    if ($emails) {
        $conditions[] = "LOWER(TRIM($alias.email)) NOT IN (" . implode(',', array_map(fn($v) => dbQ(strtolower(trim($v))), $emails)) . ")";
    }
    if ($usernames) {
        $conditions[] = "LOWER(TRIM($alias.username)) NOT IN (" . implode(',', array_map(fn($v) => dbQ(strtolower(trim($v))), $usernames)) . ")";
    }
    if ($nomi) {
        $conditions[] = "LOWER(TRIM(CONCAT($alias.cognome, ' ', $alias.nome))) NOT IN (" . implode(',', array_map(fn($v) => dbQ(strtolower(trim($v))), $nomi)) . ")";
    }

    return $conditions ? implode(' AND ', $conditions) : '1=1';
}

function programmiSvoltiCompletezzaModuloHaContenuto(string $contenuto): bool
{
    $contenuto = trim($contenuto);
    if ($contenuto === '') {
        return false;
    }

    $decoded = json_decode($contenuto, true);
    if (is_array($decoded)) {
        foreach (['competenze_raggiunte', 'contenuti_trattati', 'abilita'] as $key) {
            if (trim(strip_tags((string)($decoded[$key] ?? ''))) !== '') {
                return true;
            }
        }
        foreach (['competenze_raggiunte_html', 'contenuti_trattati_html', 'abilita_html'] as $key) {
            if (trim(strip_tags((string)($decoded[$key] ?? ''))) !== '') {
                return true;
            }
        }
        return false;
    }

    return trim(strip_tags($contenuto)) !== '';
}

function programmiSvoltiCompletezzaRighe(array $filters = []): array
{
    global $__anno_scolastico_corrente_id;

    $annoId = intval($filters['anno_id'] ?? 0);
    if ($annoId <= 0) {
        $annoId = intval($__anno_scolastico_corrente_id ?? 0);
    }

    $where = [
        'di.id_anno_scolastico = ' . intval($annoId),
        'c.attiva = 1',
        'd.attivo = 1',
        programmiSvoltiCompletezzaDocentiEsclusiWhere('d'),
        "UPPER(TRIM(m.nome)) NOT IN ('SOSTEGNO', 'SCIENCE E TECNOLOGIE APPLICATE', 'SCIENZE E TECNOLOGIE APPLICATE')",
    ];

    $classeId = intval($filters['classe_id'] ?? 0);
    $materiaId = intval($filters['materia_id'] ?? 0);
    $docenteId = intval($filters['docente_id'] ?? 0);
    if ($classeId > 0) {
        $where[] = 'di.id_classe = ' . intval($classeId);
    }
    if ($materiaId > 0) {
        $where[] = 'di.id_materia = ' . intval($materiaId);
    }
    if ($docenteId > 0) {
        $where[] = 'di.id_docente = ' . intval($docenteId);
    }

    $rows = dbGetAll("
        SELECT
            di.id_docente,
            di.id_classe,
            di.id_materia,
            c.classe AS classe_nome,
            c.anno AS classe_anno,
            m.nome AS materia_nome,
            d.cognome AS docente_cognome,
            d.nome AS docente_nome,
            d.email AS docente_email,
            ps.id AS programma_id,
            ps.updated AS programma_updated,
            psm.id AS modulo_id,
            psm.contenuto AS modulo_contenuto
        FROM docente_insegna di
        INNER JOIN docente d ON d.id = di.id_docente
        INNER JOIN classi c ON c.id = di.id_classe
        INNER JOIN materia m ON m.id = di.id_materia
        LEFT JOIN programmi_svolti ps
            ON ps.id_docente = di.id_docente
           AND ps.id_materia = di.id_materia
           AND ps.id_anno_scolastico = di.id_anno_scolastico
           AND (
                ps.id_classe = di.id_classe
                OR EXISTS (
                    SELECT 1
                    FROM programmi_svolti_classi psc
                    WHERE psc.id_programma_svolto = ps.id
                      AND psc.id_classe = di.id_classe
                    LIMIT 1
                )
           )
        LEFT JOIN programmi_svolti_moduli psm ON psm.id_programma = ps.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.classe ASC, d.cognome ASC, d.nome ASC, m.nome ASC, ps.id ASC, psm.ordine ASC
    ");

    $items = [];
    foreach ($rows ?: [] as $row) {
        $key = intval($row['id_docente']) . ':' . intval($row['id_classe']) . ':' . intval($row['id_materia']);
        if (!isset($items[$key])) {
            $items[$key] = [
                'docente_id' => intval($row['id_docente']),
                'classe_id' => intval($row['id_classe']),
                'materia_id' => intval($row['id_materia']),
                'classe' => (string)$row['classe_nome'],
                'materia' => (string)$row['materia_nome'],
                'docente' => trim((string)$row['docente_cognome'] . ' ' . (string)$row['docente_nome']),
                'email' => trim((string)$row['docente_email']),
                'programma_ids' => [],
                'moduli' => 0,
                'moduli_compilati' => 0,
                'ultimo_aggiornamento' => '',
            ];
        }

        $programmaId = intval($row['programma_id'] ?? 0);
        if ($programmaId > 0) {
            $items[$key]['programma_ids'][$programmaId] = true;
            if (!empty($row['programma_updated'])) {
                $items[$key]['ultimo_aggiornamento'] = (string)$row['programma_updated'];
            }
        }
        if (intval($row['modulo_id'] ?? 0) > 0) {
            $items[$key]['moduli']++;
            if (programmiSvoltiCompletezzaModuloHaContenuto((string)($row['modulo_contenuto'] ?? ''))) {
                $items[$key]['moduli_compilati']++;
            }
        }
    }

    $missing = [];
    foreach ($items as $item) {
        $item['programmi'] = count($item['programma_ids']);
        unset($item['programma_ids']);
        if ($item['programmi'] <= 0) {
            $item['stato'] = 'Programma non inserito';
            $missing[] = $item;
        } elseif ($item['moduli_compilati'] <= 0) {
            $item['stato'] = 'Programma vuoto';
            $missing[] = $item;
        }
    }

    return $missing;
}

function programmiSvoltiCompletezzaRighePerDocente(array $rows): array
{
    $grouped = [];
    foreach ($rows as $row) {
        $key = intval($row['docente_id']);
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'docente' => $row['docente'],
                'email' => $row['email'],
                'righe' => [],
            ];
        }
        $grouped[$key]['righe'][] = $row;
    }
    return $grouped;
}

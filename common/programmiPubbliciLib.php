<?php

require_once __DIR__ . '/connect.php';

function programmiPubbliciH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function programmiPubbliciTypeConfig(string $type): ?array
{
    if ($type === 'materie') {
        return [
            'table' => 'programma_materie',
            'config_keys' => ['programmaMaterie', 'programmiMaterie'],
            'settings_key' => 'programmiMaterie',
            'label' => 'Programmi didattica',
            'print' => '../didattica/stampaProgramma.php',
            'title' => 'Programma didattico',
        ];
    }
    if ($type === 'minimi') {
        return [
            'table' => 'programma_minimi',
            'config_keys' => ['programmiMinimi'],
            'settings_key' => 'programmiMinimi',
            'label' => 'Programmi obiettivi minimi',
            'print' => '../didattica/stampaProgrammaMinimi.php',
            'title' => 'Programmi obiettivi minimi',
        ];
    }
    if ($type === 'svolti') {
        return [
            'table' => 'programmi_svolti',
            'config_keys' => ['programmiSvolti'],
            'settings_key' => 'programmiSvolti',
            'label' => 'Programmi svolti',
            'print' => '../didattica/stampaProgrammiSvolti.php',
            'title' => 'Programma svolto',
        ];
    }
    return null;
}

function programmiPubbliciModuleEnabled(string $type): bool
{
    $config = programmiPubbliciTypeConfig($type);
    if ($config == null) {
        return false;
    }

    foreach (($config['config_keys'] ?? []) as $key) {
        if (getSettingsValue('config', $key, false)) {
            return true;
        }
    }

    return false;
}

function programmiPubbliciVisibleForRole(string $type, string $role): bool
{
    $config = programmiPubbliciTypeConfig($type);
    if ($config == null || !programmiPubbliciModuleEnabled($type)) {
        return false;
    }

    $settingsKey = (string)($config['settings_key'] ?? '');
    if ($settingsKey === '') {
        return false;
    }

    if ($role === 'studente') {
        return getSettingsValue($settingsKey, 'visibile_studenti', false);
    }
    if ($role === 'genitore') {
        return getSettingsValue($settingsKey, 'visibile_genitori', false);
    }

    return false;
}

function programmiPubbliciAnyVisibleForRole(string $role): bool
{
    return programmiPubbliciVisibleForRole('materie', $role)
        || programmiPubbliciVisibleForRole('minimi', $role)
        || programmiPubbliciVisibleForRole('svolti', $role);
}

function programmiPubbliciClassYearFromLabel(string $className): int
{
    if (preg_match('/([1-5])/', $className, $matches)) {
        return intval($matches[1]);
    }
    return 0;
}

function programmiPubbliciStudentContext(int $studentId): ?array
{
    global $__anno_scolastico_corrente_id;

    if ($studentId <= 0) {
        return null;
    }

    $row = dbGetFirst("
        SELECT
            s.id AS studente_id,
            s.nome AS studente_nome,
            s.cognome AS studente_cognome,
            sf.id_classe,
            c.classe,
            c.anno AS classe_anno,
            c.id_primo_indirizzo,
            c.id_secondo_indirizzo,
            i1.nome AS primo_indirizzo,
            i2.nome AS secondo_indirizzo
        FROM studente s
        INNER JOIN studente_frequenta sf
            ON sf.id_studente = s.id
           AND sf.id_anno_scolastico = " . dbI((int)$__anno_scolastico_corrente_id) . "
        INNER JOIN classi c
            ON c.id = sf.id_classe
        LEFT JOIN indirizzo i1
            ON i1.id = c.id_primo_indirizzo
        LEFT JOIN indirizzo i2
            ON i2.id = c.id_secondo_indirizzo
        WHERE s.id = " . dbI($studentId) . "
          AND s.attivo = 1
        LIMIT 1
    ");

    if ($row == null) {
        return null;
    }

    $year = intval($row['classe_anno'] ?? 0);
    if ($year <= 0) {
        $year = programmiPubbliciClassYearFromLabel((string)($row['classe'] ?? ''));
    }

    $addressIds = [];
    foreach (['id_primo_indirizzo', 'id_secondo_indirizzo'] as $key) {
        $id = intval($row[$key] ?? 0);
        if ($id > 0) {
            $addressIds[$id] = true;
        }
    }

    $addressLabels = [];
    foreach (['primo_indirizzo', 'secondo_indirizzo'] as $key) {
        $label = trim((string)($row[$key] ?? ''));
        if ($label !== '') {
            $addressLabels[$label] = true;
        }
    }

    $row['anno_programmi'] = $year;
    $row['indirizzi_ids'] = array_keys($addressIds);
    $row['indirizzi_label'] = implode(' / ', array_keys($addressLabels));

    return $row;
}

function programmiPubbliciGenitoreStudents(int $parentId): array
{
    if ($parentId <= 0) {
        return [];
    }

    $students = dbGetAll("
        SELECT s.id, s.cognome, s.nome
        FROM studente s
        INNER JOIN genitori_studenti gs
            ON gs.id_studente = s.id
        WHERE gs.id_genitore = " . dbI($parentId) . "
          AND s.attivo = 1
        ORDER BY s.cognome ASC, s.nome ASC
    ") ?: [];

    $result = [];
    foreach ($students as $student) {
        $context = programmiPubbliciStudentContext((int)$student['id']);
        if ($context != null) {
            $result[] = $context;
        }
    }

    return $result;
}

function programmiPubbliciGenitoreCanAccessStudent(int $parentId, int $studentId): bool
{
    if ($parentId <= 0 || $studentId <= 0) {
        return false;
    }

    $count = dbGetValue("
        SELECT COUNT(*)
        FROM genitori_studenti gs
        INNER JOIN studente s
            ON s.id = gs.id_studente
        WHERE gs.id_genitore = " . dbI($parentId) . "
          AND gs.id_studente = " . dbI($studentId) . "
          AND s.attivo = 1
    ");

    return intval($count) > 0;
}

function programmiPubbliciRowsForStudent(string $type, int $studentId, int $annoScolasticoId = 0): array
{
    $config = programmiPubbliciTypeConfig($type);
    $context = programmiPubbliciStudentContext($studentId);
    if ($config == null || $context == null) {
        return [];
    }

    $year = intval($context['anno_programmi'] ?? 0);
    if ($year <= 0) {
        return [];
    }

    if ($type === 'svolti') {
        return programmiPubbliciSvoltiRowsForStudent($studentId, $annoScolasticoId);
    }

    $table = $config['table'];
    $where = "WHERE p.anno = " . dbI($year) . " ";
    $addressIds = array_map('intval', $context['indirizzi_ids'] ?? []);
    $addressIds = array_values(array_filter($addressIds, function ($id) {
        return $id > 0;
    }));

    if (empty($addressIds)) {
        return [];
    }
    $where .= "AND p.id_indirizzo IN (" . implode(',', $addressIds) . ") ";

    return dbGetAll("
        SELECT
            p.id,
            p.anno,
            p.id_indirizzo,
            p.id_materia,
            p.updated,
            i.nome AS indirizzo_nome,
            m.nome AS materia_nome
        FROM $table p
        INNER JOIN indirizzo i
            ON i.id = p.id_indirizzo
        INNER JOIN materia m
            ON m.id = p.id_materia
        $where
        ORDER BY m.nome ASC, i.nome ASC
    ") ?: [];
}

function programmiPubbliciSvoltiYearsForStudent(int $studentId): array
{
    if ($studentId <= 0) {
        return [];
    }

    return dbGetAll("
        SELECT DISTINCT a.id, a.anno
        FROM studente_frequenta sf
        INNER JOIN anno_scolastico a
            ON a.id = sf.id_anno_scolastico
        WHERE sf.id_studente = " . dbI($studentId) . "
        ORDER BY a.id DESC
    ") ?: [];
}

function programmiPubbliciSvoltiRowsForStudent(int $studentId, int $annoScolasticoId = 0): array
{
    if ($studentId <= 0) {
        return [];
    }

    $whereAnno = $annoScolasticoId > 0 ? " AND ps.id_anno_scolastico = " . dbI($annoScolasticoId) . " " : "";

    return dbGetAll("
        SELECT DISTINCT
            ps.id,
            ps.id_anno_scolastico,
            ps.id_materia,
            ps.id_docente,
            ps.updated,
            a.anno AS anno_scolastico,
            c.classe AS classe_nome,
            m.nome AS materia_nome,
            d.cognome AS docente_cognome,
            d.nome AS docente_nome
        FROM studente_frequenta sf
        INNER JOIN programmi_svolti ps
            ON ps.id_anno_scolastico = sf.id_anno_scolastico
           AND (
                ps.id_classe = sf.id_classe
                OR EXISTS (
                    SELECT 1
                    FROM programmi_svolti_classi psc
                    WHERE psc.id_programma_svolto = ps.id
                      AND psc.id_classe = sf.id_classe
                )
           )
        INNER JOIN anno_scolastico a
            ON a.id = ps.id_anno_scolastico
        INNER JOIN classi c
            ON c.id = sf.id_classe
        INNER JOIN materia m
            ON m.id = ps.id_materia
        INNER JOIN docente d
            ON d.id = ps.id_docente
        WHERE sf.id_studente = " . dbI($studentId) . "
        $whereAnno
        ORDER BY a.id DESC, c.classe ASC, m.nome ASC, d.cognome ASC, d.nome ASC
    ") ?: [];
}

function programmiPubbliciCanAccessProgram(string $type, int $programId, int $studentId): bool
{
    if ($programId <= 0 || $studentId <= 0) {
        return false;
    }

    foreach (programmiPubbliciRowsForStudent($type, $studentId) as $row) {
        if (intval($row['id'] ?? 0) === $programId) {
            return true;
        }
    }

    return false;
}

function programmiPubbliciRenderContext(array $context): string
{
    $html = '<div class="programmi-pubblici-context">';
    $html .= '<span><span>Studente</span><strong>' . programmiPubbliciH(($context['studente_cognome'] ?? '') . ' ' . ($context['studente_nome'] ?? '')) . '</strong></span>';
    $html .= '<span><span>Classe corrente</span><strong>' . programmiPubbliciH($context['classe'] ?? '') . '</strong></span>';
    $html .= '<span><span>Anno corrente</span><strong>' . intval($context['anno_programmi'] ?? 0) . '</strong></span>';
    if (intval($context['anno_programmi'] ?? 0) >= 3) {
        $html .= '<span><span>Indirizzo</span><strong>' . programmiPubbliciH($context['indirizzi_label'] ?? '') . '</strong></span>';
    }
    $html .= '</div>';

    return $html;
}

function programmiPubbliciRenderRows(array $rows, string $type, int $studentId): string
{
    $config = programmiPubbliciTypeConfig($type);
    if ($config == null) {
        return '';
    }
    if (empty($rows)) {
        return '<div class="alert alert-info">Nessun programma disponibile per questo percorso.</div>';
    }

    if ($type === 'svolti') {
        return programmiPubbliciRenderSvoltiRows($rows, $studentId);
    }

    $html = '<div class="table-responsive programmi-pubblici-table-wrap"><table class="table table-bordered table-striped table-green programmi-pubblici-table">';
    $html .= '<thead><tr><th class="text-center programmi-pubblici-number">N.</th><th>Materia</th><th>Anno</th><th>Indirizzo</th><th>Aggiornato</th><th>PDF</th></tr></thead><tbody>';
    $cards = '<div class="programmi-pubblici-cards">';
    $rowNumber = 0;
    foreach ($rows as $row) {
        $rowNumber++;
        $updated = trim((string)($row['updated'] ?? ''));
        if ($updated !== '') {
            $time = strtotime($updated);
            $updated = $time ? date('d/m/Y', $time) : $updated;
        }
        $href = $config['print']
            . '?id=' . intval($row['id'])
            . '&print=1'
            . '&titolo=' . urlencode($config['title'])
            . '&public_student_id=' . intval($studentId);
        $html .= '<tr>';
        $html .= '<td class="text-center programmi-pubblici-number">' . $rowNumber . '</td>';
        $html .= '<td>' . programmiPubbliciH($row['materia_nome'] ?? '') . '</td>';
        $html .= '<td class="text-center">' . programmiPubbliciH($row['anno'] ?? '') . '</td>';
        $html .= '<td>' . programmiPubbliciH($row['indirizzo_nome'] ?? '') . '</td>';
        $html .= '<td class="text-center">' . programmiPubbliciH($updated) . '</td>';
        $html .= '<td class="text-center"><a class="btn btn-primary btn-xs" target="_blank" href="' . programmiPubbliciH($href) . '"><span class="glyphicon glyphicon-download-alt"></span> PDF</a></td>';
        $html .= '</tr>';

        $cards .= '<div class="programmi-pubblici-card">';
        $cards .= '<div class="programmi-pubblici-card-title"><span class="programmi-pubblici-card-number">' . $rowNumber . '</span><span>' . programmiPubbliciH($row['materia_nome'] ?? '') . '</span></div>';
        $cards .= '<div class="programmi-pubblici-card-meta">';
        $cards .= '<div class="programmi-pubblici-card-meta-item"><span class="programmi-pubblici-card-label">Anno</span><span>' . programmiPubbliciH($row['anno'] ?? '') . '</span></div>';
        $cards .= '<div class="programmi-pubblici-card-meta-item"><span class="programmi-pubblici-card-label">Indirizzo</span><span>' . programmiPubbliciH($row['indirizzo_nome'] ?? '') . '</span></div>';
        $cards .= '<div class="programmi-pubblici-card-meta-item"><span class="programmi-pubblici-card-label">Aggiornato</span><span>' . programmiPubbliciH($updated !== '' ? $updated : '-') . '</span></div>';
        $cards .= '</div>';
        $cards .= '<a class="btn btn-primary btn-sm programmi-pubblici-card-action" target="_blank" href="' . programmiPubbliciH($href) . '"><span class="glyphicon glyphicon-download-alt"></span> Scarica PDF</a>';
        $cards .= '</div>';
    }
    $html .= '</tbody></table></div>';
    $cards .= '</div>';

    return $html . $cards;
}

function programmiPubbliciRenderSvoltiRows(array $rows, int $studentId): string
{
    $config = programmiPubbliciTypeConfig('svolti');
    if ($config == null) {
        return '';
    }

    $html = '<div class="table-responsive programmi-pubblici-table-wrap"><table class="table table-bordered table-striped table-green programmi-pubblici-table">';
    $html .= '<thead><tr><th class="text-center programmi-pubblici-number">N.</th><th>Anno scolastico</th><th>Classe</th><th>Materia</th><th>Docente</th><th>Aggiornato</th><th>PDF</th></tr></thead><tbody>';
    $cards = '<div class="programmi-pubblici-cards">';
    $rowNumber = 0;

    foreach ($rows as $row) {
        $rowNumber++;
        $updated = trim((string)($row['updated'] ?? ''));
        if ($updated !== '') {
            $time = strtotime($updated);
            $updated = $time ? date('d/m/Y', $time) : $updated;
        }
        $docente = trim((string)($row['docente_cognome'] ?? '') . ' ' . (string)($row['docente_nome'] ?? ''));
        $href = $config['print']
            . '?id=' . intval($row['id'])
            . '&print=1'
            . '&format=pdf'
            . '&view_scope=own'
            . '&titolo=' . urlencode($config['title'])
            . '&public_student_id=' . intval($studentId);

        $html .= '<tr>';
        $html .= '<td class="text-center programmi-pubblici-number">' . $rowNumber . '</td>';
        $html .= '<td class="text-center">' . programmiPubbliciH($row['anno_scolastico'] ?? '') . '</td>';
        $html .= '<td class="text-center">' . programmiPubbliciH($row['classe_nome'] ?? '') . '</td>';
        $html .= '<td>' . programmiPubbliciH($row['materia_nome'] ?? '') . '</td>';
        $html .= '<td>' . programmiPubbliciH($docente) . '</td>';
        $html .= '<td class="text-center">' . programmiPubbliciH($updated) . '</td>';
        $html .= '<td class="text-center"><a class="btn btn-primary btn-xs" target="_blank" href="' . programmiPubbliciH($href) . '"><span class="glyphicon glyphicon-download-alt"></span> PDF</a></td>';
        $html .= '</tr>';

        $cards .= '<div class="programmi-pubblici-card">';
        $cards .= '<div class="programmi-pubblici-card-title"><span class="programmi-pubblici-card-number">' . $rowNumber . '</span><span>' . programmiPubbliciH($row['materia_nome'] ?? '') . '</span></div>';
        $cards .= '<div class="programmi-pubblici-card-meta">';
        $cards .= '<div class="programmi-pubblici-card-meta-item"><span class="programmi-pubblici-card-label">Anno scolastico</span><span>' . programmiPubbliciH($row['anno_scolastico'] ?? '') . '</span></div>';
        $cards .= '<div class="programmi-pubblici-card-meta-item"><span class="programmi-pubblici-card-label">Classe</span><span>' . programmiPubbliciH($row['classe_nome'] ?? '') . '</span></div>';
        $cards .= '<div class="programmi-pubblici-card-meta-item"><span class="programmi-pubblici-card-label">Docente</span><span>' . programmiPubbliciH($docente) . '</span></div>';
        $cards .= '<div class="programmi-pubblici-card-meta-item"><span class="programmi-pubblici-card-label">Aggiornato</span><span>' . programmiPubbliciH($updated !== '' ? $updated : '-') . '</span></div>';
        $cards .= '</div>';
        $cards .= '<a class="btn btn-primary btn-sm programmi-pubblici-card-action" target="_blank" href="' . programmiPubbliciH($href) . '"><span class="glyphicon glyphicon-download-alt"></span> Scarica PDF</a>';
        $cards .= '</div>';
    }

    $html .= '</tbody></table></div>';
    $cards .= '</div>';

    return $html . $cards;
}

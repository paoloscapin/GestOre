<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/debts_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mcds_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mcds_gender_key($value): string
{
    $value = strtoupper(trim((string)$value));
    if (in_array($value, ['M', 'MASCHIO', 'MASCHILE'], true)) {
        return 'M';
    }
    if (in_array($value, ['F', 'FEMMINA', 'FEMMINILE'], true)) {
        return 'F';
    }
    return 'ND';
}

function mcds_gender_label(string $gender): string
{
    if ($gender === 'M') {
        return 'Maschi';
    }
    if ($gender === 'F') {
        return 'Femmine';
    }
    if ($gender === 'T') {
        return 'Totale';
    }
    return 'Non indicato';
}

function mcds_grade_from_class(string $className): int
{
    if (preg_match('/^\s*([1-5])/', $className, $matches)) {
        return intval($matches[1]);
    }
    return 0;
}

function mcds_grade_label(int $grade): string
{
    $labels = [
        1 => 'Classi prime',
        2 => 'Classi seconde',
        3 => 'Classi terze',
        4 => 'Classi quarte',
        5 => 'Classi quinte',
    ];
    return $labels[$grade] ?? 'Classe non definita';
}

function mcds_percent(int $part, int $total): float
{
    return $total > 0 ? round(($part * 100) / $total, 1) : 0.0;
}

function mcds_query_string(array $params): string
{
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function mcds_address_rows(): array
{
    if (!mastercomAdminTableExists('indirizzo')) {
        return [];
    }
    return dbGetAll("SELECT id, nome, nome_breve FROM indirizzo ORDER BY nome ASC") ?: [];
}

function mcds_group_key(array $classMeta, string $groupBy): string
{
    if ($groupBy === 'grade') {
        return 'grade_' . intval($classMeta['grade'] ?? 0);
    }
    return 'class_' . intval($classMeta['class_id'] ?? 0);
}

function mcds_group_label(array $classMeta, string $groupBy): string
{
    if ($groupBy === 'grade') {
        return mcds_grade_label(intval($classMeta['grade'] ?? 0));
    }
    return (string)($classMeta['classe'] ?? '');
}

function mcds_selected_genders(string $genderMode, string $genderFilter): array
{
    if ($genderMode !== 'split') {
        return ['T'];
    }
    if ($genderFilter === 'M') {
        return ['M'];
    }
    if ($genderFilter === 'F') {
        return ['F'];
    }
    return ['M', 'F'];
}

function mcds_empty_group(string $key, string $label, string $gender): array
{
    return [
        'key' => $key,
        'label' => $label,
        'gender' => $gender,
        'student_ids' => [],
        'debt_students' => [],
        'debt_total' => 0,
        'first_attempt' => 0,
        'second_attempt' => 0,
        'not_recovered' => 0,
        'no_outcome' => 0,
        'empty_gender_note' => '',
        'sort_grade' => 0,
        'sort_label' => $label,
    ];
}

function mcds_build_stats(int $schoolYearId, string $groupBy, int $gradeFilter, int $addressId, string $genderMode, string $genderFilter): array
{
    if ($schoolYearId <= 0) {
        return ['rows' => [], 'totals' => null];
    }

    $hasAddressColumns = mastercomAdminTableColumnExists('classi', 'id_primo_indirizzo') && mastercomAdminTableColumnExists('classi', 'id_secondo_indirizzo');
    $hasAddressTable = mastercomAdminTableExists('indirizzo');
    $classWhere = ["sf.id_anno_scolastico = " . dbI($schoolYearId)];
    if ($gradeFilter > 0) {
        $classWhere[] = "c.classe LIKE " . dbQ($gradeFilter . '%');
    }
    if ($addressId > 0 && $hasAddressColumns) {
        $classWhere[] = "(c.id_primo_indirizzo = " . dbI($addressId) . " OR c.id_secondo_indirizzo = " . dbI($addressId) . ")";
    }
    $classWhereSql = implode(' AND ', $classWhere);
    $addressSelect = $hasAddressColumns
        ? "c.id_primo_indirizzo, c.id_secondo_indirizzo,"
        : "0 AS id_primo_indirizzo, 0 AS id_secondo_indirizzo,";
    $addressNameSelect = $hasAddressTable && $hasAddressColumns
        ? "i1.nome AS primo_indirizzo_nome, i2.nome AS secondo_indirizzo_nome,"
        : "'' AS primo_indirizzo_nome, '' AS secondo_indirizzo_nome,";
    $addressJoin = $hasAddressTable && $hasAddressColumns
        ? "LEFT JOIN indirizzo i1 ON i1.id = c.id_primo_indirizzo
        LEFT JOIN indirizzo i2 ON i2.id = c.id_secondo_indirizzo"
        : "";

    $classRows = dbGetAll("
        SELECT
            c.id AS class_id,
            c.classe,
            $addressSelect
            $addressNameSelect
            sf.id_studente,
            COALESCE(NULLIF(st.sesso, ''), ms.sesso) AS sesso
        FROM studente_frequenta sf
        INNER JOIN classi c ON c.id = sf.id_classe
        $addressJoin
        LEFT JOIN studente st ON st.id = sf.id_studente
        LEFT JOIN mastercom_studenti ms ON ms.id_studente_gestore = sf.id_studente
        WHERE $classWhereSql
        ORDER BY c.classe ASC
    ") ?: [];

    $classMeta = [];
    $studentGender = [];
    $groups = [];
    $selectedGenders = mcds_selected_genders($genderMode, $genderFilter);

    foreach ($classRows as $row) {
        $classId = intval($row['class_id'] ?? 0);
        if ($classId <= 0) {
            continue;
        }
        if (!isset($classMeta[$classId])) {
            $classMeta[$classId] = [
                'class_id' => $classId,
                'classe' => (string)($row['classe'] ?? ''),
                'grade' => mcds_grade_from_class((string)($row['classe'] ?? '')),
                'id_primo_indirizzo' => intval($row['id_primo_indirizzo'] ?? 0),
                'id_secondo_indirizzo' => intval($row['id_secondo_indirizzo'] ?? 0),
                'primo_indirizzo_nome' => (string)($row['primo_indirizzo_nome'] ?? ''),
                'secondo_indirizzo_nome' => (string)($row['secondo_indirizzo_nome'] ?? ''),
            ];
        }

        $studentId = intval($row['id_studente'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }
        $gender = mcds_gender_key($row['sesso'] ?? '');
        $studentGender[$studentId] = $gender;

        $baseKey = mcds_group_key($classMeta[$classId], $groupBy);
        $baseLabel = mcds_group_label($classMeta[$classId], $groupBy);
        $targetGenders = $genderMode === 'split' ? $selectedGenders : ['T'];

        foreach ($targetGenders as $targetGender) {
            if ($genderMode === 'split' && $gender !== $targetGender) {
                continue;
            }
            $key = $baseKey . '|' . $targetGender;
            if (!isset($groups[$key])) {
                $groups[$key] = mcds_empty_group($key, $baseLabel, $targetGender);
                $groups[$key]['sort_grade'] = intval($classMeta[$classId]['grade'] ?? 0);
                $groups[$key]['sort_label'] = $baseLabel;
            }
            $groups[$key]['student_ids'][$studentId] = true;
        }
    }

    if ($genderMode === 'split') {
        foreach ($classMeta as $meta) {
            $baseKey = mcds_group_key($meta, $groupBy);
            $baseLabel = mcds_group_label($meta, $groupBy);
            foreach ($selectedGenders as $targetGender) {
                $key = $baseKey . '|' . $targetGender;
                if (!isset($groups[$key])) {
                    $groups[$key] = mcds_empty_group($key, $baseLabel, $targetGender);
                    $groups[$key]['sort_grade'] = intval($meta['grade'] ?? 0);
                    $groups[$key]['sort_label'] = $baseLabel;
                }
            }
        }
    }

    $debtRows = mastercomDebtsReportRows($schoolYearId, 0);
    foreach ($debtRows as $row) {
        $classId = intval($row['id_classe_gestore'] ?? 0);
        $studentId = intval($row['id_studente_gestore'] ?? 0);
        if ($classId <= 0 || $studentId <= 0 || !isset($classMeta[$classId])) {
            continue;
        }

        $meta = $classMeta[$classId];
        $baseKey = mcds_group_key($meta, $groupBy);
        $baseLabel = mcds_group_label($meta, $groupBy);
        $gender = $studentGender[$studentId] ?? 'ND';
        $targetGenders = $genderMode === 'split' ? $selectedGenders : ['T'];

        foreach ($targetGenders as $targetGender) {
            if ($genderMode === 'split' && $gender !== $targetGender) {
                continue;
            }
            $key = $baseKey . '|' . $targetGender;
            if (!isset($groups[$key])) {
                $groups[$key] = mcds_empty_group($key, $baseLabel, $targetGender);
                $groups[$key]['sort_grade'] = intval($meta['grade'] ?? 0);
                $groups[$key]['sort_label'] = $baseLabel;
            }

            $groups[$key]['debt_students'][$studentId] = intval($groups[$key]['debt_students'][$studentId] ?? 0) + 1;
            $groups[$key]['debt_total']++;

            $courseRecovered = $row['corso_recuperato'] ?? null;
            $mastercomRecovered = intval($row['recuperato_mastercom'] ?? 0) === 1;
            if (!$mastercomRecovered) {
                $groups[$key]['not_recovered']++;
            } elseif (intval($courseRecovered) === 1 && intval($row['corso_appello'] ?? 0) >= 2) {
                $groups[$key]['second_attempt']++;
            } elseif (intval($courseRecovered) === 1) {
                $groups[$key]['first_attempt']++;
            } else {
                $groups[$key]['no_outcome']++;
            }
        }
    }

    $rows = [];
    $totals = mcds_empty_group('totals', 'Totale', 'T');
    foreach ($groups as $group) {
        $oneDebt = 0;
        $multiDebt = 0;
        foreach ($group['debt_students'] as $count) {
            if (intval($count) > 1) {
                $multiDebt++;
            } else {
                $oneDebt++;
            }
        }

        $totalStudents = count($group['student_ids']);
        $studentsWithDebt = count($group['debt_students']);
        $group['total_students'] = $totalStudents;
        $group['students_with_debt'] = $studentsWithDebt;
        $group['one_debt_students'] = $oneDebt;
        $group['multi_debt_students'] = $multiDebt;
        $group['debt_percent'] = mcds_percent($studentsWithDebt, $totalStudents);
        $group['one_debt_percent'] = mcds_percent($oneDebt, $totalStudents);
        $group['multi_debt_percent'] = mcds_percent($multiDebt, $totalStudents);
        if ($genderMode === 'split' && $totalStudents === 0) {
            $emptyGroupLabel = $groupBy === 'class' ? 'in questa classe' : 'nel gruppo selezionato';
            $group['empty_gender_note'] = (string)($group['gender'] ?? '') === 'F'
                ? 'Nessuna femmina ' . $emptyGroupLabel
                : 'Nessun maschio ' . $emptyGroupLabel;
        }
        $rows[] = $group;

        foreach ($group['student_ids'] as $studentId => $_) {
            $totals['student_ids'][$studentId] = true;
        }
        foreach ($group['debt_students'] as $studentId => $count) {
            $totals['debt_students'][$studentId] = intval($totals['debt_students'][$studentId] ?? 0) + intval($count);
        }
        $totals['debt_total'] += intval($group['debt_total']);
        $totals['first_attempt'] += intval($group['first_attempt']);
        $totals['second_attempt'] += intval($group['second_attempt']);
        $totals['not_recovered'] += intval($group['not_recovered']);
        $totals['no_outcome'] += intval($group['no_outcome']);
    }

    usort($rows, function ($a, $b) {
        $gradeCmp = intval($a['sort_grade'] ?? 0) <=> intval($b['sort_grade'] ?? 0);
        if ($gradeCmp !== 0) {
            return $gradeCmp;
        }
        $labelCmp = strnatcasecmp((string)($a['sort_label'] ?? ''), (string)($b['sort_label'] ?? ''));
        if ($labelCmp !== 0) {
            return $labelCmp;
        }
        return strcmp((string)($a['gender'] ?? ''), (string)($b['gender'] ?? ''));
    });

    $totalsOne = 0;
    $totalsMulti = 0;
    foreach ($totals['debt_students'] as $count) {
        if (intval($count) > 1) {
            $totalsMulti++;
        } else {
            $totalsOne++;
        }
    }
    $totals['total_students'] = count($totals['student_ids']);
    $totals['students_with_debt'] = count($totals['debt_students']);
    $totals['one_debt_students'] = $totalsOne;
    $totals['multi_debt_students'] = $totalsMulti;
    $totals['debt_percent'] = mcds_percent(intval($totals['students_with_debt']), intval($totals['total_students']));
    $totals['one_debt_percent'] = mcds_percent($totalsOne, intval($totals['total_students']));
    $totals['multi_debt_percent'] = mcds_percent($totalsMulti, intval($totals['total_students']));

    return ['rows' => $rows, 'totals' => $totals];
}

function mcds_table_html(array $rows, ?array $totals, bool $splitGender, bool $forExport = false): string
{
    $headers = ['Gruppo'];
    if ($splitGender) {
        $headers[] = 'Sesso';
    }
    $headers = array_merge($headers, [
        'Studenti',
        'Con carenza',
        '% con carenza',
        '1 carenza',
        '% 1 carenza',
        'Piu carenze',
        '% piu carenze',
        'Carenze totali',
        'Rec. 1 appello',
        'Rec. 2 appello',
        'Non rec. MasterCom',
        'Rec. senza appello',
    ]);

    $html = '<table class="table table-bordered table-condensed mcds-table">';
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . mcds_h($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    if (empty($rows)) {
        $html .= '<tr><td colspan="' . count($headers) . '" class="text-center">Nessun dato disponibile per i filtri selezionati.</td></tr>';
    }

    foreach ($rows as $row) {
        $html .= '<tr>';
        $emptyGenderNote = trim((string)($row['empty_gender_note'] ?? ''));
        $html .= '<td>' . mcds_h($row['label'] ?? '');
        if ($emptyGenderNote !== '') {
            $html .= $forExport ? ' - ' . mcds_h($emptyGenderNote) : '<br><span class="mcds-empty-note">' . mcds_h($emptyGenderNote) . '</span>';
        }
        $html .= '</td>';
        if ($splitGender) {
            $html .= '<td>' . mcds_h(mcds_gender_label((string)($row['gender'] ?? 'ND'))) . '</td>';
        }
        $html .= '<td class="text-right">' . intval($row['total_students'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . intval($row['students_with_debt'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . number_format((float)($row['debt_percent'] ?? 0), 1, ',', '.') . '%</td>';
        $html .= '<td class="text-right">' . intval($row['one_debt_students'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . number_format((float)($row['one_debt_percent'] ?? 0), 1, ',', '.') . '%</td>';
        $html .= '<td class="text-right">' . intval($row['multi_debt_students'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . number_format((float)($row['multi_debt_percent'] ?? 0), 1, ',', '.') . '%</td>';
        $html .= '<td class="text-right">' . intval($row['debt_total'] ?? 0) . '</td>';
        $html .= '<td class="text-right mcds-ok">' . intval($row['first_attempt'] ?? 0) . '</td>';
        $html .= '<td class="text-right mcds-warn">' . intval($row['second_attempt'] ?? 0) . '</td>';
        $html .= '<td class="text-right mcds-bad">' . intval($row['not_recovered'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . intval($row['no_outcome'] ?? 0) . '</td>';
        $html .= '</tr>';
    }

    if ($totals !== null) {
        $html .= '<tr class="mcds-total-row">';
        $html .= '<td>Totale</td>';
        if ($splitGender) {
            $html .= '<td></td>';
        }
        $html .= '<td class="text-right">' . intval($totals['total_students'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . intval($totals['students_with_debt'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . number_format((float)($totals['debt_percent'] ?? 0), 1, ',', '.') . '%</td>';
        $html .= '<td class="text-right">' . intval($totals['one_debt_students'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . number_format((float)($totals['one_debt_percent'] ?? 0), 1, ',', '.') . '%</td>';
        $html .= '<td class="text-right">' . intval($totals['multi_debt_students'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . number_format((float)($totals['multi_debt_percent'] ?? 0), 1, ',', '.') . '%</td>';
        $html .= '<td class="text-right">' . intval($totals['debt_total'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . intval($totals['first_attempt'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . intval($totals['second_attempt'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . intval($totals['not_recovered'] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . intval($totals['no_outcome'] ?? 0) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    if ($forExport) {
        $html = str_replace(
            [
                ' class="table table-bordered table-condensed mcds-table"',
                ' class="text-right mcds-ok"',
                ' class="text-right mcds-warn"',
                ' class="text-right mcds-bad"',
                ' class="text-right"',
                ' class="text-center"',
                ' class="mcds-total-row"',
            ],
            ['', '', '', '', '', '', ''],
            $html
        );
    }

    return $html;
}

function mcds_chart_html(array $rows): string
{
    if (empty($rows)) {
        return '<div class="alert alert-warning">Nessun grafico disponibile per i filtri selezionati.</div>';
    }

    $html = '<div class="mcds-charts">';
    $html .= '<div class="mcds-chart"><h4>Studenti con almeno una carenza</h4>';
    foreach ($rows as $row) {
        $percent = max(0, min(100, (float)($row['debt_percent'] ?? 0)));
        $emptyGenderNote = trim((string)($row['empty_gender_note'] ?? ''));
        $rowLabel = ($row['label'] ?? '') . (((string)($row['gender'] ?? 'T') !== 'T') ? ' - ' . mcds_gender_label((string)$row['gender']) : '');
        if ($emptyGenderNote !== '') {
            $rowLabel .= ' (' . $emptyGenderNote . ')';
        }
        $html .= '<div class="mcds-bar-row">';
        $html .= '<div class="mcds-bar-label">' . mcds_h($rowLabel) . '</div>';
        $html .= '<div class="mcds-bar-track"><div class="mcds-bar mcds-bar-blue" style="width:' . $percent . '%"></div></div>';
        $html .= '<div class="mcds-bar-value">' . number_format($percent, 1, ',', '.') . '%</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '<div class="mcds-chart"><h4>Esito recuperi per carenza</h4>';
    foreach ($rows as $row) {
        $firstCount = intval($row['first_attempt'] ?? 0);
        $secondCount = intval($row['second_attempt'] ?? 0);
        $badCount = intval($row['not_recovered'] ?? 0);
        $noneCount = intval($row['no_outcome'] ?? 0);
        $total = $firstCount + $secondCount + $badCount + $noneCount;
        $emptyGenderNote = trim((string)($row['empty_gender_note'] ?? ''));
        $rowLabel = ($row['label'] ?? '') . (((string)($row['gender'] ?? 'T') !== 'T') ? ' - ' . mcds_gender_label((string)$row['gender']) : '');
        if ($emptyGenderNote !== '') {
            $rowLabel .= ' (' . $emptyGenderNote . ')';
        }
        if ($total <= 0) {
            $html .= '<div class="mcds-stack-row">';
            $html .= '<div class="mcds-bar-label">' . mcds_h($rowLabel) . '</div>';
            $html .= '<div class="mcds-stack mcds-stack-empty"><span>' . mcds_h($emptyGenderNote !== '' ? $emptyGenderNote : 'Nessun esito') . '</span></div>';
            $html .= '</div>';
            continue;
        }
        $first = ($firstCount * 100) / $total;
        $second = ($secondCount * 100) / $total;
        $bad = ($badCount * 100) / $total;
        $none = max(0, 100 - $first - $second - $bad);
        $html .= '<div class="mcds-stack-row">';
        $html .= '<div class="mcds-bar-label">' . mcds_h($rowLabel) . '</div>';
        $html .= '<div class="mcds-stack">';
        $html .= '<span class="mcds-stack-first" title="Recuperata al primo appello: ' . $firstCount . '" style="width:' . round($first, 4) . '%"></span>';
        $html .= '<span class="mcds-stack-second" title="Recuperata al secondo appello: ' . $secondCount . '" style="width:' . round($second, 4) . '%"></span>';
        $html .= '<span class="mcds-stack-bad" title="Non recuperata: ' . $badCount . '" style="width:' . round($bad, 4) . '%"></span>';
        $html .= '<span class="mcds-stack-none" title="Recuperata senza appello corso: ' . $noneCount . '" style="width:' . round($none, 4) . '%"></span>';
        $html .= '</div></div>';
    }
    $html .= '<div class="mcds-legend"><span class="mcds-l-first"></span> Recuperata al primo appello <span class="mcds-l-second"></span> Recuperata al secondo appello <span class="mcds-l-bad"></span> Non recuperata in MasterCom <span class="mcds-l-none"></span> Recuperata senza appello corso</div>';
    $html .= '</div></div>';

    return $html;
}

mastercomDebtsEnsureTables();
gestoreEnsureStudenteSessoColumn();
mastercomDebtsRefreshMissingSubjectMatches();
mastercomDebtsRefreshCachedClassMatches();

global $__anno_scolastico_corrente_id;

$schoolYears = mastercomDebtsSchoolYears();
$selectedYearId = intval($_GET['school_year_id'] ?? ($__anno_scolastico_corrente_id ?? 0));
if ($selectedYearId <= 0 && !empty($schoolYears)) {
    $selectedYearId = intval($schoolYears[0]['id'] ?? 0);
}

$groupBy = (string)($_GET['group_by'] ?? 'class');
if (!in_array($groupBy, ['class', 'grade'], true)) {
    $groupBy = 'class';
}
$gradeFilter = intval($_GET['grade'] ?? 0);
if ($gradeFilter < 0 || $gradeFilter > 5) {
    $gradeFilter = 0;
}
$addressId = intval($_GET['address_id'] ?? 0);
$genderMode = (string)($_GET['gender_mode'] ?? 'combined');
if (!in_array($genderMode, ['combined', 'split'], true)) {
    $genderMode = 'combined';
}
$genderFilter = strtoupper((string)($_GET['gender_filter'] ?? 'both'));
if (!in_array($genderFilter, ['BOTH', 'M', 'F'], true)) {
    $genderFilter = 'BOTH';
}
if ($genderMode !== 'split') {
    $genderFilter = 'BOTH';
}

$stats = mcds_build_stats($selectedYearId, $groupBy, $gradeFilter, $addressId, $genderMode, $genderFilter);
$rows = $stats['rows'];
$totals = $stats['totals'];
$addressRows = mcds_address_rows();
$splitGender = $genderMode === 'split';

$currentParams = [
    'school_year_id' => $selectedYearId,
    'group_by' => $groupBy,
    'grade' => $gradeFilter,
    'address_id' => $addressId,
    'gender_mode' => $genderMode,
    'gender_filter' => $genderFilter,
];

if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    $fileName = mastercomDebtsExportFileName('statistiche_carenze_mastercom', 'xls');
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h2>Statistiche carenze MasterCom</h2>';
    echo mcds_table_html($rows, $totals, $splitGender, true);
    echo '</body></html>';
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf)) {
        require_once $tcpdf;
    } else {
        require_once '../common/tcpdf/tcpdf.php';
    }

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GestOre');
    $pdf->SetAuthor('GestOre');
    $pdf->SetTitle('Statistiche carenze MasterCom');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(true, 8);
    $pdf->AddPage();
    $html = '<style>
        h1 { color: #0b5d7e; font-size: 18px; }
        table { border-collapse: collapse; width: 100%; font-size: 8px; }
        th { background-color: #245b78; color: #fff; font-weight: bold; border: 1px solid #222; padding: 4px; }
        td { border: 1px solid #555; padding: 3px; }
    </style>';
    $html .= '<h1>Statistiche carenze MasterCom</h1>';
    $html .= mcds_table_html($rows, $totals, $splitGender, true);
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output(mastercomDebtsExportFileName('statistiche_carenze_mastercom', 'pdf'), 'D');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Statistiche carenze MasterCom</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .mcds-toolbar {
            background: linear-gradient(#fff7e8, #ffd082);
            border: 1px solid #f0ad4e;
            border-radius: 4px;
            padding: 14px;
            margin-bottom: 16px;
        }
        .mcds-toolbar .row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .mcds-toolbar .mcds-filter {
            min-width: 160px;
            flex: 1 1 180px;
        }
        .mcds-toolbar .mcds-filter-wide {
            min-width: 220px;
            flex: 1 1 240px;
        }
        .mcds-actions {
            flex: 0 0 auto;
            white-space: nowrap;
        }
        .mcds-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        .mcds-kpi {
            background: #fff;
            border: 1px solid #c7d9e6;
            border-left: 5px solid #2f7d9f;
            border-radius: 4px;
            padding: 10px 12px;
        }
        .mcds-kpi .label {
            display: block;
            color: #375569;
            font-size: 12px;
            padding: 0;
            text-align: left;
        }
        .mcds-kpi .value {
            color: #11394e;
            font-size: 24px;
            font-weight: 700;
            line-height: 1.15;
        }
        .mcds-table th {
            background: #245b78;
            color: #fff;
            border-color: #1e4c64 !important;
            vertical-align: middle !important;
            white-space: nowrap;
        }
        .mcds-table td {
            vertical-align: middle !important;
        }
        .mcds-empty-note {
            color: #8a5a00;
            font-size: 12px;
            font-weight: 700;
        }
        .mcds-total-row {
            background: #e8f3fb;
            font-weight: 700;
        }
        .mcds-ok {
            background: #dff4df;
        }
        .mcds-warn {
            background: #fff1c2;
        }
        .mcds-bad {
            background: #ffd8d4;
        }
        .mcds-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        .mcds-chart {
            background: #fff;
            border: 1px solid #c7d9e6;
            border-radius: 4px;
            padding: 12px;
        }
        .mcds-chart h4 {
            margin-top: 0;
            color: #11394e;
        }
        .mcds-bar-row,
        .mcds-stack-row {
            display: grid;
            grid-template-columns: minmax(120px, 190px) 1fr 54px;
            gap: 8px;
            align-items: center;
            margin: 7px 0;
        }
        .mcds-stack-row {
            grid-template-columns: minmax(120px, 190px) 1fr;
        }
        .mcds-bar-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mcds-bar-track,
        .mcds-stack {
            display: flex;
            height: 18px;
            background: #fff;
            border: 1px solid #c7d9e6;
            border-radius: 3px;
            overflow: hidden;
        }
        .mcds-bar {
            height: 100%;
        }
        .mcds-bar-blue {
            background: #2b8cc4;
        }
        .mcds-bar-value {
            text-align: right;
            font-weight: 700;
        }
        .mcds-stack span {
            display: block;
            flex: 0 0 auto;
            height: 100%;
        }
        .mcds-stack-empty {
            align-items: center;
            justify-content: center;
            color: #667085;
            font-size: 12px;
            font-style: italic;
        }
        .mcds-stack-empty span {
            flex: 0 1 auto;
            height: auto;
        }
        .mcds-stack-first,
        .mcds-l-first {
            background: #3ba35c;
        }
        .mcds-stack-second,
        .mcds-l-second {
            background: #f0a32f;
        }
        .mcds-stack-bad,
        .mcds-l-bad {
            background: #d9534f;
        }
        .mcds-stack-none,
        .mcds-l-none {
            background: #7c5cc4;
        }
        .mcds-legend {
            margin-top: 10px;
            color: #375569;
            font-size: 12px;
        }
        .mcds-legend span {
            display: inline-block;
            width: 14px;
            height: 10px;
            margin-left: 10px;
            margin-right: 4px;
        }
        @media (max-width: 760px) {
            .mcds-charts {
                grid-template-columns: 1fr;
            }
            .mcds-bar-row,
            .mcds-stack-row {
                grid-template-columns: 1fr;
            }
            .mcds-bar-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>
<?php
if (haRuolo('admin')) {
    require_once '../common/header-admin.php';
} else {
    require_once '../common/header-didattica.php';
}
?>
<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-stats"></span>&emsp;Statistiche carenze MasterCom
        </div>
        <div class="panel-body">
            <form method="get" class="mcds-toolbar" id="mcdsFilterForm">
                <div class="row">
                    <div class="mcds-filter-wide">
                        <label for="school_year_id">Anno scolastico</label>
                        <select id="school_year_id" name="school_year_id" class="form-control">
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?php echo intval($year['id']); ?>" <?php echo intval($year['id']) === $selectedYearId ? 'selected' : ''; ?>>
                                    <?php echo mcds_h($year['anno']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mcds-filter">
                        <label for="group_by">Raggruppa</label>
                        <select id="group_by" name="group_by" class="form-control">
                            <option value="class" <?php echo $groupBy === 'class' ? 'selected' : ''; ?>>Per classe</option>
                            <option value="grade" <?php echo $groupBy === 'grade' ? 'selected' : ''; ?>>Per anno</option>
                        </select>
                    </div>
                    <div class="mcds-filter">
                        <label for="grade">Anno classe</label>
                        <select id="grade" name="grade" class="form-control">
                            <option value="0" <?php echo $gradeFilter === 0 ? 'selected' : ''; ?>>Tutti</option>
                            <?php for ($grade = 1; $grade <= 5; $grade++): ?>
                                <option value="<?php echo $grade; ?>" <?php echo $gradeFilter === $grade ? 'selected' : ''; ?>>
                                    <?php echo mcds_h(mcds_grade_label($grade)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mcds-filter-wide">
                        <label for="address_id">Indirizzo</label>
                        <select id="address_id" name="address_id" class="form-control">
                            <option value="0" <?php echo $addressId === 0 ? 'selected' : ''; ?>>Tutti gli indirizzi</option>
                            <?php foreach ($addressRows as $address): ?>
                                <option value="<?php echo intval($address['id']); ?>" <?php echo intval($address['id']) === $addressId ? 'selected' : ''; ?>>
                                    <?php echo mcds_h($address['nome_breve'] ?: $address['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mcds-filter">
                        <label for="gender_mode">Maschi/Femmine</label>
                        <select id="gender_mode" name="gender_mode" class="form-control">
                            <option value="combined" <?php echo $genderMode === 'combined' ? 'selected' : ''; ?>>Uniti</option>
                            <option value="split" <?php echo $genderMode === 'split' ? 'selected' : ''; ?>>Separati</option>
                        </select>
                    </div>
                    <div class="mcds-filter" id="gender_filter_part" style="<?php echo $genderMode === 'split' ? '' : 'display:none;'; ?>">
                        <label for="gender_filter">Filtro sesso</label>
                        <select id="gender_filter" name="gender_filter" class="form-control">
                            <option value="BOTH" <?php echo $genderFilter === 'BOTH' ? 'selected' : ''; ?>>Entrambi</option>
                            <option value="M" <?php echo $genderFilter === 'M' ? 'selected' : ''; ?>>Solo maschi</option>
                            <option value="F" <?php echo $genderFilter === 'F' ? 'selected' : ''; ?>>Solo femmine</option>
                        </select>
                    </div>
                    <div class="mcds-actions">
                        <label>&nbsp;</label><br>
                        <a class="btn btn-default" href="mastercom_debts.php?school_year_id=<?php echo intval($selectedYearId); ?>">
                            <span class="glyphicon glyphicon-arrow-left"></span> Carenze
                        </a>
                        <a class="btn btn-danger" href="?<?php echo mcds_h(mcds_query_string(array_merge($currentParams, ['export' => 'pdf']))); ?>">
                            <span class="glyphicon glyphicon-file"></span> PDF
                        </a>
                        <a class="btn btn-success" href="?<?php echo mcds_h(mcds_query_string(array_merge($currentParams, ['export' => 'xls']))); ?>">
                            <span class="glyphicon glyphicon-list-alt"></span> XLS
                        </a>
                    </div>
                </div>
            </form>

            <div class="alert alert-info">
                Le statistiche usano le carenze lette da MasterCom nella cache locale e gli esiti dei corsi di recupero gia collegati nella tabella carenze.
            </div>

            <?php if ($selectedYearId <= 0): ?>
                <div class="alert alert-warning">Seleziona un anno scolastico specifico per calcolare le percentuali sugli studenti della classe.</div>
            <?php endif; ?>

            <?php if ($totals !== null): ?>
                <div class="mcds-summary">
                    <div class="mcds-kpi"><span class="label">Studenti considerati</span><span class="value"><?php echo intval($totals['total_students'] ?? 0); ?></span></div>
                    <div class="mcds-kpi"><span class="label">Con almeno una carenza</span><span class="value"><?php echo intval($totals['students_with_debt'] ?? 0); ?></span></div>
                    <div class="mcds-kpi"><span class="label">% studenti con carenza</span><span class="value"><?php echo number_format((float)($totals['debt_percent'] ?? 0), 1, ',', '.'); ?>%</span></div>
                    <div class="mcds-kpi"><span class="label">Carenze totali</span><span class="value"><?php echo intval($totals['debt_total'] ?? 0); ?></span></div>
                </div>
            <?php endif; ?>

            <?php echo mcds_chart_html($rows); ?>
            <?php echo mcds_table_html($rows, $totals, $splitGender); ?>
        </div>
    </div>
</div>
<script>
    document.querySelectorAll('#mcdsFilterForm select').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('mcdsFilterForm').submit();
        });
    });
</script>
</body>
</html>

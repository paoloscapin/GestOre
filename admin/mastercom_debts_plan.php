<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/debts_plan_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mcdp_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mcdp_students_html(array $students): string
{
    $items = [];
    foreach ($students as $student) {
        $class = trim((string)($student['class'] ?? ''));
        $name = trim((string)($student['name'] ?? ''));
        if (!empty($student['auditor'])) {
            $name .= ' (uditore)';
        }
        $items[] = mcdp_h($name . ($class !== '' ? ' (' . $class . ')' : ''));
    }
    return implode('<br>', $items);
}

function mcdp_group_title(array $group): string
{
    $title = 'Anno ' . ($group['class_year'] ?? 'NA') . ' - ' . ($group['subject'] ?? '');
    if (intval($group['part_total'] ?? 1) > 1) {
        $title .= ' - gruppo ' . intval($group['part_index'] ?? 1) . '/' . intval($group['part_total'] ?? 1);
    }
    return $title;
}

function mcdp_slots_html(array $group): string
{
    $slots = $group['slots'] ?? [];
    if (empty($slots) && !empty($group['slot'])) {
        $slots = [$group['slot']];
    }

    $labels = [];
    foreach ($slots as $slot) {
        $labels[] = mcdp_h($slot['label'] ?? '');
    }
    return implode('<br>', $labels);
}

function mcdp_group_lock_form(array $group, array $teachers, int $schoolYearId, int $minSize, int $maxSize): string
{
    if (!empty($group['imported'])) {
        $aula = trim((string)($group['aula'] ?? ''));
        $docente = trim((string)($group['docente_nome'] ?? ''));
        $parts = ['Importato da CSV'];
        if ($docente !== '') {
            $parts[] = 'Docente: ' . $docente;
        }
        if ($aula !== '') {
            $parts[] = 'Aula: ' . $aula;
        }
        return '<span class="label label-success">' . mcdp_h(implode(' - ', $parts)) . '</span>';
    }

    $planId = trim((string)($group['plan_id'] ?? ''));
    if ($planId === '') {
        return '';
    }

    $slots = $group['slots'] ?? [];
    if (empty($slots) && !empty($group['slot'])) {
        $slots = [$group['slot']];
    }
    while (count($slots) < 3) {
        $slots[] = ['date' => '', 'start' => '', 'end' => ''];
    }

    $html = '<form method="post" class="mcdp-lock-form">';
    $html .= '<input type="hidden" name="action" value="save_lock">';
    $html .= '<input type="hidden" name="school_year_id" value="' . intval($schoolYearId) . '">';
    $html .= '<input type="hidden" name="min_size" value="' . intval($minSize) . '">';
    $html .= '<input type="hidden" name="max_size" value="' . intval($maxSize) . '">';
    $html .= '<input type="hidden" name="plan_id" value="' . mcdp_h($planId) . '">';
    $html .= '<div class="mcdp-lock-grid">';
    for ($i = 0; $i < 3; $i++) {
        $slot = $slots[$i] ?? [];
        $html .= '<div class="mcdp-lock-lesson">';
        $html .= '<span>' . ($i + 1) . '</span>';
        $html .= '<input type="date" class="form-control input-sm" name="slot_date[]" value="' . mcdp_h($slot['date'] ?? '') . '">';
        $html .= '<input type="time" class="form-control input-sm" name="slot_start[]" value="' . mcdp_h($slot['start'] ?? '') . '">';
        $html .= '<input type="time" class="form-control input-sm" name="slot_end[]" value="' . mcdp_h($slot['end'] ?? '') . '">';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<input type="text" class="form-control input-sm mcdp-lock-aula" name="aula" value="' . mcdp_h($group['aula'] ?? '') . '" placeholder="Aula">';
    $html .= '<select class="form-control input-sm mcdp-lock-docente" name="id_docente">';
    $html .= '<option value="0">Docente da abbinare</option>';
    foreach ($teachers as $teacher) {
        $teacherId = intval($teacher['id'] ?? 0);
        $selected = $teacherId === intval($group['id_docente'] ?? 0) ? ' selected' : '';
        $label = trim((string)($teacher['cognome'] ?? '') . ' ' . (string)($teacher['nome'] ?? ''));
        $html .= '<option value="' . $teacherId . '"' . $selected . '>' . mcdp_h($label) . '</option>';
    }
    $html .= '</select>';
    $html .= '<div class="mcdp-lock-actions">';
    $html .= '<button type="submit" class="btn btn-xs btn-warning"><span class="glyphicon glyphicon-pushpin"></span> Blocca</button>';
    $html .= '</div>';
    $html .= '</form>';
    if (!empty($group['locked'])) {
        $html .= '<form method="post" class="mcdp-unlock-form">';
        $html .= '<input type="hidden" name="action" value="delete_lock">';
        $html .= '<input type="hidden" name="school_year_id" value="' . intval($schoolYearId) . '">';
        $html .= '<input type="hidden" name="min_size" value="' . intval($minSize) . '">';
        $html .= '<input type="hidden" name="max_size" value="' . intval($maxSize) . '">';
        $html .= '<input type="hidden" name="plan_id" value="' . mcdp_h($planId) . '">';
        $html .= '<button type="submit" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-remove"></span> Sblocca</button>';
        $html .= '</form>';
    }

    return $html;
}

function mcdp_import_summary_html(array $summary): string
{
    if (empty($summary)) {
        return '';
    }

    $alertClass = !empty($summary['ok']) ? (!empty($summary['applied']) ? 'alert-success' : 'alert-info') : 'alert-danger';
    $html = '<div class="alert ' . $alertClass . '">';
    $html .= '<strong>' . mcdp_h($summary['message'] ?? '') . '</strong>';
    if (!empty($summary['ok'])) {
        $html .= '<div class="mcdp-import-kpis">';
        $html .= '<span>Righe CSV: <strong>' . intval($summary['rows'] ?? 0) . '</strong></span>';
        $html .= '<span>Gruppi reali: <strong>' . intval($summary['groups'] ?? 0) . '</strong></span>';
        $html .= '<span>In itinere: <strong>' . intval($summary['itinere_groups'] ?? 0) . '</strong></span>';
        $auditors = intval($summary['auditors'] ?? 0);
        $matchableRows = max(0, intval($summary['rows'] ?? 0) - $auditors);
        $html .= '<span>Uditori: <strong>' . $auditors . '</strong></span>';
        $html .= '<span>Uditori agganciati: <strong>' . intval($summary['auditors_matched'] ?? 0) . '/' . $auditors . '</strong></span>';
        $html .= '<span>Agganciati: <strong>' . intval($summary['matched'] ?? 0) . '/' . $matchableRows . '</strong></span>';
        $html .= '</div>';
    }

    $warnings = array_values((array)($summary['warnings'] ?? []));
    if (!empty($warnings)) {
        $html .= '<div><strong>Avvisi date</strong><ul>';
        foreach (array_slice($warnings, 0, 10) as $warning) {
            $html .= '<li>' . mcdp_h($warning) . '</li>';
        }
        if (count($warnings) > 10) {
            $html .= '<li>Altri avvisi: ' . (count($warnings) - 10) . '</li>';
        }
        $html .= '</ul></div>';
    }

    $unmatched = array_values((array)($summary['unmatched'] ?? []));
    if (!empty($unmatched)) {
        $html .= '<div><strong>Righe non agganciate a MasterCom</strong><ul>';
        foreach (array_slice($unmatched, 0, 20) as $missing) {
            $html .= '<li>' . mcdp_h($missing) . '</li>';
        }
        if (count($unmatched) > 20) {
            $html .= '<li>Altre righe non agganciate: ' . (count($unmatched) - 20) . '</li>';
        }
        $html .= '</ul></div>';
    }

    $html .= '</div>';
    return $html;
}

function mcdp_sync_summary_html(array $summary): string
{
    if (empty($summary)) {
        return '';
    }
    $alertClass = !empty($summary['ok']) ? 'alert-success' : 'alert-danger';
    $html = '<div class="alert ' . $alertClass . '">';
    $html .= '<strong>' . mcdp_h($summary['message'] ?? '') . '</strong>';
    if (!empty($summary['ok'])) {
        $html .= '<div class="mcdp-import-kpis">';
        $html .= '<span>Creati: <strong>' . intval($summary['created'] ?? 0) . '</strong></span>';
        $html .= '<span>Aggiornati: <strong>' . intval($summary['updated'] ?? 0) . '</strong></span>';
        $html .= '<span>Presenti in corso: <strong>' . intval($summary['actual_courses'] ?? 0) . '</strong></span>';
        $html .= '<span>Date sincronizzate: <strong>' . intval($summary['dates_synced'] ?? 0) . '</strong></span>';
        $html .= '<span>Iscritti aggiunti: <strong>' . intval($summary['students_added'] ?? 0) . '</strong></span>';
        $html .= '<span>Iscritti rimossi: <strong>' . intval($summary['students_removed'] ?? 0) . '</strong></span>';
        $html .= '<span>Uditori iscritti: <strong>' . intval($summary['auditors_synced'] ?? 0) . '</strong></span>';
        $html .= '</div>';
        $createdIds = array_values((array)($summary['created_ids'] ?? []));
        if (!empty($createdIds)) {
            $html .= '<div class="small">Primi id corso creati: ' . mcdp_h(implode(', ', array_map('intval', $createdIds))) . '</div>';
        }
    }

    foreach (['skipped' => 'Corsi saltati', 'warnings' => 'Avvisi', 'unmatched_students' => 'Studenti non agganciati'] as $key => $title) {
        $items = array_values((array)($summary[$key] ?? []));
        if (empty($items)) {
            continue;
        }
        $html .= '<div><strong>' . mcdp_h($title) . '</strong><ul>';
        foreach (array_slice($items, 0, 20) as $item) {
            $html .= '<li>' . mcdp_h($item) . '</li>';
        }
        if (count($items) > 20) {
            $html .= '<li>Altri elementi: ' . (count($items) - 20) . '</li>';
        }
        $html .= '</ul></div>';
    }

    $html .= '</div>';
    return $html;
}

function mcdp_previous_school_year_id_from_rows(array $schoolYears, int $selectedYearId): int
{
    $selectedLabel = '';
    foreach ($schoolYears as $year) {
        if (intval($year['id'] ?? 0) === $selectedYearId) {
            $selectedLabel = trim((string)($year['anno'] ?? ''));
            break;
        }
    }

    $wantedLabel = '';
    if (preg_match('/^(\d{4})\s*\/\s*\d{4}$/', $selectedLabel, $matches)) {
        $wantedLabel = (intval($matches[1]) - 1) . '/' . intval($matches[1]);
    }

    if ($wantedLabel !== '') {
        foreach ($schoolYears as $year) {
            if (trim((string)($year['anno'] ?? '')) === $wantedLabel) {
                return intval($year['id'] ?? 0);
            }
        }
    }

    $fallbackId = 0;
    foreach ($schoolYears as $year) {
        $yearId = intval($year['id'] ?? 0);
        if ($yearId > 0 && $yearId < $selectedYearId && $yearId > $fallbackId) {
            $fallbackId = $yearId;
        }
    }

    return $fallbackId;
}

function mcdp_next_school_year_id_from_rows(array $schoolYears, int $selectedYearId): int
{
    $selectedLabel = '';
    foreach ($schoolYears as $year) {
        if (intval($year['id'] ?? 0) === $selectedYearId) {
            $selectedLabel = trim((string)($year['anno'] ?? ''));
            break;
        }
    }

    $wantedLabel = '';
    if (preg_match('/^\d{4}\s*\/\s*(\d{4})$/', $selectedLabel, $matches)) {
        $wantedLabel = intval($matches[1]) . '/' . (intval($matches[1]) + 1);
    }

    if ($wantedLabel !== '') {
        foreach ($schoolYears as $year) {
            if (trim((string)($year['anno'] ?? '')) === $wantedLabel) {
                return intval($year['id'] ?? 0);
            }
        }
    }

    $fallbackId = 0;
    foreach ($schoolYears as $year) {
        $yearId = intval($year['id'] ?? 0);
        if ($yearId > $selectedYearId && ($fallbackId === 0 || $yearId < $fallbackId)) {
            $fallbackId = $yearId;
        }
    }

    return $fallbackId > 0 ? $fallbackId : $selectedYearId;
}

function mcdp_course_summary_rows(array $groups): array
{
    $summary = [];
    foreach ($groups as $group) {
        $classYear = trim((string)($group['class_year'] ?? 'NA'));
        $subject = trim((string)($group['subject'] ?? 'Materia'));
        $key = $classYear . '|' . $subject;
        if (!isset($summary[$key])) {
            $summary[$key] = [
                'class_year' => $classYear,
                'subject' => $subject,
                'courses' => 0,
                'students' => 0,
                'locked' => 0,
                'unscheduled' => 0,
            ];
        }
        $summary[$key]['courses']++;
        $summary[$key]['students'] += intval($group['student_count'] ?? count($group['students'] ?? []));
        if (!empty($group['locked'])) {
            $summary[$key]['locked']++;
        }
        if (empty($group['slots']) && empty($group['slot'])) {
            $summary[$key]['unscheduled']++;
        }
    }

    uasort($summary, function ($a, $b) {
        $cmp = strcmp((string)$a['class_year'], (string)$b['class_year']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)$a['subject'], (string)$b['subject']);
    });

    return array_values($summary);
}

function mcdp_unique_classes(array $students): string
{
    $classes = [];
    foreach ($students as $student) {
        $class = trim((string)($student['class'] ?? ''));
        if ($class !== '') {
            $classes[$class] = true;
        }
    }
    $classLabels = array_keys($classes);
    sort($classLabels, SORT_NATURAL | SORT_FLAG_CASE);
    return implode(', ', $classLabels);
}

function mcdp_export_group_key(array $group): string
{
    $groupKey = trim((string)(($group['plan_id'] ?? '') ?: ($group['key'] ?? '')));
    if ($groupKey === '') {
        $groupKey = md5(json_encode([$group['class_year'] ?? '', $group['subject'] ?? '', $group['part_index'] ?? '']));
    }

    return $groupKey;
}

function mcdp_export_group_short_label(array $group): string
{
    $partIndex = intval($group['part_index'] ?? 1);
    $partTotal = intval($group['part_total'] ?? 1);
    if ($partIndex <= 0) {
        $partIndex = 1;
    }
    if ($partTotal <= 0) {
        $partTotal = 1;
    }

    return $partIndex . '/' . $partTotal;
}

function mcdp_export_year_order($year): int
{
    $year = trim((string)$year);
    return preg_match('/^[1-5]$/', $year) ? intval($year) : 99;
}

function mcdp_export_sort_groups(array $groups): array
{
    usort($groups, function ($a, $b) {
        $cmp = mcdp_export_year_order($a['class_year'] ?? '') <=> mcdp_export_year_order($b['class_year'] ?? '');
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strnatcasecmp((string)($a['subject'] ?? ''), (string)($b['subject'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = intval($a['part_index'] ?? 1) <=> intval($b['part_index'] ?? 1);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp(mcdp_export_group_key($a), mcdp_export_group_key($b));
    });

    return $groups;
}

function mcdp_export_group_refs(array $groups): array
{
    $refs = [];
    $number = 1;
    foreach ($groups as $group) {
        $groupKey = mcdp_export_group_key($group);
        if (isset($refs[$groupKey])) {
            continue;
        }
        $subject = trim((string)($group['subject'] ?? ''));
        $refs[$groupKey] = [
            'number' => $number,
            'label' => $number . ($subject !== '' ? ' - ' . $subject : ''),
        ];
        $number++;
    }

    return $refs;
}

function mcdp_student_course_titles(array $groups, array $groupRefs): array
{
    $map = [];
    foreach ($groups as $group) {
        $groupKey = mcdp_export_group_key($group);
        $title = trim((string)($groupRefs[$groupKey]['label'] ?? ''));
        if ($title === '') {
            $title = trim((string)($group['subject'] ?? ''));
        }
        foreach (($group['students'] ?? []) as $student) {
            $studentId = intval($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }
            $map[$studentId][$groupKey] = $title;
        }
    }

    return $map;
}

function mcdp_student_other_courses(array $student, array $studentCourseTitles, string $currentGroupKey): array
{
    $studentId = intval($student['id'] ?? 0);
    if ($studentId <= 0) {
        return [];
    }

    $otherTitles = $studentCourseTitles[$studentId] ?? [];
    if ($currentGroupKey !== '') {
        unset($otherTitles[$currentGroupKey]);
    }
    return array_values(array_unique(array_filter(array_map('trim', $otherTitles))));
}

function mcdp_public_course_rows(array $groups, array $studentCourseCounts, array $studentCourseTitles, string $kind): array
{
    $rows = [];
    foreach ($groups as $group) {
        $students = (array)($group['students'] ?? []);
        $groupKey = mcdp_export_group_key($group);
        $groupTitle = mcdp_export_group_short_label($group);
        $groupClasses = mcdp_unique_classes($students);
        $studentCount = intval($group['student_count'] ?? count($students));
        $courseNumber = intval($GLOBALS['mcdp_export_group_refs'][$groupKey]['number'] ?? 0);

        foreach ($students as $student) {
            $studentId = intval($student['id'] ?? 0);
            $otherCourses = mcdp_student_other_courses($student, $studentCourseTitles, $groupKey);
            $rows[] = [
                'kind' => $kind,
                'group_key' => $groupKey,
                'course_number' => $courseNumber > 0 ? $courseNumber : '',
                'class_year' => (string)($group['class_year'] ?? ''),
                'subject' => (string)($group['subject'] ?? ''),
                'teacher' => trim((string)($student['teacher'] ?? '')),
                'classes' => $groupClasses,
                'group' => $groupTitle,
                'student_count' => $studentCount,
                'student_class' => trim((string)($student['class'] ?? '')),
                'student_name' => trim((string)($student['name'] ?? '')),
                'student_type' => !empty($student['auditor']) ? 'Uditore' : '',
                'other_courses' => implode("\n", $otherCourses),
                'other_courses_html' => implode('<br>', array_map('mcdp_h', $otherCourses)),
                'has_multi' => $studentId > 0 && intval($studentCourseCounts[$studentId] ?? 0) > 1,
            ];
        }
    }

    usort($rows, function ($a, $b) {
        $cmp = intval($a['course_number'] ?? 0) <=> intval($b['course_number'] ?? 0);
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strcmp((string)$a['class_year'], (string)$b['class_year']);
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strcmp((string)$a['subject'], (string)$b['subject']);
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strcmp((string)$a['group'], (string)$b['group']);
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strnatcasecmp((string)$a['student_class'], (string)$b['student_class']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strnatcasecmp((string)$a['student_name'], (string)$b['student_name']);
    });

    return $rows;
}

function mcdp_export_filename(string $ext): string
{
    return 'pianificazione_corsi_recupero_' . date('Ymd_His') . '.' . $ext;
}

function mcdp_export_column_width(array $rows, string $key, int $min, int $max, int $padding = 2): int
{
    $longest = 0;
    foreach ($rows as $row) {
        $value = str_replace(["\r\n", "\r"], "\n", (string)($row[$key] ?? ''));
        foreach (explode("\n", $value) as $line) {
            $lineLength = mb_strlen(trim($line), 'UTF-8');
            if ($lineLength > $longest) {
                $longest = $lineLength;
            }
        }
    }

    return max($min, min($max, $longest + $padding));
}

function mcdp_export_columns(): array
{
    return [
        'course_number' => 'N. corso',
        'class_year' => 'Anno',
        'subject' => 'Materia',
        'teacher' => 'Docente carenza',
        'classes' => 'Classi',
        'group' => 'Gruppo',
        'student_count' => 'N. studenti',
        'student_class' => 'Classe studente',
        'student_name' => 'Studente',
        'student_type' => 'Tipo',
        'other_courses' => 'Altri corsi/recuperi',
    ];
}

function mcdp_course_list_columns(): array
{
    return [
        'subject' => 'Disciplina-materia',
        'classes' => 'Classi abbinate al corso',
        'teacher' => 'Docente',
        'schedule' => 'Giorni ed orari del corso',
        'room' => 'Aula',
    ];
}

function mcdp_course_list_rows(array $groups): array
{
    $rows = [];
    foreach (mcdp_export_sort_groups($groups) as $group) {
        $students = (array)($group['students'] ?? []);
        $classes = mcdp_unique_classes($students);
        if ($classes === '') {
            $classes = trim((string)($group['class_year'] ?? ''));
        }
        $slots = $group['slots'] ?? [];
        if (empty($slots) && !empty($group['slot'])) {
            $slots = [$group['slot']];
        }
        $slotLabels = [];
        foreach ($slots as $slot) {
            $label = trim((string)($slot['label'] ?? ''));
            if ($label === '') {
                $date = trim((string)($slot['date'] ?? ''));
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
                    $date = $m[3] . '/' . $m[2] . '/' . $m[1];
                }
                $label = trim($date . ' ' . trim((string)($slot['start'] ?? '')) . '-' . trim((string)($slot['end'] ?? '')));
            }
            if ($label !== '') {
                $slotLabels[] = $label;
            }
        }
        $rows[] = [
            'subject' => trim((string)($group['subject'] ?? '')),
            'classes' => $classes,
            'teacher' => trim((string)(($group['docente_nome'] ?? '') ?: ($group['teacher_name'] ?? '') ?: ($group['docente'] ?? ''))),
            'schedule' => implode("\n", $slotLabels),
            'room' => trim((string)($group['aula'] ?? '')),
        ];
    }
    return $rows;
}

function mcdp_course_list_export_xlsx(array $rows): void
{
    require_once __DIR__ . '/../common/vendor/autoload.php';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Elenco corsi');
    $columns = mcdp_course_list_columns();
    $sheet->setCellValue('A1', 'Elenco corsi carenze');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $colIndex = 1;
    foreach ($columns as $label) {
        $sheet->setCellValueByColumnAndRow($colIndex++, 3, $label);
    }
    $sheet->getStyle('A3:E3')->getFont()->setBold(true);
    $rowIndex = 4;
    foreach ($rows as $row) {
        $colIndex = 1;
        foreach (array_keys($columns) as $key) {
            $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, (string)($row[$key] ?? ''));
        }
        $rowIndex++;
    }
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->getStyle('A:E')->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . mcdp_export_filename('xlsx') . '"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

function mcdp_course_list_export_pdf(array $rows): void
{
    if (!class_exists('TCPDF')) {
        $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdf)) {
            require_once $tcpdf;
        } else {
            require_once __DIR__ . '/../common/tcpdf/tcpdf.php';
        }
    }
    $columns = mcdp_course_list_columns();
    $html = '<h2>Elenco corsi carenze</h2><table border="1" cellpadding="4"><thead><tr>';
    foreach ($columns as $label) {
        $html .= '<th><strong>' . mcdp_h($label) . '</strong></th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach (array_keys($columns) as $key) {
            $html .= '<td>' . nl2br(mcdp_h($row[$key] ?? '')) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GestOre');
    $pdf->SetAuthor('GestOre');
    $pdf->SetTitle('Elenco corsi carenze');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(true, 8);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output(mcdp_export_filename('pdf'), 'D');
    exit;
}

function mcdp_export_xlsx(array $courseRows, array $itinereRows, array $plan): void
{
    require_once __DIR__ . '/../common/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $columns = mcdp_export_columns();

    foreach ([
        ['sheet' => $spreadsheet->getActiveSheet(), 'title' => 'Corsi', 'heading' => 'Lista corsi recupero carenze', 'rows' => $courseRows],
        ['sheet' => $spreadsheet->createSheet(), 'title' => 'Recupero in itinere', 'heading' => 'Recupero in itinere - gruppi sotto soglia', 'rows' => $itinereRows],
    ] as $sheetInfo) {
        $sheet = $sheetInfo['sheet'];
        $rows = (array)$sheetInfo['rows'];
        $sheet->setTitle($sheetInfo['title']);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));

        $sheet->setCellValue('A1', $sheetInfo['heading']);
        $sheet->setCellValue('A2', 'Anno carenza: ' . ($plan['school_year_label'] ?? '') . ' - generato il ' . date('d/m/Y H:i'));
        $sheet->fromArray(array_values($columns), null, 'A4', true);

        $rowIndex = 5;
        $courseRanges = [];
        foreach ($rows as $row) {
            $sheet->fromArray(array_map(function ($key) use ($row) {
                return trim((string)($row[$key] ?? ''));
            }, array_keys($columns)), null, 'A' . $rowIndex, true);

            $groupKey = trim((string)($row['group_key'] ?? ''));
            if ($groupKey !== '') {
                if (!isset($courseRanges[$groupKey])) {
                    $courseRanges[$groupKey] = [
                        'start' => $rowIndex,
                        'end' => $rowIndex,
                        'number' => intval($row['course_number'] ?? 0),
                    ];
                } else {
                    $courseRanges[$groupKey]['end'] = $rowIndex;
                }
            }

            if (intval($row['course_number'] ?? 0) % 2 === 0) {
                $sheet->getStyle('A' . $rowIndex . ':' . $lastCol . $rowIndex)
                    ->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('EAF6FF');
            }
            $rowIndex++;
        }
        if (empty($rows)) {
            $sheet->setCellValue('A5', 'Nessun gruppo.');
            $rowIndex = 6;
        }

        $lastRow = max(4, $rowIndex - 1);
        foreach ($courseRanges as $range) {
            $start = intval($range['start'] ?? 0);
            $end = intval($range['end'] ?? 0);
            if ($start > 0) {
                $sheet->mergeCells('A' . $start . ':A' . $end);
                $sheet->mergeCells('B' . $start . ':B' . $end);
                $sheet->mergeCells('C' . $start . ':C' . $end);
                $sheet->mergeCells('E' . $start . ':E' . $end);
                $sheet->mergeCells('F' . $start . ':F' . $end);
                $sheet->mergeCells('G' . $start . ':G' . $end);
                $sheet->getStyle('A' . $start . ':A' . $end)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getStyle('B' . $start . ':B' . $end)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getStyle('C' . $start . ':C' . $end)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getStyle('E' . $start . ':E' . $end)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getStyle('F' . $start . ':F' . $end)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getStyle('G' . $start . ':G' . $end)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $classStart = $start;
                $previousClass = null;
                for ($r = $start; $r <= $end; $r++) {
                    $currentClass = trim((string)$sheet->getCell('H' . $r)->getValue());
                    if ($previousClass === null) {
                        $previousClass = $currentClass;
                        continue;
                    }
                    if ($currentClass !== $previousClass) {
                        if ($r - 1 > $classStart) {
                            $sheet->mergeCells('D' . $classStart . ':D' . ($r - 1));
                            $sheet->mergeCells('H' . $classStart . ':H' . ($r - 1));
                            $sheet->getStyle('D' . $classStart . ':D' . ($r - 1))->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                            $sheet->getStyle('H' . $classStart . ':H' . ($r - 1))->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                        }
                        $classStart = $r;
                        $previousClass = $currentClass;
                    }
                }
                if ($end > $classStart) {
                    $sheet->mergeCells('D' . $classStart . ':D' . $end);
                    $sheet->mergeCells('H' . $classStart . ':H' . $end);
                    $sheet->getStyle('D' . $classStart . ':D' . $end)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    $sheet->getStyle('H' . $classStart . ':H' . $end)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                }
                $sheet->getStyle('A' . $start . ':' . $lastCol . $start)
                    ->getBorders()
                    ->getTop()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
                    ->getColor()
                    ->setRGB('5B7083');
                $sheet->getStyle('A' . $end . ':' . $lastCol . $end)
                    ->getBorders()
                    ->getBottom()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
                    ->getColor()
                    ->setRGB('5B7083');
            }
        }
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0B4F71');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('667085');
        $sheet->getStyle('A1:' . $lastCol . '2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4:' . $lastCol . '4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A4:' . $lastCol . '4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('0B79A5');
        $sheet->getStyle('A4:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('C7D2DA');
        $sheet->getStyle('A4:' . $lastCol . $lastRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle('A4:' . $lastCol . '4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5:A' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B5:B' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F5:G' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H5:H' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C5:C' . $lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('J5:J' . $lastRow)->getAlignment()->setWrapText(true);
        foreach ($courseRanges as $range) {
            $start = intval($range['start'] ?? 0);
            $end = intval($range['end'] ?? 0);
            if ($start <= 0 || $end < $start) {
                continue;
            }
            foreach (['A', 'B', 'C', 'E', 'F', 'G'] as $column) {
                $sheet->getStyle($column . $start . ':' . $column . $end)
                    ->getAlignment()
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
            $classStart = $start;
            $previousClass = null;
            for ($r = $start; $r <= $end; $r++) {
                $currentClass = trim((string)$sheet->getCell('H' . $r)->getValue());
                if ($previousClass === null) {
                    $previousClass = $currentClass;
                    continue;
                }
                if ($currentClass !== $previousClass) {
                    foreach (['D', 'H'] as $column) {
                        $sheet->getStyle($column . $classStart . ':' . $column . ($r - 1))
                            ->getAlignment()
                            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    }
                    $classStart = $r;
                    $previousClass = $currentClass;
                }
            }
            foreach (['D', 'H'] as $column) {
                $sheet->getStyle($column . $classStart . ':' . $column . $end)
                    ->getAlignment()
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
        }
        $sheet->getColumnDimension('A')->setWidth(9);
        $sheet->getColumnDimension('B')->setWidth(7);
        $sheet->getColumnDimension('C')->setWidth(34);
        $sheet->getColumnDimension('D')->setWidth(26);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(30);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(mcdp_export_column_width($rows, 'other_courses', 30, 58));
        $sheet->freezePane('A5');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . mcdp_export_filename('xlsx') . '"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

function mcdp_export_pdf(array $courseRows, array $itinereRows, array $plan): void
{
    if (!class_exists('TCPDF')) {
        $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdf)) {
            require_once $tcpdf;
        } else {
            require_once __DIR__ . '/../common/tcpdf/tcpdf.php';
        }
    }

    $columns = mcdp_export_columns();
    $widths = ['4%', '3%', '22%', '10%', '6%', '5%', '5%', '7%', '14%', '24%'];
    $html = '<style>
        h1 { color:#0b4f71; font-size:18px; }
        h2 { color:#0b4f71; font-size:12px; margin-top:10px; }
        .meta { color:#667085; font-size:9px; margin-bottom:8px; }
        table { border-collapse:collapse; font-size:7px; }
        th { background-color:#0b79a5; color:#ffffff; font-weight:bold; text-align:center; }
        td, th { border:1px solid #c7d2da; padding:4px; }
        .course-alt td { background-color:#eaf6ff; }
        .course-start td { border-top:1.6px solid #5b7083; }
        .course-end td { border-bottom:1.6px solid #5b7083; }
    </style>';
    $html .= '<h1>Lista corsi recupero carenze</h1>';
    $html .= '<div class="meta">Anno carenza: ' . mcdp_h($plan['school_year_label'] ?? '') . ' - corsi: ' . count($courseRows) . ' - recuperi in itinere: ' . count($itinereRows) . ' - generato il ' . date('d/m/Y H:i') . '</div>';

    foreach ([
        'Corsi di recupero' => $courseRows,
        'Recupero in itinere - gruppi sotto soglia' => $itinereRows,
    ] as $title => $rows) {
        $html .= '<h2>' . mcdp_h($title) . '</h2>';
        $html .= '<table width="100%" cellpadding="3"><thead><tr>';
        $i = 0;
        foreach ($columns as $label) {
            $html .= '<th width="' . $widths[$i] . '">' . mcdp_h($label) . '</th>';
            $i++;
        }
        $html .= '</tr></thead><tbody>';
        $rowGroups = [];
        foreach ($rows as $row) {
            $groupKey = trim((string)($row['group_key'] ?? ''));
            if ($groupKey === '') {
                $groupKey = 'row_' . count($rowGroups);
            }
            if (!isset($rowGroups[$groupKey])) {
                $rowGroups[$groupKey] = [];
            }
            $rowGroups[$groupKey][] = $row;
        }
        foreach ($rowGroups as $groupRows) {
            $rowspan = max(1, count($groupRows));
            $classSpans = [];
            $classSpanStarts = [];
            $classStart = 0;
            $previousClass = null;
            foreach ($groupRows as $index => $groupRow) {
                $currentClass = trim((string)($groupRow['student_class'] ?? ''));
                if ($previousClass === null) {
                    $previousClass = $currentClass;
                    continue;
                }
                if ($currentClass !== $previousClass) {
                    $classSpans[$classStart] = $index - $classStart;
                    $classSpanStarts[$classStart] = true;
                    $classStart = $index;
                    $previousClass = $currentClass;
                }
            }
            $classSpans[$classStart] = count($groupRows) - $classStart;
            $classSpanStarts[$classStart] = true;
            foreach ($groupRows as $rowIndex => $row) {
                $rowClasses = [];
                if (intval($row['course_number'] ?? 0) % 2 === 0) {
                    $rowClasses[] = 'course-alt';
                }
                if ($rowIndex === 0) {
                    $rowClasses[] = 'course-start';
                }
                if ($rowIndex === $rowspan - 1) {
                    $rowClasses[] = 'course-end';
                }
                $html .= '<tr' . (!empty($rowClasses) ? ' class="' . implode(' ', $rowClasses) . '"' : '') . '>';
                $i = 0;
                foreach (array_keys($columns) as $key) {
                    if (in_array($key, ['course_number', 'class_year', 'subject', 'classes', 'group', 'student_count'], true) && $rowIndex > 0) {
                        $i++;
                        continue;
                    }
                    if (in_array($key, ['teacher', 'student_class'], true) && empty($classSpanStarts[$rowIndex])) {
                        $i++;
                        continue;
                    }
                    $value = $key === 'other_courses'
                        ? (string)($row['other_courses_html'] ?? '')
                        : mcdp_h(trim((string)($row[$key] ?? '')));
                    $align = in_array($key, ['course_number', 'class_year', 'group', 'student_count', 'student_class'], true) ? ' align="center"' : '';
                    $nowrap = $key === 'subject' ? ' style="white-space:nowrap;"' : '';
                    $rowspanAttr = '';
                    if (in_array($key, ['course_number', 'class_year', 'subject', 'classes', 'group', 'student_count'], true) && $rowspan > 1) {
                        $rowspanAttr = ' rowspan="' . $rowspan . '"';
                    } elseif (in_array($key, ['teacher', 'student_class'], true) && intval($classSpans[$rowIndex] ?? 1) > 1) {
                        $rowspanAttr = ' rowspan="' . intval($classSpans[$rowIndex]) . '"';
                    }
                    $html .= '<td width="' . $widths[$i] . '" valign="middle"' . $align . $rowspanAttr . $nowrap . '>' . $value . '</td>';
                    $i++;
                }
                $html .= '</tr>';
            }
        }
        if (empty($rows)) {
            $html .= '<tr><td colspan="' . count($columns) . '">Nessun gruppo.</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GestOre');
    $pdf->SetAuthor('GestOre');
    $pdf->SetTitle('Lista corsi recupero carenze');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(true, 8);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output(mcdp_export_filename('pdf'), 'D');
    exit;
}

global $__anno_scolastico_corrente_id;

$schoolYears = mastercomDebtsSchoolYears();
$selectedYearId = intval($_REQUEST['school_year_id'] ?? ($__anno_scolastico_corrente_id ?? 0));
$minSize = max(2, intval($_REQUEST['min_size'] ?? 4));
$maxSize = max($minSize, intval($_REQUEST['max_size'] ?? 10));
if ($maxSize > 30) {
    $maxSize = 30;
}
$defaultCourseYearId = mcdp_next_school_year_id_from_rows($schoolYears, $selectedYearId);
$selectedCourseYearId = intval($_REQUEST['course_school_year_id'] ?? $defaultCourseYearId);

$message = '';
$error = '';
$importSummary = [];
$syncSummary = [];
$neoSyncSummary = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $planId = trim((string)($_POST['plan_id'] ?? ''));
    if ($action === 'import_real_plan') {
        $uploaded = $_FILES['real_plan_csv'] ?? null;
        $applyImport = !empty($_POST['apply_import']);
        if (!is_array($uploaded) || intval($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Seleziona un file CSV valido da importare.';
        } else {
            $importSummary = mastercomDebtsPlanImportRealCsv($selectedYearId, (string)($uploaded['tmp_name'] ?? ''), $applyImport);
            if (empty($importSummary['ok'])) {
                $error = trim((string)($importSummary['message'] ?? 'Import non riuscito.'));
            } elseif (!empty($importSummary['applied'])) {
                $message = trim((string)($importSummary['message'] ?? 'Import applicato.'));
            }
        }
    } elseif ($action === 'sync_real_plan_courses') {
        $debtYearId = intval($_POST['debt_school_year_id'] ?? $selectedYearId);
        $courseYearId = intval($_POST['course_school_year_id'] ?? $defaultCourseYearId);
        $selectedCourseYearId = $courseYearId;
        $syncSummary = mastercomDebtsPlanSyncRealCoursesToCorsi($selectedYearId, $debtYearId, $courseYearId);
        if (empty($syncSummary['ok'])) {
            $error = trim((string)($syncSummary['message'] ?? 'Sincronizzazione non riuscita.'));
        } else {
            $message = trim((string)($syncSummary['message'] ?? 'Sincronizzazione completata.'));
        }
    } elseif ($action === 'assign_neo_carenza') {
        $selectedCourseYearId = intval($_POST['course_school_year_id'] ?? $selectedCourseYearId);
        $assign = mastercomDebtsPlanAssignNeoCarenza(intval($_POST['id_corso'] ?? 0), intval($_POST['id_studente'] ?? 0));
        if (empty($assign['ok'])) {
            $error = trim((string)($assign['message'] ?? 'Abbinamento non riuscito.'));
        } else {
            $message = trim((string)($assign['message'] ?? 'Studente aggiunto al corso.'));
        }
    } elseif ($action === 'save_lock') {
        $dates = $_POST['slot_date'] ?? [];
        $starts = $_POST['slot_start'] ?? [];
        $ends = $_POST['slot_end'] ?? [];
        $slots = [];
        for ($i = 0; $i < 3; $i++) {
            $slots[] = [
                'date' => trim((string)($dates[$i] ?? '')),
                'start' => trim((string)($starts[$i] ?? '')),
                'end' => trim((string)($ends[$i] ?? '')),
            ];
        }
        $invalidSlots = false;
        foreach ($slots as $slot) {
            $startMinutes = mastercomDebtsPlanTimeMinutes((string)($slot['start'] ?? ''));
            $endMinutes = mastercomDebtsPlanTimeMinutes((string)($slot['end'] ?? ''));
            if (($slot['date'] ?? '') === '' || $startMinutes < 0 || $endMinutes <= $startMinutes) {
                $invalidSlots = true;
                break;
            }
        }
        if ($invalidSlots) {
            $error = 'Per bloccare un gruppo servono tre date complete con orario valido.';
        } else {
            mastercomDebtsPlanSaveLock(
                $selectedYearId,
                $planId,
                $slots,
                trim((string)($_POST['aula'] ?? '')),
                intval($_POST['id_docente'] ?? 0)
            );
            $message = 'Gruppo bloccato e salvato.';
        }
    } elseif ($action === 'delete_lock') {
        mastercomDebtsPlanDeleteLock($selectedYearId, $planId);
        $message = 'Blocco rimosso.';
    }
}

try {
    if ($selectedYearId > 0) {
        $neoSyncSummary = mastercomDebtsPlanSyncNeoIscrizioniCarenze($selectedYearId);
    }
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'Sincronizzazione carenze neo-iscritti non riuscita: ' . $e->getMessage();
    }
}

$plan = $selectedYearId > 0 ? mastercomDebtsPlanBuild($selectedYearId, $minSize, $maxSize) : null;
$scheduled = $plan['scheduled_groups'] ?? [];
$unscheduled = $plan['unscheduled_groups'] ?? [];
$autonomous = $plan['autonomous_groups'] ?? [];
$teachers = mastercomDebtsPlanTeacherRows();
$neoCarenzeRows = mastercomDebtsPlanNeoCarenzeRows($selectedCourseYearId, $selectedYearId);
$studentCourseCounts = $plan['student_course_counts'] ?? [];
$courseSummaryRows = $plan !== null ? mcdp_course_summary_rows(array_merge($scheduled, $unscheduled)) : [];
$multiDebtStudents = 0;
foreach ($studentCourseCounts as $count) {
    if (intval($count) > 1) {
        $multiDebtStudents++;
    }
}

$export = strtolower(trim((string)($_GET['export'] ?? '')));
if ($plan !== null && in_array($export, ['course_list_pdf', 'course_list_xlsx'], true)) {
    $courseListRows = mcdp_course_list_rows(array_merge($scheduled, $unscheduled));
    if ($export === 'course_list_xlsx') {
        mcdp_course_list_export_xlsx($courseListRows);
    }
    mcdp_course_list_export_pdf($courseListRows);
}
if ($plan !== null && in_array($export, ['pdf', 'xlsx'], true)) {
    $courseGroupsForExport = mcdp_export_sort_groups(array_merge($scheduled, $unscheduled));
    $itinereGroupsForExport = mcdp_export_sort_groups($autonomous);
    $exportGroups = array_merge($courseGroupsForExport, $itinereGroupsForExport);
    $GLOBALS['mcdp_export_group_refs'] = mcdp_export_group_refs($exportGroups);
    $studentCourseTitles = mcdp_student_course_titles($exportGroups, $GLOBALS['mcdp_export_group_refs']);
    $exportStudentCourseCounts = [];
    foreach ($studentCourseTitles as $studentId => $titles) {
        $exportStudentCourseCounts[$studentId] = count($titles);
    }
    $courseRows = mcdp_public_course_rows($courseGroupsForExport, $exportStudentCourseCounts, $studentCourseTitles, 'corso');
    $itinereRows = mcdp_public_course_rows($itinereGroupsForExport, $exportStudentCourseCounts, $studentCourseTitles, 'itinere');
    if ($export === 'xlsx') {
        mcdp_export_xlsx($courseRows, $itinereRows, $plan);
    }
    mcdp_export_pdf($courseRows, $itinereRows, $plan);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Pianificazione recupero carenze</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .mcdp-toolbar {
            background: linear-gradient(#eef8ff, #bfe8fb);
            border: 1px solid #6ab7d8;
            border-radius: 4px;
            padding: 14px;
            margin-bottom: 16px;
        }
        .mcdp-toolbar-row {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }
        .mcdp-toolbar-row .form-group {
            margin-bottom: 0;
        }
        .mcdp-kpis {
            display: grid;
            grid-template-columns: repeat(5, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        .mcdp-kpi {
            border: 1px solid #d7e3ec;
            background: #fff;
            border-radius: 4px;
            padding: 12px;
            min-height: 80px;
        }
        .mcdp-kpi .label {
            display: block;
            color: #456;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0;
            background: transparent;
            padding: 0;
        }
        .mcdp-kpi .value {
            display: block;
            font-size: 28px;
            font-weight: 700;
            color: #0b5d7e;
            line-height: 1.15;
        }
        .mcdp-table th,
        .mcdp-table td {
            vertical-align: middle !important;
        }
        .mcdp-table th:nth-child(1),
        .mcdp-table th:nth-child(2),
        .mcdp-table th:nth-child(5),
        .mcdp-table th:nth-child(6) {
            text-align: center;
        }
        .mcdp-table .mcdp-slot {
            font-weight: 700;
            color: #0b5d7e;
            white-space: nowrap;
        }
        .mcdp-summary-table {
            max-width: 980px;
            margin-bottom: 18px;
        }
        .mcdp-summary-table th:nth-child(1),
        .mcdp-summary-table th:nth-child(3),
        .mcdp-summary-table th:nth-child(4),
        .mcdp-summary-table th:nth-child(5),
        .mcdp-summary-table th:nth-child(6) {
            text-align: center;
        }
        .mcdp-autonomous td {
            background: #fff8df !important;
        }
        .mcdp-unscheduled td {
            background: #ffdede !important;
        }
        .mcdp-locked td {
            background: #e6f3ff !important;
        }
        .mcdp-lock-form {
            min-width: 430px;
        }
        .mcdp-lock-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 4px;
            margin-bottom: 5px;
        }
        .mcdp-lock-lesson {
            display: grid;
            grid-template-columns: 20px 128px 86px 86px;
            gap: 4px;
            align-items: center;
        }
        .mcdp-lock-aula,
        .mcdp-lock-docente {
            margin-bottom: 5px;
        }
        .mcdp-lock-actions {
            display: flex;
            gap: 5px;
        }
        .mcdp-unlock-form {
            margin-top: 5px;
        }
        .mcdp-note {
            color: #456;
            margin-bottom: 16px;
        }
        .mcdp-import-panel {
            border: 1px solid #d7e3ec;
            background: #f8fbfd;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 16px;
        }
        .mcdp-import-row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .mcdp-import-row .form-group {
            margin-bottom: 0;
        }
        .mcdp-import-kpis {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        @media (max-width: 1100px) {
            .mcdp-kpis {
                grid-template-columns: repeat(2, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
<?php require_once headerAdminDidatticaPath('../common'); ?>
<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-calendar"></span>&emsp;Pianificazione recupero carenze
        </div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo mcdp_h($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo mcdp_h($error); ?></div>
            <?php endif; ?>

            <form method="get" class="mcdp-toolbar">
                <div class="mcdp-toolbar-row">
                    <div class="form-group">
                        <label for="school_year_id">Anno carenza</label>
                        <select id="school_year_id" name="school_year_id" class="form-control">
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?php echo intval($year['id']); ?>" <?php echo intval($year['id']) === $selectedYearId ? 'selected' : ''; ?>>
                                    <?php echo mcdp_h($year['anno']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="min_size">Min studenti</label>
                        <input type="number" min="2" max="10" class="form-control" id="min_size" name="min_size" value="<?php echo intval($minSize); ?>">
                    </div>
                    <div class="form-group">
                        <label for="max_size">Max studenti</label>
                        <input type="number" min="4" max="30" class="form-control" id="max_size" name="max_size" value="<?php echo intval($maxSize); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-refresh"></span> Rigenera
                    </button>
                    <a class="btn btn-danger" href="mastercom_debts_plan.php?school_year_id=<?php echo intval($selectedYearId); ?>&min_size=<?php echo intval($minSize); ?>&max_size=<?php echo intval($maxSize); ?>&export=pdf">
                        <span class="glyphicon glyphicon-file"></span> PDF
                    </a>
                    <a class="btn btn-success" href="mastercom_debts_plan.php?school_year_id=<?php echo intval($selectedYearId); ?>&min_size=<?php echo intval($minSize); ?>&max_size=<?php echo intval($maxSize); ?>&export=xlsx">
                        <span class="glyphicon glyphicon-list-alt"></span> XLS
                    </a>
                    <a class="btn btn-danger" href="mastercom_debts_plan.php?school_year_id=<?php echo intval($selectedYearId); ?>&min_size=<?php echo intval($minSize); ?>&max_size=<?php echo intval($maxSize); ?>&export=course_list_pdf">
                        <span class="glyphicon glyphicon-file"></span> PDF elenco corsi
                    </a>
                    <a class="btn btn-success" href="mastercom_debts_plan.php?school_year_id=<?php echo intval($selectedYearId); ?>&min_size=<?php echo intval($minSize); ?>&max_size=<?php echo intval($maxSize); ?>&export=course_list_xlsx">
                        <span class="glyphicon glyphicon-list-alt"></span> XLS elenco corsi
                    </a>
                    <a class="btn btn-default" href="mastercom_debts.php?school_year_id=<?php echo intval($selectedYearId); ?>">
                        <span class="glyphicon glyphicon-arrow-left"></span> Carenze
                    </a>
                </div>
            </form>

            <form method="post" enctype="multipart/form-data" class="mcdp-import-panel">
                <input type="hidden" name="action" value="import_real_plan">
                <input type="hidden" name="school_year_id" value="<?php echo intval($selectedYearId); ?>">
                <input type="hidden" name="min_size" value="<?php echo intval($minSize); ?>">
                <input type="hidden" name="max_size" value="<?php echo intval($maxSize); ?>">
                <div class="mcdp-import-row">
                    <div class="form-group">
                        <label for="real_plan_csv">Import corsi reali CSV</label>
                        <input type="file" class="form-control" id="real_plan_csv" name="real_plan_csv" accept=".csv,text/csv">
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="apply_import" value="1">
                            Applica import e sostituisci piano reale dell'anno
                        </label>
                    </div>
                    <button type="submit" class="btn btn-info">
                        <span class="glyphicon glyphicon-upload"></span> Importa CSV
                    </button>
                </div>
            </form>

            <?php echo mcdp_import_summary_html($importSummary); ?>
            <?php $defaultDebtYearId = $selectedYearId; ?>

            <form method="post" class="mcdp-import-panel">
                <input type="hidden" name="action" value="sync_real_plan_courses">
                <input type="hidden" name="school_year_id" value="<?php echo intval($selectedYearId); ?>">
                <input type="hidden" name="min_size" value="<?php echo intval($minSize); ?>">
                <input type="hidden" name="max_size" value="<?php echo intval($maxSize); ?>">
                <div class="mcdp-import-row">
                    <div class="form-group">
                        <label for="debt_school_year_id">Anno carenze sorgente</label>
                        <select id="debt_school_year_id" name="debt_school_year_id" class="form-control">
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?php echo intval($year['id']); ?>" <?php echo intval($year['id']) === $defaultDebtYearId ? 'selected' : ''; ?>>
                                    <?php echo mcdp_h($year['anno']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="course_school_year_id">Anno corsi didattici</label>
                        <select id="course_school_year_id" name="course_school_year_id" class="form-control">
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?php echo intval($year['id']); ?>" <?php echo intval($year['id']) === $selectedCourseYearId ? 'selected' : ''; ?>>
                                    <?php echo mcdp_h($year['anno']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-transfer"></span> Aggiorna corsi didattici
                    </button>
                    <a class="btn btn-default" href="../didattica/corsi.php">
                        <span class="glyphicon glyphicon-list-alt"></span> Vai ai corsi
                    </a>
                </div>
            </form>

            <?php echo mcdp_sync_summary_html($syncSummary); ?>

            <h4>Neo-iscritti con carenze da piazzare</h4>
            <div class="mcdp-note">
                Elenco ricavato dalle pratiche di iscrizione completate con carenze formative. Gli studenti esterni vengono sincronizzati in GestOre in classe provvisoria EE, mentre la carenza usa la classe tecnica dell'anno precedente all'iscrizione (es. 1EE se entra in seconda). Le carenze vengono create con docente #<?php echo intval(MASTERCOM_DEBTS_PLAN_NEO_CARENZE_DOCENTE_ID); ?>.
                Gli avvisi dei corsi per questi studenti dovranno usare le email dei genitori, non la mail studente.
                <?php if (!empty($neoSyncSummary)): ?>
                    <br>
                    Sincronizzazione: pratiche lette <strong><?php echo intval($neoSyncSummary['read'] ?? 0); ?></strong>,
                    studenti aggiornati <strong><?php echo intval($neoSyncSummary['students_synced'] ?? 0); ?></strong>,
                    carenze create <strong><?php echo intval($neoSyncSummary['created'] ?? 0); ?></strong>,
                    carenze aggiornate <strong><?php echo intval($neoSyncSummary['updated'] ?? 0); ?></strong>.
                    <?php if (!empty($neoSyncSummary['errors'])): ?>
                        <span class="text-danger">Avvisi: <?php echo mcdp_h(implode(' | ', array_slice((array)$neoSyncSummary['errors'], 0, 5))); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <table class="table table-bordered table-condensed mcdp-table">
                <thead>
                <tr>
                    <th>Studente</th>
                    <th>Classe</th>
                    <th>Materia</th>
                    <th>Email genitori</th>
                    <th>Corso</th>
                    <th>Abbinamento</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($neoCarenzeRows)): ?>
                    <tr><td colspan="6" class="text-center">Nessun neo-iscritto da piazzare per l'anno corsi selezionato.</td></tr>
                <?php endif; ?>
                <?php foreach ($neoCarenzeRows as $row): ?>
                    <tr>
                        <td><?php echo mcdp_h($row['student_name'] ?? ''); ?></td>
                        <td><?php echo mcdp_h($row['class_name'] ?? ''); ?></td>
                        <td>
                            <?php echo mcdp_h($row['subject'] ?? ''); ?>
                            <?php if (intval($row['subject_id'] ?? 0) <= 0): ?>
                                <div class="text-danger small">Materia non agganciata in GestOre</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo mcdp_h(implode(', ', (array)($row['parents'] ?? []))); ?></td>
                        <td>
                            <?php $courses = (array)($row['courses'] ?? []); ?>
                            <?php if (empty($courses)): ?>
                                <span class="text-danger">Nessun corso carenze disponibile per questa materia</span>
                            <?php else: ?>
                                <form method="post" class="form-inline">
                                    <input type="hidden" name="action" value="assign_neo_carenza">
                                    <input type="hidden" name="school_year_id" value="<?php echo intval($selectedYearId); ?>">
                                    <input type="hidden" name="course_school_year_id" value="<?php echo intval($selectedCourseYearId); ?>">
                                    <input type="hidden" name="min_size" value="<?php echo intval($minSize); ?>">
                                    <input type="hidden" name="max_size" value="<?php echo intval($maxSize); ?>">
                                    <input type="hidden" name="id_studente" value="<?php echo intval($row['student_id'] ?? 0); ?>">
                                    <select name="id_corso" class="form-control input-sm">
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo intval($course['id'] ?? 0); ?>">
                                                #<?php echo intval($course['id'] ?? 0); ?> -
                                                <?php echo mcdp_h(trim((string)($course['titolo'] ?? ''))); ?>
                                                <?php if (trim((string)($course['docente_nome'] ?? '')) !== ''): ?>
                                                    - <?php echo mcdp_h(trim((string)$course['docente_nome'])); ?>
                                                <?php endif; ?>
                                                (<?php echo intval($course['iscritti'] ?? 0); ?> iscritti)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-xs btn-success" style="margin-top:6px;">
                                        <span class="glyphicon glyphicon-plus"></span> Aggancia
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($courses)): ?>
                                <span class="label label-warning">Da piazzare</span>
                            <?php else: ?>
                                <span class="text-muted">Prima crea/sincronizza il corso</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($plan === null): ?>
                <div class="alert alert-warning">Seleziona un anno scolastico.</div>
            <?php else: ?>
                <?php if (!empty($plan['using_imported_plan'])): ?>
                    <div class="alert alert-info">
                        Sono caricati i corsi reali importati da CSV per l'anno
                        <strong><?php echo mcdp_h($plan['school_year_label']); ?></strong>.
                        La proposta automatica resta disponibile solo se si svuota l'import reale.
                    </div>
                <?php endif; ?>
                <div class="mcdp-note">
                    <?php if (!empty($plan['using_imported_plan'])): ?>
                        La pianificazione usa i corsi reali importati da CSV per l'anno
                        <strong><?php echo mcdp_h($plan['school_year_label']); ?></strong>,
                        inclusi partecipanti effettivi, docenti, aule, date e recuperi in itinere.
                    <?php else: ?>
                        La pianificazione usa le carenze non recuperate lette da MasterCom per l'anno
                        <strong><?php echo mcdp_h($plan['school_year_label']); ?></strong>.
                        I gruppi sono per anno di classe e materia. Il calendario proposto copre il periodo
                        <strong>24/08/<?php echo intval($plan['calendar_year']); ?> - 05/09/<?php echo intval($plan['calendar_year']); ?></strong>.
                        Ogni corso prevede tre lezioni da 2 ore scolastiche, cioe 100 minuti ciascuna, in tre giornate diverse.
                    <?php endif; ?>
                </div>

                <div class="mcdp-kpis">
                    <div class="mcdp-kpi"><span class="label">Carenze sorgente</span><span class="value"><?php echo intval($plan['source_rows']); ?></span></div>
                    <div class="mcdp-kpi"><span class="label">Gruppi base</span><span class="value"><?php echo count($plan['base_groups']); ?></span></div>
                    <div class="mcdp-kpi"><span class="label">Corsi proposti</span><span class="value"><?php echo count($plan['course_groups']); ?></span></div>
                    <div class="mcdp-kpi"><span class="label">Autonomi</span><span class="value"><?php echo count($autonomous); ?></span></div>
                    <div class="mcdp-kpi"><span class="label">Studenti con piu carenze</span><span class="value"><?php echo intval($multiDebtStudents); ?></span></div>
                </div>

                <?php if (!empty($unscheduled)): ?>
                    <div class="alert alert-danger">
                        Attenzione: <?php echo count($unscheduled); ?> gruppi non sono entrati negli slot disponibili senza sovrapporre studenti.
                    </div>
                <?php endif; ?>

                <h4>Riepilogo corsi e docenti</h4>
                <table class="table table-bordered table-condensed mcdp-table mcdp-summary-table">
                    <thead>
                    <tr>
                        <th>Anno</th>
                        <th>Materia</th>
                        <th>Corsi</th>
                        <th>Docenti necessari</th>
                        <th>Studenti</th>
                        <th>Bloccati</th>
                        <th>Non pianificati</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($courseSummaryRows)): ?>
                        <tr><td colspan="7" class="text-center">Nessun corso da riepilogare.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($courseSummaryRows as $row): ?>
                        <tr>
                            <td class="text-center"><?php echo mcdp_h($row['class_year']); ?></td>
                            <td><?php echo mcdp_h($row['subject']); ?></td>
                            <td class="text-center"><?php echo intval($row['courses']); ?></td>
                            <td class="text-center"><strong><?php echo intval($row['courses']); ?></strong></td>
                            <td class="text-center"><?php echo intval($row['students']); ?></td>
                            <td class="text-center"><?php echo intval($row['locked']); ?></td>
                            <td class="text-center"><?php echo intval($row['unscheduled']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h4>Corsi proposti</h4>
                <table class="table table-bordered table-condensed mcdp-table">
                    <thead>
                    <tr>
                        <th>Data / ora</th>
                        <th>Anno</th>
                        <th>Materia</th>
                        <th>Gruppo</th>
                        <th>Studenti</th>
                        <th>Elenco studenti</th>
                        <th>Blocca / modifica</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($scheduled)): ?>
                        <tr><td colspan="7" class="text-center">Nessun corso proposto.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($scheduled as $group): ?>
                        <tr class="<?php echo !empty($group['locked']) ? 'mcdp-locked' : ''; ?>">
                            <td class="text-center mcdp-slot"><?php echo mcdp_slots_html($group); ?></td>
                            <td class="text-center"><?php echo mcdp_h($group['class_year'] ?? ''); ?></td>
                            <td><?php echo mcdp_h($group['subject'] ?? ''); ?></td>
                            <td><?php echo mcdp_h(mcdp_group_title($group)); ?></td>
                            <td class="text-center"><?php echo intval($group['student_count'] ?? count($group['students'] ?? [])); ?></td>
                            <td><?php echo mcdp_students_html($group['students'] ?? []); ?></td>
                            <td><?php echo mcdp_group_lock_form($group, $teachers, $selectedYearId, $minSize, $maxSize); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($unscheduled as $group): ?>
                        <tr class="mcdp-unscheduled">
                            <td class="text-center"><strong>Non pianificato</strong></td>
                            <td class="text-center"><?php echo mcdp_h($group['class_year'] ?? ''); ?></td>
                            <td><?php echo mcdp_h($group['subject'] ?? ''); ?></td>
                            <td><?php echo mcdp_h(mcdp_group_title($group)); ?></td>
                            <td class="text-center"><?php echo intval($group['student_count'] ?? count($group['students'] ?? [])); ?></td>
                            <td><?php echo mcdp_students_html($group['students'] ?? []); ?></td>
                            <td><?php echo mcdp_group_lock_form($group, $teachers, $selectedYearId, $minSize, $maxSize); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h4>Recupero autonomo</h4>
                <table class="table table-bordered table-condensed mcdp-table">
                    <thead>
                    <tr>
                        <th>Anno</th>
                        <th>Materia</th>
                        <th>Studenti</th>
                        <th>Elenco studenti</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($autonomous)): ?>
                        <tr><td colspan="4" class="text-center">Nessun gruppo sotto soglia.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($autonomous as $group): ?>
                        <tr class="mcdp-autonomous">
                            <td class="text-center"><?php echo mcdp_h($group['class_year'] ?? ''); ?></td>
                            <td><?php echo mcdp_h($group['subject'] ?? ''); ?></td>
                            <td class="text-center"><?php echo intval($group['student_count'] ?? count($group['students'] ?? [])); ?></td>
                            <td><?php echo mcdp_students_html($group['students'] ?? []); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    document.getElementById('school_year_id').addEventListener('change', function () {
        this.form.submit();
    });
</script>
</body>
</html>

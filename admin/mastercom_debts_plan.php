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

function mcdp_public_course_rows(array $groups): array
{
    $rows = [];
    foreach ($groups as $group) {
        $slots = array_values($group['slots'] ?? []);
        if (empty($slots) && !empty($group['slot'])) {
            $slots = [$group['slot']];
        }
        while (count($slots) < 3) {
            $slots[] = ['label' => ''];
        }

        $rows[] = [
            'class_year' => (string)($group['class_year'] ?? ''),
            'subject' => (string)($group['subject'] ?? ''),
            'classes' => mcdp_unique_classes($group['students'] ?? []),
            'group' => mcdp_group_title($group),
            'students' => intval($group['student_count'] ?? count($group['students'] ?? [])),
            'lesson_1' => (string)($slots[0]['label'] ?? ''),
            'lesson_2' => (string)($slots[1]['label'] ?? ''),
            'lesson_3' => (string)($slots[2]['label'] ?? ''),
            'aula' => trim((string)($group['aula'] ?? '')),
            'docente' => trim((string)($group['docente_nome'] ?? '')),
        ];
    }

    usort($rows, function ($a, $b) {
        $cmp = strcmp((string)$a['class_year'], (string)$b['class_year']);
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strcmp((string)$a['subject'], (string)$b['subject']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)$a['lesson_1'], (string)$b['lesson_1']);
    });

    return $rows;
}

function mcdp_export_filename(string $ext): string
{
    return 'pianificazione_corsi_recupero_' . date('Ymd_His') . '.' . $ext;
}

function mcdp_export_columns(): array
{
    return [
        'class_year' => 'Anno',
        'subject' => 'Materia',
        'classes' => 'Classi',
        'group' => 'Gruppo',
        'students' => 'Studenti',
        'lesson_1' => 'Lezione 1',
        'lesson_2' => 'Lezione 2',
        'lesson_3' => 'Lezione 3',
        'aula' => 'Aula',
        'docente' => 'Docente',
    ];
}

function mcdp_export_xlsx(array $rows, array $plan): void
{
    require_once __DIR__ . '/../common/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Corsi recupero');

    $columns = mcdp_export_columns();
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));

    $sheet->setCellValue('A1', 'Calendario corsi recupero carenze');
    $sheet->setCellValue('A2', 'Anno carenza: ' . ($plan['school_year_label'] ?? '') . ' - generato il ' . date('d/m/Y H:i'));
    $sheet->fromArray(array_values($columns), null, 'A4');

    $rowIndex = 5;
    foreach ($rows as $row) {
        $sheet->fromArray(array_map(function ($key) use ($row) {
            $value = trim((string)($row[$key] ?? ''));
            if (($key === 'aula' || $key === 'docente') && $value === '') {
                return 'Da definire';
            }
            return $value;
        }, array_keys($columns)), null, 'A' . $rowIndex);
        $rowIndex++;
    }

    $lastRow = max(4, $rowIndex - 1);
    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0B4F71');
    $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('667085');
    $sheet->getStyle('A4:' . $lastCol . '4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A4:' . $lastCol . '4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('0B79A5');
    $sheet->getStyle('A4:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('C7D2DA');
    $sheet->getStyle('A4:' . $lastCol . $lastRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)->setWrapText(true);

    foreach (range(1, count($columns)) as $colIndex) {
        $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . mcdp_export_filename('xlsx') . '"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

function mcdp_export_pdf(array $rows, array $plan): void
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
    $widths = ['5%', '18%', '10%', '13%', '5%', '11%', '11%', '11%', '5%', '11%'];
    $html = '<style>
        h1 { color:#0b4f71; font-size:18px; }
        .meta { color:#667085; font-size:9px; margin-bottom:8px; }
        table { border-collapse:collapse; font-size:7px; }
        th { background-color:#0b79a5; color:#ffffff; font-weight:bold; text-align:center; }
        td, th { border:1px solid #c7d2da; padding:4px; }
    </style>';
    $html .= '<h1>Calendario corsi recupero carenze</h1>';
    $html .= '<div class="meta">Anno carenza: ' . mcdp_h($plan['school_year_label'] ?? '') . ' - corsi: ' . count($rows) . ' - generato il ' . date('d/m/Y H:i') . '</div>';
    $html .= '<table width="100%" cellpadding="3"><thead><tr>';
    $i = 0;
    foreach ($columns as $label) {
        $html .= '<th width="' . $widths[$i] . '">' . mcdp_h($label) . '</th>';
        $i++;
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        $i = 0;
        foreach (array_keys($columns) as $key) {
            $value = trim((string)($row[$key] ?? ''));
            if (($key === 'aula' || $key === 'docente') && $value === '') {
                $value = 'Da definire';
            }
            $html .= '<td width="' . $widths[$i] . '">' . mcdp_h($value) . '</td>';
            $i++;
        }
        $html .= '</tr>';
    }
    if (empty($rows)) {
        $html .= '<tr><td colspan="' . count($columns) . '">Nessun corso pianificato.</td></tr>';
    }
    $html .= '</tbody></table>';

    $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GestOre');
    $pdf->SetAuthor('GestOre');
    $pdf->SetTitle('Calendario corsi recupero carenze');
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

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $planId = trim((string)($_POST['plan_id'] ?? ''));
    if ($action === 'save_lock') {
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

$plan = $selectedYearId > 0 ? mastercomDebtsPlanBuild($selectedYearId, $minSize, $maxSize) : null;
$scheduled = $plan['scheduled_groups'] ?? [];
$unscheduled = $plan['unscheduled_groups'] ?? [];
$autonomous = $plan['autonomous_groups'] ?? [];
$teachers = mastercomDebtsPlanTeacherRows();
$studentCourseCounts = $plan['student_course_counts'] ?? [];
$courseSummaryRows = $plan !== null ? mcdp_course_summary_rows(array_merge($scheduled, $unscheduled)) : [];
$multiDebtStudents = 0;
foreach ($studentCourseCounts as $count) {
    if (intval($count) > 1) {
        $multiDebtStudents++;
    }
}

$export = strtolower(trim((string)($_GET['export'] ?? '')));
if ($plan !== null && in_array($export, ['pdf', 'xlsx'], true)) {
    $publicRows = mcdp_public_course_rows($scheduled);
    if ($export === 'xlsx') {
        mcdp_export_xlsx($publicRows, $plan);
    }
    mcdp_export_pdf($publicRows, $plan);
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
                    <a class="btn btn-default" href="mastercom_debts.php?school_year_id=<?php echo intval($selectedYearId); ?>">
                        <span class="glyphicon glyphicon-arrow-left"></span> Carenze
                    </a>
                </div>
            </form>

            <?php if ($plan === null): ?>
                <div class="alert alert-warning">Seleziona un anno scolastico.</div>
            <?php else: ?>
                <div class="mcdp-note">
                    La pianificazione usa le carenze non recuperate lette da MasterCom per l'anno
                    <strong><?php echo mcdp_h($plan['school_year_label']); ?></strong>.
                    I gruppi sono per anno di classe e materia. Il calendario proposto copre il periodo
                    <strong>24/08/<?php echo intval($plan['calendar_year']); ?> - 05/09/<?php echo intval($plan['calendar_year']); ?></strong>.
                    Ogni corso prevede tre lezioni da 2 ore scolastiche, cioe 100 minuti ciascuna, in tre giornate diverse.
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

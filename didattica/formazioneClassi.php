<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

formazioneClassiEnsureTables();

$tipiFormazione = formazioneClassiTipi();
$tipoFormazione = trim((string)($_GET['tipo_formazione'] ?? 'quinte'));
if (!isset($tipiFormazione[$tipoFormazione])) {
    $tipoFormazione = 'quinte';
}
$targetClassYear = intval($tipiFormazione[$tipoFormazione]['anno'] ?? 5);
$tipoFormazioneLabel = (string)($tipiFormazione[$tipoFormazione]['label'] ?? 'Future quinte');

$sourceYearId = intval($_GET['anno_origine_id'] ?? 0);
if ($sourceYearId <= 0) {
    $sourceYearId = formazioneClassiCurrentYearId();
}
$targetYearId = intval($_GET['anno_target_id'] ?? 0);
if ($targetYearId <= 0) {
    $targetYearId = formazioneClassiDefaultTargetYear($sourceYearId);
}
$indirizzo = trim((string)($_GET['indirizzo'] ?? ''));
$tabletFilter = formazioneClassiNormalizeTabletFilter((string)($_GET['tablet_filter'] ?? 'all'));

$schoolYears = formazioneClassiSchoolYears();
$formationMeta = [];
foreach ($tipiFormazione as $viewTipo => $viewData) {
    $formationMeta[$viewTipo] = [
        'label' => (string)($viewData['label'] ?? $viewTipo),
        'targetYear' => intval($viewData['anno'] ?? 5),
        'activeAddress' => '',
        'addresses' => [],
    ];
}
$addressOptions = [];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'meta') {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxMeta = [];
    $requestedTipo = trim((string)($_GET['tipo_formazione'] ?? ''));
    $metaTipi = isset($tipiFormazione[$requestedTipo]) ? [$requestedTipo => $tipiFormazione[$requestedTipo]] : $tipiFormazione;
    foreach ($metaTipi as $viewTipo => $viewData) {
        $viewTargetYear = intval($viewData['anno'] ?? 5);
        $viewAddressOptions = formazioneClassiAddressOptionsForFormation($sourceYearId, $viewTargetYear, $targetYearId);
        $viewActiveAddress = $viewTipo === $tipoFormazione ? $indirizzo : '';
        if (($viewActiveAddress === '' || !array_key_exists($viewActiveAddress, $viewAddressOptions)) && !empty($viewAddressOptions)) {
            $viewActiveAddress = (string)array_key_first($viewAddressOptions);
        }
        $ajaxMeta[$viewTipo] = [
            'label' => (string)($viewData['label'] ?? $viewTipo),
            'targetYear' => $viewTargetYear,
            'activeAddress' => $viewActiveAddress,
            'addresses' => $viewAddressOptions,
        ];
    }
    echo json_encode(['ok' => true, 'meta' => $ajaxMeta], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'section') {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxTipo = trim((string)($_GET['tipo_formazione'] ?? ''));
    $ajaxAddress = trim((string)($_GET['indirizzo'] ?? ''));
    $ajaxTabletFilter = formazioneClassiNormalizeTabletFilter((string)($_GET['tablet_filter'] ?? 'all'));
    if (!isset($tipiFormazione[$ajaxTipo])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Tipo formazione non valido.']);
        exit;
    }
    $ajaxTargetYear = intval($tipiFormazione[$ajaxTipo]['anno'] ?? 5);
    if ($ajaxAddress === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Indirizzo non valido.']);
        exit;
    }

    $ajaxState = formazioneClassiState($sourceYearId, $targetYearId, $ajaxTipo, $ajaxAddress, $ajaxTabletFilter);
    $ajaxClassColors = fc_class_colors(array_map(function ($class) {
        return (string)($class['label'] ?? '');
    }, $ajaxState['classes'] ?? []));

    echo json_encode([
        'ok' => true,
        'tipo' => $ajaxTipo,
        'indirizzo' => $ajaxAddress,
        'html' => fc_render_address_section($ajaxAddress, $ajaxState, $ajaxClassColors, $ajaxTargetYear),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fc_select($a, $b): string
{
    return (string)$a === (string)$b ? 'selected' : '';
}

function fc_anno_label(int $year): string
{
    $labels = [1 => 'prime', 2 => 'seconde', 3 => 'terze', 4 => 'quarte', 5 => 'quinte'];
    return $labels[$year] ?? 'classi';
}

function fc_unassigned_groups(array $students, int $targetClassYear): array
{
    $groups = [
        'bocciati' => [],
        'bocciati_persi' => [],
        'bocciati_seconda' => [],
        'bocciati_terza' => [],
        'promossi_seconda' => [],
        'neo_iscritti' => [],
        'altri' => [],
    ];

    foreach ($students as $student) {
        $origin = (string)($student['gruppo_origine'] ?? '');
        if ($origin === 'bocciato') {
            if (!empty($student['in_uscita'])) {
                $student['non_trascinabile'] = 1;
                $student['bocciato_perso'] = 1;
                $groups['bocciati_persi'][] = $student;
                continue;
            }
            $originYear = mastercomTabelloniClassYearFromName((string)($student['classe_origine'] ?? ''));
            if ($targetClassYear === 3 && $originYear === 2) {
                $student['non_trascinabile'] = 1;
                $groups['bocciati_seconda'][] = $student;
                continue;
            }
            if ($targetClassYear === 3 && $originYear === 3) {
                $groups['bocciati_terza'][] = $student;
                continue;
            }
            $groups['bocciati'][] = $student;
        } elseif ($origin === 'promosso') {
            if ($targetClassYear === 3) {
                $groups['promossi_seconda'][] = $student;
            } else {
                $student['gruppo_origine'] = 'neo_iscritto';
                $groups['neo_iscritti'][] = $student;
            }
        } elseif ($origin === 'neo_iscritto') {
            $groups['neo_iscritti'][] = $student;
        } else {
            $groups['altri'][] = $student;
        }
    }

    return $groups;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Formazione classi</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .fc-toolbar {
            background: #f8fbff;
            border: 1px solid #c7d8ea;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 14px;
        }
        .fc-toolbar-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }
        .fc-toolbar .form-group { margin-bottom: 0; min-width: 220px; }
        .fc-view-toggle {
            min-width: auto;
        }
        .fc-view-toggle .btn {
            height: 30px;
        }
        .fc-auto-panel {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 10px;
            margin-bottom: 12px;
            padding: 10px 12px;
            border: 1px solid #c7d8ea;
            border-left: 5px solid #2563eb;
            border-radius: 4px;
            background: #f8fbff;
        }
        .fc-auto-title {
            font-weight: 800;
            color: #102a43;
            margin-right: 6px;
            min-width: 160px;
        }
        .fc-auto-field {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 86px;
            margin: 0;
        }
        .fc-auto-field label {
            margin: 0;
            font-size: 11px;
            color: #52657a;
        }
        .fc-auto-field input {
            height: 30px;
            padding: 4px 7px;
        }
        .fc-auto-help {
            flex: 1 1 260px;
            color: #52657a;
            font-size: 12px;
            line-height: 1.25;
        }
        .fc-address-section {
            --fc-work-height: calc(100vh - 300px);
        }
        .fc-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 14px;
            align-items: stretch;
            height: var(--fc-work-height);
            min-height: 340px;
        }
        .fc-side-stack {
            height: 100%;
            max-height: none;
            overflow-y: auto;
            overscroll-behavior: contain;
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-width: 0;
        }
        .fc-classes-window {
            min-width: 0;
            height: 100%;
            max-height: none;
            min-height: 0;
            overflow: auto;
            overscroll-behavior: contain;
            padding-bottom: 0;
            scrollbar-gutter: stable both-edges;
        }
        .fc-classes {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            min-width: max-content;
        }
        .fc-class-panel {
            flex: 0 0 330px;
        }
        .fc-class-panel, .fc-bocciati-panel {
            border: 1px solid #d8dee8;
            border-left: 7px solid var(--fc-class-color, #4c78a8);
            border-radius: 4px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        .fc-class-heading, .fc-bocciati-heading {
            padding: 10px 12px;
            border-bottom: 1px solid #e5eaf1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            background: var(--fc-class-header, #eef6ff);
        }
        .fc-class-title {
            font-weight: 700;
            font-size: 16px;
            color: #102a43;
        }
        .fc-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 5px;
            padding: 8px 12px;
            color: #52657a;
            font-size: 12px;
            border-bottom: 1px solid #edf1f5;
        }
        .fc-stat strong {
            display: block;
            color: #17202f;
            font-size: 14px;
        }
        .fc-dropzone {
            min-height: 120px;
            padding: 9px;
            background: var(--fc-class-bg, #fafcff);
            transition: background .15s, outline .15s;
        }
        .fc-dropzone.fc-over {
            background: #fff8db;
            outline: 2px dashed #d59a00;
            outline-offset: -5px;
        }
        .fc-student {
            border: 1px solid #d7dde8;
            border-left: 5px solid #4c78a8;
            border-radius: 4px;
            background: #fff;
            padding: 8px 9px;
            margin-bottom: 7px;
            cursor: grab;
        }
        .fc-student.fc-student-bocciato {
            border-left-color: #dc2626;
            background: #fecaca;
            border-color: #ef4444;
            box-shadow: inset 0 0 0 1px #b91c1c, 0 1px 4px rgba(185, 28, 28, 0.22);
        }
        .fc-student.fc-student-bocciato .fc-student-name::after {
            content: " bocciato";
            display: inline-block;
            margin-left: 6px;
            padding: 1px 5px;
            border-radius: 3px;
            background: #dc2626;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }
        .fc-student.fc-student-neo-iscritto {
            border-left-color: #16a34a;
            background: #dcfce7;
            border-color: #86efac;
            box-shadow: inset 0 0 0 1px #22c55e, 0 1px 4px rgba(22, 163, 74, 0.18);
        }
        .fc-student.fc-student-neo-iscritto .fc-student-name::after {
            content: " neo iscritto";
            display: inline-block;
            margin-left: 6px;
            padding: 1px 5px;
            border-radius: 3px;
            background: #16a34a;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }
        .fc-student.fc-student-promosso {
            border-left-color: #2563eb;
            background: #dbeafe;
            border-color: #93c5fd;
            box-shadow: inset 0 0 0 1px #60a5fa, 0 1px 4px rgba(37, 99, 235, 0.16);
        }
        .fc-student.fc-student-promosso .fc-student-name::after {
            content: " promosso";
            display: inline-block;
            margin-left: 6px;
            padding: 1px 5px;
            border-radius: 3px;
            background: #2563eb;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }
        .fc-student.fc-student-uscita {
            border-left-color: #94a3b8;
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #64748b;
            cursor: default;
            opacity: .82;
        }
        .fc-student.fc-student-uscita .fc-student-name {
            text-decoration: line-through;
        }
        .fc-student.fc-student-uscita .fc-student-name::after {
            content: " in uscita";
            display: inline-block;
            margin-left: 6px;
            padding: 1px 5px;
            border-radius: 3px;
            background: #64748b;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }
        .fc-student.fc-student-locked {
            cursor: not-allowed;
            opacity: .9;
        }
        .fc-student.fc-student-lost {
            border-left-color: #7c3aed;
            background: #f5f3ff;
            border-color: #c4b5fd;
            color: #4338ca;
            cursor: not-allowed;
        }
        .fc-student.fc-student-lost .fc-student-name {
            text-decoration: none;
            color: #312e81;
        }
        .fc-student.fc-student-lost .fc-student-name::after {
            content: " perso";
            display: inline-block;
            margin-left: 6px;
            padding: 1px 5px;
            border-radius: 3px;
            background: #7c3aed;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }
        .fc-student.fc-cat-design {
            background: #f7ff00;
            border-color: #a3e635;
            border-left-color: #65a30d;
            box-shadow: 0 0 0 2px rgba(217, 249, 0, .55), 0 2px 8px rgba(77, 124, 15, .22);
        }
        .fc-student.fc-cat-design .fc-student-meta {
            color: #3f6212;
        }
        .fc-student.fc-cat-normal {
            background: #dbeafe;
            border-color: #60a5fa;
            border-left-color: #2563eb;
        }
        .fc-student:active { cursor: grabbing; }
        .fc-student-name { font-weight: 700; color: #17202f; }
        .fc-student-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
        }
        .fc-lock-btn {
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            background: #fff;
            color: #475569;
            padding: 2px 6px;
            line-height: 1.2;
            font-size: 12px;
        }
        .fc-lock-btn.locked {
            border-color: #b45309;
            background: #fef3c7;
            color: #92400e;
            font-weight: 800;
        }
        .fc-lock-btn .glyphicon,
        .fc-lock-btn .fc-lock-symbol {
            margin-right: 3px;
        }
        .fc-class-lock {
            margin-left: auto;
            white-space: nowrap;
        }
        .fc-student-lock {
            flex: 0 0 auto;
        }
        .fc-student-meta {
            color: #64748b;
            font-size: 12px;
            margin-top: 2px;
        }
        .fc-student-values {
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .fc-student-note {
            margin-top: 8px;
            padding: 8px 9px;
            border-radius: 4px;
            background: #fff7ed;
            border: 1px solid #fb923c;
            border-left: 5px solid #ea580c;
            color: #7c2d12;
            font-size: 12px;
            line-height: 1.35;
            white-space: pre-wrap;
        }
        .fc-student-note.fc-student-note-parent {
            background: #fffbeb;
            border-color: #f59e0b;
            border-left-color: #b45309;
            color: #78350f;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, .14);
        }
        .fc-student-note strong {
            display: block;
            color: #92400e;
            margin-bottom: 4px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .fc-curvature-badge {
            display: block;
            margin-top: 8px;
            padding: 7px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .fc-curvature-design {
            background: #1f2937;
            border: 1px solid #111827;
            border-left: 5px solid #65a30d;
            color: #f7ff00;
        }
        .fc-curvature-normal {
            background: #eff6ff;
            border: 1px solid #60a5fa;
            border-left: 5px solid #2563eb;
            color: #1e3a8a;
        }
        .fc-chip {
            border-radius: 3px;
            padding: 2px 5px;
            background: #edf2f7;
            color: #324255;
            font-size: 11px;
        }
        .fc-attr-chip {
            background: #fef3c7;
            color: #7c2d12;
            border: 1px solid #f59e0b;
            font-weight: 800;
        }
        .fc-bocciati-panel {
            border-left-color: #d97706;
            --fc-class-bg: #fff7ed;
            --fc-class-header: #ffedd5;
        }
        .fc-bocciati-heading {
            background: #fff3e0;
        }
        .fc-bocciati-panel .fc-student {
            border-left-color: #d97706;
        }
        .fc-side-panel-bocciati {
            border-left-color: #dc2626;
            --fc-class-bg: #fff1f2;
            --fc-class-header: #ffe4e6;
        }
        .fc-side-panel-bocciati .fc-bocciati-heading {
            background: #ffe4e6;
        }
        .fc-side-panel-bocciati .fc-student {
            border-left-color: #dc2626;
        }
        .fc-side-panel-bocciati-seconda {
            border-left-color: #475569;
            --fc-class-bg: #f8fafc;
            --fc-class-header: #e2e8f0;
        }
        .fc-side-panel-bocciati-seconda .fc-bocciati-heading {
            background: #e2e8f0;
        }
        .fc-side-panel-bocciati-seconda .fc-student {
            border-left-color: #475569;
        }
        .fc-side-panel-bocciati-persi {
            border-left-color: #7c3aed;
            --fc-class-bg: #f5f3ff;
            --fc-class-header: #ede9fe;
        }
        .fc-side-panel-bocciati-persi .fc-bocciati-heading {
            background: #ede9fe;
        }
        .fc-side-panel-bocciati-persi .fc-student {
            border-left-color: #7c3aed;
        }
        .fc-side-panel-neo {
            border-left-color: #16a34a;
            --fc-class-bg: #f0fdf4;
            --fc-class-header: #dcfce7;
        }
        .fc-side-panel-neo .fc-bocciati-heading {
            background: #dcfce7;
        }
        .fc-side-panel-neo .fc-student {
            border-left-color: #16a34a;
        }
        .fc-side-panel-promossi {
            border-left-color: #2563eb;
            --fc-class-bg: #eff6ff;
            --fc-class-header: #dbeafe;
        }
        .fc-side-panel-promossi .fc-bocciati-heading {
            background: #dbeafe;
        }
        .fc-side-panel-promossi .fc-student {
            border-left-color: #2563eb;
        }
        .fc-side-panel-altri {
            border-left-color: #64748b;
            --fc-class-bg: #f8fafc;
            --fc-class-header: #e2e8f0;
        }
        .fc-side-panel-altri .fc-bocciati-heading {
            background: #e2e8f0;
        }
        .fc-side-panel-altri .fc-student {
            border-left-color: #64748b;
        }
        .fc-empty {
            text-align: center;
            color: #748094;
            padding: 18px 8px;
            border: 1px dashed #d7dde8;
            border-radius: 4px;
            background: #fafcff;
        }
        .fc-summary {
            border-top: 1px solid #e5eaf1;
            padding: 10px 12px 12px;
            background: #fbfdff;
        }
        .fc-summary-title {
            font-weight: 700;
            color: #17202f;
            margin-bottom: 8px;
        }
        .fc-summary-indicator {
            border-bottom: 1px solid #e8edf4;
            padding: 8px 0;
        }
        .fc-summary-indicator:last-child { border-bottom: 0; }
        .fc-summary-indicator-title {
            font-weight: 700;
            color: #17202f;
            margin-bottom: 6px;
        }
        .fc-mf-legend {
            margin-top: -2px;
            margin-bottom: 6px;
            font-size: 11px;
            color: #52657a;
        }
        .fc-mf-legend span {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-right: 9px;
        }
        .fc-mf-legend i {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
        }
        .fc-summary-bar-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin: 5px 0;
        }
        .fc-summary-class-label {
            flex: 0 0 92px;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 700;
            color: #17202f;
        }
        .fc-summary-class-code {
            display: inline;
        }
        .fc-summary-bar-wrap {
            flex: 1 1 auto;
            min-width: 70px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 42px;
            align-items: center;
            gap: 6px;
        }
        .fc-bar-track {
            height: 8px;
            border-radius: 999px;
            background: #e5eaf1;
            overflow: hidden;
        }
        .fc-bar-fill {
            display: block;
            height: 100%;
            width: var(--fc-bar-width, 0%);
            border-radius: 999px;
            background: var(--fc-class-color, #4c78a8);
        }
        .fc-mf-track {
            display: flex;
            height: 8px;
            border-radius: 999px;
            background: #e5eaf1;
            overflow: hidden;
        }
        .fc-mf-male {
            display: block;
            height: 100%;
            width: var(--fc-mf-male-width, 0%);
            background: #2563eb;
        }
        .fc-mf-female {
            display: block;
            height: 100%;
            flex: 1 1 auto;
            background: #db2777;
        }
        .fc-bar-value {
            text-align: right;
            font-weight: 700;
            color: #17202f;
            font-size: 12px;
        }
        .fc-compact-dashboard {
            display: none;
        }
        .fc-address-section.fc-compact-mode .fc-layout {
            display: none;
        }
        .fc-address-section.fc-compact-mode .fc-compact-dashboard {
            display: block;
        }
        .fc-compact-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(340px, 1fr));
            gap: 14px;
            align-items: start;
        }
        .fc-compact-panel {
            border: 1px solid #d8dee8;
            border-radius: 4px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            padding: 12px;
        }
        .fc-compact-panel-wide {
            grid-column: 1 / -1;
        }
        .fc-compact-table {
            width: 100%;
            margin-bottom: 0;
            table-layout: fixed;
        }
        .fc-compact-table th,
        .fc-compact-table td {
            text-align: center;
            vertical-align: middle !important;
            white-space: nowrap;
        }
        .fc-compact-table th:first-child,
        .fc-compact-table td:first-child {
            text-align: left;
            width: 90px;
        }
        .fc-compact-table .fc-color-dot {
            margin-right: 6px;
        }
        .fc-color-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: -1px;
            background: var(--fc-class-color, #4c78a8);
        }
        .fc-status {
            min-height: 22px;
            margin-top: 8px;
            color: #52657a;
        }
        .fc-context-menu {
            position: fixed;
            z-index: 10000;
            display: none;
            min-width: 230px;
            padding: 5px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.22);
        }
        .fc-context-menu.open { display: block; }
        .fc-context-menu button {
            display: block;
            width: 100%;
            padding: 8px 10px;
            border: 0;
            border-radius: 3px;
            background: #fff;
            color: #17202f;
            text-align: left;
        }
        .fc-context-menu button:hover { background: #eff6ff; }
        .fc-context-menu button[disabled] {
            color: #94a3b8;
            cursor: not-allowed;
        }
        .fc-loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(15, 23, 42, .68);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .fc-loading-overlay.open { display: flex; }
        .fc-loading-card {
            width: min(520px, 100%);
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 22px 70px rgba(0, 0, 0, .32);
            padding: 24px;
            text-align: center;
            border-top: 7px solid #0ea5e9;
        }
        .fc-loading-title {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .fc-loading-text {
            color: #475569;
            font-size: 15px;
            min-height: 22px;
            margin-bottom: 14px;
        }
        .fc-loading-percent {
            font-size: 30px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 10px;
        }
        .fc-loading-track {
            height: 18px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .fc-loading-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #0ea5e9, #22c55e);
            transition: width .25s ease;
        }
        .fc-loading-detail {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }
        @media (max-width: 1100px) {
            .fc-layout {
                grid-template-columns: 1fr;
                height: auto;
                min-height: 0;
            }
            .fc-compact-grid { grid-template-columns: 1fr; }
            .fc-side-stack { height: auto; max-height: none; overflow: visible; }
            .fc-classes-window {
                height: auto;
                max-height: none;
                min-height: 0;
                overflow-x: auto;
                overflow-y: visible;
            }
            .fc-classes {
                min-width: 0;
            }
            .fc-class-panel {
                flex-basis: min(86vw, 330px);
            }
        }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>
<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading"><span class="glyphicon glyphicon-th-large"></span>&emsp;Formazione classi - <span id="fc_current_tipo_label"><?php echo formazioneClassiH(strtolower($tipoFormazioneLabel)); ?></span></div>
        <div class="panel-body">
            <form method="get" class="fc-toolbar" id="fc_filter_form">
                <div class="fc-toolbar-row">
                    <div class="form-group">
                        <label>Classi da formare</label>
                        <select name="tipo_formazione" class="form-control input-sm" id="fc_tipo_select">
                            <?php foreach ($tipiFormazione as $tipoKey => $tipoData): ?>
                                <option value="<?php echo formazioneClassiH($tipoKey); ?>" <?php echo fc_select($tipoFormazione, $tipoKey); ?>>
                                    <?php echo formazioneClassiH($tipoData['label'] ?? $tipoKey); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Anno origine tabelloni</label>
                        <select name="anno_origine_id" class="form-control input-sm fc-reload-select">
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?php echo intval($year['id']); ?>" <?php echo fc_select($sourceYearId, $year['id']); ?>>
                                    <?php echo formazioneClassiH($year['anno'] ?? $year['id']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Anno classi da formare</label>
                        <select name="anno_target_id" class="form-control input-sm fc-reload-select">
                            <?php foreach ($schoolYears as $year): ?>
                                <option value="<?php echo intval($year['id']); ?>" <?php echo fc_select($targetYearId, $year['id']); ?>>
                                    <?php echo formazioneClassiH($year['anno'] ?? $year['id']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Indirizzo</label>
                        <select name="indirizzo" class="form-control input-sm" id="fc_indirizzo_select">
                            <option value="">Caricamento indirizzi...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tablet</label>
                        <select name="tablet_filter" class="form-control input-sm" id="fc_tablet_filter">
                            <option value="all" <?php echo fc_select($tabletFilter, 'all'); ?>>Tutte</option>
                            <option value="tablet" <?php echo fc_select($tabletFilter, 'tablet'); ?>>Solo tablet</option>
                            <option value="non_tablet" <?php echo fc_select($tabletFilter, 'non_tablet'); ?>>Solo non tablet</option>
                        </select>
                    </div>
                    <div class="form-group fc-view-toggle">
                        <label>Vista</label><br>
                        <button type="button" class="btn btn-default btn-sm" id="fc_compact_toggle">
                            <span class="glyphicon glyphicon-stats"></span> Vista compatta
                        </button>
                    </div>
                    <div class="fc-status" id="fc_status"></div>
                </div>
            </form>

            <?php foreach ($tipiFormazione as $viewTipo => $view): ?>
                <?php $viewTargetClassYear = intval($view['anno'] ?? 0); ?>
                <div class="fc-formation-view" data-tipo="<?php echo formazioneClassiH($viewTipo); ?>" <?php echo $viewTipo === $tipoFormazione ? '' : 'hidden'; ?>>
                    <div class="alert alert-info">
                        <?php if (in_array($viewTargetClassYear, [2, 4, 5], true)): ?>
                            I promossi delle <?php echo formazioneClassiH(fc_anno_label($viewTargetClassYear - 1)); ?> dell'indirizzo selezionato sono gia nella classe futura corrispondente.
                        <?php elseif ($viewTargetClassYear === 1): ?>
                            La bozza include gli iscritti alle future prime collegati alle pratiche di iscrizione dell'anno target.
                        <?php elseif ($viewTargetClassYear === 3): ?>
                            La bozza include gli studenti delle iscrizioni alle future terze, filtrati in base all'indirizzo scelto.
                        <?php else: ?>
                            La bozza parte dalle sezioni target e dagli studenti gia disponibili per l'anno selezionato.
                        <?php endif; ?>
                        A destra trovi gli studenti da piazzare: bocciati in rosso e neo iscritti da fuori in verde. I bocciati con uscita o ritiro segnalato sono nel gruppo dei persi e non sono trascinabili.
                    </div>
                    <div class="fc-view-loader-placeholder" data-tipo="<?php echo formazioneClassiH($viewTipo); ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div id="fc_loading_overlay" class="fc-loading-overlay" role="status" aria-live="polite">
    <div class="fc-loading-card">
        <div class="fc-loading-title">Caricamento formazione classi</div>
        <div class="fc-loading-text" id="fc_loading_text">Preparazione dati...</div>
        <div class="fc-loading-percent" id="fc_loading_percent">0%</div>
        <div class="fc-loading-track"><div class="fc-loading-bar" id="fc_loading_bar"></div></div>
        <div class="fc-loading-detail" id="fc_loading_detail">0 di 0 sezioni caricate</div>
    </div>
</div>
<div id="fc_context_menu" class="fc-context-menu" role="menu" aria-hidden="true">
    <button type="button" id="fc_context_movimenti" role="menuitem">Apri pratica in movimenti studenti</button>
</div>
<script>
let fcDraggedId = 0;
let fcDraggedSourceZone = null;
let fcContextStudent = null;
let fcFormationMeta = <?php echo json_encode($formationMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};
const fcInitialTipo = <?php echo json_encode($tipoFormazione, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const fcInitialTabletFilter = <?php echo json_encode($tabletFilter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let fcSectionsLoaded = false;
const fcLoadedSections = {};
const fcSectionPromises = {};
let fcSelectionRequestId = 0;

document.addEventListener('DOMContentLoaded', function () {
    fcResizeWorkArea();
    fcLoadInitialFormation();
});
window.addEventListener('resize', fcResizeWorkArea);
window.addEventListener('orientationchange', fcResizeWorkArea);

async function fcLoadInitialFormation() {
    const requestId = fcBeginSelectionRequest();
    const tipo = document.getElementById('fc_tipo_select')?.value || fcInitialTipo || '';
    fcSetStatus('Calcolo indirizzi disponibili...');
    try {
        await fcEnsureFormationMeta(tipo);
        if (!fcIsCurrentSelectionRequest(requestId, tipo)) return;
        fcShowFormationType(tipo);
        const address = fcFormationMeta[tipo]?.activeAddress || '';
        await fcEnsureSectionLoaded(tipo, address, 'Caricamento ' + (fcFormationMeta[tipo]?.label || tipo) + ' - ' + (fcFormationMeta[tipo]?.addresses?.[address] || address), requestId);
        if (!fcIsCurrentSelectionRequest(requestId, tipo, address)) return;
        fcSectionsLoaded = true;
        fcSetStatus('');
        fcResizeWorkArea();
    } catch (error) {
        if (fcIsCurrentSelectionRequest(requestId, tipo)) {
            fcSetStatus('Errore nel caricamento della formazione classi.');
        }
    }
}

async function fcHandleFormationTypeChange(tipo) {
    const requestId = fcBeginSelectionRequest();
    fcShowLoadingOverlay('Caricamento formazione classi...');
    fcShowFormationType(tipo);
    fcSetStatus('Calcolo indirizzi disponibili...');
    try {
        await fcEnsureFormationMeta(tipo);
        if (!fcIsCurrentSelectionRequest(requestId, tipo)) return;
        fcShowFormationType(tipo);
        const address = fcFormationMeta[tipo]?.activeAddress || '';
        await fcEnsureSectionLoaded(tipo, address, 'Caricamento ' + (fcFormationMeta[tipo]?.label || tipo) + ' - ' + (fcFormationMeta[tipo]?.addresses?.[address] || address), requestId);
        if (!fcIsCurrentSelectionRequest(requestId, tipo, address)) return;
        fcSetStatus('');
        fcResizeWorkArea();
    } catch (error) {
        if (fcIsCurrentSelectionRequest(requestId, tipo)) {
            fcSetStatus('Errore nel caricamento della vista selezionata.');
        }
    } finally {
        if (fcIsCurrentSelectionRequest(requestId, tipo)) {
            fcHideLoadingOverlay();
        }
    }
}

async function fcHandleAddressChange(address) {
    const requestId = fcBeginSelectionRequest();
    const tipo = fcActiveFormationType();
    fcShowLoadingOverlay('Caricamento indirizzo selezionato...');
    fcShowAddress(address || '');
    if (!tipo || !address) {
        fcHideLoadingOverlay();
        return;
    }
    fcSetStatus('Caricamento ' + (fcFormationMeta[tipo]?.addresses?.[address] || address) + '...');
    try {
        await fcEnsureSectionLoaded(tipo, address, 'Caricamento ' + (fcFormationMeta[tipo]?.addresses?.[address] || address), requestId);
        if (!fcIsCurrentSelectionRequest(requestId, tipo, address)) return;
        fcSetStatus('');
        fcResizeWorkArea();
    } catch (error) {
        if (fcIsCurrentSelectionRequest(requestId, tipo, address)) {
            fcSetStatus('Errore nel caricamento dell\'indirizzo selezionato.');
        }
    } finally {
        if (fcIsCurrentSelectionRequest(requestId, tipo, address)) {
            fcHideLoadingOverlay();
        }
    }
}

async function fcHandleTabletFilterChange() {
    const requestId = fcBeginSelectionRequest();
    const tipo = fcActiveFormationType();
    const address = document.getElementById('fc_indirizzo_select')?.value || '';
    if (!tipo || !address) return;
    fcShowLoadingOverlay('Caricamento filtro tablet...');
    try {
        await fcEnsureSectionLoaded(tipo, address, 'Caricamento filtro tablet', requestId, true);
        if (!fcIsCurrentSelectionRequest(requestId, tipo, address)) return;
        fcSetStatus('');
        fcResizeWorkArea();
    } catch (error) {
        if (fcIsCurrentSelectionRequest(requestId, tipo, address)) {
            fcSetStatus('Errore nel caricamento del filtro tablet.');
        }
    } finally {
        if (fcIsCurrentSelectionRequest(requestId, tipo, address)) {
            fcHideLoadingOverlay();
        }
    }
}

function fcBeginSelectionRequest() {
    fcSelectionRequestId += 1;
    return fcSelectionRequestId;
}

function fcIsCurrentSelectionRequest(requestId, tipo, address) {
    if (requestId !== fcSelectionRequestId) {
        return false;
    }
    if (tipo && fcActiveFormationType() !== tipo) {
        return false;
    }
    if (address !== undefined) {
        const selectedAddress = document.getElementById('fc_indirizzo_select')?.value || '';
        if (selectedAddress !== address) {
            return false;
        }
    }
    return true;
}

async function fcEnsureFormationMeta(tipo) {
    if (tipo && fcFormationMeta[tipo] && Object.keys(fcFormationMeta[tipo].addresses || {}).length > 0) {
        return fcFormationMeta[tipo];
    }
    const meta = await fcFetchFormationMeta(tipo);
    Object.assign(fcFormationMeta, meta || {});
    return fcFormationMeta[tipo] || null;
}

function fcFetchFormationMeta(tipo) {
    const url = new URL(window.location.href);
    url.searchParams.set('ajax', 'meta');
    if (tipo) {
        url.searchParams.set('tipo_formazione', tipo);
    }
    return fetch(url.toString(), {credentials: 'same-origin'})
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function (json) {
            if (!json || !json.ok || !json.meta) {
                throw new Error(json && json.message ? json.message : 'Meta non validi');
            }
            return json.meta;
        });
}

async function fcEnsureSectionLoaded(tipo, address, text, requestId, forceReload) {
    if (!tipo || !address) {
        return;
    }
    const key = fcSectionKey(tipo, address);
    if (forceReload && fcLoadedSections[key]) {
        delete fcLoadedSections[key];
        const view = document.querySelector('.fc-formation-view[data-tipo="' + fcCssEscape(tipo) + '"]');
        const existing = view ? view.querySelector('.fc-address-section[data-address="' + fcCssEscape(address) + '"]') : null;
        if (existing) existing.remove();
    }
    if (fcLoadedSections[key]) {
        if (requestId === undefined || fcIsCurrentSelectionRequest(requestId, tipo, address)) {
            fcShowAddress(address);
        }
        return;
    }
    if (!fcSectionPromises[key]) {
        fcSetStatus((text || 'Caricamento sezione') + '...');
        fcSectionPromises[key] = fcFetchFormationSection(tipo, address)
            .then(function (html) {
                fcInsertFormationSection(tipo, address, html);
                fcLoadedSections[key] = true;
                return html;
            })
            .finally(function () {
                delete fcSectionPromises[key];
            });
    }
    await fcSectionPromises[key];
    if (requestId === undefined || fcIsCurrentSelectionRequest(requestId, tipo, address)) {
        fcShowAddress(address);
    }
}

function fcInsertFormationSection(tipo, address, html) {
    const view = document.querySelector('.fc-formation-view[data-tipo="' + fcCssEscape(tipo) + '"]');
    if (!view) {
        return;
    }
    const existing = view.querySelector('.fc-address-section[data-address="' + fcCssEscape(address) + '"]');
    if (existing) {
        fcInitFormationInteractions(existing);
        return;
    }
    const placeholder = view.querySelector('.fc-view-loader-placeholder');
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    Array.from(wrapper.children).forEach(function (child) {
        child.hidden = (child.dataset.address || '') !== (fcFormationMeta[tipo]?.activeAddress || '');
        if (placeholder) {
            placeholder.parentNode.insertBefore(child, placeholder);
        } else {
            view.appendChild(child);
        }
        fcInitFormationInteractions(child);
    });
    fcResizeWorkArea();
}

function fcSectionKey(tipo, address) {
    return tipo + '|' + address + '|' + fcActiveTabletFilter();
}

function fcFetchFormationSection(tipo, address) {
    const url = new URL(window.location.href);
    url.searchParams.set('ajax', 'section');
    url.searchParams.set('tipo_formazione', tipo);
    url.searchParams.set('indirizzo', address);
    url.searchParams.set('tablet_filter', fcActiveTabletFilter());
    return fetch(url.toString(), {credentials: 'same-origin'})
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function (json) {
            if (!json || !json.ok) {
                throw new Error(json && json.message ? json.message : 'Risposta non valida');
            }
            return String(json.html || '');
        });
}

function fcUpdateLoadingProgress(done, total, text) {
    const pct = total > 0 ? Math.round((done / total) * 100) : 100;
    const percent = document.getElementById('fc_loading_percent');
    const bar = document.getElementById('fc_loading_bar');
    const label = document.getElementById('fc_loading_text');
    const detail = document.getElementById('fc_loading_detail');
    if (percent) percent.textContent = pct + '%';
    if (bar) bar.style.width = pct + '%';
    if (label) label.textContent = text || '';
    if (detail) detail.textContent = done + ' di ' + total + ' passaggi completati';
}

function fcShowLoadingOverlay(text) {
    const overlay = document.getElementById('fc_loading_overlay');
    if (!overlay) return;
    const label = document.getElementById('fc_loading_text');
    const percent = document.getElementById('fc_loading_percent');
    const bar = document.getElementById('fc_loading_bar');
    const detail = document.getElementById('fc_loading_detail');
    if (label) label.textContent = text || 'Caricamento dati...';
    if (percent) percent.textContent = '';
    if (bar) bar.style.width = '100%';
    if (detail) detail.textContent = 'Attendere qualche secondo';
    overlay.classList.add('open');
}

function fcHideLoadingOverlay() {
    const overlay = document.getElementById('fc_loading_overlay');
    if (overlay) overlay.classList.remove('open');
}

function fcSetStatus(text) {
    const status = document.getElementById('fc_status');
    if (status) {
        status.textContent = text || '';
    }
}

function fcResizeWorkArea() {
    window.requestAnimationFrame(function () {
        document.querySelectorAll('.fc-address-section:not([hidden])').forEach(function (section) {
            if (section.classList.contains('fc-compact-mode')) {
                return;
            }
            const layout = section.querySelector('.fc-layout');
            if (!layout || window.matchMedia('(max-width: 1100px)').matches) {
                section.style.removeProperty('--fc-work-height');
                return;
            }
            const rect = layout.getBoundingClientRect();
            const available = Math.max(340, window.innerHeight - rect.top - 14);
            section.style.setProperty('--fc-work-height', available + 'px');
        });
    });
}

document.querySelectorAll('.fc-reload-select').forEach(function (select) {
    select.addEventListener('change', function () {
        const form = document.getElementById('fc_filter_form');
        if (form) {
            fcShowLoadingOverlay('Caricamento formazione classi...');
            form.submit();
        }
    });
});

document.getElementById('fc_tipo_select')?.addEventListener('change', function () {
    fcHandleFormationTypeChange(this.value || '');
});

document.getElementById('fc_indirizzo_select')?.addEventListener('change', function () {
    fcHandleAddressChange(this.value || '');
});

document.getElementById('fc_tablet_filter')?.addEventListener('change', function () {
    fcHandleTabletFilterChange();
});

document.getElementById('fc_compact_toggle')?.addEventListener('click', function () {
    const activeView = document.querySelector('.fc-formation-view:not([hidden])');
    const active = activeView ? activeView.querySelector('.fc-address-section:not([hidden])') : null;
    if (!active) return;
    const compact = !active.classList.contains('fc-compact-mode');
    document.querySelectorAll('.fc-formation-view').forEach(function (view) {
        view.querySelectorAll('.fc-address-section').forEach(function (section) {
            section.classList.toggle('fc-compact-mode', compact);
        });
    });
    this.innerHTML = compact
        ? '<span class="glyphicon glyphicon-th-large"></span> Vista dettaglio'
        : '<span class="glyphicon glyphicon-stats"></span> Vista compatta';
    fcResizeWorkArea();
});

function fcActiveFormationType() {
    return document.getElementById('fc_tipo_select')?.value || '';
}

function fcActiveTabletFilter() {
    return document.getElementById('fc_tablet_filter')?.value || fcInitialTabletFilter || 'all';
}

function fcActiveFormationView() {
    return document.querySelector('.fc-formation-view:not([hidden])');
}

function fcActiveAddressSection() {
    const view = fcActiveFormationView();
    return view ? view.querySelector('.fc-address-section:not([hidden])') : null;
}

function fcSetCompactMode(compact) {
    document.querySelectorAll('.fc-address-section').forEach(function (section) {
        section.classList.toggle('fc-compact-mode', compact);
    });
}

function fcShowAddress(address) {
    const activeView = fcActiveFormationView();
    let found = false;
    (activeView || document).querySelectorAll('.fc-address-section').forEach(function (section) {
        const active = (section.dataset.address || '') === address;
        section.hidden = !active;
        if (active) {
            found = true;
        }
    });
    const status = document.getElementById('fc_status');
    if (status) {
        status.textContent = found || !fcSectionsLoaded ? '' : 'Indirizzo non disponibile nella pagina caricata.';
    }
    const tipo = fcActiveFormationType();
    if (tipo !== '' && fcFormationMeta[tipo] && address !== '') {
        fcFormationMeta[tipo].activeAddress = address;
    }
    if (found && window.history && window.URLSearchParams) {
        const url = new URL(window.location.href);
        if (tipo !== '') {
            url.searchParams.set('tipo_formazione', tipo);
        }
        url.searchParams.set('indirizzo', address);
        url.searchParams.set('tablet_filter', fcActiveTabletFilter());
        window.history.replaceState({}, '', url.toString());
    }
    fcResizeWorkArea();
}

function fcShowFormationType(tipo) {
    const meta = fcFormationMeta[tipo] || null;
    if (!meta) {
        return;
    }

    document.querySelectorAll('.fc-formation-view').forEach(function (view) {
        view.hidden = (view.dataset.tipo || '') !== tipo;
    });

    const label = document.getElementById('fc_current_tipo_label');
    if (label) {
        label.textContent = String(meta.label || tipo).toLowerCase();
    }

    fcReplaceAddressOptions(meta.addresses || {}, meta.activeAddress || '');
    fcUpdateTabletFilterState(tipo);
    if (meta.activeAddress !== '') {
        fcShowAddress(meta.activeAddress || '');
    }
    fcResizeWorkArea();
}

function fcUpdateTabletFilterState(tipo) {
    const select = document.getElementById('fc_tablet_filter');
    if (!select) return;
    const targetYear = Number(fcFormationMeta[tipo]?.targetYear || 0);
    const enabled = targetYear === 1 || targetYear === 2;
    select.disabled = !enabled;
    if (!enabled && select.value !== 'all') {
        select.value = 'all';
    }
}

function fcReplaceAddressOptions(addresses, activeAddress) {
    const select = document.getElementById('fc_indirizzo_select');
    if (!select) return;
    select.innerHTML = '';
    const entries = Object.entries(addresses || {});
    entries.forEach(function ([value, label]) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        option.selected = value === activeAddress;
        select.appendChild(option);
    });
    if (entries.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Nessun indirizzo disponibile';
        select.appendChild(option);
    }
}

function fcInitFormationInteractions(scope) {
    scope = scope || document;

    scope.querySelectorAll('.fc-student').forEach(function (card) {
        if (card.dataset.fcDragBound === '1') {
            return;
        }
        card.dataset.fcDragBound = '1';
        card.addEventListener('dragstart', function (event) {
            if (card.classList.contains('fc-student-uscita') || card.classList.contains('fc-student-locked')) {
                event.preventDefault();
                return;
            }
            fcDraggedId = Number(card.dataset.rowId || 0);
            fcDraggedSourceZone = card.closest('.fc-dropzone');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(fcDraggedId));
        });
        card.addEventListener('dragend', function () {
            fcDraggedId = 0;
            fcDraggedSourceZone = null;
            document.querySelectorAll('.fc-dropzone.fc-over').forEach(function (dropzone) {
                dropzone.classList.remove('fc-over');
            });
        });
    });

    scope.querySelectorAll('.fc-dropzone').forEach(function (zone) {
        if (zone.dataset.readonly === '1' || zone.dataset.fcDropBound === '1') {
            return;
        }
        zone.dataset.fcDropBound = '1';
        zone.addEventListener('dragover', function (event) {
            event.preventDefault();
            zone.classList.add('fc-over');
        });
        zone.addEventListener('dragleave', function () {
            zone.classList.remove('fc-over');
        });
        zone.addEventListener('drop', function (event) {
            event.preventDefault();
            zone.classList.remove('fc-over');
            const rowId = Number(event.dataTransfer.getData('text/plain') || fcDraggedId || 0);
            const layout = zone.closest('.fc-layout');
            const sessionId = Number(layout?.dataset.sessionId || 0);
            if (!rowId || !sessionId || !layout) return;
            fcMoveStudent(rowId, zone.dataset.targetLabel || '', layout, sessionId, zone);
        });
    });

    scope.querySelectorAll('.fc-student[data-id-movimento]').forEach(function (card) {
        if (card.dataset.fcContextBound === '1') {
            return;
        }
        card.dataset.fcContextBound = '1';
        card.addEventListener('contextmenu', function (event) {
            if (Number(card.dataset.idMovimento || 0) <= 0) return;
            event.preventDefault();
            fcContextStudent = card;
            fcShowContextMenu(event.clientX, event.clientY);
        });
    });

    scope.querySelectorAll('.fc-auto-assign').forEach(function (button) {
        if (button.dataset.fcAutoBound === '1') {
            return;
        }
        button.dataset.fcAutoBound = '1';
        button.addEventListener('click', function () {
            fcAutoAssign(button);
        });
    });

    scope.querySelectorAll('.fc-student-lock').forEach(function (button) {
        if (button.dataset.fcLockBound === '1') {
            return;
        }
        button.dataset.fcLockBound = '1';
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            fcToggleStudentLock(button);
        });
    });

    scope.querySelectorAll('.fc-class-lock').forEach(function (button) {
        if (button.dataset.fcLockBound === '1') {
            return;
        }
        button.dataset.fcLockBound = '1';
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            fcToggleClassLock(button);
        });
    });
}

function fcToggleStudentLock(button) {
    const card = button.closest('.fc-student');
    const layout = button.closest('.fc-layout');
    const status = document.getElementById('fc_status');
    const sessionId = Number(layout?.dataset.sessionId || 0);
    const rowId = Number(card?.dataset.rowId || 0);
    const locked = button.dataset.locked !== '1';
    if (!sessionId || !rowId) return;
    fcPostLock({scope: 'student', session_id: sessionId, row_id: rowId, locked: locked ? 1 : 0}, button)
        .then(function (json) {
            if (!json || !json.ok) {
                if (status) status.textContent = json && json.message ? json.message : 'Blocco non salvato.';
                return;
            }
            fcApplyStudentLock(card, locked, card.dataset.classLocked === '1');
            if (status) status.textContent = json.message || 'Blocco salvato.';
        });
}

function fcToggleClassLock(button) {
    const panel = button.closest('.fc-class-panel');
    const layout = button.closest('.fc-layout');
    const status = document.getElementById('fc_status');
    const sessionId = Number(layout?.dataset.sessionId || 0);
    const classLabel = button.dataset.classLabel || panel?.dataset.classLabel || '';
    const locked = button.dataset.locked !== '1';
    if (!sessionId || !classLabel) return;
    fcPostLock({scope: 'class', session_id: sessionId, class_label: classLabel, locked: locked ? 1 : 0}, button)
        .then(function (json) {
            if (!json || !json.ok) {
                if (status) status.textContent = json && json.message ? json.message : 'Blocco classe non salvato.';
                return;
            }
            fcApplyClassLock(panel, locked);
            if (status) status.textContent = json.message || 'Blocco classe salvato.';
        });
}

function fcPostLock(payload, button) {
    const data = new FormData();
    Object.keys(payload).forEach(function (key) {
        data.append(key, String(payload[key]));
    });
    button.disabled = true;
    return fetch('formazioneClassiLock.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .catch(() => ({ok: false, message: 'Errore di rete durante il salvataggio del blocco.'}))
        .finally(() => {
            button.disabled = false;
        });
}

function fcApplyStudentLock(card, individualLocked, classLocked) {
    if (!card) return;
    const effectiveLocked = individualLocked || classLocked;
    card.classList.toggle('fc-student-locked', effectiveLocked);
    card.draggable = !effectiveLocked && !card.classList.contains('fc-student-uscita');
    card.dataset.classLocked = classLocked ? '1' : '0';
    const button = card.querySelector('.fc-student-lock');
    if (button) {
        button.dataset.locked = individualLocked ? '1' : '0';
        button.classList.toggle('locked', individualLocked);
        button.innerHTML = (individualLocked ? '<span class="glyphicon glyphicon-lock"></span>' : '<span class="fc-lock-symbol" aria-hidden="true">&#128275;</span>') + ' ' + (individualLocked ? 'Sblocca' : 'Blocca');
    }
}

function fcApplyClassLock(panel, locked) {
    if (!panel) return;
    const button = panel.querySelector('.fc-class-lock');
    if (button) {
        button.dataset.locked = locked ? '1' : '0';
        button.classList.toggle('locked', locked);
        button.innerHTML = (locked ? '<span class="glyphicon glyphicon-lock"></span>' : '<span class="fc-lock-symbol" aria-hidden="true">&#128275;</span>') + ' ' + (locked ? 'Sblocca classe' : 'Blocca classe');
    }
    panel.querySelectorAll('.fc-student').forEach(function (card) {
        const studentButton = card.querySelector('.fc-student-lock');
        const individualLocked = studentButton ? studentButton.dataset.locked === '1' : false;
        fcApplyStudentLock(card, individualLocked, locked);
    });
}

function fcAutoAssign(button) {
    const section = button.closest('.fc-address-section');
    const layout = section ? section.querySelector('.fc-layout') : null;
    const status = document.getElementById('fc_status');
    const sessionId = Number(layout?.dataset.sessionId || 0);
    if (!section || !layout || !sessionId) {
        if (status) status.textContent = 'Sezione formazione non trovata.';
        return;
    }

    const targetLabels = Array.from(layout.querySelectorAll('.fc-class-panel[data-class-label]'))
        .filter(panel => !(panel.querySelector('.fc-class-lock')?.dataset.locked === '1'))
        .map(panel => panel.dataset.classLabel || '')
        .filter(label => label !== '');
    const cards = Array.from(layout.querySelectorAll('.fc-dropzone:not([data-readonly="1"]) .fc-student'))
        .filter(card => {
            if (card.classList.contains('fc-student-uscita') || card.classList.contains('fc-student-locked')) {
                return false;
            }
            return card.classList.contains('fc-student-neo-iscritto') || card.classList.contains('fc-student-promosso');
        });
    const rowIds = cards.map(card => Number(card.dataset.rowId || 0)).filter(id => id > 0);
    if (targetLabels.length === 0 || rowIds.length === 0) {
        if (status) status.textContent = 'Non ci sono studenti da distribuire in questa sezione.';
        return;
    }

    const data = new FormData();
    data.append('session_id', String(sessionId));
    rowIds.forEach(id => data.append('row_ids[]', String(id)));
    targetLabels.forEach(label => data.append('target_labels[]', label));
    section.querySelectorAll('.fc-auto-weight').forEach(function (input) {
        data.append('weights[' + (input.dataset.weightKey || '') + ']', input.value || '0');
    });

    button.disabled = true;
    const previousHtml = button.innerHTML;
    button.innerHTML = '<span class="glyphicon glyphicon-refresh"></span> Distribuzione...';
    if (status) status.textContent = 'Distribuzione automatica in corso...';
    fetch('formazioneClassiAuto.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(json => {
            if (!json || !json.ok) {
                if (status) status.textContent = json && json.message ? json.message : 'Distribuzione non salvata.';
                return;
            }
            const assignments = json.assignments || {};
            Object.keys(assignments).forEach(function (rowId) {
                const card = layout.querySelector('.fc-student[data-row-id="' + fcCssEscape(rowId) + '"]');
                const targetZone = layout.querySelector('.fc-dropzone[data-target-label="' + fcCssEscape(assignments[rowId]) + '"]');
                if (card && targetZone) {
                    fcMoveCard(card, targetZone);
                }
            });
            fcRefreshAllStats(layout);
            if (status) status.textContent = json.message || 'Distribuzione automatica salvata.';
        })
        .catch(() => {
            if (status) status.textContent = 'Errore di rete durante la distribuzione automatica.';
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = previousHtml;
        });
}

function fcShowContextMenu(x, y) {
    const menu = document.getElementById('fc_context_menu');
    const button = document.getElementById('fc_context_movimenti');
    if (!menu || !button) return;
    const movementId = Number(fcContextStudent?.dataset.idMovimento || 0);
    button.disabled = movementId <= 0;
    button.textContent = movementId > 0 ? 'Apri pratica in movimenti studenti' : 'Nessuna pratica movimento collegata';
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
    menu.classList.add('open');
    menu.setAttribute('aria-hidden', 'false');

    const rect = menu.getBoundingClientRect();
    const left = Math.min(x, window.innerWidth - rect.width - 8);
    const top = Math.min(y, window.innerHeight - rect.height - 8);
    menu.style.left = Math.max(8, left) + 'px';
    menu.style.top = Math.max(8, top) + 'px';
}

function fcHideContextMenu() {
    const menu = document.getElementById('fc_context_menu');
    if (!menu) return;
    menu.classList.remove('open');
    menu.setAttribute('aria-hidden', 'true');
}

document.addEventListener('click', fcHideContextMenu);
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        fcHideContextMenu();
    }
});

document.getElementById('fc_context_movimenti')?.addEventListener('click', function () {
    const movementId = Number(fcContextStudent?.dataset.idMovimento || 0);
    if (movementId > 0) {
        window.open('movimentiStudenti.php?open_movimento_id=' + encodeURIComponent(String(movementId)), '_blank', 'noopener');
    }
    fcHideContextMenu();
});

function fcMoveStudent(rowId, targetLabel, layout, sessionId, droppedZone) {
    const status = document.getElementById('fc_status');
    const card = layout.querySelector('.fc-student[data-row-id="' + rowId + '"]');
    const targetZone = droppedZone || layout.querySelector('.fc-dropzone[data-target-label="' + fcCssEscape(targetLabel) + '"]');
    if (!card || !targetZone) {
        if (status) status.textContent = 'Elemento non trovato nella pagina.';
        return;
    }
    if (targetZone.dataset.readonly === '1') {
        if (status) status.textContent = 'Questo gruppo e solo di controllo: lo studente non e stato spostato.';
        return;
    }
    const targetPanel = targetZone.closest('.fc-class-panel');
    const classLock = targetPanel ? targetPanel.querySelector('.fc-class-lock') : null;
    if (classLock && classLock.dataset.locked === '1') {
        if (status) status.textContent = 'Classe bloccata: sblocca la classe prima di spostare studenti.';
        return;
    }
    if (card.closest('.fc-dropzone') === targetZone || fcDraggedSourceZone === targetZone) {
        if (status) status.textContent = '';
        return;
    }
    if (status) status.textContent = 'Salvataggio spostamento...';
    const data = new FormData();
    data.append('session_id', String(sessionId));
    data.append('row_id', String(rowId));
    data.append('target_label', targetLabel);
    fetch('formazioneClassiMove.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(json => {
            if (!json || !json.ok) {
                if (status) status.textContent = json && json.message ? json.message : 'Spostamento non salvato.';
                return;
            }
            if (status) status.textContent = json.message || 'Spostamento salvato.';
            fcMoveCard(card, targetZone);
            fcRefreshAllStats(layout);
        })
        .catch(() => {
            if (status) status.textContent = 'Errore di rete durante lo spostamento.';
        });
}

function fcMoveCard(card, targetZone) {
    targetZone.querySelectorAll('.fc-empty').forEach(function (empty) { empty.remove(); });
    targetZone.appendChild(card);
    fcSortZone(targetZone);
    document.querySelectorAll('.fc-dropzone:not([data-readonly="1"])').forEach(function (zone) {
        const hasCards = zone.querySelector('.fc-student');
        if (!hasCards && !zone.querySelector('.fc-empty')) {
            const empty = document.createElement('div');
            empty.className = 'fc-empty';
            empty.textContent = zone.dataset.emptyText || (zone.dataset.targetLabel ? 'Trascina qui gli studenti' : 'Nessuno studente da piazzare');
            zone.appendChild(empty);
        }
    });
}

function fcSortZone(zone) {
    const cards = Array.from(zone.querySelectorAll('.fc-student'));
    cards.sort(function (a, b) {
        return (a.dataset.name || '').localeCompare(b.dataset.name || '', 'it', {sensitivity: 'base'});
    });
    cards.forEach(function (card) {
        zone.appendChild(card);
    });
}

function fcRefreshAllStats(scope) {
    scope = scope || document;
    const allStats = {};
    scope.querySelectorAll('.fc-class-panel, .fc-bocciati-panel').forEach(function (panel) {
        const zone = panel.querySelector('.fc-dropzone');
        const stats = fcStatsFromZone(zone);
        const label = zone ? (zone.dataset.targetLabel || '') : '';
        if (label !== '') {
            allStats[label] = stats;
        }
        fcWriteStats(panel, stats);
        const badge = panel.querySelector('.label');
        if (badge && panel.dataset.preserveBadge !== '1') {
            badge.textContent = panel.classList.contains('fc-bocciati-panel') ? String(stats.count) : (stats.count + ' studenti');
        }
        if (label !== '') {
            fcWriteSummary(label, stats);
        }
    });
    fcRefreshSummaryScale(allStats, scope);
}

function fcStatsFromZone(zone) {
    const stats = {count: 0, maschi: 0, femmine: 0, media_generale: null, voto_matematica: null, voto_italiano: null, voto_capacita_relazionale: null, dsa: 0, fascia_c: 0, legge_104: 0};
    const sums = {media_generale: 0, voto_matematica: 0, voto_italiano: 0, voto_capacita_relazionale: 0};
    const counts = {media_generale: 0, voto_matematica: 0, voto_italiano: 0, voto_capacita_relazionale: 0};
    if (!zone) return stats;
    zone.querySelectorAll('.fc-student').forEach(function (card) {
        if (card.classList.contains('fc-student-uscita')) {
            return;
        }
        stats.count++;
        const sesso = String(card.dataset.sesso || '').toUpperCase();
        if (sesso === 'M') stats.maschi++;
        if (sesso === 'F') stats.femmine++;
        if (String(card.dataset.dsa || '') === '1') stats.dsa++;
        if (String(card.dataset.fascia_c || '') === '1') stats.fascia_c++;
        if (String(card.dataset.legge_104 || '') === '1') stats.legge_104++;
        Object.keys(sums).forEach(function (key) {
            const raw = card.getAttribute('data-' + key) || '';
            if (raw === '') return;
            const value = Number(raw);
            if (!Number.isFinite(value)) return;
            sums[key] += value;
            counts[key]++;
        });
    });
    Object.keys(sums).forEach(function (key) {
        stats[key] = counts[key] > 0 ? sums[key] / counts[key] : null;
    });
    return stats;
}

function fcWriteStats(panel, stats) {
    Object.keys(stats).forEach(function (key) {
        const target = panel.querySelector('[data-stat="' + key + '"]');
        if (!target) return;
        target.textContent = fcIsIntegerStat(key) ? String(stats[key]) : fcFormatAvg(stats[key]);
    });
}

function fcWriteSummary(label, stats) {
    const scope = fcActiveAddressSection() || document;
    scope.querySelectorAll('[data-summary-label="' + fcCssEscape(label) + '"]').forEach(function (row) {
        Object.keys(stats).forEach(function (key) {
            const target = row.querySelector('[data-summary-stat="' + key + '"]');
            if (!target) return;
            target.textContent = fcIsIntegerStat(key) ? String(stats[key]) : fcFormatAvg(stats[key]);
        });
        row.querySelectorAll('[data-bar-stat]').forEach(function (bar) {
            const key = bar.dataset.barStat || '';
            bar.style.setProperty('--fc-bar-width', fcBarWidth(key, stats[key], bar.dataset.barMax || ''));
        });
    });
    const mfTarget = scope.querySelector('[data-summary-mf-label="' + fcCssEscape(label) + '"]');
    if (mfTarget) {
        ['maschi', 'femmine'].forEach(function (key) {
            const target = mfTarget.querySelector('[data-summary-stat="' + key + '"]');
            if (!target) return;
            target.textContent = String(stats[key]);
        });
        const total = Math.max(1, Number(stats.maschi || 0) + Number(stats.femmine || 0));
        const maleWidth = Math.max(0, Math.min(100, (Number(stats.maschi || 0) / total) * 100));
        mfTarget.querySelectorAll('.fc-mf-track').forEach(function (track) {
            track.style.setProperty('--fc-mf-male-width', maleWidth + '%');
        });
    }
}

function fcRefreshSummaryScale(allStats, scope) {
    scope = scope || document;
    let maxCount = 0;
    Object.keys(allStats).forEach(function (label) {
        maxCount = Math.max(maxCount, Number(allStats[label].count || 0));
    });
    const maxForStudents = Math.max(1, maxCount + 1);
    scope.querySelectorAll('[data-bar-stat="count"]').forEach(function (bar) {
        const row = bar.closest('[data-summary-label]');
        if (!row) return;
        const label = row.getAttribute('data-summary-label') || '';
        const count = allStats[label] ? Number(allStats[label].count || 0) : 0;
        bar.style.setProperty('--fc-bar-width', Math.max(0, Math.min(100, (count / maxForStudents) * 100)) + '%');
    });
}

function fcIsIntegerStat(key) {
    return ['count', 'maschi', 'femmine', 'dsa', 'fascia_c', 'legge_104'].includes(key);
}

function fcBarWidth(key, value, maxValue) {
    const n = Number(value);
    if (!Number.isFinite(n) || n <= 0) return '0%';
    if (key === 'count') return '0%';
    const max = Number(maxValue || 10);
    return Math.max(0, Math.min(100, (n / Math.max(1, max)) * 100)) + '%';
}

function fcFormatAvg(value) {
    if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) {
        return '-';
    }
    return Number(value).toFixed(2).replace('.', ',');
}

function fcCssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
    }
    return String(value).replace(/["\\]/g, '\\$&');
}
</script>
</body>
</html>
<?php

function fc_render_stats(array $stats): string
{
    return '<div class="fc-stats">'
        . '<div class="fc-stat"><strong data-stat="count">' . intval($stats['count'] ?? 0) . '</strong>studenti</div>'
        . '<div class="fc-stat"><strong><span data-stat="maschi">' . intval($stats['maschi'] ?? 0) . '</span>/<span data-stat="femmine">' . intval($stats['femmine'] ?? 0) . '</span></strong>M/F</div>'
        . '<div class="fc-stat"><strong data-stat="media_generale">' . formazioneClassiH(formazioneClassiFormatAvg($stats['media_generale'] ?? null)) . '</strong>media</div>'
        . '<div class="fc-stat"><strong data-stat="voto_matematica">' . formazioneClassiH(formazioneClassiFormatAvg($stats['voto_matematica'] ?? null)) . '</strong>matematica</div>'
        . '<div class="fc-stat"><strong data-stat="voto_capacita_relazionale">' . formazioneClassiH(formazioneClassiFormatAvg($stats['voto_capacita_relazionale'] ?? null)) . '</strong>capacita rel.</div>'
        . '<div class="fc-stat"><strong data-stat="dsa">' . intval($stats['dsa'] ?? 0) . '</strong>DSA</div>'
        . '<div class="fc-stat"><strong data-stat="fascia_c">' . intval($stats['fascia_c'] ?? 0) . '</strong>fascia C</div>'
        . '<div class="fc-stat"><strong data-stat="legge_104">' . intval($stats['legge_104'] ?? 0) . '</strong>104</div>'
        . '</div>';
}

function fc_render_address_section(string $addressKey, array $state, array $classColors, int $targetClassYear): string
{
    ob_start();
    $sessionId = intval($state['session']['id'] ?? 0);
    ?>
    <div class="fc-address-section" data-address="<?php echo formazioneClassiH($addressKey); ?>">
        <div class="fc-compact-dashboard">
            <?php echo fc_render_compact_dashboard($state['classes'], $classColors); ?>
        </div>
        <div class="fc-auto-panel">
            <div class="fc-auto-title">
                <span class="glyphicon glyphicon-random"></span>
                Auto-distribuzione
            </div>
            <div class="fc-auto-field">
                <label>Media</label>
                <input type="number" class="form-control input-sm fc-auto-weight" data-weight-key="media_generale" value="4" min="0" max="20" step="0.5">
            </div>
            <div class="fc-auto-field">
                <label>Matematica</label>
                <input type="number" class="form-control input-sm fc-auto-weight" data-weight-key="voto_matematica" value="3" min="0" max="20" step="0.5">
            </div>
            <div class="fc-auto-field">
                <label>Italiano</label>
                <input type="number" class="form-control input-sm fc-auto-weight" data-weight-key="voto_italiano" value="2" min="0" max="20" step="0.5">
            </div>
            <div class="fc-auto-field">
                <label>Cap. relazionale</label>
                <input type="number" class="form-control input-sm fc-auto-weight" data-weight-key="voto_capacita_relazionale" value="2" min="0" max="20" step="0.5">
            </div>
            <div class="fc-auto-field">
                <label>M/F</label>
                <input type="number" class="form-control input-sm fc-auto-weight" data-weight-key="rapporto_mf" value="2" min="0" max="20" step="0.5">
            </div>
            <button type="button" class="btn btn-primary btn-sm fc-auto-assign">
                <span class="glyphicon glyphicon-flash"></span> Distribuisci da piazzare
            </button>
            <div class="fc-auto-help">Prima pareggia il numero studenti, poi distribuisce le ragazze; dopo rende simili media, matematica, italiano, capacita relazionale e rapporto M/F secondo i pesi indicati.</div>
        </div>
        <div class="fc-layout" data-session-id="<?php echo intval($sessionId); ?>">
            <div class="fc-classes-window">
                <div class="fc-classes">
                    <?php foreach ($state['classes'] as $class): ?>
                        <?php $stats = $class['stats']; ?>
                        <?php $classColor = $classColors[(string)$class['label']] ?? '#4c78a8'; ?>
                        <?php $classLocked = fc_class_locked($class['students']); ?>
                        <section class="fc-class-panel" data-class-label="<?php echo formazioneClassiH($class['label']); ?>" style="<?php echo formazioneClassiH(fc_class_style($classColor)); ?>">
                            <div class="fc-class-heading">
                                <div class="fc-class-title"><span class="fc-color-dot"></span><?php echo formazioneClassiH($class['label']); ?></div>
                                <span class="label label-primary"><?php echo intval($stats['count']); ?> studenti</span>
                                <button type="button" class="fc-lock-btn fc-class-lock <?php echo $classLocked ? 'locked' : ''; ?>" data-class-label="<?php echo formazioneClassiH($class['label']); ?>" data-locked="<?php echo $classLocked ? '1' : '0'; ?>" title="Blocca o sblocca tutti gli studenti della classe">
                                    <?php echo $classLocked ? '<span class="glyphicon glyphicon-lock"></span>' : '<span class="fc-lock-symbol" aria-hidden="true">&#128275;</span>'; ?> <?php echo $classLocked ? 'Sblocca classe' : 'Blocca classe'; ?>
                                </button>
                            </div>
                            <?php echo fc_render_stats($stats); ?>
                            <div class="fc-dropzone" data-target-label="<?php echo formazioneClassiH($class['label']); ?>">
                                <?php if (empty($class['students'])): ?>
                                    <div class="fc-empty">Trascina qui gli studenti</div>
                                <?php endif; ?>
                                <?php foreach ($class['students'] as $student): ?>
                                    <?php echo fc_render_student($student); ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php $unassignedGroups = fc_unassigned_groups($state['unassigned'], $targetClassYear); ?>
            <aside class="fc-side-stack">
                <section class="fc-bocciati-panel fc-side-panel-neo">
                    <div class="fc-bocciati-heading">
                        <div class="fc-class-title">Neo iscritti esterni</div>
                        <span class="label label-success"><?php echo count($unassignedGroups['neo_iscritti']); ?></span>
                    </div>
                    <?php echo fc_render_stats(formazioneClassiStats($unassignedGroups['neo_iscritti'])); ?>
                    <div class="fc-dropzone" data-target-label="" data-empty-text="Nessun neo iscritto esterno da piazzare">
                        <?php if (empty($unassignedGroups['neo_iscritti'])): ?>
                            <div class="fc-empty">Nessun neo iscritto esterno da piazzare</div>
                        <?php endif; ?>
                        <?php foreach ($unassignedGroups['neo_iscritti'] as $student): ?>
                            <?php echo fc_render_student($student); ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if ($targetClassYear === 3): ?>
                    <section class="fc-bocciati-panel fc-side-panel-promossi">
                        <div class="fc-bocciati-heading">
                            <div class="fc-class-title">Promossi di seconda</div>
                            <span class="label label-primary"><?php echo count($unassignedGroups['promossi_seconda']); ?></span>
                        </div>
                        <?php echo fc_render_stats(formazioneClassiStats($unassignedGroups['promossi_seconda'])); ?>
                        <div class="fc-dropzone" data-target-label="" data-empty-text="Nessun promosso di seconda da piazzare">
                            <?php if (empty($unassignedGroups['promossi_seconda'])): ?>
                                <div class="fc-empty">Nessun promosso di seconda da piazzare</div>
                            <?php endif; ?>
                            <?php foreach ($unassignedGroups['promossi_seconda'] as $student): ?>
                                <?php echo fc_render_student($student); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($unassignedGroups['altri'])): ?>
                    <section class="fc-bocciati-panel fc-side-panel-altri">
                        <div class="fc-bocciati-heading">
                            <div class="fc-class-title">Altri da piazzare</div>
                            <span class="label label-default"><?php echo count($unassignedGroups['altri']); ?></span>
                        </div>
                        <?php echo fc_render_stats(formazioneClassiStats($unassignedGroups['altri'])); ?>
                        <div class="fc-dropzone" data-target-label="" data-empty-text="Nessun altro studente da piazzare">
                            <?php foreach ($unassignedGroups['altri'] as $student): ?>
                                <?php echo fc_render_student($student); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($targetClassYear === 3): ?>
                    <section class="fc-bocciati-panel fc-side-panel-bocciati">
                        <div class="fc-bocciati-heading">
                            <div class="fc-class-title">Bocciati terza</div>
                            <span class="label label-danger"><?php echo count($unassignedGroups['bocciati_terza']); ?></span>
                        </div>
                        <?php echo fc_render_stats(formazioneClassiStats($unassignedGroups['bocciati_terza'])); ?>
                        <div class="fc-dropzone" data-target-label="" data-empty-text="Nessun bocciato di terza da piazzare">
                            <?php if (empty($unassignedGroups['bocciati_terza'])): ?>
                                <div class="fc-empty">Nessun bocciato di terza da piazzare</div>
                            <?php endif; ?>
                            <?php foreach ($unassignedGroups['bocciati_terza'] as $student): ?>
                                <?php echo fc_render_student($student); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="fc-bocciati-panel fc-side-panel-bocciati">
                        <div class="fc-bocciati-heading">
                            <div class="fc-class-title">Bocciati</div>
                            <span class="label label-danger"><?php echo count($unassignedGroups['bocciati']); ?></span>
                        </div>
                        <?php echo fc_render_stats(formazioneClassiStats($unassignedGroups['bocciati'])); ?>
                        <div class="fc-dropzone" data-target-label="" data-empty-text="Nessun bocciato da piazzare">
                            <?php if (empty($unassignedGroups['bocciati'])): ?>
                                <div class="fc-empty">Nessun bocciato da piazzare</div>
                            <?php endif; ?>
                            <?php foreach ($unassignedGroups['bocciati'] as $student): ?>
                                <?php echo fc_render_student($student); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($targetClassYear === 3): ?>
                    <section class="fc-bocciati-panel fc-side-panel-bocciati-seconda">
                        <div class="fc-bocciati-heading">
                            <div class="fc-class-title">Bocciati seconda</div>
                            <span class="label label-default"><?php echo count($unassignedGroups['bocciati_seconda']); ?></span>
                        </div>
                        <?php echo fc_render_stats(formazioneClassiStats($unassignedGroups['bocciati_seconda'])); ?>
                        <div class="fc-dropzone" data-target-label="" data-readonly="1" data-empty-text="Nessun bocciato di seconda">
                            <?php if (empty($unassignedGroups['bocciati_seconda'])): ?>
                                <div class="fc-empty">Nessun bocciato di seconda</div>
                            <?php endif; ?>
                            <?php foreach ($unassignedGroups['bocciati_seconda'] as $student): ?>
                                <?php echo fc_render_student($student); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="fc-bocciati-panel fc-side-panel-bocciati-persi" data-preserve-badge="1">
                    <div class="fc-bocciati-heading">
                        <div class="fc-class-title">Bocciati persi</div>
                        <span class="label label-default"><?php echo count($unassignedGroups['bocciati_persi']); ?></span>
                    </div>
                    <div class="fc-stats">
                        <div class="fc-stat"><strong><?php echo count($unassignedGroups['bocciati_persi']); ?></strong>persi</div>
                        <div class="fc-stat"><strong>-</strong>M/F</div>
                        <div class="fc-stat"><strong>-</strong>media</div>
                        <div class="fc-stat"><strong>-</strong>matematica</div>
                    </div>
                    <div class="fc-dropzone" data-target-label="" data-readonly="1" data-empty-text="Nessun bocciato perso">
                        <?php if (empty($unassignedGroups['bocciati_persi'])): ?>
                            <div class="fc-empty">Nessun bocciato perso</div>
                        <?php endif; ?>
                        <?php foreach ($unassignedGroups['bocciati_persi'] as $student): ?>
                            <?php echo fc_render_student($student); ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="fc-bocciati-panel">
                    <div class="fc-summary">
                        <div class="fc-summary-title">Confronto classi</div>
                        <?php echo fc_render_summary_by_indicator($state['classes'], $classColors); ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
    <?php
    return trim((string)ob_get_clean());
}

function fc_class_locked(array $students): bool
{
    if (empty($students)) {
        return false;
    }
    foreach ($students as $student) {
        if (empty($student['blocco_classe'])) {
            return false;
        }
    }
    return true;
}

function fc_render_student(array $student): string
{
    $classes = ['fc-student'];
    if (($student['gruppo_origine'] ?? '') === 'bocciato') {
        $classes[] = 'fc-student-bocciato';
    }
    if (($student['gruppo_origine'] ?? '') === 'neo_iscritto') {
        $classes[] = 'fc-student-neo-iscritto';
    }
    if (($student['gruppo_origine'] ?? '') === 'promosso') {
        $classes[] = 'fc-student-promosso';
    }
    if (!empty($student['in_uscita'])) {
        $classes[] = 'fc-student-uscita';
    }
    if (!empty($student['non_trascinabile']) || !empty($student['bloccato'])) {
        $classes[] = 'fc-student-locked';
    }
    if (!empty($student['bocciato_perso'])) {
        $classes[] = 'fc-student-lost';
    }
    if (($student['curvatura_design'] ?? '') === 'design') {
        $classes[] = 'fc-cat-design';
    } elseif (($student['curvatura_design'] ?? '') === 'normale') {
        $classes[] = 'fc-cat-normal';
    }
    $movementId = intval(($student['id_movimento_uscita'] ?? 0) ?: ($student['id_movimento'] ?? 0));
    $draggable = (empty($student['in_uscita']) && empty($student['non_trascinabile']) && empty($student['bloccato'])) ? 'true' : 'false';
    $hasDsa = formazioneClassiStudentHasAttr($student, STUD_ATTR_R7A2) ? 1 : 0;
    $hasFasciaC = formazioneClassiStudentHasAttr($student, STUD_ATTR_Z8C3) ? 1 : 0;
    $has104 = formazioneClassiStudentHasAttr($student, STUD_ATTR_Q4M9) ? 1 : 0;
    $html = '<div class="' . implode(' ', $classes) . '" draggable="' . $draggable . '"'
        . ' data-row-id="' . intval($student['id']) . '"'
        . ' data-id-movimento="' . $movementId . '"'
        . ' data-class-locked="' . (!empty($student['blocco_classe']) ? '1' : '0') . '"'
        . ' data-name="' . formazioneClassiH($student['nome'] ?? '') . '"'
        . ' data-sesso="' . formazioneClassiH($student['sesso'] ?? '') . '"'
        . ' data-dsa="' . $hasDsa . '"'
        . ' data-fascia_c="' . $hasFasciaC . '"'
        . ' data-legge_104="' . $has104 . '"'
        . ' data-media_generale="' . formazioneClassiH(fc_float_attr($student['media_generale'] ?? null)) . '"'
        . ' data-voto_matematica="' . formazioneClassiH(fc_float_attr($student['voto_matematica'] ?? null)) . '"'
        . ' data-voto_italiano="' . formazioneClassiH(fc_float_attr($student['voto_italiano'] ?? null)) . '"'
        . ' data-voto_capacita_relazionale="' . formazioneClassiH(fc_float_attr($student['voto_capacita_relazionale'] ?? null)) . '"'
        . '>';
    $html .= '<div class="fc-student-head">';
    $html .= '<div class="fc-student-name">' . formazioneClassiH($student['nome']) . '</div>';
    $locked = !empty($student['blocco_individuale']);
    $lockIcon = $locked ? '<span class="glyphicon glyphicon-lock"></span>' : '<span class="fc-lock-symbol" aria-hidden="true">&#128275;</span>';
    $html .= '<button type="button" class="fc-lock-btn fc-student-lock ' . ($locked ? 'locked' : '') . '" data-locked="' . ($locked ? '1' : '0') . '" title="Blocca o sblocca questo studente">' . $lockIcon . ' ' . ($locked ? 'Sblocca' : 'Blocca') . '</button>';
    $html .= '</div>';
    $meta = [];
    $studentGroup = (string)($student['gruppo_origine'] ?? '');
    $originClass = trim((string)($student['classe_origine'] ?? ''));
    if ($originClass !== '' && !($studentGroup === 'neo_iscritto' && formazioneClassiNorm($originClass) === 'MEDIE')) {
        $meta[] = 'ex ' . $student['classe_origine'];
    }
    if (trim($studentGroup) !== '') {
        $meta[] = str_replace('_', ' ', $studentGroup);
    }
    if (!empty($student['in_uscita'])) {
        $meta[] = !empty($student['uscita_confermata']) ? 'uscita confermata' : 'uscita/ritiro segnalato';
    }
    if (!empty($student['non_trascinabile']) && empty($student['bocciato_perso'])) {
        $meta[] = 'non assegnabile a una terza';
    }
    if (!empty($student['blocco_classe'])) {
        $meta[] = 'classe bloccata';
    } elseif (!empty($student['blocco_individuale'])) {
        $meta[] = 'studente bloccato';
    }
    if (!empty($student['bocciato_perso'])) {
        $meta[] = 'non assegnabile a una classe';
        $meta[] = 'cambio scuola o ritiro';
    }
    if (!empty($student['doppio_bocciato_non_consecutivo'])) {
        $meta[] = 'doppio bocciato non consecutivo';
    }
    $html .= '<div class="fc-student-meta">' . formazioneClassiH(implode(' · ', $meta)) . '</div>';
    $html .= '<div class="fc-student-values">';
    $html .= '<span class="fc-chip">Med ' . formazioneClassiH(formazioneClassiFormatAvg($student['media_generale'] ?? null)) . '</span>';
    $html .= '<span class="fc-chip">Mat ' . formazioneClassiH(formazioneClassiFormatAvg($student['voto_matematica'] ?? null)) . '</span>';
    $html .= '<span class="fc-chip">Ita ' . formazioneClassiH(formazioneClassiFormatAvg($student['voto_italiano'] ?? null)) . '</span>';
    $html .= '<span class="fc-chip">Rel ' . formazioneClassiH(formazioneClassiFormatAvg($student['voto_capacita_relazionale'] ?? null)) . '</span>';
    foreach (($student['attributi_riservati'] ?? []) as $attr) {
        $source = trim((string)($attr['fonte'] ?? ''));
        $title = $source !== '' ? ' title="fonte: ' . formazioneClassiH($source) . '"' : '';
        $html .= '<span class="fc-chip fc-attr-chip"' . $title . '>' . formazioneClassiH($attr['label'] ?? $attr['codice'] ?? '') . '</span>';
    }
    if (!empty($student['in_uscita'])) {
        $html .= '<span class="fc-chip">Fuori formazione</span>';
    }
    if (!empty($student['non_trascinabile'])) {
        $html .= '<span class="fc-chip">Solo controllo</span>';
    }
    if (!empty($student['bocciato_perso'])) {
        $html .= '<span class="fc-chip">Perso</span>';
    }
    $html .= '</div>';
    if (($student['curvatura_design'] ?? '') === 'design') {
        $html .= '<div class="fc-curvature-badge fc-curvature-design">CAT Design</div>';
    } elseif (($student['curvatura_design'] ?? '') === 'normale') {
        $html .= '<div class="fc-curvature-badge fc-curvature-normal">CAT Normale</div>';
    }
    if (trim((string)($student['note_formazione'] ?? '')) !== '') {
        $noteMeta = [];
        if (trim((string)($student['note_formazione_origine'] ?? '')) !== '') {
            $noteMeta[] = str_replace('_', ' ', (string)$student['note_formazione_origine']);
        }
        if (trim((string)($student['note_formazione_stato'] ?? '')) !== '') {
            $noteMeta[] = str_replace('_', ' ', (string)$student['note_formazione_stato']);
        }
        $fromIscrizione = in_array('iscrizione', $noteMeta, true);
        $title = $fromIscrizione ? 'Note genitori' : 'Nota segreteria';
        if ($noteMeta) {
            $title .= ' - ' . implode(' / ', $noteMeta);
        }
        $noteClass = $fromIscrizione ? ' fc-student-note-parent' : '';
        $html .= '<div class="fc-student-note' . $noteClass . '"><strong>' . formazioneClassiH($title) . '</strong>' . formazioneClassiH($student['note_formazione']) . '</div>';
    }
    if (!empty($student['in_uscita'])) {
        $state = str_replace('_', ' ', (string)($student['uscita_stato_pratica'] ?? ''));
        $title = !empty($student['uscita_confermata']) ? 'Uscita confermata' : 'Uscita/ritiro segnalato';
        $fallback = !empty($student['uscita_confermata']) ? 'pratica in uscita confermata' : 'pratica di uscita o ritiro avviata';
        $html .= '<div class="fc-student-note"><strong>' . formazioneClassiH($title) . '</strong>' . formazioneClassiH($state !== '' ? $state : $fallback) . '</div>';
    }
    $html .= '</div>';
    return $html;
}

function fc_summary_indicators(array $classes): array
{
    $max = [
        'count' => 1,
        'dsa' => 1,
        'fascia_c' => 1,
        'legge_104' => 1,
    ];
    foreach ($classes as $class) {
        $stats = (array)($class['stats'] ?? []);
        foreach (array_keys($max) as $key) {
            $max[$key] = max($max[$key], intval($stats[$key] ?? 0) + 1);
        }
    }

    return [
        'count' => ['label' => 'Numero studenti', 'max' => $max['count'], 'format' => 'int'],
        'media_generale' => ['label' => 'Media generale', 'max' => 10, 'format' => 'avg'],
        'voto_matematica' => ['label' => 'Matematica', 'max' => 10, 'format' => 'avg'],
        'voto_italiano' => ['label' => 'Italiano', 'max' => 10, 'format' => 'avg'],
        'voto_capacita_relazionale' => ['label' => 'Capacita relazionale', 'max' => 10, 'format' => 'avg'],
        'dsa' => ['label' => 'DSA', 'max' => $max['dsa'], 'format' => 'int'],
        'fascia_c' => ['label' => 'Fascia C', 'max' => $max['fascia_c'], 'format' => 'int'],
        'legge_104' => ['label' => '104', 'max' => $max['legge_104'], 'format' => 'int'],
    ];
}

function fc_render_compact_dashboard(array $classes, array $classColors): string
{
    $html = '<div class="fc-compact-grid">';
    $html .= '<section class="fc-compact-panel fc-compact-panel-wide">';
    $html .= '<div class="fc-summary-title">Quadro numerico classi</div>';
    $html .= '<div class="table-responsive"><table class="table table-condensed table-bordered fc-compact-table">';
    $html .= '<thead><tr><th>Classe</th><th>Studenti</th><th>M/F</th><th>Media</th><th>Mat.</th><th>Ita.</th><th>Cap. rel.</th><th>DSA</th><th>Fascia C</th><th>104</th></tr></thead><tbody>';
    foreach ($classes as $class) {
        $label = (string)($class['label'] ?? '');
        $stats = (array)($class['stats'] ?? []);
        $color = $classColors[$label] ?? '#4c78a8';
        $html .= '<tr data-summary-label="' . formazioneClassiH($label) . '" style="' . formazioneClassiH(fc_class_style($color)) . '">';
        $html .= '<td><span class="fc-color-dot"></span><strong>' . formazioneClassiH(fc_short_class_label($label)) . '</strong></td>';
        $html .= '<td data-summary-stat="count">' . intval($stats['count'] ?? 0) . '</td>';
        $html .= '<td><span data-summary-stat="maschi">' . intval($stats['maschi'] ?? 0) . '</span>/<span data-summary-stat="femmine">' . intval($stats['femmine'] ?? 0) . '</span></td>';
        $html .= '<td data-summary-stat="media_generale">' . formazioneClassiH(formazioneClassiFormatAvg($stats['media_generale'] ?? null)) . '</td>';
        $html .= '<td data-summary-stat="voto_matematica">' . formazioneClassiH(formazioneClassiFormatAvg($stats['voto_matematica'] ?? null)) . '</td>';
        $html .= '<td data-summary-stat="voto_italiano">' . formazioneClassiH(formazioneClassiFormatAvg($stats['voto_italiano'] ?? null)) . '</td>';
        $html .= '<td data-summary-stat="voto_capacita_relazionale">' . formazioneClassiH(formazioneClassiFormatAvg($stats['voto_capacita_relazionale'] ?? null)) . '</td>';
        $html .= '<td data-summary-stat="dsa">' . intval($stats['dsa'] ?? 0) . '</td>';
        $html .= '<td data-summary-stat="fascia_c">' . intval($stats['fascia_c'] ?? 0) . '</td>';
        $html .= '<td data-summary-stat="legge_104">' . intval($stats['legge_104'] ?? 0) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    $html .= '</section>';

    foreach (fc_summary_indicators($classes) as $key => $indicator) {
        $html .= '<section class="fc-compact-panel">';
        $html .= '<div class="fc-summary-title">' . formazioneClassiH($indicator['label']) . '</div>';
        foreach ($classes as $class) {
            $label = (string)($class['label'] ?? '');
            $stats = (array)($class['stats'] ?? []);
            $color = $classColors[$label] ?? '#4c78a8';
            $html .= fc_render_indicator_bar($label, $key, $stats[$key] ?? null, (float)$indicator['max'], $color, (string)$indicator['format']);
        }
        $html .= '</section>';
    }

    $html .= '<section class="fc-compact-panel">';
    $html .= '<div class="fc-summary-title">Maschi / femmine</div>';
    $html .= '<div class="fc-mf-legend"><span><i style="background:#2563eb"></i>Maschi</span><span><i style="background:#db2777"></i>Femmine</span></div>';
    foreach ($classes as $class) {
        $label = (string)($class['label'] ?? '');
        $stats = (array)($class['stats'] ?? []);
        $color = $classColors[$label] ?? '#4c78a8';
        $html .= fc_render_mf_bar($label, intval($stats['maschi'] ?? 0), intval($stats['femmine'] ?? 0), $color);
    }
    $html .= '</section>';
    $html .= '</div>';
    return $html;
}

function fc_render_summary_by_indicator(array $classes, array $classColors): string
{
    $indicators = fc_summary_indicators($classes);

    $html = '';
    foreach ($indicators as $key => $indicator) {
        $html .= '<div class="fc-summary-indicator">';
        $html .= '<div class="fc-summary-indicator-title">' . formazioneClassiH($indicator['label']) . '</div>';
        foreach ($classes as $class) {
            $label = (string)($class['label'] ?? '');
            $stats = (array)($class['stats'] ?? []);
            $color = $classColors[$label] ?? '#4c78a8';
            $html .= fc_render_indicator_bar($label, $key, $stats[$key] ?? null, (float)$indicator['max'], $color, (string)$indicator['format']);
        }
        $html .= '</div>';
    }

    $html .= '<div class="fc-summary-indicator">';
    $html .= '<div class="fc-summary-indicator-title">Maschi / femmine</div>';
    $html .= '<div class="fc-mf-legend"><span><i style="background:#2563eb"></i>Maschi</span><span><i style="background:#db2777"></i>Femmine</span></div>';
    foreach ($classes as $class) {
        $label = (string)($class['label'] ?? '');
        $stats = (array)($class['stats'] ?? []);
        $color = $classColors[$label] ?? '#4c78a8';
        $html .= fc_render_mf_bar($label, intval($stats['maschi'] ?? 0), intval($stats['femmine'] ?? 0), $color);
    }
    $html .= '</div>';

    return $html;
}

function fc_render_indicator_bar(string $label, string $key, $rawValue, float $max, string $color, string $format): string
{
    $raw = ($rawValue === null || $rawValue === '') ? 0.0 : (float)$rawValue;
    $width = $max > 0 ? max(0, min(100, ($raw / $max) * 100)) : 0;
    $display = $format === 'int' ? (string)intval($raw) : formazioneClassiFormatAvg($rawValue);
    return '<div class="fc-summary-bar-row" data-summary-label="' . formazioneClassiH($label) . '" style="' . formazioneClassiH(fc_class_style($color)) . '">'
        . '<span class="fc-summary-class-label"><span class="fc-color-dot"></span><span class="fc-summary-class-code">' . formazioneClassiH(fc_short_class_label($label)) . '</span></span>'
        . '<span class="fc-summary-bar-wrap">'
        . '<span class="fc-bar-track"><span class="fc-bar-fill" data-bar-stat="' . formazioneClassiH($key) . '" data-bar-max="' . formazioneClassiH(number_format($max, 2, '.', '')) . '" style="--fc-bar-width: ' . formazioneClassiH(number_format($width, 2, '.', '')) . '%"></span></span>'
        . '<span class="fc-bar-value" data-summary-stat="' . formazioneClassiH($key) . '">' . formazioneClassiH($display) . '</span>'
        . '</span></div>';
}

function fc_render_mf_bar(string $label, int $maschi, int $femmine, string $color): string
{
    $total = max(1, $maschi + $femmine);
    $maleWidth = max(0, min(100, ($maschi / $total) * 100));
    return '<div class="fc-summary-bar-row" data-summary-mf-label="' . formazioneClassiH($label) . '" style="' . formazioneClassiH(fc_class_style($color)) . '">'
        . '<span class="fc-summary-class-label"><span class="fc-color-dot"></span><span class="fc-summary-class-code">' . formazioneClassiH(fc_short_class_label($label)) . '</span></span>'
        . '<span class="fc-summary-bar-wrap">'
        . '<span class="fc-mf-track" style="--fc-mf-male-width: ' . formazioneClassiH(number_format($maleWidth, 2, '.', '')) . '%"><span class="fc-mf-male"></span><span class="fc-mf-female"></span></span>'
        . '<span class="fc-bar-value"><span data-summary-stat="maschi">' . $maschi . '</span>/<span data-summary-stat="femmine">' . $femmine . '</span></span>'
        . '</span></div>';
}

function fc_short_class_label(string $label): string
{
    $label = trim($label);
    if (preg_match('/^([1-5][A-Z]{2,3}[A-Z0-9]*)/iu', $label, $m)) {
        return strtoupper($m[1]);
    }
    return $label;
}

function fc_float_attr($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return (string)floatval($value);
}

function fc_class_colors(array $labels): array
{
    $palette = ['#2563eb', '#16a34a', '#9333ea', '#ea580c'];
    $colors = [];
    $index = 0;
    foreach ($labels as $label) {
        $label = trim((string)$label);
        if ($label === '' || isset($colors[$label])) {
            continue;
        }
        $colors[$label] = $palette[$index % count($palette)];
        $index++;
    }
    return $colors;
}

function fc_class_style(string $color): string
{
    $bgByColor = [
        '#2563eb' => '#dbeafe',
        '#16a34a' => '#dcfce7',
        '#9333ea' => '#f3e8ff',
        '#ea580c' => '#ffedd5',
    ];
    $headerByColor = [
        '#2563eb' => '#bfdbfe',
        '#16a34a' => '#bbf7d0',
        '#9333ea' => '#e9d5ff',
        '#ea580c' => '#fed7aa',
    ];
    $bg = $bgByColor[$color] ?? '#e0f2fe';
    $header = $headerByColor[$color] ?? '#bae6fd';
    return '--fc-class-color: ' . $color . '; --fc-class-bg: ' . $bg . '; --fc-class-header: ' . $header . ';';
}

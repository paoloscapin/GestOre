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
$addressOptions = formazioneClassiAddressOptionsForFormation($sourceYearId, $targetClassYear);
$indirizzo = trim((string)($_GET['indirizzo'] ?? ''));
if (($indirizzo === '' || !array_key_exists($indirizzo, $addressOptions)) && !empty($addressOptions)) {
    $indirizzo = (string)array_key_first($addressOptions);
}

$schoolYears = formazioneClassiSchoolYears();
$statesByAddress = [];
$classColorsByAddress = [];
foreach ($addressOptions as $addressKey => $addressLabel) {
    $addressKey = (string)$addressKey;
    $statesByAddress[$addressKey] = formazioneClassiState($sourceYearId, $targetYearId, $tipoFormazione, $addressKey);
    $classColorsByAddress[$addressKey] = fc_class_colors(array_map(function ($class) {
        return (string)($class['label'] ?? '');
    }, $statesByAddress[$addressKey]['classes'] ?? []));
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
        .fc-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 14px;
            align-items: start;
        }
        .fc-classes-window {
            min-width: 0;
            overflow-x: auto;
            padding-bottom: 8px;
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
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
        .fc-student:active { cursor: grabbing; }
        .fc-student-name { font-weight: 700; color: #17202f; }
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
            margin-top: 7px;
            padding: 6px 7px;
            border-radius: 4px;
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #7c2d12;
            font-size: 12px;
            line-height: 1.35;
            white-space: pre-wrap;
        }
        .fc-student-note strong {
            display: block;
            color: #9a3412;
            margin-bottom: 2px;
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
            position: sticky;
            top: 70px;
            max-height: calc(100vh - 88px);
            overflow-y: auto;
        }
        .fc-bocciati-heading {
            background: #fff3e0;
        }
        .fc-bocciati-panel .fc-student {
            border-left-color: #d97706;
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
        @media (max-width: 1100px) {
            .fc-layout { grid-template-columns: 1fr; }
            .fc-bocciati-panel { position: static; }
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
        <div class="panel-heading"><span class="glyphicon glyphicon-th-large"></span>&emsp;Formazione classi - <?php echo formazioneClassiH(strtolower($tipoFormazioneLabel)); ?></div>
        <div class="panel-body">
            <form method="get" class="fc-toolbar" id="fc_filter_form">
                <div class="fc-toolbar-row">
                    <div class="form-group">
                        <label>Classi da formare</label>
                        <select name="tipo_formazione" class="form-control input-sm fc-reload-select">
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
                            <?php foreach ($addressOptions as $addressKey => $addressLabel): ?>
                                <option value="<?php echo formazioneClassiH($addressKey); ?>" <?php echo fc_select($indirizzo, $addressKey); ?>>
                                    <?php echo formazioneClassiH($addressLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fc-status" id="fc_status"></div>
                </div>
            </form>

            <?php if ($indirizzo === ''): ?>
                <div class="alert alert-warning">Non ho trovato indirizzi nei tabelloni finali MasterCom dell'anno selezionato.</div>
            <?php else: ?>
                <div class="alert alert-info">
                    <?php if (in_array($targetClassYear, [2, 4, 5], true)): ?>
                        I promossi delle <?php echo formazioneClassiH(fc_anno_label($targetClassYear - 1)); ?> dell'indirizzo selezionato sono gia nella classe futura corrispondente.
                    <?php else: ?>
                        Per le <?php echo formazioneClassiH(strtolower($tipoFormazioneLabel)); ?> la bozza parte dalle sezioni target; l'import/distribuzione degli iscritti sara' il prossimo passo.
                    <?php endif; ?>
                    I bocciati delle <?php echo formazioneClassiH(fc_anno_label($targetClassYear)); ?> sono a destra: trascinali nella classe in cui vuoi inserirli.
                </div>
                <?php foreach ($statesByAddress as $addressKey => $state): ?>
                    <?php $sessionId = intval($state['session']['id'] ?? 0); ?>
                    <?php $classColors = $classColorsByAddress[$addressKey] ?? []; ?>
                    <div class="fc-address-section" data-address="<?php echo formazioneClassiH($addressKey); ?>" <?php echo $addressKey === $indirizzo ? '' : 'hidden'; ?>>
                        <div class="fc-layout" data-session-id="<?php echo intval($sessionId); ?>">
                            <div class="fc-classes-window">
                                <div class="fc-classes">
                                    <?php foreach ($state['classes'] as $class): ?>
                                        <?php $stats = $class['stats']; ?>
                                        <?php $classColor = $classColors[(string)$class['label']] ?? '#4c78a8'; ?>
                                        <section class="fc-class-panel" data-class-label="<?php echo formazioneClassiH($class['label']); ?>" style="<?php echo formazioneClassiH(fc_class_style($classColor)); ?>">
                                            <div class="fc-class-heading">
                                                <div class="fc-class-title"><span class="fc-color-dot"></span><?php echo formazioneClassiH($class['label']); ?></div>
                                                <span class="label label-primary"><?php echo intval($stats['count']); ?> studenti</span>
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

                            <aside class="fc-bocciati-panel">
                                <div class="fc-bocciati-heading">
                                    <div class="fc-class-title">Bocciati da piazzare</div>
                                    <span class="label label-warning"><?php echo count($state['unassigned']); ?></span>
                                </div>
                                <?php echo fc_render_stats($state['unassigned_stats']); ?>
                                <div class="fc-dropzone" data-target-label="">
                                    <?php if (empty($state['unassigned'])): ?>
                                        <div class="fc-empty">Nessun bocciato da piazzare</div>
                                    <?php endif; ?>
                                    <?php foreach ($state['unassigned'] as $student): ?>
                                        <?php echo fc_render_student($student); ?>
                                    <?php endforeach; ?>
                                </div>
                                <div class="fc-summary">
                                    <div class="fc-summary-title">Confronto classi</div>
                                    <?php echo fc_render_summary_by_indicator($state['classes'], $classColors); ?>
                                </div>
                            </aside>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<div id="fc_context_menu" class="fc-context-menu" role="menu" aria-hidden="true">
    <button type="button" id="fc_context_movimenti" role="menuitem">Apri pratica in movimenti studenti</button>
</div>
<script>
let fcDraggedId = 0;
let fcContextStudent = null;

document.querySelectorAll('.fc-reload-select').forEach(function (select) {
    select.addEventListener('change', function () {
        const form = document.getElementById('fc_filter_form');
        if (form) {
            form.submit();
        }
    });
});

document.getElementById('fc_indirizzo_select')?.addEventListener('change', function () {
    fcShowAddress(this.value || '');
});

function fcShowAddress(address) {
    let found = false;
    document.querySelectorAll('.fc-address-section').forEach(function (section) {
        const active = (section.dataset.address || '') === address;
        section.hidden = !active;
        if (active) {
            found = true;
        }
    });
    const status = document.getElementById('fc_status');
    if (status) {
        status.textContent = found ? '' : 'Indirizzo non disponibile nella pagina caricata.';
    }
    if (found && window.history && window.URLSearchParams) {
        const url = new URL(window.location.href);
        url.searchParams.set('indirizzo', address);
        window.history.replaceState({}, '', url.toString());
    }
}

document.querySelectorAll('.fc-student').forEach(function (card) {
    card.addEventListener('dragstart', function (event) {
        fcDraggedId = Number(card.dataset.rowId || 0);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(fcDraggedId));
    });
});

document.querySelectorAll('.fc-dropzone').forEach(function (zone) {
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
        fcMoveStudent(rowId, zone.dataset.targetLabel || '', layout, sessionId);
    });
});

document.querySelectorAll('.fc-student-bocciato').forEach(function (card) {
    card.addEventListener('contextmenu', function (event) {
        event.preventDefault();
        fcContextStudent = card;
        fcShowContextMenu(event.clientX, event.clientY);
    });
});

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

function fcMoveStudent(rowId, targetLabel, layout, sessionId) {
    const status = document.getElementById('fc_status');
    const card = layout.querySelector('.fc-student[data-row-id="' + rowId + '"]');
    const targetZone = layout.querySelector('.fc-dropzone[data-target-label="' + fcCssEscape(targetLabel) + '"]');
    if (!card || !targetZone) {
        if (status) status.textContent = 'Elemento non trovato nella pagina.';
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
    document.querySelectorAll('.fc-dropzone').forEach(function (zone) {
        const hasCards = zone.querySelector('.fc-student');
        if (!hasCards && !zone.querySelector('.fc-empty')) {
            const empty = document.createElement('div');
            empty.className = 'fc-empty';
            empty.textContent = zone.dataset.targetLabel ? 'Trascina qui gli studenti' : 'Nessun bocciato da piazzare';
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
        if (badge) {
            badge.textContent = panel.classList.contains('fc-bocciati-panel') ? String(stats.count) : (stats.count + ' studenti');
        }
        if (label !== '') {
            fcWriteSummary(label, stats);
        }
    });
    fcRefreshSummaryScale(allStats, scope);
}

function fcStatsFromZone(zone) {
    const stats = {count: 0, maschi: 0, femmine: 0, media_generale: null, voto_matematica: null, voto_italiano: null, voto_capacita_relazionale: null};
    const sums = {media_generale: 0, voto_matematica: 0, voto_italiano: 0, voto_capacita_relazionale: 0};
    const counts = {media_generale: 0, voto_matematica: 0, voto_italiano: 0, voto_capacita_relazionale: 0};
    if (!zone) return stats;
    zone.querySelectorAll('.fc-student').forEach(function (card) {
        stats.count++;
        const sesso = String(card.dataset.sesso || '').toUpperCase();
        if (sesso === 'M') stats.maschi++;
        if (sesso === 'F') stats.femmine++;
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
    const scope = document.querySelector('.fc-address-section:not([hidden])') || document;
    scope.querySelectorAll('[data-summary-label="' + fcCssEscape(label) + '"]').forEach(function (row) {
        Object.keys(stats).forEach(function (key) {
            const target = row.querySelector('[data-summary-stat="' + key + '"]');
            if (!target) return;
            target.textContent = fcIsIntegerStat(key) ? String(stats[key]) : fcFormatAvg(stats[key]);
        });
        row.querySelectorAll('[data-bar-stat]').forEach(function (bar) {
            const key = bar.dataset.barStat || '';
            bar.style.setProperty('--fc-bar-width', fcBarWidth(key, stats[key]));
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
    return key === 'count' || key === 'maschi' || key === 'femmine';
}

function fcBarWidth(key, value) {
    const n = Number(value);
    if (!Number.isFinite(n) || n <= 0) return '0%';
    if (key === 'count') return '0%';
    return Math.max(0, Math.min(100, (n / 10) * 100)) + '%';
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
        . '</div>';
}

function fc_render_student(array $student): string
{
    $classes = ['fc-student'];
    if (($student['gruppo_origine'] ?? '') === 'bocciato') {
        $classes[] = 'fc-student-bocciato';
    }
    $html = '<div class="' . implode(' ', $classes) . '" draggable="true"'
        . ' data-row-id="' . intval($student['id']) . '"'
        . ' data-id-movimento="' . intval($student['id_movimento'] ?? 0) . '"'
        . ' data-name="' . formazioneClassiH($student['nome'] ?? '') . '"'
        . ' data-sesso="' . formazioneClassiH($student['sesso'] ?? '') . '"'
        . ' data-media_generale="' . formazioneClassiH(fc_float_attr($student['media_generale'] ?? null)) . '"'
        . ' data-voto_matematica="' . formazioneClassiH(fc_float_attr($student['voto_matematica'] ?? null)) . '"'
        . ' data-voto_italiano="' . formazioneClassiH(fc_float_attr($student['voto_italiano'] ?? null)) . '"'
        . ' data-voto_capacita_relazionale="' . formazioneClassiH(fc_float_attr($student['voto_capacita_relazionale'] ?? null)) . '"'
        . '>';
    $html .= '<div class="fc-student-name">' . formazioneClassiH($student['nome']) . '</div>';
    $meta = [];
    if (trim((string)($student['classe_origine'] ?? '')) !== '') {
        $meta[] = 'ex ' . $student['classe_origine'];
    }
    if (trim((string)($student['gruppo_origine'] ?? '')) !== '') {
        $meta[] = $student['gruppo_origine'];
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
    $html .= '</div>';
    if (trim((string)($student['note_formazione'] ?? '')) !== '') {
        $noteMeta = [];
        if (trim((string)($student['note_formazione_origine'] ?? '')) !== '') {
            $noteMeta[] = str_replace('_', ' ', (string)$student['note_formazione_origine']);
        }
        if (trim((string)($student['note_formazione_stato'] ?? '')) !== '') {
            $noteMeta[] = str_replace('_', ' ', (string)$student['note_formazione_stato']);
        }
        $title = 'Nota segreteria';
        if ($noteMeta) {
            $title .= ' - ' . implode(' / ', $noteMeta);
        }
        $html .= '<div class="fc-student-note"><strong>' . formazioneClassiH($title) . '</strong>' . formazioneClassiH($student['note_formazione']) . '</div>';
    }
    $html .= '</div>';
    return $html;
}

function fc_render_summary_by_indicator(array $classes, array $classColors): string
{
    $maxStudents = 1;
    foreach ($classes as $class) {
        $stats = (array)($class['stats'] ?? []);
        $maxStudents = max($maxStudents, intval($stats['count'] ?? 0) + 1);
    }

    $indicators = [
        'count' => ['label' => 'Numero studenti', 'max' => $maxStudents, 'format' => 'int'],
        'media_generale' => ['label' => 'Media generale', 'max' => 10, 'format' => 'avg'],
        'voto_matematica' => ['label' => 'Matematica', 'max' => 10, 'format' => 'avg'],
        'voto_capacita_relazionale' => ['label' => 'Capacita relazionale', 'max' => 10, 'format' => 'avg'],
    ];

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
        . '<span class="fc-bar-track"><span class="fc-bar-fill" data-bar-stat="' . formazioneClassiH($key) . '" style="--fc-bar-width: ' . formazioneClassiH(number_format($width, 2, '.', '')) . '%"></span></span>'
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

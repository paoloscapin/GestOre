<?php
require_once __DIR__ . '/../common/connectMBApp.php';

function op_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function op_json(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function op_norm_time($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $m)) {
        return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    }
    return $value;
}

function op_monday_of(string $date): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        $dt = new DateTime('now', new DateTimeZone('Europe/Rome'));
    }
    $dt->modify('monday this week');
    return $dt->format('Y-m-d');
}

function op_add_days(string $date, int $days): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        $dt = new DateTime('now', new DateTimeZone('Europe/Rome'));
    }
    $dt->modify(($days >= 0 ? '+' : '') . $days . ' days');
    return $dt->format('Y-m-d');
}

function op_day_label(string $date): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        return $date;
    }
    $names = [1 => 'lunedi', 2 => 'martedi', 3 => 'mercoledi', 4 => 'giovedi', 5 => 'venerdi'];
    return $names[(int)$dt->format('N')] ?? $dt->format('D');
}

function op_current_school_date(): string
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
}

function op_like(string $text): string
{
    global $__conMBApp;
    $text = trim($text);
    return "'%" . mysqli_real_escape_string($__conMBApp, $text) . "%'";
}

function op_q(string $text): string
{
    global $__conMBApp;
    return "'" . mysqli_real_escape_string($__conMBApp, $text) . "'";
}

function op_curricular_where(): string
{
    return "
      AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
      AND (o.attivitaProgetto IS NULL OR TRIM(o.attivitaProgetto) = '')
      AND UPPER(COALESCE(o.siglaMateria, '')) NOT LIKE '%UDIENZA%'
      AND UPPER(COALESCE(o.siglaMateria, '')) NOT LIKE '%PRANZO%'
      AND UPPER(COALESCE(o.siglaMateria, '')) NOT LIKE '%AULA S%'
      AND UPPER(COALESCE(m.nomeMateria, '')) NOT LIKE '%UDIENZA%'
      AND UPPER(COALESCE(m.nomeMateria, '')) NOT LIKE '%PAUSA PRANZO%'
      AND UPPER(COALESCE(m.nomeMateria, '')) NOT LIKE '%AULA STUDIO%'
    ";
}

function op_search(string $term): array
{
    $term = trim($term);
    $termLength = function_exists('mb_strlen') ? mb_strlen($term, 'UTF-8') : strlen($term);
    if ($termLength < 1) {
        return [];
    }

    $like = op_like($term);
    $items = [];

    $teacherSql = "
        SELECT 'DOCENTE' AS scope, u.username AS id, CONCAT(u.cognome, ' ', u.nome) AS label, 'Docente' AS subtitle
        FROM utente u
        WHERE u.username IS NOT NULL AND u.username <> ''
          AND u.cognome IS NOT NULL AND u.cognome <> ''
          AND u.nome IS NOT NULL AND u.nome <> ''
          AND (u.tipo = 'Docente' OR UPPER(COALESCE(u.tipo, '')) = 'ADMIN')
          AND (CONCAT(u.cognome, ' ', u.nome) LIKE $like OR CONCAT(u.nome, ' ', u.cognome) LIKE $like OR u.username LIKE $like)
          AND EXISTS (
              SELECT 1
              FROM utilizza ut
              INNER JOIN oralezione o ON o.idCalendario = ut.idCalendario
              LEFT JOIN materia m ON m.siglaMateria = o.siglaMateria
              WHERE ut.username = u.username
              " . op_curricular_where() . "
              LIMIT 1
          )
        GROUP BY u.username, u.cognome, u.nome
        ORDER BY u.cognome, u.nome
        LIMIT 12
    ";
    foreach (mb_dbGetAll($teacherSql) ?: [] as $row) {
        $items[] = $row;
    }

    $classSql = "
        SELECT 'CLASSE' AS scope, oc.classe AS id, oc.classe AS label, 'Classe' AS subtitle
        FROM occupa oc
        INNER JOIN oralezione o ON o.idCalendario = oc.idCalendario
        LEFT JOIN materia m ON m.siglaMateria = o.siglaMateria
        WHERE oc.classe IS NOT NULL AND oc.classe <> ''
          AND oc.classe LIKE $like
          " . op_curricular_where() . "
        GROUP BY oc.classe
        ORDER BY oc.classe
        LIMIT 12
    ";
    foreach (mb_dbGetAll($classSql) ?: [] as $row) {
        $items[] = $row;
    }

    $roomSql = "
        SELECT 'AULA' AS scope, o.nroAula AS id,
               CONCAT(o.nroAula, IF(COALESCE(a.descrizione, '') <> '', CONCAT(' - ', a.descrizione), '')) AS label,
               'Aula' AS subtitle
        FROM oralezione o
        LEFT JOIN materia m ON m.siglaMateria = o.siglaMateria
        LEFT JOIN aula a ON a.nroAula = o.nroAula
        WHERE o.nroAula IS NOT NULL AND o.nroAula <> ''
          AND (o.nroAula LIKE $like OR COALESCE(a.descrizione, '') LIKE $like)
          " . op_curricular_where() . "
        GROUP BY o.nroAula, a.descrizione
        ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula
        LIMIT 12
    ";
    foreach (mb_dbGetAll($roomSql) ?: [] as $row) {
        $items[] = $row;
    }

    return array_slice($items, 0, 30);
}

function op_schedule_query(string $scope, string $target, string $from, string $to): array
{
    $targetSql = op_q($target);
    $fromSql = op_q($from);
    $toSql = op_q($to);

    $baseSelect = "
        SELECT
          o.idCalendario,
          o.dataGiorno,
          o.ora,
          o.siglaMateria,
          m.nomeMateria,
          GROUP_CONCAT(DISTINCT CONCAT(u.cognome, ' ', u.nome) ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti,
          GROUP_CONCAT(DISTINCT CONCAT(ut.username, '|', u.cognome, ' ', u.nome) ORDER BY u.cognome, u.nome SEPARATOR '||') AS docenti_refs,
          GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi,
          GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule
        FROM oralezione o
        LEFT JOIN materia m ON m.siglaMateria = o.siglaMateria
        LEFT JOIN utilizza ut ON ut.idCalendario = o.idCalendario
        LEFT JOIN utente u ON u.username = ut.username
        LEFT JOIN occupa oc ON oc.idCalendario = o.idCalendario
    ";

    if ($scope === 'DOCENTE') {
        $where = "INNER JOIN utilizza filtro ON filtro.idCalendario = o.idCalendario AND filtro.username = $targetSql
                  WHERE o.dataGiorno BETWEEN $fromSql AND $toSql";
    } elseif ($scope === 'CLASSE') {
        $where = "INNER JOIN occupa filtro ON filtro.idCalendario = o.idCalendario AND filtro.classe = $targetSql
                  WHERE o.dataGiorno BETWEEN $fromSql AND $toSql";
    } else {
        $where = "WHERE o.nroAula = $targetSql AND o.dataGiorno BETWEEN $fromSql AND $toSql";
    }

    $sql = "
        $baseSelect
        $where
        " . op_curricular_where() . "
        GROUP BY o.idCalendario, o.dataGiorno, o.ora, o.siglaMateria, m.nomeMateria
        ORDER BY o.dataGiorno, o.ora, o.siglaMateria
    ";

    return mb_dbGetAll($sql) ?: [];
}

function op_schedule(string $scope, string $target, string $date): array
{
    $scope = strtoupper(trim($scope));
    if (!in_array($scope, ['DOCENTE', 'CLASSE', 'AULA'], true)) {
        throw new Exception('Tipo ricerca non valido');
    }
    if ($target === '') {
        throw new Exception('Valore non selezionato');
    }

    $monday = op_monday_of($date);
    $days = [];
    for ($i = 0; $i < 5; $i++) {
        $day = op_add_days($monday, $i);
        $days[] = ['date' => $day, 'label' => op_day_label($day)];
    }

    $slots = ['07:50', '08:40', '09:30', '10:30', '11:20', '12:10', '13:00', '13:50', '14:40', '15:30', '16:20', '17:10'];
    $grid = [];
    $mergeIndex = [];
    foreach (op_schedule_query($scope, $target, $monday, op_add_days($monday, 4)) as $row) {
        $key = substr((string)$row['dataGiorno'], 0, 10) . '|' . op_norm_time($row['ora'] ?? '');
        if (!isset($grid[$key])) {
            $grid[$key] = [];
            $mergeIndex[$key] = [];
        }
        $subject = trim((string)($row['siglaMateria'] ?? ''));
        $name = trim((string)($row['nomeMateria'] ?? ''));
        $lesson = [
            'materia' => $subject !== '' ? $subject : 'LEZIONE',
            'nome_materia' => $name,
            'docenti' => trim((string)($row['docenti'] ?? '')),
            'docenti_refs' => trim((string)($row['docenti_refs'] ?? '')),
            'classi' => trim((string)($row['classi'] ?? '')),
            'aule' => trim((string)($row['aule'] ?? '')),
        ];

        $mergeKey = implode('|', [
            $lesson['materia'],
            $lesson['nome_materia'],
            $lesson['docenti_refs'] !== '' ? $lesson['docenti_refs'] : $lesson['docenti'],
            $lesson['classi'],
        ]);

        if (isset($mergeIndex[$key][$mergeKey])) {
            $idx = $mergeIndex[$key][$mergeKey];
            $aule = array_filter(array_map('trim', explode(',', $grid[$key][$idx]['aule'] . ',' . $lesson['aule'])));
            $aule = array_values(array_unique($aule));
            usort($aule, function ($a, $b) {
                $an = is_numeric($a) ? intval($a) : null;
                $bn = is_numeric($b) ? intval($b) : null;
                if ($an !== null && $bn !== null && $an !== $bn) {
                    return $an <=> $bn;
                }
                return strnatcasecmp($a, $b);
            });
            $grid[$key][$idx]['aule'] = implode(', ', $aule);
            continue;
        }

        $mergeIndex[$key][$mergeKey] = count($grid[$key]);
        $grid[$key][] = $lesson;
    }

    return [
        'days' => $days,
        'slots' => $slots,
        'grid' => $grid,
        'week_start' => $monday,
        'week_end' => op_add_days($monday, 4),
    ];
}

$action = trim((string)($_GET['action'] ?? ''));
if ($action === 'search') {
    try {
        op_json(['ok' => true, 'items' => op_search((string)($_GET['q'] ?? ''))]);
    } catch (Throwable $e) {
        op_json(['ok' => false, 'error' => $e->getMessage()]);
    }
}

if ($action === 'schedule') {
    try {
        op_json([
            'ok' => true,
            'schedule' => op_schedule((string)($_GET['scope'] ?? ''), (string)($_GET['target'] ?? ''), (string)($_GET['date'] ?? op_current_school_date())),
        ]);
    } catch (Throwable $e) {
        op_json(['ok' => false, 'error' => $e->getMessage()]);
    }
}

$today = op_current_school_date();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orario pubblico - ITT Buonarroti Trento</title>
    <link rel="icon" href="../ore-32.png">
    <style>
        * { box-sizing: border-box; }
        body {
            background: #ffffff;
            color: #000000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            margin: 0;
            overflow-x: hidden;
        }
        .school-header {
            background: #fff url("../bporario/assets/div.png") center top / auto 88px repeat-x;
            min-height: 92px;
            position: relative;
        }
        .school-brand {
            align-items: center;
            color: #2364ac;
            display: flex;
            gap: 8px;
            left: 18px;
            position: absolute;
            top: 8px;
            z-index: 1;
        }
        .school-logo {
            height: 54px;
            width: auto;
        }
        .school-header h1 {
            font-size: 25px;
            font-weight: 700;
            left: 0;
            margin: 0;
            position: absolute;
            right: 0;
            text-align: center;
            top: 56px;
            z-index: 1;
        }
        .search-area {
            padding: 0 0 0;
        }
        .search-area label {
            font-size: 12px;
            font-weight: 400;
            margin: 0;
        }
        #searchInput {
            border: 2px inset #efefef;
            border-radius: 0;
            font-size: 13px;
            height: 22px;
            padding: 1px 3px;
            width: 150px;
        }
        .result-columns {
            display: grid;
            gap: 24px;
            grid-template-columns: 1fr 1fr 1fr;
            margin-top: 6px;
            padding: 0 0 0;
        }
        .result-column {
            min-width: 0;
        }
        .result-heading {
            border-bottom: 1px solid #000;
            font-size: 13px;
            font-weight: 700;
            padding: 3px 4px 7px;
        }
        .result-list {
            max-height: 86px;
            overflow-y: auto;
        }
        .result-item {
            background: #ffffff;
            border: 0;
            border-bottom: 1px solid #000;
            cursor: pointer;
            display: block;
            font-size: 12px;
            padding: 8px 4px;
            text-align: left;
            width: 100%;
        }
        .result-item:hover,
        .result-item:focus {
            background: #eeeeee;
            outline: 0;
        }
        .result-error {
            color: #a00;
            font-size: 12px;
            padding: 8px 4px;
        }
        main {
            padding: 8px 0 0;
        }
        .loading-line {
            color: #333;
            padding: 20px 4px;
        }
        .schedule-name {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px;
            padding-left: 0;
        }
        .table-wrap {
            overflow-x: auto;
            width: 100%;
        }
        .schedule-table {
            border-collapse: collapse;
            table-layout: fixed;
            min-width: 1120px;
            width: 100%;
        }
        .schedule-table th,
        .schedule-table td {
            border: 1px solid #000000;
            padding: 0;
            text-align: center;
            vertical-align: top;
        }
        .schedule-table th {
            background: #ffffff;
            font-size: 13px;
            font-weight: 700;
            height: 52px;
            vertical-align: middle;
        }
        .schedule-table th.day-alt,
        .schedule-table td.day-alt {
            background: #eeeeee;
        }
        .schedule-table td {
            height: 96px;
            vertical-align: middle;
        }
        .schedule-table .time-col {
            background: #ffffff;
            font-size: 14px;
            font-weight: 700;
            padding-top: 0;
            vertical-align: top;
            width: 54px;
        }
        .lesson-public {
            align-items: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 82px;
            padding: 5px 4px;
            width: 100%;
        }
        .lesson-subject {
            font-size: 15px;
            font-weight: 800;
            line-height: 1.05;
            margin-bottom: 6px;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .lesson-chips {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            justify-content: center;
            margin: 0 auto;
            max-width: 100%;
        }
        .lesson-chip-row {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            justify-content: center;
            margin: 0 0 3px;
            min-width: 0;
            max-width: 100%;
        }
        .lesson-chip-row:last-child {
            margin-bottom: 0;
        }
        .lesson-chip {
            border: 0;
            border-radius: 4px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 400;
            justify-content: center;
            line-height: 1.2;
            margin: 0;
            max-width: 98px;
            min-width: 32px;
            overflow: hidden;
            padding: 5px 8px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .lesson-chip-jump {
            cursor: pointer;
            text-decoration: none;
        }
        .lesson-chip-jump:hover,
        .lesson-chip-jump:focus {
            filter: brightness(0.92);
            outline: 1px solid rgba(0, 0, 0, .35);
            outline-offset: 1px;
        }
        .chip-docente { background: #126df2; color: #ffffff; max-width: 160px; min-width: 86px; }
        .chip-classe { background: #0c8f5a; color: #ffffff; max-width: 70px; min-width: 48px; }
        .chip-aula { background: #ffc107; color: #000000; max-width: 82px; min-width: 52px; }
        .credits {
            bottom: 8px;
            color: #06f;
            font-size: 12px;
            position: fixed;
            right: 8px;
        }
        .mobile-cards {
            display: none;
        }
        @media (max-width: 760px) {
            .school-header {
                min-height: 102px;
            }
            .school-logo {
                height: 42px;
            }
            .school-header h1 {
                font-size: 21px;
                top: 60px;
            }
            .result-columns {
                gap: 8px;
                grid-template-columns: 1fr;
            }
            .result-list {
                max-height: 110px;
            }
            main {
                padding-top: 14px;
            }
            .table-wrap {
                display: none;
            }
            .mobile-cards {
                display: block;
                padding: 0 6px 34px;
            }
            .mobile-day {
                border: 1px solid #000;
                margin-bottom: 12px;
            }
            .mobile-day-title {
                background: #eeeeee;
                border-bottom: 1px solid #000;
                font-weight: 700;
                padding: 8px;
                text-align: center;
            }
            .mobile-slot {
                border-bottom: 1px solid #000;
                padding: 8px;
            }
            .mobile-slot:last-child {
                border-bottom: 0;
            }
            .mobile-time {
                font-weight: 700;
                margin-bottom: 4px;
            }
            .mobile-empty {
                color: #666;
            }
            .lesson-subject {
                margin-bottom: 8px;
            }
            .lesson-chip {
                max-width: 145px;
            }
        }
        @media (min-width: 1500px) {
            .schedule-table td {
                height: 104px;
            }
            .lesson-chip {
                font-size: 12px;
            }
            .chip-docente {
                max-width: 170px;
                min-width: 92px;
            }
            .chip-aula {
                min-width: 54px;
            }
        }
    </style>
</head>
<body>
    <header class="school-header">
        <div class="school-brand" aria-label="ITT Buonarroti Trento">
            <img class="school-logo" src="../img/logoB_google.png" alt="Buonarroti">
        </div>
        <h1>Orario Docenti - Classi - Aule</h1>
    </header>

    <section class="search-area">
        <label for="searchInput">Ricerca:</label><input type="search" id="searchInput" autocomplete="off">
        <div class="result-columns" id="resultColumns">
            <div class="result-column">
                <div class="result-heading">Docente</div>
                <div class="result-list" id="teacherResults"></div>
            </div>
            <div class="result-column">
                <div class="result-heading">Classe</div>
                <div class="result-list" id="classResults"></div>
            </div>
            <div class="result-column">
                <div class="result-heading">Aula</div>
                <div class="result-list" id="roomResults"></div>
            </div>
        </div>
    </section>

    <main>
        <div id="content"></div>
    </main>


    <script>
        const state = { selected: null, timer: null, date: <?php echo json_encode($today); ?>, searchSeq: 0 };
        const endpoint = window.location.href.split('?')[0];
        const searchInput = document.getElementById('searchInput');
        const content = document.getElementById('content');
        const lists = {
            DOCENTE: document.getElementById('teacherResults'),
            CLASSE: document.getElementById('classResults'),
            AULA: document.getElementById('roomResults')
        };

        function esc(text) {
            return String(text || '').replace(/[&<>"']/g, function (ch) {
                return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'})[ch];
            });
        }

        function clearResults() {
            Object.keys(lists).forEach(function (scope) {
                lists[scope].innerHTML = '';
            });
        }

        function showSearchError(text) {
            clearResults();
            lists.DOCENTE.innerHTML = '<div class="result-error">' + esc(text || 'Errore ricerca') + '</div>';
        }

        async function search(term) {
            const q = term.trim();
            const seq = ++state.searchSeq;
            if (!q) {
                clearResults();
                return;
            }
            try {
                const res = await fetch(endpoint + '?action=search&q=' + encodeURIComponent(q), {credentials: 'same-origin'});
                const data = await res.json();
                if (seq !== state.searchSeq) {
                    return;
                }
                if (!data.ok) {
                    showSearchError(data.error || 'Ricerca non disponibile');
                    return;
                }
                renderResults(data.items || []);
            } catch (e) {
                if (seq !== state.searchSeq) {
                    return;
                }
                showSearchError('Ricerca non disponibile');
            }
        }

        function renderResults(items) {
            clearResults();
            items.forEach(function (item) {
                const list = lists[item.scope];
                if (!list) return;
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'result-item';
                row.textContent = item.label || '';
                row.dataset.scope = item.scope;
                row.dataset.id = item.id || '';
                row.dataset.label = item.label || '';
                row.addEventListener('click', function () {
                    selectTarget(row.dataset.scope, row.dataset.id, row.dataset.label);
                });
                list.appendChild(row);
            });
        }

        function selectTarget(scope, id, label) {
            state.selected = {
                scope: scope,
                id: id,
                label: label
            };
            searchInput.value = label;
            loadSchedule();
        }

        async function loadSchedule() {
            if (!state.selected) {
                return;
            }
            content.innerHTML = '<div class="loading-line">Caricamento orario...</div>';
            const params = new URLSearchParams({
                action: 'schedule',
                scope: state.selected.scope,
                target: state.selected.id,
                date: state.date
            });
            try {
                const res = await fetch(endpoint + '?' + params.toString(), {credentials: 'same-origin'});
                const data = await res.json();
                if (!data.ok) {
                    content.innerHTML = '<div class="loading-line">' + esc(data.error || "Errore nel caricamento dell'orario.") + '</div>';
                    return;
                }
                renderSchedule(data.schedule);
            } catch (e) {
                content.innerHTML = '<div class="loading-line">Errore nel caricamento dell\'orario.</div>';
            }
        }

        function gridEvents(schedule, day, slot) {
            return schedule.grid[day.date + '|' + slot] || [];
        }

        function splitList(values) {
            return String(values || '').split(/\s*,\s*/).filter(Boolean);
        }

        function parseTeacherRefs(values) {
            return String(values || '').split('||').filter(Boolean).map(function (value) {
                const parts = value.split('|');
                return {
                    id: parts.shift() || '',
                    label: parts.join('|') || ''
                };
            }).filter(function (item) {
                return item.id && item.label;
            });
        }

        function chipButton(scope, id, label, cls) {
            const title = "Vai all'orario " + scope.toLowerCase() + " " + label;
            return '<button type="button" class="lesson-chip lesson-chip-jump ' + cls + '" ' +
                'data-scope="' + esc(scope) + '" data-id="' + esc(id) + '" data-label="' + esc(label) + '" ' +
                'title="' + esc(title) + '">' + esc(label) + '</button>';
        }

        function chips(values, cls, scope) {
            return String(values || '').split(/\s*,\s*/).filter(Boolean).map(function (value) {
                return chipButton(scope, value, value, cls);
            }).join('');
        }

        function renderLesson(ev) {
            const teacherRefs = parseTeacherRefs(ev.docenti_refs);
            const teacherChips = teacherRefs.length
                ? teacherRefs.map(function (item) { return chipButton('DOCENTE', item.id, item.label, 'chip-docente'); }).join('')
                : splitList(ev.docenti).map(function (label) { return chipButton('DOCENTE', label, label, 'chip-docente'); }).join('');
            const classChips = chips(ev.classi, 'chip-classe', 'CLASSE');
            const roomChips = chips(ev.aule, 'chip-aula', 'AULA');
            return '<div class="lesson-public">' +
                '<div class="lesson-subject">' + esc(ev.materia) + '</div>' +
                (teacherChips ? '<div class="lesson-chip-row">' + teacherChips + '</div>' : '') +
                (classChips ? '<div class="lesson-chip-row">' + classChips + '</div>' : '') +
                (roomChips ? '<div class="lesson-chip-row">' + roomChips + '</div>' : '') +
                '</div>';
        }

        function renderSchedule(schedule) {
            let html = '<h2 class="schedule-name">' + esc(state.selected.label) + '</h2>';
            html += '<div class="table-wrap"><table class="schedule-table"><thead><tr><th class="time-col"></th>';
            schedule.days.forEach(function (day, index) {
                html += '<th class="' + (index % 2 === 1 ? 'day-alt' : '') + '">' + esc(day.label) + '</th>';
            });
            html += '</tr></thead><tbody>';
            schedule.slots.forEach(function (slot) {
                html += '<tr><td class="time-col">' + esc(slot.replace(':', 'h')) + '</td>';
                schedule.days.forEach(function (day, index) {
                    const events = gridEvents(schedule, day, slot);
                    html += '<td class="' + (index % 2 === 1 ? 'day-alt' : '') + '">' + events.map(renderLesson).join('') + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            html += '<div class="mobile-cards">';
            schedule.days.forEach(function (day) {
                html += '<section class="mobile-day"><div class="mobile-day-title">' + esc(day.label) + '</div>';
                let hasRows = false;
                schedule.slots.forEach(function (slot) {
                    const events = gridEvents(schedule, day, slot);
                    if (!events.length) return;
                    hasRows = true;
                    html += '<div class="mobile-slot"><div class="mobile-time">' + esc(slot.replace(':', 'h')) + '</div>' + events.map(renderLesson).join('') + '</div>';
                });
                if (!hasRows) {
                    html += '<div class="mobile-slot mobile-empty">Nessuna lezione</div>';
                }
                html += '</section>';
            });
            html += '</div>';
            content.innerHTML = html;
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(state.timer);
            state.timer = setTimeout(function () {
                search(searchInput.value);
            }, 150);
        });

        document.addEventListener('click', function (event) {
            const chip = event.target.closest('.lesson-chip-jump');
            if (!chip) {
                return;
            }
            event.preventDefault();
            selectTarget(chip.dataset.scope || '', chip.dataset.id || '', chip.dataset.label || '');
        });
    </script>
</body>
</html>

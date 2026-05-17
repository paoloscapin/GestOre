<?php

require_once '../common/checkSession.php';
require_once '../api/googleCalendarDocentiLib.php';

ruoloRichiesto('admin');

$message = '';
$messageType = 'info';
$results = null;

function adminGoogleCalendarDocentiPost($name, $default = '')
{
    return isset($_POST[$name]) ? trim((string)$_POST[$name]) : $default;
}

function adminGoogleCalendarDocentiResolveRange($range, $from, $to)
{
    $range = strtolower(trim((string)$range));
    $today = date('Y-m-d');

    if ($range === 'oggi' || $range === 'today') {
        return [$today, $today];
    }

    if (preg_match('/^(\d+)\s*(g|gg|giorni|d|days)$/', $range, $m)) {
        $days = max(0, intval($m[1]));
        return [$today, date('Y-m-d', strtotime($today . ' +' . $days . ' days'))];
    }

    if (preg_match('/^(\d+)\s*(m|mesi|months)$/', $range, $m)) {
        $months = max(0, intval($m[1]));
        return [date('Y-m-d', strtotime($today . ' -' . $months . ' months')), date('Y-m-d', strtotime($today . ' +' . $months . ' months'))];
    }

    return [$from, $to];
}

function adminGoogleCalendarDocentiValidateRange($from, $to)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        throw new Exception('Date non valide: usare YYYY-MM-DD oppure un range rapido.');
    }
    if ($to < $from) {
        throw new Exception('Intervallo non valido: data fine precedente alla data inizio.');
    }
}

function adminGoogleCalendarDocentiCompareGestoreMbapp()
{
    $gestoreRows = dbGetAll("
        SELECT username, cognome, nome, email
        FROM docente
        WHERE attivo = true
          AND username IS NOT NULL
          AND username <> ''
        ORDER BY cognome, nome
    ") ?: [];

    $mbappRows = mb_dbGetAll("
        SELECT username, cognome, nome, email1
        FROM utente
        WHERE username IS NOT NULL
          AND username <> ''
          AND tipo = 'Docente'
        ORDER BY cognome, nome
    ") ?: [];

    $gestore = [];
    foreach ($gestoreRows as $row) {
        $username = strtolower(trim((string)($row['username'] ?? '')));
        if ($username === '') continue;
        $gestore[$username] = [
            'username' => trim((string)($row['username'] ?? '')),
            'cognome' => trim((string)($row['cognome'] ?? '')),
            'nome' => trim((string)($row['nome'] ?? '')),
            'email' => trim((string)($row['email1'] ?? ''))
        ];
    }

    $mbapp = [];
    foreach ($mbappRows as $row) {
        $username = strtolower(trim((string)($row['username'] ?? '')));
        if ($username === '') continue;
        $mbapp[$username] = [
            'username' => trim((string)($row['username'] ?? '')),
            'cognome' => trim((string)($row['cognome'] ?? '')),
            'nome' => trim((string)($row['nome'] ?? '')),
            'email' => trim((string)($row['email'] ?? ''))
        ];
    }

    $missingInMbapp = [];
    foreach ($gestore as $key => $row) {
        if (!isset($mbapp[$key])) $missingInMbapp[] = $row;
    }

    $missingInGestore = [];
    foreach ($mbapp as $key => $row) {
        if (!isset($gestore[$key])) $missingInGestore[] = $row;
    }

    return [
        'gestore_count' => count($gestore),
        'mbapp_count' => count($mbapp),
        'missing_in_mbapp' => $missingInMbapp,
        'missing_in_gestore' => $missingInGestore
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $action = adminGoogleCalendarDocentiPost('action');
        $username = adminGoogleCalendarDocentiPost('username');
        [$from, $to] = adminGoogleCalendarDocentiResolveRange(
            adminGoogleCalendarDocentiPost('range', '15gg'),
            adminGoogleCalendarDocentiPost('from'),
            adminGoogleCalendarDocentiPost('to')
        );
        adminGoogleCalendarDocentiValidateRange($from, $to);

        if ($action === 'list') {
            $teachers = googleCalendarDocentiGetTeachers($username);
            echo json_encode([
                'ok' => true,
                'from' => $from,
                'to' => $to,
                'teachers' => $teachers
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'compare_teachers') {
            echo json_encode([
                'ok' => true,
                'compare' => adminGoogleCalendarDocentiCompareGestoreMbapp()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'sync_one') {
            $results = googleCalendarDocentiSyncUsernames([$username], $from, $to);
            echo json_encode([
                'ok' => true,
                'from' => $from,
                'to' => $to,
                'result' => $results[0] ?? null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        throw new Exception('Azione non valida.');
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Google Calendar Docenti</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-calendar"></span>&emsp;Google Calendar Docenti
        </div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="form-horizontal" id="calendarDocentiSyncForm">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="username">Docente</label>
                    <div class="col-sm-4">
                        <input type="text" class="form-control" id="username" name="username" placeholder="username docente oppure vuoto per tutti" value="<?php echo htmlspecialchars(adminGoogleCalendarDocentiPost('username'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-sm-6 help-block">
                        Lascia vuoto per sincronizzare tutti i docenti attivi.
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="range">Range rapido</label>
                    <div class="col-sm-3">
                        <select class="form-control" id="range" name="range">
                            <?php
                            $selectedRange = adminGoogleCalendarDocentiPost('range', '15gg');
                            foreach (['15gg' => 'Da oggi a +15 giorni', '30gg' => 'Da oggi a +30 giorni', '4mesi' => 'Ultimi e prossimi 4 mesi', 'custom' => 'Date manuali'] as $value => $label) {
                                echo '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . ($selectedRange === $value ? ' selected' : '') . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <label class="col-sm-1 control-label" for="from">Dal</label>
                    <div class="col-sm-2">
                        <input type="date" class="form-control" id="from" name="from" value="<?php echo htmlspecialchars(adminGoogleCalendarDocentiPost('from', date('Y-m-d')), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <label class="col-sm-1 control-label" for="to">Al</label>
                    <div class="col-sm-2">
                        <input type="date" class="form-control" id="to" name="to" value="<?php echo htmlspecialchars(adminGoogleCalendarDocentiPost('to', date('Y-m-d', strtotime('+15 days'))), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="btn btn-primary" id="btnCalendarDocentiSync">
                            <span class="glyphicon glyphicon-refresh"></span>&ensp;Sincronizza
                        </button>
                        <button type="button" class="btn btn-default" id="btnCompareDocenti">
                            <span class="glyphicon glyphicon-transfer"></span>&ensp;Confronta docenti GestOre/MBApp
                        </button>
                    </div>
                </div>
            </form>

            <div id="compareDocentiBox" class="well" style="display:none;">
                <strong>Confronto docenti GestOre / MBApp</strong>
                <div id="compareDocentiSummary" class="text-muted" style="margin-top:8px;"></div>
                <div class="row" style="margin-top:12px;">
                    <div class="col-sm-6">
                        <h4>Attivi in GestOre non presenti in MBApp</h4>
                        <div class="table-responsive">
                            <table class="table table-condensed table-striped">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Docente</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody id="missingInMbappBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <h4>Presenti in MBApp non attivi in GestOre</h4>
                        <div class="table-responsive">
                            <table class="table table-condensed table-striped">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Docente</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody id="missingInGestoreBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="syncProgressBox" class="well" style="display:none;">
                <strong id="syncProgressTitle">Sincronizzazione in corso</strong>
                <div class="progress" style="margin-top:10px; margin-bottom:8px;">
                    <div id="syncProgressBar" class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:0%;">
                        0%
                    </div>
                </div>
                <div id="syncProgressText" class="text-muted">Preparazione elenco docenti...</div>
                <div id="syncProgressCurrent" style="margin-top:6px;"></div>
            </div>

            <hr>
            <div class="table-responsive" id="syncResultsBox" style="display:none;">
                <table class="table table-striped table-condensed">
                    <thead>
                        <tr>
                            <th>Docente</th>
                            <th>Email</th>
                            <th>Cosa</th>
                            <th>Attivita</th>
                            <th>Create</th>
                            <th>Aggiornate</th>
                            <th>Invariate</th>
                            <th>Eliminate</th>
                            <th>Errori</th>
                        </tr>
                    </thead>
                    <tbody id="syncResultsBody"></tbody>
                </table>
            </div>

            <script>
            (function () {
                function esc(value) {
                    return $('<div>').text(value == null ? '' : String(value)).html();
                }

                function formPayload(extra) {
                    var payload = $('#calendarDocentiSyncForm').serializeArray();
                    var data = { ajax: '1' };
                    payload.forEach(function (item) { data[item.name] = item.value; });
                    Object.keys(extra || {}).forEach(function (key) { data[key] = extra[key]; });
                    return data;
                }

                function setProgress(done, total, text, current) {
                    var pct = total > 0 ? Math.round((done / total) * 100) : 0;
                    $('#syncProgressBar').css('width', pct + '%').text(pct + '%');
                    $('#syncProgressText').text(text || '');
                    $('#syncProgressCurrent').text(current || '');
                }

                function appendResult(row, what) {
                    row = row || {};
                    var stats = row.stats || {};
                    var failed = row.ok === false || row.error;
                    $('#syncResultsBody').append(
                        '<tr class="' + (failed ? 'danger' : '') + '">' +
                        '<td>' + esc(row.username) + '</td>' +
                        '<td>' + esc(row.email) + '</td>' +
                        '<td>' + esc(what || 'Attivita calendario docente') + '</td>' +
                        '<td>' + esc(row.activities || 0) + '</td>' +
                        '<td>' + esc(stats.created || 0) + '</td>' +
                        '<td>' + esc(stats.updated || 0) + '</td>' +
                        '<td>' + esc(stats.unchanged || 0) + '</td>' +
                        '<td>' + esc(stats.deleted || 0) + '</td>' +
                        '<td>' + esc(row.error || stats.errors || 0) + '</td>' +
                        '</tr>'
                    );
                }

                function teacherRows(rows) {
                    if (!rows || rows.length === 0) {
                        return '<tr><td colspan="3" class="text-muted">Nessuna anomalia</td></tr>';
                    }
                    return rows.map(function (row) {
                        return '<tr>' +
                            '<td>' + esc(row.username) + '</td>' +
                            '<td>' + esc(((row.cognome || '') + ' ' + (row.nome || '')).trim()) + '</td>' +
                            '<td>' + esc(row.email) + '</td>' +
                            '</tr>';
                    }).join('');
                }

                async function post(data) {
                    return $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: data,
                        dataType: 'json'
                    });
                }

                $('#calendarDocentiSyncForm').on('submit', async function (event) {
                    event.preventDefault();

                    $('#btnCalendarDocentiSync').prop('disabled', true);
                    $('#syncProgressBox').show();
                    $('#syncResultsBox').show();
                    $('#syncResultsBody').empty();
                    $('#syncProgressBar').removeClass('progress-bar-success progress-bar-danger').addClass('progress-bar-info progress-bar-striped active');
                    setProgress(0, 1, 'Preparazione elenco docenti...', '');

                    try {
                        var list = await post(formPayload({ action: 'list' }));
                        var teachers = list.teachers || [];
                        var total = teachers.length;

                        if (total === 0) {
                            setProgress(1, 1, 'Nessun docente da sincronizzare.', '');
                            return;
                        }

                        setProgress(0, total, 'Sync in corso dal ' + list.from + ' al ' + list.to + '.', 'Docenti da elaborare: ' + total);

                        for (var i = 0; i < teachers.length; i++) {
                            var teacher = teachers[i];
                            var label = (teacher.nome || teacher.username || '').trim();
                            setProgress(i, total, 'Sync in corso dal ' + list.from + ' al ' + list.to + '.', 'Sto sincronizzando: ' + label + ' (' + teacher.username + ') - attivita calendario docente');

                            try {
                                var res = await post(formPayload({
                                    action: 'sync_one',
                                    username: teacher.username
                                }));
                                appendResult(res.result, 'Lezioni, sostituzioni, impegni, uscite e assenze');
                            } catch (xhr) {
                                var err = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Errore durante la sincronizzazione';
                                appendResult({ username: teacher.username, email: teacher.email, ok: false, error: err }, 'Lezioni, sostituzioni, impegni, uscite e assenze');
                            }

                            setProgress(i + 1, total, 'Sync in corso dal ' + list.from + ' al ' + list.to + '.', 'Completati ' + (i + 1) + ' di ' + total + ' docenti.');
                        }

                        $('#syncProgressBar').removeClass('progress-bar-info active').addClass('progress-bar-success');
                        setProgress(total, total, 'Sincronizzazione completata.', 'Docenti elaborati: ' + total);
                    } catch (xhr) {
                        var err = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Errore durante la preparazione della sincronizzazione';
                        $('#syncProgressBar').removeClass('progress-bar-info active').addClass('progress-bar-danger').css('width', '100%').text('Errore');
                        $('#syncProgressText').text(err);
                        $('#syncProgressCurrent').text('');
                    } finally {
                        $('#btnCalendarDocentiSync').prop('disabled', false);
                    }
                });

                $('#btnCompareDocenti').on('click', async function () {
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $('#compareDocentiBox').show();
                    $('#compareDocentiSummary').text('Confronto in corso...');
                    $('#missingInMbappBody').html('');
                    $('#missingInGestoreBody').html('');

                    try {
                        var res = await post(formPayload({ action: 'compare_teachers' }));
                        var data = res.compare || {};
                        var missingMbapp = data.missing_in_mbapp || [];
                        var missingGestore = data.missing_in_gestore || [];

                        $('#compareDocentiSummary').text(
                            'GestOre attivi: ' + (data.gestore_count || 0) +
                            ' · MBApp: ' + (data.mbapp_count || 0) +
                            ' · Mancanti in MBApp: ' + missingMbapp.length +
                            ' · Mancanti in GestOre: ' + missingGestore.length
                        );
                        $('#missingInMbappBody').html(teacherRows(missingMbapp));
                        $('#missingInGestoreBody').html(teacherRows(missingGestore));
                    } catch (xhr) {
                        var err = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Errore durante il confronto docenti';
                        $('#compareDocentiSummary').text(err);
                    } finally {
                        $btn.prop('disabled', false);
                    }
                });
            })();
            </script>
        </div>
    </div>
</div>
</body>
</html>

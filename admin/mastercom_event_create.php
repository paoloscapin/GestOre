<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/events_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mastercomEventCreateH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mastercomEventCreateToday(string $format = 'Y-m-d'): string
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format($format);
}

function mastercomEventCreatePostArray(string $key): array
{
    $value = $_POST[$key] ?? [];
    if (!is_array($value)) {
        $value = [$value];
    }
    return $value;
}

$missingTables = mastercomAdminMissingTables(['mastercom_studenti', 'mastercom_classi', 'mastercom_docenti']);
$classRows = empty($missingTables) ? mastercomAdminOperationalClassRows('mastercom_id_classe, nome') : [];
$studentRows = [];
if (empty($missingTables)) {
    $studentRows = dbGetAll("
        SELECT
            s.mastercom_id_studente,
            s.mastercom_id_classe_corrente,
            s.cognome,
            s.nome,
            c.nome AS classe
        FROM mastercom_studenti s
        LEFT JOIN mastercom_classi c ON c.mastercom_id_classe = s.mastercom_id_classe_corrente
        WHERE COALESCE(s.attivo_mastercom, 1) = 1
        ORDER BY c.nome ASC, s.cognome ASC, s.nome ASC
    ") ?: [];
}

$message = '';
$error = '';
$submitResult = null;
$editingEventId = intval($_POST['id_evento'] ?? $_GET['id_evento'] ?? 0);
$detailLoadMessage = '';
$eventDetail = [];
if ($editingEventId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $detailResult = mastercomEventFetchDetail($editingEventId);
    if (empty($detailResult['ok'])) {
        $detailLoadMessage = 'Dettaglio evento MasterCom non letto: ' . trim((string)($detailResult['error'] ?? 'FETCH_DETAIL_FAILED'));
    } else {
        $eventDetail = is_array($detailResult['event_detail'] ?? null) ? $detailResult['event_detail'] : [];
    }
}
$getTitle = trim((string)($_GET['nome'] ?? ''));
$getStart = trim((string)($_GET['data_inizio'] ?? ''));
$getEnd = trim((string)($_GET['data_fine'] ?? ''));

$form = [
    'nome' => trim((string)($_POST['nome'] ?? ($eventDetail['nome'] ?? $getTitle))),
    'descrizione' => trim((string)($_POST['descrizione'] ?? ($eventDetail['descrizione'] ?? ''))),
    'data_inizio' => trim((string)($_POST['data_inizio'] ?? ($eventDetail['data_inizio'] ?? ($getStart !== '' ? $getStart : mastercomEventCreateToday())))),
    'ora_inizio' => trim((string)($_POST['ora_inizio'] ?? ($eventDetail['ora_inizio'] ?? '08:00'))),
    'data_fine' => trim((string)($_POST['data_fine'] ?? ($eventDetail['data_fine'] ?? ($getEnd !== '' ? $getEnd : ($_POST['data_inizio'] ?? mastercomEventCreateToday()))))),
    'ora_fine' => trim((string)($_POST['ora_fine'] ?? ($eventDetail['ora_fine'] ?? '09:00'))),
];

$selectedClassIds = array_values(array_unique(array_map('intval', mastercomEventCreatePostArray('classi'))));
$selectedStudentIdsSource = $_SERVER['REQUEST_METHOD'] === 'POST' ? mastercomEventCreatePostArray('studenti') : ($eventDetail['studenti'] ?? []);
$selectedStudentIds = array_values(array_unique(array_map('intval', $selectedStudentIdsSource)));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($missingTables)) {
    try {
        $studentIds = [];
        foreach ($selectedStudentIds as $studentId) {
            if ($studentId > 0) {
                $studentIds[$studentId] = (string)$studentId;
            }
        }

        $classIds = array_values(array_filter($selectedClassIds, function ($id) {
            return intval($id) > 0;
        }));
        if (!empty($classIds)) {
            $classIdSql = implode(',', array_map('intval', $classIds));
            $classStudents = dbGetAll("
                SELECT mastercom_id_studente
                FROM mastercom_studenti
                WHERE mastercom_id_classe_corrente IN ($classIdSql)
                  AND COALESCE(attivo_mastercom, 1) = 1
            ") ?: [];
            foreach ($classStudents as $row) {
                $studentId = intval($row['mastercom_id_studente'] ?? 0);
                if ($studentId > 0) {
                    $studentIds[$studentId] = (string)$studentId;
                }
            }
        }

        if (empty($studentIds)) {
            throw new Exception('Seleziona almeno una classe o uno studente.');
        }

        $event = [
            'mode' => $editingEventId > 0 ? 'update' : 'create',
            'id_evento' => $editingEventId,
            'nome' => $form['nome'],
            'descrizione' => $form['descrizione'],
            'data_inizio' => $form['data_inizio'],
            'ora_inizio' => $form['ora_inizio'],
            'data_fine' => $form['data_fine'],
            'ora_fine' => $form['ora_fine'],
            'libera_docenti' => false,
            'tipo_permesso' => 'G',
            'tipo_cancellazione' => 'NO',
            'mat_settimana' => [1, 2, 3, 4, 5, 6],
            'studenti' => array_values($studentIds),
        ];

        $submitResult = mastercomEventSave($event);
        if (empty($submitResult['ok'])) {
            $error = ($editingEventId > 0 ? 'Aggiornamento' : 'Inserimento') . ' evento MasterCom fallito: ' . trim((string)($submitResult['error'] ?? 'SUBMIT_FAILED'));
        } else {
            $message = ($editingEventId > 0 ? 'Evento aggiornato su MasterCom' : 'Evento inviato a MasterCom') . '. Studenti coinvolti: ' . count($studentIds) . '.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Nuovo Evento</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <?php require_once '../common/_include_bootstrap-select.php'; ?>
    <style>
        .event-help {
            color: #6b7280;
            font-size: 12px;
            margin-top: 4px;
        }
        .result-box {
            white-space: pre-wrap;
            max-height: 260px;
            overflow: auto;
            font-family: Consolas, monospace;
            font-size: 12px;
        }
        .participants-summary {
            background: #f8fafc;
            border: 1px solid #d9e2ec;
            border-radius: 4px;
            padding: 12px 14px;
            margin-top: 12px;
        }
        .participants-summary h4 {
            margin-top: 0;
        }
        .participants-summary ul {
            list-style: none;
            margin-bottom: 0;
            max-height: 220px;
            overflow: auto;
            padding-left: 0;
        }
        .participants-summary .participant-summary-item {
            align-items: center;
            display: flex;
            gap: 8px;
            justify-content: flex-start;
            padding: 2px 0;
        }
        .participants-summary .participant-summary-label {
            min-width: 0;
        }
        .participants-summary .participant-remove {
            align-items: center;
            background: #d9534f !important;
            border: 1px solid #c9302c !important;
            border-radius: 50%;
            color: #ffffff !important;
            display: inline-flex;
            flex: 0 0 auto;
            float: none !important;
            font-size: 14px;
            font-weight: 800;
            height: 22px;
            justify-content: center;
            line-height: 1;
            margin-right: 6px;
            order: 0;
            padding: 0;
            text-decoration: none;
            width: 22px;
        }
        .participants-summary .participant-remove .glyphicon {
            font-size: 10px;
            line-height: 1;
            margin: 0;
        }
        .participants-summary .participant-summary-label {
            order: 1;
        }
        .participants-summary .participant-remove:hover,
        .participants-summary .participant-remove:focus {
            background: #c9302c !important;
            color: #ffffff !important;
            text-decoration: none;
        }
        .event-loading-overlay {
            align-items: center;
            background: rgba(255, 255, 255, 0.78);
            bottom: 0;
            display: none;
            justify-content: center;
            left: 0;
            position: fixed;
            right: 0;
            top: 0;
            z-index: 9999;
        }
        .event-loading-box {
            background: #ffffff;
            border: 1px solid #b8d9ef;
            border-radius: 6px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.18);
            color: #1f4e79;
            font-size: 18px;
            padding: 24px 34px;
            text-align: center;
            min-width: 320px;
        }
        .event-loading-box .glyphicon {
            margin-right: 8px;
        }
    </style>
</head>
<body>
<?php require_once headerAdminDidatticaPath('../common'); ?>
<div class="event-loading-overlay" id="eventLoadingOverlay">
    <div class="event-loading-box">
        <span class="glyphicon glyphicon-refresh"></span>
        Creazione evento MasterCom in corso...
        <div class="event-help">Attendere la risposta di MasterCom.</div>
    </div>
</div>
<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading"><span class="glyphicon glyphicon-plus"></span>&emsp;<?php echo $editingEventId > 0 ? 'Modifica evento MasterCom #' . intval($editingEventId) : 'Nuovo evento MasterCom'; ?></div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">
                    Mancano le tabelle MasterCom: <?php echo mastercomEventCreateH(implode(', ', $missingTables)); ?>.
                </div>
            <?php else: ?>
                <?php if ($message !== ''): ?>
                    <div class="alert alert-success"><?php echo mastercomEventCreateH($message); ?></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?php echo mastercomEventCreateH($error); ?></div>
                <?php endif; ?>
                <?php if ($detailLoadMessage !== ''): ?>
                    <div class="alert alert-warning"><?php echo mastercomEventCreateH($detailLoadMessage); ?></div>
                <?php endif; ?>

                <form method="post" action="mastercom_event_create.php" class="form-horizontal" onsubmit="return mastercomEventCreateSubmit();">
                    <?php if ($editingEventId > 0): ?>
                        <input type="hidden" name="id_evento" value="<?php echo intval($editingEventId); ?>">
                        <div class="alert alert-info">
                            Stai modificando un evento esistente. I dati e gli studenti gia associati sono stati letti da MasterCom.
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-3 control-label" for="nome">Titolo</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="nome" name="nome" required value="<?php echo mastercomEventCreateH($form['nome']); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-3 control-label" for="descrizione">Descrizione</label>
                                <div class="col-sm-9">
                                    <textarea class="form-control" id="descrizione" name="descrizione" rows="5"><?php echo mastercomEventCreateH($form['descrizione']); ?></textarea>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-3 control-label">Inizio</label>
                                <div class="col-sm-5">
                                    <input type="date" class="form-control" name="data_inizio" value="<?php echo mastercomEventCreateH($form['data_inizio']); ?>" required>
                                </div>
                                <div class="col-sm-4">
                                    <input type="time" class="form-control" name="ora_inizio" value="<?php echo mastercomEventCreateH($form['ora_inizio']); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-3 control-label">Fine</label>
                                <div class="col-sm-5">
                                    <input type="date" class="form-control" name="data_fine" value="<?php echo mastercomEventCreateH($form['data_fine']); ?>" required>
                                </div>
                                <div class="col-sm-4">
                                    <input type="time" class="form-control" name="ora_fine" value="<?php echo mastercomEventCreateH($form['ora_fine']); ?>" required>
                                </div>
                            </div>

                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="col-sm-3 control-label" for="classi">Classi intere</label>
                                <div class="col-sm-9">
                                    <select id="classi" name="classi[]" class="selectpicker" multiple data-live-search="true" data-actions-box="true" data-width="100%" title="Seleziona classi...">
                                        <?php foreach ($classRows as $classRow): ?>
                                            <?php $cid = intval($classRow['mastercom_id_classe'] ?? 0); ?>
                                            <option value="<?php echo $cid; ?>" <?php echo in_array($cid, $selectedClassIds, true) ? 'selected' : ''; ?>>
                                                <?php echo mastercomEventCreateH(($classRow['nome'] ?? '') . ' [' . $cid . ']'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="event-help">Gli studenti delle classi selezionate verranno aggiunti automaticamente.</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-3 control-label" for="studenti">Studenti singoli</label>
                                <div class="col-sm-9">
                                    <select id="studenti" name="studenti[]" class="selectpicker" multiple data-live-search="true" data-actions-box="true" data-width="100%" title="Seleziona studenti...">
                                        <?php foreach ($studentRows as $studentRow): ?>
                                            <?php $sid = intval($studentRow['mastercom_id_studente'] ?? 0); ?>
                                            <option value="<?php echo $sid; ?>" <?php echo in_array($sid, $selectedStudentIds, true) ? 'selected' : ''; ?>>
                                                <?php echo mastercomEventCreateH(($studentRow['classe'] ?? '') . ' - ' . ($studentRow['cognome'] ?? '') . ' ' . ($studentRow['nome'] ?? '') . ' [' . $sid . ']'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="event-help">Puoi aggiungere studenti singoli anche se hai gia selezionato classi intere.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="participants-summary" id="participantsSummary">
                        <h4>Partecipanti selezionati</h4>
                        <div class="text-muted" id="participantsEmpty">Nessuna classe o studente selezionato.</div>
                        <div id="participantsContent" style="display:none;">
                            <strong>Classi intere</strong>
                            <ul id="participantsClasses"></ul>
                            <br>
                            <strong>Studenti singoli</strong>
                            <ul id="participantsStudents"></ul>
                        </div>
                    </div>

                    <hr>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-send"></span> <?php echo $editingEventId > 0 ? 'Aggiorna evento su MasterCom' : 'Crea evento su MasterCom'; ?>
                        </button>
                        <a href="mastercom_events.php" class="btn btn-default">
                            <span class="glyphicon glyphicon-list"></span> Lista eventi
                        </a>
                        <a href="mastercom_calendar.php" class="btn btn-default">
                            <span class="glyphicon glyphicon-calendar"></span> Agenda classe
                        </a>
                    </div>
                </form>

                <?php if (is_array($submitResult)): ?>
                    <hr>
                    <h4>Risposta MasterCom</h4>
                    <?php $eventIdDebug = is_array($submitResult['event_id_debug'] ?? null) ? $submitResult['event_id_debug'] : []; ?>
                    <?php if (!empty($eventIdDebug)): ?>
                        <div class="panel panel-default">
                            <div class="panel-heading">Diagnostica ID evento</div>
                            <div class="panel-body">
                                <p>
                                    <strong>ID stimato:</strong>
                                    <?php echo !empty($eventIdDebug['created_event_id']) ? intval($eventIdDebug['created_event_id']) : 'non determinato'; ?>
                                    <span class="text-muted">
                                        (<?php echo mastercomEventCreateH($eventIdDebug['created_event_id_confidence'] ?? 'none'); ?>)
                                    </span>
                                </p>
                                <p>
                                    <strong>Hidden id_evento:</strong>
                                    <?php echo mastercomEventCreateH(implode(', ', array_slice($eventIdDebug['hidden_id_evento'] ?? [], 0, 25)) ?: 'nessuno'); ?>
                                </p>
                                <p>
                                    <strong>Eventi letti dalla lista:</strong>
                                    <?php echo intval($eventIdDebug['events_count'] ?? 0); ?>
                                </p>
                                <p>
                                    <strong>Match esatti titolo/data:</strong>
                                    <?php echo mastercomEventCreateH(implode(', ', $eventIdDebug['exact_event_ids'] ?? []) ?: 'nessuno'); ?>
                                </p>
                                <p>
                                    <strong>ID vicino al titolo:</strong>
                                    <?php echo mastercomEventCreateH(implode(', ', $eventIdDebug['title_candidate_ids'] ?? []) ?: 'nessuno'); ?>
                                </p>
                                <p>
                                    <strong>Occorrenze id_evento nella risposta:</strong>
                                    <?php echo mastercomEventCreateH(implode(', ', array_slice($eventIdDebug['id_evento_occurrences'] ?? [], 0, 25)) ?: 'nessuna'); ?>
                                </p>
                                <?php if (!empty($eventIdDebug['title_snippets'])): ?>
                                    <strong>Estratti vicino al titolo:</strong>
                                    <?php foreach (array_slice($eventIdDebug['title_snippets'], 0, 3) as $snippet): ?>
                                        <div class="well result-box"><?php echo mastercomEventCreateH($snippet); ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="well result-box"><?php echo mastercomEventCreateH($submitResult['message'] ?? $submitResult['error'] ?? ''); ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    function mastercomEventCreateSelectedItems(selector) {
        return $(selector + ' option:selected').map(function () {
            return {
                value: String($(this).val()),
                label: $(this).text().replace(/\s+/g, ' ').trim()
            };
        }).get();
    }

    function mastercomEventCreateRenderList(target, values, emptyText, selectSelector) {
        var $target = $(target);
        $target.empty();
        if (!values.length) {
            $('<li>').addClass('text-muted').text(emptyText).appendTo($target);
            return;
        }
        values.forEach(function (item) {
            var $row = $('<li>').addClass('participant-summary-item');
            $('<button>')
                .attr('type', 'button')
                .attr('title', 'Togli dalla selezione')
                .attr('aria-label', 'Togli dalla selezione')
                .attr('data-target-select', selectSelector)
                .attr('data-value', item.value)
                .addClass('participant-remove')
                .html('<span class="glyphicon glyphicon-remove" aria-hidden="true"></span>')
                .appendTo($row);
            $('<span>').addClass('participant-summary-label').text(item.label).appendTo($row);
            $row.appendTo($target);
        });
    }

    function mastercomEventCreateUpdateSummary() {
        var classes = mastercomEventCreateSelectedItems('#classi');
        var students = mastercomEventCreateSelectedItems('#studenti');
        var hasParticipants = classes.length > 0 || students.length > 0;

        $('#participantsEmpty').toggle(!hasParticipants);
        $('#participantsContent').toggle(hasParticipants);
        mastercomEventCreateRenderList('#participantsClasses', classes, 'Nessuna classe intera selezionata.', '#classi');
        mastercomEventCreateRenderList('#participantsStudents', students, 'Nessuno studente singolo selezionato.', '#studenti');
    }

    function mastercomEventCreateSubmit() {
        if (!confirm('Inviare evento a MasterCom?')) {
            return false;
        }
        $('#eventLoadingOverlay').css('display', 'flex');
        return true;
    }

    $(function () {
        $('.selectpicker').selectpicker();
        $('#classi, #studenti').on('changed.bs.select change', mastercomEventCreateUpdateSummary);
        $('#participantsSummary').on('click', '.participant-remove', function () {
            var selectSelector = $(this).attr('data-target-select');
            var value = String($(this).attr('data-value'));
            $(selectSelector + ' option').filter(function () {
                return String($(this).val()) === value;
            }).prop('selected', false);
            $(selectSelector).selectpicker('refresh');
            mastercomEventCreateUpdateSummary();
        });
        mastercomEventCreateUpdateSummary();
    });
</script>
</body>
</html>

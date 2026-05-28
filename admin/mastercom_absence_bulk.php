<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mastercomAbsenceBulkRomeNow(): DateTime
{
    return new DateTime('now', new DateTimeZone('Europe/Rome'));
}

function mastercomAbsenceBulkClean($value): string
{
    return trim((string)(mastercomAdminCleanText($value) ?? ''));
}

function mastercomAbsenceBulkStudent(int $studentId): ?array
{
    if ($studentId <= 0 || !mastercomAdminTableExists('mastercom_studenti')) {
        return null;
    }

    return dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . intval($studentId) . " LIMIT 1");
}

function mastercomAbsenceBulkClassName(int $classId): string
{
    if ($classId <= 0 || !mastercomAdminTableExists('mastercom_classi')) {
        return '';
    }

    return mastercomAbsenceBulkClean(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . intval($classId) . " LIMIT 1") ?? '');
}

function mastercomAbsenceBulkDateParts(string $date): array
{
    $dt = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        $dt = mastercomAbsenceBulkRomeNow();
    }

    return [
        'Date_Day' => (string)intval($dt->format('d')),
        'Date_Month' => $dt->format('m'),
        'Date_Year' => $dt->format('Y'),
    ];
}

function mastercomAbsenceBulkTimeParts(string $time): array
{
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
        return [
            'Time_Hour' => '08',
            'Time_Minute' => '00',
        ];
    }

    return [
        'Time_Hour' => str_pad((string)max(0, min(23, intval($matches[1]))), 2, '0', STR_PAD_LEFT),
        'Time_Minute' => str_pad((string)max(0, min(59, intval($matches[2]))), 2, '0', STR_PAD_LEFT),
    ];
}

function mastercomAbsenceBulkDateIt(string $date): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->format('d/m/Y') : $date;
}

function mastercomAbsenceBulkDates(string $startDate, string $endDate, bool $skipWeekend): array
{
    $start = DateTime::createFromFormat('Y-m-d', $startDate, new DateTimeZone('Europe/Rome'));
    $end = DateTime::createFromFormat('Y-m-d', $endDate, new DateTimeZone('Europe/Rome'));
    if (!$start instanceof DateTime || !$end instanceof DateTime || $end < $start) {
        return [];
    }

    $dates = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $dayOfWeek = intval($cursor->format('N'));
        if (!$skipWeekend || $dayOfWeek <= 5) {
            $dates[] = $cursor->format('Y-m-d');
        }
        $cursor->modify('+1 day');
    }
    return $dates;
}

function mastercomAbsenceBulkDayStartTs(string $date): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomAbsenceBulkDayEndTs(string $date): int
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' 23:59:59', new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomAbsenceBulkExistingAbsenceDates(int $studentId, string $startDate, string $endDate): array
{
    $startTs = mastercomAbsenceBulkDayStartTs($startDate);
    $endTs = mastercomAbsenceBulkDayEndTs($endDate);
    if ($studentId <= 0 || $startTs <= 0 || $endTs <= 0) {
        return [];
    }

    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComDocenteAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);
    if (!$authResult['ok']) {
        return [];
    }

    $absencesResult = mastercomLoadAbsencesData($authResult, $studentId, $startTs, $endTs, [
        'method' => 'POST',
        'timeout' => 120,
    ]);
    if (!$absencesResult['ok'] || !is_array($absencesResult['response'] ?? null)) {
        return [];
    }

    $existing = [];
    $records = is_array($absencesResult['response']['result'] ?? null) ? $absencesResult['response']['result'] : [];
    foreach ($records as $record) {
        $ts = intval($record['data'] ?? 0);
        if ($ts <= 0) {
            continue;
        }
        $dt = new DateTime('@' . $ts);
        $dt->setTimezone(new DateTimeZone('Europe/Rome'));
        $existing[$dt->format('Y-m-d')] = true;
    }
    return $existing;
}

function mastercomAbsenceBulkPayload(array $base, string $date, string $time): array
{
    return array_merge(
        $base,
        mastercomAbsenceBulkTimeParts($time),
        mastercomAbsenceBulkDateParts($date)
    );
}

function mastercomAbsenceBulkProgressPath(string $token): string
{
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $token) ?? '';
    if ($token === '') {
        $token = 'default';
    }
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'gestore_mc_absence_bulk_' . sha1($token) . '.json';
}

function mastercomAbsenceBulkProgressWrite(string $token, array $data): void
{
    if ($token === '') {
        return;
    }
    if (!empty($data['current_date'])) {
        $data['current_date_it'] = mastercomAbsenceBulkDateIt((string)$data['current_date']);
    }
    $data['updated_at'] = date('c');
    @file_put_contents(mastercomAbsenceBulkProgressPath($token), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function mastercomAbsenceBulkJsonOut(array $data): void
{
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mastercomAbsenceBulkReleaseSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

if (($_GET['action'] ?? '') === 'progress') {
    mastercomAbsenceBulkReleaseSession();
    $token = trim((string)($_GET['token'] ?? ''));
    $path = mastercomAbsenceBulkProgressPath($token);
    if ($token !== '' && is_file($path)) {
        header('Content-Type: application/json; charset=utf-8');
        readfile($path);
        exit;
    }
    mastercomAbsenceBulkJsonOut([
        'status' => 'waiting',
        'percent' => 0,
        'message' => 'Preparazione...',
    ]);
}

$absenceTypes = [
    1 => 'Assenza Giornaliera',
    6 => 'Assenza solo al Mattino',
    7 => 'Assenza solo al Pomeriggio',
];

$studentId = intval($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$classId = intval($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$studentRow = mastercomAbsenceBulkStudent($studentId);

if ($studentRow != null && $classId <= 0) {
    $classId = intval($studentRow['mastercom_id_classe_corrente'] ?? 0);
}

$studentSurname = $studentRow != null ? mastercomAbsenceBulkClean($studentRow['cognome'] ?? '') : mastercomAbsenceBulkClean($_GET['cognome'] ?? $_POST['cognome'] ?? '');
$studentName = $studentRow != null ? mastercomAbsenceBulkClean($studentRow['nome'] ?? '') : mastercomAbsenceBulkClean($_GET['nome'] ?? $_POST['nome'] ?? '');
$className = mastercomAbsenceBulkClassName($classId);
if ($className === '') {
    $className = mastercomAbsenceBulkClean($_GET['classe'] ?? $_POST['classe'] ?? '');
}

$today = mastercomAbsenceBulkRomeNow()->format('Y-m-d');
$startDate = trim((string)($_POST['start_date'] ?? $_GET['start_date'] ?? $today));
$endDate = trim((string)($_POST['end_date'] ?? $_GET['end_date'] ?? $today));
$selectedTime = trim((string)($_POST['absence_time'] ?? $_GET['absence_time'] ?? '08:00'));
$selectedType = intval($_POST['tipo_assenza'] ?? $_GET['tipo_assenza'] ?? 1);
if (!isset($absenceTypes[$selectedType])) {
    $selectedType = 1;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skipWeekend = isset($_POST['skip_weekend']);
    $skipExisting = isset($_POST['skip_existing']);
} else {
    $skipWeekend = intval($_GET['skip_weekend'] ?? 1) === 1;
    $skipExisting = intval($_GET['skip_existing'] ?? 1) === 1;
}
$tipoGiustificazione = intval($_POST['tipo_giustificazione'] ?? 1);
$esclusioneMonteore = intval($_POST['esclusione_calcolo_monteore'] ?? 0);
$motivazione = mastercomAbsenceBulkClean($_POST['motivazione'] ?? 'Motivi famigliari');
$submitResults = [];
$submitError = '';
$isAjax = $_SERVER['REQUEST_METHOD'] === 'POST' && intval($_POST['ajax'] ?? 0) === 1;
$progressToken = trim((string)($_POST['progress_token'] ?? ''));
$summary = [
    'inviate' => 0,
    'saltate' => 0,
    'errori' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['submit_bulk_absence'] ?? '') === '1') {
    @set_time_limit(900);
    mastercomAbsenceBulkReleaseSession();

    $dates = mastercomAbsenceBulkDates($startDate, $endDate, $skipWeekend);
    mastercomAbsenceBulkProgressWrite($progressToken, [
        'status' => 'start',
        'percent' => 0,
        'processed' => 0,
        'total' => count($dates),
        'message' => 'Preparazione inserimento assenze...',
    ]);
    if ($studentId <= 0 || $classId <= 0 || $studentSurname === '' || $studentName === '' || $className === '') {
        $submitError = 'Dati studente o classe incompleti.';
    } elseif (empty($dates)) {
        $submitError = 'Intervallo date non valido o senza giorni utili.';
    } else {
        $authResult = mastercomAuthenticateService([
            'profile' => 'MasterComAuth',
            'method' => 'POST',
            'timeout' => 60,
        ]);

        if (!$authResult['ok']) {
            $submitError = 'Autenticazione MasterCom amministratore fallita.';
        } else {
            mastercomAbsenceBulkProgressWrite($progressToken, [
                'status' => 'loading_existing',
                'percent' => 1,
                'processed' => 0,
                'total' => count($dates),
                'message' => 'Controllo eventuali assenze gia presenti...',
            ]);
            $existingDates = $skipExisting ? mastercomAbsenceBulkExistingAbsenceDates($studentId, $startDate, $endDate) : [];
            $basePayload = [
                'tipo_assenza' => $selectedType,
                'tipo_giustificazione' => $tipoGiustificazione,
                'esclusione_calcolo_monteore' => $esclusioneMonteore,
                'motivazione' => $motivazione,
                'x' => '17',
                'y' => '20',
                'form_stato' => 'amministratore',
                'stato_principale' => 'assenze_principale',
                'stato_secondario' => 'inserisci_assenze_studente_update',
                'stampa_rapportino' => 'SI',
                'id_classe' => $classId,
                'classe' => $className,
                'indirizzo' => '',
                'id_indirizzo' => '',
                'id_stud' => $studentId,
                'cognome_stud' => $studentSurname,
                'nome_stud' => $studentName,
                'operazione_sorgente' => '',
                'parametro_nome' => '',
                'parametro_cognome' => '',
                'parametro_indirizzo_abitazione' => '',
                'parametro_luogo_nascita' => '',
                'parametro_citta' => '',
                'parametro_provincia' => '',
                'parametro_sesso' => '',
                'parametro_tel_cell' => '',
                'parametro_email' => '',
                'parametro_matricola' => '',
                'parametro_cap' => '',
                'parametro_nome_genitore' => '',
                'parametro_cognome_genitore' => '',
                'inserimento_diretto' => '',
            ];

            $totalDates = max(1, count($dates));
            foreach ($dates as $index => $date) {
                $processedBefore = intval($index);
                mastercomAbsenceBulkProgressWrite($progressToken, [
                    'status' => 'running',
                    'percent' => max(1, intval(floor(($processedBefore / $totalDates) * 100))),
                    'processed' => $processedBefore,
                    'total' => count($dates),
                    'current_date' => $date,
                    'message' => 'Inserimento data ' . mastercomAbsenceBulkDateIt($date),
                ]);
                if (isset($existingDates[$date])) {
                    $summary['saltate']++;
                    $submitResults[] = [
                        'date' => $date,
                        'status' => 'saltata',
                        'message' => 'Assenza/permesso gia presente in MasterCom',
                    ];
                    mastercomAbsenceBulkProgressWrite($progressToken, [
                        'status' => 'running',
                        'percent' => intval(floor((($processedBefore + 1) / $totalDates) * 100)),
                        'processed' => $processedBefore + 1,
                        'total' => count($dates),
                        'current_date' => $date,
                        'message' => 'Saltata data ' . mastercomAbsenceBulkDateIt($date),
                    ]);
                    continue;
                }

                $submitResult = mastercomSubmitAdminAbsenceAction(
                    $authResult,
                    mastercomAbsenceBulkPayload($basePayload, $date, $selectedTime),
                    [
                        'method' => 'POST',
                        'timeout' => 120,
                        'send_in_body' => false,
                    ]
                );

                if ($submitResult['ok']) {
                    $summary['inviate']++;
                    $submitResults[] = [
                        'date' => $date,
                        'status' => 'ok',
                        'message' => 'Inviata a MasterCom',
                    ];
                } else {
                    $summary['errori']++;
                    $submitResults[] = [
                        'date' => $date,
                        'status' => 'errore',
                        'message' => $submitResult['error'] ?? 'SUBMIT_FAILED',
                    ];
                }
                mastercomAbsenceBulkProgressWrite($progressToken, [
                    'status' => 'running',
                    'percent' => intval(floor((($processedBefore + 1) / $totalDates) * 100)),
                    'processed' => $processedBefore + 1,
                    'total' => count($dates),
                    'current_date' => $date,
                    'message' => 'Completata data ' . mastercomAbsenceBulkDateIt($date),
                ]);
            }
        }
    }
    mastercomAbsenceBulkProgressWrite($progressToken, [
        'status' => $submitError === '' ? 'complete' : 'error',
        'percent' => 100,
        'processed' => count($dates),
        'total' => count($dates),
        'message' => $submitError === '' ? 'Operazione completata.' : $submitError,
    ]);
    if ($isAjax) {
        mastercomAbsenceBulkJsonOut([
            'ok' => $submitError === '',
            'error' => $submitError,
            'summary' => $summary,
            'results' => $submitResults,
        ]);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Assenze periodo</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .mc-bulk-summary {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        .mc-bulk-pill {
            min-width: 150px;
            padding: 10px 14px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #ddd;
            background: #fafafa;
        }
        .mc-bulk-pill strong {
            display: block;
            font-size: 24px;
        }
        .mc-bulk-results td,
        .mc-bulk-results th {
            text-align: center;
            vertical-align: middle !important;
        }
        #mcBulkWaitOverlay {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,.78);
            align-items: center;
            justify-content: center;
        }
        #mcBulkWaitOverlay .mc-bulk-wait-box {
            width: 460px;
            max-width: calc(100vw - 40px);
            background: #fff;
            border: 1px solid #d7e3f0;
            border-radius: 8px;
            box-shadow: 0 12px 34px rgba(15,23,42,.18);
            padding: 22px 26px;
            text-align: center;
        }
        #mcBulkWaitPercent {
            font-size: 34px;
            font-weight: 800;
            color: #0f6f7b;
            line-height: 1.1;
        }
        #mcBulkWaitDate {
            font-weight: 700;
            margin-top: 8px;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div id="mcBulkWaitOverlay">
    <div class="mc-bulk-wait-box">
        <div id="mcBulkWaitPercent">0%</div>
        <div id="mcBulkWaitText">Preparazione inserimento assenze...</div>
        <div id="mcBulkWaitDate"></div>
        <div class="progress progress-striped active" style="margin-top:16px;">
            <div id="mcBulkWaitBar" class="progress-bar progress-bar-info" style="width:0%;"></div>
        </div>
    </div>
</div>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-calendar"></span>&emsp;Assenze MasterCom per periodo</div>
        <div class="panel-body">
            <p>
                <a class="btn btn-default" href="mastercom_students.php">
                    <span class="glyphicon glyphicon-chevron-left"></span> Studenti MasterCom
                </a>
                <?php if ($studentId > 0): ?>
                    <a class="btn btn-info" href="mastercom_student_absences.php?student_id=<?php echo intval($studentId); ?>">
                        Storico assenze studente
                    </a>
                <?php endif; ?>
            </p>

            <?php if ($submitError !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($submitError); ?></div>
            <?php endif; ?>

            <?php if ($studentId <= 0 || $classId <= 0): ?>
                <div class="alert alert-warning">Seleziona prima uno studente dalla pagina MasterCom Studenti.</div>
            <?php else: ?>
                <div class="alert alert-info">
                    Studente: <strong><?php echo htmlspecialchars(trim($studentSurname . ' ' . $studentName)); ?></strong>
                    | Classe: <strong><?php echo htmlspecialchars($className); ?></strong>
                </div>

                <form method="post" action="mastercom_absence_bulk.php" class="form-horizontal" id="mcBulkAbsenceForm">
                    <input type="hidden" name="submit_bulk_absence" value="1">
                    <input type="hidden" name="student_id" value="<?php echo intval($studentId); ?>">
                    <input type="hidden" name="class_id" value="<?php echo intval($classId); ?>">
                    <input type="hidden" name="cognome" value="<?php echo htmlspecialchars($studentSurname); ?>">
                    <input type="hidden" name="nome" value="<?php echo htmlspecialchars($studentName); ?>">
                    <input type="hidden" name="classe" value="<?php echo htmlspecialchars($className); ?>">

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="tipo_assenza">Tipo</label>
                        <div class="col-sm-4">
                            <select class="form-control" name="tipo_assenza" id="tipo_assenza">
                                <?php foreach ($absenceTypes as $typeId => $typeLabel): ?>
                                    <option value="<?php echo intval($typeId); ?>" <?php echo $selectedType === intval($typeId) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($typeLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="col-sm-1 control-label" for="absence_time">Ora</label>
                        <div class="col-sm-2">
                            <input type="time" class="form-control" name="absence_time" id="absence_time" value="<?php echo htmlspecialchars($selectedTime); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="start_date">Dal</label>
                        <div class="col-sm-3">
                            <input type="date" class="form-control" name="start_date" id="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <label class="col-sm-1 control-label" for="end_date">Al</label>
                        <div class="col-sm-3">
                            <input type="date" class="form-control" name="end_date" id="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="tipo_giustificazione">Giustificato</label>
                        <div class="col-sm-2">
                            <select class="form-control" name="tipo_giustificazione" id="tipo_giustificazione">
                                <option value="1" <?php echo $tipoGiustificazione === 1 ? 'selected' : ''; ?>>SI</option>
                                <option value="0" <?php echo $tipoGiustificazione === 0 ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                        <label class="col-sm-3 control-label" for="esclusione_calcolo_monteore">Escludi monteore</label>
                        <div class="col-sm-2">
                            <select class="form-control" name="esclusione_calcolo_monteore" id="esclusione_calcolo_monteore">
                                <option value="0" <?php echo $esclusioneMonteore === 0 ? 'selected' : ''; ?>>NO</option>
                                <option value="1" <?php echo $esclusioneMonteore === 1 ? 'selected' : ''; ?>>SI</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="motivazione">Motivazione</label>
                        <div class="col-sm-5">
                            <input type="text" class="form-control" name="motivazione" id="motivazione" value="<?php echo htmlspecialchars($motivazione); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-8">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="skip_weekend" value="1" <?php echo $skipWeekend ? 'checked' : ''; ?>>
                                Salta sabato e domenica
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="skip_existing" value="1" <?php echo $skipExisting ? 'checked' : ''; ?>>
                                Salta giorni con eventi assenza gia presenti
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button type="submit" class="btn btn-primary">
                                <span class="glyphicon glyphicon-ok"></span> Invia assenze del periodo
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <div id="mcBulkAjaxResults"></div>

            <?php if (!empty($submitResults)): ?>
                <hr>
                <div class="mc-bulk-summary">
                    <div class="mc-bulk-pill" style="background:#e8f5e9;border-color:#a5d6a7;">
                        <strong><?php echo intval($summary['inviate']); ?></strong>
                        inviate
                    </div>
                    <div class="mc-bulk-pill" style="background:#fffde7;border-color:#fff59d;">
                        <strong><?php echo intval($summary['saltate']); ?></strong>
                        saltate
                    </div>
                    <div class="mc-bulk-pill" style="background:#ffebee;border-color:#ef9a9a;">
                        <strong><?php echo intval($summary['errori']); ?></strong>
                        errori
                    </div>
                </div>

                <table class="table table-bordered table-striped mc-bulk-results">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Esito</th>
                            <th>Dettaglio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submitResults as $result): ?>
                            <?php
                            $status = (string)$result['status'];
                            $labelClass = $status === 'ok' ? 'success' : ($status === 'saltata' ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars(mastercomAbsenceBulkDateIt((string)$result['date'])); ?></td>
                                <td><span class="label label-<?php echo $labelClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                <td><?php echo htmlspecialchars((string)$result['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function mcBulkSetProgress(data) {
    var percent = Math.max(0, Math.min(100, parseInt(data && data.percent ? data.percent : 0, 10)));
    $('#mcBulkWaitPercent').text(percent + '%');
    $('#mcBulkWaitBar').css('width', percent + '%');
    $('#mcBulkWaitText').text((data && data.message) ? data.message : 'Operazione in corso...');
    $('#mcBulkWaitDate').text((data && data.current_date_it) ? ('Data: ' + data.current_date_it) : '');
}

function mcBulkShowProgress() {
    mcBulkSetProgress({ percent: 0, message: 'Preparazione inserimento assenze...' });
    $('#mcBulkWaitOverlay').css('display', 'flex');
}

function mcBulkHideProgressSoon() {
    setTimeout(function () {
        $('#mcBulkWaitOverlay').fadeOut(180);
    }, 900);
}

function mcBulkRenderResults(payload) {
    var summary = payload.summary || { inviate: 0, saltate: 0, errori: 0 };
    var rows = payload.results || [];
    var html = '';
    if (payload.error) {
        html += '<div class="alert alert-danger">' + $('<div>').text(payload.error).html() + '</div>';
    }
    html += '<hr><div class="mc-bulk-summary">';
    html += '<div class="mc-bulk-pill" style="background:#e8f5e9;border-color:#a5d6a7;"><strong>' + parseInt(summary.inviate || 0, 10) + '</strong>inviate</div>';
    html += '<div class="mc-bulk-pill" style="background:#fffde7;border-color:#fff59d;"><strong>' + parseInt(summary.saltate || 0, 10) + '</strong>saltate</div>';
    html += '<div class="mc-bulk-pill" style="background:#ffebee;border-color:#ef9a9a;"><strong>' + parseInt(summary.errori || 0, 10) + '</strong>errori</div>';
    html += '</div>';
    html += '<table class="table table-bordered table-striped mc-bulk-results"><thead><tr><th>Data</th><th>Esito</th><th>Dettaglio</th></tr></thead><tbody>';
    rows.forEach(function (row) {
        var status = String(row.status || '');
        var label = status === 'ok' ? 'success' : (status === 'saltata' ? 'warning' : 'danger');
        var date = row.date_it || '';
        if (!date && row.date) {
            var parts = String(row.date).split('-');
            date = parts.length === 3 ? (parts[2] + '/' + parts[1] + '/' + parts[0]) : row.date;
        }
        html += '<tr>';
        html += '<td>' + $('<div>').text(date).html() + '</td>';
        html += '<td><span class="label label-' + label + '">' + $('<div>').text(status).html() + '</span></td>';
        html += '<td>' + $('<div>').text(row.message || '').html() + '</td>';
        html += '</tr>';
    });
    html += '</tbody></table>';
    $('#mcBulkAjaxResults').html(html);
}

$(document).on('submit', '#mcBulkAbsenceForm', function (event) {
    event.preventDefault();
    if (!confirm('Confermi invio assenze a MasterCom per il periodo selezionato?')) {
        return false;
    }

    var form = this;
    var token = String(Date.now()) + '_' + Math.random().toString(16).slice(2);
    var data = new FormData(form);
    data.append('ajax', '1');
    data.append('progress_token', token);

    mcBulkShowProgress();
    $('#mcBulkAjaxResults').empty();

    var poll = setInterval(function () {
        fetch('mastercom_absence_bulk.php?action=progress&token=' + encodeURIComponent(token), { cache: 'no-store' })
            .then(function (response) { return response.json(); })
            .then(function (progress) { mcBulkSetProgress(progress); })
            .catch(function () {});
    }, 600);

    fetch(form.action, {
        method: 'POST',
        body: data,
        cache: 'no-store'
    }).then(function (response) {
        return response.json();
    }).then(function (payload) {
        clearInterval(poll);
        mcBulkSetProgress({ percent: 100, message: payload.ok ? 'Operazione completata.' : (payload.error || 'Operazione completata con errori.') });
        mcBulkRenderResults(payload);
        mcBulkHideProgressSoon();
    }).catch(function () {
        clearInterval(poll);
        mcBulkSetProgress({ percent: 100, message: 'Errore di connessione durante l invio.' });
        mcBulkHideProgressSoon();
        alert('Errore di connessione durante l invio.');
    });

    return false;
});
</script>
</body>
</html>

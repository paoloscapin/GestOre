<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

function mastercomAbsenceEditRomeNow(): DateTime
{
    return new DateTime('now', new DateTimeZone('Europe/Rome'));
}

function mastercomAbsenceEditClean($value): string
{
    return trim((string)(mastercomAdminCleanText($value) ?? ''));
}

function mastercomAbsenceEditStudent(int $studentId): ?array
{
    if ($studentId <= 0 || !mastercomAdminTableExists('mastercom_studenti')) {
        return null;
    }

    return dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . intval($studentId) . " LIMIT 1");
}

function mastercomAbsenceEditClassName(int $classId): string
{
    if ($classId <= 0 || !mastercomAdminTableExists('mastercom_classi')) {
        return '';
    }

    return mastercomAbsenceEditClean(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . intval($classId) . " LIMIT 1") ?? '');
}

function mastercomAbsenceEditDateFromTimestamp(int $timestamp): string
{
    if ($timestamp <= 0) {
        return mastercomAbsenceEditRomeNow()->format('Y-m-d');
    }

    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format('Y-m-d');
}

function mastercomAbsenceEditTimeFromTimestamp(int $timestamp): string
{
    if ($timestamp <= 0) {
        return mastercomAbsenceEditRomeNow()->format('H:i');
    }

    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format('H:i');
}

function mastercomAbsenceEditDateParts(string $date): array
{
    $dt = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        $dt = mastercomAbsenceEditRomeNow();
    }

    return [
        'Date_Day' => (string)intval($dt->format('d')),
        'Date_Month' => $dt->format('m'),
        'Date_Year' => $dt->format('Y'),
    ];
}

function mastercomAbsenceEditTimeParts(string $time): array
{
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
        $now = mastercomAbsenceEditRomeNow();
        return [
            'Time_Hour' => $now->format('H'),
            'Time_Minute' => $now->format('i'),
        ];
    }

    return [
        'Time_Hour' => str_pad((string)max(0, min(23, intval($matches[1]))), 2, '0', STR_PAD_LEFT),
        'Time_Minute' => str_pad((string)max(0, min(59, intval($matches[2]))), 2, '0', STR_PAD_LEFT),
    ];
}

$studentId = intval($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$classId = intval($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$absenceId = intval($_GET['id_assenza'] ?? $_POST['id_assenza'] ?? 0);
$absenceDate = intval($_GET['data_assenza'] ?? $_POST['data_assenza'] ?? 0);
$studentRow = mastercomAbsenceEditStudent($studentId);

if ($studentRow != null) {
    if ($classId <= 0) {
        $classId = intval($studentRow['mastercom_id_classe_corrente'] ?? 0);
    }
    $studentSurname = mastercomAbsenceEditClean($studentRow['cognome'] ?? '');
    $studentName = mastercomAbsenceEditClean($studentRow['nome'] ?? '');
} else {
    $studentSurname = mastercomAbsenceEditClean($_GET['cognome'] ?? $_POST['cognome'] ?? '');
    $studentName = mastercomAbsenceEditClean($_GET['nome'] ?? $_POST['nome'] ?? '');
}

$className = mastercomAbsenceEditClassName($classId);
if ($className === '') {
    $className = mastercomAbsenceEditClean($_GET['classe'] ?? $_POST['classe'] ?? '');
}

$absenceTypes = [
    1 => 'Assenza Giornaliera',
    2 => 'Entrata in Ritardo al mattino',
    8 => 'Entrata in Ritardo al pomeriggio',
    3 => 'Uscita in Anticipo al mattino',
    9 => 'Uscita in Anticipo al pomeriggio',
    6 => 'Assenza solo al Mattino',
    7 => 'Assenza solo al Pomeriggio',
    12 => 'Entrata in Ritardo Minore al Mattino',
    18 => 'Entrata in Ritardo Minore al Pomeriggio',
];

$selectedType = intval($_POST['tipo_assenza'] ?? $_GET['tipo_assenza'] ?? 1);
if (!isset($absenceTypes[$selectedType])) {
    $selectedType = 1;
}

$defaultDate = mastercomAbsenceEditDateFromTimestamp($absenceDate);
$defaultTime = mastercomAbsenceEditClean($_GET['absence_time'] ?? '');
if (!preg_match('/^\d{1,2}:\d{2}$/', $defaultTime)) {
    $defaultTime = mastercomAbsenceEditTimeFromTimestamp($absenceDate);
}

$selectedDate = trim((string)($_POST['absence_date'] ?? $_GET['absence_date'] ?? $defaultDate));
$selectedTime = trim((string)($_POST['absence_time'] ?? $_GET['absence_time'] ?? $defaultTime));
$submitOk = false;
$submitError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['submit_edit_absence'] ?? '') === '1') {
    if ($studentId <= 0 || $classId <= 0 || $absenceId <= 0 || $absenceDate <= 0 || $studentSurname === '' || $studentName === '' || $className === '') {
        $submitError = 'Dati modifica assenza incompleti';
    } else {
        $authResult = mastercomAuthenticateService([
            'profile' => 'MasterComAuth',
            'method' => 'POST',
            'timeout' => 60,
        ]);

        if (!$authResult['ok']) {
            $submitError = 'Autenticazione MasterCom amministratore fallita';
        } else {
            $payload = array_merge(
                [
                    'tipo_assenza' => $selectedType,
                ],
                mastercomAbsenceEditTimeParts($selectedTime),
                mastercomAbsenceEditDateParts($selectedDate),
                [
                    'esclusione_calcolo_monteore' => intval($_POST['esclusione_calcolo_monteore'] ?? 0),
                    'x' => '18',
                    'y' => '13',
                    'form_stato' => 'amministratore',
                    'stato_principale' => 'assenze_principale',
                    'stato_secondario' => 'modifica_assenze_studente_update',
                    'id_classe' => $classId,
                    'classe' => $className,
                    'indirizzo' => '',
                    'id_indirizzo' => '',
                    'id_stud' => $studentId,
                    'cognome_stud' => $studentSurname,
                    'nome_stud' => $studentName,
                    'id_assenza' => $absenceId,
                    'data_assenza' => $absenceDate,
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
                ]
            );

            $submitResult = mastercomSubmitAdminAbsenceAction($authResult, $payload, [
                'method' => 'POST',
                'timeout' => 120,
                'send_in_body' => true,
            ]);

            if ($submitResult['ok']) {
                $submitOk = true;
            } else {
                $submitError = 'Modifica assenza MasterCom fallita: ' . ($submitResult['error'] ?? 'SUBMIT_FAILED');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Modifica Assenza</title>
    <meta charset="UTF-8">
    <?php if ($submitOk): ?>
        <meta http-equiv="refresh" content="3;url=mastercom_presence.php?class_id=<?php echo intval($classId); ?>">
    <?php endif; ?>
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .mc-success-center {
            max-width: 520px;
            margin: 14vh auto 0;
            padding: 36px 42px;
            text-align: center;
            background: #f4fff8;
            border: 1px solid #b8e6c7;
            border-radius: 10px;
            box-shadow: 0 8px 28px rgba(0,0,0,0.12);
        }
        .mc-success-center .glyphicon {
            font-size: 46px;
            color: #2e8b57;
            margin-bottom: 16px;
        }
        .mc-success-center h2 {
            margin-top: 0;
            color: #1f6f43;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <?php if ($submitOk): ?>
        <div class="mc-success-center">
            <div class="glyphicon glyphicon-ok-circle"></div>
            <h2>Assenza modificata correttamente</h2>
            <p><?php echo htmlspecialchars(trim($studentSurname . ' ' . $studentName)); ?></p>
            <p class="text-muted">Tra pochi secondi torni allo snapshot presenze della classe.</p>
            <p>
                <a class="btn btn-success" href="mastercom_presence.php?class_id=<?php echo intval($classId); ?>">
                    Torna subito allo snapshot
                </a>
            </p>
        </div>
    <?php else: ?>
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-edit"></span>&emsp;Modifica assenza MasterCom</div>
        <div class="panel-body">
            <p>
                <a class="btn btn-default" href="mastercom_presence.php?class_id=<?php echo intval($classId); ?>">
                    <span class="glyphicon glyphicon-chevron-left"></span> Torna allo snapshot
                </a>
            </p>

            <?php if ($submitError !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($submitError); ?></div>
            <?php endif; ?>

            <?php if ($studentId <= 0 || $classId <= 0 || $absenceId <= 0 || $absenceDate <= 0): ?>
                <div class="alert alert-warning">Parametri modifica assenza mancanti.</div>
            <?php else: ?>
                <div class="alert alert-info">
                    Studente: <strong><?php echo htmlspecialchars(trim($studentSurname . ' ' . $studentName)); ?></strong>
                    | Classe: <strong><?php echo htmlspecialchars($className); ?></strong>
                    | ID assenza: <strong><?php echo intval($absenceId); ?></strong>
                </div>

                <form method="post" action="mastercom_absence_edit.php" class="form-horizontal">
                    <input type="hidden" name="submit_edit_absence" value="1">
                    <input type="hidden" name="student_id" value="<?php echo intval($studentId); ?>">
                    <input type="hidden" name="class_id" value="<?php echo intval($classId); ?>">
                    <input type="hidden" name="cognome" value="<?php echo htmlspecialchars($studentSurname); ?>">
                    <input type="hidden" name="nome" value="<?php echo htmlspecialchars($studentName); ?>">
                    <input type="hidden" name="classe" value="<?php echo htmlspecialchars($className); ?>">
                    <input type="hidden" name="id_assenza" value="<?php echo intval($absenceId); ?>">
                    <input type="hidden" name="data_assenza" value="<?php echo intval($absenceDate); ?>">

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="tipo_assenza">Tipo</label>
                        <div class="col-sm-5">
                            <select class="form-control" name="tipo_assenza" id="tipo_assenza">
                                <?php foreach ($absenceTypes as $typeId => $typeLabel): ?>
                                    <option value="<?php echo intval($typeId); ?>" <?php echo $selectedType === intval($typeId) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($typeLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="absence_date">Data</label>
                        <div class="col-sm-3">
                            <input type="date" class="form-control" name="absence_date" id="absence_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                        </div>
                        <label class="col-sm-1 control-label" for="absence_time">Ora</label>
                        <div class="col-sm-2">
                            <input type="time" class="form-control" name="absence_time" id="absence_time" value="<?php echo htmlspecialchars($selectedTime); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="esclusione_calcolo_monteore">Escludi monteore</label>
                        <div class="col-sm-2">
                            <select class="form-control" name="esclusione_calcolo_monteore" id="esclusione_calcolo_monteore">
                                <option value="0" selected>NO</option>
                                <option value="1">SI</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Confermi modifica assenza su MasterCom?');">
                                <span class="glyphicon glyphicon-ok"></span> Salva modifica su MasterCom
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

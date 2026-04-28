<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

function mastercomAdminRomeToday(string $format = 'Y-m-d'): string
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format($format);
}

function mastercomAdminRomeFormatTs(int $timestamp, string $format = 'd/m/Y'): string
{
    if ($timestamp <= 0) {
        return '';
    }

    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone(new DateTimeZone('Europe/Rome'));
    return $dt->format($format);
}

function mastercomAdminSchoolYearDateRange(): array
{
    $year = trim((string)(mastercomAdminCurrentSchoolYear() ?? ''));
    if (preg_match('/^(\d{4})\s*\/\s*(\d{4})$/', $year, $matches)) {
        return [
            'start' => $matches[1] . '-09-01',
            'end' => $matches[2] . '-08-31',
        ];
    }

    $currentYear = intval(mastercomAdminRomeToday('Y'));
    $currentMonth = intval(mastercomAdminRomeToday('n'));
    $startYear = $currentMonth >= 9 ? $currentYear : ($currentYear - 1);
    $endYear = $startYear + 1;

    return [
        'start' => $startYear . '-09-01',
        'end' => $endYear . '-08-31',
    ];
}

function mastercomAdminDayStartTs(string $date): int
{
    $value = trim($date);
    if ($value === '') {
        return 0;
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value . ' 00:00:00', new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomAdminDayEndTs(string $date): int
{
    $value = trim($date);
    if ($value === '') {
        return 0;
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value . ' 23:59:59', new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

$missingTables = mastercomAdminMissingTables(['mastercom_studenti']);
$studentId = intval($_GET['student_id'] ?? 0);
$mirrorRow = empty($missingTables) && $studentId > 0
    ? dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . $studentId . " LIMIT 1")
    : null;

$range = mastercomAdminSchoolYearDateRange();
$defaultStart = $range['start'];
$defaultEnd = min($range['end'], mastercomAdminRomeToday('Y-m-d'));

$startDate = trim((string)($_GET['start_date'] ?? $defaultStart));
$endDate = trim((string)($_GET['end_date'] ?? $defaultEnd));

$records = [];
$errorMessage = '';

if (empty($missingTables) && $studentId > 0) {
    $startTs = mastercomAdminDayStartTs($startDate);
    $endTs = mastercomAdminDayEndTs($endDate);

    if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        $errorMessage = 'Intervallo date non valido';
    } else {
        $authResult = mastercomAuthenticateService([
            'profile' => 'MasterComDocenteAuth',
            'method' => 'POST',
            'timeout' => 60,
        ]);

        if (!$authResult['ok']) {
            $errorMessage = 'Autenticazione MasterCom docente fallita';
        } else {
            $absencesResult = mastercomLoadAbsencesData($authResult, $studentId, $startTs, $endTs, [
                'method' => 'POST',
                'timeout' => 120,
            ]);

            if (!$absencesResult['ok'] || !is_array($absencesResult['response'] ?? null)) {
                $errorMessage = 'Caricamento assenze MasterCom fallito';
            } else {
                $records = is_array($absencesResult['response']['result'] ?? null) ? $absencesResult['response']['result'] : [];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Storico Assenze</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-calendar"></span>&emsp;Storico assenze MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Mancano tabelle: <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.</div>
            <?php elseif ($studentId <= 0): ?>
                <div class="alert alert-info">Seleziona uno studente da una pagina MasterCom per aprire lo storico assenze.</div>
            <?php else: ?>
                <div class="alert alert-info">
                    Studente:
                    <strong><?php echo htmlspecialchars(trim((string)($mirrorRow['cognome'] ?? '') . ' ' . (string)($mirrorRow['nome'] ?? ''))); ?></strong>
                    <?php if (!empty($mirrorRow['mastercom_id_classe_corrente'])): ?>
                        | classe MasterCom: <strong><?php echo htmlspecialchars((string)(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . intval($mirrorRow['mastercom_id_classe_corrente']) . " LIMIT 1") ?? '')); ?></strong>
                    <?php endif; ?>
                </div>

                <form method="get" action="mastercom_student_absences.php" class="form-inline" style="margin-bottom: 15px;">
                    <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                    <div class="form-group">
                        <label for="start_date">Dal&nbsp;</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="form-group" style="margin-left: 10px;">
                        <label for="end_date">Al&nbsp;</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Aggiorna</button>
                </form>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php else: ?>
                    <div class="alert alert-info">Eventi trovati: <strong><?php echo count($records); ?></strong></div>

                    <table class="table table-striped table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Codice</th>
                                <th>Orario</th>
                                <th>Giustificata</th>
                                <th>Descrizione</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <?php
                                $ts = intval($record['data'] ?? 0);
                                $dateLabel = mastercomAdminRomeFormatTs($ts, 'd/m/Y');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dateLabel); ?></td>
                                    <td><?php echo htmlspecialchars((string)($record['codice'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars(trim((string)($record['orario'] ?? ''))); ?></td>
                                    <td>
                                        <?php if (!empty($record['giustificata'])): ?>
                                            <span class="label label-success">sì</span>
                                        <?php else: ?>
                                            <span class="label label-danger">no</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)($record['descrizione'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

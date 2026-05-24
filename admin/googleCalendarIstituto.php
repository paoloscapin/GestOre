<?php

require_once '../common/checkSession.php';

ruoloRichiesto('admin');

define('GESTORE_CDC_COLLEGIO_SYNC_LIBRARY', true);
require_once '../api/googleCalendarIstitutoCdcCollegioSync.php';

function adminGoogleCalendarIstitutoH($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adminGoogleCalendarIstitutoParam($name, $default = '')
{
    return isset($_REQUEST[$name]) ? trim((string)$_REQUEST[$name]) : $default;
}

function adminGoogleCalendarIstitutoActionLabel($action)
{
    $action = trim((string)$action);
    switch ($action) {
        case 'would_update':
            return 'Gia presente: verrebbe aggiornato';
        case 'would_insert':
            return 'Nuovo: verrebbe creato';
        case 'would_adopt_update':
            return 'Gia presente su Calendar: verrebbe agganciato e aggiornato';
        case 'would_skip_existing':
            return 'Gia presente: verrebbe saltato';
        case 'update':
            return 'Aggiornato';
        case 'insert':
            return 'Creato';
        case 'adopt_update':
            return 'Agganciato e aggiornato';
        case 'skip_existing':
            return 'Gia presente: saltato';
        default:
            return $action;
    }
}

function adminGoogleCalendarIstitutoDateIt($date)
{
    $date = trim((string)$date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}

function adminGoogleCalendarIstitutoDateTimeIt($dateTime)
{
    $dateTime = trim((string)$dateTime);
    if ($dateTime === '') {
        return '';
    }

    try {
        $dt = new DateTime($dateTime);
        $dt->setTimezone(new DateTimeZone('Europe/Rome'));
        return $dt->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return $dateTime;
    }
}

$from = adminGoogleCalendarIstitutoParam('from', date('Y-m-d'));
$to = adminGoogleCalendarIstitutoParam('to', date('Y-m-d', strtotime('+30 days')));
$dryRun = adminGoogleCalendarIstitutoParam('dry_run', '') === '1';
$defaultUpdateExisting = (bool)($__settings->local->googleCalendar->calendarIstitutoUpdateExisting ?? true);
$updateExisting = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? adminGoogleCalendarIstitutoParam('update_existing', '') === '1'
    : $defaultUpdateExisting;
$singleIdAssenza = intval(adminGoogleCalendarIstitutoParam('idAssenza', '0'));
$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = cdcRunSync($from, $to, $dryRun, [
            'update_existing' => $updateExisting,
            'idAssenza' => $singleIdAssenza
        ]);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Google Calendar Istituto</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .cdc-sync-summary .well { min-height: 92px; }
        .cdc-sync-table-wrap { max-height: 520px; overflow: auto; border: 1px solid #ddd; }
        .cdc-sync-table-wrap table { margin-bottom: 0; }
        .cdc-sync-table-wrap th,
        .cdc-sync-table-wrap td { text-align: center; vertical-align: middle !important; }
        .cdc-muted { color: #666; }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-calendar"></span>&emsp;Google Calendar Istituto - CdC e Collegio docenti
        </div>
        <div class="panel-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo adminGoogleCalendarIstitutoH($error); ?></div>
            <?php elseif (is_array($result)): ?>
                <div class="alert alert-success">
                    Sincronizzazione completata: <?php echo intval($result['processed'] ?? 0); ?> eventi processati su <?php echo intval($result['found'] ?? 0); ?> impegni trovati.
                    <?php if (!empty($result['dry_run'])): ?>
                        <strong>Prova senza scrittura attiva.</strong>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="form-inline" style="margin-bottom: 20px;" onsubmit="return confirm('Avviare la sincronizzazione Calendar Istituto per il periodo selezionato?');">
                <div class="form-group">
                    <label for="from">Dal</label>
                    <input type="date" class="form-control" id="from" name="from" value="<?php echo adminGoogleCalendarIstitutoH($from); ?>">
                </div>
                <div class="form-group">
                    <label for="to">Al</label>
                    <input type="date" class="form-control" id="to" name="to" value="<?php echo adminGoogleCalendarIstitutoH($to); ?>">
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="dry_run" value="1" <?php echo $dryRun ? 'checked' : ''; ?>> prova senza scrittura
                    </label>
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="update_existing" value="1" <?php echo $updateExisting ? 'checked' : ''; ?>> aggiorna anche eventi gia presenti
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">
                    <span class="glyphicon glyphicon-refresh"></span> Sincronizza
                </button>
            </form>

            <p class="cdc-muted">
                La pagina usa lo stesso motore dell'API <code>googleCalendarIstitutoCdcCollegioSync.php</code> e recupera automaticamente l'anno scolastico corrente.
            </p>

            <?php if (is_array($result)): ?>
                <div class="row cdc-sync-summary">
                    <div class="col-md-2"><div class="well"><strong>Periodo</strong><br><?php echo adminGoogleCalendarIstitutoH(adminGoogleCalendarIstitutoDateIt($result['from'] ?? '')); ?> - <?php echo adminGoogleCalendarIstitutoH(adminGoogleCalendarIstitutoDateIt($result['to'] ?? '')); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Anno scolastico ID</strong><br><?php echo intval($result['id_anno_scolastico'] ?? 0); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Impegni trovati</strong><br><?php echo intval($result['found'] ?? 0); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Eventi processati</strong><br><?php echo intval($result['processed'] ?? 0); ?></div></div>
                    <div class="col-md-4"><div class="well"><strong>Calendario</strong><br><?php echo adminGoogleCalendarIstitutoH($result['calendar_nome'] ?? ''); ?><br><small><?php echo adminGoogleCalendarIstitutoH($result['calendar_id'] ?? ''); ?></small></div></div>
                </div>
                <p class="cdc-muted">
                    Modalita: <?php echo !empty($result['update_existing']) ? 'crea nuovi eventi e aggiorna quelli gia presenti' : 'crea solo i nuovi eventi, senza aggiornare quelli gia presenti'; ?>.
                    <?php if (!empty($result['idAssenza'])): ?>
                        Sincronizzazione singolo evento MBApp #<?php echo intval($result['idAssenza']); ?>.
                    <?php endif; ?>
                </p>

                <h4>Risultati</h4>
                <div class="cdc-sync-table-wrap">
                    <table class="table table-bordered table-condensed table-striped">
                        <thead>
                        <tr>
                            <th>ID assenza MBApp</th>
                            <th>Titolo</th>
                            <th>Inizio</th>
                            <th>Fine</th>
                            <th>Azione</th>
                            <th>Calendario</th>
                            <th>Evento Google</th>
                            <th>Partecipanti</th>
                            <th>Sync singolo</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($result['results'] ?? []) as $row): ?>
                            <?php $rowIdAssenza = intval($row['idAssenza'] ?? 0); ?>
                            <tr>
                                <td><?php echo $rowIdAssenza; ?></td>
                                <td><?php echo adminGoogleCalendarIstitutoH($row['titolo'] ?? ($row['event']['summary'] ?? '')); ?></td>
                                <td><?php echo adminGoogleCalendarIstitutoH(adminGoogleCalendarIstitutoDateTimeIt($row['event']['start']['dateTime'] ?? '')); ?></td>
                                <td><?php echo adminGoogleCalendarIstitutoH(adminGoogleCalendarIstitutoDateTimeIt($row['event']['end']['dateTime'] ?? '')); ?></td>
                                <td><?php echo adminGoogleCalendarIstitutoH(adminGoogleCalendarIstitutoActionLabel($row['action'] ?? '')); ?></td>
                                <td><?php echo adminGoogleCalendarIstitutoH($row['calendar_nome'] ?? ''); ?></td>
                                <td><?php echo adminGoogleCalendarIstitutoH($row['google_event_id'] ?? ''); ?></td>
                                <td><?php echo intval($row['attendees_count'] ?? 0); ?></td>
                                <td>
                                    <?php if ($rowIdAssenza > 0): ?>
                                        <form method="post" class="form-inline" onsubmit="return confirm('Sincronizzare solo questo evento su Google Calendar?');">
                                            <input type="hidden" name="from" value="<?php echo adminGoogleCalendarIstitutoH($from); ?>">
                                            <input type="hidden" name="to" value="<?php echo adminGoogleCalendarIstitutoH($to); ?>">
                                            <input type="hidden" name="idAssenza" value="<?php echo $rowIdAssenza; ?>">
                                            <input type="hidden" name="update_existing" value="1">
                                            <button type="submit" class="btn btn-xs btn-info" title="Sincronizza e aggiorna solo questo evento">
                                                <span class="glyphicon glyphicon-refresh"></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($result['results'])): ?>
                            <tr><td colspan="9" class="text-center text-muted">Nessun evento da sincronizzare nel periodo selezionato.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>

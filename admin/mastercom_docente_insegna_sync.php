<?php

require_once '../common/checkSession.php';
require_once '../common/docente_insegna_mbapp_sync_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function docenteInsegnaSyncH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function docenteInsegnaSyncParam(string $name, string $default = ''): string
{
    return isset($_REQUEST[$name]) ? trim((string)$_REQUEST[$name]) : $default;
}

[$defaultFrom, $defaultTo] = docenteInsegnaMbappCurrentWeekRange();
$from = docenteInsegnaSyncParam('from', $defaultFrom);
$to = docenteInsegnaSyncParam('to', $defaultTo);
$removeObsolete = docenteInsegnaMbappSyncBool(docenteInsegnaSyncParam('rimuovi_obsoleti', '1'), true);
$apply = $_SERVER['REQUEST_METHOD'] === 'POST' && docenteInsegnaSyncParam('action', '') === 'sync';
$result = null;
$error = '';

try {
    $result = docenteInsegnaMbappSync([
        'from' => $from,
        'to' => $to,
        'apply' => $apply,
        'rimuovi_obsoleti' => $removeObsolete,
        'preserva_se_vuoto' => true,
    ]);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$summary = [
    'righe_mbapp' => is_array($result) ? count($result['mbapp_rows'] ?? []) : 0,
    'gia_presenti' => is_array($result) ? count($result['already_present'] ?? []) : 0,
    'da_inserire' => is_array($result) ? count($result['to_insert'] ?? []) : 0,
    'da_rimuovere' => is_array($result) ? count($result['to_remove'] ?? []) : 0,
    'anomalie' => is_array($result) ? count($result['errors'] ?? []) : 0,
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Docente insegna</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .sync-summary .well { min-height: 92px; }
        .sync-table-wrap { max-height: 420px; overflow: auto; border: 1px solid #ddd; }
        .sync-table-wrap table { margin-bottom: 0; }
        .sync-muted { color: #666; }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-link"></span>&emsp;Sync docente_insegna da MBApp
        </div>
        <div class="panel-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo docenteInsegnaSyncH($error); ?></div>
            <?php endif; ?>

            <?php if (is_array($result) && !empty($result['skipped'])): ?>
                <div class="alert alert-warning">
                    <?php echo docenteInsegnaSyncH($result['skip_reason'] ?? 'Nessuna modifica effettuata.'); ?>
                </div>
            <?php elseif ($apply): ?>
                <div class="alert alert-success">
                    Sincronizzazione completata. Inseriti <?php echo intval($summary['da_inserire']); ?> record, rimossi <?php echo intval($summary['da_rimuovere']); ?> record.
                </div>
            <?php endif; ?>

            <form method="get" action="mastercom_docente_insegna_sync.php" class="form-inline" style="margin-bottom: 15px;">
                <div class="form-group">
                    <label for="from">Dal</label>
                    <input type="date" class="form-control" id="from" name="from" value="<?php echo docenteInsegnaSyncH($from); ?>">
                </div>
                <div class="form-group">
                    <label for="to">Al</label>
                    <input type="date" class="form-control" id="to" name="to" value="<?php echo docenteInsegnaSyncH($to); ?>">
                </div>
                <div class="form-group">
                    <label for="rimuovi_obsoleti">Rimuovi obsoleti</label>
                    <select class="form-control" id="rimuovi_obsoleti" name="rimuovi_obsoleti">
                        <option value="1" <?php echo $removeObsolete ? 'selected' : ''; ?>>Si</option>
                        <option value="0" <?php echo !$removeObsolete ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-info">
                    <span class="glyphicon glyphicon-eye-open"></span> Preview
                </button>
            </form>

            <?php if (is_array($result)): ?>
                <div class="row sync-summary">
                    <div class="col-md-2"><div class="well"><strong>Periodo</strong><br><?php echo docenteInsegnaSyncH($result['from']); ?> - <?php echo docenteInsegnaSyncH($result['to']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Righe MBApp</strong><br><?php echo intval($summary['righe_mbapp']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Da inserire</strong><br><?php echo intval($summary['da_inserire']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Gia presenti</strong><br><?php echo intval($summary['gia_presenti']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Da rimuovere</strong><br><?php echo intval($summary['da_rimuovere']); ?></div></div>
                    <div class="col-md-2"><div class="well"><strong>Anomalie</strong><br><?php echo intval($summary['anomalie']); ?></div></div>
                </div>

                <p class="sync-muted">
                    Colonna data MBApp usata: <code><?php echo docenteInsegnaSyncH($result['date_column'] ?? ''); ?></code>.
                    Se a giugno non risultano ore nel periodo scelto, la sincronizzazione non svuota la tabella.
                </p>

                <?php if (!$apply): ?>
                    <form method="post" action="mastercom_docente_insegna_sync.php" style="margin-bottom: 20px;" onsubmit="return confirm('Eseguire la sincronizzazione docente_insegna con questi dati?');">
                        <input type="hidden" name="action" value="sync">
                        <input type="hidden" name="from" value="<?php echo docenteInsegnaSyncH($from); ?>">
                        <input type="hidden" name="to" value="<?php echo docenteInsegnaSyncH($to); ?>">
                        <input type="hidden" name="rimuovi_obsoleti" value="<?php echo $removeObsolete ? '1' : '0'; ?>">
                        <button type="submit" class="btn btn-success">
                            <span class="glyphicon glyphicon-refresh"></span> Esegui sincronizzazione
                        </button>
                    </form>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <h4>Da inserire<?php echo $apply ? ' / inseriti' : ''; ?></h4>
                        <?php docenteInsegnaSyncRenderRows($result['to_insert'] ?? []); ?>
                    </div>
                    <div class="col-md-6">
                        <h4>Da rimuovere<?php echo $apply ? ' / rimossi' : ''; ?></h4>
                        <?php docenteInsegnaSyncRenderRows($result['to_remove'] ?? []); ?>
                    </div>
                </div>

                <h4>Anomalie</h4>
                <?php docenteInsegnaSyncRenderErrors($result['errors'] ?? []); ?>

                <h4>Diagnostica MBApp</h4>
                <table class="table table-bordered table-condensed">
                    <tbody>
                    <tr>
                        <th style="width: 320px;">Range date presenti in MBApp</th>
                        <td><?php echo docenteInsegnaSyncH(($result['debug']['range_date']['min_data'] ?? '') . ' - ' . ($result['debug']['range_date']['max_data'] ?? '')); ?></td>
                    </tr>
                    <tr>
                        <th>Incroci docente/classe/materia senza filtro data</th>
                        <td><?php echo docenteInsegnaSyncH($result['debug']['count_senza_filtro_data']['n'] ?? '0'); ?></td>
                    </tr>
                    <tr>
                        <th>Incroci docente/classe/materia nel periodo</th>
                        <td><?php echo docenteInsegnaSyncH($result['debug']['count_con_filtro_data']['n'] ?? '0'); ?></td>
                    </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
<?php

function docenteInsegnaSyncRenderRows(array $rows): void
{
    if (empty($rows)) {
        echo '<p class="sync-muted">Nessun record.</p>';
        return;
    }

    echo '<div class="sync-table-wrap"><table class="table table-striped table-condensed">';
    echo '<thead><tr><th>Username</th><th>Classe</th><th>Materia</th><th>ID docente</th><th>ID classe</th><th>ID materia</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . docenteInsegnaSyncH($row['username'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['classe'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['materia'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['id_docente'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['id_classe'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['id_materia'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function docenteInsegnaSyncRenderErrors(array $rows): void
{
    if (empty($rows)) {
        echo '<p class="text-success"><strong>Nessuna anomalia.</strong></p>';
        return;
    }

    echo '<div class="sync-table-wrap"><table class="table table-bordered table-condensed">';
    echo '<thead><tr><th>Tipo</th><th>Username</th><th>Classe MBApp</th><th>Materia MBApp</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td><span class="label label-warning">' . docenteInsegnaSyncH($row['tipo'] ?? '') . '</span></td>';
        echo '<td>' . docenteInsegnaSyncH($row['username'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['classe'] ?? '') . '</td>';
        echo '<td>' . docenteInsegnaSyncH($row['materia'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

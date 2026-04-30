<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/__Settings.php';
require_once '../common/ticket_mail_lib.php';

ruoloRichiesto('admin');

function ticketMailImportPostValue(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return trim((string)$value);
}

function ticketMailImportPostBool(string $key, bool $default = false): bool
{
    if (!isset($_POST[$key])) {
        return $default;
    }

    $value = $_POST[$key];
    if (is_bool($value)) {
        return $value;
    }

    return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
}

$config = ticketMailConfig();
$defaultMarkSeen = (bool)($config['mark_seen_after_import'] ?? true);
$form = [
    'limit' => ticketMailImportPostValue('limit', '10'),
    'mark_seen' => ticketMailImportPostBool('mark_seen', $defaultMarkSeen),
];

$importResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ticketMailImportPostValue('action') === 'import_mail') {
    $importResult = ticketMailImportInbox(
        intval($form['limit']),
        $form['mark_seen'],
        false
    );
}

$recentLog = [];
if (ticketMailTableExists('ticket_mail_import_log')) {
    $recentLog = dbGetAll("
        SELECT *
        FROM ticket_mail_import_log
        ORDER BY id DESC
        LIMIT 30
    ");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Import Mail Ticket</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-import"></span>&emsp;Import Mail Ticket</div>
        <div class="panel-body">
            <div class="alert alert-info">
                Importa manualmente le nuove mail indirizzate a <strong><?php echo htmlspecialchars((string)$config['alias_address']); ?></strong>.
                Le mail inviate in risposta ai ticket potranno uscire tecnicamente da <strong><?php echo htmlspecialchars((string)($__settings->local->smtpMail ?? '')); ?></strong>,
                ma lato utente il mittente visibile previsto resta <strong><?php echo htmlspecialchars((string)($config['reply_visible_from'] ?? $config['alias_address'])); ?></strong>.
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Configurazione attiva</strong></div>
                        <div class="panel-body">
                            <table class="table table-bordered table-condensed">
                                <tbody>
                                <tr><th>Abilitato</th><td><?php echo !empty($config['enabled']) ? 'si' : 'no'; ?></td></tr>
                                <tr><th>Alias ticket</th><td><?php echo htmlspecialchars((string)$config['alias_address']); ?></td></tr>
                                <tr><th>Mailbox IMAP</th><td><?php echo htmlspecialchars((string)$config['imap_mailbox']); ?></td></tr>
                                <tr><th>Utente mailbox</th><td><?php echo htmlspecialchars((string)$config['mailbox_user']); ?></td></tr>
                                <tr><th>Mittente visibile risposte</th><td><?php echo htmlspecialchars((string)($config['reply_visible_from'] ?? '')); ?></td></tr>
                                <tr><th>Chat Telegram servizio</th><td><?php echo htmlspecialchars((string)($config['service_chat_id'] ?? '')); ?></td></tr>
                                <tr><th>Fascia silenziosa</th><td><?php echo !empty($config['quiet_hours_enabled']) ? htmlspecialchars((string)$config['quiet_hours_start']) . ' - ' . htmlspecialchars((string)$config['quiet_hours_end']) : 'disattivata'; ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Esegui import</strong></div>
                        <div class="panel-body">
                            <?php if ($importResult !== null): ?>
                                <div class="alert alert-<?php echo !empty($importResult['ok']) ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars((string)($importResult['message'] ?? '')); ?>
                                    <?php if (!empty($importResult['counts'])): ?>
                                        <br>
                                        <small>
                                            elaborate: <?php echo intval($importResult['counts']['processed'] ?? 0); ?>,
                                            importate: <?php echo intval($importResult['counts']['imported'] ?? 0); ?>,
                                            ignorate: <?php echo intval($importResult['counts']['skipped'] ?? 0); ?>,
                                            errori: <?php echo intval($importResult['counts']['errors'] ?? 0); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if (!empty($importResult['mailbox_used'])): ?>
                                        <br><small>mailbox usata: <?php echo htmlspecialchars((string)$importResult['mailbox_used']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <form method="post" class="form-inline" style="margin-bottom:18px;">
                                <input type="hidden" name="action" value="import_mail">
                                <div class="form-group">
                                    <label for="limit">Max mail da controllare</label>
                                    <input type="number" min="1" max="30" class="form-control" id="limit" name="limit" value="<?php echo htmlspecialchars($form['limit']); ?>" style="width:100px;">
                                </div>
                                <div class="checkbox" style="margin-left:16px; margin-right:16px;">
                                    <label>
                                        <input type="checkbox" name="mark_seen" value="1" <?php echo !empty($form['mark_seen']) ? 'checked' : ''; ?>>
                                        segna come lette dopo import
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary">Importa nuove mail</button>
                            </form>

                            <?php if ($importResult !== null && !empty($importResult['results'])): ?>
                                <div style="max-height: 520px; overflow:auto;">
                                    <table class="table table-striped table-bordered table-condensed">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Mittente</th>
                                            <th>Oggetto</th>
                                            <th>Ticket</th>
                                            <th>Esito</th>
                                            <th>Dettaglio</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($importResult['results'] as $row): ?>
                                            <tr>
                                                <td style="text-align:center;"><?php echo intval($row['msgno'] ?? 0); ?></td>
                                                <td><?php echo htmlspecialchars((string)($row['from_email'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($row['subject'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars((string)($row['ticket_code'] ?? '')); ?></td>
                                                <td style="text-align:center;">
                                                    <?php
                                                    $status = (string)($row['status'] ?? '');
                                                    if ($status === 'imported') {
                                                        echo '<span class="label label-success">importata</span>';
                                                    } elseif ($status === 'error') {
                                                        echo '<span class="label label-danger">errore</span>';
                                                    } else {
                                                        echo '<span class="label label-default">ignorata</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars((string)($row['note'] ?? '')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading"><strong>Log ultime importazioni</strong></div>
                <div class="panel-body">
                    <?php if (empty($recentLog)): ?>
                        <div class="alert alert-warning" style="margin-bottom:0;">
                            Nessun log disponibile. Se manca la tabella, esegui prima la SQL di creazione.
                        </div>
                    <?php else: ?>
                        <div style="max-height: 420px; overflow:auto;">
                            <table class="table table-striped table-bordered table-condensed">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Quando</th>
                                    <th>Mittente</th>
                                    <th>Oggetto</th>
                                    <th>Ticket</th>
                                    <th>Esito</th>
                                    <th>Nota</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentLog as $row): ?>
                                    <tr>
                                        <td style="text-align:center;"><?php echo intval($row['id'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['imported_at'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['from_email'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['subject'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['ticket_code'] ?? '')); ?></td>
                                        <td style="text-align:center;"><?php echo htmlspecialchars((string)($row['esito'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['nota'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

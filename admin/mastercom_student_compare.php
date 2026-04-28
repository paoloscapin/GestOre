<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

$mastercomId = intval($_GET['id'] ?? 0);
$mirror = dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . $mastercomId . " LIMIT 1");
$compare = $mirror ? mastercomAdminStudentDiffs($mirror) : ['local' => null, 'diffs' => ['record' => 'non trovato']];
$message = trim((string)($_GET['message'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Confronto Studente MasterCom</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-education"></span>&emsp;Confronto studente</div>
        <div class="panel-body">
            <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($mirror == null): ?>
                <div class="alert alert-warning">Studente MasterCom non trovato.</div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-6">
                        <h4>MasterCom</h4>
                        <table class="table table-bordered">
                            <tr><th>ID MasterCom</th><td><?php echo intval($mirror['mastercom_id_studente']); ?></td></tr>
                            <tr><th>Cognome</th><td><?php echo htmlspecialchars($mirror['cognome'] ?? ''); ?></td></tr>
                            <tr><th>Nome</th><td><?php echo htmlspecialchars($mirror['nome'] ?? ''); ?></td></tr>
                            <tr><th>Email</th><td><?php echo htmlspecialchars($mirror['email1'] ?? ''); ?></td></tr>
                            <tr><th>Codice fiscale</th><td><?php echo htmlspecialchars($mirror['codice_fiscale'] ?? ''); ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h4>GestOre</h4>
                        <?php if ($compare['local'] != null): ?>
                            <table class="table table-bordered">
                                <tr><th>ID GestOre</th><td><?php echo intval($compare['local']['id']); ?></td></tr>
                                <tr><th>Cognome</th><td><?php echo htmlspecialchars($compare['local']['cognome'] ?? ''); ?></td></tr>
                                <tr><th>Nome</th><td><?php echo htmlspecialchars($compare['local']['nome'] ?? ''); ?></td></tr>
                                <tr><th>Email</th><td><?php echo htmlspecialchars($compare['local']['email'] ?? ''); ?></td></tr>
                                <tr><th>Codice fiscale</th><td><?php echo htmlspecialchars($compare['local']['codice_fiscale'] ?? ''); ?></td></tr>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-warning">Nessuno studente GestOre collegato o riconosciuto.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <h4>Differenze</h4>
                <?php if (empty($compare['diffs'])): ?>
                    <div class="alert alert-success">Nessuna differenza rilevata.</div>
                <?php else: ?>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr><th>Campo</th><th>GestOre</th><th>MasterCom</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($compare['diffs'] as $field => $values): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($field); ?></td>
                                    <td><?php echo htmlspecialchars(is_array($values) ? (string)($values['gestore'] ?? '') : ''); ?></td>
                                    <td><?php echo htmlspecialchars(is_array($values) ? (string)($values['mastercom'] ?? '') : (string)$values); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <form method="post" action="mastercom_align.php" style="display:inline-block;">
                    <input type="hidden" name="type" value="student">
                    <input type="hidden" name="direction" value="gestore_from_mastercom">
                    <input type="hidden" name="id" value="<?php echo intval($mirror['mastercom_id_studente']); ?>">
                    <button class="btn btn-primary" type="submit">Allinea GestOre da MasterCom</button>
                </form>
                <form method="post" action="mastercom_align.php" style="display:inline-block;">
                    <input type="hidden" name="type" value="student">
                    <input type="hidden" name="direction" value="mastercom_from_gestore">
                    <input type="hidden" name="id" value="<?php echo intval($mirror['mastercom_id_studente']); ?>">
                    <button class="btn btn-default" type="submit">Allinea scheda MasterCom locale da GestOre</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

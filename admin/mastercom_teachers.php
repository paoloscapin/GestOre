<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

$missingTables = mastercomAdminMissingTables(['mastercom_docenti']);
$filter = trim((string)($_GET['filter'] ?? 'all'));
$rows = empty($missingTables)
    ? dbGetAll("
        SELECT
            m.*,
            d.cognome AS gestore_cognome,
            d.nome AS gestore_nome,
            d.username AS gestore_username,
            d.attivo AS gestore_attivo
        FROM mastercom_docenti m
        LEFT JOIN docente d ON d.id = m.id_docente_gestore
        ORDER BY m.nome_visualizzato ASC
    ")
    : [];
$filteredRows = [];
foreach ($rows as $row) {
    if (mastercomAdminTeacherMatchesFilter($row, $filter)) {
        $filteredRows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Docenti</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once headerAdminDidatticaPath('../common'); ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-blackboard"></span>&emsp;Docenti MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Manca la tabella <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.</div>
            <?php else: ?>
                <form method="get" action="mastercom_teachers.php" class="form-inline" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="filter">Vista&nbsp;</label>
                        <select name="filter" id="filter" class="form-control" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Tutti</option>
                            <option value="aligned" <?php echo $filter === 'aligned' ? 'selected' : ''; ?>>Solo allineati</option>
                            <option value="issues" <?php echo $filter === 'issues' ? 'selected' : ''; ?>>Solo con problemi</option>
                            <option value="active_gestore" <?php echo $filter === 'active_gestore' ? 'selected' : ''; ?>>Solo collegati e attivi in GestOre</option>
                        </select>
                    </div>
                    <a href="mastercom_teachers.php" class="btn btn-default">Mostra tutti</a>
                </form>

                <div class="alert alert-info">
                    Record visualizzati: <strong><?php echo count($filteredRows); ?></strong> su <?php echo count($rows); ?>.
                </div>

                <table class="table table-striped table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>ID MasterCom</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Collegato GestOre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredRows as $row): ?>
                            <?php $status = mastercomAdminTeacherStatus($row); ?>
                            <tr>
                                <td><?php echo intval($row['mastercom_id_user']); ?></td>
                                <td><?php echo htmlspecialchars($row['nome_visualizzato'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['tipo_utente'] ?? ''); ?></td>
                                <td>
                                    <?php echo !empty($row['id_docente_gestore'])
                                        ? htmlspecialchars(trim(($row['gestore_cognome'] ?? '') . ' ' . ($row['gestore_nome'] ?? '') . ' [' . ($row['gestore_username'] ?? '') . ']'))
                                        : '<span class="label label-warning">non collegato</span>'; ?>
                                    <?php if (!empty($row['id_docente_gestore'])): ?>
                                        <br><small>attivo GestOre: <?php echo intval($row['gestore_attivo'] ?? 0) === 1 ? 'sÃ¬' : 'no'; ?></small>
                                    <?php endif; ?>
                                    <br>
                                    <span class="label label-<?php echo htmlspecialchars($status['class']); ?>">
                                        <?php echo htmlspecialchars($status['label']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

$missingTables = mastercomAdminMissingTables(['mastercom_classi']);
$rows = empty($missingTables)
    ? dbGetAll("
        SELECT
            m.*,
            c.classe AS gestore_classe
        FROM mastercom_classi m
        LEFT JOIN classi c ON c.id = m.id_classe_gestore
        ORDER BY m.nome ASC
    ")
    : [];
$currentSchoolYear = mastercomAdminCurrentSchoolYear();
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Classi</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-th-large"></span>&emsp;Classi MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Manca la tabella <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.</div>
            <?php else: ?>
                <table class="table table-striped table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>ID MasterCom</th>
                            <th>Classe</th>
                            <th>Anno</th>
                            <th>Collegato GestOre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $resolvedClass = mastercomAdminResolveLocalClass($row);
                            $effectiveYear = trim((string)($row['anno_scolastico'] ?? ''));
                            if ($effectiveYear === '') {
                                $effectiveYear = $currentSchoolYear ?? '';
                            }
                            ?>
                            <tr>
                                <td><?php echo intval($row['mastercom_id_classe']); ?></td>
                                <td><?php echo htmlspecialchars($row['nome'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($effectiveYear); ?></td>
                                <td>
                                    <?php
                                    if (!empty($row['id_classe_gestore'])) {
                                        echo htmlspecialchars($row['gestore_classe'] ?? '');
                                    } elseif ($resolvedClass != null) {
                                        echo htmlspecialchars($resolvedClass['classe'] ?? '') . ' <span class="label label-info">match automatico</span>';
                                    } else {
                                        echo '<span class="label label-warning">non collegata</span>';
                                    }
                                    ?>
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

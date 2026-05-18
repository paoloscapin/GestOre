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
                            <th style="text-align: center;">ID MasterCom</th>
                            <th style="text-align: center;">Classe</th>
                            <th style="text-align: center;">Anno</th>
                            <th style="text-align: center;">Collegato GestOre</th>
                            <th style="text-align: center;"></th>
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
                            $isLinkedToGestore = intval($row['id_classe_gestore'] ?? 0) > 0;
                            ?>
                            <tr>
                                <td style="text-align: center;"><?php echo intval($row['mastercom_id_classe']); ?></td>
                                <td style="text-align: center;"><?php echo htmlspecialchars($row['nome'] ?? ''); ?></td>
                                <td style="text-align: center;"><?php echo htmlspecialchars($effectiveYear); ?></td>
                                <td style="text-align: center;">
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
                                <td style="text-align: center;">
                                    <?php if ($isLinkedToGestore): ?>
                                        <a class="btn btn-xs btn-default" href="mastercom_students.php?class_id=<?php echo intval($row['mastercom_id_classe']); ?>">
                                            Studenti
                                        </a>
                                        <a class="btn btn-xs btn-info" href="mastercom_presence.php?class_id=<?php echo intval($row['mastercom_id_classe']); ?>">
                                            Presenze
                                        </a>
                                        <a class="btn btn-xs btn-primary" href="mastercom_calendar.php?class_id=<?php echo intval($row['mastercom_id_classe']); ?>">
                                            Agenda
                                        </a>
                                        <a class="btn btn-xs btn-success" href="mastercom_grades.php?class_id=<?php echo intval($row['mastercom_id_classe']); ?>">
                                            Voti
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">esclusa dai menu operativi</span>
                                    <?php endif; ?>
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

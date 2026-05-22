<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

$filter = trim((string)($_GET['filter'] ?? 'all'));
$allowedFilters = ['all', 'aligned', 'missing', 'issues', 'low', 'medium', 'high'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$missingTables = mastercomAdminMissingTables(['mastercom_genitori', 'mastercom_studenti', 'mastercom_genitori_studenti']);
$rows = empty($missingTables)
    ? dbGetAll("
        SELECT
            g.*,
            gg.cognome AS gestore_cognome,
            gg.nome AS gestore_nome,
            gg.email AS gestore_email,
            gg.attivo AS gestore_attivo,
            local_links.studenti_locali_count,
            links.studenti_count AS mastercom_studenti_count,
            links.studenti_nomi AS mastercom_studenti_nomi
        FROM mastercom_genitori g
        LEFT JOIN genitori gg ON gg.id = g.id_genitore_gestore
        LEFT JOIN (
            SELECT
                id_genitore,
                COUNT(*) AS studenti_locali_count
            FROM genitori_studenti
            GROUP BY id_genitore
        ) local_links ON local_links.id_genitore = gg.id
        LEFT JOIN (
            SELECT
                gs.mastercom_id_parente,
                COUNT(*) AS studenti_count,
                GROUP_CONCAT(TRIM(CONCAT(COALESCE(s.cognome, ''), ' ', COALESCE(s.nome, ''))) ORDER BY s.cognome, s.nome SEPARATOR ' | ') AS studenti_nomi
            FROM mastercom_genitori_studenti gs
            INNER JOIN mastercom_studenti s
                ON s.mastercom_id_studente = gs.mastercom_id_studente
            GROUP BY gs.mastercom_id_parente
        ) links ON links.mastercom_id_parente = g.mastercom_id_parente
        ORDER BY g.cognome ASC, g.nome ASC
    ")
    : [];
$visibleCount = 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Genitori</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-user"></span>&emsp;Genitori MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Mancano tabelle: <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.</div>
            <?php else: ?>
                <form class="form-inline" method="get" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="filter">Filtro</label>
                        <select class="form-control" id="filter" name="filter" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Tutti</option>
                            <option value="aligned" <?php echo $filter === 'aligned' ? 'selected' : ''; ?>>Solo ok</option>
                            <option value="missing" <?php echo $filter === 'missing' ? 'selected' : ''; ?>>Non presenti in GestOre</option>
                            <option value="issues" <?php echo $filter === 'issues' ? 'selected' : ''; ?>>Solo con differenze</option>
                            <option value="low" <?php echo $filter === 'low' ? 'selected' : ''; ?>>Differenza lieve</option>
                            <option value="medium" <?php echo $filter === 'medium' ? 'selected' : ''; ?>>Differenze medie</option>
                            <option value="high" <?php echo $filter === 'high' ? 'selected' : ''; ?>>Differenze alte</option>
                        </select>
                    </div>
                    <a href="mastercom_parents.php" class="btn btn-default">Mostra tutti</a>
                </form>
                <table class="table table-striped table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>ID MasterCom</th>
                            <th>Genitore</th>
                            <th>Contatti MasterCom</th>
                            <th>Residenza MasterCom</th>
                            <th>Studenti MasterCom</th>
                            <th>GestOre</th>
                            <th>Esito</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $compare = mastercomAdminParentDiffs($row); ?>
                            <?php if (!mastercomAdminParentMatchesFilter($compare, $filter)) { continue; } ?>
                            <?php $status = mastercomAdminDiffStatus($compare); ?>
                            <?php
                            $visibleCount++;
                            $mirrorFullName = trim(
                                (string)(mastercomAdminCleanText($row['cognome'] ?? '') ?? '') . ' ' .
                                (string)(mastercomAdminCleanText($row['nome'] ?? '') ?? '')
                            );
                            $mirrorEmail = mastercomAdminCleanText($row['email'] ?? '') ?? '';
                            $mirrorPhone = mastercomAdminCleanText($row['telefono'] ?? '') ?? '';
                            $mirrorMobile = mastercomAdminCleanText($row['cellulare'] ?? '') ?? '';
                            $mirrorAddress = mastercomAdminCleanText($row['indirizzo'] ?? '') ?? '';
                            $mirrorZip = mastercomAdminCleanText($row['cap'] ?? '') ?? '';
                            $mirrorCity = mastercomAdminCleanText($row['citta'] ?? '') ?? '';
                            $mirrorProvince = mastercomAdminCleanText($row['provincia'] ?? '') ?? '';
                            $linkedStudents = mastercomAdminCleanText($row['mastercom_studenti_nomi'] ?? '') ?? '';
                            $linkedStudentsCount = intval($row['mastercom_studenti_count'] ?? 0);
                            ?>
                            <tr>
                                <td><?php echo intval($row['mastercom_id_parente']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($mirrorFullName); ?><br>
                                    <small><?php echo htmlspecialchars($mirrorEmail); ?></small>
                                </td>
                                <td>
                                    <div><strong>Mail:</strong> <?php echo htmlspecialchars($mirrorEmail !== '' ? $mirrorEmail : '-'); ?></div>
                                    <div><strong>Telefono:</strong> <?php echo htmlspecialchars($mirrorPhone !== '' ? $mirrorPhone : '-'); ?></div>
                                    <div><strong>Cellulare:</strong> <?php echo htmlspecialchars($mirrorMobile !== '' ? $mirrorMobile : '-'); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($mirrorAddress !== '' ? $mirrorAddress : '-'); ?></div>
                                    <small>
                                        <?php
                                        $place = trim($mirrorZip . ' ' . $mirrorCity . ($mirrorProvince !== '' ? ' (' . $mirrorProvince . ')' : ''));
                                        echo htmlspecialchars($place !== '' ? $place : '-');
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($linkedStudentsCount > 0): ?>
                                        <strong><?php echo $linkedStudentsCount; ?></strong><br>
                                        <small><?php echo htmlspecialchars($linkedStudents); ?></small>
                                    <?php else: ?>
                                        <span class="label label-default">nessuno studente collegato</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($compare['local'] != null): ?>
                                        <?php echo htmlspecialchars(trim(($compare['local']['cognome'] ?? '') . ' ' . ($compare['local']['nome'] ?? ''))); ?><br>
                                        <small><?php echo htmlspecialchars($compare['local']['email'] ?? ''); ?></small>
                                        <?php if (intval($row['gestore_attivo'] ?? 0) === 0): ?>
                                            <br><span class="label label-default">disattivato in GestOre</span>
                                        <?php endif; ?>
                                        <?php if (intval($row['studenti_locali_count'] ?? 0) === 0): ?>
                                            <br>
                                            <?php if (intval($row['gestore_attivo'] ?? 0) === 0): ?>
                                                <span class="label label-success">senza studenti in GestOre, attivo=0</span>
                                            <?php else: ?>
                                                <span class="label label-warning">senza studenti in GestOre, attivo=1</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="label label-warning">non collegato</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="label label-<?php echo htmlspecialchars($status['class']); ?>">
                                        <?php echo htmlspecialchars($status['label']); ?>
                                    </span>
                                    <?php if (!empty($compare['diffs']) && is_array($compare['diffs'])): ?>
                                        <br><small><?php echo count($compare['diffs']); ?> campi</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn btn-xs btn-default" href="mastercom_parent_compare.php?id=<?php echo intval($row['mastercom_id_parente']); ?>">
                                        Confronta
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><strong>Record visualizzati:</strong> <?php echo intval($visibleCount); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

$filter = trim((string)($_GET['filter'] ?? 'all'));
$allowedFilters = ['all', 'aligned', 'missing', 'issues', 'active_mismatch', 'low', 'medium', 'high'];
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
            local_links.studenti_locali_attivi_count,
            links.studenti_count AS mastercom_studenti_count,
            links.studenti_attivi_mastercom_count,
            links.studenti_ritirati_mastercom_count,
            links.studenti_nomi AS mastercom_studenti_nomi
        FROM mastercom_genitori g
        LEFT JOIN genitori gg ON gg.id = g.id_genitore_gestore
        LEFT JOIN (
            SELECT
                id_genitore,
                COUNT(*) AS studenti_locali_count,
                SUM(CASE WHEN COALESCE(st.attivo, 0) = 1 THEN 1 ELSE 0 END) AS studenti_locali_attivi_count
            FROM genitori_studenti
            LEFT JOIN studente st ON st.id = genitori_studenti.id_studente
            GROUP BY id_genitore
        ) local_links ON local_links.id_genitore = gg.id
        LEFT JOIN (
            SELECT
                gs.mastercom_id_parente,
                COUNT(*) AS studenti_count,
                SUM(CASE
                    WHEN UPPER(REPLACE(mc.nome, '.', '')) LIKE '1RIT%' OR UPPER(REPLACE(mc.nome, '.', '')) LIKE '1RR%' THEN 0
                    ELSE 1
                END) AS studenti_attivi_mastercom_count,
                SUM(CASE
                    WHEN UPPER(REPLACE(mc.nome, '.', '')) LIKE '1RIT%' OR UPPER(REPLACE(mc.nome, '.', '')) LIKE '1RR%' THEN 1
                    ELSE 0
                END) AS studenti_ritirati_mastercom_count,
                GROUP_CONCAT(TRIM(CONCAT(COALESCE(s.cognome, ''), ' ', COALESCE(s.nome, ''))) ORDER BY s.cognome, s.nome SEPARATOR ' | ') AS studenti_nomi
            FROM mastercom_genitori_studenti gs
            INNER JOIN mastercom_studenti s
                ON s.mastercom_id_studente = gs.mastercom_id_studente
            LEFT JOIN mastercom_classi mc
                ON mc.mastercom_id_classe = s.mastercom_id_classe_corrente
            GROUP BY gs.mastercom_id_parente
        ) links ON links.mastercom_id_parente = g.mastercom_id_parente
        ORDER BY g.cognome ASC, g.nome ASC
    ")
    : [];
if (empty($missingTables)) {
    foreach ($rows as &$row) {
        $localParent = mastercomAdminResolveLocalParent($row);
        if ($localParent != null && intval($localParent['id'] ?? 0) > 0) {
            $localParentId = intval($localParent['id']);
            $row['studenti_locali_count'] = (int)dbGetValue("
                SELECT COUNT(*)
                FROM genitori_studenti
                WHERE id_genitore = " . (int)$localParentId . "
            ");
            $row['studenti_locali_attivi_count'] = (int)dbGetValue("
                SELECT COUNT(*)
                FROM genitori_studenti gs
                INNER JOIN studente st ON st.id = gs.id_studente
                WHERE gs.id_genitore = " . (int)$localParentId . "
                  AND COALESCE(st.attivo, 0) = 1
            ");
        }

        $activeMastercomChildren = intval($row['studenti_attivi_mastercom_count'] ?? 0);
        $activeLocalChildren = intval($row['studenti_locali_attivi_count'] ?? 0);
        $row['expected_gestore_attivo'] = ($activeMastercomChildren > 0 || $activeLocalChildren > 0) ? 1 : 0;
    }
    unset($row);
}
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
<?php require_once headerAdminDidatticaPath('../common'); ?>
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
                            <option value="active_mismatch" <?php echo $filter === 'active_mismatch' ? 'selected' : ''; ?>>Incongruenze attivo</option>
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
                            $localParentId = intval($compare['local']['id'] ?? 0);
                            $localStudentsCount = intval($row['studenti_locali_count'] ?? 0);
                            $localActiveStudentsCount = intval($row['studenti_locali_attivi_count'] ?? 0);
                            if ($localParentId > 0) {
                                $localStudentsCount = (int)dbGetValue("
                                    SELECT COUNT(*)
                                    FROM genitori_studenti
                                    WHERE id_genitore = " . (int)$localParentId . "
                                ");
                                $localActiveStudentsCount = (int)dbGetValue("
                                    SELECT COUNT(*)
                                    FROM genitori_studenti gs
                                    INNER JOIN studente st ON st.id = gs.id_studente
                                    WHERE gs.id_genitore = " . (int)$localParentId . "
                                      AND COALESCE(st.attivo, 0) = 1
                                ");
                            }
                            $expectedActive = intval($row['expected_gestore_attivo'] ?? 1);
                            $activeMastercomChildren = intval($row['studenti_attivi_mastercom_count'] ?? 0);
                            $withdrawnMastercomChildren = intval($row['studenti_ritirati_mastercom_count'] ?? 0);
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
                                        <?php if ($withdrawnMastercomChildren > 0): ?>
                                            <br><span class="label label-default"><?php echo $withdrawnMastercomChildren; ?> ritirati</span>
                                        <?php endif; ?>
                                        <?php if ($activeMastercomChildren > 0): ?>
                                            <span class="label label-success"><?php echo $activeMastercomChildren; ?> attivi</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="label label-default">nessuno studente collegato</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($compare['local'] != null): ?>
                                        <?php echo htmlspecialchars(trim(($compare['local']['cognome'] ?? '') . ' ' . ($compare['local']['nome'] ?? ''))); ?><br>
                                        <small><?php echo htmlspecialchars($compare['local']['email'] ?? ''); ?></small>
                                        <?php if ($expectedActive === 0 && intval($compare['local']['attivo'] ?? 0) === 1): ?>
                                            <br><span class="label label-danger">solo figli ritirati: da disattivare</span>
                                        <?php elseif ($expectedActive === 1 && intval($compare['local']['attivo'] ?? 0) !== 1): ?>
                                            <br><span class="label label-danger">ha figli attivi: da riattivare</span>
                                        <?php endif; ?>
                                        <?php if ($expectedActive === 1 && intval($compare['local']['attivo'] ?? 0) === 0): ?>
                                            <br><span class="label label-default">disattivato in GestOre</span>
                                        <?php endif; ?>
                                        <?php if ($localStudentsCount === 0): ?>
                                            <br>
                                            <?php if (intval($compare['local']['attivo'] ?? 0) === 0): ?>
                                                <span class="label label-success">senza studenti in GestOre, attivo=0</span>
                                            <?php else: ?>
                                                <span class="label label-warning">senza studenti in GestOre, attivo=1</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($expectedActive === 0): ?>
                                            <span class="label label-success">assente in GestOre (ok)</span>
                                        <?php else: ?>
                                            <span class="label label-warning">non collegato</span>
                                        <?php endif; ?>
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

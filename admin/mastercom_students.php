<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

$missingTables = mastercomAdminMissingTables(['mastercom_studenti', 'mastercom_classi', 'mastercom_genitori', 'mastercom_genitori_studenti']);
$hasDescrizioneMateriaIntegrativa = empty($missingTables) && mastercomAdminTableColumnExists('mastercom_studenti', 'descrizione_materia_integrativa');
$selectedClassId = intval($_GET['class_id'] ?? 0);
$classRows = empty($missingTables)
    ? dbGetAll("SELECT mastercom_id_classe, nome FROM mastercom_classi ORDER BY nome ASC")
    : [];
$classFilterSql = $selectedClassId > 0 ? (" WHERE s.mastercom_id_classe_corrente = " . $selectedClassId . " ") : '';
$rows = empty($missingTables)
    ? dbGetAll("
        SELECT
            s.*,
            mc.nome AS classe_mastercom,
            st.cognome AS gestore_cognome,
            st.nome AS gestore_nome,
            st.email AS gestore_email,
            st.attivo AS gestore_attivo,
            local_links.genitori_locali_count,
            links.genitori_count AS mastercom_genitori_count,
            links.genitori_nomi AS mastercom_genitori_nomi
        FROM mastercom_studenti s
        LEFT JOIN mastercom_classi mc ON mc.mastercom_id_classe = s.mastercom_id_classe_corrente
        LEFT JOIN studente st ON st.id = s.id_studente_gestore
        LEFT JOIN (
            SELECT
                id_studente,
                COUNT(*) AS genitori_locali_count
            FROM genitori_studenti
            GROUP BY id_studente
        ) local_links ON local_links.id_studente = st.id
        LEFT JOIN (
            SELECT
                gs.mastercom_id_studente,
                COUNT(*) AS genitori_count,
                GROUP_CONCAT(TRIM(CONCAT(COALESCE(g.cognome, ''), ' ', COALESCE(g.nome, ''))) ORDER BY g.cognome, g.nome SEPARATOR ' | ') AS genitori_nomi
            FROM mastercom_genitori_studenti gs
            INNER JOIN mastercom_genitori g
                ON g.mastercom_id_parente = gs.mastercom_id_parente
            GROUP BY gs.mastercom_id_studente
        ) links ON links.mastercom_id_studente = s.mastercom_id_studente
        $classFilterSql
        ORDER BY s.cognome ASC, s.nome ASC
    ")
    : [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Studenti</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-education"></span>&emsp;Studenti MasterCom</div>
        <div class="panel-body">
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">Mancano tabelle: <?php echo htmlspecialchars(implode(', ', $missingTables)); ?>.</div>
            <?php else: ?>
                <form method="get" action="mastercom_students.php" class="form-inline" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="class_id">Classe&nbsp;</label>
                        <select name="class_id" id="class_id" class="form-control" onchange="this.form.submit()">
                            <option value="0">Tutte le classi</option>
                            <?php foreach ($classRows as $classRow): ?>
                                <option value="<?php echo intval($classRow['mastercom_id_classe']); ?>" <?php echo $selectedClassId === intval($classRow['mastercom_id_classe']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($classRow['nome'] ?? '') . ' [' . ($classRow['mastercom_id_classe'] ?? '') . ']'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($selectedClassId > 0): ?>
                        <a href="mastercom_presence.php?class_id=<?php echo $selectedClassId; ?>" class="btn btn-info">Snapshot presenze</a>
                    <?php endif; ?>
                    <a href="mastercom_students.php" class="btn btn-default">Mostra tutti</a>
                </form>

                <div class="alert alert-info">
                    Record visualizzati: <strong><?php echo count($rows); ?></strong>
                    <?php if ($selectedClassId > 0): ?>
                        nella classe selezionata.
                    <?php else: ?>
                        su tutte le classi sincronizzate.
                    <?php endif; ?>
                </div>

                <table class="table table-striped table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th style="text-align: center;">ID MasterCom</th>
                            <th>Studente</th>
                            <th style="text-align: center;">Classe</th>
                            <th style="text-align: center;">Religione</th>
                            <th>Attivit&agrave; alternativa</th>
                            <th>Genitori MasterCom</th>
                            <th>GestOre</th>
                            <th style="text-align: center;">Esito</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $compare = mastercomAdminStudentDiffs($row); ?>
                            <?php $status = mastercomAdminDiffStatus($compare); ?>
                            <?php
                            $linkedParents = mastercomAdminCleanText($row['mastercom_genitori_nomi'] ?? '') ?? '';
                            $linkedParentsCount = intval($row['mastercom_genitori_count'] ?? 0);
                            $rawStudent = json_decode((string)($row['raw_json'] ?? ''), true);
                            $rawCsvExport = is_array($rawStudent['_csv_export'] ?? null) ? $rawStudent['_csv_export'] : [];
                            ?>
                            <tr>
                                <td style="text-align: center;"><?php echo intval($row['mastercom_id_studente']); ?></td>
                                <td>
                                    <?php
                                    $photoFile = trim((string)($row['foto'] ?? ''));
                                    $photoUrl = $photoFile !== ''
                                        ? ($__application_base_path . '/common/mastercom/photo.php?proxy=1&file=' . urlencode($photoFile))
                                        : '';
                                    ?>
                                    <?php if ($photoUrl !== ''): ?>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img
                                                src="<?php echo htmlspecialchars($photoUrl); ?>"
                                                alt="Foto studente"
                                                style="width: 42px; height: 42px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc;"
                                                loading="lazy"
                                            >
                                            <div>
                                                <?php echo htmlspecialchars(trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''))); ?><br>
                                                <small><?php echo htmlspecialchars($row['email1'] ?? ''); ?></small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars(trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''))); ?><br>
                                        <small><?php echo htmlspecialchars($row['email1'] ?? ''); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;"><?php echo htmlspecialchars($row['classe_mastercom'] ?? ''); ?></td>
                                <td style="text-align: center;">
                                    <?php
                                    $esoneroReligione = $row['esonero_religione'];
                                    if (($esoneroReligione === null || $esoneroReligione === '') && array_key_exists('esonero_religione', $rawCsvExport)) {
                                        $esoneroReligione = $rawCsvExport['esonero_religione'];
                                    }
                                    if ($esoneroReligione === null || $esoneroReligione === '') {
                                        echo '<span class="label label-default">n/d</span>';
                                    } elseif (intval($esoneroReligione) === 1) {
                                        echo '<span class="label label-warning">NO</span>';
                                    } else {
                                        echo '<span class="label label-success">SI</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $materiaIntegrativa = $hasDescrizioneMateriaIntegrativa
                                        ? (mastercomAdminCleanText($row['descrizione_materia_integrativa'] ?? '') ?? '')
                                        : '';
                                    if ($materiaIntegrativa === '') {
                                        $materiaIntegrativa = mastercomAdminCleanText($rawCsvExport['descrizione_materia_integrativa'] ?? '') ?? '';
                                    }
                                    ?>
                                    <?php if ($materiaIntegrativa !== ''): ?>
                                        <?php echo htmlspecialchars($materiaIntegrativa); ?>
                                    <?php elseif (($row['esonero_religione'] ?? null) !== null && intval($row['esonero_religione']) === 0): ?>
                                        <span class="label label-default">non necessaria</span>
                                    <?php else: ?>
                                        <span class="label label-default">n/d</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($linkedParentsCount > 0): ?>
                                        <strong><?php echo $linkedParentsCount; ?></strong><br>
                                        <small><?php echo htmlspecialchars($linkedParents); ?></small>
                                    <?php else: ?>
                                        <span class="label label-default">nessun genitore collegato</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($compare['local'] != null): ?>
                                        <?php echo htmlspecialchars(trim(($compare['local']['cognome'] ?? '') . ' ' . ($compare['local']['nome'] ?? ''))); ?><br>
                                        <small><?php echo htmlspecialchars($compare['local']['email'] ?? ''); ?></small>
                                        <?php if (intval($row['genitori_locali_count'] ?? 0) === 0): ?>
                                            <br>
                                            <?php if (intval($row['gestore_attivo'] ?? 0) === 0): ?>
                                                <span class="label label-success">senza genitori in GestOre, attivo=0</span>
                                            <?php else: ?>
                                                <span class="label label-warning">senza genitori in GestOre, attivo=1</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="label label-warning">non collegato</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="label label-<?php echo htmlspecialchars($status['class']); ?>">
                                        <?php echo htmlspecialchars($status['label']); ?>
                                    </span>
                                    <?php if (!empty($compare['diffs']) && is_array($compare['diffs'])): ?>
                                        <br><small><?php echo count($compare['diffs']); ?> campi</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn btn-xs btn-default" href="mastercom_student_compare.php?id=<?php echo intval($row['mastercom_id_studente']); ?>">
                                        Confronta
                                    </a>
                                    <a class="btn btn-xs btn-info" href="mastercom_student_absences.php?student_id=<?php echo intval($row['mastercom_id_studente']); ?>">
                                        Assenze
                                    </a>
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

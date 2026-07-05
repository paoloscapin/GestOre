<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function classiAdminH($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function classiAdminColumn(string $table, string $column): bool
{
    return mastercomAdminTableColumnExists($table, $column);
}

function classiAdminSelected($a, $b): string
{
    return intval($a) === intval($b) ? 'selected' : '';
}

function classiAdminChecked($value): string
{
    return intval($value) === 1 ? 'checked' : '';
}

function classiAdminEnsureTables(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS `classi_anno_scolastico` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_classe` INT NOT NULL,
            `id_anno_scolastico` INT NOT NULL,
            `attiva` TINYINT(1) NOT NULL DEFAULT 1,
            `is_tablet` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_classi_anno_classe_anno` (`id_classe`, `id_anno_scolastico`),
            KEY `idx_classi_anno_anno` (`id_anno_scolastico`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    if (!classiAdminColumn('classi_anno_scolastico', 'is_tablet')) {
        dbExec("ALTER TABLE classi_anno_scolastico ADD COLUMN is_tablet TINYINT(1) NOT NULL DEFAULT 0 AFTER attiva");
    }

    dbExec("
        CREATE TABLE IF NOT EXISTS `classi_articolate` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(100) NOT NULL,
            `id_anno_scolastico` INT NOT NULL,
            `attiva` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_classi_articolate_anno` (`id_anno_scolastico`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS `classi_articolate_classi` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_articolata` INT NOT NULL,
            `id_classe` INT NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_articolata_classe` (`id_articolata`, `id_classe`),
            KEY `idx_articolata_classi_classe` (`id_classe`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function classiAdminSaveCoordinator(int $classeId, int $annoId, int $docenteId): void
{
    if ($classeId <= 0 || $annoId <= 0 || !mastercomAdminTableExists('coordinatori')) {
        return;
    }

    dbExec("DELETE FROM coordinatori WHERE id_classe = " . dbI($classeId) . " AND id_anno_scolastico = " . dbI($annoId));
    if ($docenteId > 0) {
        dbExec("
            INSERT INTO coordinatori (id_classe, id_docente, id_anno_scolastico)
            VALUES (" . dbI($classeId) . ", " . dbI($docenteId) . ", " . dbI($annoId) . ")
        ");
    }
}

function classiAdminBuildFields(string $classe, int $anno, int $primoIndirizzo, int $secondoIndirizzo, ?int $attiva = null): array
{
    $fields = [
        'classe' => dbQ($classe),
    ];
    if (classiAdminColumn('classi', 'anno')) {
        $fields['anno'] = dbI($anno);
    }
    if (classiAdminColumn('classi', 'id_primo_indirizzo')) {
        $fields['id_primo_indirizzo'] = dbI(max(0, $primoIndirizzo));
    }
    if (classiAdminColumn('classi', 'id_secondo_indirizzo')) {
        $fields['id_secondo_indirizzo'] = dbI(max(0, $secondoIndirizzo));
    }
    if ($attiva !== null && classiAdminColumn('classi', 'attiva')) {
        $fields['attiva'] = dbI($attiva);
    }
    return $fields;
}

function classiAdminUpdateClass(int $id, array $fields): void
{
    $set = [];
    foreach ($fields as $field => $value) {
        $set[] = "`$field` = $value";
    }
    if (!empty($set)) {
        dbExec("UPDATE classi SET " . implode(', ', $set) . " WHERE id = " . dbI($id) . " LIMIT 1");
    }
}

function classiAdminSaveClassYear(int $classeId, int $annoId, int $attiva, int $isTablet = 0): void
{
    if ($classeId <= 0 || $annoId <= 0) {
        return;
    }

    $existingId = intval(dbGetValue("
        SELECT id
        FROM classi_anno_scolastico
        WHERE id_classe = " . dbI($classeId) . "
          AND id_anno_scolastico = " . dbI($annoId) . "
        LIMIT 1
    "));

    if ($existingId > 0) {
        $set = [
            'attiva = ' . dbI($attiva),
            'is_tablet = ' . dbI($isTablet),
        ];
        if (classiAdminColumn('classi_anno_scolastico', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }
        dbExec("UPDATE classi_anno_scolastico SET " . implode(', ', $set) . " WHERE id = " . dbI($existingId) . " LIMIT 1");
    } else {
        $fields = [
            'id_classe' => dbI($classeId),
            'id_anno_scolastico' => dbI($annoId),
            'attiva' => dbI($attiva),
            'is_tablet' => dbI($isTablet),
        ];
        if (classiAdminColumn('classi_anno_scolastico', 'created_at')) {
            $fields['created_at'] = 'NOW()';
        }
        if (classiAdminColumn('classi_anno_scolastico', 'updated_at')) {
            $fields['updated_at'] = 'NOW()';
        }
        dbExec("INSERT INTO classi_anno_scolastico (`" . implode('`, `', array_keys($fields)) . "`) VALUES (" . implode(', ', array_values($fields)) . ")");
    }
}

function classiAdminSaveArticolata(int $id, string $nome, int $annoId, int $attiva, array $classIds): int
{
    if ($nome === '') {
        throw new Exception('Il nome della classe articolata e obbligatorio.');
    }
    if ($annoId <= 0) {
        throw new Exception('Anno scolastico non valido.');
    }

    $cleanClassIds = [];
    foreach ($classIds as $classId) {
        $classId = intval($classId);
        if ($classId > 0) {
            $cleanClassIds[$classId] = $classId;
        }
    }
    if (empty($cleanClassIds)) {
        throw new Exception('Seleziona almeno una classe da includere nella classe articolata.');
    }

    if ($id > 0) {
        $set = [
            'nome = ' . dbQ($nome),
            'id_anno_scolastico = ' . dbI($annoId),
            'attiva = ' . dbI($attiva),
        ];
        if (classiAdminColumn('classi_articolate', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }
        dbExec("UPDATE classi_articolate SET " . implode(', ', $set) . " WHERE id = " . dbI($id) . " LIMIT 1");
    } else {
        $fields = [
            'nome' => dbQ($nome),
            'id_anno_scolastico' => dbI($annoId),
            'attiva' => dbI($attiva),
        ];
        if (classiAdminColumn('classi_articolate', 'created_at')) {
            $fields['created_at'] = 'NOW()';
        }
        if (classiAdminColumn('classi_articolate', 'updated_at')) {
            $fields['updated_at'] = 'NOW()';
        }
        dbExec("INSERT INTO classi_articolate (`" . implode('`, `', array_keys($fields)) . "`) VALUES (" . implode(', ', array_values($fields)) . ")");
        $id = intval(dbGetValue("SELECT LAST_INSERT_ID()"));
    }

    dbExec("DELETE FROM classi_articolate_classi WHERE id_articolata = " . dbI($id));
    foreach ($cleanClassIds as $classId) {
        dbExec("
            INSERT INTO classi_articolate_classi (id_articolata, id_classe)
            VALUES (" . dbI($id) . ", " . dbI($classId) . ")
        ");
    }

    return $id;
}

function classiAdminDeleteArticolata(int $id): void
{
    if ($id <= 0) {
        throw new Exception('Classe articolata non valida.');
    }
    dbExec("DELETE FROM classi_articolate_classi WHERE id_articolata = " . dbI($id));
    dbExec("DELETE FROM classi_articolate WHERE id = " . dbI($id) . " LIMIT 1");
}

function classiAdminBackfillClassYearsFromStudenti(): int
{
    if (!mastercomAdminTableExists('studente_frequenta')) {
        return 0;
    }

    global $__con;

    dbExec("
        INSERT INTO classi_anno_scolastico (id_classe, id_anno_scolastico, attiva, is_tablet, created_at, updated_at)
        SELECT DISTINCT
            sf.id_classe,
            sf.id_anno_scolastico,
            1,
            0,
            NOW(),
            NOW()
        FROM studente_frequenta sf
        INNER JOIN classi c ON c.id = sf.id_classe
        LEFT JOIN classi_anno_scolastico cas
            ON cas.id_classe = sf.id_classe
           AND cas.id_anno_scolastico = sf.id_anno_scolastico
        WHERE sf.id_classe > 0
          AND sf.id_anno_scolastico > 0
          AND cas.id IS NULL
    ");

    return intval(mysqli_affected_rows($__con));
}

classiAdminEnsureTables();

$message = '';
$error = '';
$annoCorrenteId = intval($__anno_scolastico_corrente_id ?? 0);
$action = trim((string)($_POST['action'] ?? ''));

$anniScolastici = mastercomAdminTableExists('anno_scolastico')
    ? (dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY anno DESC, id DESC") ?: [])
    : [];
if (empty($anniScolastici) && $annoCorrenteId > 0) {
    $anniScolastici[] = ['id' => $annoCorrenteId, 'anno' => ($__anno_scolastico_corrente_anno ?? $annoCorrenteId)];
}

$selectedAnnoId = intval($_REQUEST['anno_id'] ?? $annoCorrenteId);
$annoValido = false;
$selectedAnnoLabel = (string)$selectedAnnoId;
foreach ($anniScolastici as $annoRow) {
    if (intval($annoRow['id']) === $selectedAnnoId) {
        $annoValido = true;
        $selectedAnnoLabel = (string)($annoRow['anno'] ?? $selectedAnnoId);
        break;
    }
}
if (!$annoValido && !empty($anniScolastici)) {
    $selectedAnnoId = intval($anniScolastici[0]['id']);
    $selectedAnnoLabel = (string)($anniScolastici[0]['anno'] ?? $selectedAnnoId);
}
if ($selectedAnnoId <= 0) {
    $selectedAnnoId = $annoCorrenteId;
}

try {
    $backfilledClassYears = classiAdminBackfillClassYearsFromStudenti();
    if ($backfilledClassYears > 0) {
        $message = 'Storico classi aggiornato da studente_frequenta: ' . $backfilledClassYears . ' abbinamenti classe/anno recuperati.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_all_classes') {
        $classiPost = is_array($_POST['classi'] ?? null) ? $_POST['classi'] : [];
        $updated = 0;
        foreach ($classiPost as $idRaw => $classData) {
            $id = intval($idRaw);
            if ($id <= 0 || !is_array($classData)) {
                continue;
            }

            $classe = trim((string)($classData['classe'] ?? ''));
            $anno = intval($classData['anno'] ?? 0);
            $primoIndirizzo = intval($classData['id_primo_indirizzo'] ?? 0);
            $secondoIndirizzo = intval($classData['id_secondo_indirizzo'] ?? 0);
            $attivaAnno = isset($classData['attiva_anno']) ? 1 : 0;
            $isTablet = isset($classData['is_tablet']) ? 1 : 0;
            $coordinatoreId = intval($classData['coordinatore_id'] ?? 0);

            if ($classe === '') {
                throw new Exception('Il nome classe e obbligatorio per tutte le righe.');
            }
            if ($anno <= 0 && preg_match('/^(\d+)/', $classe, $matches)) {
                $anno = intval($matches[1]);
            }

            $attivaLegacy = $selectedAnnoId === $annoCorrenteId ? $attivaAnno : null;
            classiAdminUpdateClass($id, classiAdminBuildFields($classe, $anno, $primoIndirizzo, $secondoIndirizzo, $attivaLegacy));
            classiAdminSaveClassYear($id, $selectedAnnoId, $attivaAnno, $isTablet);
            classiAdminSaveCoordinator($id, $selectedAnnoId, $coordinatoreId);
            $updated++;
        }
        $message = 'Classi aggiornate per l\'anno scolastico ' . $selectedAnnoLabel . ': ' . $updated . '.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_class') {
        $classe = trim((string)($_POST['classe'] ?? ''));
        $anno = intval($_POST['anno'] ?? 0);
        $primoIndirizzo = intval($_POST['id_primo_indirizzo'] ?? 0);
        $secondoIndirizzo = intval($_POST['id_secondo_indirizzo'] ?? 0);
        $attivaAnno = isset($_POST['attiva_anno']) ? 1 : 0;
        $isTablet = isset($_POST['is_tablet']) ? 1 : 0;
        $coordinatoreId = intval($_POST['coordinatore_id'] ?? 0);

        if ($classe === '') {
            throw new Exception('Il nome classe e obbligatorio.');
        }
        if ($anno <= 0 && preg_match('/^(\d+)/', $classe, $matches)) {
            $anno = intval($matches[1]);
        }

        $attivaLegacy = $selectedAnnoId === $annoCorrenteId ? $attivaAnno : null;
        $fields = classiAdminBuildFields($classe, $anno, $primoIndirizzo, $secondoIndirizzo, $attivaLegacy);
        dbExec("INSERT INTO classi (`" . implode('`, `', array_keys($fields)) . "`) VALUES (" . implode(', ', array_values($fields)) . ")");
        $newId = intval(dbGetValue("SELECT LAST_INSERT_ID()"));
        classiAdminSaveClassYear($newId, $selectedAnnoId, $attivaAnno, $isTablet);
        classiAdminSaveCoordinator($newId, $selectedAnnoId, $coordinatoreId);
        $message = 'Classe creata per l\'anno scolastico ' . $selectedAnnoLabel . '.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_articolata') {
        classiAdminSaveArticolata(
            intval($_POST['id'] ?? 0),
            trim((string)($_POST['nome'] ?? '')),
            $selectedAnnoId,
            isset($_POST['attiva']) ? 1 : 0,
            is_array($_POST['classi_ids'] ?? null) ? $_POST['classi_ids'] : []
        );
        $message = 'Classe articolata salvata.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_articolata') {
        classiAdminDeleteArticolata(intval($_POST['id'] ?? 0));
        $message = 'Classe articolata cancellata.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$indirizzi = mastercomAdminTableExists('indirizzo') ? (dbGetAll("SELECT * FROM indirizzo ORDER BY nome ASC") ?: []) : [];
$docenti = dbGetAll("SELECT id, cognome, nome FROM docente WHERE attivo = 1 ORDER BY cognome ASC, nome ASC") ?: [];

$classRows = dbGetAll("
    SELECT
        c.*,
        i1.nome AS primo_indirizzo_nome,
        i2.nome AS secondo_indirizzo_nome,
        COALESCE(cas.attiva, CASE WHEN " . dbI($selectedAnnoId) . " = " . dbI($annoCorrenteId) . " THEN c.attiva ELSE 0 END) AS attiva_anno,
        COALESCE(cas.is_tablet, 0) AS is_tablet,
        MIN(coord.id_docente) AS coordinatore_id
    FROM classi c
    LEFT JOIN classi_anno_scolastico cas
        ON cas.id_classe = c.id
       AND cas.id_anno_scolastico = " . dbI($selectedAnnoId) . "
    LEFT JOIN indirizzo i1 ON i1.id = c.id_primo_indirizzo
    LEFT JOIN indirizzo i2 ON i2.id = c.id_secondo_indirizzo
    LEFT JOIN coordinatori coord
        ON coord.id_classe = c.id
       AND coord.id_anno_scolastico = " . dbI($selectedAnnoId) . "
    GROUP BY c.id
    ORDER BY attiva_anno DESC, c.classe ASC
") ?: [];

$articolate = dbGetAll("
    SELECT
        ca.*,
        GROUP_CONCAT(cac.id_classe ORDER BY c.classe SEPARATOR ',') AS classi_ids,
        GROUP_CONCAT(c.classe ORDER BY c.classe SEPARATOR ' / ') AS classi_nomi
    FROM classi_articolate ca
    LEFT JOIN classi_articolate_classi cac ON cac.id_articolata = ca.id
    LEFT JOIN classi c ON c.id = cac.id_classe
    WHERE ca.id_anno_scolastico = " . dbI($selectedAnnoId) . "
    GROUP BY ca.id
    ORDER BY ca.attiva DESC, ca.nome ASC
") ?: [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestione classi</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .classi-table td, .classi-table th { vertical-align: middle !important; }
        .classi-table select, .classi-table input[type="text"], .classi-table input[type="number"] { min-width: 100px; }
        .classi-actions { white-space: nowrap; text-align: center; }
        .classi-year-bar { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; margin-bottom: 18px; }
        .classi-year-bar .form-group { margin-bottom: 0; }
        .classi-articolata-card { border: 1px solid #d7d7d7; border-radius: 4px; padding: 12px; margin-bottom: 10px; background: #fff; }
        .classi-articolata-card .form-group { margin-right: 8px; }
        .classi-articolata-select { min-width: 260px; min-height: 92px; }
        .classi-help { color: #555; font-size: 12px; margin-top: 4px; }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-yellow4">
        <div class="panel-heading"><span class="glyphicon glyphicon-th-large"></span>&emsp;Gestione classi GestOre</div>
        <div class="panel-body">
            <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo classiAdminH($message); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo classiAdminH($error); ?></div><?php endif; ?>

            <form method="get" class="classi-year-bar">
                <div class="form-group">
                    <label>Anno scolastico</label>
                    <select name="anno_id" class="form-control input-sm">
                        <?php foreach ($anniScolastici as $annoRow): ?>
                            <option value="<?php echo intval($annoRow['id']); ?>" <?php echo classiAdminSelected($selectedAnnoId, $annoRow['id']); ?>>
                                <?php echo classiAdminH($annoRow['anno'] ?? $annoRow['id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><span class="glyphicon glyphicon-search"></span> Visualizza</button>
                <div class="classi-help">
                    Le classi non vengono cancellate: si abilita o disabilita la loro presenza nell'anno scolastico selezionato.
                </div>
            </form>

            <div class="alert alert-info">
                Coordinatori e classi articolate sono riferiti all'anno scolastico selezionato:
                <strong><?php echo classiAdminH($selectedAnnoLabel); ?></strong>.
                Per l'anno corrente viene aggiornata anche l'attivazione storica usata dalle pagine gia esistenti.
                Le classi marcate <strong>Tablet</strong> valgono solo per l'anno scolastico selezionato e vengono usate dalla formazione classi dell'anno target.
            </div>

            <h4>Nuova classe</h4>
            <form method="post" class="form-inline" style="margin-bottom:18px;">
                <input type="hidden" name="action" value="save_class">
                <input type="hidden" name="anno_id" value="<?php echo intval($selectedAnnoId); ?>">
                <input type="text" name="classe" class="form-control" placeholder="Classe, es. 3CTA" required>
                <input type="number" name="anno" class="form-control" placeholder="Anno" min="1" max="5">
                <?php classiAdminRenderIndirizzoSelect('id_primo_indirizzo', $indirizzi, 0, 'Primo indirizzo'); ?>
                <?php classiAdminRenderIndirizzoSelect('id_secondo_indirizzo', $indirizzi, 0, 'Secondo indirizzo'); ?>
                <?php classiAdminRenderDocenteSelect('coordinatore_id', $docenti, 0, 'Coordinatore'); ?>
                <label class="checkbox-inline"><input type="checkbox" name="attiva_anno" value="1" checked> attiva in <?php echo classiAdminH($selectedAnnoLabel); ?></label>
                <label class="checkbox-inline"><input type="checkbox" name="is_tablet" value="1"> classe tablet</label>
                <button type="submit" class="btn btn-success"><span class="glyphicon glyphicon-plus"></span> Aggiungi</button>
            </form>

            <form method="post" onsubmit="return confirm('Salvare tutte le modifiche alle classi per l\'anno scolastico selezionato?');">
                <input type="hidden" name="action" value="save_all_classes">
                <input type="hidden" name="anno_id" value="<?php echo intval($selectedAnnoId); ?>">
                <div style="margin-bottom:10px;" class="text-right">
                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-floppy-disk"></span> Salva tutte le modifiche
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped classi-table">
                        <thead>
                        <tr>
                            <th class="text-center">Classe</th>
                            <th class="text-center">Anno</th>
                            <th class="text-center">Primo indirizzo</th>
                            <th class="text-center">Secondo indirizzo</th>
                            <th class="text-center">Coordinatore</th>
                            <th class="text-center">Attiva nell'anno</th>
                            <th class="text-center">Tablet<br><small><?php echo classiAdminH($selectedAnnoLabel); ?></small></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($classRows as $row): ?>
                            <?php $rowId = intval($row['id']); ?>
                            <tr>
                                <td><input type="text" name="classi[<?php echo $rowId; ?>][classe]" class="form-control input-sm" value="<?php echo classiAdminH($row['classe'] ?? ''); ?>" required></td>
                                <td><input type="number" name="classi[<?php echo $rowId; ?>][anno]" class="form-control input-sm" value="<?php echo intval($row['anno'] ?? 0); ?>" min="1" max="5"></td>
                                <td><?php classiAdminRenderIndirizzoSelect('classi[' . $rowId . '][id_primo_indirizzo]', $indirizzi, intval($row['id_primo_indirizzo'] ?? 0)); ?></td>
                                <td><?php classiAdminRenderIndirizzoSelect('classi[' . $rowId . '][id_secondo_indirizzo]', $indirizzi, intval($row['id_secondo_indirizzo'] ?? 0)); ?></td>
                                <td><?php classiAdminRenderDocenteSelect('classi[' . $rowId . '][coordinatore_id]', $docenti, intval($row['coordinatore_id'] ?? 0)); ?></td>
                                <td class="text-center"><input type="checkbox" name="classi[<?php echo $rowId; ?>][attiva_anno]" value="1" <?php echo classiAdminChecked($row['attiva_anno'] ?? 0); ?>></td>
                                <td class="text-center"><input type="checkbox" name="classi[<?php echo $rowId; ?>][is_tablet]" value="1" <?php echo classiAdminChecked($row['is_tablet'] ?? 0); ?> title="Vale per l'anno scolastico selezionato"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:10px;" class="text-right">
                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-floppy-disk"></span> Salva tutte le modifiche
                    </button>
                </div>
            </form>

            <hr>
            <h4>Classi articolate - <?php echo classiAdminH($selectedAnnoLabel); ?></h4>
            <div class="classi-help" style="margin-bottom:10px;">
                Una classe articolata raggruppa piu classi reali dello stesso anno scolastico. I programmi la usano gia tramite queste tabelle.
            </div>

            <div class="classi-articolata-card">
                <form method="post" class="form-inline">
                    <input type="hidden" name="action" value="save_articolata">
                    <input type="hidden" name="anno_id" value="<?php echo intval($selectedAnnoId); ?>">
                    <input type="text" name="nome" class="form-control" placeholder="Nome articolata, es. 3CAT/3LEG" required>
                    <?php classiAdminRenderClassiMultiple('classi_ids[]', $classRows, []); ?>
                    <label class="checkbox-inline"><input type="checkbox" name="attiva" value="1" checked> attiva</label>
                    <button type="submit" class="btn btn-success"><span class="glyphicon glyphicon-plus"></span> Aggiungi articolata</button>
                </form>
            </div>

            <?php foreach ($articolate as $art): ?>
                <?php
                $artId = intval($art['id']);
                $selectedClassIds = array_filter(array_map('intval', explode(',', (string)($art['classi_ids'] ?? ''))));
                ?>
                <div class="classi-articolata-card">
                    <form method="post" class="form-inline" style="display:inline-block; margin-right:8px;">
                        <input type="hidden" name="action" value="save_articolata">
                        <input type="hidden" name="anno_id" value="<?php echo intval($selectedAnnoId); ?>">
                        <input type="hidden" name="id" value="<?php echo $artId; ?>">
                        <input type="text" name="nome" class="form-control" value="<?php echo classiAdminH($art['nome'] ?? ''); ?>" required>
                        <?php classiAdminRenderClassiMultiple('classi_ids[]', $classRows, $selectedClassIds); ?>
                        <label class="checkbox-inline"><input type="checkbox" name="attiva" value="1" <?php echo classiAdminChecked($art['attiva'] ?? 0); ?>> attiva</label>
                        <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk"></span> Salva</button>
                    </form>
                    <form method="post" style="display:inline-block;" onsubmit="return confirm('Cancellare questa classe articolata?');">
                        <input type="hidden" name="action" value="delete_articolata">
                        <input type="hidden" name="anno_id" value="<?php echo intval($selectedAnnoId); ?>">
                        <input type="hidden" name="id" value="<?php echo $artId; ?>">
                        <button type="submit" class="btn btn-danger"><span class="glyphicon glyphicon-trash"></span></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
<?php

function classiAdminRenderIndirizzoSelect(string $name, array $rows, int $selectedId, string $placeholder = '-'): void
{
    echo '<select name="' . classiAdminH($name) . '" class="form-control input-sm">';
    echo '<option value="0">' . classiAdminH($placeholder) . '</option>';
    foreach ($rows as $row) {
        echo '<option value="' . intval($row['id']) . '" ' . classiAdminSelected($selectedId, $row['id']) . '>' . classiAdminH($row['nome'] ?? '') . '</option>';
    }
    echo '</select>';
}

function classiAdminRenderDocenteSelect(string $name, array $rows, int $selectedId, string $placeholder = '-'): void
{
    echo '<select name="' . classiAdminH($name) . '" class="form-control input-sm">';
    echo '<option value="0">' . classiAdminH($placeholder) . '</option>';
    foreach ($rows as $row) {
        $label = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
        echo '<option value="' . intval($row['id']) . '" ' . classiAdminSelected($selectedId, $row['id']) . '>' . classiAdminH($label) . '</option>';
    }
    echo '</select>';
}

function classiAdminRenderClassiMultiple(string $name, array $rows, array $selectedIds): void
{
    $selectedMap = array_fill_keys(array_map('intval', $selectedIds), true);
    echo '<select name="' . classiAdminH($name) . '" class="form-control input-sm classi-articolata-select" multiple>';
    foreach ($rows as $row) {
        $id = intval($row['id'] ?? 0);
        $label = (string)($row['classe'] ?? '');
        if (intval($row['attiva_anno'] ?? 0) !== 1) {
            $label .= ' (non attiva)';
        }
        echo '<option value="' . $id . '" ' . (isset($selectedMap[$id]) ? 'selected' : '') . '>' . classiAdminH($label) . '</option>';
    }
    echo '</select>';
}

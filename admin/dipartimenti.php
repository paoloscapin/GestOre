<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function dipAdminH($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dipAdminSelected($a, $b): string
{
    return intval($a) === intval($b) ? 'selected' : '';
}

function dipAdminChecked($value): string
{
    return intval($value) === 1 ? 'checked' : '';
}

function dipAdminColumn(string $table, string $column): bool
{
    return mastercomAdminTableColumnExists($table, $column);
}

function dipAdminSaveCoordinator(int $dipartimentoId, int $annoId, int $docenteId): void
{
    if ($dipartimentoId <= 0 || $annoId <= 0 || !mastercomAdminTableExists('coordinatori_dipartimento')) {
        return;
    }

    dbExec("DELETE FROM coordinatori_dipartimento WHERE id_dipartimento = " . dbI($dipartimentoId) . " AND id_anno_scolastico = " . dbI($annoId));
    if ($docenteId > 0) {
        dbExec("
            INSERT INTO coordinatori_dipartimento (id_dipartimento, id_docente, id_anno_scolastico)
            VALUES (" . dbI($dipartimentoId) . ", " . dbI($docenteId) . ", " . dbI($annoId) . ")
        ");
    }
}

$message = '';
$error = '';
$annoCorrenteId = intval($__anno_scolastico_corrente_id ?? 0);
$hasDipartimenti = mastercomAdminTableExists('dipartimenti');
$hasCoordinatori = mastercomAdminTableExists('coordinatori_dipartimento');
$action = trim((string)($_POST['action'] ?? ''));

try {
    if ($hasDipartimenti && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['save_department', 'delete_department'], true)) {
        $id = intval($_POST['id'] ?? 0);
        if ($action === 'delete_department') {
            if ($id <= 0) {
                throw new Exception('Dipartimento non valido.');
            }
            if ($hasCoordinatori) {
                dbExec("DELETE FROM coordinatori_dipartimento WHERE id_dipartimento = " . dbI($id));
            }
            dbExec("DELETE FROM dipartimenti WHERE id = " . dbI($id) . " LIMIT 1");
            $message = 'Dipartimento cancellato.';
        } else {
            $nome = trim((string)($_POST['nome'] ?? ''));
            $sigla = trim((string)($_POST['sigla'] ?? ''));
            $attivo = isset($_POST['attivo']) ? 1 : 0;
            $coordinatoreId = intval($_POST['coordinatore_id'] ?? 0);

            if ($nome === '') {
                throw new Exception('Il nome dipartimento e obbligatorio.');
            }

            $fields = ['nome' => dbQ($nome)];
            if (dipAdminColumn('dipartimenti', 'sigla')) {
                $fields['sigla'] = dbQ($sigla);
            }
            if (dipAdminColumn('dipartimenti', 'attivo')) {
                $fields['attivo'] = dbI($attivo);
            }

            if ($id > 0) {
                $set = [];
                foreach ($fields as $field => $value) {
                    $set[] = "`$field` = $value";
                }
                dbExec("UPDATE dipartimenti SET " . implode(', ', $set) . " WHERE id = " . dbI($id) . " LIMIT 1");
                dipAdminSaveCoordinator($id, $annoCorrenteId, $coordinatoreId);
                $message = 'Dipartimento aggiornato.';
            } else {
                dbExec("INSERT INTO dipartimenti (`" . implode('`, `', array_keys($fields)) . "`) VALUES (" . implode(', ', array_values($fields)) . ")");
                $newId = intval(dbGetValue("SELECT LAST_INSERT_ID()"));
                dipAdminSaveCoordinator($newId, $annoCorrenteId, $coordinatoreId);
                $message = 'Dipartimento creato.';
            }
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$docenti = dbGetAll("SELECT id, cognome, nome FROM docente WHERE attivo = 1 ORDER BY cognome ASC, nome ASC") ?: [];
$rows = [];
if ($hasDipartimenti) {
    $rows = dbGetAll("
        SELECT
            dep.*,
            coord.id_docente AS coordinatore_id,
            d.cognome AS coordinatore_cognome,
            d.nome AS coordinatore_nome
        FROM dipartimenti dep
        LEFT JOIN coordinatori_dipartimento coord
            ON coord.id_dipartimento = dep.id
           AND coord.id_anno_scolastico = " . dbI($annoCorrenteId) . "
        LEFT JOIN docente d ON d.id = coord.id_docente
        ORDER BY dep.nome ASC
    ") ?: [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gestione dipartimenti</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .dip-table td, .dip-table th { vertical-align: middle !important; }
        .dip-actions { white-space: nowrap; text-align: center; }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-yellow4">
        <div class="panel-heading"><span class="glyphicon glyphicon-list"></span>&emsp;Gestione dipartimenti</div>
        <div class="panel-body">
            <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo dipAdminH($message); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo dipAdminH($error); ?></div><?php endif; ?>
            <?php if (!$hasDipartimenti): ?>
                <div class="alert alert-warning">Tabella <code>dipartimenti</code> non presente.</div>
            <?php else: ?>
                <div class="alert alert-info">
                    I coordinatori di dipartimento sono salvati per l'anno scolastico corrente:
                    <strong><?php echo dipAdminH($__anno_scolastico_corrente_anno ?? $annoCorrenteId); ?></strong>.
                </div>

                <h4>Nuovo dipartimento</h4>
                <form method="post" class="form-inline" style="margin-bottom:18px;">
                    <input type="hidden" name="action" value="save_department">
                    <input type="text" name="nome" class="form-control" placeholder="Nome dipartimento" required>
                    <?php if (dipAdminColumn('dipartimenti', 'sigla')): ?>
                        <input type="text" name="sigla" class="form-control" placeholder="Sigla">
                    <?php endif; ?>
                    <?php dipAdminRenderDocenteSelect('coordinatore_id', $docenti, 0, 'Coordinatore'); ?>
                    <?php if (dipAdminColumn('dipartimenti', 'attivo')): ?>
                        <label class="checkbox-inline"><input type="checkbox" name="attivo" value="1" checked> attivo</label>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success"><span class="glyphicon glyphicon-plus"></span> Aggiungi</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped dip-table">
                        <thead>
                        <tr>
                            <?php if (dipAdminColumn('dipartimenti', 'sigla')): ?><th class="text-center">Sigla</th><?php endif; ?>
                            <th class="text-center">Nome</th>
                            <th class="text-center">Coordinatore</th>
                            <?php if (dipAdminColumn('dipartimenti', 'attivo')): ?><th class="text-center">Attivo</th><?php endif; ?>
                            <th class="text-center">Azioni</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <form method="post">
                                    <input type="hidden" name="id" value="<?php echo intval($row['id']); ?>">
                                    <?php if (dipAdminColumn('dipartimenti', 'sigla')): ?>
                                        <td><input type="text" name="sigla" class="form-control input-sm" value="<?php echo dipAdminH($row['sigla'] ?? ''); ?>"></td>
                                    <?php endif; ?>
                                    <td><input type="text" name="nome" class="form-control input-sm" value="<?php echo dipAdminH($row['nome'] ?? ''); ?>" required></td>
                                    <td><?php dipAdminRenderDocenteSelect('coordinatore_id', $docenti, intval($row['coordinatore_id'] ?? 0)); ?></td>
                                    <?php if (dipAdminColumn('dipartimenti', 'attivo')): ?>
                                        <td class="text-center"><input type="checkbox" name="attivo" value="1" <?php echo dipAdminChecked($row['attivo'] ?? 0); ?>></td>
                                    <?php endif; ?>
                                    <td class="dip-actions">
                                        <button type="submit" name="action" value="save_department" class="btn btn-primary btn-xs"><span class="glyphicon glyphicon-floppy-disk"></span></button>
                                        <button type="submit" name="action" value="delete_department" class="btn btn-danger btn-xs" onclick="return confirm('Cancellare il dipartimento?');"><span class="glyphicon glyphicon-trash"></span></button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
<?php

function dipAdminRenderDocenteSelect(string $name, array $rows, int $selectedId, string $placeholder = '-'): void
{
    echo '<select name="' . dipAdminH($name) . '" class="form-control input-sm">';
    echo '<option value="0">' . dipAdminH($placeholder) . '</option>';
    foreach ($rows as $row) {
        $label = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
        echo '<option value="' . intval($row['id']) . '" ' . dipAdminSelected($selectedId, $row['id']) . '>' . dipAdminH($label) . '</option>';
    }
    echo '</select>';
}

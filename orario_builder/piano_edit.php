<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idPiano = ob_int($_GET['id_piano'] ?? 0);

$piano = dbGetFirst("
    SELECT *
    FROM orario_piano_orario
    WHERE id = $idPiano
    LIMIT 1
");

if (!$piano) {
    die('Piano non trovato');
}

$anni = ob_get_anni_scolastici();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica piano orario</title>
    <?php
    require_once __DIR__ . '/../common/header-common.php';
    require_once __DIR__ . '/../common/style.php';
    require_once __DIR__ . '/../common/_include_bootstrap-notify.php';
    require_once __DIR__ . '/../common/_include_bootstrap-select.php';
    ?>
</head>

<body>
<?php require_once __DIR__ . '/../common/header-admin.php'; ?>

<div class="container-fluid">

    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-pencil"></span>&ensp;
            Modifica piano orario
        </div>

        <div class="panel-body">

            <p>
                <a class="btn btn-default" href="piani_orario.php">
                    <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                </a>
            </p>

            <form method="post" action="piano_update.php">
                <input type="hidden" name="id_piano" value="<?php echo intval($piano['id']); ?>">

                <div class="form-group">
                    <label>Nome</label>
                    <input type="text"
                           name="nome"
                           class="form-control"
                           required
                           value="<?php echo ob_h($piano['nome']); ?>">
                </div>

                <div class="form-group">
                    <label>Anno scolastico</label>
                    <select name="id_anno_scolastico" class="form-control selectpicker" data-live-search="true" required>
                        <?php foreach ($anni as $a): ?>
                            <option value="<?php echo intval($a['id']); ?>"
                                <?php echo intval($a['id']) === intval($piano['id_anno_scolastico']) ? 'selected' : ''; ?>>
                                <?php echo ob_h(ob_anno_label($a)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Anno classe</label>
                    <select name="anno_classe" class="form-control">
                        <option value="">Non specificato</option>
                        <option value="1" <?php echo intval($piano['anno_classe']) === 1 ? 'selected' : ''; ?>>Prima</option>
                        <option value="2" <?php echo intval($piano['anno_classe']) === 2 ? 'selected' : ''; ?>>Seconda</option>
                        <option value="3" <?php echo intval($piano['anno_classe']) === 3 ? 'selected' : ''; ?>>Terza</option>
                        <option value="4" <?php echo intval($piano['anno_classe']) === 4 ? 'selected' : ''; ?>>Quarta</option>
                        <option value="5" <?php echo intval($piano['anno_classe']) === 5 ? 'selected' : ''; ?>>Quinta</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Descrizione</label>
                    <textarea name="descrizione"
                              class="form-control"
                              rows="4"><?php echo ob_h($piano['descrizione']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Attivo</label>
                    <select name="attivo" class="form-control">
                        <option value="1" <?php echo intval($piano['attivo']) === 1 ? 'selected' : ''; ?>>Sì</option>
                        <option value="0" <?php echo intval($piano['attivo']) === 0 ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-success">
                    <span class="glyphicon glyphicon-floppy-disk"></span>
                    Salva modifiche
                </button>
            </form>

        </div>
    </div>

</div>

<script>
if (typeof jQuery !== 'undefined' && typeof jQuery.fn.selectpicker === 'function') {
    jQuery('.selectpicker').selectpicker();
}
</script>

</body>
</html>
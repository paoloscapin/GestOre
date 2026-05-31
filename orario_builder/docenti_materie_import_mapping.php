<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idScenario = ob_int($_GET['id_scenario'] ?? 0);
$idImport = ob_int($_GET['id_import'] ?? 0);

$classi = ob_get_classi_attive();

$mancanti = $_SESSION['orario_import_classi_mancanti'] ?? [];

if (empty($mancanti)) {
    ob_redirect("docenti_materie.php?id_scenario=$idScenario");
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Mapping classi import CSV</title>
    <?php
    require_once __DIR__ . '/../common/header-common.php';
    require_once __DIR__ . '/../common/style.php';
    require_once __DIR__ . '/../common/_include_bootstrap-select.php';
    ?>
</head>
<body>
<?php require_once __DIR__ . '/../common/header-admin.php'; ?>

<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading">
            Abbina classi CSV
        </div>

        <div class="panel-body">
            <div class="alert alert-warning">
                Alcune classi presenti nel CSV non esistono con lo stesso nome in GestOre.
                Abbinale manualmente: l’associazione verrà salvata per i prossimi import.
            </div>

            <form method="post" action="docenti_materie_import_mapping_save.php">
                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                <input type="hidden" name="id_import" value="<?php echo intval($idImport); ?>">

                <table class="table table-striped table-bordered">
                    <thead>
                    <tr>
                        <th>Classe CSV</th>
                        <th>Classe GestOre</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mancanti as $alias): ?>
                        <tr>
                            <td>
                                <strong><?php echo ob_h($alias); ?></strong>
                                <input type="hidden" name="alias_classe[]" value="<?php echo ob_h($alias); ?>">
                            </td>
                            <td>
                                <select name="id_classe[]" class="form-control selectpicker" data-live-search="true" required>
                                    <option value="">Seleziona classe...</option>
                                    <?php foreach ($classi as $c): ?>
                                        <option value="<?php echo intval($c['id']); ?>">
                                            <?php echo ob_h($c['classe']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <button class="btn btn-success">
                    Salva abbinamenti e continua import
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
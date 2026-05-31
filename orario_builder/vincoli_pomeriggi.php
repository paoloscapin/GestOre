<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idScenario = ob_int($_GET['id_scenario'] ?? 0);
$scenario = ob_get_scenario($idScenario);

if (!$scenario) {
    die('Scenario non trovato');
}

$giorni = [
    1 => 'Lunedì',
    2 => 'Martedì',
    3 => 'Mercoledì',
    4 => 'Giovedì',
    5 => 'Venerdì',
    6 => 'Sabato'
];

$vincoliGruppo = dbGetAll("
    SELECT *
    FROM orario_vincolo_pomeriggi_gruppo
    WHERE id_scenario = $idScenario
      AND attivo = 1
    ORDER BY id
") ?: [];

$bilanciamenti = dbGetAll("
    SELECT *
    FROM orario_vincolo_bilanciamento_pomeriggi
    WHERE id_scenario = $idScenario
      AND attivo = 1
    ORDER BY id
") ?: [];

function checkedCsv($csv, $value) {
    $arr = array_filter(array_map('trim', explode(',', (string)$csv)));
    return in_array((string)$value, $arr, true) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Vincoli pomeriggi</title>
    <?php
    require_once __DIR__ . '/../common/header-common.php';
    require_once __DIR__ . '/../common/style.php';
    require_once __DIR__ . '/../common/_include_bootstrap-notify.php';
    ?>
</head>

<body>
<?php require_once __DIR__ . '/../common/header-admin.php'; ?>

<div class="container-fluid">

    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-calendar"></span>&ensp;
            Vincoli pomeriggi - <?php echo ob_h($scenario['nome']); ?>
        </div>

        <div class="panel-body">

            <p>
                <a class="btn btn-default" href="scenari.php">
                    <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                </a>

                <a class="btn btn-info" href="classe_slot_vincoli.php?id_scenario=<?php echo intval($idScenario); ?>">
                    <span class="glyphicon glyphicon-th"></span>&ensp;Vincoli classi su griglia
                </a>
            </p>

            <div class="alert alert-info">
                Qui configuri le regole sui pomeriggi. I giorni usano questa codifica:
                1=Lunedì, 2=Martedì, 3=Mercoledì, 4=Giovedì, 5=Venerdì, 6=Sabato.
            </div>

            <h4>Nuovo vincolo per gruppo di classi</h4>

            <form method="post" action="vincoli_pomeriggi_save.php">
                <input type="hidden" name="azione" value="salva_gruppo">
                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                <div class="row">
                    <div class="col-md-4">
                        <label>Nome vincolo</label>
                        <input type="text" name="nome" class="form-control" required
                               placeholder="Prime - mercoledì fisso + altro pomeriggio">
                    </div>

                    <div class="col-md-2">
                        <label>Anni classe</label>
                        <input type="text" name="filtro_anno_classe" class="form-control" required
                               placeholder="1 oppure 3,4,5">
                    </div>

                    <div class="col-md-2">
                        <label>Pomeriggi settimanali</label>
                        <input type="number" name="pomeriggi_settimanali" class="form-control" value="2" min="0" max="6">
                    </div>

                    <div class="col-md-4">
                        <label>Distribuzione</label>
                        <select name="distribuzione" class="form-control">
                            <option value="UNIFORME">Uniforme</option>
                            <option value="VINCOLATA_UNIFORME">Vincolata + uniforme</option>
                        </select>
                    </div>
                </div>

                <br>

                <div class="row">
                    <div class="col-md-6">
                        <label>Giorni ammessi</label><br>
                        <?php foreach ($giorni as $k => $g): ?>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="giorni_ammessi[]" value="<?php echo intval($k); ?>" checked>
                                <?php echo ob_h($g); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="col-md-6">
                        <label>Giorni obbligatori</label><br>
                        <?php foreach ($giorni as $k => $g): ?>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="giorni_obbligatori[]" value="<?php echo intval($k); ?>">
                                <?php echo ob_h($g); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <br>

                <div class="form-group">
                    <label>Note</label>
                    <textarea name="note" class="form-control" rows="2"></textarea>
                </div>

                <button class="btn btn-primary">
                    <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi vincolo
                </button>
            </form>

            <hr>

            <h4>Vincoli gruppo configurati</h4>

            <table class="table table-striped table-bordered table-hover">
                <thead>
                <tr>
                    <th>Nome</th>
                    <th>Anni</th>
                    <th>Pomeriggi</th>
                    <th>Giorni ammessi</th>
                    <th>Giorni obbligatori</th>
                    <th>Distribuzione</th>
                    <th style="width:170px;">Azioni</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($vincoliGruppo as $v): ?>
                    <tr>
                        <form method="post" action="vincoli_pomeriggi_save.php">
                            <input type="hidden" name="azione" value="salva_gruppo">
                            <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                            <input type="hidden" name="id" value="<?php echo intval($v['id']); ?>">

                            <td><input type="text" name="nome" class="form-control input-sm" value="<?php echo ob_h($v['nome']); ?>"></td>
                            <td><input type="text" name="filtro_anno_classe" class="form-control input-sm" value="<?php echo ob_h($v['filtro_anno_classe']); ?>"></td>
                            <td><input type="number" name="pomeriggi_settimanali" class="form-control input-sm" value="<?php echo intval($v['pomeriggi_settimanali']); ?>"></td>

                            <td>
                                <?php foreach ($giorni as $k => $g): ?>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="giorni_ammessi[]" value="<?php echo intval($k); ?>" <?php echo checkedCsv($v['giorni_ammessi'], $k); ?>>
                                        <?php echo substr($g, 0, 3); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>

                            <td>
                                <?php foreach ($giorni as $k => $g): ?>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="giorni_obbligatori[]" value="<?php echo intval($k); ?>" <?php echo checkedCsv($v['giorni_obbligatori'], $k); ?>>
                                        <?php echo substr($g, 0, 3); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>

                            <td>
                                <select name="distribuzione" class="form-control input-sm">
                                    <option value="UNIFORME" <?php echo $v['distribuzione'] === 'UNIFORME' ? 'selected' : ''; ?>>Uniforme</option>
                                    <option value="VINCOLATA_UNIFORME" <?php echo $v['distribuzione'] === 'VINCOLATA_UNIFORME' ? 'selected' : ''; ?>>Vincolata + uniforme</option>
                                </select>
                            </td>

                            <td>
                                <button class="btn btn-xs btn-success">
                                    <span class="glyphicon glyphicon-floppy-disk"></span>
                                    Salva
                                </button>

                                <button class="btn btn-xs btn-danger"
                                        name="azione"
                                        value="elimina_gruppo"
                                        onclick="return confirm('Eliminare questo vincolo?');">
                                    <span class="glyphicon glyphicon-trash"></span>
                                    Elimina
                                </button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($vincoliGruppo)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Nessun vincolo gruppo configurato.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <hr>

            <h4>Bilanciamento globale pomeriggi</h4>

            <form method="post" action="vincoli_pomeriggi_save.php">
                <input type="hidden" name="azione" value="salva_bilanciamento">
                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                <div class="row">
                    <div class="col-md-3">
                        <label>Nome</label>
                        <input type="text" name="nome" class="form-control" value="Distribuzione uniforme pomeriggi classi">
                    </div>

                    <div class="col-md-3">
                        <label>Giorni da bilanciare</label><br>
                        <?php foreach ($giorni as $k => $g): ?>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="giorni_da_bilanciare[]" value="<?php echo intval($k); ?>" <?php echo $k <= 5 ? 'checked' : ''; ?>>
                                <?php echo substr($g, 0, 3); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="col-md-2">
                        <label>Livello</label>
                        <select name="livello" class="form-control">
                            <option value="MORBIDO">Morbido</option>
                            <option value="RIGIDO">Rigido</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label>Peso</label>
                        <input type="number" name="peso" class="form-control" value="100">
                    </div>

                    <div class="col-md-2">
                        <label>Scarto massimo</label>
                        <input type="number" name="scarto_massimo" class="form-control" value="1" min="0">
                    </div>
                </div>

                <br>

                <button class="btn btn-warning">
                    <span class="glyphicon glyphicon-scale"></span>&ensp;Aggiungi bilanciamento
                </button>
            </form>

            <br>

            <table class="table table-striped table-bordered table-hover">
                <thead>
                <tr>
                    <th>Nome</th>
                    <th>Giorni</th>
                    <th>Livello</th>
                    <th>Peso</th>
                    <th>Scarto max</th>
                    <th style="width:170px;">Azioni</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($bilanciamenti as $b): ?>
                    <tr>
                        <form method="post" action="vincoli_pomeriggi_save.php">
                            <input type="hidden" name="azione" value="salva_bilanciamento">
                            <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                            <input type="hidden" name="id" value="<?php echo intval($b['id']); ?>">

                            <td><input type="text" name="nome" class="form-control input-sm" value="<?php echo ob_h($b['nome']); ?>"></td>

                            <td>
                                <?php foreach ($giorni as $k => $g): ?>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="giorni_da_bilanciare[]" value="<?php echo intval($k); ?>" <?php echo checkedCsv($b['giorni_da_bilanciare'], $k); ?>>
                                        <?php echo substr($g, 0, 3); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>

                            <td>
                                <select name="livello" class="form-control input-sm">
                                    <option value="MORBIDO" <?php echo $b['livello'] === 'MORBIDO' ? 'selected' : ''; ?>>Morbido</option>
                                    <option value="RIGIDO" <?php echo $b['livello'] === 'RIGIDO' ? 'selected' : ''; ?>>Rigido</option>
                                </select>
                            </td>

                            <td><input type="number" name="peso" class="form-control input-sm" value="<?php echo intval($b['peso']); ?>"></td>

                            <td><input type="number" name="scarto_massimo" class="form-control input-sm" value="<?php echo ob_h($b['scarto_massimo']); ?>"></td>

                            <td>
                                <button class="btn btn-xs btn-success">
                                    <span class="glyphicon glyphicon-floppy-disk"></span>
                                    Salva
                                </button>

                                <button class="btn btn-xs btn-danger"
                                        name="azione"
                                        value="elimina_bilanciamento"
                                        onclick="return confirm('Eliminare questo bilanciamento?');">
                                    <span class="glyphicon glyphicon-trash"></span>
                                    Elimina
                                </button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($bilanciamenti)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">Nessun bilanciamento configurato.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

        </div>
    </div>

</div>

</body>
</html>
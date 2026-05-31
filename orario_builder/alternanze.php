<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idScenario = ob_int($_GET['id_scenario'] ?? 0);

$scenari = ob_get_scenari();
$classi = ob_get_classi_attive();
$materie = ob_get_materie();

$scenarioWhere = $idScenario > 0 ? "WHERE g.id_scenario = $idScenario" : "";

$gruppi = dbGetAll("
    SELECT g.*, s.nome AS scenario_nome
    FROM orario_alternanza_gruppo g
    LEFT JOIN orario_scenario s ON s.id = g.id_scenario
    $scenarioWhere
    ORDER BY g.id DESC
") ?: [];

$righeByGruppo = [];

foreach ($gruppi as $g) {
    $idGruppo = intval($g['id']);

    $righeByGruppo[$idGruppo] = dbGetAll("
        SELECT
            r.*,
            c.classe,
            m1.nome AS materia_periodo_1,
            m2.nome AS materia_periodo_2
        FROM orario_alternanza_riga r
        JOIN classi c ON c.id = r.id_classe
        JOIN materia m1 ON m1.id = r.id_materia_periodo_1
        JOIN materia m2 ON m2.id = r.id_materia_periodo_2
        WHERE r.id_gruppo = $idGruppo
        ORDER BY c.classe
    ") ?: [];
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Alternanze materie</title>
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
                <span class="glyphicon glyphicon-transfer"></span>&ensp;
                Alternanze materie tra periodi
            </div>

            <div class="panel-body">

                <p>
                    <a class="btn btn-default" href="<?php echo $idScenario > 0 ? 'scenari.php' : 'index.php'; ?>">
                        <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                    </a>
                </p>

                <div class="alert alert-info">
                    Qui configuri i casi in cui una classe fa una materia nel primo periodo e un’altra nel secondo periodo.
                    Il motore dovrà poi imporre lo stesso slot orario per consentire lo scambio senza cambiare orario alle classi.
                </div>

                <h4>Nuovo gruppo alternanza</h4>

                <form method="post" action="alternanza_save.php">
                    <input type="hidden" name="azione" value="crea_gruppo">

                    <div class="row">
                        <div class="col-md-3">
                            <label>Scenario</label>
                            <select name="id_scenario" class="form-control selectpicker" data-live-search="true" required>
                                <?php foreach ($scenari as $s): ?>
                                    <option value="<?php echo intval($s['id']); ?>"
                                        <?php echo intval($s['id']) === $idScenario ? 'selected' : ''; ?>>
                                        <?php echo ob_h($s['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>Nome gruppo</label>
                            <input type="text"
                                name="nome"
                                class="form-control"
                                required
                                placeholder="Esempio: Biologia/Tedesco biennio B-O">
                        </div>

                        <div class="col-md-5">
                            <label>Descrizione</label>
                            <input type="text"
                                name="descrizione"
                                class="form-control"
                                placeholder="Esempio: 1B/1O e 2B/2O si scambiano a metà anno">
                        </div>
                    </div>

                    <br>

                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-plus"></span>&ensp;Crea gruppo
                    </button>
                </form>

                <hr>

                <?php foreach ($gruppi as $g): ?>
                    <?php $idGruppo = intval($g['id']); ?>

                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong><?php echo ob_h($g['nome']); ?></strong>
                            <?php if (!empty($g['scenario_nome'])): ?>
                                <span class="text-muted">
                                    — <?php echo ob_h($g['scenario_nome']); ?>
                                </span>
                            <?php endif; ?>
                            <form method="post" action="alternanza_save.php" style="margin-top:10px;">
                                <input type="hidden" name="azione" value="salva_gruppo">
                                <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($g['id_scenario']); ?>">

                                <div class="row">
                                    <div class="col-md-4">
                                        <input type="text"
                                            name="nome"
                                            class="form-control input-sm"
                                            value="<?php echo ob_h($g['nome']); ?>"
                                            required>
                                    </div>

                                    <div class="col-md-6">
                                        <input type="text"
                                            name="descrizione"
                                            class="form-control input-sm"
                                            value="<?php echo ob_h($g['descrizione']); ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-xs btn-success">
                                            <span class="glyphicon glyphicon-floppy-disk"></span>
                                            Salva gruppo
                                        </button>
                                    </div>
                                </div>
                            </form>
                            <form method="post"
                                action="alternanza_save.php"
                                style="display:inline; float:right;"
                                onsubmit="return confirm('Eliminare tutto il gruppo alternanza?');">
                                <input type="hidden" name="azione" value="elimina_gruppo">
                                <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($g['id_scenario']); ?>">
                                <button type="submit" class="btn btn-xs btn-danger">
                                    <span class="glyphicon glyphicon-trash"></span>
                                    Elimina gruppo
                                </button>
                            </form>
                        </div>

                        <div class="panel-body">

                            <?php if (!empty($g['descrizione'])): ?>
                                <p class="text-muted"><?php echo ob_h($g['descrizione']); ?></p>
                            <?php endif; ?>

                            <h5>Aggiungi riga</h5>

                            <form method="post" action="alternanza_save.php">
                                <input type="hidden" name="azione" value="salva_riga">
                                <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($g['id_scenario']); ?>">

                                <div class="row">
                                    <div class="col-md-3">
                                        <label>Classe</label>
                                        <select name="id_classe" class="form-control selectpicker" data-live-search="true" required>
                                            <?php foreach ($classi as $c): ?>
                                                <option value="<?php echo intval($c['id']); ?>">
                                                    <?php echo ob_h($c['classe']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label>Materia primo periodo</label>
                                        <select name="id_materia_periodo_1" class="form-control selectpicker" data-live-search="true" required>
                                            <?php foreach ($materie as $m): ?>
                                                <option value="<?php echo intval($m['id']); ?>">
                                                    <?php echo ob_h($m['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label>Materia secondo periodo</label>
                                        <select name="id_materia_periodo_2" class="form-control selectpicker" data-live-search="true" required>
                                            <?php foreach ($materie as $m): ?>
                                                <option value="<?php echo intval($m['id']); ?>">
                                                    <?php echo ob_h($m['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-1">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-success btn-block">
                                            <span class="glyphicon glyphicon-plus"></span>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <br>

                            <table class="table table-striped table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Classe</th>
                                        <th>Primo periodo</th>
                                        <th>Secondo periodo</th>
                                        <th style="width:170px;">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($righeByGruppo[$idGruppo] as $r): ?>
                                        <tr>
                                            <form method="post" action="alternanza_save.php">
                                                <input type="hidden" name="azione" value="salva_riga">
                                                <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">
                                                <input type="hidden" name="id_riga" value="<?php echo intval($r['id']); ?>">
                                                <input type="hidden" name="id_scenario" value="<?php echo intval($g['id_scenario']); ?>">

                                                <td>
                                                    <select name="id_classe" class="form-control input-sm">
                                                        <?php foreach ($classi as $c): ?>
                                                            <option value="<?php echo intval($c['id']); ?>"
                                                                <?php echo intval($c['id']) === intval($r['id_classe']) ? 'selected' : ''; ?>>
                                                                <?php echo ob_h($c['classe']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>

                                                <td>
                                                    <select name="id_materia_periodo_1" class="form-control input-sm">
                                                        <?php foreach ($materie as $m): ?>
                                                            <option value="<?php echo intval($m['id']); ?>"
                                                                <?php echo intval($m['id']) === intval($r['id_materia_periodo_1']) ? 'selected' : ''; ?>>
                                                                <?php echo ob_h($m['nome']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>

                                                <td>
                                                    <select name="id_materia_periodo_2" class="form-control input-sm">
                                                        <?php foreach ($materie as $m): ?>
                                                            <option value="<?php echo intval($m['id']); ?>"
                                                                <?php echo intval($m['id']) === intval($r['id_materia_periodo_2']) ? 'selected' : ''; ?>>
                                                                <?php echo ob_h($m['nome']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>

                                                <td>
                                                    <button type="submit" class="btn btn-xs btn-success">
                                                        <span class="glyphicon glyphicon-floppy-disk"></span>
                                                        Salva
                                                    </button>

                                                    <button type="submit"
                                                        name="azione"
                                                        value="elimina_riga"
                                                        class="btn btn-xs btn-danger"
                                                        onclick="return confirm('Eliminare questa riga?');">
                                                        <span class="glyphicon glyphicon-trash"></span>
                                                        Elimina
                                                    </button>
                                                </td>
                                            </form>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($righeByGruppo[$idGruppo])): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                Nessuna riga configurata.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($gruppi)): ?>
                    <div class="alert alert-warning">
                        Nessun gruppo alternanza configurato.
                    </div>
                <?php endif; ?>

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
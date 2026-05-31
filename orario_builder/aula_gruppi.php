<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$gruppi = dbGetAll("
    SELECT *
    FROM orario_aula_gruppo
    WHERE attivo = 1
    ORDER BY ordine, nome
") ?: [];

$aule = dbGetAll("
    SELECT *
    FROM aule
    WHERE attiva = 1
    ORDER BY tipo, codice
") ?: [];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gruppi aule</title>
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
            <span class="glyphicon glyphicon-blackboard"></span>&ensp;Gruppi aule
        </div>

        <div class="panel-body">

            <p>
                <a class="btn btn-default" href="index.php">
                    <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                </a>
            </p>

            <form method="post" action="aula_gruppi_save.php">
                <input type="hidden" name="azione" value="crea_gruppo">

                <div class="row">
                    <div class="col-md-4">
                        <label>Nome gruppo</label>
                        <input type="text" name="nome" class="form-control" required placeholder="Es. Palestre">
                    </div>

                    <div class="col-md-6">
                        <label>Descrizione</label>
                        <input type="text" name="descrizione" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <label>Ordine</label>
                        <input type="number" name="ordine" class="form-control" value="100">
                    </div>
                </div>

                <br>

                <button class="btn btn-primary">
                    <span class="glyphicon glyphicon-plus"></span>&ensp;Crea gruppo
                </button>
            </form>

            <hr>

            <?php foreach ($gruppi as $g): ?>
                <?php
                $idGruppo = intval($g['id']);
                $auleGruppo = dbGetAll("
                    SELECT aga.id, a.codice, a.nome, a.tipo, a.piano, a.ala
                    FROM orario_aula_gruppo_aula aga
                    JOIN aule a ON a.id = aga.id_aula
                    WHERE aga.id_gruppo = $idGruppo
                    ORDER BY a.codice
                ") ?: [];
                ?>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <strong><?php echo ob_h($g['nome']); ?></strong>
                    </div>

                    <div class="panel-body">
                        <form method="post" action="aula_gruppi_save.php">
                            <input type="hidden" name="azione" value="salva_gruppo">
                            <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">

                            <div class="row">
                                <div class="col-md-4">
                                    <label>Nome</label>
                                    <input type="text" name="nome" class="form-control" value="<?php echo ob_h($g['nome']); ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label>Descrizione</label>
                                    <input type="text" name="descrizione" class="form-control" value="<?php echo ob_h($g['descrizione']); ?>">
                                </div>

                                <div class="col-md-2">
                                    <label>Ordine</label>
                                    <input type="number" name="ordine" class="form-control" value="<?php echo intval($g['ordine']); ?>">
                                </div>
                            </div>

                            <br>

                            <button class="btn btn-success btn-sm">
                                <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva gruppo
                            </button>

                            <button class="btn btn-danger btn-sm"
                                    name="azione"
                                    value="elimina_gruppo"
                                    onclick="return confirm('Eliminare questo gruppo?');">
                                <span class="glyphicon glyphicon-trash"></span>&ensp;Elimina
                            </button>
                        </form>

                        <hr>

                        <form method="post" action="aula_gruppi_save.php">
                            <input type="hidden" name="azione" value="aggiungi_aula">
                            <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">

                            <div class="row">
                                <div class="col-md-10">
                                    <label>Aggiungi aula</label>
                                    <select name="id_aula" class="form-control selectpicker" data-live-search="true" required>
                                        <?php foreach ($aule as $a): ?>
                                            <option value="<?php echo intval($a['id']); ?>">
                                                <?php echo ob_h($a['codice'] . ' - ' . $a['nome'] . ' [' . $a['tipo'] . ']'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label>&nbsp;</label>
                                    <button class="btn btn-primary btn-block">
                                        <span class="glyphicon glyphicon-plus"></span>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <br>

                        <table class="table table-striped table-bordered">
                            <thead>
                            <tr>
                                <th>Codice</th>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Piano</th>
                                <th>Ala</th>
                                <th style="width:100px;">Azioni</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($auleGruppo as $ag): ?>
                                <tr>
                                    <td><?php echo ob_h($ag['codice']); ?></td>
                                    <td><?php echo ob_h($ag['nome']); ?></td>
                                    <td><?php echo ob_h($ag['tipo']); ?></td>
                                    <td><?php echo ob_h($ag['piano']); ?></td>
                                    <td><?php echo ob_h($ag['ala']); ?></td>
                                    <td>
                                        <form method="post" action="aula_gruppi_save.php">
                                            <input type="hidden" name="azione" value="rimuovi_aula">
                                            <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">
                                            <input type="hidden" name="id_riga" value="<?php echo intval($ag['id']); ?>">
                                            <button class="btn btn-xs btn-danger">
                                                <span class="glyphicon glyphicon-remove"></span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($auleGruppo)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Nessuna aula nel gruppo.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>

                    </div>
                </div>
            <?php endforeach; ?>

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